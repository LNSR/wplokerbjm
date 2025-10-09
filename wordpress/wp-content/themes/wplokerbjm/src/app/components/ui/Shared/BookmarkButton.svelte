<script lang="ts">
  import { bookmarkStore } from "$lib/stores/Bookmark.svelte";
  import type { JobCardProps } from "@/types";

  let { jobId, variant = undefined } = $props<{
    jobId: number;
    variant: JobCardProps["variant"];
  }>();

  const isSaved = (id: number) => bookmarkStore.isSaved(id);
  const toggleSave = (id: number) => bookmarkStore.toggleSave(id);

  let isLoading = $state(false);
  let isHovered = $state(false);
  let confirmationState: "saved" | "removed" | null = $state(null);
  let errorState: "save" | "remove" | null = $state(null);
  let isPending = $state(false);
  let preToggleSaved = $state(false);
  // synchronous lock to prevent same-tick re-entrancy from multiple rapid DOM clicks
  let _clickLock = false;

  async function handleToggleSave(e: MouseEvent) {
    // prevent parent handlers
    e.preventDefault();
    e.stopPropagation();
    if (!jobId) return;
    // protect against both reactive loading state and synchronous re-entry
    if (isLoading || _clickLock) return;

    _clickLock = true;

    isLoading = true;
    preToggleSaved = isSaved(jobId);
    isPending = true;
    try {
      const wasSaved = isSaved(jobId);
      await toggleSave(jobId);
      isPending = false;

      if (!wasSaved && isSaved(jobId)) {
        confirmationState = "saved";
      }
      if (wasSaved && !isSaved(jobId)) {
        confirmationState = "removed";
      }
      if (confirmationState !== null) {
        setTimeout(() => (confirmationState = null), 1000);
      }

      // small delay to give visual feedback
      await new Promise((r) => setTimeout(r, 1000));
    } catch (err) {
      isPending = false;
      const wasSaved = preToggleSaved;
      errorState = wasSaved ? "remove" : "save";
      setTimeout(() => (errorState = null), 3000);
    } finally {
      isLoading = false;
      _clickLock = false;
    }
  }

  const buttonSizeClass = $derived.by(() => {
    switch (variant) {
      case "carousel":
        return "!btn-sm";
      case "featured":
      case "detail":
        return "!btn-md";
      default:
        return "!btn-md";
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
    useBookmarkStyle(isSaved(jobId), confirmationState === "saved")
  );

  // legacy class-based icon variable removed — using displayedIconSpec instead
  // New reactive icon spec: name and optional classes (color override)
  const displayedIconSpec = $derived.by(() => {
    if (isPending) {
      return preToggleSaved
        ? { name: "trash", classes: "text-red-400" }
        : { name: "bookmark", classes: "text-[var(--wpl-global-color-1)]" };
    }
    const saved = isSaved(jobId);
    if (confirmationState === "saved") return { name: "bookmark", classes: "" };
    return saved
      ? { name: "trash", classes: "text-red-400" }
      : { name: "bookmark", classes: "text-[var(--wpl-global-color-1)]" };
  });

  const iconSizeClass = $derived.by(() => {
    switch (variant) {
      case "carousel":
        return "h-4 w-4";
      case "featured":
      case "detail":
        return "h-5 w-5";
      default:
        return "h-5 w-5";
    }
  });
</script>

<div class="relative flex items-center">
  <button
    type="button"
    onclick={handleToggleSave}
    onmouseenter={() => (isHovered = true)}
    onmouseleave={() => (isHovered = false)}
    class={"rounded-full transition-colors duration-300" +
      buttonSizeClass +
      (isLoading ? " !opacity-50 cursor-not-allowed" : "") +
      bookmarkStyle.style}
    disabled={isLoading}
    title={isSaved(jobId) ? "Hapus bookmark" : "Simpan lowongan"}
    aria-label="Bookmark job"
    aria-pressed={isSaved(jobId)}
  >
    {#if displayedIconSpec.name === "bookmark"}
      <svg
        class={`${iconSizeClass} ${displayedIconSpec.classes}`}
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
        focusable="false"
      >
        <path d="M6 2a2 2 0 00-2 2v17l8-4 8 4V4a2 2 0 00-2-2H6z" />
      </svg>
    {:else}
      <svg
        class={`${iconSizeClass} ${displayedIconSpec.classes}`}
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
    {/if}
  </button>

  {#if isHovered && !isLoading}
    <div class="absolute -top-8 right-0 flex items-center pointer-events-none">
      <div
        class="bg-[var(--wpl-global-color-1)] text-white text-xs font-semibold px-2 py-1 rounded shadow-sm"
      >
        {isSaved(jobId) ? "Hapus?" : "Simpan?"}
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

<style>
  /* relies on Tailwind classes used widely in the project */
</style>
