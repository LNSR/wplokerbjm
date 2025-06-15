export const dom = {
  updateSearchResults(html: string, containerId = '#search-results'): void {
    const container = document.querySelector(containerId)
    if (container) {
      container.innerHTML = html
    }
  },

  scrollToElement(selector: string, offset = 0): void {
    const element = document.querySelector(selector)
    if (element) {
      const top = element.getBoundingClientRect().top + window.pageYOffset - offset
      window.scrollTo({ top, behavior: 'smooth' })
    }
  }
}