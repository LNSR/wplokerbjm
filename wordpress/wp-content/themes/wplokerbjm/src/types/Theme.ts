export interface WPLokerBJMThemedData {
  logo: {
    logoUrl: string;
    logoSrcset?: string;
    logoSizes: string;
    logoDecoding?: HTMLImageElement["decoding"];
    logoWidth?: number;
    logoHeight?: number;
  };
  wpRestNonce?: string | null;
  siteIconTags?: string;
}

export type ThemeName = 'light' | 'dark' | 'lavender';
