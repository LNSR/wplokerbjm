<script module lang="ts">
  import { onMount, onDestroy } from "svelte";
  import { SvelteSet, SvelteMap } from "svelte/reactivity";
  import { timeEffect } from "$lib/utils/elements.svelte";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { generalStore } from "$lib/stores/General.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import { Virtualization } from "$lib/utils/Virtualization.svelte";
  import type { CardJob } from "@/types";
  import { isMobile, isJobGridEl } from "$lib/utils/elements.svelte";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import {
    GlobalNavigateTo,
    routeStore,
    routeStateStore,
  } from "@/app/lib/stores/Route.svelte";
  import { SvelteDate } from "svelte/reactivity";
  import { fade } from "svelte/transition";
  import {
    BookmarkSolid,
    XmarkSolid,
    TrashAltSolid,
    ExclamationTriangleSolid,
    UserTieSolid,
    CalendarSolid,
    ExclamationCircleSolid,
    CheckCircleSolid,
    ThumbTackSolid,
    MagnifyingGlassSolid,
  } from "svelte-awesome-icons";
  import type { Attachment } from "svelte/attachments";

  let modalEl: HTMLDialogElement;
  let deleteConfirmModal: HTMLDialogElement;
  let modalBox: HTMLElement;
  let dragHandle = $state<HTMLElement | undefined>();
  let contentContainer = $state<HTMLDivElement | undefined>();

  // Virtualization state
  let containerScrollY = $state(0);
  let containerHeight = $state(0);
  let cardHeights = new SvelteMap(
    routeStateStore.getCardHeights("bookmarkModal"),
  );

  // Dragging state
  let translateX = $state(0);
  let translateY = $state(0);
  let isDragging = $state(false);
  let activePointerId: number | null = null;
  let startClientX = $state(0);
  let startClientY = $state(0);
  let startHeight = $state(0);

  // loading mirrors the central store isSyncing to ensure UI reflects store activity
  let loading = $derived(bookmarkStore.isSyncing);
  let error = $state("");
  const showCopySuccess = $state(false);
  let isOffline = $state(false);
  let showDeleteConfirm = $state(false);
  const removingIds = $state(new SvelteSet<number>());
  const now = $state(new SvelteDate());

  // Store bindings
  const savedJobs = $derived(bookmarkStore.jobs);
  const warning = $derived(bookmarkStore.warning);
  const deletedJobs = $derived(bookmarkStore.deletedJobs);
  const lastSyncTime = $derived(bookmarkStore.lastSyncTime);

  const STALE_THRESHOLD = 5 * 60 * 1000;

  // Search state for filtering saved jobs (title and company)
  let searchQuery = $state("");
  let isSearchOpen = $state(false);
  let searchInput = $state<HTMLInputElement | null>(null);

  // Bookmark for medium-heavy data operations
  class BookmarkHandler {
    async fetchJobs(forceRefresh = false): Promise<void> {
      if (!forceRefresh && savedJobs.length === 0) {
        return;
      }
      const now = Date.now();
      const isStale = now - lastSyncTime > STALE_THRESHOLD;
      if (!forceRefresh && !isStale && savedJobs.length > 0) return;
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

    async removeBookmark(id: number): Promise<void> {
      removingIds.add(id);
      await new Promise((resolve) => setTimeout(resolve, 1000));
      await bookmarkStore.removeJob(id);
      removingIds.delete(id);
    }

    handleDeleteAll(): void {
      showDeleteConfirm = true;
    }

    async confirmDeleteAll(): Promise<void> {
      showDeleteConfirm = false;
      savedJobs.forEach((job) => {
        if (job.id) removingIds.add(job.id);
      });
      await new Promise((resolve) => setTimeout(resolve, 500));
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

    cancelDeleteAll(): void {
      showDeleteConfirm = false;
    }

    handleClearDeleted(): void {
      bookmarkStore.clearDeleted();
    }

    handleRefresh(): Promise<void> {
      return bookmarkHandler.fetchJobs(true);
    }

    scheduleFetchJobs = () => {
      const runFetch = () => void bookmarkHandler.fetchJobs();
      if (typeof (window as any).requestIdleCallback === "function") {
        (window as any).requestIdleCallback(() => runFetch());
      } else {
        requestAnimationFrame(() => runFetch());
      }
    };

    displayedSavedJobs = $derived.by(() => {
      return savedJobs.map((job) => ({
        ...job,
        timeAgo: generalStore.useTimeAgo(job.post_time, now)(),
        deadlineInfo: job.deadline
          ? generalStore.useDeadline(job.deadline, now)()
          : { text: "", style: "" },
        statusInfo: job.status_pekerjaan
          ? generalStore.useStatusJob(Number(job.status_pekerjaan))
          : { label: "", color: "" },
      }));
    });

    filteredDisplayedJobs = $derived.by(() => {
      const q = String(searchQuery || "")
        .trim()
        .toLowerCase();
      if (!q) return this.displayedSavedJobs;
      return this.displayedSavedJobs.filter((job) => {
        const title = String(job.title || "").toLowerCase();
        const company = String(job.nama_perusahaan || "").toLowerCase();
        return title.includes(q) || company.includes(q);
      });
    });
  }

  class VirtualizationManager {
    // Virtualized jobs computation
    virtualizedJobs = $derived.by(() => {
      return Virtualization.computeList({
        displayJobs: bookmarkHandler.filteredDisplayedJobs,
        scrollY: containerScrollY,
        containerHeight: containerHeight,
        cardHeights: cardHeights,
        fallbackHeight: 200,
        gap: 12,
        buffer: 2,
      });
    });

    measureHeight(jobId: number): Attachment<HTMLElement> {
      return Virtualization.createMeasureHeight(cardHeights, jobId);
    }

    // Update container dimensions
    updateContainerDimensions() {
      if (!contentContainer) return;

      // Measure on next animation frame to ensure layout is complete (dialog might be animating)
      requestAnimationFrame(() => {
        try {
          const height =
            contentContainer?.clientHeight ||
            contentContainer?.getBoundingClientRect().height ||
            0;
          if (height !== containerHeight) {
            containerHeight = height;
          }

          // Also capture current scroll position so virtualization can compute visible items immediately
          const scrollTop = contentContainer?.scrollTop || 0;
          if (scrollTop !== containerScrollY) {
            containerScrollY = scrollTop;
          }
        } catch (e) {
          void e;
        }
      });
    }

    // Clear card heights that are no longer displayed
    get clearCardHeights(): SvelteMap<number, number> {
      const currentJobIds = new SvelteSet(
        bookmarkHandler.filteredDisplayedJobs.map(
          (job: CardJob) => job.id || 0,
        ),
      );
      const heightsToKeep = new SvelteMap<number, number>();
      for (const [jobId, height] of cardHeights) {
        if (currentJobIds.has(jobId)) {
          heightsToKeep.set(jobId, height);
        }
      }
      if (heightsToKeep.size !== cardHeights.size) {
        cardHeights = heightsToKeep;
      }
      return cardHeights;
    }
  }

  class BookmarkUtilities {
    static get formattedLastSync() {
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
    }
  }

  export const bookmarkHandler = new BookmarkHandler();
  export const virtualizationManager = new VirtualizationManager();
</script>

<script lang="ts">
  let { open = $bindable() } = $props<{ open: boolean }>();

  /**
   * UI Specific Modal Handler
   */
  class ModalHandler {
    startDrag = (e: PointerEvent): void => {
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
      startClientX = e.clientX;
      startClientY = e.clientY;
      if (isMobile()) {
        startHeight = modalBox?.clientHeight || 0;
      }
      window.addEventListener("pointermove", this.onPointerMove);
      window.addEventListener("pointerup", this.onPointerUp);
      e.preventDefault();
    };

    onPointerMove = (e: PointerEvent): void => {
      if (!isDragging) return;
      if (activePointerId !== null && e.pointerId !== activePointerId) return;
      if (!modalBox) return;
      if (isMobile()) {
        const dy = e.clientY - startClientY;
        const newH = startHeight - dy;
        translateX = 0;
        translateY = 0;
        try {
          modalBox.style.setProperty("height", `${newH}px`, "important");
        } catch {
          modalBox.style.height = `${newH}px`;
        }
        return;
      } else {
        translateX = e.clientX - startClientX;
        translateY = e.clientY - startClientY;
      }
    };

    onPointerUp = (e: PointerEvent): void => {
      if (!isDragging) return;
      try {
        if (typeof e.pointerId !== "undefined")
          dragHandle?.releasePointerCapture(e.pointerId);
      } catch (err) {
        void err;
      }
      isDragging = false;
      activePointerId = null;
      window.removeEventListener("pointermove", this.onPointerMove);
      window.removeEventListener("pointerup", this.onPointerUp);
    };
    resetPosition(): void {
      translateX = 0;
      translateY = 0;
      try {
        if (modalBox) modalBox.style.removeProperty("height");
      } catch {
        // ignore
      }
    }

    handleKeydown = (e: KeyboardEvent): void => {
      if (e.key === "Escape" && open) this.closeModal();
    };

    handleJobClick(job: CardJob): void {
      this.closeModal();

      const el: HTMLElement | null = isJobGridEl();
      if (!isMobile() && el) {
        // Desktop: open overlay
        routeStateStore.saveCardHeights(new Map(cardHeights), "bookmarkModal");
        jobOverlay.openOverlay(job.slug ?? "", job);
        jobOverlay.scrollToCard(job.slug ?? "");
      } else {
        // Mobile: navigate
        if (job.permalink) {
          const url = new URL(job.permalink, routeStore.currentUrl.origin);
          void GlobalNavigateTo(url.pathname + url.search + url.hash);
        }
      }
    }

    closeModal(): void {
      open = false;
    }

    portalDialog(append: boolean = true): void {
      if (typeof document === "undefined") return;
      const appContainer =
        document.querySelector(".route-container") ?? document.body;
      switch (append) {
        case true:
          if (modalEl && modalEl.parentElement !== appContainer) {
            appContainer.appendChild(modalEl);
          }
          if (
            deleteConfirmModal &&
            deleteConfirmModal.parentElement !== appContainer
          ) {
            appContainer.appendChild(deleteConfirmModal);
          }
          break;
        case false:
          if (modalEl && modalEl.parentElement === appContainer) {
            appContainer.removeChild(modalEl);
          }
          if (
            deleteConfirmModal &&
            deleteConfirmModal.parentElement === appContainer
          ) {
            appContainer.removeChild(deleteConfirmModal);
          }
      }
    }
    get layoutBreakpoint() {
      const w = window.innerWidth;
      if (w < 640) return "mobile";
      if (w < 1024) return "tablet";
      return "desktop";
    }

    // Handle scroll events on the content container
    handleContentScroll = (e: Event): void => {
      const target = e.target as HTMLDivElement;
      containerScrollY = target.scrollTop;
    };
  }

  const modalHandler = new ModalHandler();

  const isMobileValue = $derived.by(() => isMobile());

  const modalStyle = $derived(
    `transform: translate(${translateX}px, ${translateY}px); touch-action: ${isMobileValue ? "none" : "auto"};`,
  );

  // Virtualized jobs for rendering
  const virtualizedJobs = $derived(virtualizationManager.virtualizedJobs);

  // Only collapse action buttons on small (mobile) layouts when search is active
  const shouldCollapseActions = $derived.by(() => {
    return isSearchOpen && modalHandler.layoutBreakpoint === "mobile";
  });

  // Ensure we re-measure when the modal is opened so virtualization has correct container size
  $effect(() => {
    if (!open) return;

    // Small delay to allow dialog show animation / placement into DOM, then measure
    setTimeout(() => {
      virtualizationManager.updateContainerDimensions();
      // Double-check on next frame as layout may still settle
      requestAnimationFrame(virtualizationManager.updateContainerDimensions);
      // Force read of scrollTop to trigger reactivity if needed
      containerScrollY = contentContainer?.scrollTop ?? 0;
    }, 50);
  });

  $effect(() => {
    requestAnimationFrame(() => {
      virtualizationManager.clearCardHeights;
    });
  });

  $effect(() => {
    timeEffect(); //;
    // React to showDeleteConfirm changes to control the delete confirmation modal
    if (showDeleteConfirm) {
      if (!deleteConfirmModal?.open) deleteConfirmModal?.showModal();
    } else {
      deleteConfirmModal?.close();
    }
  });

  onMount(() => {
    if (open) {
      bookmarkStore.flushSync();
      bookmarkHandler.scheduleFetchJobs();

      if (!modalEl?.open) modalEl?.showModal();
      if (isMobileValue && modalBox) {
        const vh = window.innerHeight;
        const initialHeight = Math.round(vh * 0.6);
        modalBox.style.setProperty("height", `${initialHeight}px`, "important");
      }
    } else {
      modalEl?.close();
      modalHandler.resetPosition();
    }

    if (open && modalHandler.layoutBreakpoint === "desktop" && !isSearchOpen) {
      isSearchOpen = true;
      searchInput?.focus();
    }

    document.addEventListener("keydown", modalHandler.handleKeydown);
    window.addEventListener(
      "resize",
      virtualizationManager.updateContainerDimensions,
    );
    if (open) {
      open = true;
      // Update dimensions after mount
      setTimeout(() => virtualizationManager.updateContainerDimensions(), 0);
    }

    modalHandler.portalDialog(true);
  });

  onDestroy(() => {
    document.removeEventListener("keydown", modalHandler.handleKeydown);
    window.removeEventListener(
      "resize",
      virtualizationManager.updateContainerDimensions,
    );
    window.removeEventListener("pointermove", modalHandler.onPointerMove);
    window.removeEventListener("pointerup", modalHandler.onPointerUp);

    try {
      if (dragHandle && activePointerId !== null)
        dragHandle.releasePointerCapture(activePointerId);
    } catch (e) {
      void e;
    }

    modalHandler.resetPosition();

    modalHandler.portalDialog(false);

    routeStateStore.saveCardHeights(new Map(cardHeights), "bookmarkModal");
  });
</script>

<dialog
  bind:this={modalEl}
  class="modal modal-bottom sm:modal-middle"
  class:modal-open={open}
>
  <div
    bind:this={modalBox}
    role="dialog"
    tabindex="0"
    class="modal-box p-0 flex flex-col relative max-h-[80vh] rounded-t-xl overflow-hidden md:mx-auto md:!max-w-3xl md:z-60 md:rounded-b-xl"
    class:mobile-sheet={isMobileValue}
    style={modalStyle}
    onpointerdown={modalHandler.startDrag}
  >
    <!-- Drag Handle -->
    {#if isMobileValue}
      <div
        bind:this={dragHandle}
        class="drag-handle !w-12 !h-2 bg-base-content/20 rounded-full mx-auto mt-3 mb-2 cursor-grab active:cursor-grabbing touch-none select-none transition-colors duration-200 hover:bg-base-content/30 active:bg-base-content/40 md:bg-base-content/15 md:hover:bg-base-content/25 md:active:bg-base-content/35"
        onpointerdown={modalHandler.startDrag}
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
          <div class="flex items-center gap-3">
            <h3 class="font-bold text-lg flex items-center gap-2">
              <BookmarkSolid
                class="h-5 w-5 text-[var(--wpl-global-color-1)]"
                aria-hidden="true"
              />
              Lowongan Tersimpan
              {#if !loading && savedJobs.length > 0}
                <span
                  class="bg-[var(--wpl-global-color-1)] text-sm rounded-full px-2 py-0.1 z-10"
                  >{savedJobs.length}</span
                >
              {/if}
            </h3>
          </div>

          <button
            onclick={() => modalHandler.closeModal()}
            class="btn btn-xs btn-info rounded-full w-auto whitespace-nowrap"
            aria-label="Tutup dialog"
            title="close modal"
          >
            <XmarkSolid class="h-4 w-4" aria-hidden="true" />
          </button>
        </div>

        <!-- Second row: action buttons placed under the header -->
        <div
          class="flex flex-row items-center gap-2 mt-3 w-full overflow-hidden"
        >
          <!-- Left: search control that expands; when open, action buttons collapse to the right -->
          <div class="flex-1 flex items-center min-w-0">
            {#if !loading}
              <button
                onclick={() => {
                  isSearchOpen = !isSearchOpen;
                  if (isSearchOpen) setTimeout(() => searchInput?.focus(), 0);
                }}
                class="btn btn-ghost btn-sm mr-2"
                aria-label="Cari dalam simpanan"
                title="Cari"
              >
                <MagnifyingGlassSolid class="h-4 w-4" aria-hidden="true" />
              </button>
            {/if}

            {#if !loading && isSearchOpen}
              <input
                id="bookmark-search"
                name="bookmark_search"
                bind:this={searchInput}
                bind:value={searchQuery}
                class="input input-sm w-full md:w-96 min-w-0 flex-grow"
                placeholder="Cari judul atau perusahaan"
                onkeydown={(e) => {
                  if (e.key === "Escape") {
                    isSearchOpen = false;
                  }
                }}
              />
            {/if}
          </div>

          <!-- Right: action buttons or close-search button when search open -->
          <div class="flex items-center gap-2 flex-shrink-0">
            {#if !loading && isSearchOpen}
              <button
                onclick={() => {
                  isSearchOpen = false;
                  searchQuery = "";
                  try {
                    searchInput?.blur();
                  } catch {
                    // ignore
                  }
                }}
                class="btn btn-ghost btn-sm"
                aria-label="Tutup pencarian"
                title="Tutup pencarian"
              >
                <XmarkSolid class="h-4 w-4" aria-hidden="true" />
              </button>
            {/if}

            {#if !shouldCollapseActions}
              {#if !loading && savedJobs.length > 0}
                <button
                  onclick={bookmarkHandler.handleDeleteAll}
                  disabled={loading}
                  class="btn btn-ghost btn-sm md:btn-md text-error w-auto whitespace-nowrap"
                  aria-label="hapus semua"
                  title="hapus semua"
                >
                  <TrashAltSolid class="h-4 w-4 mr-2" aria-hidden="true" />
                  Hapus Semua
                </button>
                <button
                  onclick={bookmarkHandler.handleRefresh}
                  disabled={loading}
                  class="btn btn-ghost btn-sm md:btn-md w-auto whitespace-nowrap"
                  aria-label="sync ke server"
                  title="sync ke server"
                >
                  <RefreshSpinner size="h-4 w-4 mr-2" spin={loading} />
                  Sync/Refresh
                </button>
              {/if}
            {/if}
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div
      bind:this={contentContainer}
      class="flex-1 overflow-y-auto max-h-full px-6 py-4"
      onscroll={modalHandler.handleContentScroll}
    >
      <!-- Loading State -->
      {#if loading}
        <div class="flex items-center justify-center py-12">
          <LoadingSpinner srLabel="Memuat..." size="md" />
        </div>

        <!-- Error State -->
      {:else if error}
        <div class="alert alert-error">
          <ExclamationTriangleSolid
            class="h-6 w-6 shrink-0 text-error"
            aria-hidden="true"
          />
          <span>{error}</span>
        </div>

        <!-- Warning State -->
      {:else if warning}
        <div class="alert alert-warning">
          <ExclamationTriangleSolid
            class="h-6 w-6 shrink-0 text-warning"
            aria-hidden="true"
          />
          <span>{warning}</span>
        </div>

        <!-- Empty State -->
      {:else if savedJobs.length === 0 && deletedJobs.length === 0}
        <div class="text-center py-12">
          <BookmarkSolid
            class="h-16 w-16 mx-auto text-base-300 mb-4"
            aria-hidden="true"
          />
          <p class="text-base-content/60">Belum ada lowongan yang disimpan</p>
          <p class="text-sm text-base-content/40 mt-2">
            Klik ikon bookmark pada lowongan untuk menyimpannya
          </p>
        </div>

        <!-- Saved Jobs -->
      {:else}
        {#if savedJobs.length > 0}
          <div class="mb-6">
            <div
              class="flex flex-row items-center justify-between break-words whitespace-normal mb-2"
            >
              <h4 class="font-semibold text-md">
                Tersedia ({savedJobs.length})
              </h4>
              {#if lastSyncTime > 0 && !loading}
                <div class="text-xs font-semibold flex flex-col">
                  <span class="mb-1 flex items-center gap-1"
                    >Terakhir sync:</span
                  >
                  <span>{BookmarkUtilities.formattedLastSync}</span>
                </div>
              {/if}
            </div>

            <div
              class="relative"
              style="height: {virtualizedJobs.totalHeight}px;"
            >
              {#each virtualizedJobs.visibleJobs as job, idx (job.id)}
                {@const absoluteIndex = virtualizedJobs.startIndex + idx}
                {@const topPosition =
                  virtualizedJobs.itemPositions[absoluteIndex] || 0}
                <div
                  class="card bg-base-300 shadow-sm hover:shadow-md transition-transform duration-400 absolute left-0 right-0"
                  class:scale-0={removingIds.has(job.id || 0)}
                  style="transform: translate3d(0, {topPosition}px, 0);"
                  out:fade={{ duration: 200 }}
                  {@attach virtualizationManager.measureHeight(job.id || 0)}
                >
                  <div class="card-body p-4">
                    {#if job.title === ""}
                      <div class="animate-pulse">
                        <div class="flex items-start justify-between gap-3">
                          <div class="flex-1 min-w-1">
                            <div
                              class="h-4 bg-base-content/20 rounded mb-2"
                            ></div>
                            <div
                              class="h-3 bg-base-content/20 rounded mb-2 w-3/4"
                            ></div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
                              <div
                                class="h-3 bg-base-content/20 rounded w-20"
                              ></div>
                              <div
                                class="h-3 bg-base-content/20 rounded w-16"
                              ></div>
                              <div
                                class="h-3 bg-base-content/20 rounded w-24"
                              ></div>
                            </div>
                            <div class="mt-2">
                              <div
                                class="h-3 bg-base-content/20 rounded w-32 mb-2"
                              ></div>
                              <div
                                class="h-3 bg-base-content/20 rounded w-28"
                              ></div>
                            </div>
                          </div>
                          <div class="flex flex-col gap-1">
                            <div
                              class="h-8 w-8 bg-base-content/20 rounded"
                            ></div>
                          </div>
                        </div>
                      </div>
                    {:else}
                      <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                          <p
                            class="font-bold text-base flex items-center gap-2 mb-1"
                          >
                            <button
                              onclick={() => modalHandler.handleJobClick(job)}
                              class="hover:text-[var(--wpl-global-color-1)] transition-colors text-left w-full"
                              aria-label={`View job details for ${job.title}`}
                            >
                              {job.title}
                            </button>
                          </p>
                          {#if !job.nama_perusahaan}
                            <div class="divider mt-0"></div>
                          {/if}

                          {#if job.nama_perusahaan}
                            <p
                              class="text-base font-semibold mb-6 flex items-center gap-2"
                            >
                              <UserTieSolid
                                class="h-4 w-4 text-[var(--wpl-global-color-1)] inline-block"
                                aria-hidden="true"
                              />
                              {job.nama_perusahaan}
                            </p>
                            <div class="divider -mt-4"></div>
                          {/if}

                          <div class="flex flex-wrap gap-x-4 gap-y-1 mb-2">
                            {#each generalStore.useSummaryJob(job.ringkasanPekerjaan) as row}
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

                          {#if job.statusInfo.label || job.deadlineInfo.text}
                            <div class="divider my-2"></div>
                            <div class="mt-2 inline-block">
                              {#if job.statusInfo.label}
                                <span
                                  class="px-3 py-1 badge font-bold rounded mr-2 {job
                                    .statusInfo.color}"
                                >
                                  {#if job.statusInfo.label === "Urgent"}
                                    <ExclamationTriangleSolid
                                      class="h-4 w-4"
                                      aria-hidden="true"
                                    />
                                  {:else if job.statusInfo.label === "Pinned"}
                                    <ThumbTackSolid
                                      class="h-4 w-4"
                                      aria-hidden="true"
                                    />
                                  {/if}
                                  {job.statusInfo.label}
                                </span>
                              {/if}
                              {#if job.deadlineInfo.text}
                                <span
                                  class="px-3 py-1 badge font-bold rounded {job
                                    .deadlineInfo.style}"
                                >
                                  <CalendarSolid
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                  />
                                  <span>{job.deadlineInfo.text}</span>
                                </span>
                              {/if}
                            </div>
                          {/if}

                          {#if loading}
                            <div class="flex items-center justify-center py-12">
                              <LoadingSpinner srLabel="Memuat..." size="md" />
                            </div>
                          {/if}
                        </div>
                        <div class="flex flex-col gap-1">
                          <button
                            onclick={() =>
                              bookmarkHandler.removeBookmark(job.id || 0)}
                            disabled={loading || removingIds.has(job.id || 0)}
                            class="btn btn-xs btn-ghost text-error"
                            title="Hapus bookmark"
                            aria-label="Hapus bookmark untuk {job.title}"
                          >
                            <TrashAltSolid class="h-4 w-4" aria-hidden="true" />
                          </button>
                        </div>
                      </div>
                    {/if}
                  </div>
                </div>
              {/each}
            </div>
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
                onclick={bookmarkHandler.handleClearDeleted}
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
                      <ExclamationCircleSolid
                        class="h-5 w-5 text-error"
                        aria-hidden="true"
                      />
                      <span class="text-sm"
                        >Lowongan ID #{id} tidak tersedia</span
                      >
                    </div>
                    <button
                      onclick={() => bookmarkHandler.removeBookmark(id)}
                      class="btn btn-xs btn-ghost"
                      title="Hapus dari daftar"
                      aria-label="Remove from deleted list"
                    >
                      <TrashAltSolid class="h-4 w-4" aria-hidden="true" />
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
              <CheckCircleSolid
                class="h-6 w-6 stroke-current shrink-0"
                aria-hidden="true"
              />
              <span>Link berhasil disalin!</span>
            </div>
          </div>
        {/if}

        <!-- Offline Notice -->
        {#if isOffline}
          <div class="alert alert-warning mt-4">
            <ExclamationTriangleSolid
              class="h-6 w-6 stroke-current shrink-0 text-warning"
              aria-hidden="true"
            />
            <span>Mode offline - menampilkan data tersimpan</span>
          </div>
        {/if}
      {/if}
    </div>
  </div>
  <div
    role="button"
    tabindex="0"
    class="modal-backdrop"
    onclick={() => modalHandler.closeModal()}
    onkeydown={(e) => {
      if (e.key === "Enter" || e.key === " ") modalHandler.closeModal();
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
      <ExclamationTriangleSolid class="h-6 w-6 text-error" aria-hidden="true" />
      Konfirmasi Hapus Semua
    </h3>
    <p class="py-4">
      Apakah Anda yakin ingin menghapus semua bookmark? Tindakan ini tidak dapat
      dibatalkan dan akan menghapus semua lowongan yang telah Anda simpan.
    </p>
    <div class="modal-action">
      <button
        onclick={bookmarkHandler.cancelDeleteAll}
        class="btn btn-ghost"
        disabled={loading}
      >
        Batal
      </button>
      <button
        onclick={bookmarkHandler.confirmDeleteAll}
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
    onclick={bookmarkHandler.cancelDeleteAll}
    onkeydown={(e) => {
      if (e.key === "Enter" || e.key === " ") bookmarkHandler.cancelDeleteAll();
    }}
  ></div>
</dialog>
