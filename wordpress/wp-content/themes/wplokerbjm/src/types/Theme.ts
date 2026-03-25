export interface WPLokerBJMThemedData {
  logo: {
    logoUrl: string;
    logoSrcset?: string;
    logoSizes: string;
    logoDecoding: HTMLImageElement["decoding"];
    logoWidth?: number;
    logoHeight?: number;
  };
  disableTracking: boolean;
  wpRestNonce?: string | null;
  siteIconTags?: string;
}

export enum ThemeName {
  Light = 'light',
  Dark = 'dark',
  Lavender = 'lavender',
}