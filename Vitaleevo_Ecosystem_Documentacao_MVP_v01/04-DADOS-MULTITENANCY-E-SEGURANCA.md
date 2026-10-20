# Dados, multiempresa e segurança
## Modelo de tenancy
Base de dados PostgreSQL partilhada no MVP, com `tenant_id` obrigatório em todas as entidades empresariais. Dados de plataforma (planos, categorias globais, auditoria central) possuem regras de acesso distintas. Um utilizador pode pertencer a várias empresas por `memberships` e escolher o contexto activo.

## Entidades mínimas
- `users(id, name, email, password_hash, email_verified_at, ...)`
- `tenants(id, name, slug, status, ...)`
- `memberships(id, tenant_id, user_id, role, status, ...)`
- `contacts(id, tenant_id, name, email, phone, ...)`
- `pipelines(id, tenant_id, name, ...)`
- `pipeline_stages(id, tenant_id, pipeline_id, name, position, ...)`
- `opportunities(id, tenant_id, contact_id, pipeline_stage_id, owner_id, value, status, ...)`
- `tasks(id, tenant_id, opportunity_id, assigned_to, due_at, status, ...)`
- `service_categories(id, name, active, ...)` — catálogo global
- `service_requests(id, tenant_id NULLABLE, customer_contact, category_id, status, public_reference, ...)` — titularidade por política explícita
- `professionals(id, user_id, status, verification_state, ...)`
- `service_assignments(id, request_id, professional_id, status, ...)`
- `plans`, `subscriptions`, `audit_logs`, `outbox_events`, `idempotency_keys`.

**Atenção:** pedidos públicos e profissionais são transversais à plataforma. Não aplicar `tenant_id` arbitrariamente: definir titularidade e autorização caso a caso; usar `platform_scope` ou vínculo explícito, nunca consulta global indiscriminada.

## Índices e invariantes
Índices compostos `(tenant_id, id)`, `(tenant_id, status)`, `(tenant_id, created_at)` nos casos de consulta. Unicidade por tenant para slug, referências e integrações onde aplicável. FKs compostas para impedir associação cruzada quando possível. Auditoria com actor, tenant, recurso, acção, timestamp e metadados redigidos.

## Segurança obrigatória
- Resolver tenant exclusivamente a partir de sessão autenticada/membership e contexto autorizado; nunca confiar num `tenant_id` fornecido pelo browser.
- Policies e escopos em queries; testes adversariais de IDs cruzados, exports, anexos e jobs.
- Tokens curtos, cookies HttpOnly/Secure/SameSite apropriados, CSRF onde aplicável, MFA para owner antes de produção pública.
- Password hashing padrão Laravel, throttling de login, email verification, expiração de convites.
- Segredos só no servidor e gestor de segredos; nunca em `NEXT_PUBLIC_*`.
- Webhooks com assinatura, replay protection, idempotência, fila e limites de tamanho.
- Upload com validação MIME, limites, scanning quando viável, object keys privadas e URLs temporárias.
- Encriptação em trânsito e em repouso; backups cifrados e restauração testada.
- Privacidade: consentimento, retenção, exportação, eliminação e minimização de dados alinhados à legislação aplicável em Angola; validação jurídica antes do lançamento.

## Testes de isolamento
Criar tenants A/B, utilizadores A/B e dados semelhantes; verificar 403/404 e zero side effects para listagem, detalhe, pesquisa, exportação, upload, notificação, jobs, cache e webhook. Nunca aceitar apenas testes de controller feliz.
