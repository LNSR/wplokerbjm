<script lang="ts">
  import { onMount } from "svelte";
  import { goto } from "$app/navigation";
  import type { DOMAttributes } from "svelte/elements";
  import BookmarkButton from "@components/ui/Shared/BookmarkButton.svelte";
  import LoadingSpinner from "@components/ui/Shared/LoadingSpinner.svelte";
  import { routeStateStore, routeStore } from "$lib/stores/Route.svelte";
  import {
    CalendarSolid,
    ExclamationTriangleSolid,
    ThumbTackSolid,
    UserTieSolid,
  } from "svelte-awesome-icons";
  import type {
    DeadlineStatus,
    JobCardProps,
    StatusPekerjaanNumber,
    StatusPekerjaanString,
  } from "@/types";
  import { deviceDetector } from "$lib/features/DeviceDetector.svelte";
  import {
    showDeadline,
    showStatusJob,
    showSummaryJob,
    showTimeAgo,
  } from "@/lib/composables/JobUI.svelte";
  interface Props {
    jobdata: JobCardProps["jobdata"];
    variant: JobCardProps["variant"];
    permalink: NonNullable<JobCardProps["jobdata"]>["permalink"];
    onclick?: DOMAttributes<HTMLAnchorElement>["onclick"];
  }
  const {
    jobdata = undefined,
    variant = undefined,
    permalink = undefined,
    onclick = undefined,
  }: Props = $props();

  const isMobile = $derived(deviceDetector.isPlatformMobile);

  const selected = $derived.by(() => {
    const slugMatch = routeStateStore.lastVisitedJob.slug === jobdata?.slug;
    const expectedSource = variant;
    const sourceMatch =
      routeStateStore.lastVisitedJob.source === expectedSource;
    return slugMatch && sourceMatch;
  });

  // show spinner overlay when mobile navigating for the card currently selected by slug
  const spinnerVisible = $derived(isMobile && routeStore.isLoading && selected);

  // Derived UI helpers (keeps UI reactive to prop changes)
  const summaryRows = $derived(
    showSummaryJob(jobdata?.ringkasanPekerjaan).filter(
      (row) => row.label !== "Deadline",
    ),
  );
  // showStatusJob now returns a single status string
  const statusInfo: StatusPekerjaanString | "" = $derived(
    showStatusJob(jobdata?.status_pekerjaan ?? (0 as StatusPekerjaanNumber)),
  );
  const deadlineInfo: { text: string; status: DeadlineStatus } = $derived(
    showDeadline(jobdata?.ringkasanPekerjaan?.deadline ?? ""),
  );
  const timeAgo = $derived(showTimeAgo(jobdata?.post_time ?? ""));

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

  function handleClick(
    event: MouseEvent & { currentTarget: EventTarget & HTMLAnchorElement },
  ) {
    const { ctrlKey, metaKey, shiftKey, button } = event;
    if (ctrlKey || metaKey || shiftKey || button === 1) return;
    event.preventDefault();

    // If onclick prop is provided, use it for all navigation (prevents duplicate handling)
    if (onclick) {
      onclick(event);
      return;
    }

    if (isMobile) {
      if (permalink)
        void goto(new URL(permalink, window.location.origin).pathname);
      return;
    }
  }

  onMount(() => {
    routeStateStore.restoreVisitedJob();
  });
</script>

<div
  class={`group relative min-w-0 ${cardClass}`}
  data-job-slug={jobdata?.slug}
  data-job-source={variant}
