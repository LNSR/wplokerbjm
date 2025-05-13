document.addEventListener("alpine:init", () => {
  Alpine.data("dynamicSearch", () => ({
    loading: false,
    jobsHtml: "",
    searchJobs() {
      this.loading = true;
      const form = this.$el;
      const params = new URLSearchParams(new FormData(form)).toString();

      fetch(`/wp-json/astra-child/v1/search/?${params}`)
        .then((res) => res.json())
        .then((data) => {
          this.jobsHtml = data.html;
          document.getElementById("jobs-list").innerHTML = data.html;
          const jobsList = document.getElementById("jobs-list");
          if (jobsList) {
            jobsList.scrollIntoView({ behavior: "smooth", block: "start" });
          }
        })
        .finally(() => (this.loading = false));
    },
  }));
});
