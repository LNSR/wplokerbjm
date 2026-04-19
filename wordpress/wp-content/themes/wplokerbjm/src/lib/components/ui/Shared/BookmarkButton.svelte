<script lang="ts">
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import type { JobCardProps, WPBasePost } from "@/types";
  import { BookmarkSolid, TrashAltSolid } from "svelte-awesome-icons";
  import { componentRegistry } from "@/lib/stores/ComponentRegistry.svelte";
  import { useRIC } from "$lib/utils/window.svelte";
  import { onDestroy } from "svelte";
  import { deviceDetector } from "@/lib/features/DeviceDetector.svelte";

  interface Props {
    jobId: WPBasePost["id"];
    variant: JobCardProps["variant"];
  }
  const { jobId, variant = undefined }: Props = $props();

  const isJobSaved = $derived(
    bookmarkStore.jobs.some((job) => Number(job.id) === jobId),
  );
  const isDesktop = $derived(deviceDetector.isPlatformDesktop);

  let isLoading = $state(false);
  let isHovered = $state(false);
  let confirmationState: "saved" | "removed" | null = $state(null);
  let errorState: "save" | "remove" | null = $state(null);
  let isPending = $state(false);

  let preToggleSaved = $state(false);
  let bookmarkModalLoaded = false;
  let clicklock = false;

  /**
   * Toggle saved state for a job id. If saved, remove it; otherwise add it.
   */
  function toggleSave(id: number): void {
    if (bookmarkStore.jobs.some((job) => job.id === id)) {
      return void bookmarkStore.removeJob(id);
    }

    return void bookmarkStore.addJob(id);
  }

  function preloadBookmarkModal() {
    if (bookmarkModalLoaded) return;
    useRIC(
      async () => {
        if (!componentRegistry.getComponentByName("BookmarkModal")) {
          await componentRegistry.loadComponentByName("BookmarkModal");
          bookmarkModalLoaded = true;
        }
      },
      { fallbackDelay: 200, fallback: "timeout", timeout: 2000 },
    );
  }

  function resetConfirmation() {
    confirmationState = null;
    errorState = null;
  }

  function handleToggleSave(e: MouseEvent) {
    // prevent parent handlers
    e.preventDefault();
    e.stopPropagation();
    if (isNaN(jobId) || jobId < 1) return;

    // If this tab is outdated (a newer build is open elsewhere), do a cache-reload fetch then force navigation.
    if (typeof window !== "undefined" && bookmarkStore.outdatedStatus) {
      return;
    }

    const startingOperation = () => {
      clicklock = true;
      isLoading = true;
      preToggleSaved = isJobSaved;
      isPending = true;
    };

    const saveOperation = () => {
      toggleSave(jobId);
      isPending = false;
      if (!isJobSaved) {
        confirmationState = "saved";
      } else {
        confirmationState = "removed";
      }
    };

    const saveFailed = (err: unknown) => {
      isPending = false;
      const wasSaved = preToggleSaved;
      errorState = wasSaved ? "remove" : "save";
      console.error(
        `Failed to ${wasSaved ? "remove bookmark for" : "save bookmark for"} job ${jobId}:`,
        err,
      );
    };

    const finishOperation = () => {
      isLoading = false;
      clicklock = false;
      const duration =
        confirmationState !== null ? 400 : errorState !== null ? 1000 : 400; // shorter duration if only showing error
      setTimeout(() => {
        resetConfirmation();
      }, duration);
    };

    // protect against both reactive loading state and synchronous re-entry
    if (isLoading || clicklock) return;
    preloadBookmarkModal();

    startingOperation();
    try {
      saveOperation();
    } catch (err) {
      saveFailed(err);
    } finally {
      finishOperation();
    }
  }

  const buttonSizeClass = $derived.by(() => {
    switch (variant) {
      case "carousel":
        return "btn-sm btn-circle ";
      case "featured":
        return "btn-md btn-circle ";
      default:
        return "btn-md btn-circle ";
    }
  });

  const bookmarkStyle = $derived.by(() => {
    if (!isJobSaved) return "text-gray-600";
    if (confirmationState === "saved") return "text-green-700";
    return "text-red-700";
  });

  // New reactive icon spec: name and optional classes (color override)
  const displayedIconSpec = $derived.by(() => {
    if (isPending) {
      return preToggleSaved
        ? { name: "trash", classes: "text-red-400" }
        : { name: "bookmark", classes: "text-[var(--wpl-global-color-1)] " };
    }
    const saved = isJobSaved;
    return saved
      ? { name: "trash", classes: "text-red-400" }
      : { name: "bookmark", classes: "text-[var(--wpl-global-color-1)] " };
  });

  const borderIconSpec = $derived.by(() => {
    if (isPending) {
      return preToggleSaved
        ? "border-red-400 "
        : "border-[var(--wpl-global-color-1)] ";
    }

    const saved = isJobSaved;
    if (saved) {
      return "border-red-400 ";
    } else {
      return "border-[var(--wpl-global-color-1)] ";
    }
  });

  const iconSizeClass = $derived.by(() => {
    switch (variant) {
      case "carousel":
        return "h-4 w-4 ";
      case "featured":
        return "h-5 w-5 ";
      default:
        return "h-5 w-5 ";
    }
  });
  onDestroy(() => {
    resetConfirmation();
  });
