export class AuthService {
  /**
   * Checks if the current user is logged in to WordPress by inspecting cookies.
   * @returns {boolean}
   */
  static isUserLoggedIn(): boolean {
    if (typeof window !== 'undefined' && typeof window.wpUserLoggedIn !== 'undefined') {
      return !!window.wpUserLoggedIn;
    }
    return false;
  }
}