import Link from "next/link";

export default function PrivacidadePage() {
  return (
    <main className="min-h-screen p-5 md:p-10">
      <article className="card max-w-4xl mx-auto p-6 md:p-10 prose prose-slate">
        <Link href="/" className="font-black no-underline">VITALEEVO</Link>
        <p className="text-sm font-bold text-slate-500 mt-8">VERSÃO BETA · 20/10/2026</p>
        <h1 className="text-4xl font-black mt-2">Política de Privacidade</h1>
        <div className="mt-5 p-4 rounded-xl bg-amber-50 text-amber-900 text-sm">
          Documento operacional para o beta. Deve ser revisto e aprovado juridicamente antes de lançamento público em produção.
        </div>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">1. Dados tratados</h2>
          <p>O Vitaleevo pode tratar dados de conta e empresa, incluindo nome, email, telefone, memberships e preferências; dados inseridos no CRM, como contactos, empresas, oportunidades e tarefas; e dados de pedidos de serviço, como nome, telefone, email opcional, categoria, descrição, estado e avaliação.</p>
          <p>Também são mantidos registos técnicos e de auditoria necessários para segurança, isolamento entre empresas, investigação de incidentes e rastreabilidade de operações.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">2. Finalidades</h2>
          <p>Os dados são utilizados para autenticação, gestão multiempresa, operação do CRM, triagem e execução de pedidos de serviço, notificações, suporte, prevenção de abuso, segurança e auditoria.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">3. Acesso e partilha operacional</h2>
          <p>Os dados empresariais são disponibilizados apenas a utilizadores autorizados do respetivo workspace. Dados de pedidos podem ser disponibilizados à equipa responsável pela triagem e ao profissional verificado a quem o serviço for atribuído, na medida necessária à execução.</p>
          <p>Fornecedores técnicos de infraestrutura ou email poderão processar dados quando forem configurados em produção, sob os controlos e contratos aplicáveis.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">4. Segurança</h2>
          <p>O MVP aplica isolamento por tenant, controlo de papéis, proteção CSRF para sessões web, limitação de pedidos, auditoria e MFA obrigatório para o Platform Owner. Em produção, o serviço deve operar exclusivamente por HTTPS e com gestão segura de segredos e backups.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">5. Retenção, exportação e eliminação</h2>
          <p>Os períodos definitivos de retenção devem ser aprovados antes do go-live. Durante o beta controlado, a política operacional é minimizar dados, conservar apenas o necessário para o serviço e auditoria, e tratar pedidos de correção, exportação ou eliminação pelo canal de suporte definido para o piloto, respeitando obrigações de segurança e conservação aplicáveis.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">6. Consentimento em pedidos públicos</h2>
          <p>O formulário público exige consentimento antes do envio. Esse consentimento destina-se ao tratamento dos dados necessários para receber, triar, acompanhar e responder ao pedido de serviço.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">7. Revisão jurídica</h2>
          <p>A versão de produção deverá definir formalmente a entidade responsável pelo tratamento, contactos de privacidade, prazos de retenção, bases legais, subprocessadores e procedimentos de exercício de direitos, com validação jurídica alinhada à legislação aplicável em Angola.</p>
        </section>

        <div className="mt-10 pt-6 border-t flex flex-wrap gap-4 text-sm font-bold">
          <Link href="/termos">Termos de Utilização</Link>
          <Link href="/">Voltar ao Vitaleevo</Link>
        </div>
      </article>
    </main>
  );
}
