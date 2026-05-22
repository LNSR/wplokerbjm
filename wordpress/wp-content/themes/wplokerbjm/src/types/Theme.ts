export interface WPLokerBJMThemedData
{
  logo: {
    logoUrl: string;
    logoSrcset?: string;
    logoSizes: string;
    logoDecoding?: HTMLImageElement[ "decoding" ];
    logoWidth?: number;
    logoHeight?: number;
  };
  wpRestNonce?: string | null;
  siteIconTags?: string;
}

export interface ThemeScriptData
{
  localStorageKey: "wplokerbjm-theme";
  elements: {
    attribute: "data-theme";
    class: "wplokerbjm-dark-mode-enable";
  }
  themeList: "light" | "dark" | "lavender";
}