import {
  partytownSnippet,
  type PartytownConfig,
} from "@qwik.dev/partytown/integration";
/**
 * to initialize globals and ensure Partytown is bootstrapped.
 */
class PartytownManager {
  public static DEFAULT_CONFIG: PartytownConfig = {
    // debug: true,
    forward: ["dataLayer.push", "gtag"],
    lib: "/~partytown/",
  };

  private static interactionDetected = false;

  /**
   * Ensure globals used by tracking are present on window.
   * This method is intentionally agnostic of GTM/GA and only ensures
   * that the commonly-used globals exist.
   */
  private static initializeGlobals(): void {
    window.dataLayer = window.dataLayer || [];
    window.gtag =
      window.gtag ||
      ((...args: unknown[]) => {
        // gtag sometimes forwards args as an Array-like object; normalize to array
        window.dataLayer!.push(args as unknown[]);
      });
  }

  /**
   * Injects the Partytown boot script if it isn't already present and applies
   * the provided configuration (or the default config).
   * Returns true when Partytown boot script is present or was inserted.
   */
  public static ensureBoot(config?: PartytownConfig): boolean {
    if (typeof window === "undefined") return false;

    this.initializeGlobals();

    if (document.querySelector("script[partytown-boot]")) return true;

    const partytownConfig = config ?? this.DEFAULT_CONFIG;
    window.partytown = partytownConfig;

    const script = document.createElement("script");
    script.async = false;
    script.setAttribute("partytown-boot", "");
    script.innerHTML = partytownSnippet(partytownConfig);
    document.head.appendChild(script);

    return true;
  }

  /**
   * Ensures Partytown is booted only after detecting human interaction.
   * For returned visitors (within the session), boots immediately if interaction was previously detected.
   * Listens for user events like mouse, keyboard, touch, and scroll.
   * Once interaction is detected, boots Partytown, sets a session flag, and removes listeners.
   * @param config Optional Partytown configuration.
   * @returns Promise that resolves to true when Partytown is ready.
   */
  public static async ensureBootOnInteraction(
    config?: PartytownConfig,
  ): Promise<boolean> {
    if (typeof window === "undefined") return false;

    // Check if user has interacted in this session (returned visitor)
    if (sessionStorage.getItem("partytown_interacted") === "true") {
      return this.ensureBoot(config);
    }

    // If already booted or interaction detected, ensure boot immediately
    if (
      this.interactionDetected ||
      document.querySelector("script[partytown-boot]")
    ) {
      return this.ensureBoot(config);
    }

    return new Promise((resolve) => {
      const events = [
        "mousedown",
        "mousemove",
        "keypress",
        "scroll",
        "touchstart",
      ];

      const handler = () => {
        this.interactionDetected = true;
        // Set session flag for returned visitors
        sessionStorage.setItem("partytown_interacted", "true");
        // Remove all listeners
        events.forEach((event) => window.removeEventListener(event, handler));
        // Boot Partytown
        const booted = this.ensureBoot(config);
        resolve(booted);
      };

      // Add listeners with once: true for efficiency
      events.forEach((event) =>
        window.addEventListener(event, handler, { once: true, passive: true }),
      );
    });
  }
}

export const Partytown = PartytownManager;
