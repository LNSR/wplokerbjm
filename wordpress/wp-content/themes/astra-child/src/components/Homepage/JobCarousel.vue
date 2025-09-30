<template>
  <section class="min-h-[450px] md:min-h-[400px] lg:min-h-[500px]">
    <div class="flex items-center md:justify-between mb-4">
      <h2 class="text-lg font-semibold !mt-4 !mb-0">Lowongan Darurat</h2>
      <div class="hidden sm:flex gap-1">
        <button type="button" class="job-carousel-prev rounded-xs hover:bg-gray-100 transition" aria-label="Sebelumnya"
          tabindex="0">
          <!-- Left Arrow SVG -->
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24">
            <path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round" />
          </svg>
        </button>
        <button type="button" class="job-carousel-next rounded-xs hover:bg-gray-100 transition" aria-label="Berikutnya"
          tabindex="0">
          <!-- Right Arrow SVG -->
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24">
            <path d="M9 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round" />
          </svg>
        </button>
      </div>
    </div>
    <div v-if="jobs.length" class="swiper job-carousel invisible" role="region" aria-label="Lowongan Darurat">
      <div class="swiper-wrapper">
        <!-- Virtual slides will be dynamically created by Swiper -->
      </div>
      <div class="flex justify-center !mt-24">
        <div class="swiper-pagination"></div>
      </div>
    </div>
    <p v-else class="text-center text-gray-500">Belum ada lowongan darurat</p>
  </section>
</template>
<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import type { Job, CarouselProps, CardJob, JobCarousel } from '@/types'
import { useRouterOverlayWatcher } from '@/composables/Router/useRouterOverlayWatcher'
import { container } from '@/inversify.config'
import { type AppRouter } from '@/app'
import Swiper from "swiper";
import { type createApp, type App } from "vue";
import { Navigation, Pagination, Autoplay, Virtual } from "swiper/modules";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import "swiper/css/virtual";
import { useJobOverlayStore } from "@/stores";
import JobCard from "@/components/Homepage/JobCard.vue";

Swiper.use([Navigation, Pagination, Autoplay, Virtual]);

function getBatchSize(): number {
  if (window.innerWidth >= 1024) return 6;
  if (window.innerWidth >= 640) return 4;
  return 2;
}

function createJobCarousel<T = unknown>(router: AppRouter, selector = ".job-carousel"): JobCarousel<T> {
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
          } catch {
            // Navigation failed, continue
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

const props = defineProps<CarouselProps>()

const jobs = ref<Job[]>(props.jobs || [])
const loaded = ref(false)

// Get router and create carousel instance (business logic: setup)
const router = container.get<AppRouter>("AppRouter")
const carousel = createJobCarousel(router)

onMounted(async () => {
  loaded.value = true
  await nextTick()
  carousel.initSwiper(jobs.value, () => carousel.mountVirtualSlides(jobs.value))
})

useRouterOverlayWatcher(jobs)
</script>