import { themeManager } from "$lib/stores/Theme.svelte";
import { APIService } from "@/services/APIService";
import type { WPLokerBJMThemedData } from "@/types";
/**
 * Manages the WordPress REST API nonce for client-side requests.
 * The nonce is supplied server-side (via page data from +layout.server.ts)
 * and read from the cached theme data — no localStorage handling is needed.
 */
class NonceManager {
  private nonce = $derived<WPLokerBJMThemedData["wpRestNonce"]>(themeManager.getThemeData?.wpRestNonce);

  public get getNonceFromAPI(): Promise<WPLokerBJMThemedData["wpRestNonce"]> {
    return APIService.getThemeNonceGraphQL().then((nonce) => {
      if (nonce && nonce.length > 0) {
        themeManager.setNonce(nonce);
      }
      return this.nonce;
    }).catch((error) => {
      console.error("Error fetching nonce from API:", error);
      return this.nonce;
    });
  }

  public get getNonce(): WPLokerBJMThemedData["wpRestNonce"] {
    return this.nonce;
  }
}

export const nonceManager = new NonceManager();
