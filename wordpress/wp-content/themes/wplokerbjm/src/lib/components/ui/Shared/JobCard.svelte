<script lang="ts">
  import { onMount } from "svelte";
  import { goto } from "$app/navigation";
  import BookmarkButton from "@components/ui/Shared/BookmarkButton.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import { routeStateStore, routeStore } from "$lib/stores/Route.svelte";
  import {
    CalendarSolid,
    ExclamationTriangleSolid,
    ThumbTackSolid,
    UserTieSolid,
  } from "svelte-awesome-icons";
  import type { DeadlineStatus, JobCardProps, StatusPekerjaanString } from "@/types";
  import { deviceDetector } from "$lib/features/DeviceDetector.svelte";
  import { showDeadline, showStatusJob, showSummaryJob, showTimeAgo } from "@/lib/composables/JobUI.svelte";
  interface Props {
    jobdata: JobCardProps["jobdata"];
    variant: JobCardProps["variant"];
    permalink: NonNullable<JobCardProps["jobdata"]>["permalink"];
    index?: number;
    onClick?: (slug: string, event: MouseEvent, index: number) => void;
  }
  const {
    jobdata = undefined,
    variant = undefined,
    permalink = undefined,
    index = 0,
    onClick = undefined as unknown as (
      slug: string,
      event: MouseEvent,
      index: number,
    ) => void,
  }: Props = $props();

  const isMobile = $derived(deviceDetector.isPlatformMobile);

  const selected = $derived.by(() => {
    const slugMatch = routeStateStore.lastVisitedJob.slug === jobdata?.slug;
    const expectedSource = variant;
    const sourceMatch = routeStateStore.lastVisitedJob.source === expectedSource;
    return slugMatch && sourceMatch;
  });

  // show spinner overlay when mobile navigating for the card currently selected by slug
  const spinnerVisible = $derived(
    isMobile && routeStore.isLoading && selected,
  );

  // Derived UI helpers (keeps UI reactive to prop changes)
  const summaryRows = $derived(
    showSummaryJob(jobdata?.ringkasanPekerjaan),
  );
  // showStatusJob now returns a single status string
  const statusInfo: StatusPekerjaanString | "" = $derived(
    showStatusJob(jobdata?.status_pekerjaan ?? 0),
  );
  const deadlineInfo: { text: string; status: DeadlineStatus } = $derived(
    showDeadline(jobdata?.ringkasanPekerjaan?.deadline ?? ""),
  );
  const timeAgo = $derived(
    showTimeAgo(jobdata?.post_time ?? ""),
  );

  const statusClass = $derived.by(() => {
    switch (statusInfo) {
      case "Urgent":
        return "job-status-urgent";
      case "Pinned":
        return "job-status-pinned";
      default:
        return "";
    }
  });

  const deadlineClass = $derived.by(() => {
    switch (deadlineInfo.status) {
      case "upcoming":
        return "job-deadline-upcoming";
      case "soon":
        return "job-deadline-soon";
      case "last_day":
      case "today":
        return "job-deadline-last-day";
      case "expired_yesterday":
        return "job-deadline-expired-yesterday";
      case "expired":
        return "job-deadline-expired";
      default:
        return "";
    }
  });

  const cardClass = $derived(
    `card-base-${variant}${selected ? ` card-selected-${variant}` : ""}`,
  );

  const bodyClass = $derived(`card-body-${variant}`);

  function handleClick(event: MouseEvent) {
    const { ctrlKey, metaKey, shiftKey, button } = event as MouseEvent;
    if (ctrlKey || metaKey || shiftKey || button === 1) return;

    // If onClick prop is provided, use it for all navigation (prevents duplicate handling)
    if (onClick) {
      event.preventDefault();
      const slug = jobdata?.slug ?? "";
      onClick(slug, event, index ?? 0);
      return;
    }

    if (isMobile) {
      event.preventDefault();
      if (permalink)
        void goto(new URL(permalink, window.location.origin).pathname);
      return;
    }

    // For desktop/tablet: prevent default and handle overlay
    event.preventDefault();
  }

  onMount(() => {
    routeStateStore.restoreVisitedJob();
  });
