import type { ConfigContext, ExpoConfig } from "expo/config";

export default ({ config }: ConfigContext): ExpoConfig => {
  const requested = process.env.EXPO_PUBLIC_APP_BRAND || "default";
  let key = /^[a-z0-9-]+$/.test(requested) ? requested : "default";
  let brand;
  try {
    brand = require(`./src/branding/brands/${key}/brand.config.json`);
  } catch {
    key = "default";
    brand = require("./src/branding/brands/default/brand.config.json");
  }
  const assetRoot = `./src/branding/brands/${key}/assets`;

  return {
    ...config,
    name: brand.name,
    slug: "ieqcv-app",
    version: "0.1.0",
    orientation: "portrait",
    scheme: brand.scheme,
    userInterfaceStyle: "light",
    icon: `${assetRoot}/app-icon.png`,
    ios: {
      supportsTablet: true,
      bundleIdentifier: brand.appIdentifier,
    },
    android: {
      package: brand.appIdentifier,
      googleServicesFile: "./google-services.json",
      predictiveBackGestureEnabled: true,
      adaptiveIcon: {
        foregroundImage: `${assetRoot}/adaptive-icon.png`,
        backgroundColor: brand.splashBackground,
      },
    },
    plugins: [
      "expo-router",
      "expo-secure-store",
      [
        "expo-splash-screen",
        {
          image: `${assetRoot}/splash.png`,
          imageWidth: 200,
          resizeMode: "contain",
          backgroundColor: brand.splashBackground,
        },
      ],
      [
        "expo-notifications",
        {
          icon: `${assetRoot}/notification-icon.png`,
          color: brand.colors.primary,
          defaultChannel: "default",
        },
      ],
      "@react-native-community/datetimepicker",
    ],
    experiments: {
      typedRoutes: true,
      reactCompiler: true,
    },
    extra: {
      router: {},
      eas: {
        projectId:
          process.env.EXPO_PUBLIC_EAS_PROJECT_ID ||
          "5d0e0425-7b4f-4577-80bc-17831021cc63",
      },
      brand: key,
    },
    owner: "gui-tupinamba",
  };
};
