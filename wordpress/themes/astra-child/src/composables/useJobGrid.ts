import { computed, ref, onMounted, onBeforeUnmount, watch } from "vue";
import { useSearchStore } from "@/stores/search";
import { useJobOverlayStore } from "@/stores/job-overlay";
import { getJobSlugFromId } from "@/services/RouterService";
import { useRouter } from "vue-router";
import { useRouterWatcher } from "@/composables/useRouterWatcher";
import type { Job, SearchFilters } from "@/types";

export function useJobGrid(props: {
  jobs?: Job[];
  maxNumPages?: number;
  context?: "search" | "archive";
  filters?: Partial<SearchFilters>;
  title?: string;
  totalJobs?: number;
}) {
  const searchStore = useSearchStore();
  const jobs = computed(() => searchStore.jobs);
  const loading = computed(() => searchStore.loading);
  const hasMore = computed(() => searchStore.hasMore);
  const loadMore = searchStore.loadMore;

  const router = useRouter();

  const hydrated = ref(false);
  const sentinel = ref<HTMLElement | null>(null);
  let observer: IntersectionObserver | null = null;

  const jobOverlay = useJobOverlayStore();
  const overlayOpen = computed(() => jobOverlay.overlayOpen);
  const selectedId = computed(() => jobOverlay.selectedId);

  const scrollBehavior = ref<"auto" | "smooth">("auto");

  const totalJobs = computed(() => searchStore.totalJobs);
  const title = computed(() => searchStore.title);

  useRouterWatcher(jobs);

  function createObserver() {
    if (observer) observer.disconnect();
    observer = new window.IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore.value && !loading.value) {
          loadMore();
        }
      },
      { root: null, rootMargin: "0px", threshold: 0.1 }
    );
    if (sentinel.value) observer.observe(sentinel.value);
  }

  function openOverlay(id: number) {
    const jobsWithPermalink = jobs.value.filter(
      (j): j is { id: number; permalink: string } =>
        typeof j.permalink === "string"
    );
    const slug = getJobSlugFromId(jobsWithPermalink, id);
    jobOverlay.openOverlay(id, slug ?? undefined);
    scrollBehavior.value = "smooth";

    const job = jobs.value.find((j) => j.id === id);
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

  function handleJobClick(job: Job) {
    if (!job.permalink) return;
    if (window.innerWidth >= 768) {
      openOverlay(job.id);
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
  
  // Hydrate the search store with initial jobs and other props
  onMounted(() => {
    if (!hydrated.value && props.jobs && props.jobs.length) {
      searchStore.jobs = [...props.jobs];
      hydrated.value = true;
      if (props.maxNumPages) searchStore.maxNumPages = props.maxNumPages;
      if (props.context) searchStore.context = props.context;
      if (props.title) searchStore.title = props.title;
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
    hasMore,
    loadMore,
    searchStore,
    hydrated,
    sentinel,
    overlayOpen,
    selectedId,
    scrollBehavior,
    totalJobs,
    title,
    handleOverlayClose,
    handleJobClick,
    wpAdminBarOffset,
    createObserver,
  };
}
