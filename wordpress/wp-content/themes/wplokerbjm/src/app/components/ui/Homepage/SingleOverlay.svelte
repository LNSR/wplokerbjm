<script module lang="ts">
  import { NonceManager } from "@/utils";
  import type { JobDetailResponse } from "@/types";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import { PenToSquareSolid, CopySolid } from "svelte-awesome-icons";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import JobDetail from "@components/ui/Shared/JobDetail.svelte";
  import SkeletonSingleLowongan from "@components/ui/Skeletons/SkeletonSingleLowongan.svelte";

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

  // Removed: OverlayKeyboardHandler not needed
</script>

<script lang="ts">
  import { onMount, tick } from "svelte";

  const { visible } = $props<{
    visible: boolean;
  }>();

  onMount(() => {
    isLoggedIn = !!NonceManager.getNonce;
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
      `transition-transform duration-600 ease-in-out`,
    ].join(" ")}
  >
    <!-- Overlay background (only in JobGrid area) -->
    <div class="absolute top-0 left-0 right-0 bottom-0"></div>

    <!-- Drawer -->
    <aside
      class="relative shadow-xl rounded-xl border-2 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-5)] w-full max-h-[calc(100vh-var(--site-header-top)-var(--site-header-height))] overflow-y-auto flex flex-col z-50"
    >
      <div
        class="flex absolute top-5 left-4 right-4 justify-between items-center z-10 gap-4"
        role="toolbar"
        aria-label="Overlay controls"
      >
        <div class="flex items-center gap-2">
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

        <div class="flex items-center gap-2">
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
      </div>

      {#if loading}
        <div
          class="p-4 text-center pt-16 flex-1 flex flex-col items-center justify-center"
        >
          <span class="mt-4 mb-4 text-2xl font-bold">Memuat Lowongan...</span>
          <LoadingSpinner srLabel="Memuat..." size="md" />
          <SkeletonSingleLowongan />
        </div>
      {:else if !data && !loading && !error}
        <!-- Placeholder skeleton when no job selected -->
        <div class="p-6 space-y-8 pt-16 flex-1 flex flex-col">
          <span class="text-lg font-bold mb-4 text-center"
            >Silahkan pilih lowongan untuk melihat detailnya.</span
          >
          <SkeletonSingleLowongan />
        </div>
      {:else if error}
        <div class="p-4 text-red-500 pt-16 flex-1">{error}</div>
      {:else if data}
        <div class="p-6 space-y-8 pt-16 flex-1 flex flex-col">
          <JobDetail job={data} />
        </div>
      {/if}
    </aside>
  </div>
{/if}
