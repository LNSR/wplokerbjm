import Swiper from "swiper";
import { type createApp, type App } from "vue";
import type { CardJob, JobCarousel } from "@/types";
import { Navigation, Pagination, Autoplay, Virtual } from "swiper/modules";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import "swiper/css/virtual";
import { useJobOverlayStore } from "@/stores";
import { type AppRouter } from "@/app";
import JobCard from "@/components/Homepage/JobCard.vue";
import { container } from "@/inversify.config";

Swiper.use([Navigation, Pagination, Autoplay, Virtual]);

export function getBatchSize(): number {
  if (window.innerWidth >= 1024) return 6;
  if (window.innerWidth >= 640) return 4;
  return 2;
}

export function createJobCarousel<T = unknown>(router: AppRouter, selector = ".job-carousel"): JobCarousel<T> {
  let swiperInstance: Swiper | null = null;

  function getSwiperConfig(slides: T[], onVirtualUpdate?: () => void): object {
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

  function initSwiper(slides: T[] = [], onVirtualUpdate?: () => void): void {
    const el = document.querySelector(selector);
    if (el) el.classList.remove("invisible");
    swiperInstance = new Swiper(
      selector,
      getSwiperConfig(slides, onVirtualUpdate)
    );
    onVirtualUpdate && onVirtualUpdate();
  }

  // Use this method to update the slides in the swiper instance
  // This is useful when you want to change the slides dynamically
  function updateSlides(slides: T[]): void {
    if (swiperInstance?.virtual) {
      swiperInstance.virtual.slides = slides;
      swiperInstance.virtual.update(false);
    }
  }

  function mountVirtualSlides(jobs: CardJob[]): void {
    const jobOverlay = useJobOverlayStore();
    const appRouter = router.createAppRouter();
    const slides = Array.from(
      document.querySelectorAll<HTMLElement>(".virtual-slide-content")
    );
    let i = 0;

    function handleCarouselJobClick(slug: string): void {
      const job = jobs.find(j => j.slug === slug);
      jobOverlay.openOverlay(slug, job);
      if (appRouter.currentRoute.value.path === "/") {
        if (slug) {
          try {
            appRouter.push({ name: "JobDetail", params: { slug } });
          } catch (err) {
            console.error("Router navigation failed:", err, slug);
          }
        }
      }
    }


    function mountNextBatch(deadline?: IdleDeadline): void {
      const jobCardApp = container.get<typeof createApp>("CreateApp");
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
              const app = jobCardApp(JobCard, {
                jobdata: jobData,
                permalink: jobData.permalink ?? "",
                variant: "carousel",
                onClick: () => handleCarouselJobClick(jobData.slug ?? ''),
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

  return {
    initSwiper,
    updateSlides,
    mountVirtualSlides,
    getBatchSize,
  };
}