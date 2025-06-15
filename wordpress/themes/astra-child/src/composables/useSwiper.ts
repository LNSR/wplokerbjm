import Swiper from 'swiper'
import { createApp, type App } from 'vue'
import JobCard from '@/components/homepage/JobCard.vue'
import type { Job } from '@/types/job'
import { Navigation, Pagination, Autoplay, Virtual } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import 'swiper/css/virtual'
import { onMounted, nextTick, type Ref } from 'vue'

Swiper.use([Navigation, Pagination, Autoplay, Virtual])

export function useSwiper<T = unknown>(selector = '.job-carousel') {
  let swiperInstance: Swiper | null = null

  function initSwiper(
    slides: T[] = [],
    onVirtualUpdate?: () => void
  ): void {
    setTimeout(() => {
      swiperInstance = new Swiper(selector, {
        loop: false,
        slidesPerView: 1.3,
        spaceBetween: 16,
        virtual: {
          enabled: true,
          slides: slides,
          renderSlide: function (_slide: T, index: number) {
            return `<div class="swiper-slide" data-swiper-slide-index="${index}">
              <div class="virtual-slide-content" data-job-index="${index}"></div>
            </div>`
          },
        },
        autoplay: {
          delay: 5000,
          disableOnInteraction: false,
        },
        pagination: {
          el: '.swiper-pagination',
          clickable: true,
        },
        navigation: {
          nextEl: '.job-carousel-next',
          prevEl: '.job-carousel-prev',
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
          slidesUpdated: () => {
            if (onVirtualUpdate) onVirtualUpdate()
          }
        }
      })
      document.querySelector(selector)?.classList.remove('invisible')
      if (onVirtualUpdate) onVirtualUpdate()
    }, 0)
  }

  function updateSlides(slides: T[]): void {
    if (swiperInstance && swiperInstance.virtual) {
      swiperInstance.virtual.slides = slides
      swiperInstance.virtual.update(false)
    }
  }

  return { initSwiper, updateSlides }
}

export function getBatchSize(): number {
  if (window.innerWidth >= 1024) return 6   // Desktop
  if (window.innerWidth >= 640) return 4    // Tablet
  return 2                                  // Mobile
}

export function mountVirtualSlides(jobs: Job[]) {
  const slides = Array.from(document.querySelectorAll<HTMLElement>('.virtual-slide-content'))
  let i = 0

  function mountNextBatch(deadline?: IdleDeadline) {
    const batchSize = getBatchSize()
    let processed = 0
    while (i < slides.length && processed < batchSize && (!deadline || deadline.timeRemaining() > 0)) {
      const slide = slides[i]
      const slideWithApp = slide as HTMLElement & { __vue_app__?: App<Element> }
      if (!slideWithApp.__vue_app__) {
        const indexAttr = slide.getAttribute('data-job-index')
        if (indexAttr !== null) {
          const index = Number(indexAttr)
          const jobData: Job | undefined = jobs[index]
          if (jobData) {
            const app = createApp(JobCard, {
              jobdata: jobData,
              permalink: jobData.permalink ?? '',
              variant: 'carousel'
            })
            app.mount(slide)
            slideWithApp.__vue_app__ = app
          }
        }
      }
      i++
      processed++
    }
    if (i < slides.length) {
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(mountNextBatch)
      } else {
        setTimeout(mountNextBatch, 16)
      }
    }
  }
  mountNextBatch()
}

export function useJobCarousel(options: {
  jobs: Ref<Job[]>,
  loaded: Ref<boolean>,
  fetchCarousel: () => Promise<any>,
  loading: Ref<boolean>
}) {
  const { jobs, loaded, fetchCarousel } = options
  const { initSwiper } = useSwiper('.job-carousel')

  async function loadCarousel() {
    try {
      const data = await fetchCarousel()
      jobs.value = data.jobs || []
      loaded.value = true

      await nextTick()
      initSwiper(jobs.value, () => mountVirtualSlides(jobs.value))
    } catch (e) {
      jobs.value = []
    }
  }

  onMounted(() => {
    const el = document.getElementById('job-carousel')
    if (!el) return

    const observer = new window.IntersectionObserver((entries) => {
      if (entries[0].isIntersecting && !loaded.value) {
        loadCarousel()
        observer.disconnect()
      }
    })
    observer.observe(el)
  })
}