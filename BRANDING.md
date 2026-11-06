# Identidade visual Web

Este checkout contém a aplicação Web em `apps/web`. O projeto mobile e seus
arquivos de marca não estão presentes; não execute comandos `apps/mobile` neste
repositório.

## Marca ativa

A Web usa `default` automaticamente. Para selecionar outra marca configurada,
defina `VITE_APP_BRAND` no ambiente da Web:

```env
VITE_APP_BRAND=default
```

## Arquivos existentes

- Nome, slogan e assets: `apps/web/src/branding/brands/default/brand.config.ts`
- Cores: `apps/web/src/branding/brands/default/colors.ts`
- Tipografia: `apps/web/src/branding/brands/default/typography.ts`
- Ícones: `apps/web/src/branding/brands/default/icons.ts`
- Assets: `apps/web/src/branding/brands/default/assets/`
- Registro de marcas: `apps/web/src/branding/index.ts`

Os tokens principais incluem `primary`, `primaryDark`, `primarySoft`, `accent`,
`background`, `surface`, `textPrimary`, `textSecondary`, `border`, `success`,
`warning`, `error` e `info`.

## Adicionar uma marca Web

1. Copie `apps/web/src/branding/brands/default` para uma pasta com o novo
   identificador.
2. Atualize configuração, cores, tipografia e assets.
3. Registre a marca em `apps/web/src/branding/index.ts`.
4. Defina `VITE_APP_BRAND` para o identificador e execute `npm run build` em
   `apps/web`.

Quando o cliente mobile existir neste checkout, sua identidade visual poderá ser
implementada no empacotador próprio, mantendo o mesmo identificador de marca e
sem duplicar regras de negócio ou permissões.
