<script module lang="ts">
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

  // Virtualization state for saved jobs list
  const ITEM_HEIGHT = 240;
  const GAP = 12;
  // ResizeObserver to detect modal/content size changes (used for virtualization)
  let resizeObserver: ResizeObserver | null = null;
  let resizeObserverCallback: (() => void) | null = null;

  let contentEl = $state<HTMLElement | null>(null);
  let scrollY = $state(0);
  let innerW = $state(0);
  let innerH = $state(0);
  // Search state for filtering saved jobs (title and company)
  let searchQuery = $state("");
  let isSearchOpen = $state(false);
  let searchInput = $state<HTMLInputElement | null>(null);

  let virtualState = $state({
    itemsPerRow: 1,
    totalRows: 0,
    rowHeight: ITEM_HEIGHT + GAP,
    sectionTop: 0,
    startRow: 0,
    endRow: 0,
    startIndex: 0,
    endIndex: 0,
    visibleJobs: [] as CardJob[] &
      {
        deadlineInfo: { text: string; style: string };
        statusInfo: { label: string; color: string };
        timeAgo: string;
      }[],
    totalHeight: 0,
  });

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
</script>

<script lang="ts">
  import { onMount, onDestroy } from "svelte";
  import { SvelteSet } from "svelte/reactivity";
  import { timeEffect } from "$lib/utils/elements.svelte";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { generalStore } from "$lib/stores/General.svelte";
  import type { CardJob } from "@/types";
  import { isMobile } from "$lib/utils/elements.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { GlobalNavigateTo, routeStore } from "@/app/lib/stores/Route.svelte";
  import { SvelteDate } from "svelte/reactivity";
  import { fade } from "svelte/transition";
  import { Virtualization } from "$lib/utils/Virtualization.svelte";
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

  let { open = $bindable() } = $props<{ open: boolean }>();

  /**
   * UI Specific Modal Handler
   */
  class ModalHandler {
    // Use arrow class fields for handlers so `this` is preserved without manual binding

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
        const vh = window.innerHeight;
        const minH = Math.round(vh * 0.25);
        const maxH = Math.round(vh * 0.95);
        const dy = e.clientY - startClientY;
        let newH = startHeight - dy;
        newH = this.clamp(newH, minH, maxH);
        translateX = 0;
        translateY = 0;
        try {
          modalBox.style.setProperty("height", `${newH}px`, "important");
        } catch {
          modalBox.style.height = `${newH}px`;
        }
        return;
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
      if (isMobile()) {
        const releaseDy = e.clientY - startClientY;
        if (releaseDy > 150) {
          this.closeModal();
          if (modalBox) modalBox.style.removeProperty("height");
        }
      }
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

    // Utility to clamp a number between min and max
    clamp(n: number, min: number, max: number): number {
      return Math.max(min, Math.min(max, n));
    }

    onScroll = (): void => {
      if (!contentEl) return;
      scrollY = contentEl.scrollTop;
    };

    handleKeydown = (e: KeyboardEvent): void => {
      if (e.key === "Escape" && open) this.closeModal();
    };

    async handleJobClick(job: CardJob): Promise<void> {
      this.closeModal();

      // Navigate to job detail page
      if (job.permalink) {
        const url = new URL(job.permalink, routeStore.currentUrl.origin);
        void GlobalNavigateTo(url.pathname + url.search + url.hash);
      }
    }

    closeModal(): void {
      open = false;
    }

    updateSizes(): void {
      innerW = modalBox ? modalBox.clientWidth : window.innerWidth;
      innerH = contentEl ? contentEl.clientHeight : window.innerHeight;
    }

    portalDialog(append: boolean = true): void {
      if (typeof document === "undefined") return;
      switch (append) {
        case true:
          if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
          }
          if (
            deleteConfirmModal &&
            deleteConfirmModal.parentElement !== document.body
          ) {
            document.body.appendChild(deleteConfirmModal);
          }
          break;
        case false:
          if (modalEl && modalEl.parentElement === document.body) {
            document.body.removeChild(modalEl);
          }
          if (
            deleteConfirmModal &&
            deleteConfirmModal.parentElement === document.body
          ) {
            document.body.removeChild(deleteConfirmModal);
          }
      }
    }
    get layoutBreakpoint() {
      const w = Number(innerW) || window.innerWidth;
      if (w < 640) return "mobile";
      if (w < 1024) return "tablet";
      return "desktop";
    }
  }

  const modalHandler = new ModalHandler();

  const isMobileValue = $derived.by(() => isMobile());

  const modalStyle = $derived(
    `transform: translate(${translateX}px, ${translateY}px); transition: ${isDragging ? "none" : "transform 180ms ease"}; touch-action: ${isMobileValue ? "none" : "auto"};`
  );

  // Filter displayedSavedJobs by searchQuery (title or nama_perusahaan)
  const filteredDisplayedJobs = $derived(bookmarkHandler.filteredDisplayedJobs);

  // Only collapse action buttons on small (mobile) layouts when search is active
  const shouldCollapseActions = $derived.by(() => {
    return isSearchOpen && modalHandler.layoutBreakpoint === "mobile";
  });

  // Recompute virtualization whenever sizes, scroll or data changes
  $effect(() => {
    timeEffect(now);
    innerW = modalBox ? modalBox.clientWidth : window.innerWidth;
    innerH = contentEl ? contentEl.clientHeight : window.innerHeight;

    virtualState = Virtualization.computeGrid({
      displayJobs: filteredDisplayedJobs,
      innerWidth: innerW,
      innerHeight: innerH,
      scrollY,
      sectionTop: 0,
      itemHeight: ITEM_HEIGHT,
      gap: GAP,
      buffer: 3,
    });
  });

  $effect(() => {
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
      bookmarkHandler.fetchJobs();
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
    if (open) {
      open = true;
    }

    // initial set
    modalHandler.updateSizes();

    // Throttled update using rAF to avoid layout thrashing
    resizeObserverCallback = () =>
      requestAnimationFrame(modalHandler.updateSizes);

    if (typeof ResizeObserver !== "undefined") {
      resizeObserver = new ResizeObserver(resizeObserverCallback);
      if (modalBox) resizeObserver.observe(modalBox);
      if (contentEl) resizeObserver.observe(contentEl);
    } else {
      // Fallback to window resize if ResizeObserver isn't available
      window.addEventListener("resize", resizeObserverCallback);
    }

    modalHandler.portalDialog(true);
  });

  onDestroy(() => {
    document.removeEventListener("keydown", modalHandler.handleKeydown);
    window.removeEventListener("pointermove", modalHandler.onPointerMove);
    window.removeEventListener("pointerup", modalHandler.onPointerUp);

    try {
      if (dragHandle && activePointerId !== null)
        dragHandle.releasePointerCapture(activePointerId);
    } catch (e) {
      void e;
    }

    // Cleanup ResizeObserver / fallback listener
    try {
      if (resizeObserver) {
        if (modalBox) resizeObserver.unobserve(modalBox);
        if (contentEl) resizeObserver.unobserve(contentEl);
        resizeObserver.disconnect();
        resizeObserver = null;
      } else if (resizeObserverCallback) {
        window.removeEventListener("resize", resizeObserverCallback);
      }
    } catch (e) {
      void e;
    }

    resizeObserverCallback = null;
    modalHandler.resetPosition();

    modalHandler.portalDialog(false);
  });
