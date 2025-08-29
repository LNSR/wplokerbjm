import { computed, ref, onMounted, onBeforeUnmount, watch } from "vue";
import { useSearchStore } from "@/stores/Search";
import { useJobOverlayStore } from "@/stores/JobOverlay";
import { useRouter } from "vue-router";
import { useRouterOverlayWatcher } from "@/composables/Router/useRouterOverlayWatcher";
import type { CardJob, JobGridProps } from "@/types";

export function useJobGrid(props: JobGridProps = {}) {
  const searchStore = useSearchStore();
  const jobs = computed(() => searchStore.jobs);
  const loading = computed(() => searchStore.loading);
  const hasMore = computed(() => searchStore.hasMore);
  const loadMore = searchStore.loadMore;

  const router = useRouter();

  const sentinel = ref<HTMLElement | null>(null);
  let observer: IntersectionObserver | null = null;

  const jobOverlay = useJobOverlayStore();
  const overlayOpen = computed(() => jobOverlay.overlayOpen);
  const selectedSlug = computed(() => jobOverlay.selectedSlug);

  const scrollBehavior = ref<"auto" | "smooth">("auto");

  const totalJobs = computed(() => searchStore.totalJobs);
  const title = computed(() => searchStore.title);

  const selectedPermalink = computed(() => {
    return jobOverlay.selectedJob?.permalink ??
      jobs.value.find((job) => job.slug === selectedSlug.value)?.permalink;
  });

  useRouterOverlayWatcher(jobs);

  function createObserver() {
    if (observer) observer.disconnect();
    observer = new window.IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasMore.value && !loading.value) {
          loadMore();
        }
      },
      { root: null, rootMargin: "0px", threshold: 0.1 }
    );
    if (sentinel.value) observer.observe(sentinel.value);
  }

  function openOverlay(slug: string) {
    const job = jobs.value.find((j) => j.slug === slug);
    jobOverlay.openOverlay(slug, job);
    scrollBehavior.value = "smooth";
    if (job && job.permalink && window.innerWidth >= 768) {
      const url = new URL(job.permalink, window.location.origin);
      router.push(url.pathname + url.search + url.hash);
    }
  }

  function handleOverlayClose() {
    jobOverlay.closeOverlay();
    if (window.innerWidth >= 768) {
      router.push("/");
    }
  }

  function handleJobClick(job: CardJob) {
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
    const bar = document.getElementById("wpadminbar");
    if (bar) {
      wpAdminBarOffset.value = bar.offsetHeight + "px";
    } else {
      wpAdminBarOffset.value = "0px";
    }
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
    sentinel,
    overlayOpen,
    selectedSlug,
    totalJobs,
    title,
    handleOverlayClose,
    handleJobClick,
    wpAdminBarOffset,
    jobOverlay,
    selectedPermalink
  };
}
