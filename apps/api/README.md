# Vitaleevo Ecosystem — API (Laravel)

API do **Vitaleevo Ecosystem**: CRM multiempresa, pedidos de serviço e operação de profissionais.

A especificação completa está em `../../Vitaleevo_Ecosystem_Documentacao_MVP_v01/`.

## Stack

- PHP 8.3+
- Laravel (monólito modular — ADR-001)
- PostgreSQL · Redis (cache, filas, rate limits)
- PHPUnit/Pest · Playwright

## Estrutura

```
apps/api/
├── app/            # Código da aplicação (módulos por domínio)
├── bootstrap/      # Configuração da aplicação
├── config/         # Configuração (app, base de dados, sessões, filas, etc.)
├── database/       # Migrations, factories e seeders
├── routes/         # Rotas (web, console)
├── storage/        # Logs, cache, sessões e ficheiros
└── tests/          # Testes unitários e de funcionalidade
```

Os módulos de negócio previstos (Identity, Tenancy, CRM, ServiceRequests, Professionals, etc.)
ainda não estão implementados — ver `08-ROADMAP-BACKLOG-E-CRITERIOS.md`.

## Setup local

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan serve
```

Para um arranque rápido sem Docker, o `.env` pode usar SQLite; o PostgreSQL/Redis é o alvo de
staging e produção.

## Convenções

- Base `/api/v1`, JSON, datas ISO 8601 UTC (ver `06-API-INTEGRACOES-E-EVENTOS.md`).
- `tenant_id` obrigatório em todas as entidades de negócio (ver `04-DADOS-MULTITENANCY-E-SEGURANCA.md`).
