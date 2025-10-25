<script lang="ts">
  import { onMount, onDestroy } from "svelte";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { GeneralStore } from "$lib/stores/General.svelte";
  import type { CardJob } from "@/types";
  import { isMobile } from "$lib/utils/elements.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { navigateTo } from "@/app/lib/stores/Route.svelte";
  import { fade } from "svelte/transition";

  interface Props {
    open: boolean;
  }

  let { open = $bindable() }: Props = $props();

  let isMobileValue = $derived.by(() => isMobile());

  let modalEl: HTMLDialogElement;
  let deleteConfirmModal: HTMLDialogElement;
  let modalBox: HTMLElement;
  let dragHandle = $state<HTMLElement | undefined>();

  // Dragging state
  let translateX = $state(0);
  let translateY = $state(0);
  let isDragging = $state(false);
  let activePointerId: number | null = null;
  let startClientY = 0;
  let startHeight = 0;

  let modalStyle = $derived(
    `transform: translate(${translateX}px, ${translateY}px); transition: ${isDragging ? "none" : "transform 180ms ease"}; touch-action: ${isMobileValue ? "none" : "auto"};`
  );

  function clamp(n: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, n));
  }

  function startDrag(e: PointerEvent): void {
    if (e.button && e.button !== 0) return;
    if (!modalBox || !dragHandle) return;
    if (!dragHandle.contains(e.target as Node)) return;
    try {
      dragHandle.setPointerCapture(e.pointerId);
    } catch {
      // ignore
    }
    activePointerId = e.pointerId;
    isDragging = true;
    startClientY = e.clientY;
    if (isMobile()) {
      startHeight = modalBox?.clientHeight || 0;
    }
    window.addEventListener("pointermove", onPointerMove);
    window.addEventListener("pointerup", onPointerUp);
    e.preventDefault();
  }

  function onPointerMove(e: PointerEvent): void {
    if (!isDragging) return;
    if (activePointerId !== null && e.pointerId !== activePointerId) return;
    if (!modalBox) return;
    if (isMobile()) {
      const vh = window.innerHeight;
      const minH = Math.round(vh * 0.25);
      const maxH = Math.round(vh * 0.95);
      const dy = e.clientY - startClientY;
      let newH = startHeight - dy;
      newH = clamp(newH, minH, maxH);
      translateX = 0;
      translateY = 0;
      try {
        modalBox.style.setProperty("height", `${newH}px`, "important");
      } catch {
        modalBox.style.height = `${newH}px`;
      }
      return;
    }
  }

  function onPointerUp(e: PointerEvent): void {
    if (!isDragging) return;
    try {
      if (typeof e.pointerId !== "undefined")
        dragHandle?.releasePointerCapture(e.pointerId);
    } catch (err) {
      void err;
    }
    isDragging = false;
    activePointerId = null;
    window.removeEventListener("pointermove", onPointerMove);
    window.removeEventListener("pointerup", onPointerUp);
    if (isMobile()) {
      const releaseDy = e.clientY - startClientY;
      if (releaseDy > 150) {
        closeModal();
        if (modalBox) modalBox.style.removeProperty("height");
      }
    }
  }

  function resetPosition(): void {
    translateX = 0;
    translateY = 0;
    try {
      if (modalBox) modalBox.style.removeProperty("height");
    } catch {
      // ignore
    }
  }

  // Local state
  // loading mirrors the central store isSyncing to ensure UI reflects store activity
  let loading = $state(false);
  let error = $state("");
  let showCopySuccess = $state(false);
  let isOffline = $state(false);
  let showDeleteConfirm = $state(false);
  let removingIds = $state(new Set<number>());

  const STALE_THRESHOLD = 5 * 60 * 1000;

  // Store bindings
  let savedJobs = $derived(bookmarkStore.jobs);
  let warning = $derived(bookmarkStore.warning);
  let deletedJobs = $derived(bookmarkStore.deletedJobs);
  let lastSyncTime = $derived(bookmarkStore.lastSyncTime);

  let displayedSavedJobs = $derived(
    savedJobs.map((job) => ({
      ...job,
      timeAgo: GeneralStore.useTimeAgo(job.post_time)(),
      deadlineInfo: job.deadline
        ? GeneralStore.useDeadline(job.deadline)
        : { text: "", style: "" },
      statusInfo: job.statusjob
        ? GeneralStore.useStatusJob(Number(job.statusjob))
        : { label: "", color: "" },
    }))
  );

  let formattedLastSync = $derived(() => {
    const val = lastSyncTime;
    const n = Number(val);
    if (!n || Number.isNaN(n)) return "";
    try {
      return new Date(n).toLocaleString("en-GB", {
        year: "numeric",
        month: "numeric",
        day: "numeric",
        hour: "numeric",
        minute: "numeric",
        hour12: true,
      });
    } catch {
      return "";
    }
  });

  async function fetchJobs(forceRefresh = false): Promise<void> {
    if (!forceRefresh && savedJobs.length === 0) {
      return;
    }
    const now = Date.now();
    const isStale = now - lastSyncTime > STALE_THRESHOLD;
    if (!forceRefresh && !isStale && savedJobs.length > 0) return;
    // do not start a new sync if one is already in progress
    if (loading) return;
    loading = true;
    error = "";
    isOffline = false;
    try {
      await bookmarkStore.syncWithAPI();
    } catch {
      error = "Gagal memuat data. Silakan coba lagi.";
      isOffline = !navigator.onLine;
    } finally {
      loading = false;
    }
  }

  function handleRefresh(): Promise<void> {
    return fetchJobs(true);
  }

  function handleDeleteAll(): void {
    showDeleteConfirm = true;
  }

  async function confirmDeleteAll(): Promise<void> {
    showDeleteConfirm = false;
    savedJobs.forEach((job) => {
      if (job.id) removingIds.add(job.id);
    });
    await new Promise((resolve) => setTimeout(resolve, 300));
    loading = true;
    try {
      await bookmarkStore.clearAll();
      removingIds.clear();
    } catch {
      error = "Gagal menghapus semua bookmark. Silakan coba lagi.";
      removingIds.clear();
    } finally {
      loading = false;
    }
  }

  function cancelDeleteAll(): void {
    showDeleteConfirm = false;
  }

  async function removeBookmark(id: number): Promise<void> {
    removingIds.add(id);
    await new Promise((resolve) => setTimeout(resolve, 300));
    await bookmarkStore.removeJob(id);
    removingIds.delete(id);
  }

  function handleClearDeleted(): void {
    bookmarkStore.clearDeleted();
  }

  function closeModal(): void {
    open = false;
  }

  async function handleJobClick(job: CardJob): Promise<void> {
    // Close modal first
    closeModal();

    // Navigate to job detail page
    if (job.permalink) {
      const url = new URL(job.permalink, window.location.origin);
      await navigateTo(url.pathname + url.search + url.hash);
    }
  }

  function handleKeydown(e: KeyboardEvent): void {
    if (e.key === "Escape" && open) closeModal();
  }

  $effect(() => {
    loading = bookmarkStore.isSyncing;
  });

  $effect(() => {
    if (open) {
      bookmarkStore.flushSync();
      fetchJobs();
      modalEl?.showModal();
      if (isMobileValue && modalBox) {
        const vh = window.innerHeight;
        const initialHeight = Math.round(vh * 0.6);
        modalBox.style.setProperty("height", `${initialHeight}px`, "important");
      }
    } else {
      modalEl?.close();
      resetPosition();
    }
  });

  $effect(() => {
    if (showDeleteConfirm) {
      deleteConfirmModal?.showModal();
    } else {
      deleteConfirmModal?.close();
    }
  });

  onMount(() => {
    document.addEventListener("keydown", handleKeydown);
  });

  onDestroy(() => {
    document.removeEventListener("keydown", handleKeydown);
    window.removeEventListener("pointermove", onPointerMove);
    window.removeEventListener("pointerup", onPointerUp);
    try {
      if (dragHandle && activePointerId !== null)
        dragHandle.releasePointerCapture(activePointerId);
    } catch (e) {
      void e;
    }
    resetPosition();
  });
