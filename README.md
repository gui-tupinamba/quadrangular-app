# Aplicativo da igreja

Monorepo para gestão, comunicação e ministérios. Infraestrutura, modelo inicial
do banco, autenticação e autorização estão implementados. A API já oferece leituras
protegidas, cadastro, perfis, ministérios, participantes, líderes, publicações, comentários, eventos e agenda.
Os endereços configurados de produção são **https://guitupinamba.dev/** e
**https://api.guitupinamba.dev/api**. A última verificação pública de HTTPS
expirou desta estação; o estado local e as pendências estão em
[validation.md](docs/validation.md). O primeiro ADMIN foi criado pelo usuário.

O projeto possui identidade visual white-label para Web. Consulte
[BRANDING.md](BRANDING.md) para trocar cores, nomes, logos, ícones e favicon ou
criar uma nova marca Web.
O cliente mobile não está neste repositório. A API oferece o endpoint autenticado
de solicitação de exclusão; o contrato Android/iOS está em
[account-deletion-mobile.md](docs/account-deletion-mobile.md).

## Estado atual

- Symfony 7.4 LTS, Doctrine configurado, dependências fixadas em `apps/api/composer.lock`.
- Compose com PHP 8.4-FPM, PostgreSQL 17, Nginx 1.30 e serviço de testes opcional.
- `GET /api/health`: HTTP 200 com `{"status":"ok"}`; verifica a API, sem acessar o banco.
- `GET /api/ready`: executa `SELECT 1` via Doctrine; HTTP 200 se disponível, 503 genérico em falha.
- Respostas JSON de erro, autenticação nas rotas privadas e verificações de
  saúde/prontidão; endpoints públicos são explicitamente documentados.
- Arquivo original `teste` preservado. Não havia repositório Git inicializado na inspeção.
- **Docker validado em 05/11/2026:** containers obrigatórios saudáveis; health,
  readiness e Web responderam HTTP 200 localmente. A verificação HTTPS pública
  continua pendente de uma rede externa.
- As três primeiras migrations criam o domínio inicial e a autenticação/auditoria.
  O schema cresceu com imagens, notificações e solicitações de exclusão; o projeto
  tem onze migrations. Consulte o histórico e a validação por fase em
  [validation.md](docs/validation.md).
- Fase 3 validada com **75 testes e 601 verificações** no PostgreSQL real.
- Fase 4: login Web/mobile, JWT de dez minutos, refresh com rotação, logout e `/me`.
  Sessões revogáveis, limites de tentativas e comando seguro de primeiro ADMIN.
  **237 testes e 1.437 verificações aprovados** na imagem final com PostgreSQL real.
  Consulte [o contrato e a configuração](docs/authentication.md) e
  [o registro da validação mais recente](docs/validation.md).
- Fase 5: Voters, escopo de leitura compartilhado, listagem/detalhe administrativo
  de usuários, alteração de cargo/status e consulta das permissões atuais.
  Proteção do último ADMIN, remoção de liderança e auditoria na mesma transação.
  **299 testes e 2.027 verificações aprovados** na suíte completa com PostgreSQL isolado.
  Veja [as rotas e regras de autorização](docs/authorization.md). O registro de
  validação distingue esses testes da implantação no banco local da aplicação.

## Decisões e roteiro

Fase 6 implementada: cadastro administrativo, edição de perfil, troca própria e
redefinição administrativa de senha, com auditoria e revogação de sessões. Não há
nova migration. **321 testes e 2.277 verificações aprovados**, API local atualizada.
[Rotas e campos](docs/users.md); [testes executados](docs/validation.md).

Fase 7 implementada: gestão de ministérios, participação N:N e liderança explícita,
com histórico, auditoria e revogação de acesso. Sem nova migration.
**338 testes e 2.442 verificações aprovados**, API local atualizada.
[Rotas e regras de ministérios](docs/ministries.md).

Fase 8 implementada: publicações de texto, rascunhos, publicação/arquivamento,
feed e busca com filtros de acesso, gestão por ministério e auditoria. Sem nova migration.
**358 testes e 2.639 verificações aprovados**, API local atualizada.
[Rotas de publicações e feed](docs/posts.md).

