import Swiper from "swiper";
import { createApp, type App } from "vue";
import type { Job } from "@/types/Job";
import { Navigation, Pagination, Autoplay, Virtual } from "swiper/modules";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import "swiper/css/virtual";
import { useJobOverlayStore } from "@/stores/JobOverlay";
import { AppRouter } from "@/app/Router";
import { RouterService } from "@/services/RouterService";
import JobCard from "@/components/Homepage/JobCard.vue";
import { container } from "@inversify/inversify/inversify.config";

Swiper.use([Navigation, Pagination, Autoplay, Virtual]);

class JobCarousel<T = unknown> {
  private swiperInstance: Swiper | null = null;
  private selector: string;

  constructor(selector = ".job-carousel") {
    this.selector = selector;
  }

  getSwiperConfig(slides: T[], onVirtualUpdate?: () => void) {
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

  initSwiper(slides: T[] = [], onVirtualUpdate?: () => void): void {
    const el = document.querySelector(this.selector);
    if (el) el.classList.remove("invisible");
    this.swiperInstance = new Swiper(
      this.selector,
      this.getSwiperConfig(slides, onVirtualUpdate)
    );
    onVirtualUpdate && onVirtualUpdate();
  }

  updateSlides(slides: T[]): void {
    if (this.swiperInstance?.virtual) {
      this.swiperInstance.virtual.slides = slides;
      this.swiperInstance.virtual.update(false);
    }
  }

  static getBatchSize(): number {
    if (window.innerWidth >= 1024) return 6;
    if (window.innerWidth >= 640) return 4;
    return 2;
  }

  static mountVirtualSlides(jobs: Job[]) {
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

      const appRouter = container.get(AppRouter);
      if (appRouter.router.currentRoute.value.path !== "/") {
        if (slug) {
          appRouter.router.push({ name: "JobDetail", params: { slug } });
        }
      }
    }

    function mountNextBatch(deadline?: IdleDeadline) {
      const batchSize = JobCarousel.getBatchSize();
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
                totalJobs: jobs.length,
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
}

export function useSwiper<T = unknown>(selector = ".job-carousel") {
  const carousel = new JobCarousel<T>(selector);
  return {
    initSwiper: carousel.initSwiper.bind(carousel),
    updateSlides: carousel.updateSlides.bind(carousel),
  };
}

export function mountVirtualSlides(jobs: Job[]) {
  JobCarousel.mountVirtualSlides(jobs);
}
