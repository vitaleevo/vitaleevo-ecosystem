# Vitaleevo API — referência do MVP

Base local: `http://localhost:8000/api/v1`.

## Convenções

- Autenticação web: Laravel Sanctum stateful + sessão/cookies HttpOnly + XSRF.
- Antes de operações autenticadas no browser: `GET /sanctum/csrf-cookie` e enviar `X-XSRF-TOKEN`.
- Contexto multiempresa: vem do `current_tenant_id` autenticado e da membership ativa; o cliente não envia `tenant_id` para escopar recursos.
- Respostas de listas paginadas seguem o formato Laravel (`data`, `current_page`, `last_page`, etc.).
- Erros de validação: HTTP `422`; sem autenticação `401`; sem autorização `403`; precondição MFA `428`; recurso inexistente/fora do tenant `404` quando aplicável.

## Público

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/health` | Readiness de base de dados e cache |
| GET | `/plans` | Planos beta ativos |
| GET | `/service-categories` | Categorias públicas ativas |
| POST | `/auth/register` | Conta + tenant + subscrição inicial |
| POST | `/auth/login` | Login por sessão |
| POST | `/auth/forgot-password` | Solicitar reset |
| POST | `/auth/reset-password` | Concluir reset |
| GET | `/auth/verify-email/{id}/{hash}` | Verificar email por URL assinada |
| POST | `/auth/invitations/accept` | Aceitar convite de workspace |
| POST | `/public/service-requests` | Criar pedido público; exige `Idempotency-Key` |
| POST | `/public/service-requests/status` | Consultar por referência + telefone |
| POST | `/public/service-requests/feedback` | Avaliar pedido concluído |

Registo requer `name`, `email`, `password`, `password_confirmation`, `company_name`, `terms_accepted`; aceita `plan_code=trial|basic|advanced`.

## Sessão autenticada

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/me` | Utilizador, memberships, tenant ativo e estado MFA |
| POST | `/auth/logout` | Terminar sessão |
| POST | `/auth/accept-terms` | Registar aceitação dos termos atuais |
| POST | `/auth/verification-notification` | Reenviar email de verificação |
| GET | `/auth/mfa/setup` | Gerar chave TOTP do Platform Owner |
| POST | `/auth/mfa/enable` | Confirmar TOTP e receber recovery codes |
| POST | `/auth/mfa/verify` | Validar TOTP/recovery code da sessão |
| POST | `/auth/mfa/recovery-codes` | Regenerar recovery codes com TOTP atual |
| GET | `/tenants` | Tenants do utilizador |
| POST | `/tenants` | Criar novo tenant e subscrição |
| POST | `/tenants/{tenant}/switch` | Trocar contexto autorizado |

## CRM do tenant

Todas estas rotas exigem tenant ativo + email verificado.

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/dashboard` | KPIs básicos |
| GET/POST | `/contacts` | Listar/pesquisar e criar contactos |
| PATCH | `/contacts/{id}` | Atualizar contacto do tenant |
| GET | `/pipelines` | Funis e etapas |
| GET/POST | `/opportunities` | Filtrar/listar e criar oportunidades |
| PATCH | `/opportunities/{id}` | Etapa, responsável, valor ou estado `open/won/lost` |
| GET | `/opportunities/{id}/history` | Histórico imutável de mudanças |
| GET/POST | `/tasks` | Filtrar/listar e criar tarefas |
| PATCH | `/tasks/{id}` | Atualizar/concluir tarefa |
| GET/PATCH | `/settings/company` | Configurações básicas e notificações |
| GET/POST/PATCH | `/memberships[...]` | Membros, convites, papel e estado |

Filtros: `contacts?q=...`; `opportunities?q=&status=&owner_id=&pipeline_stage_id=`; `tasks?q=&status=&assigned_to=`.

## Pedidos e profissionais

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/service-requests` | Pedidos atribuídos ao tenant + feedback |
| PATCH | `/service-requests/{id}` | Atualizar estado |
| POST | `/service-requests/{id}/assignments` | Atribuir profissional ativo/verificado |
| GET | `/professionals` | Catálogo operacional ativo/verificado |
| POST | `/professionals` | Criar perfil global; somente Platform Owner |
| GET | `/professional/jobs` | Trabalhos do profissional autenticado |
| PATCH | `/professional/jobs/{id}` | Aceitar, recusar, iniciar ou concluir |

## Platform Owner

Prefixo `/platform`. Exige Platform Owner + email verificado + MFA configurado e validado na sessão (validade 12 h).

- `/platform/metrics` — KPIs globais.
- `/platform/tenants` — listar, suspender/reativar e trocar plano; mudança de plano sincroniza `subscriptions`.
- `/platform/plans` — preços/estado dos planos beta.
- `/platform/service-categories` — catálogo global.
- `/platform/professionals` — validação e ativação de profissionais.
- `/platform/service-requests` — pedidos públicos sem tenant e encaminhamento.
- `/platform/audit-logs` — auditoria global recente.

## Segurança operacional

Login, recuperação, MFA, pedidos públicos e consulta de estado possuem throttling. Pedidos públicos exigem idempotência. Convites têm token one-time com hash e expiração. MFA usa TOTP e recovery codes one-time armazenados apenas como hashes; o segredo TOTP é cifrado pelo `APP_KEY`.
