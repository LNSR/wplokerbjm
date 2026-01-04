<script module lang="ts">
  import { nonceStore } from "@/utils";
  import type { JobDetailResponse } from "@/types";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import { PenToSquareSolid, CopySolid } from "svelte-awesome-icons";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import { dynamicComponentStore } from "$lib/stores/DynamicComponent.svelte";

  let slideIn = $state(false);

  const data = $derived(jobOverlay.overlayData) as JobDetailResponse | null;
  const loading = $derived(jobOverlay.overlayLoading);
  const error = $derived(jobOverlay.overlayError);

  let isLoggedIn = $state(false);
  let editPostId = $state<number | null>(null);

  function getCloneHref(postId?: number | null): string {
    if (!postId) return "#";
    const base = `/wp-admin/admin.php?action=dt_dpp_post_as_draft&post=${postId}`;

    try {
      const dup = data?.duplicateNonce;
      if (typeof dup === "string" && dup.length > 0)
        return `${base}&nonce=${encodeURIComponent(dup)}`;
    } catch {
      // Ignore errors and return base URL
    }

    return base;
  }

  class OverlayKeyboardHandler {
    static handleKeydown(event: KeyboardEvent, closeFn: () => void): void {
      if (event.key === "Escape") {
        closeFn();
      }
    }
  }
</script>

<script lang="ts">
  import { onMount, tick } from "svelte";

  const { visible, close } = $props<{
    visible: boolean;
    close: () => void;
  }>();

  onMount(() => {
    isLoggedIn = !!nonceStore.getNonce;
    document.addEventListener("keydown", (event) =>
      OverlayKeyboardHandler.handleKeydown(event, close)
    );

    return () => {
      document.removeEventListener("keydown", (event) =>
        OverlayKeyboardHandler.handleKeydown(event, close)
      );
    };
  });

  // Explicitly set slideIn to false first so it shows everytime JobOverlay is opened
  // without setting slideIn to false explicitly, the slideIn only trigger once
  $effect(() => {
    if (visible) {
      slideIn = false;
      tick().then(() => {
        slideIn = true;
      });
    } else {
      slideIn = false;
    }
  });

  // Update editPostId when data changes
  $effect(() => {
    editPostId = data?.id ?? null;
  });
</script>

{#if visible}
  <div
    data-last-error={error}
    class={[
      "min-h-screen flex flex-col pointer-events-auto ml-7",
      slideIn ? "transform translate-x-0" : "transform translate-x-full",
      `transition-transform duration-600 ease-in-out ${visible ? "will-change-[transform]" : ""}`,
    ].join(" ")}
  >
    <!-- Overlay background (only in JobGrid area) -->
    <div
      class="absolute top-0 left-0 right-0 bottom-0"
      onclick={close}
      role="button"
      tabindex="0"
      onkeydown={(e) => e.key === "Enter" && close()}
    ></div>

    <!-- Drawer -->
    <aside
      class="bg-base-200 dark:bg-base-100/50 relative shadow-xl rounded-xl border-2 border-blue-500 w-full max-h-[calc(100vh-var(--site-header-top)-var(--site-header-height))] overflow-y-auto flex flex-col z-50"
    >
      <div
        class="absolute top-5 left-4 right-4 grid grid-cols-3 items-center z-10 gap-4"
        role="toolbar"
        aria-label="Overlay controls"
      >
        <div class="flex items-center gap-2 justify-start">
          {#if !loading && data && isLoggedIn && editPostId}
            <a
              href={`/wp-admin/post.php?post=${editPostId}&action=edit`}
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-sm btn-outline btn-warning flex items-center gap-1"
            >
              <PenToSquareSolid class="mr-1" aria-hidden="true" />
              <span>Edit</span>
            </a>
          {/if}
        </div>

        <div class="flex items-center gap-2 justify-center">
          {#if !loading && data && isLoggedIn && editPostId}
            <a
              href={getCloneHref(editPostId)}
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-sm btn-outline btn-success flex items-center gap-1"
            >
              <CopySolid class="mr-1" aria-hidden="true" />
              <span>Clone Draft</span>
            </a>
          {/if}
        </div>

        <div class="flex items-center gap-2 justify-end">
          <button
            class="btn font-semibold bg-[var(--wpl-global-color-4)] text-[var(--wpl-global-color-1)] border border-[var(--wpl-global-color-1)] hover:bg-[var(--wpl-global-color-1)] hover:text-[var(--wpl-global-color-5)]"
            onclick={close}
            aria-label="Close overlay"
          >
            Tutup
          </button>
        </div>
      </div>

      {#if loading}
        <div
          class="p-4 text-center pt-16 flex-1 flex flex-col items-center justify-center"
        >
          <LoadingSpinner srLabel="Memuat..." size="md" />
          <div class="mt-4">Memuat Lowongan...</div>
        </div>
      {:else if error}
        <div class="p-4 text-red-500 pt-16 flex-1">{error}</div>
      {:else if data}
        <div class="p-6 space-y-8 pt-16 flex-1 flex flex-col">
          {#await dynamicComponentStore.loadJobDetail() then JobDetail}
            <JobDetail job={data} />
          {/await}
        </div>
      {/if}
    </aside>
  </div>
{/if}
