import { isNil } from "es-toolkit";
import { type Component, type ComponentProps } from "svelte";
import type BookmarkModal from "@components/ui/Header/BookmarkModal.svelte";
import type CustomDropdown from "@components/ui/Homepage/SearchForm/CustomDropdown.svelte";
import type LoginModal from "@components/ui/Header/LoginModal.svelte";

interface ComponentRegistryMap {
  BookmarkModal?: Component<ComponentProps<typeof BookmarkModal>>;
  CustomDropdown?: Component<ComponentProps<typeof CustomDropdown>>;
  LoginModal?: Component<ComponentProps<typeof LoginModal>>;
}
class ComponentRegistry
{

  #components = $state<ComponentRegistryMap>({
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
  public loadComponentByName(name: keyof ComponentRegistryMap): Promise<ComponentRegistryMap[ typeof name ]> | ComponentRegistryMap[ typeof name ]
  {
    if (!isNil(this.#components[ name ])) return this.#components[ name ];

    type RegistryType = Record<keyof ComponentRegistryMap, () => Promise<{ default: ComponentRegistryMap[ keyof ComponentRegistryMap ] }>>;

    const registry: RegistryType = {
      BookmarkModal: () => import("@components/ui/Header/BookmarkModal.svelte"),
      CustomDropdown: () => import("@components/ui/Homepage/SearchForm/CustomDropdown.svelte"),
      LoginModal: () => import("@components/ui/Header/LoginModal.svelte"),
    }

    const importer = registry[ name ];
    return this.#loadComponent(name, importer);
  }

  /**
   * Getter for a component by its name.
   * @param name Component name
   * @returns Component instance or undefined if not loaded
   */
  public getComponentByName<Key extends keyof ComponentRegistryMap>(name: Key): ComponentRegistryMap[ Key ]
  {
    return this.#components[ name ];
  }

  /**
   * Load a component by its name using the provided importer function.
   * @param propName Component name
   * @param importer Function that imports the component
   */
  async #loadComponent<Key extends keyof ComponentRegistryMap>(
    propName: Key,
    importer: () => Promise<{ default: ComponentRegistryMap[ Key ] }>
  ): Promise<ComponentRegistryMap[ Key ]>
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

export const componentRegistry = new ComponentRegistry();