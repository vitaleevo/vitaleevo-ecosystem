# VITALEEVO ECOSYSTEM — PRD MULTI-APP v2.0
Data: 20/10/2026 | Estado: proposta de especificação; validar regras comerciais e legais antes de cobrar

## 1. Visão
Plataforma modular de negócios com seis apps: Vitaleevo Business, Vitaleevo Jobs, Vitaleevo Academy, Vitaleevo Market, VITAERP e HolyCRM. Identidade, tecnologia, operações e dados integrados. Uma identidade única por pessoa; papéis, organizações e vitrines múltiplos; autorização por contexto e módulo.

## 2. Personas e contas
Pessoa/cliente: compra produtos, solicita serviços, avalia transacções verificadas e acompanha encomendas.
Profissional liberal: conta individual, verificação, competências, localização, disponibilidade, vitrine pública, portefólio, propostas, contratos e histórico.
Empresa prestadora: conta organizacional, equipa e permissões, vitrine institucional, catálogo de serviços, propostas e carteira de clientes.
Empresa fornecedora: catálogo de produtos, stock ou disponibilidade, preços, políticas de entrega, vendedores/revendedores autorizados, encomendas e comissões.
Revendedor/vendedor: conta individual ou equipa, vitrine comercial, produtos autorizados de parceiros, links/códigos de atribuição, pedidos e comissões.
Aluno: cursos, progresso, avaliações, certificados verificáveis e oportunidades profissionais.
Operador Vitaleevo: moderação, suporte, resolução de disputas, validação, comissões e auditoria.
Admin de plataforma: módulos, tenants, licenças, catálogo global, políticas e relatórios.
A mesma pessoa pode assumir vários papéis. Contas empresariais têm membros, RBAC e tenant_id; a identidade pessoal não se duplica. Troca de empresa exige membership válida.

## 3. Aplicações e regras
### Vitaleevo Jobs — descoberta e contratação
Pesquisa pública por categoria, especialidade, município/província, disponibilidade e reputação; filtros e resultados paginados. Vitrine profissional/empresarial com descrição, serviços, preços indicativos ou 'sob orçamento', fotos, credenciais verificadas, avaliações reais e CTA. Cliente solicita serviço ao perfil ou publica pedido geral. Modalidades: contratação directa assistida e pedido distribuído pela Vitaleevo. Registar origem e intermediário; nenhuma comissão presumida sem regra comercial e contrato. Estados: submitted, triage, quoted, accepted, scheduled, in_progress, delivered, completed, cancelled, disputed. Avaliação apenas após serviço verificado. Dados de contacto sensíveis protegidos contra scraping e exposição indevida.

### Vitaleevo Market — comércio e revenda
Parceiro cadastra produtos e disponibilidade; define política de revenda e comissões. Revendedor solicita parceria, parceiro aprova, selecciona produtos autorizados e cria vitrine/colecção própria sem comprar stock. Cliente encomenda; sistema reserva disponibilidade com o fornecedor, regista vendedor de origem, acompanha confirmação, pagamento e entrega. Não confirmar venda apenas por clique. Comissão/margem calculada por regra versionada e libertada após evento de venda elegível, respeitando devoluções e fraude. O fornecedor mantém responsabilidade pelo stock e cumprimento acordado. Política de quem factura, recebe e entrega é obrigatória antes do checkout real.

### Vitaleevo Business — criação e crescimento
Diagnóstico inicial do empreendedor, escolha de modelo de negócio, proposta de serviços de implementação, acesso a fornecedores aprovados, catálogo digital, presença online, marketing e acompanhamento. Empresa pode activar HolyCRM, VITAERP, Market e Jobs por licenças. Serviços de consultoria são oportunidades e ordens de trabalho rastreáveis, não activações automáticas.

### Vitaleevo Academy — aprender e trabalhar
Catálogo de cursos (design, marketing, programação, Excel, informática, vendas, IA), inscrição, pagamentos quando aprovados, aulas/conteúdo, progresso, testes, emissão de certificado verificável. Perfil de competências do aluno pode ser partilhado com Jobs apenas mediante consentimento. Conclusão de curso não garante emprego; oportunidades seguem candidatura e selecção. Receita de formação e eventual colocação dependem de contratos e legislação aplicável.

### HolyCRM — vendas e automação
Contactos, empresas-clientes, pipeline, oportunidades, tarefas, actividades, campanhas e automações progressivas. Isolamento por tenant. Leads de Jobs/Market/Business só entram no CRM da empresa com base em titularidade, autorização e consentimento; nunca copiar contactos entre tenants indiscriminadamente. Integrações WhatsApp/Meta apenas com APIs oficiais e aprovações.

### VITAERP — gestão empresarial
Gestão modular: produtos, compras, inventário, vendas, facturação, clientes, fornecedores e finanças por empresa. Facturação fiscal angolana exige requisitos legais e certificações aplicáveis antes de produção; não declarar conformidade sem validação. Eventos de vendas confirmadas do Market podem criar documentos/lançamentos no ERP por integração explícita, com idempotência.

## 4. Infraestrutura partilhada
Identity (login, verificação, MFA, recuperação), Organizations/Tenancy, Membership/RBAC, ModuleCatalog/Licensing, Storefronts, Catalog, Search, Orders/ServiceRequests, Opportunities, Payments/Ledger, Commissions, Reputation, Notifications, Files, Reporting, Audit, Consent/Privacy. Core não depende dos módulos de negócio; módulos consomem contratos/eventos. Nunca permitir módulo desactivado apagar dados históricos; bloquear novas operações e preservar acesso/exportação conforme política.

