export type BookmarkModalComponent = typeof import("@components/ui/Header/BookmarkModal.svelte").default;
export type CustomDropdownComponent = typeof import("@components/ui/Homepage/CustomDropdown.svelte").default;
export type HomepageComponent = typeof import("@routes/Homepage.svelte").default;
export type PasangIklanLokerComponent = typeof import("@routes/PasangIklanLoker.svelte").default;
export type SingleLowonganComponent = typeof import("@routes/SingleLowongan.svelte").default;
export type KebijakanPrivasiComponent = typeof import("@/app/routes/KebijakanPrivasi.svelte").default;
class DynamicComponentStore {

  BookmarkModal: BookmarkModalComponent | null = $state(null);

  CustomDropdown: CustomDropdownComponent | null = $state(null);

  Homepage: HomepageComponent | null = $state(null);
  PasangIklanLoker: PasangIklanLokerComponent | null = $state(null);

  SingleLowongan: SingleLowonganComponent | null = $state(null);
  KebijakanPrivasi: KebijakanPrivasiComponent | null = $state(null);

  public async loadBookmarkModal(): Promise<BookmarkModalComponent> {
    if (this.BookmarkModal) return this.BookmarkModal;
    try {
      this.BookmarkModal = (await import("@components/ui/Header/BookmarkModal.svelte")).default;
      return this.BookmarkModal;
    } catch (error) {
      console.error("Failed to load BookmarkModal:", error);
      throw error;
    }
  }

  public async loadCustomDropdown(): Promise<CustomDropdownComponent> {
    if (this.CustomDropdown) return this.CustomDropdown;
    try {
      this.CustomDropdown = (await import("@components/ui/Homepage/CustomDropdown.svelte")).default;
      return this.CustomDropdown;
    } catch (error) {
      console.error("Failed to load CustomDropdown:", error);
      throw error;
    }
  }

  public async loadHomepage(): Promise<HomepageComponent> {
    if (this.Homepage) return this.Homepage;
    try {
      this.Homepage = (await import("@routes/Homepage.svelte")).default;
      return this.Homepage;
    } catch (error) {
      console.error("Failed to load Homepage:", error);
      throw error;
    }
  }

  public async loadPasangIklanLoker(): Promise<PasangIklanLokerComponent> {
    if (this.PasangIklanLoker) return this.PasangIklanLoker;
    try {
      this.PasangIklanLoker = (await import("@routes/PasangIklanLoker.svelte")).default;
      return this.PasangIklanLoker;
    } catch (error) {
      console.error("Failed to load PasangIklanLoker:", error);
      throw error;
    }
  }

  public async loadSingleLowongan(): Promise<SingleLowonganComponent> {
    if (this.SingleLowongan) return this.SingleLowongan;
    try {
      this.SingleLowongan = (await import("@routes/SingleLowongan.svelte")).default;
      return this.SingleLowongan;
    } catch (error) {
      console.error("Failed to load SingleLowongan:", error);
      throw error;
    }
  }

  public async loadKebijakanPrivasi(): Promise<KebijakanPrivasiComponent> {
    if (this.KebijakanPrivasi) return this.KebijakanPrivasi;
    try {
      this.KebijakanPrivasi = (await import("@/app/routes/KebijakanPrivasi.svelte")).default;
      return this.KebijakanPrivasi;
    } catch (error) {
      console.error("Failed to load KebijakanPrivasi:", error);
      throw error;
    }
  }
}

export const dynamicComponentStore = new DynamicComponentStore();