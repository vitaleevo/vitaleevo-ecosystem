# Vitaleevo Ecosystem — estado de go-live do MVP

Data de revisão técnica: **20/10/2026**.

Legenda: `[x]` concluído e validado localmente; `[~]` implementado mas depende de configuração/validação externa; `[ ]` ainda exige ação antes de produção pública.

## Produto P0

- [x] Registo, login, logout, recuperação de palavra-passe e verificação de email.
- [x] Aceitação registada de Termos e acesso à Política de Privacidade beta.
- [x] Multiempresa com membership, papéis e troca de contexto autorizada.
- [x] Testes adversariais de isolamento cross-tenant.
- [x] Contactos com pesquisa e deduplicação por tenant.
- [x] Pipeline, oportunidades, responsável, histórico, estados aberta/ganha/perdida e filtros.
- [x] Tarefas com responsável, prazo, filtros e conclusão.
- [x] Dashboard CRM básico.
- [x] Pedido público com consentimento, rate limit, referência e `Idempotency-Key`.
- [x] Consulta segura do pedido por referência + telefone.
- [x] Triagem central e encaminhamento para tenant.
- [x] Profissionais globais com estado de verificação; somente ativos/verificados podem receber serviço.
- [x] Fluxo do profissional: aceitar, recusar, iniciar e concluir.
- [x] Feedback do cliente após serviço concluído.
- [x] Notificação por email de convite e mudanças de estado, quando o mailer estiver configurado.
- [x] Owner: métricas, tenants, suspensão, planos, categorias, profissionais e auditoria.
- [x] Configurações básicas da empresa e preferência de notificações.
- [x] Planos Teste/Básico/Avançado e subscrição sincronizada ao tenant.
- [x] Interface responsiva desktop/mobile básica, estados vazios/erro e foco de formulários.

## Segurança

- [x] Laravel Sanctum stateful com CSRF para o cliente web.
- [x] Tenant resolvido pela sessão/membership; não confiado a `tenant_id` arbitrário do browser.
- [x] Login, recuperação, MFA e endpoints públicos com throttling.
- [x] Convites one-time com token armazenado apenas como hash e expiração.
- [x] MFA TOTP obrigatório antes do painel global do Platform Owner.
- [x] Recovery codes MFA one-time armazenados como hashes; segredo TOTP cifrado pelo `APP_KEY`.
- [x] Sessão MFA expira logicamente após 12 horas e exige nova validação.
- [x] Auditoria com actor, tenant, recurso, ação e metadata operacional.
- [x] `.env` e dumps locais excluídos do Git; CI rejeita envs privados e ficheiros de chave comuns.
- [~] Cookies `Secure` e HTTPS dependem da configuração real de produção/reverse proxy.
- [ ] Rever cabeçalhos de segurança/CSP no domínio final após escolher reverse proxy/hosting.
- [ ] Fazer revisão de segurança externa antes de abertura pública ampla.

## Qualidade e CI

- [x] PHP syntax check.
- [x] Laravel Pint sem divergências.
- [x] Suíte funcional automatizada com SQLite em memória isolado da base de desenvolvimento.
- [x] Integração CI preparada para migrar/seed PostgreSQL e verificar Redis antes dos testes isolados.
- [x] Frontend ESLint e build Next.js de produção.
- [x] Auditoria de dependências de produção do frontend sem vulnerabilidades altas conhecidas.
- [~] A cadeia de desenvolvimento do ESLint/Next ainda herda um advisory alto em `braces <=3.0.3` (GHSA-vfj7-8cjw-p6xm); em 20/10/2026 não existe versão corrigida publicada e o Next/eslint-config-next 16.4.2 é a versão mais recente. O pacote não integra o runtime de produção e deve ser atualizado assim que houver correção upstream.
- [x] GitHub Actions com backend, frontend, secret guard e build/smoke de containers.
- [x] Testes automatizados cobrem auth, tenancy, idempotência, convites, feedback, planos, owner, profissionais, MFA, health e isolamento do ambiente de testes.
- [~] Workflow CI está versionado, mas só poderá ser marcado como execução remota verde depois do primeiro push para o repositório GitHub configurado.

## Infraestrutura e recuperação

- [x] Docker local com PostgreSQL 16, Redis 7, Laravel, queue worker e Next.js.
- [x] Healthchecks de PostgreSQL, Redis, API e frontend.
- [x] `GET /api/v1/health` verifica base e cache sem expor detalhes de infraestrutura.
- [x] Smoke test automatizado para API pública e páginas principais.
- [x] Script de backup PostgreSQL custom-format.
- [x] Script de restore destrutivo com `-Force` explícito.
- [x] Restore drill executado em base temporária; dados de seed confirmados após restauração.
- [x] Baseline local de carga disponível em `scripts/load-smoke.mjs`.
- [ ] Configurar backups automáticos, cifrados e **off-host** em produção, com política de retenção.
- [ ] Configurar monitorização externa, alertas de indisponibilidade/erros e destino de notificações.
- [ ] Ensaiar rollback no ambiente de staging/prod escolhido, incluindo versão da app + migrações compatíveis.

## Legal e operação

- [x] Páginas `/privacidade` e `/termos` disponíveis e ligadas aos fluxos de registo/pedido.
- [x] Aceitação dos Termos registada por utilizador; utilizadores antigos são obrigados a aceitar antes de continuar.
- [~] Textos legais são deliberadamente versões beta operacionais.
- [ ] Revisão jurídica aplicável em Angola: entidade responsável, bases legais, retenção, subprocessadores, exercício de direitos, responsabilidades e condições comerciais.
- [ ] Definir canal oficial de suporte e contacto de privacidade.
- [ ] Definir processo de exportação/eliminação de dados e SLA operacional para esses pedidos.
- [ ] Formalizar contratação, faturação, pagamentos, reembolsos, garantias e responsabilidade dos serviços antes de monetização pública.

## Configuração externa obrigatória antes do domínio público

- [ ] Domínio final e DNS.
- [ ] TLS/HTTPS obrigatório e redirecionamento HTTP → HTTPS.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` exclusivo e segredos num secret manager do hosting.
- [ ] `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` e CORS limitados aos domínios reais.
- [ ] Fornecedor de email transacional com SPF, DKIM e remetente validado.
- [ ] PostgreSQL/Redis de produção com acesso privado e política de backup.
- [ ] Monitorização externa + logs centralizados com retenção e redacção apropriadas.
- [~] Limites do beta já são suportados por ambiente (`VITALEEVO_BETA_MODE`, máximo de tenants/profissionais); falta confirmar os valores operacionais e o canal de suporte.

## Estratégia recomendada de beta

O documento de produto prevê piloto controlado de **3–5 empresas e 5–10 profissionais verificados durante 2–4 semanas**. Estes números são metas de validação, não compromissos comerciais. Abrir inscrições amplas somente depois de métricas, suporte, incidentes, restauração e textos legais estarem aprovados.

## Comandos de validação local

```powershell
# Backend
$docker = "$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin\docker.exe"
& $docker compose exec -T api vendor/bin/pint --test
& $docker compose exec -T api php artisan test

# Frontend
cd apps/web
npm run lint
npm run build
cd ../..

# Stack
powershell -ExecutionPolicy Bypass -File .\scripts\smoke.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1
node .\scripts\load-smoke.mjs http://127.0.0.1:8000/api/v1/plans 100 10
```

Não tratar este ficheiro como aprovação legal ou autorização de lançamento. O objetivo é tornar explícita a diferença entre **software concluído/validado** e **dependências externas de produção**.
