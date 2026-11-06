# Privacidade e exclusão de contas

Esta documentação descreve as rotas, os dados envolvidos, o fluxo de revisão e
as operações implementadas na API e na Web. A migration da funcionalidade é
`apps/api/migrations/Version20261104120000.php`. Ela foi aplicada em
05/11/2026 ao banco ativo `quadrangular`, após backup restaurável, e a versão
Web/API foi reconstruída. As verificações locais passaram. A validação direta
dos domínios públicos em HTTPS não pôde ser concluída a partir desta estação;
aguarda confirmação por uma rede externa antes do anúncio do fluxo.

## Páginas Web

- `GET /privacidade`: política pública, sem restauração de sessão.
- `GET /excluir-conta`: formulário público, sem restauração de sessão e sem
  solicitação de senha.
- A área administrativa lista e processa pedidos para ADMIN e PASTOR ativos; as
  operações são reautorizadas pela API. PASTOR não acessa pedidos associados a
  ADMIN.

## Contrato da API

Use `Content-Type: application/json` e envie objetos JSON. A URL-base de
produção configurada no Web é `https://api.guitupinamba.dev/api`; nos exemplos,
`{API}` representa essa base sem barra final. Rotas autenticadas exigem
`Authorization: Bearer <access_token>` da sessão atual. Respostas de pedidos
contêm `Cache-Control: no-store` e não devem ser armazenadas por clientes.

### Solicitação pública

`POST {API}/account-deletion-requests`

Não exige autenticação. Campos permitidos:

```json
{
  "name": "Nome completo",
  "email": "pessoa@example.com",
  "confirm": true,
  "additional_information": "Informação opcional",
  "website": ""
}
```

`name` é obrigatório (1–120 caracteres); `email` é obrigatório e deve ser um
endereço válido (máximo 180 caracteres); `confirm` deve ser `true`.
`additional_information` é opcional, até 2.000 caracteres. `website` é um campo
honeypot opcional, que deve permanecer vazio. O corpo é limitado a 8 KiB.
Não envie senha, documentos ou dados sensíveis.

Resposta `202 Accepted`:

```json
{
  "message": "Recebemos sua solicitação. A equipe fará a validação manual da identidade. O envio não significa que a conta já foi excluída.",
  "reference": "0123456789abcdef0123456789abcdef"
}
```

