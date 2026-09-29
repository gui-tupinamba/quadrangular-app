# Identidade visual white-label

A identidade visual está isolada da API, das regras de negócio e das telas. Web e
Mobile possuem uma pasta `branding/brands` porque cada empacotador trata assets de
forma diferente, mas ambos selecionam a marca pelo mesmo identificador.

## Marca atual

Use `default` enquanto não houver uma marca específica:

```env
# apps/web/.env
VITE_APP_BRAND=default

# apps/mobile/.env
EXPO_PUBLIC_APP_BRAND=default
```

Sem essas variáveis, as duas aplicações usam `default` automaticamente.

## Alterar a marca atual

### Mobile

- Nome, identificador, esquema e cores:
  `apps/mobile/src/branding/brands/default/brand.config.json`
- Tipografia: `apps/mobile/src/branding/brands/default/typography.ts`
- Assets: `apps/mobile/src/branding/brands/default/assets/`

### Web

- Nome, slogan e assets:
  `apps/web/src/branding/brands/default/brand.config.ts`
- Cores: `apps/web/src/branding/brands/default/colors.ts`
- Tipografia: `apps/web/src/branding/brands/default/typography.ts`
- Assets: `apps/web/src/branding/brands/default/assets/`

Os tokens principais são `primary`, `primaryDark`, `primarySoft`, `accent`,
`background`, `surface`, `textPrimary`, `textSecondary`, `border`, `success`,
`warning`, `error` e `info`. Os tokens `tone-*` do Web preservam exatamente as
variações visuais atuais e podem ser ajustados quando a identidade exigir.

## Substituir arquivos

Mantenha os nomes dos arquivos dentro de `assets/`:

| Arquivo | Uso |
| --- | --- |
| `logo.jpg` | Logo principal e tela de login |
| `logo-horizontal.jpg` | Cabeçalhos e áreas horizontais |
| `logo-white.jpg` | Fundos escuros |
| `app-icon.png` | Ícone principal do aplicativo, PNG quadrado |
| `adaptive-icon.png` | Foreground adaptativo Android, PNG transparente |
| `splash.png` | Imagem da splash |
| `notification-icon.png` | Android, PNG transparente e monocromático branco |
| `favicon.ico` | Favicon do Web |

Após alterar ícone, splash ou notification icon do Mobile, sincronize o projeto
nativo e valide a configuração:

```powershell
cd apps/mobile
npm run brand:sync-native
npx expo-doctor
```

O comando de sincronização preserva automaticamente o keystore local de release.

## Criar um novo cliente

1. Copie as pastas `default` do Web e do Mobile para o mesmo identificador, por
   exemplo `igreja-cliente-a`.
2. Edite os arquivos de configuração e substitua os assets nas duas pastas.
3. Adicione a nova marca apenas aos registros centrais:
   `apps/web/src/branding/index.ts` e `apps/mobile/src/branding/index.ts`.
4. Defina `VITE_APP_BRAND=igreja-cliente-a` e
   `EXPO_PUBLIC_APP_BRAND=igreja-cliente-a`.
5. Execute a sincronização nativa e os builds de validação.

Não crie telas, componentes ou condicionais específicas por cliente. A seleção da
marca ocorre somente nos dois registros centrais e na configuração Expo.

## Checklist

- [ ] Nome do cliente
- [ ] Cor principal
- [ ] Cor secundária
- [ ] Cor de destaque
- [ ] Cores de texto, borda e estados
- [ ] Logo principal
- [ ] Logo horizontal
- [ ] Logo branca
- [ ] App Icon
- [ ] Android Adaptive Icon
- [ ] Notification Icon
- [ ] Splash e sua cor de fundo
- [ ] Favicon
- [ ] Tipografia
- [ ] Definir a marca ativa no Web
- [ ] Definir a marca ativa no Mobile
- [ ] Sincronizar o projeto Android
- [ ] Conferir Mobile
- [ ] Conferir Web

## Validação

```powershell
cd apps/web
npm run build

cd ../mobile
npm run typecheck
npx expo install --check
npx expo-doctor
```

Essa estrutura altera somente identidade visual. Espaçamentos, tamanhos, ordem dos
elementos, navegação, autenticação, permissões, API e banco permanecem compartilhados.
