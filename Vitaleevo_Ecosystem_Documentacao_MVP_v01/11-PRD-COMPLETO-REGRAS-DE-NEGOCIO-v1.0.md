# VITALEEVO ECOSYSTEM — PRD E REGRAS DE NEGÓCIO
Versão 1.0 | 20/10/2026 | Estado: especificação proposta para implementação do MVP

## 1. Visão do produto
O Vitaleevo Ecosystem é uma plataforma SaaS multiempresa para captar clientes, gerir vendas e prestar serviços. Possui três experiências ligadas: portal público de pedidos, workspace CRM de empresas e backoffice operacional da Vitaleevo. Profissionais têm um portal restrito para executar trabalhos atribuídos. O CRM é módulo nativo do Ecosystem; não reutilizamos código HolyCRM nesta base.

## 2. Objectivo do MVP e fronteiras
Uma empresa regista-se, cria o seu espaço, convida colaboradores, gere contactos/oportunidades/tarefas e acompanha actividade. Qualquer cliente pode solicitar um serviço; a operação central Vitaleevo valida, distribui a profissional verificado, acompanha e conclui. MVP inicialmente sem pagamento integrado, sem atribuição automática, sem inbox social omnicanal. Estas capacidades são P1/P2 e nunca bloqueiam as jornadas principais.

## 3. Actores e permissões
- Visitor/Customer: consultar serviços, submeter pedido com consentimento, acompanhar apenas mediante referência + prova de posse por canal verificado/token temporário; nunca listar pedidos alheios.
- Platform Owner: criar/configurar planos, catálogo e políticas; gerir empresas, profissionais, pedidos centrais e auditoria. Exigir MFA antes do beta público.
- Platform Operator: triar, atribuir e acompanhar pedidos centrais; não alterar planos, segurança ou tenants fora das permissões.
- Tenant Owner: gerir a empresa, membros, CRM, pedidos da própria empresa e definições.
- Tenant Admin: gerir CRM e equipa conforme privilégios atribuídos; sem acesso global.
- Tenant Agent: gerir contactos, oportunidades e tarefas atribuídas/autorizadas; sem alterar subscrição.
- Professional: acesso apenas aos trabalhos a si atribuídos, disponibilidade e provas de execução.
- Um utilizador pode pertencer a várias empresas; mudança de contexto exige membership activo verificado no backend.

## 4. Modelo operacional e titularidade
- Pedido criado no portal público é propriedade operacional da plataforma Vitaleevo por defeito e não aparece no CRM de tenants arbitrários.
- Pedidos originados no espaço de uma empresa pertencem à respectiva empresa; eventual encaminhamento à operação central requer aceite e acordo comercial.
- Profissionais são recursos transversais da plataforma com acesso mínimo a dados essenciais do serviço. Dados privados dos tenants não são partilhados com profissionais fora do trabalho.
- Nenhum profissional pode aceitar serviços de forma livre a partir de um catálogo global no MVP: primeiro há atribuição pela operação.
- A proposta comercial final, facturação, recebimento e responsabilidade por reclamações exigem política aprovada antes de aceitar pagamentos reais.

## 5. Jornadas ponta a ponta
### 5.1 Registo e onboarding
Registo -> verificação email -> criação tenant -> plano beta -> convite da equipa -> dashboard. Estados de tenant: draft, active, suspended, closed. Empresa suspensa não pode executar operações de negócio; manter acesso de administrador a dados/exportação conforme política. Convites expiram e não criam acesso antes de aceites.

### 5.2 CRM
Contacto (lead ou cliente) -> oportunidade -> etapa do pipeline -> tarefa/seguimento -> ganho ou perdido -> histórico. Cada empresa pode gerir o próprio funil; deve existir funil padrão inicial. Contacto pode existir sem oportunidade; oportunidade tem contacto, etapa, responsável e histórico. Não eliminar registos financeiros/históricos silenciosamente; preferir arquivo quando aplicável.
Regras: valores não negativos em Kz inteiros; probabilidade 0–100 se exibida; etapa ganha/perdida exige estado coerente; alterações registadas em auditoria; proibir referência a contacto/etapa de outro tenant.

