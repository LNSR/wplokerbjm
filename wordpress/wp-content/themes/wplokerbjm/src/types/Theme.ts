export interface WPLokerBJMThemedData {
  themeUrl: string;
  logo: string;
  logoSrcset: string;
  logoSizes: string;
  logoWidth?: number;
  logoHeight?: number;
  lastJobUpdate: string;
  logoDecoding: HTMLImageElement["decoding"];
  disableTracking: boolean;
  themeVersion?: number;
  lastTaxonomyUpdate: string;
  wpRestNonce?: string;
}

export enum ThemeName {
  Light = 'light',
  Dark = 'dark',
  Lavender = 'lavender',
}