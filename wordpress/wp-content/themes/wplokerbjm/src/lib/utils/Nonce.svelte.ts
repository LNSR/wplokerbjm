import { themeManager } from "$lib/stores/Theme.svelte";
import { APIService } from "@/services/APIService";
import type { WPLokerBJMThemedData } from "@/types";
/**
 * Manages the WordPress REST API nonce for client-side requests.
 * The nonce is supplied server-side (via page data from +layout.server.ts)
 * and read from the cached theme data — no localStorage handling is needed.
 */
class NonceManager {
  private nonce: WPLokerBJMThemedData["wpRestNonce"] = undefined;
  private loggedInNonce = $derived.by(() => themeManager.getThemeData()?.wpRestNonce as WPLokerBJMThemedData["wpRestNonce"]); // check initial nonce existence to determine if user is logged in

  /**
   * Synchronously reads the nonce from the cached theme data props.
   */
  private readStorage(): WPLokerBJMThemedData["wpRestNonce"] {
    // Only fetch nonce if theme has initial nonce from SSR
    // refetch again so it matches x-wp-nonce header from API response(just in case different nonce from JWT), but only attempt once to avoid infinite loop
    if (this.loggedInNonce && !this.nonce && this.loggedInNonce !== this.nonce) {
      void this.getNonceFromAPI();
      return this.loggedInNonce;
    }

    return undefined;
  }

  public setNonce(nonce: WPLokerBJMThemedData["wpRestNonce"]): WPLokerBJMThemedData["wpRestNonce"] {
    const themedata = themeManager.getThemeData();
    themeManager.setThemeData({
      ...themedata,
      wpRestNonce: nonce,
    } as WPLokerBJMThemedData); // re-set theme data to trigger reactive updates for components that depend on nonce
    this.nonce = nonce;
    return this.nonce;
  }

  public getNonceFromAPI(): Promise<WPLokerBJMThemedData["wpRestNonce"]> {
    return APIService.getThemeNonceGraphQL()
      .then((fetchedNonce) => {
        if (fetchedNonce && fetchedNonce.length > 0) {
          this.setNonce(fetchedNonce);
          return fetchedNonce;
        }
        return undefined;
      })
      .catch((err) => {
        console.error("NonceManager: error fetching nonce from CMS, perhaps not logged-in", err);
        return undefined;
      });
  }

  public get getNonce(): WPLokerBJMThemedData["wpRestNonce"] {
    if (this.nonce === undefined || this.nonce.length === 0) {
      return this.readStorage();
    }
    return this.nonce;
  }
}

export const nonceManager = new NonceManager();
