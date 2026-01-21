<script lang="ts">
  import { generalStore } from "$lib/stores/General.svelte";
  import BookmarkButton from "@components/ui/Shared/BookmarkButton.svelte";
  import { timeEffect } from "$lib/utils/elements.svelte";
  import { jobOverlay } from "$lib/stores/JobOverlay.svelte";
  import { GlobalNavigateTo, routeStore } from "$lib/stores/Route.svelte";
  import { isMobile } from "$lib/utils/elements.svelte";
  import {
    UserTieSolid,
    CalendarSolid,
    ExclamationTriangleSolid,
    ThumbTackSolid,
  } from "svelte-awesome-icons";
  import { SvelteDate } from "svelte/reactivity";
  import type { CardJob, JobCardProps } from "@/types";

  const {
    jobdata = {},
    variant = "carousel",
    permalink = "",
    onClick,
    isVisited = false, // Prop to mark as last visited (e.g., for the last visited job on mobile)
  } = $props<{
    jobdata: CardJob;
    variant: JobCardProps["variant"];
    permalink: string;
    onClick?: (slug: string, event: MouseEvent, index: number) => void;
    isVisited?: boolean;
  }>();

  const now = $state(new SvelteDate());

  $effect(() => {
    timeEffect(now);
  });

  // Derived UI helpers (keeps UI reactive to prop changes)
  const summaryRows = $derived.by(() =>
    generalStore.useSummaryJob(jobdata?.ringkasanPekerjaan)
  );
  const statusInfo = $derived.by(() =>
    generalStore.useStatusJob(Number(jobdata?.status_pekerjaan ?? 0))
  );
  const deadlineInfo = $derived.by(() =>
    generalStore.useDeadline(jobdata?.deadline, now)()
  );
  const timeAgo = $derived.by(() =>
    generalStore.useTimeAgo(jobdata?.post_time, now)
  );

  const selected = $derived.by(() => {
    try {
      // Only select if there's an active overlay selection OR the job is visited(for mobile)
      const overlaySelected =
        jobOverlay.selectedSlug && jobOverlay.selectedSlug === jobdata?.slug;
      return overlaySelected || isVisited;
    } catch {
      return false;
    }
  });

  const cardClass = $derived.by(() => {
    return `card-base-${variant}${selected ? ` card-selected-${variant}` : ""}`;
  });

  const bodyClass = $derived.by(() => {
    return `card-body-${variant}`;
  });

  async function handleClick(event: MouseEvent) {
    const { ctrlKey, metaKey, shiftKey, button } = event as MouseEvent;
    if (ctrlKey || metaKey || shiftKey || button === 1) return;

    // If onClick prop is provided, use it for all navigation (prevents duplicate handling)
    if (onClick) {
      event.preventDefault();
      const slug = jobdata?.slug ?? "";
      onClick(slug, event, 0);
      return;
    }

    if (isMobile()) {
      event.preventDefault();
      if (permalink)
        void GlobalNavigateTo(new URL(permalink, routeStore.currentUrl.origin).pathname);
      return;
    }

    // For desktop/tablet: prevent default and handle overlay
    event.preventDefault();

    if (variant === "carousel") {
      const slug = jobdata?.slug ?? "";
      // If parent provided an onClick handler (common in grids), delegate and avoid
      // performing a local scroll here — the parent will open overlay and handle a
      // robust scroll that respects sticky/fixed headers.
      if (onClick) {
        event.preventDefault();
        onClick(slug, event, 0);
        return;
      }

      // Otherwise handle opening overlay. Delegate scrolling to the centralized
      // jobOverlay manager so carousel, grid and card all use the same logic.
      await jobOverlay.openOverlay(slug, jobdata);
      // Let the overlay manager handle scrolling after it opens. Prefer the carousel card when present.
      jobOverlay.scrollToCard(slug, 220, true, 'carousel');
      return;
    }
  }
</script>

