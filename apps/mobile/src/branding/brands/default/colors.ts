import type { BrandColors } from "../../types";
import config from "./brand.config.json";

export const colors = config.colors satisfies BrandColors;
