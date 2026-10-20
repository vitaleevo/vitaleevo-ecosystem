# Decisões pendentes, hipóteses e riscos
## Decisões de negócio prioritárias
1. A marca pública será **Vitaleevo Ecosystem**, **Vitaleevo**, ou outra?
2. O CRM é um módulo do Ecosystem ou produto separado com marca HolyCRM?
3. Os pedidos de serviço entram sempre na operação central ou podem ser geridos directamente por cada empresa?
4. Profissionais trabalham como indivíduos, empresas ou ambos? Como serão verificados?
5. Quem contrata, factura, recebe pagamentos, faz reembolsos e assume responsabilidade pelos serviços?
6. Qual é o primeiro conjunto de categorias de serviços e em que cidades se opera?
7. O beta é gratuito, pago ou por convite? Quais limites de utilização?
8. Que identidade visual/logótipo oficial deve ser aplicado?

## Decisões técnicas
- Domínio, hosting, object storage, fornecedor de email, serviço de monitorização.
- Modo de autenticação para browser e aplicações futuras.
- Estratégia de pagamentos em Angola após validação de fornecedores.
- Modelo de consentimento e retenção de dados.
- Necessidade real de multi-moeda e idioma inglês no MVP.

## Riscos
| Risco | Impacto | Mitigação |
|---|---|---|
| Escopo excessivo | atraso e custo | MVP P0 e gates |
| Falha de isolamento tenant | crítico | policies, testes cross-tenant, auditoria |
| Dependência de APIs Meta/TikTok | funcionalidades indisponíveis | fases, sandbox e feature flags |
| Falta de liquidez | interrupção | infra mínima, pilotos pagos, custos medidos |
| Má experiência frontend | baixa activação | protótipo, testes com utilizadores |
| Problemas legais/pagamentos | responsabilidade | termos e revisão jurídica local |
| Dados sensíveis em logs | exposição | redacção e retenção mínima |
| Escala antes de validação | complexidade | monólito modular e observabilidade |

## Premissas actuais
Documentos de especificação, não código funcional. Não foi criado repositório nem executado deploy. Integrações sociais completas e pagamentos automatizados não fazem parte da primeira versão pública de testes.
