export class AuthService {
  /**
   * Checks if the current user is logged in to WordPress by window object.
   * @returns {string | null}
   */
  static getMetaContent(name: string): string | null {
    const el = document.querySelector(`meta[name="${name}"]`);
    if (el && el instanceof HTMLMetaElement) {
      return el.getAttribute('content');
    }
    return null;
  }

  /**
   * Returns whether the current visitor is logged in to WordPress.
   */
  static isUserLoggedIn(): boolean {
    return this.getMetaContent('wp-user-logged-in') === 'true';
  }

  /**
   * Returns the WP REST nonce injected by the server or null if absent.
   * Use this when making authenticated requests to the WP REST API.
   */
  static getRestNonce(): string | null {
    return this.getMetaContent('wp-rest-nonce');
  }
}