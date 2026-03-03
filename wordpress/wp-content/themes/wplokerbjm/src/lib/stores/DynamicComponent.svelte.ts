export type BookmarkModalComponent = typeof import("@components/ui/Header/BookmarkModal.svelte").default;
export type CustomDropdownComponent = typeof import("@components/ui/Homepage/CustomDropdown.svelte").default;
export type LoginModalComponent = typeof import("@components/ui/Shared/LoginModal.svelte").default;

class DynamicComponentStore {

  BookmarkModal: BookmarkModalComponent | null = $state(null);

  CustomDropdown: CustomDropdownComponent | null = $state(null);
  LoginModal: LoginModalComponent | null = $state(null);


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

  public async loadLoginModal(): Promise<LoginModalComponent> {
    if (this.LoginModal) return this.LoginModal;
    try {
      this.LoginModal = (await import("@components/ui/Shared/LoginModal.svelte")).default;
      return this.LoginModal;
    } catch (error) {
      console.error("Failed to load LoginModal:", error);
      throw error;
    }
  }
}

export const dynamicComponentStore = new DynamicComponentStore();