# PRD — Vitaleevo Ecosystem MVP
## Objectivo
Disponibilizar uma versão pública de testes que permita a uma empresa criar conta, gerir contactos e oportunidades, e receber/acompanhar pedidos de serviço; a operação Vitaleevo distribui pedidos a profissionais verificados.

## Papéis
- **Platform Owner:** governa tenants, planos, métricas, suporte e auditoria.
- **Tenant Owner/Admin:** gere empresa, membros, contactos, funis, pedidos e preferências.
- **Tenant Agent:** atende e actualiza leads/pedidos conforme permissões.
- **Professional:** vê apenas trabalhos que lhe foram atribuídos e actualiza execução.
- **Public Customer:** solicita serviço e consulta estado através de mecanismo seguro.

## Jornadas obrigatórias
1. **Registo empresarial:** criar conta → verificar email → criar empresa → seleccionar plano beta → entrar no dashboard.
2. **CRM:** criar contacto → registar oportunidade → mudar etapa → atribuir responsável → criar tarefa → concluir/perder.
3. **Pedido de serviço:** cliente preenche formulário → pedido entra na operação → triagem → atribuição a profissional → aceitação → execução → conclusão e feedback.
4. **Equipa:** admin convida utilizador → define papel → utilizador aceita → vê somente dados autorizados.
5. **Administração SaaS:** owner vê empresas, utilização, estados e auditoria; suspende acesso de forma reversível.

## Requisitos funcionais P0
- RF-001 autenticação segura e recuperação de senha.
- RF-002 criação e selecção de empresa; associação utilizador-empresa com papel.
- RF-003 isolamento tenant em leitura, escrita, pesquisa, exportação, anexos e jobs.
- RF-004 contactos e empresas-clientes, com deduplicação básica por tenant.
- RF-005 pipeline, etapas, oportunidades e histórico de alterações.
- RF-006 tarefas, responsáveis, prazos e dashboard comercial.
- RF-007 catálogo inicial de categorias de serviços administrado pela plataforma.
- RF-008 formulário público de pedido com consentimento, antispam e confirmação.
- RF-009 triagem, atribuição manual e estados de execução.
- RF-010 perfis profissionais e permissões restritas.
- RF-011 painel owner com tenants, planos beta, métricas e auditoria.
- RF-012 configurações básicas de empresa e notificações por email.
- RF-013 design responsivo, estados loading/empty/error, acessibilidade e feedback.

## P1 após estabilização
Caixa de entrada omnicanal com APIs oficiais; integração Meta; WhatsApp Embedded Signup; automações simples; relatórios; pagamentos locais; planos pagos automáticos.

## P2
IA assistida, integrações adicionais, marketplace self-service, academy, VITAERP e serviços Rust/NestJS baseados em necessidade.

## Critérios de aceitação do MVP
- Tenant A nunca acede a dados do tenant B, incluindo endpoints por ID, ficheiros, pesquisa e tarefas em fila.
- Um utilizador sem papel adequado recebe 403 sem alteração de dados.
- Um pedido público é registado uma única vez apesar de submissões repetidas.
- Um profissional não vê pedidos não atribuídos.
- O fluxo pedido → atribuição → conclusão funciona integralmente em staging.
- Não há erros críticos abertos, segredos expostos nem endpoints administrativos públicos.
- Funciona em telemóvel, tablet e desktop com navegação por teclado.

## Questões de produto
Definir se profissionais precisam de empresa própria, quem emite factura, quem recebe o pagamento, regras de disputa/cancelamento e se pedidos de serviço pertencem à Vitaleevo ou a cada tenant. Até decisão, o MVP utiliza atribuição central pela operação Vitaleevo.
