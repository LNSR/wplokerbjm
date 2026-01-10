export type BookmarkModalComponent = typeof import("@components/ui/Header/BookmarkModal.svelte").default;
export type CustomDropdownComponent = typeof import("@components/ui/Homepage/CustomDropdown.svelte").default;
export type HomepageComponent = typeof import("@routes/Homepage.svelte").default;
export type PasangIklanLokerComponent = typeof import("@routes/PasangIklanLoker.svelte").default;
export type SingleLowonganComponent = typeof import("@routes/SingleLowongan.svelte").default;
export type SkeletonHomepageComponent = typeof import("@components/ui/Skeletons/SkeletonHomepage.svelte").default;
export type SkeletonSingleLowonganComponent = typeof import("@components/ui/Skeletons/SkeletonSingleLowongan.svelte").default;
export type SkeletonPasangIklanLokerComponent = typeof import("@components/ui/Skeletons/SkeletonPasangIklanLoker.svelte").default;

class DynamicComponentStore {

  BookmarkModal: BookmarkModalComponent | null = $state(null);

  CustomDropdown: CustomDropdownComponent | null = $state(null);

  Homepage: HomepageComponent | null = $state(null);

  PasangIklanLoker: PasangIklanLokerComponent | null = $state(null);

  SingleLowongan: SingleLowonganComponent | null = $state(null);

  SkeletonHomepage: SkeletonHomepageComponent | null = $state(null);

  SkeletonSingleLowongan: SkeletonSingleLowonganComponent | null = $state(null);

  SkeletonPasangIklanLoker: SkeletonPasangIklanLokerComponent | null = $state(null);

  public async loadBookmarkModal(): Promise<BookmarkModalComponent> {
    if (this.BookmarkModal) return this.BookmarkModal;
    const comp = (await import("@components/ui/Header/BookmarkModal.svelte")).default;
    this.BookmarkModal = comp;
    return comp;
  }

  public async loadCustomDropdown(): Promise<CustomDropdownComponent> {
    if (this.CustomDropdown) return this.CustomDropdown;
    const comp = (await import("@components/ui/Homepage/CustomDropdown.svelte")).default;
    this.CustomDropdown = comp;
    return comp;
  }

  public async loadHomepage(): Promise<HomepageComponent> {
    if (this.Homepage) return this.Homepage;
    const comp = (await import("@routes/Homepage.svelte")).default;
    this.Homepage = comp;
    return comp;
  }

  public async loadPasangIklanLoker(): Promise<PasangIklanLokerComponent> {
    if (this.PasangIklanLoker) return this.PasangIklanLoker;
    const comp = (await import("@routes/PasangIklanLoker.svelte")).default;
    this.PasangIklanLoker = comp;
    return comp;
  }

  public async loadSingleLowongan(): Promise<SingleLowonganComponent> {
    if (this.SingleLowongan) return this.SingleLowongan;
    const comp = (await import("@routes/SingleLowongan.svelte")).default;
    this.SingleLowongan = comp;
    return comp;
  }

  public async loadSkeletonHomepage(): Promise<SkeletonHomepageComponent> {
    if (this.SkeletonHomepage) return this.SkeletonHomepage;
    const comp = (await import("@components/ui/Skeletons/SkeletonHomepage.svelte")).default;
    this.SkeletonHomepage = comp;
    return comp;
  }

  public async loadSkeletonSingleLowongan(): Promise<SkeletonSingleLowonganComponent> {
    if (this.SkeletonSingleLowongan) return this.SkeletonSingleLowongan;
    const comp = (await import("@components/ui/Skeletons/SkeletonSingleLowongan.svelte")).default;
    this.SkeletonSingleLowongan = comp;
    return comp;
  }

  public async loadSkeletonPasangIklanLoker(): Promise<SkeletonPasangIklanLokerComponent> {
    if (this.SkeletonPasangIklanLoker) return this.SkeletonPasangIklanLoker;
    const comp = (await import("@components/ui/Skeletons/SkeletonPasangIklanLoker.svelte")).default;
    this.SkeletonPasangIklanLoker = comp;
    return comp;
  }
}

export const dynamicComponentStore = new DynamicComponentStore();