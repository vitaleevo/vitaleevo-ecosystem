# VITALEEVO ECOSYSTEM — Plano de implementação multi-app
Versão 1.0 · 20-10-2026 · Fonte: PRD multi-app v2.0
Documento de execução; não é declaração de que os seis módulos estejam concluídos.

## Princípios vinculativos
- Laravel API e Next.js como única plataforma modular; PostgreSQL e Redis.
- Uma identidade por pessoa; perfis pessoais e memberships de várias empresas.
- Vitrines pertencem explicitamente a pessoa ou empresa; validação no servidor.
- Dados empresariais isolados por tenant. Perfil público só mostra dados autorizados.
- Módulos desbloqueados por licenças; suspender acesso não apaga históricos.
- Serviços, vendas e avaliações dependem de estados verificáveis.
- Pagamentos, comissões e facturação só depois de conformidade fiscal, política comercial e APIs aprovadas.

## Situação inicial confirmada no código
Autenticação e MFA owner, tenants, CRM, contactos, tarefas, pipeline, pedidos, atribuições, profissionais, equipa, planos e administração já tinham implementação.
O Next.js inicial tinha workspace numa única página e portal de pedido, termos e privacidade.
A nova fase introduz a rota /ecossistema, registo pessoal sem empresa, módulo de vitrines, catálogo de apps e tabelas de módulos.

## Fase 0 — Fundação transversal (P0)
- [x] Catálogo dos seis módulos na API, com estados de maturidade explícitos.
- [x] Modelo de vitrines: pessoal/empresa, serviço/comércio, estados draft/published.
- [x] Listagens publicadas e filtráveis sem expor contactos privados.
- [x] Endpoints para titular criar/editar vitrine e incluir itens, com validação do vínculo empresarial.
- [x] Interface pública /ecossistema com pesquisa e gestão de vitrines.
- [x] Permitir registo de utilizador sem empresa e criar empresa depois na mesma identidade.
- [x] Migração executada na base de dados; testes de propriedade de vitrines e isolamento empresarial aprovados.
- [ ] Modelar entitlements por plano, regras de pagamento, auditoria de publicação e aprovação de anúncios.
**Gate**: registar pessoa -> criar vitrine -> inserir item -> publicar -> pesquisar -> bloquear edição por terceiros; login empresarial continua funcional.

## Fase 1 — Vitaleevo Jobs (P0/P1)
Profissionais e empresas: candidatura, verificação documental manual, portefólio, competências, horários, geo-pesquisa, favoritos, cotação e pedido dirigido à vitrine. Triagem central opcional; estado e responsabilidade contratual definidos por origem. Proposta, aceite, execução, entrega, disputa e avaliação só depois de conclusão.
**Gate**: pedido real de teste ponta a ponta com autorizações e auditoria; nenhuma contratação/pagamento falso.

## Fase 2 — Vitaleevo Market (P1)
Fornecedor com inventário/disponibilidade, tabela de preços, política de entrega e revendedores autorizados. Revendedor expõe apenas itens aprovados; manter snapshot de preço e comissão ao criar encomenda. Cliente encomenda; fornecedor confirma, entrega e fecha. Livro de comissões versionado e reconciliação; cancelamentos e devoluções.
**Gate**: simular encomenda com dois revendedores e provar atribuição correcta, sem pagar comissão por evento não confirmado.

## Fase 3 — Vitaleevo Business e HolyCRM (P1)
Business: onboarding de empreendedor, diagnóstico, pacotes, fornecedores e tarefas de implementação. HolyCRM integra origem de leads consentidos, funil, pipeline e oportunidades; campanhas e canais externos apenas após permissões oficiais.
**Gate**: pedido Business gera oportunidade autorizada e acompanha conversão, sem vazar dados entre empresas.

## Fase 4 — Vitaleevo Academy (P2)
Cursos, aulas, progresso, testes, certificados verificáveis, perfis de competências, opt-in para oportunidades Jobs. Sem promessa automática de emprego. Rever contratos e conteúdo formativo.
**Gate**: matrícula, avaliação e certificado ligados à identidade verificada, com autorização de partilha.

## Fase 5 — VITAERP (P2)
Activação modular, clientes, artigos, stock, vendas, compras, relatórios e facturação mediante certificação e requisitos de Angola validados. Integração com encomendas Market através de eventos idempotentes.
**Gate**: não emitir documentos fiscais reais sem revisão legal e fiscal.

## Fase 6 — Serviços partilhados avançados
Pagamentos em AOA por PSP elegível, saldo e liquidação separados, regras e reversões de comissão, disputas, fraude, reputação de compra verificada, análises, notificação, integrações WhatsApp/Meta autorizadas, IA assistiva com permissões.
**Gate**: teste de reconciliação, restituição, segurança, privacidade e monitorização financeira.

## Definition of Done para cada módulo
1. PRD e casos de uso aprovados, incluindo responsabilidades e fronteiras do tenant.
2. Migração reversível e índices, contratos OpenAPI e validação de dados.
3. Autorização por pessoa/empresa, testes cross-tenant, idempotência e auditoria.
4. UI responsiva com loading, vazio, erro, acessibilidade e dados reais.
5. Testes unitários, de integração e E2E; staging, logs, backup e demonstração.
6. Nenhuma venda/comissão/pagamento tratado como confirmado sem prova do evento.

## Decisões comerciais que requerem aprovação
Regiões/categorias de lançamento, taxas por canal, critérios de validação profissional,
quem factura, política de recebimento e reembolso, prazos de liquidação,
responsabilidade dos fornecedores/revendedores, privacidade e termos locais.

## Projecto e execução local
Backend: apps/api (Laravel); Frontend: apps/web (Next.js).
Nova rota UI: /ecossistema. API: /api/v1/ecosystem/modules e /api/v1/ecosystem/storefronts.
Migração: apps/api/database/migrations/2026_10_21_000100_create_ecosystem_storefronts.php
Verificações: php artisan migrate --force; php artisan route:list --path=api/v1/ecosystem;
no frontend npm run build. Executar smoke tests após base de dados disponível.