Fase 9 implementada: criação, listagem, edição/remoção de comentários próprios e
moderação por ADMIN/PASTOR, com privacidade, estados e auditoria. Sem nova migration.
**378 testes e 2.891 verificações aprovados**, API local atualizada em 14/09/2026.
[Rotas e regras de comentários](docs/comments.md).

Fase 10 implementada: eventos gerais e de ministérios, horários com fuso, filtros
por período, publicação/cancelamento/arquivamento e auditoria. Sem nova migration.
**409 testes e 3.147 verificações aprovados**, API local atualizada em 14/09/2026.
[Rotas e regras de eventos](docs/events.md).

Fase 11 implementada: atividades gerais/por ministério e agenda combinada com
eventos, próximos compromissos, filtros e paginação global sem duplicar registros.
**442 testes e 3.445 verificações aprovados**, API local atualizada em 14/09/2026.
Sem nova migration. [Rotas e regras da agenda](docs/agenda.md).
Fase 12: Web React/TypeScript/Vite com login, painel, pessoas, ministérios,
participação/liderança, publicações/comentários, eventos, atividades, agenda e perfil.
Build estático servido pelo serviço Docker `web`, com navegação por permissões e
dados reais da API. [Execução e arquivos](docs/web.md). Sem nova migration.
Fase 13: cliente mobile planejado em Expo/React Native; seu código não está neste
repositório. A API oferece endpoints de autenticação e notificações para clientes
nativos; integração e telas mobile ainda precisam ser implementadas no repositório
do aplicativo.
Fase 14: notificações na Web e suporte de API para clientes mobile, preferências,
registro de dispositivos, fila Expo e avisos automáticos ao publicar conteúdo.
[Configuração](docs/notifications.md).

Leia [a arquitetura e o modelo inicial](docs/architecture.md),
[o plano com critérios de aceite](docs/implementation-plan.md) e
[a especificação original](docs/specification.md).

O Symfony concentra regras e permissões. `UserMinistry` representa participação N:N
e liderança específica, com vínculo único e status. As entidades já validam
invariantes locais; o banco reforça unicidade, referências e estados. A leitura de conteúdo privado
é filtrada no banco antes da paginação e contagem, com o mesmo escopo no acesso por ID.
PUBLIC significa visível a todos os usuários autenticados. Voters e políticas
consultam cargo, estado, participação e liderança atuais, sem confiar em campos do
cliente ou objetos antigos. A estratégia JWT/refresh está implementada na fase 4;
as primeiras rotas de negócio aplicam a autorização da fase 5.

As rotas incluem `GET /api/auth/permissions`, administração de usuários e os
módulos de ministérios, conteúdo, agenda, notificações e exclusão de conta.
Os contratos com parâmetros, limites e respostas estão em `docs/`. A interface
mobile não faz parte deste repositório.

```text
apps/
  api/                   Symfony, config, controllers, serviços e testes
  web/                   React + TypeScript + Vite, telas e testes de navegador
  mobile/                planejado; não existe neste checkout
packages/
  api-client/            futuro cliente HTTP compartilhado
  types/                 futuros contratos públicos
  validation/            futura validação de entrada dos clientes
  shared/                futuros helpers comuns
docker/
  php/                   imagem, PHP-FPM e verificação do processo
  nginx/                 entrada HTTP e encaminhamento FastCGI
  postgres/              inicialização do usuário restrito
docs/                    especificação, decisões e plano incremental
scripts/                 configuração local e smoke test
docker-compose.yml
.env.example
```

## Requisitos