</script>

<dialog
  bind:this={modalEl}
  class="modal modal-bottom sm:modal-middle"
  class:modal-open={open}
>
  <div
    bind:this={modalBox}
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
      bind:this={contentEl}
      onscroll={modalHandler.onScroll}
      class="flex-1 overflow-y-auto max-h-full px-6 py-4"
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
        <div>
          {#if savedJobs.length > 0}
            <div class="mb-6">
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
                    <span>{BookmarkUtilities.formattedLastSync}</span>
                  </div>
                {/if}
              </div>

              <div class="relative mt-3">
                <div
                  style="height: {virtualState.totalHeight}px; position: relative;"
                >
                  <div
                    style="position: absolute; left:0; right:0; transform: translateY({virtualState.startRow *
                      virtualState.rowHeight}px);"
                  >
                    {#each virtualState.visibleJobs as job (job.id)}
                      <div
                        class="card bg-base-300 shadow-sm hover:shadow-md transition-all duration-300 mb-3"
                        class:scale-95={removingIds.has(job.id || 0)}
                        out:fade={{ duration: 200 }}
                      >
                        <div class="card-body p-4">
                          {#if job.title === ""}
                            <div class="animate-pulse">
                              <div
                                class="flex items-start justify-between gap-3"
                              >
                                <div class="flex-1 min-w-1">
                                  <div
                                    class="h-4 bg-base-content/20 rounded mb-2"
                                  ></div>
                                  <div
                                    class="h-3 bg-base-content/20 rounded mb-2 w-3/4"
                                  ></div>
                                  <div
                                    class="flex flex-wrap gap-x-4 gap-y-1 mb-2"
                                  >
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
                                  class="text-md font-bold text-base flex items-center gap-2 mb-1"
                                >
                                  <button
                                    onclick={() =>
                                      modalHandler.handleJobClick(job)}
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
                                    class="text-md font-semibold mb-6 flex items-center gap-2"
                                  >
                                    <UserTieSolid
                                      class="h-4 w-4 text-[var(--wpl-global-color-1)] inline-block"
                                      aria-hidden="true"
                                    />
                                    {job.nama_perusahaan}
                                  </p>
                                  <div class="divider -mt-4"></div>
                                {/if}

                                <div
                                  class="flex flex-wrap gap-x-4 gap-y-1 mb-2"
                                >
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
                                  <div
                                    class="flex items-center justify-center py-12"
                                  >
                                    <LoadingSpinner
                                      srLabel="Memuat..."
                                      size="md"
                                    />
                                  </div>
                                {/if}
                              </div>
                              <div class="flex flex-col gap-1">
                                <button
                                  onclick={() =>
                                    bookmarkHandler.removeBookmark(job.id || 0)}
                                  disabled={loading ||
                                    removingIds.has(job.id || 0)}
                                  class="btn btn-xs btn-ghost text-error"
                                  title="Hapus bookmark"
                                  aria-label="Hapus bookmark untuk {job.title}"
                                >
                                  <TrashAltSolid
                                    class="h-4 w-4"
                                    aria-hidden="true"
                                  />
                                </button>
                              </div>
                            </div>
                          {/if}
                        </div>
                      </div>
                    {/each}
                  </div>
                </div>
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
        </div>
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
