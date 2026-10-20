# AGENTS.md — Vitaleevo Ecosystem · API (Laravel)

Regras para qualquer assistente ou programador que trabalhe nesta aplicação.

## Contexto e documentação oficial

A especificação do produto vive em `../../Vitaleevo_Ecosystem_Documentacao_MVP_v01/`:

- `02-PRD-MVP.md` — requisitos funcionais e jornadas
- `03-ARQUITETURA-E-ADRS.md` — módulos, dependências e decisões (ADRs)
- `04-DADOS-MULTITENANCY-E-SEGURANCA.md` — entidades, isolamento e controlos
- `06-API-INTEGRACOES-E-EVENTOS.md` — contratos, eventos e canais
- `07-DEVOPS-TESTES-E-LANCAMENTO.md` — ambientes, CI/CD e go-live
- `09-REGRAS-PARA-AGENTES-E-CODIGO.md` — regras de implementação

## Missão

Implementar exclusivamente o pedido aprovado para o Vitaleevo Ecosystem, de forma modular, segura, simples e com qualidade profissional.

## Regras inegociáveis

1. Antes de programar, ler PRD, arquitectura, contratos, esquema e ficheiros relevantes.
2. Não escrever código que não tenha sido solicitado; não introduzir features, dependências ou refactors não aprovados.
3. Pesquisar primeiro a documentação oficial e o código existente; usar comunidade apenas como complemento crítico. Verificar compatibilidade de versões e licenças.
4. Preferir funcionalidades nativas do Laravel e bibliotecas maduras a abstrações próprias.
5. Código curto **sem sacrificar legibilidade, testes, segurança ou tratamento de erros**.
6. Modularidade por domínio, com fronteiras explícitas; não criar microserviços sem ADR.
7. Todos os dados de negócio respeitam tenant e autorização no servidor; testar acesso cruzado.
8. Nunca guardar segredos no repositório, no browser ou nos logs.
9. Migrations reversíveis quando possível, com rollback previsto.
10. Não afirmar que testes passaram sem os executar. Não alterar produção sem aprovação explícita.
11. Não copiar código de projectos externos sem verificar licença e proveniência.
12. Documentar pressupostos e pedir decisão em ambiguidade de negócio que altere dados, segurança ou cobrança.

## Fluxo por tarefa

**Entender → localizar → propor mudança mínima → implementar → testar → rever → documentar.**

## Stack

- PHP 8.3+ e Laravel (monólito modular — ADR-001).
- PostgreSQL; Redis para cache, filas e rate limits (ADR-002).
- Testes: PHPUnit/Pest e Playwright (E2E).
- Rust/Axum e NestJS não entram no MVP (ADR-004).

## Setup local

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan serve
```

## Notas de segurança

- Segredos apenas no servidor/gestor de segredos; nunca em `NEXT_PUBLIC_*` no frontend.
- Resolver o tenant a partir da sessão autenticada/membership, nunca de um `tenant_id` enviado pelo browser.
