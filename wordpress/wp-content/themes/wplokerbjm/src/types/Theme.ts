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
}

export enum ThemeName {
  Light = 'light',
  Dark = 'dark',
}