</script>

<dialog
  bind:this={modalEl}
  class="modal modal-bottom sm:modal-middle"
  class:modal-open={open}
>
  <div
    bind:this={modalBox}
    class="modal-box p-0 flex flex-col relative max-h-[80vh] rounded-t-xl overflow-hidden md:mx-auto md:!max-w-2xl md:z-60 md:rounded-b-xl"
    class:mobile-sheet={isMobileValue}
    style={modalStyle}
    onpointerdown={startDrag}
  >
    <!-- Drag Handle -->
    {#if isMobileValue}
      <div
        bind:this={dragHandle}
        class="drag-handle !w-12 !h-2 bg-base-content/20 rounded-full mx-auto mt-3 mb-2 cursor-grab active:cursor-grabbing touch-none select-none transition-colors duration-200 hover:bg-base-content/30 active:bg-base-content/40 md:bg-base-content/15 md:hover:bg-base-content/25 md:active:bg-base-content/35"
        onpointerdown={startDrag}
        aria-label="Drag to resize modal"
        role="button"
        tabindex="0"
      ></div>
    {/if}

    <!-- Header -->
    <div
      class="sticky top-0 z-10 border-b border-base-300 px-6 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between"
    >
      <div class="w-full">
        <div class="flex items-center justify-between w-full">
          <h3 class="font-bold text-lg flex items-center gap-2">
            <svg
              class="h-5 w-5 text-[var(--wpl-global-color-1)]"
              viewBox="0 0 24 24"
              fill="currentColor"
              aria-hidden="true"
              focusable="false"
            >
              <path d="M6 2a2 2 0 00-2 2v17l8-4 8 4V4a2 2 0 00-2-2H6z" />
            </svg>
            Lowongan Tersimpan
            {#if !loading && savedJobs.length > 0}
              <span
                class="bg-[var(--wpl-global-color-1)] text-sm rounded-full px-2 py-0.1 z-10"
                >{savedJobs.length}</span
              >
            {/if}
          </h3>

          <button
            onclick={closeModal}
            class="btn btn-xs btn-info rounded-full w-auto whitespace-nowrap"
            aria-label="Tutup dialog"
            title="close modal"
          >
            <svg
              class="h-4 w-4"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
              focusable="false"
            >
              <path d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Second row: action buttons placed under the header -->
        <div
          class="flex flex-row flex-wrap items-center gap-2 mt-3 w-full justify-end"
        >
          {#if !loading && savedJobs.length > 0}
            <button
              onclick={handleDeleteAll}
              disabled={loading}
              class="btn btn-ghost btn-sm md:btn-md text-error w-auto whitespace-nowrap"
              aria-label="hapus semua"
              title="hapus semua"
            >
              <svg
                class="h-4 w-4 mr-2"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false"
              >
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                <path d="M10 11v6" />
                <path d="M14 11v6" />
              </svg>
              Hapus Semua
            </button>
            <button
              onclick={handleRefresh}
              disabled={loading}
              class="btn btn-ghost btn-sm md:btn-md w-auto whitespace-nowrap"
              aria-label="sync ke server"
              title="sync ke server"
            >
              <RefreshSpinner size="h-4 w-4 mr-2" spin={loading} />
              Sync/Refresh
            </button>
          {/if}
        </div>
      </div>
    </div>

    <!-- Content -->
    <div class="flex-1 overflow-y-auto max-h-full px-6 py-4">
      <!-- Loading State -->
      {#if loading}
        <div class="flex items-center justify-center py-12">
          <LoadingSpinner srLabel="Memuat..." size="md" />
        </div>

        <!-- Error State -->
      {:else if error}
        <div class="alert alert-error">
          <svg
            class="h-6 w-6 shrink-0 text-error"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
            focusable="false"
          >
            <path
              d="M12 2a10 10 0 100 20 10 10 0 000-20zm0 11a1 1 0 11.001-2.001A1 1 0 0112 13zm0 4a1 1 0 01-1-1v-2a1 1 0 112 0v2a1 1 0 01-1 1z"
            />
          </svg>
          <span>{error}</span>
        </div>

        <!-- Warning State -->
      {:else if warning}
        <div class="alert alert-warning">
          <svg
            class="h-6 w-6 shrink-0 text-warning"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
            focusable="false"
          >
            <path
              d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"
              fill="currentColor"
            />
          </svg>
          <span>{warning}</span>
        </div>

        <!-- Empty State -->
      {:else if savedJobs.length === 0 && deletedJobs.length === 0}
        <div class="text-center py-12">
          <svg
            class="h-16 w-16 mx-auto text-base-300 mb-4"
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
            focusable="false"
          >
            <path d="M6 2a2 2 0 00-2 2v17l8-4 8 4V4a2 2 0 00-2-2H6z" />
          </svg>
          <p class="text-base-content/60">Belum ada lowongan yang disimpan</p>
          <p class="text-sm text-base-content/40 mt-2">
            Klik ikon bookmark pada lowongan untuk menyimpannya
          </p>
        </div>

        <!-- Saved Jobs -->
      {:else}
        <div>
          {#if savedJobs.length > 0}
            <div class="space-y-3 mb-6">
              <div
                class="flex flex-row items-center justify-between break-words whitespace-normal"
              >
                <h4 class="font-semibold text-md">
                  Tersedia ({savedJobs.length})
                </h4>
                {#if lastSyncTime > 0 && !loading}
                  <div class="text-xs font-semibold flex flex-col">
                    <span class="mb-1 flex items-center gap-1"
                      >Terakhir sync:</span
                    >
                    <span>{formattedLastSync()}</span>
                  </div>
                {/if}
              </div>
              {#each displayedSavedJobs as job (job.id)}
                <div
                  class="card bg-base-200 shadow-sm hover:shadow-md transition-all duration-300"
                  class:scale-95={removingIds.has(job.id || 0)}
                  out:fade={{ duration: 200 }}
                >
                  <div class="card-body p-4">
                    <!-- Skeleton for loading job -->
                    {#if job.title === ""}
                      <div class="animate-pulse">
                        <div class="flex items-start justify-between gap-3">
                          <div class="flex-1 min-w-1">
                            <div class="h-4 bg-base-300 rounded mb-2"></div>
                            <div
                              class="h-3 bg-base-300 rounded mb-2 w-3/4"
                            ></div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
                              <div class="h-3 bg-base-300 rounded w-20"></div>
                              <div class="h-3 bg-base-300 rounded w-16"></div>
                              <div class="h-3 bg-base-300 rounded w-24"></div>
                            </div>
                            <div class="mt-2">
                              <div
                                class="h-3 bg-base-300 rounded w-32 mb-2"
                              ></div>
                              <div class="h-3 bg-base-300 rounded w-28"></div>
                            </div>
                          </div>
                          <div class="flex flex-col gap-1">
                            <div class="h-8 w-8 bg-base-300 rounded"></div>
                          </div>
                        </div>
                      </div>
                    {:else}
                      <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                          <h5 class="font-bold text-base truncate mb-1">
                            <button
                              onclick={() => handleJobClick(job)}
                              class="hover:text-[var(--wpl-global-color-1)] transition-colors text-left w-full"
                              aria-label={`View job details for ${job.title}`}
                            >
                              {job.title}
                            </button>
                          </h5>

                          {#if job.nama_perusahaan}
                            <p class="text-sm font-semibold mb-2">
                              <svg
                                class="h-5 w-5 inline-block align-text-bottom text-[var(--wpl-global-color-1)] mr-1"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                                focusable="false"
                              >
                                <path
                                  d="M12 12c2.21 0 4-1.79 4-4S14.21 4 12 4 8 5.79 8 8s1.79 4 4 4zm0 2c-3.31 0-6 2.69-6 6h12c0-3.31-2.69-6-6-6z"
                                />
                              </svg>
                              {job.nama_perusahaan}
                            </p>
                          {/if}

                          <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
                            {#each GeneralStore.useSummaryJob(job.ringkasanPekerjaan) as row}
                              {#if row.label !== "Deadline"}
                                {@const Icon = row.icon}
                                <span
                                  class="flex items-center text-base md:text-base font-semibold gap-2 py-1"
                                >
                                  {#if Icon}
                                    <Icon
                                      class="text-[var(--wpl-global-color-1)] w-4 h-4 shrink-0"
                                      aria-hidden="true"
                                    />
                                  {/if}
                                  <span>{@html row.value}</span>
                                </span>
                              {/if}
                            {/each}
                          </div>

                          <div class="mt-2 inline-block">
                            {#if job.statusInfo.label}
                              <span
                                class="px-3 py-1 badge font-bold rounded mr-2 {job
                                  .statusInfo.color}"
                              >
                                {job.statusInfo.label}
                              </span>
                            {/if}
                            {#if job.deadlineInfo.text}
                              <span
                                class="px-3 py-1 badge font-bold rounded {job
                                  .deadlineInfo.style}"
                              >
                                <svg
                                  class="h-4 w-4"
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  stroke="currentColor"
                                  stroke-width="2"
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                  aria-hidden="true"
                                  focusable="false"
                                >
                                  <rect
                                    x="3"
                                    y="4"
                                    width="18"
                                    height="18"
                                    rx="2"
                                    ry="2"
                                  ></rect>
                                  <line x1="16" y1="2" x2="16" y2="6"></line>
                                  <line x1="8" y1="2" x2="8" y2="6"></line>
                                  <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <span>{job.deadlineInfo.text}</span>
                              </span>
                            {/if}
                          </div>

                          {#if loading}
                            <div class="flex items-center justify-center py-12">
                              <LoadingSpinner srLabel="Memuat..." size="md" />
                            </div>
                          {/if}
                        </div>
                        <div class="flex flex-col gap-1">
                          <button
                            onclick={() => removeBookmark(job.id || 0)}
                            disabled={loading || removingIds.has(job.id || 0)}
                            class="btn btn-xs btn-ghost text-error"
                            title="Hapus bookmark"
                            aria-label="Hapus bookmark untuk {job.title}"
                          >
                            <svg
                              class="h-4 w-4"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              stroke-width="2"
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              aria-hidden="true"
                              focusable="false"
                            >
                              <polyline points="3 6 5 6 21 6" />
                              <path
                                d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"
                              />
                              <path d="M10 11v6" />
                              <path d="M14 11v6" />
                            </svg>
                          </button>
                        </div>
                      </div>
                    {/if}
                  </div>
                </div>
              {/each}
            </div>
          {/if}

          <!-- Deleted Jobs -->
          {#if deletedJobs.length > 0}
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <h4 class="font-semibold text-sm text-base-content/70">
                  Tidak Tersedia ({deletedJobs.length})
                </h4>
                <button
                  onclick={handleClearDeleted}
                  class="btn btn-xs btn-ghost text-error"
                  aria-label="Clear all deleted jobs"
                >
                  Hapus Semua
                </button>
              </div>
              {#each deletedJobs as id (id)}
                <div
                  class="card bg-base-300 opacity-60"
                  out:fade={{ duration: 200 }}
                >
                  <div class="card-body p-4">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <svg
                          class="h-5 w-5 text-error"
                          viewBox="0 0 24 24"
                          fill="currentColor"
                          aria-hidden="true"
                          focusable="false"
                        >
                          <path
                            d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 14a1 1 0 11-2 0 1 1 0 012 0zm0-6a1 1 0 11-2 0v3a1 1 0 112 0V10z"
                          />
                        </svg>
                        <span class="text-sm"
                          >Lowongan ID #{id} tidak tersedia</span
                        >
                      </div>
                      <button
                        onclick={() => removeBookmark(id)}
                        class="btn btn-xs btn-ghost"
                        title="Hapus dari daftar"
                        aria-label="Remove from deleted list"
                      >
                        <svg
                          class="h-4 w-4"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          aria-hidden="true"
                          focusable="false"
                        >
                          <polyline points="3 6 5 6 21 6" />
                          <path
                            d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"
                          />
                          <path d="M10 11v6" />
                          <path d="M14 11v6" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              {/each}
            </div>
          {/if}

          <!-- Copy Success Toast -->
          {#if showCopySuccess}
            <div class="toast toast-top toast-center z-50">
              <div class="alert alert-success">
                <svg
                  class="h-6 w-6 stroke-current shrink-0"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  aria-hidden="true"
                  focusable="false"
                >
                  <path
                    d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10z"
                  />
                  <path d="M9 12l2 2 4-4" />
                </svg>
                <span>Link berhasil disalin!</span>
              </div>
            </div>
          {/if}

          <!-- Offline Notice -->
          {#if isOffline}
            <div class="alert alert-warning mt-4">
              <svg
                class="h-6 w-6 stroke-current shrink-0 text-warning"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
                focusable="false"
              >
                <path
                  d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                />
                <path d="M12 9v4" />
                <path d="M12 17h.01" />
              </svg>
              <span>Mode offline - menampilkan data tersimpan</span>
            </div>
          {/if}
        </div>
      {/if}
    </div>
  </div>
  <div
    role="button"
    tabindex="0"
    class="modal-backdrop"
    onclick={closeModal}
    onkeydown={(e) => {
      if (e.key === "Enter" || e.key === " ") closeModal();
    }}
  ></div>
</dialog>

<!-- Delete All Confirmation Modal -->
<dialog
  bind:this={deleteConfirmModal}
  class="modal modal-bottom sm:modal-middle"
  class:modal-open={showDeleteConfirm}
>
  <div class="modal-box">
    <h3 class="font-bold text-lg flex items-center gap-2">
      <svg
        class="h-6 w-6 text-error"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
        focusable="false"
      >
        <path
          d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"
        />
      </svg>
      Konfirmasi Hapus Semua
    </h3>
    <p class="py-4">
      Apakah Anda yakin ingin menghapus semua bookmark? Tindakan ini tidak dapat
      dibatalkan dan akan menghapus semua lowongan yang telah Anda simpan.
    </p>
    <div class="modal-action">
      <button
        onclick={cancelDeleteAll}
        class="btn btn-ghost"
        disabled={loading}
      >
        Batal
      </button>
      <button
        onclick={confirmDeleteAll}
        class="btn btn-error"
        disabled={loading}
      >
        {#if loading}
          <LoadingSpinner size="sm" srLabel="Menghapus semua..." />
        {/if}
        Hapus Semua
      </button>
    </div>
  </div>
  <div
    role="button"
    tabindex="0"
    class="modal-backdrop"
    onclick={cancelDeleteAll}
    onkeydown={(e) => {
      if (e.key === "Enter" || e.key === " ") cancelDeleteAll();
    }}
  ></div>
</dialog>
