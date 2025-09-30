import { computed, ref, onMounted, onBeforeUnmount, watch, type ComputedRef, type Ref } from "vue";
import { getWpAdminBarTopOffset } from "@/utils/elements";
import { useSearchStore, useJobOverlayStore } from "@/stores";
import { useRouter } from "vue-router";
import { useRouterOverlayWatcher } from "@/composables/Router/useRouterOverlayWatcher";
import type { CardJob, JobGridProps } from "@/types";

export function useJobGrid(props: JobGridProps = {}): {
  jobs: ComputedRef<CardJob[]>;
  loading: ComputedRef<boolean>;
  searchStore: ReturnType<typeof useSearchStore>;
  sentinel: Ref<Element | null>;
  overlayOpen: ComputedRef<boolean>;
  selectedSlug: ComputedRef<string | null>;
  totalJobs: ComputedRef<number>;
  title: ComputedRef<string>;
  handleOverlayClose: () => void;
  handleJobClick: (job: CardJob) => void;
  wpAdminBarOffset: Ref<string>;
  selectedPermalink: ComputedRef<string | undefined>;
} {
  const searchStore = useSearchStore();
  const jobs = computed(() => searchStore.jobs);
  const loading = computed(() => searchStore.loading);
  const hasMore = computed(() => searchStore.hasMore);
  const loadMore = searchStore.loadMore;

  const router = useRouter();

  const sentinel = ref<Element | null>();
  let observer: IntersectionObserver | null = null;

  const jobOverlay = useJobOverlayStore();
  const overlayOpen = computed(() => jobOverlay.overlayOpen);
  const selectedSlug = computed(() => jobOverlay.selectedSlug);

  const totalJobs = computed(() => searchStore.totalJobs);
  const title = computed(() => searchStore.title);

  const selectedPermalink = computed(() => {
    return jobOverlay.selectedJob?.permalink ??
      jobs.value.find((job) => job.slug === selectedSlug.value)?.permalink;
  });

  useRouterOverlayWatcher(jobs);

  function createObserver(): void {
    if (observer) observer.disconnect();
    observer = new window.IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasMore.value && !loading.value) {
          loadMore();
        }
      },
      { root: null, rootMargin: "0px", threshold: 0.1 }
    );
  if (sentinel.value) observer.observe(sentinel.value as Element);
  }

  function openOverlay(slug: string): void {
    const job = jobs.value.find((j) => j.slug === slug);
    jobOverlay.openOverlay(slug, job);
    if (job && job.permalink && window.innerWidth >= 768) {
      const url = new URL(job.permalink, window.location.origin);
      router.push(url.pathname + url.search + url.hash);
    }
  }

  function handleOverlayClose(): void {
    jobOverlay.closeOverlay();
    if (window.innerWidth >= 768) {
      router.push("/");
    }
  }

  function handleJobClick(job: CardJob): void {
    if (!job.permalink) return;
    if (window.innerWidth >= 768) {
      openOverlay(job.slug ?? "");
    } else {
      try {
        const url = new URL(job.permalink, window.location.origin);
        if (url.host === window.location.host) {
          window.location.assign(url.pathname + url.search + url.hash);
        } else {
          window.location.href = job.permalink;
        }
      } catch {
        window.location.href = job.permalink;
      }
    }
  }
  // Calculate the height of the WordPress admin bar
  const wpAdminBarOffset = ref("0px");
  onMounted(() => {
    wpAdminBarOffset.value = getWpAdminBarTopOffset() + 'px';
  });

  onMounted(() => {
    if (props.jobs && props.jobs.length) {
      searchStore.jobs = [...props.jobs];
      if (props.maxNumPages) searchStore.maxNumPages = props.maxNumPages;
      if (props.context) searchStore.context = props.context; // 'Latest'
      if (props.title) searchStore.title = props.title; // Lowongan Terbaru
      if (props.totalJobs !== undefined)
        searchStore.totalJobs = props.totalJobs;
    }
    createObserver();
  });

  onBeforeUnmount(() => {
    if (observer) observer.disconnect();
  });

  // Close overlay when jobs in JobGrid change
  watch(
    () => searchStore.jobs,
    () => {
      if (overlayOpen.value) {
        jobOverlay.closeOverlay();
      }
    }
  );

  return {
    jobs,
    loading,
    searchStore,
  sentinel: sentinel as Ref<Element | null>,
    overlayOpen,
    selectedSlug,
    totalJobs,
    title,
    handleOverlayClose,
    handleJobClick,
    wpAdminBarOffset,
    selectedPermalink
  };
}