### 5.3 Pedido de serviço
Formulário público -> referência única -> confirmação -> fila de triagem -> validação da categoria, localização e informação -> orçamento se necessário -> atribuição a profissional apto -> aceite/recusa -> em execução -> concluído -> validação pela operação -> feedback -> encerrado.
Estados permitidos: submitted, triage, awaiting_information, quoted, awaiting_customer_approval, ready_for_assignment, offered, accepted, in_progress, delivered, completed, cancelled, disputed.
Transições verificadas no backend; saltos de estado bloqueados; cancelamento guarda motivo, actor, data. Cada atribuição gera histórico; apenas uma atribuição activa principal por pedido no MVP. Repetição de POST com a mesma idempotency key não cria duplicados.

### 5.4 Portal profissional
Candidatura -> verificação manual -> estado approved -> disponibilidade -> oferta atribuída -> aceitar/recusar no prazo definido pela operação -> início -> actualizações -> prova de conclusão -> revisão. Profissionais reprovados/inactivos não recebem atribuições.

### 5.5 Administração
Owner consulta tenants, colaboradores, profissionais, catálogo, fila central, estado de subscrições beta, auditoria e indicadores reais. Acções sensíveis exigem autorização, registo e confirmação explícita. Suspensão é reversível; eliminação definitiva segue política legal e retenção.

## 6. Ecrãs e navegação
### Público
Landing (benefícios claros, CTA), catálogo de serviços, descrição de serviço, formulário de pedido, confirmação e acompanhamento seguro, login/registo, páginas legais.
### Empresa
Dashboard, Contactos, Contacto detalhado, Oportunidades (Kanban e lista), Tarefas, Pedidos próprios, Equipa, Definições, Subscrição.
### Operação / Owner
Visão operacional, Pedidos e triagem, Profissionais/verificação, Atribuições, Empresas, Planos beta, Auditoria e Monitorização.
### Profissional
Trabalhos, detalhe de trabalho, disponibilidade, perfil.
Toda página deve ter estados de loading, vazio, erro, sem permissão e confirmação de acções destrutivas. Layout responsivo; CRM prioriza desktop, portal público prioriza telemóvel.

## 7. Dados e multi-tenancy
Entidades mínimas: users, tenants, memberships, roles/permissions, contacts, client_organizations, pipelines, pipeline_stages, opportunities, opportunity_history, tasks, service_categories, service_requests, service_request_events, professionals, professional_skills, service_assignments, plans, subscriptions, invitations, notifications, audit_logs, idempotency_keys, outbox_events.
Dados empresariais levam tenant_id obrigatório, FKs e índices por tenant. Pedidos centrais não recebem tenant fictício; ownership de plataforma é explícito. Backend resolve tenant a partir de sessão e membership, nunca aceita tenant_id arbitrário do frontend. IDs ULID ou UUID consistentes. Datas UTC; interface Africa/Luanda por padrão; moeda AOA e inteiros em kwanza.

## 8. Regras de segurança
Sessão protegida com cookie HttpOnly/Secure, protecção CSRF onde aplicável, throttle login e formulários, verificação de email e políticas RBAC no servidor. Verificar tenancy em leitura, criação, edição, pesquisa, exportações, uploads, filas, cache e notificações. Logs sem tokens nem dados desnecessários. Upload privado e URLs temporárias. Backup e teste de recuperação antes de produção. MFA obrigatório para owner no beta público. Pedidos públicos usam rate-limit, honeypot ou captcha configurável e consentimento registado.

## 9. Planos, licenças e receitas
No MVP, plano BETA controlado por feature flags e limites configuráveis de membros/contactos, sem cobrança automática. Trial, suspensão, renovação e upgrade serão modelados, mas activação de cobrança real só após fornecedor, fiscalidade, política de cancelamento, facturação e testes. Propostas comerciais indicativas não são preços publicados. Regra futura: plano controla acesso a módulos e limites; desactivação impede novas acções, não elimina dados do tenant. Comissões por serviços só reconhecidas após conclusão e recebimento confirmado, sujeitas à política contratual.

