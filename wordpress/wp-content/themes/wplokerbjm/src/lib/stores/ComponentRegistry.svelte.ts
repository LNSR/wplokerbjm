import { isNil } from "es-toolkit";
import { type Component, type ComponentProps } from "svelte";
import { SvelteMap } from "svelte/reactivity";
import type BookmarkModal from "@components/ui/Header/BookmarkModal.svelte";
import type CustomDropdown from "@components/ui/Homepage/SearchForm/CustomDropdown.svelte";
import type LoginModal from "@components/ui/Header/LoginModal.svelte";

interface ComponentRegistryMap
{
  BookmarkModal?: Component<ComponentProps<typeof BookmarkModal>>;
  CustomDropdown?: Component<ComponentProps<typeof CustomDropdown>>;
  LoginModal?: Component<ComponentProps<typeof LoginModal>>;
}

class ComponentRegistry
{
  #components = new SvelteMap<keyof ComponentRegistryMap, ComponentRegistryMap[ keyof ComponentRegistryMap ]>();
  #componentLoading = false;
  #registry: Record<keyof ComponentRegistryMap, () => Promise<{ default: any }>> = {
    BookmarkModal: () => import("@components/ui/Header/BookmarkModal.svelte"),
    CustomDropdown: () => import("@components/ui/Homepage/SearchForm/CustomDropdown.svelte"),
    LoginModal: () => import("@components/ui/Header/LoginModal.svelte"),
  };

  /**
   * Load a component by its name.
   */
  public loadComponentByName<Key extends keyof ComponentRegistryMap>(
    name: Key
  ): Promise<ComponentRegistryMap[ Key ]> | ComponentRegistryMap[ Key ]
  {
    const cached = this.getComponentByName(name);
    if (!isNil(cached)) return cached;

    const importer = this.#registry[ name ];
    return this.#loadComponent(name, importer);
  }

  /**
   * Getter for a component by its name.
   */
  public getComponentByName<Key extends keyof ComponentRegistryMap>(
    name: Key
  ): ComponentRegistryMap[ Key ]
  {
    return this.#components.get(name) as ComponentRegistryMap[ Key ];
  }

  /**
   * Load a component by its name using the provided importer function.
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

      this.#components.set(propName, comp);
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