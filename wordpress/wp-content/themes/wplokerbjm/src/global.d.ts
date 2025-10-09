declare global {
  interface Window {
    wpTheme?: {
      themeUrl: string;
      logo: string;
      lastJobUpdate: string;
      loggedIn: boolean;
    };
    adsbygoogle?: unknown[];
  }
}

export {};