A resposta tem o mesmo formato e status independentemente de o e-mail existir.
O honeypot também devolve uma referência e `202`, sem criar pedido. O limite é
três tentativas por e-mail e vinte por IP em janelas fixas de 15 minutos. Os
identificadores do limitador são HMAC; contadores com mais de um dia são limpos
quando chegam novos pedidos. No tráfego via Cloudflare, a API usa
`CF-Connecting-IP` somente quando Symfony reconhece a conexão como proveniente
de proxy confiável; em conexão direta, usa o IP do peer. Não confia no
`X-Forwarded-For` enviado pelo cliente. Isso depende de manter o Nginx de origem
restrito ao Cloudflare Tunnel/loopback, como no Compose. Cloudflare documenta o
cabeçalho [`CF-Connecting-IP`](https://developers.cloudflare.com/fundamentals/reference/http-headers/);
Symfony documenta [proxies e cabeçalhos confiáveis](https://symfony.com/doc/current/deployment/proxies.html).

### Solicitação autenticada

`POST {API}/profile/account-deletion-request`

Exige access token válido e a sessão ativa que o originou. Corpo:

```json
{ "confirm": true }
```

Resposta `202 Accepted`:

```json
{
  "message": "Recebemos sua solicitação. A equipe fará a análise antes de concluir a exclusão.",
  "reference": "0123456789abcdef0123456789abcdef"
}
```

A conta é obtida da sessão, nunca de um ID fornecido pelo cliente. O pedido fica
`PENDING`, associado à conta e marcado como validado por
`AUTHENTICATED_SESSION`; a exclusão não é automática. Um pedido aberto da mesma
conta impede outro pedido autenticado. A sessão continua sob controle das regras
normais de autenticação até a conclusão administrativa.

### Revisão administrativa

Todas as rotas abaixo exigem Bearer token de sessão atual de usuário ativo com
cargo ADMIN ou PASTOR. ADMIN pode processar contas comuns e contas ADMIN, sujeito
à proteção do último ADMIN. A listagem de PASTOR omite pedidos associados a
ADMIN; acesso direto ao detalhe ou às ações desses pedidos responde `403`. PASTOR
pode processar pedidos de contas comuns. Nenhuma autorização depende somente da
navegação ou das permissões exibidas na Web.

Listagem:

```http
GET {API}/admin/account-deletion-requests?page=1&limit=20&status=PENDING
```

`page` começa em 1; `limit` padrão é 20 e máximo 100; `status` é opcional e deve
ser `PENDING`, `UNDER_REVIEW`, `APPROVED`, `REJECTED` ou `COMPLETED`. A resposta
filtra pelo cargo do ator antes da paginação:

```json
{
  "items": [
    {
      "reference": "0123456789abcdef0123456789abcdef",
      "name": "Nome completo",
      "email": "pessoa@example.com",
      "status": "PENDING",
      "requested_at": "2026-11-04T12:00:00+00:00",
      "identity_verified_at": null,
      "processed_at": null,
      "rejection_reason": null
    }
  ],
  "pagination": { "page": 1, "limit": 20, "total": 1 }
}
```

Detalhe:

```http
GET {API}/admin/account-deletion-requests/{reference}
```

O identificador é hexadecimal de 32 caracteres. A resposta contém `request` com
os campos da listagem, `additional_information`,
`identity_verified_by_id`, `processed_by_id`, `identity_verified_by_name`,
`processed_by_name` e `history`. Cada entrada do histórico contém `action`,
`actor_id` (ou `null`) e `created_at`. Os dados adicionais e de contato são
apagados ao rejeitar ou concluir o pedido.

Ações, sempre `POST` e sem query string:

| Rota | Corpo | Pré-condição e resposta |
| --- | --- | --- |
| `.../{reference}/review` | `{}` ou corpo vazio | `PENDING` → `UNDER_REVIEW`; responde `{ "status": "UNDER_REVIEW" }`. |
| `.../{reference}/verify-identity` | `{}` ou corpo vazio | Em `UNDER_REVIEW`; ADMIN confirma que fez contato fora do sistema. Associa a conta pelo e-mail validado e registra ator e horário, sem guardar prova/documentos. Responde com status e `identity_verified_at`. |
| `.../{reference}/approve` | `{}` ou corpo vazio | Exige identidade validada e conta relacionada; `UNDER_REVIEW` → `APPROVED`; responde status e `processed_at`. |
| `.../{reference}/reject` | `{ "reason": "Justificativa" }` | Exige justificativa não vazia, até 1.000 caracteres. Pode rejeitar pedido aberto; limpa nome, e-mail e informação adicional. Responde status e `processed_at`. |
| `.../{reference}/complete` | `{}` ou corpo vazio | Exige `APPROVED`; executa a exclusão transacional e responde `reference`, `status` e `processed_at`. |

Corpos `{}` podem ser enviados como JSON; também se aceita corpo vazio nas ações
que não têm campos. Campos extras são recusados.

### Respostas de erro

| HTTP | Uso neste fluxo |
| --- | --- |
| `400` | JSON inválido, `Content-Type` incorreto ou corpo público acima de 8 KiB. |
| `401` | Access token, usuário ou sessão ausente, expirada ou revogada. |
| `403` | Papel sem autorização ou PASTOR tentando acessar pedido de ADMIN. |
| `404` | Referência inexistente ou malformada. |
| `409` | Pedido duplicado, transição inválida, identidade não associada ou proteção do último ADMIN. |
| `422` | Campo inválido, parâmetro de paginação/filtro inválido ou campo adicional não permitido. |
| `429` | Limite público excedido; a resposta inclui `Retry-After`. |

Clientes devem tratar status HTTP como fonte do resultado, não inferir a existência
de uma conta a partir da referência pública. Os erros seguem o envelope JSON
comum da API.

## Fluxo e validação de identidade

```text
PENDING → UNDER_REVIEW → APPROVED → COMPLETED
    └───────────────→ REJECTED
                     ↑             ↑
          UNDER_REVIEW ────────────┘
          APPROVED ────────────────┘
```

Transições permitidas:

| Estado atual | Próximo estado permitido |
| --- | --- |
| `PENDING` | `UNDER_REVIEW` ou `REJECTED` |
| `UNDER_REVIEW` | `APPROVED` após registrar validação, ou `REJECTED` |
| `APPROVED` | `COMPLETED` ou `REJECTED` |
| `REJECTED`, `COMPLETED` | Nenhum; estados terminais |

Um pedido público não pode ser aprovado sem registro da validação manual. O
procedimento aprovado é contatar a pessoa fora do sistema pelo canal de
atendimento disponibilizado, verificar identidade sem considerar o conhecimento
do e-mail como prova suficiente e marcar a confirmação no painel. Um ADMIN ou
PASTOR autorizado pode registrar a confirmação para uma conta comum; somente
ADMIN pode processar pedido de outra conta ADMIN. Não armazenar cópias de
documentos nem evidências desnecessárias. Solicitações autenticadas identificam
a conta pela sessão, mas ainda passam por análise e aprovação administrativa.

Não há envio automático de e-mail nem prazo universal configurado. Após validar
a identidade, a equipe comunica por contato fora do sistema a previsão para cada
caso. O formulário avisa que recebimento não significa exclusão imediata.

## Dados removidos, preservados e retenção

Na conclusão, em uma transação PostgreSQL, a linha da conta é anonimizada como
`Conta excluída`, e-mail sintético em domínio reservado `.invalid`, senha
aleatória sem credencial conhecida, cargo MEMBER, estado INACTIVE, telefone e
nascimento removidos. A linha tombstone preserva as FKs de posts, eventos,
atividades e arquivos que permanecem sem autoria pessoal.

São removidos comentários, sessões, refresh tokens e cadeias, dispositivos push,
preferências, vínculos com ministérios, destinatários e notificações direcionadas
ou criadas pela conta. Auditorias que tinham a conta como ator perdem ator e
metadados; auditorias sobre o próprio registro de usuário são excluídas. O último
ADMIN ativo não pode ser excluído; somente ADMIN pode concluir exclusão de outro
ADMIN.

Ao rejeitar ou concluir um pedido, nome, e-mail e informação adicional são
apagados. O histórico mantém referência, estado, datas, responsáveis e motivo de
rejeição. O motivo é texto livre limitado a 1.000 caracteres; operadores devem
registrar apenas a justificativa necessária e não incluir dados sensíveis.

A política Web lista as categorias de cadastro/perfil, participação,
publicações/interações, eventos/agenda, sessões/segurança, notificações e pedidos
de exclusão; explica finalidades, Cloudflare e os serviços Expo Push/FCM quando
notificações push são usadas, direitos LGPD e contato. O e-mail
`contatoguitupinamba@gmail.com` é apresentado somente como canal disponibilizado,
não como endereço institucional.

Retenção operacional documentada: até 14 dias em cópias locais e até 30 dias em
cópias externas. A aplicação ainda não configura expurgo automático nem prazo
geral para sessões expiradas e logs de auditoria. Essa definição e a revisão de
possível dado pessoal dentro de anexos preservados são pendências operacionais;
a política não deve ser tratada como confirmação de uma base legal específica.

## Implantação e checklist de publicação

Migration e Web/API foram implantadas em 05/11/2026. Backup e restauração,
schema e operações locais foram verificados. Antes de anunciar o fluxo:

1. Confirmar pela Internet móvel ou outra rede externa as páginas
   `https://guitupinamba.dev/privacidade`,
   `https://guitupinamba.dev/excluir-conta` e o POST público contra a API.
2. Criar um pedido de teste controlado, validar, rejeitar/concluir conforme o
   procedimento e confirmar revogação, tombstone e ausência de PII residual.
3. Configurar no Play Console o URL Web de exclusão e conferir a declaração
   Data safety contra os SDKs e práticas do build que será publicado.
4. Revisar e definir a retenção geral de logs/sessões expiradas e a avaliação de
   anexos, então atualizar a política caso a operação mude.

O requisito do Google Play inclui um caminho de solicitação dentro do app e um
recurso Web funcional. Este repositório não contém o cliente mobile: a API
autenticada está pronta e o contrato de integração está em
[account-deletion-mobile.md](account-deletion-mobile.md), mas a interface dentro
do app ainda precisa ser implementada no projeto mobile. Não se declara
conformidade até validar o caminho interno no cliente que será publicado. Consulte
a [orientação atual do Google Play](https://support.google.com/googleplay/android-developer/answer/13327111).

## Validação registrada

Os testes integrados focados e o teste de regressão do rate limit passaram. A
suíte unitária passou (133 testes / 280 assertions), o build Web passou e os dois
cenários Playwright reportaram sucesso. A suíte completa passou (326 testes /
3.356 assertions; dois testes de imagem foram ignorados no PHP do host sem GD).
Em 05/11/2026 a migration foi aplicada ao banco ativo após backup e restauração
de validação; health, ready, Web, formulário com honeypot e operações locais
passaram. A verificação HTTPS externa permanece pendente por timeout de saída
desta estação. Detalhes em [validation.md](validation.md).
