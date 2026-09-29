import type { BrandConfig } from "../../types";
import config from "./brand.config.json";
import { colors } from "./colors";
import { typography } from "./typography";

export const defaultBrandConfig = {
  ...config,
  colors,
  typography,
} satisfies BrandConfig;