## 10. Integrações e notificações
P0: emails transaccionais (verificação, convites, actualização do pedido), formulários públicos. P1: Meta Graph API, Facebook/Instagram, WhatsApp Cloud API/Embedded Signup com avaliação de coexistência; credenciais e aprovações oficiais obrigatórias. P2: TikTok, automações, IA, pagamentos, VITAERP e Academy. Nunca usar scraping nem guardar password de contas sociais. Eventos críticos idempotentes e auditáveis.

## 11. Contratos/API
API versionada /api/v1. Endpoints por domínio para auth, me, tenants, memberships, contacts, pipelines, opportunities, tasks, categories, service-requests, assignments, professionals e platform. Documentar OpenAPI e erros padronizados antes de consumir frontend. Listas paginadas com filtros; server-side validation; mutations idempotentes quando criam pedidos, convites ou eventos externos. Não inferir permissão da UI.

## 12. Critérios de aceitação (obrigatórios)
AC-01: utilizador cria e verifica conta, cria tenant e entra no dashboard.
AC-02: empresas A e B não partilham contactos, oportunidades, tarefas, anexos, exports ou notificações em qualquer endpoint.
AC-03: agente sem privilégio recebe 403/404 e a base de dados permanece inalterada.
AC-04: contactos e oportunidades seguem pipeline, com histórico e responsável, sem perda de dados.
AC-05: cliente envia pedido uma vez, recebe referência e consulta por prova de posse válida.
AC-06: operador atribui profissional aprovado e acompanha aceite -> execução -> conclusão.
AC-07: profissional não consegue visualizar serviço não atribuído nem dados de outra empresa.
AC-08: owner pode suspender/reactivar um tenant com auditoria.
AC-09: páginas funcionam em desktop/mobile, navegação teclado e mensagens em português claro.
AC-10: teste E2E das jornadas, backups e recuperação, métricas e staging validados antes do beta público.

## 13. KPIs do produto
Activação de tenants em 7 dias, empresas activas, contactos criados, oportunidades abertas/ganhas, taxa de conversão, pedidos recebidos, tempo até primeira resposta, tempo até atribuição, taxa de conclusão, reclamações, retenção, MRR (quando pago), custos por tenant. Dashboard mostra dados reais ou empty state — nunca números inventados.

## 14. Implementação e sequência de entregas
Sprint 0: protótipo navegável e decisões de ownership/visual. Fase 1: login, tenancy, RBAC, auditoria, design system. Fase 2: contactos, oportunidades, tarefas, dashboard. Fase 3: catálogo, pedidos, operador, profissionais. Fase 4: testes adversariais, staging, observabilidade, políticas e beta.
Definition of Done: rotas e contratos, validação, policies/tenant scoping, testes unitários e E2E críticos, UI responsiva, estados de erro/vazio, auditoria, migrações e instruções de operação.

## 15. Decisões propostas vs. a aprovar
**Propostas de trabalho:** marca Vitaleevo Ecosystem; CRM como módulo interno; operação central para pedidos públicos; profissionais verificados manualmente; beta limitado; AOA; Luanda como zona piloto a validar; atribuição manual. Estas são premissas e não aprovações comerciais.
**Aprovação obrigatória antes do go-live:** entidade contratante/facturadora, responsabilidade pelo serviço, política de reembolso/disputa, regiões/categorias operadas, texto legal e consentimento, modelo de preço, logótipo/identidade oficial, hosting/dados e integrações permitidas.

## 16. Fora de âmbito deliberadamente
ERP completo, marketplace com contratação autónoma, academia LMS, pagamentos automáticos, IA autónoma, campanhas sociais, motor próprio de workflow, microserviços Rust/NestJS. A sua inclusão requer PRD e aprovação separados.
