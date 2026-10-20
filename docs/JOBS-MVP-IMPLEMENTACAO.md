# VITALEEVO JOBS — PRD de execução e estado da implementação
Data: 20/10/2026 | Fase Jobs MVP — contratação assistida, não pagamento automático

## Objectivo
Permitir que clientes encontrem vitrines de serviços, solicitem directamente um serviço listado ou orçamento geral, acompanhem o pedido com referência + telefone, e que profissionais verificados ou empresas recebam trabalhos após triagem central. A operação Vitaleevo valida o fim do trabalho antes de permitir a avaliação.

## Actores e autorizações
Cliente: pesquisa sem login, pede serviço sem login com consentimento e acompanha por referência e telefone de origem.
Profissional: uma conta Vitaleevo, candidatura com especialidade e telefone; alteração de dados requer nova verificação. Após verificação e atribuição, acessa apenas os seus trabalhos.
Empresa prestadora: vitrine associada a tenant activo. Owner/admin vê resumos de pedidos à sua vitrine; após encaminhamento para o tenant, o CRM da própria empresa permite atribuir profissionais.
Operador Owner: MFA, valida candidatura profissional no painel anterior, encaminha pedidos e confirma a entrega.
Outros tenants e utilizadores não recebem dados privados; resumo da vitrine não inclui contacto do cliente antes do encaminhamento.

## Jornada principal (actualmente implementada)
1. Utilizador publica uma vitrine Jobs e itens de serviço através de formulários modais.
2. Cliente pesquisa /jobs, abre detalhes e escolhe «Solicitar» ou «Solicitar orçamento» (modal).
3. API valida storefront publicado, serviço listado, categoria activa, dados mínimos e consentimento. Idempotency-Key impede pedido duplicado.
4. Pedido entra em service_requests, com referência JB-*, tenant_id null. jobs_request_targets vincula a vitrine e o item.
5. Owner com MFA visualiza fila e encaminha: pessoal -> profissional activo/verificado, empresa -> tenant activo.
6. Profissional utiliza /jobs «Trabalhos atribuídos»: assigned -> accepted -> in_progress -> completed (entrega). Para Jobs, a entrega muda o pedido para delivered.
7. Owner confirma a entrega: delivered -> completed. Cliente pode acompanhar e enviar avaliação pelo mecanismo existente.
8. Em recusas, pedido regressa à triagem e pode ser reencaminhado conforme política operacional. Cancelamento/encerramento impede actualizações subsequentes do profissional.

## Modais de interface
Componente acessível src/components/Modal.tsx. Usar em novas acções de formulário:
- Criar vitrine, adicionar serviço/produto (ecossistema).
- Solicitar orçamento ou serviço, acompanhar pedido, registar/actualizar candidatura profissional (Jobs).
- Clique no botão abre modal; Escape, botão Fechar e clique fora fecham, excepto quando é preferível evitar perda de edição. O conteúdo tem foco inicial, focus trap e restauração do foco anterior. Formulários apresentam erros dentro do modal.
Pesquisa rápida continua inline pois não é um formulário criado por um botão de acção.

## Backend implementado
Migração jobs_request_targets; JobsController; 7 endpoints novos:
POST /api/v1/jobs/requests (público)
GET /api/v1/jobs/professional/me (autenticado)
POST /api/v1/jobs/professional/apply (autenticado)
GET /api/v1/jobs/my-requests (autenticado)
GET /api/v1/platform/jobs/requests (owner+MFA)
POST /api/v1/platform/jobs/requests/{id}/route (owner+MFA)
POST /api/v1/platform/jobs/requests/{id}/complete (owner+MFA)
Profissional usa endpoints já existentes GET /api/v1/professional/jobs e PATCH /api/v1/professional/jobs/{id}.
Acompanhamento usa POST /api/v1/public/service-requests/status e feedback usa POST /api/v1/public/service-requests/feedback.

## Protecções
Nunca mostrar números/contactos de clientes nas vitrines públicas.
Impedir vitrine não publicada ou item pertencente a outra vitrine.
Owner não distribui trabalhos a profissionais por validar.
Máquina de estados impede saltos e dupla conclusão.
Pedidos Jobs de empresas não podem ser concluídos directamente pelo CRM; requerem validação da operação.
Não existem cobranças, retenções ou comissões reais neste MVP.
Os relatórios e estados são reais; nenhuma avaliação fictícia será apresentada.

## Limitações e backlog P1
Formalização de propostas/preços, aceite de orçamento pelo cliente, contratos, reagendamento, anexos e provas de execução, chat e notificações para o profissional, gestão de recusas com alternativa, disputa/reembolso, pesquisa geoespacial, reputação agregada, pagamentos e comissões. Estes fluxos exigem regras comerciais próprias.
Operação hoje exige acção manual e profissionais verificados.
Dados fiscais/contratuais e províncias de operação requerem aprovação antes de divulgação comercial.

## Execução e QA
PHP: php artisan migrate --force; php artisan db:seed --class=JobsCategorySeeder --force; php artisan test
Next: npm run lint; npm run build; npm run start -- --hostname 127.0.0.1 --port 3002
URL local: http://127.0.0.1:3002/jobs
API local: http://127.0.0.1:8000/api/v1/jobs/requests
A porta 3000 pode estar ocupada por outra aplicação; não interromper processos alheios.
Os testes utilizam SQLite de memória, sem alterar dados de desenvolvimento.
