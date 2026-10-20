# Frontend — direcção visual e design system
## Objectivo
Produto SaaS profissional, rápido e coerente, com navegação clara para utilizadores empresariais angolanos, em português, com boa experiência em redes móveis.

## Princípios visuais
- Layout limpo, alta legibilidade, hierarquia clara e densidade equilibrada.
- Sistema de tokens: cores semânticas, espaçamentos 4/8 px, raio, sombras, tipografia, estados.
- Uma família tipográfica moderna com fallback; evitar excesso de fontes, gradientes e ícones decorativos.
- Tema claro prioritário; escuro apenas se não atrasar MVP.
- Componentes acessíveis baseados em biblioteca madura (ex.: shadcn/ui + Radix), sem reescrever primitivas.
- Gráficos só para decisões reais; números sem fonte não devem aparecer como dados verdadeiros.

## Estrutura de navegação
**Público:** Início, Serviços, Como funciona, Para empresas, Entrar, Pedir serviço.
**Empresa:** Visão geral, Contactos, Oportunidades, Tarefas, Pedidos, Equipa, Definições.
**Owner:** Empresas, Pedidos, Profissionais, Planos, Auditoria, Saúde da plataforma.
**Profissional:** Meus trabalhos, Disponibilidade, Perfil.

## Ecrãs P0
Landing; login/registo; onboarding de empresa; dashboard; contactos (lista/detalhe/formulário); pipeline Kanban/lista; tarefas; pedido público; painel de pedidos; atribuição; portal profissional; equipa; definições; owner.

## Componentes partilhados
AppShell, Sidebar, Topbar, Breadcrumb, PageHeader, DataTable, EmptyState, LoadingSkeleton, ErrorState, ConfirmDialog, FormField, SearchFilter, StatusBadge, ActivityTimeline, KPIStat, Toast, PermissionGate.

## Critérios de qualidade
- Mobile-first para pedido público; desktop-first responsivo para CRM.
- WCAG 2.2 AA como meta; contraste, foco visível, teclado, rótulos e mensagens de erro.
- Performance: metas iniciais LCP ≤2,5s, INP ≤200ms e CLS ≤0,1 no percentil 75, a medir em condições reais.
- Não carregar tabelas completas: paginação e filtros no servidor.
- Todos os ecrãs com loading, vazio, erro, sucesso e permissões insuficientes.
- Formulários com validação de frontend e backend; mensagens em português natural.
- Criar protótipo navegável e testar 5 utilizadores antes de consolidar padrões.

## Tokens iniciais — proposta, não marca final
`primary #155EEF`, `surface #FFFFFF`, `background #F8FAFC`, `text #101828`, `muted #667085`, `success #12B76A`, `warning #F79009`, `danger #F04438`; validar contraste e identidade Vitaleevo.

## Regras de implementação
Next.js Server Components por defeito; Client Components apenas onde há interactividade. Não criar uma segunda camada de estado global para dados já geridos pelo servidor. Não duplicar validação sem razão. Evitar animações pesadas. Usar TypeScript strict e tipos derivados de contratos quando disponíveis.
