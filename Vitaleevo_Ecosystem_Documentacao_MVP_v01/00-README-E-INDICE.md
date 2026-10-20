# VITALEEVO ECOSYSTEM — Documentação oficial inicial
**Versão:** 0.1 | **Data:** 20/10/2026 | **Estado:** proposta de base para validação

**Estado do código:** scaffolding inicial criado — `apps/api` (Laravel) e `apps/web` (Next.js). As funcionalidades do MVP ainda não estão implementadas; ver `08-ROADMAP-BACKLOG-E-CRITERIOS.md`.

## Decisão fundadora
Produto novo, construído do zero. O HolyCRM é apenas referência funcional; não é dependência nem origem automática de código. Frontend de qualidade comercial desde o MVP. Backend principal Laravel; Next.js/TypeScript para interface; PostgreSQL e Redis. Rust (Axum) e NestJS só mediante necessidade medida e ADR aprovado.

## Documentos
1. `01-VISAO-NEGOCIO-E-MVP.md` — problema, utilizadores, monetização, escopo e critérios de aceitação.
2. `02-PRD-MVP.md` — requisitos funcionais e jornadas.
3. `03-ARQUITETURA-E-ADRS.md` — arquitectura modular e decisões.
4. `04-DADOS-MULTITENANCY-E-SEGURANCA.md` — entidades, isolamento e controlos.
5. `05-FRONTEND-DESIGN-SYSTEM.md` — experiência visual e interface.
6. `06-API-INTEGRACOES-E-EVENTOS.md` — contratos, canais e webhooks.
7. `07-DEVOPS-TESTES-E-LANCAMENTO.md` — ambientes, CI/CD e go-live.
8. `08-ROADMAP-BACKLOG-E-CRITERIOS.md` — fases, prioridades e definição de pronto.
9. `09-REGRAS-PARA-AGENTES-E-CODIGO.md` — instruções obrigatórias para implementação.
10. `10-DECISOES-PENDENTES-E-RISCOS.md` — escolhas a validar antes de construir.

## Princípios
- **Um produto utilizável primeiro:** validar procura e operação antes de ampliar módulos.
- **Monólito modular primeiro:** simplicidade operacional sem bloquear extracção futura.
- **Segurança multiempresa por desenho:** tenant em todos os fluxos de dados de negócio.
- **Menos código, mais clareza:** reutilizar bibliotecas maduras, não inventar frameworks internos.
- **Documentação é contrato:** não implementar funcionalidades fora do escopo aprovado.
- **Frontend não é acessório:** acessibilidade, responsividade, estados vazios, erros e performance são requisitos de aceitação.

## Fluxo de trabalho
Aprovar escopo → definir protótipos → implementar fundação → entregar vertical slice → testes → staging → piloto fechado → beta público → medição → evolução.
