import { isNil } from "es-toolkit";
import { type Component, type ComponentProps } from "svelte";
import type BookmarkModal from "@components/ui/Header/BookmarkModal.svelte";
import type CustomDropdown from "@components/ui/Homepage/CustomDropdown.svelte";
import type LoginModal from "@components/ui/Shared/LoginModal.svelte";

type DynamicComponent = {
  BookmarkModal?: Component<ComponentProps<typeof BookmarkModal>>;
  CustomDropdown?: Component<ComponentProps<typeof CustomDropdown>>;
  LoginModal?: Component<ComponentProps<typeof LoginModal>>;
}
class DynamicComponentStore
{

  #components = $state<DynamicComponent>({
    BookmarkModal: undefined,
    CustomDropdown: undefined,
    LoginModal: undefined
  });
  #componentLoading: boolean = false;

  /**
   * Load a component by its name.
   * @param name Component name
   * @returns Promise resolving to the component instance, or the instance itself if already loaded
   */
  public loadComponentByName(name: keyof DynamicComponent): Promise<DynamicComponent[ typeof name ]> | DynamicComponent[ typeof name ]
  {
    if (!isNil(this.#components[ name ])) return this.#components[ name ];
    switch (name)
    {
      case 'BookmarkModal':
        return this.loadComponent(
          name,
          () => import(`@components/ui/Header/${name}.svelte`)
        );
      case 'CustomDropdown':
        return this.loadComponent(
          name,
          () => import(`@components/ui/Homepage/${name}.svelte`)
        );
      case 'LoginModal':
        return this.loadComponent(
          name,
          () => import(`@components/ui/Shared/${name}.svelte`)
        );
      default:
        throw new Error(`Unknown component name: ${name}`);
    }
  }

  /**
   * Getter for a component by its name.
   * @param name Component name
   * @returns Component instance or undefined if not loaded
   */
  public getComponentByName<Key extends keyof DynamicComponent>(name: Key): DynamicComponent[ Key ]
  {
    return this.#components[ name ];
  }

  private async loadComponent<Key extends keyof DynamicComponent>(
    propName: Key,
    importer: () => Promise<{ default: DynamicComponent[ Key ] }>
  ): Promise<DynamicComponent[ Key ]>
  {
    try
    {
      this.#componentLoading = true;
      const comp = (await importer()).default;
      this.#components[ propName ] = comp;
      return comp;
    } catch (error)
    {
      console.error(`Failed to load ${propName}:`, error);
      throw error;
    } finally
    {
      this.#componentLoading = false;
    }
  }
}

export const dynamicComponentStore = new DynamicComponentStore();