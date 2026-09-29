import type { ActiveBrand } from "./types";
import { defaultBrandConfig } from "./brands/default/brand.config";

const brands = {
  default: {
    ...defaultBrandConfig,
    assets: {
      // Uma única imagem identifica o app na tela de login e no launcher.
      logo: require("./brands/default/assets/app-icon.png"),
      logoHorizontal: require("./brands/default/assets/logo-horizontal.jpg"),
      logoWhite: require("./brands/default/assets/logo-white.jpg"),
      appIcon: require("./brands/default/assets/app-icon.png"),
      adaptiveIcon: require("./brands/default/assets/adaptive-icon.png"),
      splash: require("./brands/default/assets/splash.png"),
      notificationIcon: require("./brands/default/assets/notification-icon.png"),
    },
  },
} satisfies Record<string, ActiveBrand>;

export type BrandKey = keyof typeof brands;

const requestedBrand = process.env.EXPO_PUBLIC_APP_BRAND || "default";

export const activeBrandKey: BrandKey = Object.hasOwn(brands, requestedBrand)
  ? (requestedBrand as BrandKey)
  : "default";

export const brand = brands[activeBrandKey];
