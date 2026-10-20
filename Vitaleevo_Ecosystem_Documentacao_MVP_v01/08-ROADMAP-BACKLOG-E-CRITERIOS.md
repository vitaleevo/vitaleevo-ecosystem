# Roadmap e backlog
## Sequência de execução
### Sprint 0 — definição e protótipo
Aprovar fronteiras do negócio, dados, papéis, mapa de navegação, protótipo das páginas-chave, identidade visual e critérios de aceitação. Entregável: protótipo validado e backlog estimado.

### Fase 1 — fundação técnica
Repositório, Docker, CI, autenticação, tenants, memberships, RBAC, auditoria, layout frontend, staging e seed de dados fictícios.
**Gate:** testes cross-tenant verdes, login e onboarding E2E.

### Fase 2 — CRM utilizável
Contactos, pipeline, oportunidades, tarefas, dashboard, filtros e pesquisa.
**Gate:** empresa completa ciclo de lead a oportunidade concluída.

### Fase 3 — pedidos e profissionais
Catálogo, formulário público, triagem, profissionais, atribuição manual, estados, notificações e feedback.
**Gate:** pedido real de teste é concluído ponta a ponta sem intervenção técnica.

### Fase 4 — beta público controlado
Hardening, políticas legais, monitorização, backups, testes de carga, suporte, métricas e lançamento controlado.
**Gate:** checklist go-live aprovada e métricas monitorizadas.

### Pós-MVP
Inbox Meta/WhatsApp, automações, pagamentos, IA assistida, marketplace aberto, Academy, ERP e extração de serviços se necessária.

## Priorização
P0 = bloqueia o fluxo principal ou a segurança; P1 = melhora retenção/operação após validação; P2 = expansão. Nunca incluir P1/P2 numa sprint P0 por conveniência.

## Definition of Done
Requisito aceite, testes relevantes, validação de permissões e tenancy, estados de UI, acessibilidade básica, documentação API, logs adequados, revisão de código, deploy staging e demonstração funcional.

## Indicadores de gestão
Tempo de ciclo, bugs críticos, cobertura dos fluxos críticos, p95 API, tempo de onboarding, activação, pedidos concluídos, conversão, MRR, custo mensal de infraestrutura.

## Estimativa
Datas e custo só após estimar backlog e conhecer equipa, disponibilidade e infraestrutura. Não confundir sequência com prazo garantido.
