<script lang="ts">
  import { onMount } from "svelte";
  import { SvelteSet, SvelteMap, SvelteDate } from "svelte/reactivity";
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import { generalJobStore } from "@/lib/stores/GeneralJob.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import { useVirtualization } from "@/lib/utils/virtualization.svelte";
  import type { ListVirtualizationState } from "@/lib/utils/virtualization.svelte";
  import type { CardJob } from "@/types";
  import { isMobile } from "$lib/utils/window.svelte";
  import { isJobGridEl, teleportTo } from "$lib/utils/elements.svelte";
  import { useRIC } from "$lib/utils/window.svelte";
  import { jobOverlayManager } from "$lib/stores/JobOverlay.svelte";
  import RefreshSpinner from "@components/ui/Shared/RefreshSpinner.svelte";
  import { goto } from "$app/navigation";
  import { routeStateStore } from "$lib/stores/Route.svelte";
  import JobCard from "@components/ui/Shared/JobCard.svelte";
  import {
    BookmarkSolid,
    XmarkSolid,
    TrashAltSolid,
    ExclamationTriangleSolid,
    ExclamationCircleSolid,
    MagnifyingGlassSolid,
  } from "svelte-awesome-icons";
  import type { Attachment } from "svelte/attachments";

  let { open = $bindable() }: { open: boolean } = $props();

  // This component scope state properties
  let modalEl: HTMLDialogElement;
  let modalBox: HTMLElement;
  let contentRect = $state<DOMRectReadOnly | null>(null);

  // Search state for filtering saved jobs (title and company)
  let searchQuery = $state("");
  let isSearchOpen = $state(false);
  let searchInput = $state<HTMLInputElement | null>(null);

  class BookmarkUIHandler {
    showDeleteConfirm = $state(false);
    removingIds = new SvelteSet<number>();
    deletedJobs = $derived(bookmarkStore.deletedJobs);
    globalLoading = $derived(bookmarkStore.isSyncing);
    refreshing = $state(false);
    refreshTime = new SvelteDate();
    error = $state("");

    constructor() {
      this.refreshTime.setTime(bookmarkStore.lastSyncTime);
    }
    public async fetchJobs(forceRefresh = false): Promise<void> {
      if (!forceRefresh && bookmarkStore.jobs.length === 0) {
        return;
      }
      const STALE_THRESHOLD = 5 * 60 * 1000;
      const now = Date.now();
      const isStale = now - bookmarkStore.lastSyncTime > STALE_THRESHOLD;
      if (!forceRefresh && !isStale && bookmarkStore.jobs.length > 0) return;
      if (this.refreshing || this.globalLoading) return;
      this.refreshing = true;
      this.error = "";
      try {
        await bookmarkStore.syncWithAPI();
      } catch {
        this.error = "Gagal memuat data. Silakan coba lagi.";
      } finally {
        this.refreshing = false;
        this.refreshTime.setTime(bookmarkStore.lastSyncTime);
      }
    }

    public async removeBookmark(id: number): Promise<void> {
      this.removingIds.add(id);
      await new Promise((resolve) => setTimeout(resolve, 200));
      await bookmarkStore.removeJob(id);
      this.removingIds.delete(id);
    }

    public showDeleteAllConfirmation(): void {
      this.showDeleteConfirm = true;
    }

    public DeleteAllApproved(): void {
      this.showDeleteConfirm = false;
      bookmarkStore.jobs.forEach((job) => {
        if (job.id) this.removingIds.add(job.id);
      });
      this.refreshing = true;
      bookmarkStore
        .clearAll()
        .then(() => {
          this.removingIds.clear();
        })
        .catch(() => {
          this.error = "Gagal menghapus semua bookmark. Silakan coba lagi.";
          this.removingIds.clear();
        })
        .finally(() => {
          this.refreshing = false;
        });
    }

    public cancelDeleteAll(): void {
      this.showDeleteConfirm = false;
    }

    public handleClearDeleted(): void {
      bookmarkStore.deletedJobs = [];
    }

    public handleRefresh(): Promise<void> {
      return bookmarkHandler.fetchJobs(true);
    }

    public scheduleFetchJobs = () => {
      const runFetch = () => void bookmarkHandler.fetchJobs();
      useRIC(runFetch, { fallback: "animationFrame" });
    };

    public displayedSavedJobs = $derived.by(() => {
      return bookmarkStore.jobs.map((job) => ({
        ...job,
        timeAgo: generalJobStore.showTimeAgo(job.post_time!),
        deadlineInfo: job.ringkasanPekerjaan?.deadline
          ? generalJobStore.showDeadline(job.ringkasanPekerjaan.deadline)
          : { text: "", status: "unknown" },
        // statusInfo is a single status string now (previously an object with identical label/status)
        statusInfo: job.status_pekerjaan
          ? generalJobStore.showStatusJob(job.status_pekerjaan)
          : "none",
      }));
    });

    public filteredDisplayedJobs = $derived.by(() => {
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
    public formattedLastSync = $derived.by(() => {
      try {
        return this.refreshTime.toLocaleString("en-GB", {
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
  }

  class VirtualizationManager {
    public measuring = $state(false); // measuring: true while card heights are being measured in background to avoid INP
    public containerScrollY = $state(0);
    public cardHeights = new SvelteMap(
      routeStateStore.getCardHeights("bookmarkModal"),
    );
    #measurePollHandle: ReturnType<typeof setInterval> | null = null;
    #measureTimeoutHandle: ReturnType<typeof setTimeout> | null = null;

    public virtualizedJobs: ListVirtualizationState<CardJob> = $derived(
      useVirtualization.computeList({
        displayJobs: bookmarkHandler.filteredDisplayedJobs,
        scrollY: this.containerScrollY,
        containerHeight: contentRect?.height || 0,
        cardHeights: this.cardHeights,
        fallbackHeight: 200,
        gap: 24,
        buffer: 3,
      }),
    );

    public measureHeight(jobId: number): Attachment<HTMLElement> {
      return useVirtualization.createMeasureHeight(this.cardHeights, jobId);
    }

    // Clear card heights that are no longer displayed
    public clearCardHeights() {
      const currentJobIds = new SvelteSet(
        bookmarkHandler.filteredDisplayedJobs.map(
          (job: CardJob) => job.id || 0,
        ),
      );
      const heightsToKeep = new SvelteMap<number, number>();
      for (const [jobId, height] of this.cardHeights) {
        if (currentJobIds.has(jobId)) {
          heightsToKeep.set(jobId, height);
        }
      }
      if (heightsToKeep.size !== this.cardHeights.size) {
        this.cardHeights.clear();
        for (const [k, v] of heightsToKeep) {
          this.cardHeights.set(k, v);
        }
      }
      return this.cardHeights;
    }

    /**
     * Start background measurement: poll cardHeights until visible items measured or timeout
     * !One time measurement
     * */
    public startBackgroundMeasure() {
      // clear any existing handles
      if (this.#measurePollHandle) {
        clearInterval(this.#measurePollHandle);
        this.#measurePollHandle = null;
      }
      if (this.#measureTimeoutHandle) {
        clearTimeout(this.#measureTimeoutHandle);
        this.#measureTimeoutHandle = null;
      }

      this.measuring = true;

      const maxWait = 1000; // ms
      const interval = 80; // ms
      let elapsed = 0;

      this.#measurePollHandle = setInterval(() => {
        try {
          const visible =
            (this.virtualizedJobs && this.virtualizedJobs.visibleJobs) || [];
          const needed = visible.length;

          // Count measured heights for visible IDs
          let measured = 0;
          for (const [id] of this.cardHeights) {
            if (visible.some((v: any) => Number(v.id) === Number(id)))
              measured++;
          }

          if (needed === 0 || measured >= needed) {
            if (this.#measurePollHandle) {
              clearInterval(this.#measurePollHandle);
              this.#measurePollHandle = null;
            }
            if (this.#measureTimeoutHandle) {
              clearTimeout(this.#measureTimeoutHandle);
              this.#measureTimeoutHandle = null;
            }
            this.measuring = false;
          }
        } catch (e) {
          console.error("Error during background measure polling:", e);
        }

        elapsed += interval;
        if (elapsed >= maxWait) {
          if (this.#measurePollHandle) {
            clearInterval(this.#measurePollHandle);
            this.#measurePollHandle = null;
          }
          this.measuring = false;
        }
      }, interval);

      // fallback timeout to stop measuring
      this.#measureTimeoutHandle = setTimeout(() => {
        if (this.#measurePollHandle) {
          clearInterval(this.#measurePollHandle);
          this.#measurePollHandle = null;
        }
        this.measuring = false;
        this.#measureTimeoutHandle = null;
      }, maxWait + 200);
    }

    public stopBackgroundMeasure() {
      if (this.#measurePollHandle) {
        clearInterval(this.#measurePollHandle);
        this.#measurePollHandle = null;
      }
      if (this.#measureTimeoutHandle) {
        clearTimeout(this.#measureTimeoutHandle);
        this.#measureTimeoutHandle = null;
      }
      this.measuring = false;
    }
  }

  /**
   * UI Specific Modal Handler
   */
  class ModalHandler {
    public drag = $state({
      handle: undefined as HTMLElement | undefined,
      isDragging: false,
      activePointerId: null as number | null,
      translate: { x: 0, y: 0 },
      startClient: { x: 0, y: 0 },
      startHeight: 0,
      modalHeight: null as string | null,
    });
    public startDrag = (e: PointerEvent): void => {
      if (e.button && e.button !== 0) return;
      if (!modalBox || !this.drag.handle) return;
      if (!this.drag.handle.contains(e.target as Node)) return;
      try {
        this.drag.handle.setPointerCapture(e.pointerId);
      } catch {
        // ignore
      }
      this.drag.activePointerId = e.pointerId;
      this.drag.isDragging = true;
      this.drag.startClient.x = e.clientX;
      this.drag.startClient.y = e.clientY;
      if (isMobile()) {
        this.drag.startHeight = modalBox?.clientHeight || 0;
      }
      e.preventDefault();
    };

    public onPointerMove = (e: PointerEvent): void => {
      if (!this.drag.isDragging) return;
      if (
        this.drag.activePointerId !== null &&
        e.pointerId !== this.drag.activePointerId
      )
        return;
      if (!modalBox) return;
      if (isMobile()) {
        const dy = e.clientY - this.drag.startClient.y;
        const newH = this.drag.startHeight - dy;
        this.drag.translate.x = 0;
        this.drag.translate.y = 0;
        this.drag.modalHeight = `${newH}px`;
        return;
      } else {
        this.drag.translate.x = e.clientX - this.drag.startClient.x;
        this.drag.translate.y = e.clientY - this.drag.startClient.y;
      }
    };

    public onPointerUp = (e: PointerEvent): void => {
      if (!this.drag.isDragging) return;
      try {
        if (typeof e.pointerId !== "undefined")
          this.drag.handle?.releasePointerCapture(e.pointerId);
      } catch (err) {
        void err;
      }
      this.drag.isDragging = false;
      this.drag.activePointerId = null;
    };
    public resetPosition(): void {
      this.drag.translate.x = 0;
      this.drag.translate.y = 0;
      this.drag.modalHeight = null;
    }

    public handleKeydown = (e: KeyboardEvent): void => {
      if (e.key === "Escape" && open) this.closeModal();
    };

    public handleJobClick(job: CardJob): void {
      routeStateStore.MarkVisitedJob(job.slug ?? "", "bookmark");

      const el: HTMLElement | null = isJobGridEl;

      if (!isMobile() && el) {
        // Desktop: open overlay
        routeStateStore.saveCardHeights(
          new SvelteMap(virtualizationManager.cardHeights),
          "bookmarkModal",
        );
        // mark as "featured" for desktop
        jobOverlayManager?.openOverlay(job.slug ?? "", job, "featured", {
          gotoCB: () => {
            this.closeModal();
          },
        });
      } else {
        // Mobile: navigate
        if (job.permalink) {
          routeStateStore.saveCardHeights(
            new SvelteMap(virtualizationManager.cardHeights),
            "bookmarkModal",
          );
          const url = new URL(job.permalink, window.location.origin);
          goto(url.pathname + url.search + url.hash).then(() => {
            this.closeModal();
          });
        }
      }
    }

    public closeModal(): void {
      open = false;
      virtualizationManager.stopBackgroundMeasure();
    }
  }

  const bookmarkHandler = new BookmarkUIHandler();
  const virtualizationManager = new VirtualizationManager();
  const modalHandler = new ModalHandler();

  const isMobileValue = $derived(isMobile());

  const modalStyle = $derived(
    `transform: translate(${modalHandler.drag.translate.x}px, ${modalHandler.drag.translate.y}px); touch-action: ${isMobileValue ? "none" : "auto"};`,
  );
  const loadingUI = $derived(
    bookmarkHandler.refreshing || virtualizationManager.measuring,
  );
  const isBusy = $derived(bookmarkStore.isSyncing || loadingUI);

  // Only collapse action buttons on small (mobile) layouts when search is active
  const shouldCollapseActions = $derived(
    isSearchOpen && routeStateStore.currentDevice === "mobile",
  );

  function observeOpenCloseDialog(): Attachment<HTMLDialogElement> {
    return (dialog: HTMLDialogElement) => {
      requestAnimationFrame(() => {
        if (
          open &&
          bookmarkHandler.showDeleteConfirm &&
          !dialog.open &&
          dialog.isConnected
        ) {
          dialog.showModal();
        } else if (!bookmarkHandler.showDeleteConfirm && dialog.open) {
          dialog.close();
        }
      });

      return () => {
        dialog.close();
      };
    };
  }

  onMount(() => {
    if (open) {
      bookmarkStore.debouncedSync?.flush();
      bookmarkHandler.scheduleFetchJobs();

      if (!modalEl?.open) modalEl?.showModal();
      if (isMobileValue && modalBox) {
        const vh = window.innerHeight;
        const initialHeight = Math.round(vh * 0.6);
        modalHandler.drag.modalHeight = `${initialHeight}px`;
      }
      // Start measuring container + card heights in background to avoid blocking interaction
      virtualizationManager.startBackgroundMeasure();
    } else {
      modalEl?.close();
      modalHandler.resetPosition();
    }

    // Focus search input when opening modal on desktop if not already open
    if (open && routeStateStore.currentDevice === "desktop" && !isSearchOpen) {
      isSearchOpen = true;
      searchInput?.focus();
    }
    return () => {
      if (open) modalEl?.close();
      if (
        modalHandler.drag.handle &&
        modalHandler.drag.activePointerId !== null
      )
        modalHandler.drag.handle.releasePointerCapture(
          modalHandler.drag.activePointerId,
        );

      modalHandler.resetPosition();
      virtualizationManager.stopBackgroundMeasure();

      virtualizationManager.clearCardHeights();
    };
  });
</script>

<svelte:window
  on:pointermove={modalHandler.onPointerMove}
  on:pointerup={modalHandler.onPointerUp}
/>

<svelte:document on:keydown={modalHandler.handleKeydown} />

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
    style:height={modalHandler.drag.modalHeight}
    onpointerdown={modalHandler.startDrag}
  >
    <!-- Drag Handle -->
    {#if isMobileValue}
      <div
        bind:this={modalHandler.drag.handle}
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
              {#if !isBusy && bookmarkStore.jobs.length > 0}
                <span
                  class="bg-[var(--wpl-global-color-1)] text-[var(--wpl-global-color-5)] text-sm rounded-full px-2 py-0.1 z-10"
                  >{bookmarkStore.jobs.length}</span
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
            {#if !isBusy}
              <button
                onclick={() => {
                  isSearchOpen = !isSearchOpen;
                  if (isSearchOpen) setTimeout(() => searchInput?.focus(), 0);
                }}
                disabled={isBusy ||
                  bookmarkStore.bookmarkBroadcastChannel.isOutdated}
                class="btn btn-ghost btn-sm mr-2"
                aria-label="Cari dalam simpanan"
                title="Cari"
              >
                <MagnifyingGlassSolid class="h-4 w-4" aria-hidden="true" />
              </button>
            {/if}

            {#if !isBusy && isSearchOpen}
              <input
                id="bookmark-search"
                name="bookmark_search"
                bind:this={searchInput}
                bind:value={searchQuery}
                disabled={bookmarkStore.bookmarkBroadcastChannel.isOutdated}
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
            {#if !isBusy && isSearchOpen}
              <button
                onclick={() => {
                  isSearchOpen = false;
                  searchQuery = "";
                  try {
                    searchInput?.blur();
                  } catch (e) {
                    console.error(e);
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
              {#if !isBusy && bookmarkStore.jobs.length > 0}
                <button
                  onclick={() => bookmarkHandler.showDeleteAllConfirmation()}
                  disabled={isBusy ||
                    bookmarkStore.bookmarkBroadcastChannel.isOutdated}
                  class="btn btn-ghost btn-sm md:btn-md text-error w-auto whitespace-nowrap"
                  aria-label="hapus semua"
                  title="hapus semua"
                >
                  <TrashAltSolid class="h-4 w-4 mr-2" aria-hidden="true" />
                  Hapus Semua
                </button>
                <button
                  onclick={() => bookmarkHandler.handleRefresh()}
                  disabled={isBusy ||
                    bookmarkStore.bookmarkBroadcastChannel.isOutdated}
                  class="btn btn-ghost btn-sm md:btn-md w-auto whitespace-nowrap"
                  aria-label="sync ke server"
                  title="sync ke server"
                >
                  <RefreshSpinner size="h-4 w-4 mr-2" spin={isBusy} />
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
      bind:contentRect
      class="flex-1 overflow-y-auto max-h-full px-6 py-4"
      onscroll={(e: UIEvent) => {
        const target = e.target as HTMLElement;
        virtualizationManager.containerScrollY = target.scrollTop;
      }}
    >
      <!-- Loading / initial measuring State -->
      {#if isBusy}
        <div class="flex items-center justify-center py-12">
          <LoadingSpinner srLabel="Memuat..." size="md" />
        </div>

        <!-- Error State -->
      {:else if bookmarkHandler.error}
        <div class="alert alert-error">
          <ExclamationTriangleSolid
            class="h-6 w-6 shrink-0 text-error"
            aria-hidden="true"
          />
          <span>{bookmarkHandler.error}</span>
        </div>

        <!-- Warning State -->
      {:else if bookmarkStore.globalWarning}
        <div class="alert alert-warning">
          <ExclamationTriangleSolid
            class="h-6 w-6 shrink-0 text-warning"
            aria-hidden="true"
          />
          <span>{bookmarkStore.globalWarning}</span>
        </div>

        <!-- Empty State -->
      {:else if bookmarkStore.jobs.length === 0 && bookmarkStore.deletedJobs.length === 0}
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
        {#if bookmarkStore.jobs.length > 0}
          <div class="mb-6">
            <div
              class="flex flex-row items-center justify-between break-words whitespace-normal mb-2"
            >
              <h4 class="font-semibold text-md">
                Tersedia ({bookmarkStore.jobs.length})
              </h4>
              {#if bookmarkStore.lastSyncTime > 0 && !isBusy}
                <div class="text-xs font-semibold flex flex-col">
                  <span class="mb-1 flex items-center gap-1"
                    >Terakhir sync:</span
                  >
                  <span>{bookmarkHandler.formattedLastSync}</span>
                </div>
              {/if}
            </div>

            <div
              class="relative"
              style="height: {virtualizationManager.virtualizedJobs
                .totalHeight}px;"
            >
              {#each virtualizationManager.virtualizedJobs.visibleJobs as job, idx (job.id)}
                {@const absoluteIndex =
                  virtualizationManager.virtualizedJobs.startIndex + idx}
                {@const topPosition =
                  virtualizationManager.virtualizedJobs.itemPositions[
                    absoluteIndex
                  ] || 0}
                <div
                  class="card bg-[var(--wpl-global-color-5)] border-2 border-[var(--wpl-global-color-1)] shadow-sm hover:shadow-md absolute left-0 right-0"
                  class:scale-0={bookmarkHandler.removingIds.has(job.id || 0)}
                  style="transform: translate3d(0, {topPosition}px, 0);"
                  {@attach virtualizationManager.measureHeight(job.id || 0)}
                >
                  {#if job.title === ""}
                    <div class="card-body animate-pulse">
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
                          <div class="h-8 w-8 bg-base-content/20 rounded"></div>
                        </div>
                      </div>
                    </div>
                  {:else}
                    <div class="flex w-full">
                      <div class="flex-1">
                        <JobCard
                          jobdata={job}
                          variant="bookmark"
                          permalink={job.permalink as string}
                          onClick={() => modalHandler.handleJobClick(job)}
                        />
                      </div>

                      <div
                        class="flex flex-shrink-1 border-l-1 border-[var(--wpl-global-color-1)] items-center"
                      >
                        <button
                          onclick={() =>
                            bookmarkHandler.removeBookmark(job.id || 0)}
                          disabled={isBusy ||
                            bookmarkHandler.removingIds.has(job.id || 0)}
                          class="btn btn-xs btn-ghost text-error"
                          title="Hapus bookmark"
                          aria-label="Hapus bookmark untuk {job.title}"
                        >
                          <TrashAltSolid class="h-6 w-6" aria-hidden="true" />
                        </button>
                      </div>
                    </div>
                  {/if}
                </div>
              {/each}
            </div>
          </div>
        {/if}

        <!-- Deleted Jobs -->
        {#if bookmarkHandler.deletedJobs.length > 0}
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="font-semibold text-sm text-base-content/70">
                Tidak Tersedia ({bookmarkHandler.deletedJobs.length})
              </h4>
              <button
                onclick={() => bookmarkHandler.handleClearDeleted()}
                class="btn btn-xs btn-ghost text-error"
                aria-label="Clear all deleted jobs"
              >
                Hapus Semua
              </button>
            </div>
            {#each bookmarkHandler.deletedJobs as id (id)}
              <div class="card bg-base-300 opacity-60">
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
  {@attach observeOpenCloseDialog()}
  {@attach teleportTo("#app")}
  class="modal modal-bottom sm:modal-middle"
  class:modal-open={bookmarkHandler.showDeleteConfirm}
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
        onclick={() => bookmarkHandler.cancelDeleteAll()}
        class="btn btn-ghost"
        disabled={isBusy}
      >
        Batal
      </button>
      <button
        onclick={() => bookmarkHandler.DeleteAllApproved()}
        class="btn btn-error"
        disabled={isBusy}
      >
        {#if bookmarkHandler.refreshing}
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
    onclick={() => bookmarkHandler.cancelDeleteAll()}
    onkeydown={(e: KeyboardEvent) => {
      if (e.key === "Enter" || e.key === " ") bookmarkHandler.cancelDeleteAll();
    }}
  ></div>
</dialog>
