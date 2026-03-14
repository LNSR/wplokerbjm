export type BookmarkModalComponent = typeof import("@components/ui/Header/BookmarkModal.svelte").default;
export type CustomDropdownComponent = typeof import("@components/ui/Homepage/CustomDropdown.svelte").default;
export type LoginModalComponent = typeof import("@components/ui/Shared/LoginModal.svelte").default;

class DynamicComponentStore {

  BookmarkModal = $state<BookmarkModalComponent | null>(null);

  CustomDropdown = $state<CustomDropdownComponent | null>(null);
  LoginModal = $state<LoginModalComponent | null>(null);
  #componentLoading: boolean = false;


  public async loadBookmarkModal(): Promise<BookmarkModalComponent> {
    if (this.BookmarkModal) return this.BookmarkModal;
    try {
      this.#componentLoading = true;
      return this.BookmarkModal = (await import("@components/ui/Header/BookmarkModal.svelte")).default;
    } catch (error) {
      console.error("Failed to load BookmarkModal:", error);
      throw error;
    } finally {
      this.#componentLoading = false;
    }
  }

  public async loadCustomDropdown(): Promise<CustomDropdownComponent> {
    if (this.CustomDropdown) return this.CustomDropdown;
    try {
      this.#componentLoading = true;
      return this.CustomDropdown = (await import("@components/ui/Homepage/CustomDropdown.svelte")).default;
    } catch (error) {
      console.error("Failed to load CustomDropdown:", error);
      throw error;
    } finally {
      this.#componentLoading = false;
    }
  }

  public async loadLoginModal(): Promise<LoginModalComponent> {
    if (this.LoginModal) return this.LoginModal;
    try {
      this.#componentLoading = true;
      return this.LoginModal = (await import("@components/ui/Shared/LoginModal.svelte")).default;
    } catch (error) {
      console.error("Failed to load LoginModal:", error);
      throw error;
    } finally {
      this.#componentLoading = false;
    }
  }
}

export const dynamicComponentStore = new DynamicComponentStore();