## 5. Jornadas transversais
J1: Pessoa regista conta -> verifica identidade mínima -> escolhe cliente/profissional/revendedor/aluno -> completa perfil -> pede activação de módulos.
J2: Profissional cria vitrine -> submete credenciais -> moderação -> publica -> recebe pedido -> orçamento -> execução -> conclusão -> avaliação -> comissão elegível.
J3: Empresa cria tenant -> convida equipa -> publica serviços e vitrine -> recebe pedidos -> acompanha no CRM -> conclui.
J4: Fornecedor publica produto -> aprova revendedor -> revendedor expõe produto na vitrine -> cliente compra -> fornecedor confirma e entrega -> pagamento confirmado -> comissão elegível -> liquidação.
J5: Aluno inscreve-se -> aprende -> avaliação -> certificado -> opt-in para perfil profissional -> candidata-se a trabalho.
J6: Empreendedor recebe diagnóstico Business -> escolhe pacote -> implementação -> activa módulos -> acompanha resultados.

## 6. Modelo de dados mínimo
users, personal_profiles, organizations, memberships, roles, permissions, module_definitions, module_entitlements, plans, subscriptions, storefronts, storefront_media, service_offerings, professional_profiles, professional_verifications, service_requests, service_quotes, service_assignments, service_events, product_catalog, supplier_offers, reseller_partnerships, reseller_listings, carts, orders, order_items, fulfilments, payment_transactions, commission_rules, commission_entries, settlements, reviews, disputes, courses, lessons, enrollments, assessments, certificates, crm_contacts, crm_opportunities, tasks, audit_logs, idempotency_keys, outbox_events. Distinguir objectos globais de objectos tenant-scoped; FKs e policies impedem cruzamento de dados.

## 7. Regras críticas de integridade
- Um utilizador pode ter vários papéis; permissões avaliadas por módulo, organização e recurso.
- Vitrine pode pertencer a pessoa ou organização, nunca ambas sem ownership explícito.
- Avaliação exige transacção/serviço concluído verificável; moderação e recurso contra abuso.
- Comissão exige atribuição inequívoca, evento elegível, cálculo versionado e reversão em reembolso/chargeback.
- Não prometer stock de terceiros; confirmar disponibilidade no momento da encomenda.
- Pagamento, saldo disponível e saldo a liquidar são estados diferentes; razão financeira auditável.
- Alterações de preço/comissão não retroagem a encomendas aceites.
- Dados pessoais, documentos e pagamentos são privados por defeito; minimização e consentimento.
- Admin global não pode aceder a dados pessoais sem justificação, autorização e auditoria.

## 8. UX e design
Landing Ecosystem com selector de apps e pesquisa global; login único; dashboard 'Meu espaço' com cartões dos módulos activos; alternância pessoa/empresa; vitrine pública profissional e comercial; painéis próprios por persona; app shell e design system comuns, rotas e menus conforme permissões. Mobile-first nas vitrines e pedidos; desktop responsivo no CRM/ERP. Estados vazios, carregamento, erro, sucesso e acessibilidade WCAG 2.2 AA como meta.

## 9. Arquitectura técnica
Laravel 13 modular monolith como API e fonte de verdade; Next.js 16 + TypeScript frontend; PostgreSQL, Redis, object storage. Contratos OpenAPI, jobs com tenant explícito, eventos idempotentes, auditoria, feature flags. Evitar criar seis backends e seis logins independentes. Microserviços apenas mediante ADR. Testes cross-tenant, autorização, pedidos duplicados, comissões, estados de entrega, cancelamentos e reembolsos.

## 10. Fases propostas
F0: design system, identidade, tenants, RBAC, catálogo de módulos, onboarding e storefronts.
F1: Jobs MVP (vitrine, busca, pedido, triagem, atribuição, execução, reputação verificada).
F2: Market MVP (fornecedor, parceria, vitrine revendedor, pedido, atribuição de venda, comissão registada sem liquidação automática).
F3: Business (diagnóstico, pacotes, gestão de implementação), HolyCRM básico integrado.
F4: Academy (cursos, matrícula, progresso, certificado) e VITAERP inicial com requisitos fiscais validados.
F5: pagamentos e liquidação automáticos, integrações Meta/WhatsApp, automações e relatórios avançados após aprovações.
Sequência é proposta; prioridades e orçamento dependem de validação.

## 11. Critérios de aceitação
Uma conta única navega entre papéis e empresas autorizadas; tenants A/B não vêem dados uns dos outros; profissional publica vitrine após validação; cliente encontra e solicita serviço; operador atribui e conclui; fornecedor aprova revendedor; revendedor publica catálogo autorizado; venda guarda atribuição e comissão calculada sem duplicação; aluno obtém certificado verificável; cada módulo pode ser activado/desactivado sem apagar dados; logs e testes críticos aprovados.

## 12. Decisões pendentes para aprovação
Categorias e províncias iniciais; contratação directa versus centralizada e taxa por origem; modelo jurídico de intermediação; preços/limites SaaS; política de comissões, pagamentos, devoluções e disputas; quem factura e entrega; validação de profissionais e fornecedores; elegibilidade de certificados; marca e domínios; requisitos fiscais e privacidade em Angola. Nenhuma decisão pendente deve ser tratada como aprovada em produção.
