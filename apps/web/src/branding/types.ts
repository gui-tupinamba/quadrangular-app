export type BrandConfig = {
  id: string;
  name: string;
  shortName: string;
  tagline: string;
  colors: Record<string, string>;
  typography: {
    regular: string;
    medium: string;
    bold: string;
  };
  assets: {
    logo: string;
    logoHorizontal: string;
    logoWhite: string;
    favicon: string;
  };
};