<div class={`group ${cardClass}`} data-job-slug={jobdata?.slug} data-job-source={variant === 'carousel' ? 'carousel' : 'grid'}>
  <a href={permalink} class="contents" onclick={handleClick}>
    <div class={bodyClass}>
      <div class="flex-1 flex flex-col justify-start">
        <div class="flex items-center justify-between mb-2 gap-x-2">
          <h3
            class="card-title font-bold md:text-xl group-hover:text-blue-700 transition-colors"
          >
            {jobdata?.title}
          </h3>
          <div class="flex items-center gap-2">
            <time
              class="text-lg font-semibold text-center text-[var(--wpl-global-color-1)]"
              datetime={jobdata?.post_time}
            >
              {timeAgo()}
            </time>
          </div>
        </div>

        {#if !jobdata?.nama_perusahaan}
          <div class="divider mt-0"></div>
        {:else}
          <h4 class="font-bold text-lg flex items-center gap-2 mb-6">
            <UserTieSolid
              class="h-6 w-6 text-[var(--wpl-global-color-1)] inline-block"
              aria-hidden="true"
            />
            {jobdata?.nama_perusahaan}
          </h4>
          <div class="divider -mt-4"></div>
        {/if}

        <div
          class="flex flex-wrap gap-x-4 gap-y-1 mb-2 text-[var(--wpl-global-color-1)]"
        >
          {#each summaryRows as row (row.label)}
            {#if row.label !== "Deadline"}
              {@const Icon = row.icon}
              <span
                class="flex items-center text-base md:text-base font-semibold gap-2 py-1"
              >
                {#if Icon}
                  <Icon
                    class="text-[var(--wpl-global-color-1)] w-5 h-5 shrink-0"
                    aria-hidden="true"
                  />
                {/if}
                <span>{row.value ?? ""}</span>
              </span>
            {/if}
          {/each}
        </div>
      </div>

      <div class="divider my-2"></div>

      <div class="flex items-center justify-between font-semibold">
        {#if statusInfo.label}
          <span
            class={[
              "flex items-center badge gap-1 px-3 py-1 font-semibold rounded",
              statusInfo.color,
            ].join(" ")}
          >
            {#if statusInfo.label === "Urgent"}
              <ExclamationTriangleSolid class="h-4 w-4" aria-hidden="true" />
            {:else if statusInfo.label === "Pinned"}
              <ThumbTackSolid class="h-4 w-4" aria-hidden="true" />
            {/if}
            {statusInfo.label}
          </span>
        {/if}
        <div class="flex items-center gap-1 ml-auto">
          {#if deadlineInfo.text}
            <span
              class={[
                "flex items-center badge gap-1 px-3 py-1 font-semibold rounded",
                deadlineInfo.style,
              ].join(" ")}
            >
              <CalendarSolid class="h-4 w-4" aria-hidden="true" />
              <span>{deadlineInfo.text}</span>
            </span>
          {/if}

          <!-- Bookmark button migrated to Svelte component -->
          <BookmarkButton jobId={Number(jobdata.id)} {variant} />
        </div>
      </div>
    </div>
  </a>
</div>

<style lang="postcss">
  @reference "@css/app.css";
  div[data-job-slug] {
    /* Make native scrolling consider header height if browser supports this CSS property. */
    scroll-margin-top: var(--site-scroll-padding-top, 0px);
  }
  @utility card-base {
    @apply card block rounded-xl cursor-pointer border-2 border-blue-500 bg-base-200 dark:bg-base-100/50;
  }

  .card-base-carousel {
    @apply card-base max-w-full hover:shadow-lg hover:border-blue-400;
  }

  .card-selected-carousel {
    @apply ring-2 ring-blue-600 border-blue-700 transition-transform translate-y-5;
  }

  .card-base-featured {
    @apply card-base w-full h-full hover:shadow-xl hover:border-blue-600 hover:scale-[1.02] hover:border-solid;
  }

  .card-selected-featured {
    @apply ring-4 ring-blue-500 border-blue-700 transition-transform scale-[1.03];
  }

  .card-body-carousel {
    @apply card-body relative p-3 gap-0 flex flex-col min-h-[300px] h-full;
  }

  .card-body-featured {
    @apply card-body relative p-4 gap-1 flex flex-col h-full;
  }
</style>
