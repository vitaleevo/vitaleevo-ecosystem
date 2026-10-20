import Link from "next/link";

export default function TermosPage() {
  return (
    <main className="min-h-screen p-5 md:p-10">
      <article className="card max-w-4xl mx-auto p-6 md:p-10 prose prose-slate">
        <Link href="/" className="font-black no-underline">VITALEEVO</Link>
        <p className="text-sm font-bold text-slate-500 mt-8">VERSÃO BETA · 20/10/2026</p>
        <h1 className="text-4xl font-black mt-2">Termos de Utilização</h1>
        <div className="mt-5 p-4 rounded-xl bg-amber-50 text-amber-900 text-sm">
          Documento operacional para piloto controlado. Requer revisão jurídica e comercial antes de lançamento público em produção.
        </div>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">1. Objeto</h2>
          <p>O Vitaleevo disponibiliza, no MVP, um ambiente multiempresa para gestão de contactos, oportunidades, tarefas, pedidos de serviço, equipa, profissionais e administração da plataforma. Funcionalidades pós-MVP ou integrações de terceiros só fazem parte do serviço quando forem expressamente ativadas.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">2. Contas e segurança</h2>
          <p>Cada utilizador é responsável por manter as suas credenciais protegidas e por utilizar apenas os workspaces para os quais foi autorizado. O Platform Owner está sujeito a autenticação multifator. Atividades abusivas, tentativas de acesso a dados de outra empresa ou utilização indevida podem resultar em suspensão.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">3. Dados inseridos pelas empresas</h2>
          <p>A empresa utilizadora é responsável pela legitimidade, qualidade e finalidade dos dados que coloca no CRM e pela gestão das permissões da sua equipa. O sistema separa os dados por tenant e regista ações relevantes para auditoria.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">4. Pedidos e profissionais</h2>
          <p>Pedidos públicos podem ser triados pela operação e atribuídos a profissionais verificados. O estado do pedido é acompanhado no sistema. Condições comerciais, faturação, pagamentos, reembolsos, garantias e responsabilidade contratual por serviços devem ser formalizadas separadamente antes de qualquer operação comercial pública.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">5. Planos beta</h2>
          <p>Os planos Teste, Básico e Avançado são configurações do beta e os respetivos preços ou limites podem ser ajustados pelo Platform Owner antes do lançamento comercial. A ativação de um plano pago no MVP não representa, por si só, confirmação de pagamento quando não existir um processo financeiro formal associado.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">6. Disponibilidade e alterações</h2>
          <p>Durante o beta podem ocorrer manutenções, correções e alterações de funcionalidades. Mudanças materiais que afetem utilização, dados ou condições comerciais devem ser comunicadas aos participantes do piloto.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">7. Uso aceitável</h2>
          <p>Não é permitido utilizar a plataforma para acesso não autorizado, fraude, envio abusivo, introdução deliberada de código malicioso, violação de direitos de terceiros ou tentativa de contornar controlos de segurança e limites técnicos.</p>
        </section>

        <section className="mt-8 space-y-3">
          <h2 className="text-2xl font-black">8. Validação antes do go-live</h2>
          <p>A versão final deverá identificar a entidade contratante, canais formais de suporte, regras de cobrança e cancelamento, responsabilidades, propriedade intelectual, nível de serviço, resolução de conflitos e demais cláusulas exigidas pela operação, com validação jurídica aplicável em Angola.</p>
        </section>

        <div className="mt-10 pt-6 border-t flex flex-wrap gap-4 text-sm font-bold">
          <Link href="/privacidade">Política de Privacidade</Link>
          <Link href="/">Voltar ao Vitaleevo</Link>
        </div>
      </article>
    </main>
  );
}
