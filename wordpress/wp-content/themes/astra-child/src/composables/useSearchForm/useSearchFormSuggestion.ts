import { ref, type Ref } from "vue";

export function useSuggestions(searchStore: ReturnType<
  typeof import("@/stores").useSearchStore>,
  handleSubmit: () => void): {
    selectedSuggestionIndex: Ref<number>;
    handleFocus: () => void;
    navigateSuggestions: (direction: number) => void;
    selectSuggestion: (suggestion: string) => void;
    hideSuggestionsImmediate: () => void;
  } {
  const selectedSuggestionIndex = ref(-1);

  function handleFocus(): void {
    if (searchStore.hasSuggestions) {
      searchStore.showSuggestions = true;
      selectedSuggestionIndex.value = -1;
    }
  }

  function navigateSuggestions(direction: number): void {
    if (!searchStore.showSuggestions || !searchStore.hasSuggestions) return;

    const maxIndex = searchStore.suggestions.length - 1;

    if (direction > 0) {
      selectedSuggestionIndex.value =
        selectedSuggestionIndex.value < maxIndex
          ? selectedSuggestionIndex.value + 1
          : 0;
    } else {
      selectedSuggestionIndex.value =
        selectedSuggestionIndex.value > 0
          ? selectedSuggestionIndex.value - 1
          : maxIndex;
    }
  }

  function selectSuggestion(suggestion: string): void {
    searchStore.selectSuggestion(suggestion);
    selectedSuggestionIndex.value = -1;
    handleSubmit();
  }

  function hideSuggestionsImmediate(): void {
    searchStore.showSuggestions = false;
    selectedSuggestionIndex.value = -1;
  }

  return {
    selectedSuggestionIndex,
    handleFocus,
    navigateSuggestions,
    selectSuggestion,
    hideSuggestionsImmediate,
  };
}
