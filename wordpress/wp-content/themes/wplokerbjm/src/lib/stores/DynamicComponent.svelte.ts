export type BookmarkModalComponent = typeof import("@components/ui/Header/BookmarkModal.svelte").default;
export type CustomDropdownComponent = typeof import("@components/ui/Homepage/CustomDropdown.svelte").default;
export type LoginModalComponent = typeof import("@components/ui/Shared/LoginModal.svelte").default;

class DynamicComponentStore {

  BookmarkModal = $state<BookmarkModalComponent | undefined>(undefined);

  CustomDropdown = $state<CustomDropdownComponent | undefined>(undefined);
  LoginModal = $state<LoginModalComponent | undefined>(undefined);
  #componentLoading: boolean = false;
  private async loadComponent<T>(
    propName: keyof Pick<DynamicComponentStore, 'BookmarkModal' | 'CustomDropdown' | 'LoginModal'>,
    importer: () => Promise<{ default: T }>
  ): Promise<T> {
    const current = (this as any)[propName];
    if (current) return current;
    try {
      this.#componentLoading = true;
      const comp = (await importer()).default;
      return (this as any)[propName] = comp;
    } catch (error) {
      console.error(`Failed to load ${propName}:`, error);
      throw error;
    } finally {
      this.#componentLoading = false;
    }
  }

  public loadBookmarkModal(): Promise<BookmarkModalComponent> {
    return this.loadComponent<BookmarkModalComponent>(
      'BookmarkModal',
      () => import("@components/ui/Header/BookmarkModal.svelte")
    );
  }

  public loadCustomDropdown(): Promise<CustomDropdownComponent> {
    return this.loadComponent<CustomDropdownComponent>(
      'CustomDropdown',
      () => import("@components/ui/Homepage/CustomDropdown.svelte")
    );
  }

  public loadLoginModal(): Promise<LoginModalComponent> {
    return this.loadComponent<LoginModalComponent>(
      'LoginModal',
      () => import("@components/ui/Shared/LoginModal.svelte")
    );
  }
}

export const dynamicComponentStore = new DynamicComponentStore();