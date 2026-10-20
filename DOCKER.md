# Vitaleevo — ambiente Docker

Stack completa de desenvolvimento: **Next.js + Laravel + PostgreSQL + Redis**, com hot reload.
Não é preciso reconstruir imagens quando alteras código.

## Arrancar

```sh
docker compose up -d
```

Na primeira vez (ou se mudares o `Dockerfile`/dependências de sistema):

```sh
docker compose up -d --build
```

## Endereços

| Serviço | Endereço |
|---|---|
| Frontend (Next.js) | http://localhost:3001 |
| API (Laravel) | http://localhost:8000 |
| PostgreSQL | localhost:5433 |
| Redis | localhost:6380 |

O HolyCRM continua na porta **3000** e não é afetado.

## Hot reload

- **Frontend:** altera ficheiros em `apps/web/` e o browser atualiza sozinho. O Turbopack usa
  polling (`watchOptions.pollIntervalMs` em `apps/web/next.config.ts`) porque os eventos de
  ficheiros do Windows não chegam ao container.
- **API:** altera ficheiros em `apps/api/`; o PHP é interpretado a cada pedido, por isso a
  alteração aparece imediatamente.

Não é necessário `docker compose build` nem reiniciar containers para alterações de código.

## Parar / ver estado

```sh
docker compose down      # parar (mantém os dados)
docker compose ps        # estado
docker compose logs -f   # logs de todos
docker compose logs -f web
docker compose logs -f api
```

## Comandos úteis

```sh
# Laravel
docker compose exec api php artisan migrate
docker compose exec api php artisan tinker

# Composer
docker compose exec api composer install

# npm
docker compose exec web npm install

# Base de dados
docker compose exec db psql -U vitaleevo -d vitaleevo

# Redis
docker compose exec redis redis-cli
```

## Quando é preciso reconstruir

Só quando mudam dependências de sistema (Dockerfile, extensões PHP, versão do Node) ou quando
se quer limpar tudo:

```sh
docker compose up -d --build
```

Se mudarem apenas `composer.json` / `package.json`, basta instalar dentro do container:

```sh
docker compose exec api composer install
docker compose exec web npm install
```

## Dados

PostgreSQL e Redis usam volumes Docker (`pgdata`, `redisdata`), por isso os dados sobrevivem a
`docker compose down`. Para apagar tudo (incluindo dados):

```sh
docker compose down -v
```

## Estrutura

- `docker-compose.yml` — serviços, portas, variáveis e comandos
- `apps/api/Dockerfile` — PHP 8.3 + `pdo_pgsql` + extensão Redis
- `apps/web/Dockerfile` — Node 24
- `apps/api/.dockerignore` e `apps/web/.dockerignore`

## Saúde e smoke test

A API expõe readiness em `GET /api/v1/health`, verificando PostgreSQL e cache Redis. API, banco,
Redis e frontend possuem healthchecks no Compose.

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\smoke.ps1
node .\scripts\load-smoke.mjs http://127.0.0.1:8000/api/v1/plans 100 10
```

O endpoint `/health` tem rate limit e não deve ser usado como alvo de carga intensiva. O servidor
Laravel roda com `artisan serve --no-reload` no Compose porque, no Laravel 13, o processo filho do
servidor de desenvolvimento pode filtrar variáveis Docker ao recarregar a partir do `.env` local.

## Testes

```powershell
docker compose exec -T api php artisan test
docker compose exec -T api vendor/bin/pint --test
cd apps/web
npm run lint
npm run build
```

A suíte Laravel força SQLite em memória, cache/sessão `array` e fila síncrona antes do bootstrap.
Isto impede `RefreshDatabase` de tocar no PostgreSQL de desenvolvimento e impede rate limits de
testes de persistirem no Redis local.

## Backup e restauração local

Criar um dump PostgreSQL em formato custom:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1
```

Restaurar de forma destrutiva apenas quando for intencional:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\restore.ps1 -BackupPath .\backups\vitaleevo-AAAAMMDD-HHMMSS.dump -Force
```

Estes scripts são para o ambiente Docker/local e para drills. Em produção, os backups precisam de
retenção independente, encriptação e cópia fora do mesmo host.
