import { nonceStore } from '$lib/stores/Nonce.svelte';

export class AuthService {
  /**
   * Returns the WP REST nonce from Pinia
   * Use this when making authenticated requests to the WP REST API.
   */
  static getRestNonce(): string | null {
    return nonceStore.getNonce();
  }

  /**
   * Sets the WP REST nonce, typically from API response headers.
   * Stores in Pinia for reactivity and sessionStorage for persistence.
   */
  static setRestNonce(nonce: string): void {
    nonceStore.setNonce(nonce);
  }
}