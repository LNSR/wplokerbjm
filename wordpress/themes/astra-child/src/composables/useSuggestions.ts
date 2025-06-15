import { ref } from 'vue'
import { useSearchStore } from '@/stores'

export function useSuggestions(
  searchStore: ReturnType<typeof useSearchStore>,
  handleSubmit: () => void
) {
  const selectedSuggestionIndex = ref(-1)

  function handleFocus() {
    if (searchStore.hasSuggestions) {
      searchStore.showSuggestions = true
      selectedSuggestionIndex.value = -1
    }
  }

  function navigateSuggestions(direction: number) {
    if (!searchStore.showSuggestions || !searchStore.hasSuggestions) return

    const maxIndex = searchStore.suggestions.length - 1

    if (direction > 0) {
      selectedSuggestionIndex.value = selectedSuggestionIndex.value < maxIndex
        ? selectedSuggestionIndex.value + 1
        : 0
    } else {
      selectedSuggestionIndex.value = selectedSuggestionIndex.value > 0
        ? selectedSuggestionIndex.value - 1
        : maxIndex
    }
  }

  function selectSuggestion(suggestion: string) {
    searchStore.selectSuggestion(suggestion)
    selectedSuggestionIndex.value = -1
    handleSubmit()
  }

  function hideSuggestionsImmediate() {
    searchStore.showSuggestions = false
    selectedSuggestionIndex.value = -1
  }

  return {
    selectedSuggestionIndex,
    handleFocus,
    navigateSuggestions,
    selectSuggestion,
    hideSuggestionsImmediate
  }
}