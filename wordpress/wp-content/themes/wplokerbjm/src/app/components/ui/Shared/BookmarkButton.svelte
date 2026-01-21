<script lang="ts">
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import type { JobCardProps, WPBasePost } from "@/types";
  import { BookmarkSolid, TrashAltSolid } from "svelte-awesome-icons";

  const { jobId, variant = undefined } = $props<{
    jobId: WPBasePost["id"];
    variant: JobCardProps["variant"];
  }>();

  const isJobSaved = $derived(bookmarkStore.jobs.some(job => (Number(job.id) === jobId)));
  const toggleSave = (id: number) => bookmarkStore.toggleSave(id);

  let isLoading = $state(false);
  let isHovered = $state(false);
  let confirmationState: "saved" | "removed" | null = $state(null);
  let errorState: "save" | "remove" | null = $state(null);
  let isPending = $state(false);
  let isTouchDevice = $state(false);

  let preToggleSaved = $state(false);
  // synchronous lock to prevent same-tick re-entrancy from multiple rapid DOM clicks
  let _clickLock = false;

  async function handleToggleSave(e: MouseEvent) {
    // prevent parent handlers
    e.preventDefault();
    e.stopPropagation();
    if (isNaN(jobId) || jobId < 1) return;
    // protect against both reactive loading state and synchronous re-entry
    if (isLoading || _clickLock) return;

    _clickLock = true;

    isLoading = true;
    preToggleSaved = isJobSaved;
    isPending = true;
    try {
      const wasSaved = isJobSaved;
      await toggleSave(jobId);
      isPending = false;

      if (!wasSaved) {
        confirmationState = "saved";
      } else {
        confirmationState = "removed";
      }

    } catch {
      isPending = false;
      const wasSaved = preToggleSaved;
      errorState = wasSaved ? "remove" : "save";
    } finally {
      isLoading = false;
      _clickLock = false;
    }
  }

  const buttonSizeClass = $derived.by(() => {
    switch (variant) {
      case "carousel":
        return "btn-sm btn-circle ";
      case "featured":
      case "detail":
        return "btn-md btn-circle ";
      default:
        return "btn-md btn-circle ";
    }
  });

  function useBookmarkStyle(
    saved: boolean,
    showConfirmation: boolean
  ): { style: string } {
    if (!saved) return { style: "text-gray-600" };
    if (showConfirmation) return { style: "text-green-700" };
    return { style: "text-red-700" };
  }

  const bookmarkStyle = $derived.by(() =>
    useBookmarkStyle(isJobSaved, confirmationState === "saved")
  );

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
      case "detail":
        return "h-5 w-5 ";
      default:
        return "h-5 w-5 ";
    }
  });

  $effect(() => {
    isTouchDevice = "ontouchstart" in window || navigator.maxTouchPoints > 0;
  });

  $effect(() => {
    if (confirmationState !== null) {
      const timeout = setTimeout(() => (confirmationState = null), 1000);
      return () => clearTimeout(timeout);
    }
  });

  $effect(() => {
    if (errorState !== null) {
      const timeout = setTimeout(() => (errorState = null), 3000);
      return () => clearTimeout(timeout);
    }
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
      bookmarkStyle.style}
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

  {#if !isTouchDevice && isHovered && !isLoading}
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
