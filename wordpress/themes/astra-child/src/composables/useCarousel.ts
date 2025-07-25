import { onMounted, nextTick, type Ref } from "vue";
import { useSwiper, mountVirtualSlides } from "@/composables/useCarousel/useSwiper";
import { useRouterWatcher } from "@/composables/useRouterWatcher";

export function useJobCarousel(options: {
  jobs: Ref<any[]>;
  loaded: Ref<boolean>;
  propAttribute?: string;
}) {
  const { jobs, loaded, propAttribute = "data-props" } = options;
  const { initSwiper } = useSwiper(".job-carousel");

  onMounted(async () => {
    const el = document.getElementById("job-carousel");
    if (el) {
      try {
        const propAttr = el.getAttribute(propAttribute);
        const props = JSON.parse(propAttr || "{}");
        jobs.value = props.jobs || [];
        loaded.value = true;
        await nextTick();
        initSwiper(jobs.value, () => mountVirtualSlides(jobs.value));
      } catch (e) {
        jobs.value = [];
        loaded.value = true;
        console.error("Failed to parse job carousel props:", e);
      }
    }
  });

  useRouterWatcher(jobs);

  return {
    jobs,
    loaded,
  };
}
