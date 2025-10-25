export interface WPThemeData {
  themeUrl: string;
  logo: string;
  logoSrcset: string;
  logoSizes: string;
  logoWidth?: number;
  logoHeight?: number;
  lastJobUpdate: string;
  logoDecoding: HTMLImageElement["decoding"];
  disableTracking: boolean;
}