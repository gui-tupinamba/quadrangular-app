import type { BrandConfig } from "../../types";
import logo from "./assets/logo.jpg";
import logoHorizontal from "./assets/logo-horizontal.jpg";
import logoWhite from "./assets/logo-white.jpg";
import { colors } from "./colors";
import { brandIcons } from "./icons";
import { typography } from "./typography";

export const defaultBrand = {
  id: "default",
  name: "Koinonia",
  shortName: "Koinonia",
  tagline: "A PLATAFORMA QUE CONECTA SUA IGREJA",
  colors,
  typography,
  assets: {
    logo,
    logoHorizontal,
    logoWhite,
    favicon: brandIcons.favicon,
  },
} satisfies BrandConfig;
