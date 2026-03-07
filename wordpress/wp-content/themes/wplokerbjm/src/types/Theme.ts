export interface WPLokerBJMThemedData {
  themeUrl: string;
  logo: {
    logoUrl: string;
    logoSrcset?: string;
    logoSizes: string;
    logoDecoding: HTMLImageElement["decoding"];
    logoWidth?: number;
    logoHeight?: number;
  };
  lastJobUpdate: string;
  disableTracking: boolean;
  themeVersion: number;
  lastTaxonomyUpdate: string;
  wpRestNonce?: string;
  siteIconTags: string;
}

export enum ThemeName {
  Light = 'light',
  Dark = 'dark',
  Lavender = 'lavender',
}