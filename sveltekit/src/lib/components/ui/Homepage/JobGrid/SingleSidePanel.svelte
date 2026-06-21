<script lang="ts">
  import { page } from "$app/state";
  import { type Attachment } from "svelte/attachments";
  import type { JobDetailResponse } from "@/types";
  import { routeStore } from "@/lib/stores/Route.svelte";
  import { themePropsStore } from "$lib/stores/Theme.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import { PenToSquareSolid, CopySolid } from "svelte-awesome-icons";
  import { useSidePanel } from "@/lib/composables/SidePanel.svelte";
  import JobDetail from "@components/ui/Shared/JobDetail.svelte";
  import SkeletonSingleLowongan from "@components/ui/Skeletons/SkeletonSingleLowongan.svelte";
  import { getCmsOrigin } from "@/utils/environment";

  const data = $derived((page.data?.job as JobDetailResponse | null) ?? null);
  const editPostId = $derived((data?.id as JobDetailResponse["id"]) ?? null);

  const isSidePanelVisible = $derived(
    Boolean(data?.id || useSidePanel.selectedSlug),
  );

  const isLoggedIn = $derived<boolean>(!!themePropsStore.getNonce);
  const action = "duplicate_post_new_draft";

  function getCloneHref(postId?: number | null): string {
    if (!postId || !data?.dpNonce) return "#";
    const base = `${getCmsOrigin()}/wp-admin/admin.php?action=${action}&post=${postId}`;

    try {
      const nonce = data?.dpNonce;
      if (typeof nonce === "string" && nonce.length > 0)
        return `${base}&_wpnonce=${encodeURIComponent(nonce)}`;
    } catch (e) {
      console.error("Error constructing clone URL with nonce:", e);
    }

    return base;
  }

  const drawerElementAttachment: Attachment = (() => {
    let drawerElement: Element | null = null;
    return (node: Element) => {
      data?.id; // re-run when job changes to reset scroll
      drawerElement = node;

      drawerElement.scrollTop = 0;
      return () => {
        drawerElement = null;
      };
    };
  })();
</script>

<svelte:window
  onscroll={() => (useSidePanel.isScrolling ||= true)}
  onscrollend={() => (useSidePanel.isScrolling &&= false)}
/>

<div
  data-last-error=""
  aria-hidden={!isSidePanelVisible}
  class="min-h-screen flex flex-col ml-7 translate-x-6"
>
  <!-- Drawer -->
  <aside
    {@attach drawerElementAttachment}
    class="relative shadow-xl rounded-xl border-2 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-5)] w-full max-h-[calc(100vh-var(--site-scroll-padding-top)-var(--site-header-height))] overflow-y-auto flex flex-col z-50"
  >
    <div
      class="flex absolute top-5 left-4 right-4 justify-between items-center z-10 gap-4"
      role="toolbar"
      aria-label="Overlay controls"
    >
      <div class="flex items-center gap-2">
        {#if !routeStore.isLoading && data && isLoggedIn && editPostId}
          <a
            href={`${getCmsOrigin()}/wp-admin/post.php?post=${editPostId}&action=edit`}
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
        {#if !routeStore.isLoading && data && isLoggedIn && editPostId}
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

    {#if routeStore.isLoading}
      <div
        class="p-4 text-center pt-16 flex-1 flex flex-col items-center justify-center"
      >
        <span class="mt-4 mb-4 text-2xl font-bold">Memuat Lowongan...</span>
        <LoadingSpinner srLabel="Memuat..." size="md" />
        <SkeletonSingleLowongan />
      </div>
    {:else if !data}
      <!-- Placeholder skeleton when no job selected -->
      <div class="p-6 space-y-8 pt-16 flex-1 flex flex-col">
        <span class="text-lg font-bold mb-4 text-center"
          >Silahkan pilih lowongan untuk melihat detailnya.</span
        >
        <SkeletonSingleLowongan />
      </div>
    {:else if data}
      <div class="p-6 space-y-8 pt-16 flex-1 flex flex-col">
        <JobDetail job={data} />
      </div>
    {/if}
  </aside>
</div>
