# Ambientes, CI/CD, testes e lançamento
## Ambientes obrigatórios
- **Local:** Docker Compose para PostgreSQL/Redis, API Laravel e Next.js em desenvolvimento. Para um arranque rápido sem Docker, a API pode correr com SQLite e cache/filas em base de dados; o PostgreSQL/Redis mantém-se como alvo de staging e produção.
- **Staging:** domínio e base de dados independentes; dados sintéticos; credenciais sandbox; acesso controlado; deploy automático a partir de branch de integração.
- **Produção:** segredos, base de dados, object storage, backups, observabilidade e domínio próprios; deploy após aprovação.

Nunca partilhar base de dados, buckets, chaves, sessões, webhooks ou tokens entre staging e produção. Nunca testar envios reais em massa a partir de staging.

## Git e entrega
`main` protegida; feature branches curtas; PR com revisão e checks obrigatórios; tags/releases; migrations versionadas; rollback de aplicação e plano de recuperação de dados. Não executar migrations destrutivas sem estratégia expand/contract.

## Pipeline CI
1. Composer validate/install; PHP lint, Pint e testes.
2. TypeScript typecheck, ESLint, testes unitários e build Next.js.
3. Testes de integração PostgreSQL/Redis e isolamento de tenant.
4. Scans de dependências e segredos; build de containers.
5. Playwright smoke E2E em staging.
6. Deploy produção manualmente aprovado e smoke pós-deploy.

## Testes mínimos
- Unitários: regras de negócio e estados.
- Integração: API, auth, permissões, queries, transacções, filas.
- E2E: registo → onboarding → contacto → oportunidade; pedido → atribuição → conclusão.
- Segurança: cross-tenant, IDOR, CSRF, rate limits, upload, assinatura webhook, replay.
- Carga: medir p95 e throughput de fluxos principais antes do beta público; limites derivados de orçamento e metas, não de números inventados.
- Recuperação: simulação de falha, restauração de backup e teste de rollback.

## Checklist go-live
[ ] Domínio e HTTPS; [ ] MFA owner; [ ] email transaccional; [ ] backups com restauração; [ ] política de privacidade/termos; [ ] alertas; [ ] logs sem segredos; [ ] monitorização; [ ] suporte e canal de feedback; [ ] limites beta; [ ] CI verde; [ ] testes cross-tenant verdes; [ ] rollback ensaiado.

## Estratégia beta
Piloto com 3–5 empresas e 5–10 profissionais verificados; acompanhar utilização e suporte durante 2–4 semanas; só depois abrir inscrições controladas. Estes números são metas de teste, não compromissos comerciais.
