# APIs, integrações e eventos
## Convenções
Base `/api/v1`, JSON, datas ISO 8601 UTC, valores monetários em unidades menores ou inteiro Kz com convenção documentada; nunca float para dinheiro. IDs UUID/ULID por decisão consistente. Erros no formato RFC 9457 Problem Details quando aplicável.

## Endpoints P0 (contratos de alto nível)
- `POST /auth/register`, `POST /auth/login`, `POST /auth/logout`, `GET /me`.
- `GET/POST /tenants`, `POST /tenants/{id}/switch`, `GET/POST /memberships`.
- `GET/POST /contacts`, `GET/PATCH /contacts/{id}`.
- `GET/POST /pipelines`, `GET/POST /opportunities`, `PATCH /opportunities/{id}`.
- `GET/POST /tasks`, `PATCH /tasks/{id}`.
- `GET /service-categories`, `POST /public/service-requests`.
- `GET /service-requests`, `GET/PATCH /service-requests/{id}`.
- `POST /service-requests/{id}/assignments`, `PATCH /assignments/{id}`.
- `GET /professional/jobs`, `PATCH /professional/jobs/{id}`.
- `GET /platform/tenants`, `GET /platform/metrics`.

**Nota:** endpoints ilustrativos; nomes finais e payloads precisam de OpenAPI antes de implementação. Rotas públicas não aceitam leitura por ID sem prova de posse.

## Eventos internos
`TenantCreated`, `MemberInvited`, `ContactCreated`, `OpportunityStageChanged`, `ServiceRequestSubmitted`, `ServiceRequestAssigned`, `ServiceRequestCompleted`, `SubscriptionChanged`.

Usar outbox transaccional quando evento externo ou efeito financeiro exigir entrega fiável. Consumidores idempotentes; correlação por `event_id`, `tenant_id`, `request_id`. Evitar event bus distribuído no MVP.

## Estratégia de canais
1. Formulário web e notificações email: MVP.
2. Facebook/Instagram via Graph API e OAuth oficial: fase seguinte, com permissões aprovadas.
3. WhatsApp Cloud API com Embedded Signup e eventual coexistência apenas conforme elegibilidade e aprovação Meta.
4. TikTok: apenas quando endpoints e permissões necessárias estiverem disponíveis.

## Segurança de integrações
Tokens encriptados, rotação/revogação, webhooks verificados, retries com backoff, rate limits, auditoria de ligações e desligamentos, escopo por tenant. Nunca recolher passwords de redes sociais nem usar scraping para simular APIs.

## Contratos externos
Toda integração tem adapter, interface mínima, testes mockados, feature flag, timeout, retry limitado e documentação de quotas/custos. Implementar somente depois de obter credenciais de sandbox e caso de uso aprovado.
