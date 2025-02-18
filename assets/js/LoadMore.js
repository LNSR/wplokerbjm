window.loadMoreJobs = function (context = "latest", maxPages = 1) {
  return {
    page: 1,
    loading: false,
    hasMore: maxPages > 1,
    context: context,
    maxPages: maxPages,
    loadMore() {
      this.loading = true;
      const url = new URL(
        "/wp-json/astra-child/v1/load-more/",
        window.location.origin
      );
      url.searchParams.append("paged", this.page + 1);
      url.searchParams.append("context", this.context);
      // Add other params as needed (cari, lokasi, etc.)

      fetch(url.toString())
        .then((res) => res.json())
        .then((data) => {
          if (!data.html || this.page + 1 > this.maxPages) {
            this.hasMore = false;
          } else {
            document
              .getElementById("jobs-list")
              .insertAdjacentHTML("beforeend", data.html);
            this.page++;
          }
          this.loading = false;
        });
    },
  };
};
