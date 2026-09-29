import { copyFileSync, existsSync, mkdirSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { spawnSync } from "node:child_process";

const releaseKey = join(process.cwd(), "android", "app", "igreja-release.keystore");
const backupDir = join(tmpdir(), `ieqcv-brand-sync-${process.pid}`);
const backupKey = join(backupDir, "igreja-release.keystore");
const hasReleaseKey = existsSync(releaseKey);

if (hasReleaseKey) {
  mkdirSync(backupDir, { recursive: true });
  copyFileSync(releaseKey, backupKey);
}

try {
  const command = process.platform === "win32" ? "npx.cmd" : "npx";
  const result = spawnSync(
    command,
    ["expo", "prebuild", "--platform", "android", "--no-install", ...process.argv.slice(2)],
    { cwd: process.cwd(), stdio: "inherit" },
  );

  if (result.error) throw result.error;
  process.exitCode = result.status ?? 1;
} finally {
  if (hasReleaseKey && existsSync(backupKey)) {
    mkdirSync(dirname(releaseKey), { recursive: true });
    copyFileSync(backupKey, releaseKey);
  }
  rmSync(backupDir, { recursive: true, force: true });
}
