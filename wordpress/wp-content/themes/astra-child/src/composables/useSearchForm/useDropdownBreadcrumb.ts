import { ref, computed, type Ref, type ComputedRef } from 'vue'
import type { Option } from './useDropdown'

export function useBreadcrumb(): {
  breadcrumb: ComputedRef<string[]>;
  stack: Ref<Option[][]>;
  activeIndex: Ref<number>;
  goBack: () => void;
  goToBreadcrumb: (idx: number) => void;
  pushBreadcrumb: (label: string, children: Option[]) => void;
  resetBreadcrumb: () => void;
} {
  const breadcrumbLabels = ref<string[]>([])
  const stack = ref<Option[][]>([])
  const activeIndex = ref(0)

  const breadcrumb = computed(() => breadcrumbLabels.value)

  function goBack(): void {
    if (stack.value.length) {
      stack.value.pop()
      breadcrumbLabels.value.pop()
      activeIndex.value = 0
    }
  }

  function goToBreadcrumb(idx: number): void {
    stack.value = stack.value.slice(0, idx + 1)
    breadcrumbLabels.value = breadcrumbLabels.value.slice(0, idx + 1)
    activeIndex.value = 0
  }

  function pushBreadcrumb(label: string, children: Option[]): void {
    stack.value.push(children)
    breadcrumbLabels.value.push(label)
    activeIndex.value = 0
  }

  function resetBreadcrumb(): void {
    stack.value = []
    breadcrumbLabels.value = []
    activeIndex.value = 0
  }

  return {
    breadcrumb,
    stack,
    activeIndex,
    goBack,
    goToBreadcrumb,
    pushBreadcrumb,
    resetBreadcrumb,
  }
}