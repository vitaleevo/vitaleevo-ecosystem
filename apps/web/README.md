# Vitaleevo Ecosystem — Web (Next.js)

Frontend do **Vitaleevo Ecosystem**: páginas públicas, área de empresa, portal de profissionais e painel de operação.

A especificação completa está em `../../Vitaleevo_Ecosystem_Documentacao_MVP_v01/`.

## Stack

- Next.js (App Router)
- React · TypeScript strict
- Tailwind CSS
- Testes: ESLint, typecheck e Playwright (E2E)

## Estrutura

```
apps/web/src/app/     # Rotas e páginas (App Router)
apps/web/public/      # Ficheiros estáticos
```

Os ecrãs P0 (landing, autenticação, CRM, pedidos, painel) ainda não estão implementados —
ver `05-FRONTEND-DESIGN-SYSTEM.md` e `08-ROADMAP-BACKLOG-E-CRITERIOS.md`.

## Setup local

```sh
npm install
npm run dev
```

Abra http://localhost:3000.

## Convenções

- Português (Angola) como idioma de interface (`lang="pt"`).
- Tokens de design definidos em `src/app/globals.css` (ver `05-FRONTEND-DESIGN-SYSTEM.md`).
- Server Components por defeito; Client Components apenas onde há interactividade.