No Windows: Docker Desktop iniciado, com backend WSL2, containers Linux e Docker
Compose v2.24 ou superior. No Linux/VPS: Docker Engine e plugin Compose. Instalação:
[Windows](https://docs.docker.com/desktop/setup/install/windows-install/) e
[Linux](https://docs.docker.com/engine/install/).

PHP, Composer, Node e PostgreSQL instalados no host não são necessários para executar
os containers. PHP 8.2+ no host é uma alternativa para o script de configuração e
testes locais. A imagem runtime utiliza PHP 8.4. Com o `.env.example`, Nginx usa
a porta local 8080 (`HTTP_PORT`) e a Web usa 5174 (`WEB_PORT`).

## Preparar e executar

Na raiz do projeto, gere `.env` com três segredos aleatórios independentes:

```powershell
php scripts/setup.php
php scripts/setup-auth.php
```

Os scripts preservam `.env` e chaves existentes e não exibem segredos. O primeiro
cria as variáveis básicas; o segundo cria as chaves JWT em `.secrets`. Se não houver PHP
local, com Docker já funcionando, execute no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace php:8.4-cli-alpine php scripts/setup.php
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace php:8.4-cli-alpine php scripts/setup-auth.php
```

Em shell Linux:

```sh
docker run --rm --user "$(id -u):$(id -g)" --mount "type=bind,source=$(pwd),target=/workspace" -w /workspace php:8.4-cli-alpine php scripts/setup.php
docker run --rm --user "$(id -u):$(id -g)" --mount "type=bind,source=$(pwd),target=/workspace" -w /workspace php:8.4-cli-alpine php scripts/setup-auth.php
```

Alternativamente copie `.env.example` para `.env` e preencha os três campos vazios
com valores aleatórios fortes. Use senha hexadecimal em `APP_DATABASE_PASSWORD`,
pois ela é incorporada à URL de conexão. Não publique o arquivo.

Login, refresh e logout exigem HTTPS por padrão. Para usar essas rotas no loopback
HTTP desta infraestrutura, acrescente `AUTH_ALLOW_INSECURE_LOCAL=1` ao `.env` local.
Esse modo usa um cookie de desenvolvimento separado. Mantenha `0` na implantação
externa. Consulte [autenticação](docs/authentication.md) para origens e contratos.

Inicie a infraestrutura:

```sh
docker compose config --quiet
docker compose up --build -d --wait
docker compose ps
```

O primeiro build instala as dependências a partir do lock. Depois, `docker compose up`
também inicia a base em primeiro plano. Alterações no código PHP exigem
`docker compose up --build -d --wait`, pois o código está incorporado à imagem.
O início do PHP-FPM prepara cache e proxies Doctrine. Não há instalação Composer
nem migrations automáticas no banco da aplicação a cada início.

Após o primeiro início ou uma atualização com novas migrations:

```sh
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:schema:validate
```

O projeto possui onze migrations. O comando de migrate executa apenas as versões
pendentes; o estado de implantação verificado está em [validation.md](docs/validation.md).

Verifique o caminho HTTP completo:

```powershell
.\scripts\smoke.ps1
```

Se a política local bloquear scripts PowerShell, use os comandos abaixo ou execute
com a política permitida pela sua organização. Em PowerShell ou shell Linux:

```sh
curl -f http://127.0.0.1:8080/api/health
curl -f http://127.0.0.1:8080/api/ready
```

No Windows PowerShell 5, use `curl.exe` para evitar o alias de `Invoke-WebRequest`.
Ambos devem responder `{"status":"ok"}`. Para outra porta, passe
`-BaseUrl http://127.0.0.1:SUA_PORTA` ao smoke test.

`http://127.0.0.1:8080/` é a entrada da API e responde 404; abra a Web em
`http://127.0.0.1:5174/`. Os endpoints health/ready são públicos e não retornam
informações internas. Rotas privadas exigem autenticação e autorização; endpoints
públicos específicos, como a solicitação pública de exclusão, estão documentados
em seus contratos. A exposição está configurada por Cloudflare Tunnel, mas a última
verificação pública de HTTPS expirou nesta estação.

## Variáveis de ambiente

| Variável | Uso |
| --- | --- |
| `COMPOSE_PROJECT_NAME` | Identifica serviços, redes e volumes deste projeto |
| `HTTP_PORT` | Porta Nginx, publicada apenas em `127.0.0.1` |
| `APP_ENV`, `APP_DEBUG` | `prod` e `0` por padrão; testes usam `test` |
| `APP_SECRET` | Segredo Symfony, obrigatório e gerado localmente |
| `POSTGRES_DB` | Banco criado no primeiro início |
| `POSTGRES_USER`, `POSTGRES_PASSWORD` | Superusuário de bootstrap/manutenção, não fornecido à API |
| `APP_DATABASE_USER`, `APP_DATABASE_PASSWORD` | Usuário restrito da API e migrations |
| `DATABASE_URL` | Construída pelo Compose e fornecida ao PHP; não precisa duplicá-la no `.env` |
| `JWT_ISSUER`, `JWT_AUDIENCE` | Identificadores validados em cada access token |
| `JWT_PRIVATE_KEY_PATH`, `JWT_PUBLIC_KEY_PATH` | Caminhos dos secrets montados pelo Compose |
| `AUTH_ALLOWED_ORIGINS` | Origens Web exatas, separadas por vírgulas |
| `AUTH_ALLOW_INSECURE_LOCAL` | `0` padrão; `1` habilita autenticação HTTP somente para Host de loopback |

O runtime Symfony recebe variáveis do processo; não lê `.env` da aplicação. O Compose
lê o `.env` da raiz e injeta somente os valores necessários a cada serviço. Não
execute `docker compose config` sem `--quiet` em logs compartilhados: a saída normal
pode conter segredos interpolados. Variáveis de ambiente não substituem controle de
acesso ao host/Docker. As chaves JWT já usam secrets montados somente para leitura.

Alterar senha no `.env` não altera a senha já armazenada no volume PostgreSQL.
O script de inicialização roda somente em volume vazio. Com dados existentes,
planeje a rotação no banco e na configuração de forma coordenada, sem apagar o volume.

## Rede e persistência

Nginx é o único serviço com porta no host, em loopback. Ele participa da rede
`application`, compartilhada com PHP. PostgreSQL participa somente da rede interna
`database`, compartilhada com PHP. Nginx não acessa o banco diretamente;
as portas 5432 e 9000 não são publicadas. PHP e PostgreSQL não têm saída externa
nessa configuração; serviços futuros de email/push exigirão ajuste explícito de rede.

Os testes usam `postgres-test`, banco `igreja_test` em tmpfs, e rede `test_database`
separada, sem acesso ao banco da aplicação. Não há porta publicada. Parar esse banco
descarta seus dados; o runner aplica as migrations novamente no próximo teste.

O volume `postgres_data` persiste o banco. O usuário da aplicação não é superusuário,
não cria bancos/roles e só recebe conexão e uso/criação no schema da aplicação.
Nesta base ele também executa migrations; separar identidade de migrations e runtime
antes de uma implantação que exija restrições de DDL em produção.

`pg_isready` aguarda o PostgreSQL aceitar conexões TCP; PHP possui ping FastCGI;
Nginx verifica `/api/health`. `/api/ready` confirma a conexão real com as credenciais
da aplicação. A opção `--wait` verifica saúde dos serviços, e o smoke test completa
a verificação do acesso da aplicação ao banco.

```sh
docker compose logs --tail=100 nginx php postgres
docker compose restart php
docker compose down
```

`down` preserva o volume. `down -v` apaga os dados: não utilizar como solução de rotina.
Logs possuem rotação; a aplicação evita registrar mensagem de exceção, credenciais
e parâmetros de conexão. Não há logs de acesso Nginx nesta etapa. Datas usam UTC.

## Testes

No Docker, o serviço opcional instala dependências de desenvolvimento, inicia o
PostgreSQL isolado, aplica migrations, valida o schema e prepara o cache antes dos
testes unitários, funcionais e de integração:

```sh
docker compose --profile tools run --build --rm api-test
docker compose exec php php bin/console lint:container
docker compose exec php php bin/console lint:yaml config
```

Os serviços `api-test` e `postgres-test` não são iniciados por `docker compose up` comum. O build verifica
as extensões PHP via `composer check-platform-reqs`. O runtime não inclui PHPUnit.

Alternativa no host, com PHP 8.2+ e extensões exigidas pelo Composer:

```sh
cd apps/api
composer install
composer validate --strict
composer test
```

`composer test` roda a suíte sem depender de PostgreSQL. Os doubles de conexão são
exclusivos dos testes; o runtime sempre usa Doctrine. `composer test:integration`
exige `RUN_DATABASE_TESTS=1` e `DATABASE_URL` de um banco migrado, com nome terminado
em `_test`; sem a flag os testes integrados são ignorados. A suíte consulta o nome
real do banco antes de inserir fixtures. Testes do domínio usam rollback; testes
de autenticação confirmam transações e removem apenas suas próprias fixtures. Prefira o runner
Docker, que também restringe o host a `postgres-test`. Rodar testes no host não
valida rede Docker, PHP-FPM, Nginx ou persistência.

**Registro desta entrega:** resultados finais em [validation.md](docs/validation.md).

## Banco e migrations

As entidades `User`, `Ministry`, `UserMinistry`, `Post`, `Comment`, `Event` e
`MinistrySchedule`, as três entidades de autenticação e `AuditLog` estão mapeadas
em `apps/api/src/Entity`. Enums e repositórios ficam em `src/Enum` e `src/Repository`.

A migration inicial `apps/api/migrations/Version20260910170000.php` cria as tabelas
de domínio base, com IDs identity, FKs, índices, unicidade e CHECKs. Migrations
posteriores adicionaram autenticação, auditoria, imagens, notificações e exclusão
de contas. Conteúdo privado exige ministério; publicação publicada exige data;
intervalos e estados inválidos são recusados pelo banco. Email normalizado e slug
são únicos, assim como cada par usuário/ministério. Imagens de posts e eventos
usam tabelas próprias com referências ao conteúdo associado.

`Version20260910200000` adiciona sessões, refresh tokens e limites de autenticação.
`Version20260911050000` adiciona `audit_logs`, com FK de ator, índices e CHECKs,
preservando as tabelas existentes. Mudanças de cargo/status e seus registros de
auditoria são confirmados juntos pelo serviço administrativo.

O SQL de bootstrap cria somente a identidade restrita e suas permissões.
O banco inicial é criado pela imagem PostgreSQL. Comandos de administração:

```sh
docker compose exec php php bin/console doctrine:migrations:status
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:schema:validate
```

Não usar `doctrine:schema:update --force` como estratégia de implantação. Faça backup
antes de alterações relevantes. O usuário restrito não possui `CREATEDB` por decisão.

## Backup e recuperação

Gere um backup completo do banco e uploads com:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/backup.ps1
```

O script mantém 14 dias por padrão e grava checksums no manifesto. Para validar a
recuperação sem tocar no banco principal:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-restore.ps1 -BackupDirectory backups/<data-do-backup>
```

Instale as rotinas diárias do Agendador de Tarefas com
`scripts/install-scheduled-tasks.ps1`. O procedimento completo, retenção e metas de
recuperação estão em [operations.md](docs/operations.md). Mantenha uma cópia
criptografada fora deste computador.

## Módulos e situação

Fases 2–12 e 14–15 implementadas: infraestrutura, domínio, autenticação,
autorização, usuários, ministérios, conteúdo, agenda, Web, notificações e acesso
externo seguro. O cliente mobile não está neste checkout; a API oferece suporte
para clientes nativos. Veja [notifications.md](docs/notifications.md).
Fase 16 implementa solicitações de exclusão, anonimização transacional, painel
administrativo, páginas públicas e contrato da API. Escopo, rotas e pendências de publicação em
[account-deletion.md](docs/account-deletion.md).
A criação inicial é
controlada por `docker compose exec php php bin/console app:admin:create`, com senha
oculta e sem credenciais padrão. O primeiro ADMIN local foi criado pelo usuário.

Os métodos de entidade protegem invariantes locais; serviços e Voters autorizam
o ator. A API já permite consultar usuários e alterar cargo/status com as salvaguardas
da fase 5. A interface administrativa Web está disponível no domínio principal.

Web (fase 12): [instruções](docs/web.md). Contratos para clientes mobile:
[autenticação](docs/authentication.md) e [exclusão de conta](docs/account-deletion-mobile.md).
A exposição está configurada por Cloudflare Tunnel nos hosts `guitupinamba.dev`
e `api.guitupinamba.dev`, com origens restritas. A conferência pública de HTTPS
mais recente expirou desta estação; veja [validation.md](docs/validation.md).

Na atualização da fase 16 em 05/11/2026, `Version20261104120000` foi aplicada ao
banco ativo após backup com restauração verificada; Web e API foram reconstruídas
e as verificações locais passaram. A suíte unitária passou (133 testes / 280
assertions) e a integrada passou (326 testes / 3.356 assertions; dois testes de
imagem ignorados no host sem GD, instalado na imagem Docker). A conferência direta
dos domínios em HTTPS expirou nesta estação e requer validação por rede externa.
Resultados e pendências estão em [validation.md](docs/validation.md).
