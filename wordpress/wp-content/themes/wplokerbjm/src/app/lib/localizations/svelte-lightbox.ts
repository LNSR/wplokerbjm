import { i18n } from "svelte-lightbox";

// Provide Bahasa Indonesia localization for the footer counter
// Keep API compatible: function(activeImage, imageCount) => string
function localized(activeImage: number, imageCount: number): string {
  // "Gambar <current> dari <total>"
  return `Gambar ${activeImage + 1} dari ${imageCount}`;
}

// Overwrite the store value to use the localized function
i18n.set({
  generateLocalizedGalleryCounter: localized,
});

export default i18n;
