import { expect, test } from "@playwright/test";

test("política de privacidade abre sem restaurar uma sessão", async ({ page }) => {
  const requests: string[] = [];
  page.on("request", (request) => requests.push(request.url()));
  await page.goto("/privacidade");
  await expect(page.getByRole("heading", { name: "Política de privacidade" })).toBeVisible();
  await expect(page.locator("article").getByRole("link", { name: "Solicitar exclusão de conta" })).toBeVisible();
  expect(requests.some((url) => url.includes("/auth/refresh"))).toBe(false);
});

test("formulário público confirma o recebimento sem pedir senha", async ({ page }) => {
  await page.route("**/api/account-deletion-requests", (route) => route.fulfill({
    status: 202,
    contentType: "application/json",
    body: JSON.stringify({ message: "Recebemos sua solicitação.", reference: "a1b2c3d4e5f60718293a4b5c6d7e8f90" }),
  }));
  await page.goto("/excluir-conta");
  await expect(page.getByRole("heading", { name: "Solicitar exclusão de conta" })).toBeVisible();
  await page.getByLabel("Nome completo").fill("Pessoa de teste");
  await page.getByLabel("E-mail cadastrado").fill("pessoa@example.test");
  await page.getByLabel(/Confirmo que desejo solicitar/).check();
  await page.getByRole("button", { name: "Enviar solicitação" }).click();
  await expect(page.getByRole("heading", { name: "Solicitação recebida" })).toBeVisible();
  await expect(page.getByText("a1b2c3d4e5f60718293a4b5c6d7e8f90")).toBeVisible();
  await expect(page.getByLabel(/Senha/)).toHaveCount(0);
});
