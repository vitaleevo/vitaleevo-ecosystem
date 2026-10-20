# AGENTS.md — Regras para qualquer assistente ou programador
## Missão
Implementar exclusivamente o pedido aprovado para Vitaleevo Ecosystem, de forma modular, segura, simples e com frontend de qualidade profissional.

## Regras inegociáveis
1. Antes de programar, ler PRD, arquitectura, contratos, esquema e ficheiros relevantes.
2. Não escrever código que não tenha sido solicitado; não introduzir features, dependências ou refactors não aprovados.
3. Pesquisar primeiro documentação oficial e código existente; usar Stack Overflow/comunidade apenas como complemento crítico. Verificar compatibilidade de versões e licenças.
4. Preferir funcionalidades nativas Laravel/Next.js e bibliotecas maduras a abstrações próprias.
5. Código curto **sem sacrificar legibilidade, testes, segurança ou tratamento de erros**.
6. Modularidade por domínio, com fronteiras explícitas; não criar microserviços sem ADR.
7. Todos os dados de negócio devem respeitar tenant e autorização no servidor; testar acesso cruzado.
8. Nunca guardar segredos no repositório, no browser ou nos logs.
9. Para mudanças de esquema, criar migrations reversíveis quando possível e prever rollback.
10. Para UI, implementar estados loading, vazio, erro, sucesso, acessibilidade e responsividade.
11. Cada PR deve indicar ficheiros alterados, motivo, testes executados e riscos conhecidos.
12. Não afirmar que testes passaram sem os executar. Não alterar produção sem aprovação explícita.
13. Não copiar código de projectos externos sem verificar licença e proveniência.
14. Documentar pressupostos e pedir decisão quando houver ambiguidade de negócio que altere dados, segurança ou cobrança.

## Fluxo por tarefa
**Entender → localizar → propor mudança mínima → implementar → testar → rever → documentar.**

## Critérios para Rust/NestJS
Só criar serviço novo quando existirem métricas, limitação concreta, alternativa avaliada e plano operacional aprovado. Laravel continua responsável pelo core e fonte de verdade.

## Modelo de resposta para implementação
- Pedido e interpretação
- Ficheiros a tocar
- Mudança mínima proposta
- Segurança/multi-tenancy
- Testes
- Resultado e limitações
