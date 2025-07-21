import Swiper from "swiper";
import {
  createApp,
  type App,
  onMounted,
  nextTick,
  defineAsyncComponent,
  type Ref,
} from "vue";
import type { Job } from "@/types/job";
import { Navigation, Pagination, Autoplay, Virtual } from "swiper/modules";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import "swiper/css/virtual";
import { useJobOverlayStore } from "@/stores/job-overlay";
import { useApi } from "@/composables/useApi";
import { router } from "@/app/router";
import { RouterService } from "@/services/RouterService";
import { useRouterWatcher } from "@/composables/useRouterWatcher";

Swiper.use([Navigation, Pagination, Autoplay, Virtual]);

const JobCard = defineAsyncComponent(
  () => import("@/components/homepage/JobCard.vue")
);

// Swiper configuration generator
function getSwiperConfig<T>(slides: T[], onVirtualUpdate?: () => void) {
  return {
    loop: false,
    slidesPerView: 1.3,
    spaceBetween: 16,
    virtual: {
      enabled: true,
      slides,
      renderSlide: (_slide: T, index: number) =>
        `<div class="swiper-slide" data-swiper-slide-index="${index}">
          <div class="virtual-slide-content" data-job-index="${index}"></div>
        </div>`,
    },
    autoplay: {
      delay: 5000,
      disableOnInteraction: false,
    },
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
    },
    navigation: {
      nextEl: ".job-carousel-next",
      prevEl: ".job-carousel-prev",
    },
    breakpoints: {
      640: {
        slidesPerView: 2,
        spaceBetween: 24,
      },
      1024: {
        slidesPerView: 4,
        spaceBetween: 32,
      },
    },
    on: {
      slidesUpdated: () => onVirtualUpdate && onVirtualUpdate(),
    },
  };
}

export function useSwiper<T = unknown>(selector = ".job-carousel") {
  let swiperInstance: Swiper | null = null;

  function initSwiper(slides: T[] = [], onVirtualUpdate?: () => void): void {
    swiperInstance = new Swiper(
      selector,
      getSwiperConfig(slides, onVirtualUpdate)
    );
    document.querySelector(selector)?.classList.remove("invisible");
    onVirtualUpdate && onVirtualUpdate();
  }

  function updateSlides(slides: T[]): void {
    if (swiperInstance?.virtual) {
      swiperInstance.virtual.slides = slides;
      swiperInstance.virtual.update(false);
    }
  }

  return { initSwiper, updateSlides };
}

export function getBatchSize(): number {
  if (window.innerWidth >= 1024) return 6; // Desktop
  if (window.innerWidth >= 640) return 4; // Tablet
  return 2; // Mobile
}

export function mountVirtualSlides(jobs: Job[]) {
  const jobOverlay = useJobOverlayStore();
  const slides = Array.from(
    document.querySelectorAll<HTMLElement>(".virtual-slide-content")
  );
  let i = 0;

  function handleCarouselJobClick(jobId: number) {
    jobOverlay.openOverlay(jobId);
    const jobsForSlug = jobs
      .filter(
        (j): j is { id: number; permalink: string } =>
          typeof j.permalink === "string"
      )
      .map((j) => ({ id: j.id, permalink: j.permalink! }));
    const slug = RouterService.getJobSlugFromId(jobsForSlug, jobId);
    if (slug) {
      router.push({ name: "JobDetail", params: { slug } });
    }
  }

  function mountNextBatch(deadline?: IdleDeadline) {
    const batchSize = getBatchSize();
    let processed = 0;
    while (
      i < slides.length &&
      processed < batchSize &&
      (!deadline || deadline.timeRemaining() > 0)
    ) {
      const slide = slides[i] as HTMLElement & { __vue_app__?: App<Element> };
      if (!slide.__vue_app__) {
        const indexAttr = slide.getAttribute("data-job-index");
        if (indexAttr !== null) {
          const index = Number(indexAttr);
          const jobData = jobs[index];
          if (jobData) {
            const app = createApp(JobCard, {
              jobdata: jobData,
              permalink: jobData.permalink ?? "",
              variant: "carousel",
              onClick: () => handleCarouselJobClick(jobData.id),
            });
            app.mount(slide);
            slide.__vue_app__ = app;
          }
        }
      }
      i++;
      processed++;
    }
    if (i < slides.length) {
      if ("requestIdleCallback" in window) {
        window.requestIdleCallback(mountNextBatch);
      } else {
        setTimeout(mountNextBatch, 16);
      }
    }
  }
  mountNextBatch();
  if (i < slides.length) {
    if ("requestIdleCallback" in window) {
      window.requestIdleCallback(mountNextBatch);
    } else {
      setTimeout(mountNextBatch, 16);
    }
  }
}

export function useJobCarousel(options: {
  jobs: Ref<Job[]>;
  loaded: Ref<boolean>;
}) {
  const { jobs, loaded } = options;
  const { loading, fetchCarousel } = useApi();
  const { initSwiper } = useSwiper(".job-carousel");

  async function loadCarousel() {
    try {
      const data = await fetchCarousel();
      jobs.value = data.jobs || [];
      loaded.value = true;

      await nextTick();
      initSwiper(jobs.value, () => mountVirtualSlides(jobs.value));
    } catch {
      jobs.value = [];
    }
  }

  onMounted(() => {
    const el = document.getElementById("job-carousel");
    if (!el) return;
    loadCarousel();
  });

  useRouterWatcher(jobs);

  return {
    jobs,
    loaded,
    loading
  };
}
