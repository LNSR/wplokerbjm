window.loadMoreJobs = function (
  context = "latest",
  maxPages = 1,
  filters = {}
) {
  return {
    page: 1,
    loading: false,
    hasMore: maxPages > 1,
    context: context,
    maxPages: maxPages,
    filters: filters,
    loadMore() {
      this.loading = true;
      const url = new URL(
        "/wp-json/astra-child/v1/load-more/",
        window.location.origin
      );
      url.searchParams.append("paged", this.page + 1);
      url.searchParams.append("context", this.context);

      for (const [key, value] of Object.entries(this.filters)) {
        if (value) url.searchParams.append(key, value);
      }

      fetch(url.toString())
        .then((res) => res.json())
        .then((data) => {
          if (!data.html || this.page + 1 > this.maxPages) {
            this.hasMore = false;
          } else {
            document
              .getElementById("job-cards")
              .insertAdjacentHTML("beforeend", data.html);
            this.page++;
          }
          this.loading = false;
        });
    },
  };
};
