import { defaultBrand } from "./brands/default/brand.config";

const brands = { default: defaultBrand } as const;

export type BrandKey = keyof typeof brands;

const requestedBrand = import.meta.env.VITE_APP_BRAND || "default";

export const activeBrandKey: BrandKey = Object.hasOwn(brands, requestedBrand)
  ? (requestedBrand as BrandKey)
  : "default";

export const brand = brands[activeBrandKey];

export function applyBranding(): void {
  const root = document.documentElement;
  for (const [token, value] of Object.entries(brand.colors)) {
    root.style.setProperty(`--brand-${token}`, value);
  }
  root.style.setProperty("--brand-font-regular", brand.typography.regular);
  root.style.setProperty("--brand-font-medium", brand.typography.medium);
  root.style.setProperty("--brand-font-bold", brand.typography.bold);

  document.title = brand.name;
  document
    .querySelector('meta[name="theme-color"]')
    ?.setAttribute("content", brand.colors.primary);

  let favicon = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
  if (!favicon) {
    favicon = document.createElement("link");
    favicon.rel = "icon";
    document.head.append(favicon);
  }
  favicon.href = brand.assets.favicon;
}
