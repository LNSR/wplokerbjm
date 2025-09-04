import { onMounted, nextTick, type Ref } from "vue";
import { useRouterOverlayWatcher } from "@/composables/Router/useRouterOverlayWatcher";
import { createJobCarousel } from "./useCarousel/useSwiper";
import { container } from "@/inversify.config";
import { type AppRouter } from "@/app";
import type { CardJob } from "@/types";

export function useJobCarousel(options: {
  jobs: Ref<CardJob[]>;
  loaded: Ref<boolean>;
}): {
  jobs: Ref<CardJob[]>;
  loaded: Ref<boolean>;
} {
  const router = container.get<AppRouter>("AppRouter");
  const carousel = createJobCarousel(router);
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
