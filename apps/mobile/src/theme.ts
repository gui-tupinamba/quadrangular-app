import { brand } from "./branding";

const brandColors = brand.colors;

export const colors = {
  ...brandColors,
  green: brandColors.primary,
  greenDark: brandColors.primaryDark,
  greenSoft: brandColors.primarySoft,
  cream: brandColors.background,
  gold: brandColors.accent,
  ink: brandColors.textPrimary,
  muted: brandColors.textSecondary,
  white: brandColors.onPrimary,
  danger: brandColors.error,
};

export const typography = brand.typography;

export const radius = {
  small: 4,
  medium: 8,
  large: 16,
  round: 999,
} as const;