</script>

<div class="relative flex items-center">
  <button
    type="button"
    onclick={handleToggleSave}
    onmouseenter={() => (isHovered = true)}
    onmouseleave={() => (isHovered = false)}
    class={"rounded-full flex items-center justify-center border-1 hover:border-2 transition-colors duration-300 " +
      buttonSizeClass +
      borderIconSpec +
      (isLoading ? " !opacity-50 cursor-not-allowed " : "") +
      bookmarkStyle}
    disabled={isLoading}
    title={isJobSaved ? "Hapus bookmark" : "Simpan lowongan"}
    aria-label="Bookmark job"
    aria-pressed={isJobSaved}
  >
    {#if displayedIconSpec.name === "bookmark"}
      <BookmarkSolid
        class={`${iconSizeClass} ${displayedIconSpec.classes}`}
        aria-hidden="true"
      />
    {:else}
      <TrashAltSolid
        class={`${iconSizeClass} ${displayedIconSpec.classes}`}
        aria-hidden="true"
      />
    {/if}
  </button>

  {#if isDesktop && isHovered && !isLoading}
    {@const hapus = isJobSaved}
    <div class="absolute -top-8 right-0 flex items-center pointer-events-none">
      <div
        class={`${hapus ? "bg-red-400 " : "bg-[var(--wpl-global-color-1)] "} text-white text-xs font-semibold px-2 py-1 rounded shadow-sm`}
      >
        {hapus ? "Hapus?" : "Simpan?"}
      </div>
    </div>
  {/if}

  {#if confirmationState !== null}
    <div
      class="absolute -top-8 right-0 flex items-center"
      role="status"
      aria-live="polite"
    >
      {#if confirmationState === "saved"}
        <div
          class="bg-green-600 text-white text-xs font-semibold px-2 py-1 rounded shadow-sm"
        >
          Tersimpan
        </div>
      {:else}
        <div
          class="bg-gray-700 text-white text-xs font-semibold px-2 py-1 rounded shadow-sm"
        >
          Terhapus
        </div>
      {/if}
    </div>
  {/if}

  {#if errorState !== null}
    <div
      class="absolute -top-8 right-0 flex items-center"
      role="alert"
      aria-live="assertive"
    >
      <div
        class="bg-red-600 text-white text-xs font-semibold px-2 py-1 rounded shadow-sm"
      >
        {errorState === "save" ? "Gagal menyimpan" : "Gagal menghapus"}
      </div>
    </div>
  {/if}
</div>