>
  <a href={permalink} class="contents" onclick={handleClick}>
    <div class={bodyClass}>
      <div class="flex min-w-0 flex-auto flex-col justify-start">
        <div
          class="mb-2 flex min-w-0 gap-2 flex-row sm:items-start justify-between"
        >
          <div class="flex flex-col min-w-0 justify-start">
            <h3
              class="card-title min-w-0 font-bold leading-tight transition-colors group-hover:text-[var(--wpl-global-color-1)] md:text-xl"
            >
              {jobdata?.title}
            </h3>
          </div>

          <div
            class="flex min-w-0 flex-col items-center gap-2 sm:w-auto justify-end"
          >
            <time
              class="text-shadow-md min-w-0 break-words text-left font-semibold text-[var(--wpl-global-color-1)] sm:text-center"
              datetime={jobdata?.post_time}
            >
              {timeAgo}
            </time>
          </div>
        </div>

        {#if !jobdata?.nama_perusahaan}
          <div class="divider mt-0"></div>
        {:else}
          <h4 class="mb-6 flex min-w-0 items-start gap-2 text-lg font-bold">
            <UserTieSolid
              class="mt-0.5 inline-block h-5 w-5 shrink-0 text-[var(--wpl-global-color-1)] md:h-6 md:w-6"
              aria-hidden="true"
            />
            <span class="min-w-0 break-words leading-tight"
              >{jobdata?.nama_perusahaan}</span
            >
          </h4>
          <div class="divider -mt-4"></div>
        {/if}

        <div
          class="mb-2 flex min-w-0 flex-wrap gap-x-4 gap-y-1 text-[var(--wpl-global-color-1)]"
        >
          {#each summaryRows as row (row.label)}
            {@const Icon = row.icon}
            <span
              class="flex min-w-0 items-start gap-2 py-1 text-base font-semibold md:text-base"
            >
              {#if Icon}
                <Icon
                  class="mt-0.5 h-4 w-4 shrink-0 text-[var(--wpl-global-color-1)] sm:h-5 sm:w-5"
                  aria-hidden="true"
                />
              {/if}
              <span class="min-w-0 break-words leading-tight"
                >{row.value ?? ""}</span
              >
            </span>
          {/each}
        </div>
      </div>

      <div class="divider my-2"></div>

      <div
        class="flex min-w-0 flex-wrap items-start justify-between gap-3 font-semibold"
      >
        {#if statusInfo}
          <span
            class={`badge flex h-auto min-w-0 items-start gap-1 rounded px-3 py-1 font-semibold ${statusClass}`}
          >
            {#if statusInfo === "Urgent"}
              <ExclamationTriangleSolid
                class="mt-0.5 h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4"
                aria-hidden="true"
              />
            {:else if statusInfo === "Pinned"}
              <ThumbTackSolid
                class="mt-0.5 h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4"
                aria-hidden="true"
              />
            {/if}
            <span class="min-w-0 break-words leading-tight">{statusInfo}</span>
          </span>
        {/if}

        {#if deadlineInfo.text}
          <span
            class={`badge flex h-auto min-w-0 items-start gap-1 rounded px-3 py-1 font-semibold ${deadlineClass}`}
          >
            <CalendarSolid
              class="mt-0.5 h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4"
              aria-hidden="true"
            />
            <span class="min-w-0 break-words leading-tight"
              >{deadlineInfo.text}</span
            >
          </span>
        {/if}
        <div class="ml-auto flex shrink-0 items-center gap-1">
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
    @apply flex card-base w-full min-w-0 max-w-full hover:shadow-lg hover:border-[var(--wpl-global-color-1)] flex-col;
  }

  .card-selected-carousel {
    @apply ring-2 ring-[var(--wpl-global-color-1)] border-[var(--wpl-global-color-1)] transition-transform translate-y-5;
  }

  .card-base-featured {
    @apply card-base w-full min-w-0 h-full hover:shadow-xl hover:border-[var(--wpl-global-color-1)] hover:scale-[1.02] hover:border-solid;
  }

  .card-selected-featured {
    @apply ring-4 ring-[var(--wpl-global-color-1)] border-[var(--wpl-global-color-1)] transition-transform scale-[1.03];
  }

  .card-body-carousel {
    @apply card-body relative p-3 gap-0 flex flex-col w-full min-w-0 min-h-[300px] h-full;
  }

  .card-body-featured,
  .card-body-bookmark {
    @apply card-body relative p-3 sm:p-4 gap-1 flex flex-col w-full min-w-0 h-full;
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