</script>

<div
  class={`group relative ${cardClass}`}
  data-job-slug={jobdata?.slug}
  data-job-source={variant}
>
  <a href={permalink} class="contents" onclick={handleClick}>
    <div class={bodyClass}>
      <div class="flex-1 flex flex-col justify-start">
        <div class="flex items-center justify-between mb-2 gap-x-2">
          <h3
            class="card-title font-bold md:text-xl group-hover:text-[var(--wpl-global-color-1)] transition-colors"
          >
            {jobdata?.title}
          </h3>
          <div class="flex items-center gap-2">
            <time
              class="text-shadow-md font-semibold text-center text-[var(--wpl-global-color-1)]"
              datetime={jobdata?.post_time}
            >
              {timeAgo}
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

      <div class="flex items-start justify-between font-semibold gap-3">
        {#if statusInfo}
          <span
            class={`flex items-center badge gap-1 px-3 py-1 font-semibold rounded ${statusClass}`}
          >
            {#if statusInfo === "Urgent"}
              <ExclamationTriangleSolid class="h-4 w-4" aria-hidden="true" />
            {:else if statusInfo === "Pinned"}
              <ThumbTackSolid class="h-4 w-4" aria-hidden="true" />
            {/if}
            <span>{statusInfo}</span>
          </span>
        {/if}

        {#if deadlineInfo.text}
          <span
            class={`flex badge gap-1 px-3 py-1 font-semibold rounded ${deadlineClass}`}
          >
            <CalendarSolid class="h-4 w-4" aria-hidden="true" />
            <span>{deadlineInfo.text}</span>
          </span>
        {/if}
        <div class="flex items-center gap-1 ml-auto">
          {#if variant !== "bookmark"}
            <BookmarkButton jobId={Number(jobdata?.id)} {variant} />
          {/if}
        </div>
      </div>
      {#if spinnerVisible}
        <div
          class="absolute inset-0 backdrop-blur-sm flex items-center justify-center z-20 will-change-contents"
        >
          <LoadingSpinner size="lg" srLabel="Memuat..." />
        </div>
      {/if}
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
    @apply card block rounded-xl cursor-pointer border-2 border-[var(--wpl-global-color-1)] bg-[var(--wpl-global-color-5)];
  }

  .card-base-carousel {
    @apply flex card-base max-w-full hover:shadow-lg hover:border-[var(--wpl-global-color-1)] flex-col;
  }

  .card-selected-carousel {
    @apply ring-2 ring-[var(--wpl-global-color-1)] border-[var(--wpl-global-color-1)] transition-transform translate-y-5;
  }

  .card-base-featured {
    @apply card-base w-full h-full hover:shadow-xl hover:border-[var(--wpl-global-color-1)] hover:scale-[1.02] hover:border-solid;
  }

  .card-selected-featured {
    @apply ring-4 ring-[var(--wpl-global-color-1)] border-[var(--wpl-global-color-1)] transition-transform scale-[1.03];
  }

  .card-body-carousel {
    @apply card-body relative p-3 gap-0 flex flex-col min-h-[300px] h-full;
  }

  .card-body-featured,
  .card-body-bookmark {
    @apply card-body relative p-4 gap-1 flex flex-col h-full;
  }

  .job-status-urgent {
    @apply bg-red-600 text-white border border-red-700 shadow-sm text-xs;
  }

  .job-status-pinned {
    @apply bg-yellow-400 text-black border border-yellow-600 shadow-sm text-xs;
  }

  .job-deadline-upcoming {
    @apply bg-blue-600 text-white border border-blue-800 text-xs;
  }

  .job-deadline-soon {
    @apply bg-yellow-400 text-black border border-yellow-600 text-xs;
  }

  .job-deadline-last-day {
    @apply bg-red-600 text-white border border-red-800 text-xs;
  }

  .job-deadline-expired-yesterday {
    @apply bg-gray-500 text-white border border-gray-700 text-xs;
  }

  .job-deadline-expired {
    @apply bg-gray-400 text-black border border-gray-700 text-xs;
  }
</style>
