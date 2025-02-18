function autoSuggestSearch() {
  return {
    query: "",
    suggestions: [],
    show: false,
    loading: false,

    getSuggestions() {
      this.loading = true;

      if (this.query.length < 2) {
        this.suggestions = [];
        this.show = false;
        this.loading = false;
        return;
      }

      const url = new URL(
        "/wp-json/astra-child/v1/auto-suggest/",
        window.location.origin
      );
      url.searchParams.append("query", this.query);

      fetch(url.toString())
        .then((response) => {
          if (!response.ok) {
            this.suggestions = [];
            this.show = false;
            return [];
          }
          return response.json();
        })
        .then((data) => {
          if (Array.isArray(data)) {
            this.suggestions = [...new Set(data.filter((item) => item))];
            this.show = this.suggestions.length > 0;
          } else {
            this.suggestions = [];
            this.show = false;
          }
        })
        .finally(() => {
          this.loading = false;
        });
    },

    selectSuggestion(suggestion) {
      this.query = suggestion;
      this.show = false;
    },
  };
}
window.autoSuggestSearch = autoSuggestSearch;
