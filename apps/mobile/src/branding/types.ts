import type { ImageSourcePropType } from "react-native";

export type BrandColors = {
  primary: string;
  primaryDark: string;
  primarySoft: string;
  secondary: string;
  accent: string;
  background: string;
  surface: string;
  textPrimary: string;
  textSecondary: string;
  textDisabled: string;
  border: string;
  divider: string;
  success: string;
  warning: string;
  error: string;
  errorSurface: string;
  info: string;
  onPrimary: string;
  overlay: string;
  shadow: string;
  heroText: string;
  primaryWashSubtle: string;
  primaryWash: string;
  primaryWashStrong: string;
};

export type BrandConfig = {
  id: string;
  name: string;
  shortName: string;
  tagline: string;
  appIdentifier: string;
  scheme: string;
  splashBackground: string;
  colors: BrandColors;
  typography: {
    regular: string | undefined;
    medium: string | undefined;
    bold: string | undefined;
  };
};

export type BrandAssets = {
  logo: ImageSourcePropType;
  logoHorizontal: ImageSourcePropType;
  logoWhite: ImageSourcePropType;
  appIcon: ImageSourcePropType;
  adaptiveIcon: ImageSourcePropType;
  splash: ImageSourcePropType;
  notificationIcon: ImageSourcePropType;
};

export type ActiveBrand = BrandConfig & { assets: BrandAssets };
