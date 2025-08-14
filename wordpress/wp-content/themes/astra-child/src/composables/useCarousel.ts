import { onMounted, nextTick, type Ref } from "vue";
import { JobCarousel } from "@/composables/useCarousel/useSwiper";
import { useRouterOverlayWatcher } from "@/composables/Router/useRouterOverlayWatcher";
import { container } from "@/inversify.config";

export function useJobCarousel(options: {
  jobs: Ref<any[]>;
  loaded: Ref<boolean>;
}) {
  const carousel = container.get(JobCarousel);
  const { jobs, loaded } = options;
  const initSwiper = carousel.initSwiper.bind(carousel);

  onMounted(async () => {
    loaded.value = true;
    await nextTick();
  // await initSwiper to ensure Swiper modules and CSS are loaded
  await initSwiper(jobs.value, () => carousel.mountVirtualSlides(jobs.value));
  });

  useRouterOverlayWatcher(jobs);

  return {
    jobs,
    loaded,
  };
}
