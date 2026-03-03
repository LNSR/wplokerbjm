import { isAppEl } from "@/utils/elements";
import { browser } from "$app/environment";

export class HeaderStore {
  public headerHeight = $state(0);
  public appEl = browser
    ? (document.querySelector(isAppEl) as HTMLElement | null)
    : null;
}

export const headerStore = new HeaderStore();
