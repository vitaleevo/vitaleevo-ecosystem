"use client";

import Link from "next/link";
import Modal from "../../components/Modal";
import { FormEvent, useCallback, useEffect, useState } from "react";

const API = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

type Module = { code:string; name:string; summary:string; phase:"existing"|"foundation"|"planned" };
type Storefront = { id:number; slug:string; name:string; description:string|null; app_code:string; province:string|null; municipality:string|null; status?:string; owner_kind?:string };
type Listing = { id:number; title:string; description:string|null; price_kz:number|null; price_mode:string };
type Mine = { data:Storefront[] };

const accents:Record<string,{letter:string,tone:string,description:string}> = {
  business:{letter:"B",tone:"bg-blue-50 text-blue-700",description:"Crie, organize e desenvolva o seu negócio."},
  jobs:{letter:"J",tone:"bg-orange-50 text-orange-700",description:"Encontre profissionais ou apresente os seus serviços."},
  academy:{letter:"A",tone:"bg-orange-50 text-orange-700",description:"Aprenda competências com aplicação prática."},
  market:{letter:"M",tone:"bg-blue-50 text-blue-700",description:"Produtos, fornecedores e vitrines de revenda."},
  vitaerp:{letter:"E",tone:"bg-sky-50 text-sky-700",description:"Operações, inventário e gestão empresarial."},
  holycrm:{letter:"C",tone:"bg-orange-50 text-orange-700",description:"Contactos, oportunidades e relacionamento."}
};

function cookie(name:string) {
  if (typeof document === "undefined") return "";
  const part = document.cookie.split("; ").find(x => x.startsWith(name+"="));
  return part ? decodeURIComponent(part.split("=").slice(1).join("=")) : "";
}

async function request<T>(path:string, init:RequestInit={}):Promise<T> {
  const method = (init.method || "GET").toUpperCase();
  if (method !== "GET") await fetch(API+"/sanctum/csrf-cookie",{credentials:"include"});
  const headers:Record<string,string>={Accept:"application/json",...((init.headers as Record<string,string>)||{})};
  if(init.body) headers["Content-Type"]="application/json";
  const xsrf=cookie("XSRF-TOKEN"); if(xsrf) headers["X-XSRF-TOKEN"]=xsrf;
  const res=await fetch(API+"/api/v1"+path,{...init,credentials:"include",headers});
  if(!res.ok) {
    const data=await res.json().catch(()=>({message:"Não foi possível concluir a operação."}));
    throw new Error(data.message || Object.values(data.errors||{}).flat().join(" ") || "Erro "+res.status);
  }
  return res.json();
}

export default function EcossistemaPage() {
  const [modules,setModules]=useState<Module[]>([]);
  const [stores,setStores]=useState<Storefront[]>([]);
  const [mine,setMine]=useState<Storefront[]>([]);
  const [authenticated,setAuthenticated]=useState(false);
  const [busy,setBusy]=useState(true);
  const [message,setMessage]=useState("");
  const [error,setError]=useState("");
  const [query,setQuery]=useState("");
  const [app,setApp]=useState("");
  const [selected,setSelected]=useState<{storefront:Storefront;listings:Listing[]}|null>(null);
  const [creating,setCreating]=useState(false);
  const [editingListing,setEditingListing]=useState<number|null>(null);

  const refresh=useCallback(async()=>{
    try {
      const [m,s] = await Promise.all([
        request<{data:Module[]}>("/ecosystem/modules"),
        request<{data:Storefront[]}>("/ecosystem/storefronts")
      ]);
      setModules(m.data); setStores(s.data);
    } catch(e) {setError((e as Error).message);}
    finally {setBusy(false);}
  },[]);
  const refreshMine=useCallback(async()=>{
    try {
      const me=await request<{user:{id:number}}>("/me");
      setAuthenticated(Boolean(me.user.id));
      const own=await request<Mine>("/ecosystem/mine");
      setMine(own.data);
    } catch {setAuthenticated(false);setMine([]);}
  },[]);
  useEffect(()=>{const timer=window.setTimeout(()=>{void refresh();void refreshMine();},0);return ()=>window.clearTimeout(timer);},[refresh,refreshMine]);

  async function search(e:FormEvent<HTMLFormElement>){
    e.preventDefault();setError("");setSelected(null);
    try {
      const qs=new URLSearchParams();
      if(query.trim())qs.set("q",query.trim());
      if(app)qs.set("app",app);
      const result=await request<{data:Storefront[]}>("/ecosystem/storefronts?"+qs.toString());
      setStores(result.data);
    }catch(e){setError((e as Error).message);}
  }
  async function openShop(slug:string){
    setError("");
    try {
      setSelected(await request<{storefront:Storefront;listings:Listing[]}>("/ecosystem/storefronts/"+encodeURIComponent(slug)));
    } catch(e){setError((e as Error).message);}
  }
  async function createStorefront(e:FormEvent<HTMLFormElement>){
    e.preventDefault();setError("");setMessage("");
    const form=e.currentTarget;
    const data=Object.fromEntries(new FormData(form).entries());
    try {
      await request("/ecosystem/storefronts",{method:"POST",body:JSON.stringify(data)});
      form.reset();setCreating(false);setMessage("Vitrine criada como rascunho. Adicione serviços ou produtos e publique quando estiver pronta.");
      await refreshMine();
    }catch(e){setError((e as Error).message);}
  }
  async function publish(s:Storefront){
    setError("");setMessage("");
    try {
      await request("/ecosystem/storefronts/"+s.id,{method:"PATCH",body:JSON.stringify({status:s.status==="published"?"draft":"published"})});
      setMessage(s.status==="published"?"Vitrine retirada da pesquisa pública.":"Vitrine publicada e disponível na pesquisa.");
      await Promise.all([refreshMine(),refresh()]);
    }catch(e){setError((e as Error).message);}
  }
  async function addListing(e:FormEvent<HTMLFormElement>,id:number){
    e.preventDefault();setError("");setMessage("");
    const form=e.currentTarget;const data=Object.fromEntries(new FormData(form).entries());
    try {
      await request("/ecosystem/storefronts/"+id+"/listings",{method:"POST",body:JSON.stringify(data)});
      setEditingListing(null);setMessage("Item adicionado à vitrine.");
      await refresh();
    }catch(e){setError((e as Error).message);}
  }
  const currency=(v:number)=>new Intl.NumberFormat("pt-AO").format(v)+" Kz";

  return <main className="min-h-screen bg-[#F8FAFC] text-[#0F172A]">
    <header className="border-b border-[#DBEAFE] bg-white/95">
      <div className="mx-auto max-w-7xl px-5 py-5 flex items-center justify-between gap-4">
        <Link href="/ecossistema" className="font-black text-xl tracking-tight text-[#2563EB]">VITALEEVO<span className="text-[#F97316]">.</span></Link>
        <nav aria-label="Navegação principal" className="flex flex-wrap gap-4 items-center text-sm font-semibold">
          <a href="#aplicacoes" className="text-slate-600 hover:text-[#2563EB]">Aplicações</a>
          <a href="#vitrines" className="text-slate-600 hover:text-[#2563EB]">Vitrines</a>
          <Link href="/" className="rounded-xl bg-[#2563EB] px-4 py-2.5 text-white hover:bg-[#1D4ED8]">{authenticated?"Meu workspace":"Entrar"}</Link>
        </nav>
      </div>
    </header>

    <section className="relative overflow-hidden bg-[#1E3A8A] text-white">
      <div className="absolute -right-24 -top-36 h-96 w-96 rounded-full bg-[#60A5FA]/30 blur-3xl" aria-hidden="true"/>
      <div className="absolute left-1/2 bottom-0 h-64 w-64 rounded-full bg-[#F97316]/10 blur-3xl" aria-hidden="true"/>
      <div className="relative mx-auto max-w-7xl px-5 py-20 md:py-28 grid md:grid-cols-[1.3fr_.7fr] items-center gap-12">
        <div>
          <div className="inline-flex items-center gap-2 rounded-full border border-white/25 px-4 py-2 text-sm text-white/90"><span className="h-2 w-2 rounded-full bg-[#F97316]"/> UMA CONTA, NOVAS OPORTUNIDADES</div>
          <h1 className="mt-7 max-w-3xl text-4xl md:text-6xl leading-[1.08] font-black tracking-tight">O seu negócio começa aqui. <span className="text-[#FDBA74]">E cresce connosco.</span></h1>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-white/75">Encontre profissionais, apresente os seus serviços, venda produtos, desenvolva competências e faça a gestão da sua empresa num só ecossistema.</p>
          <div className="mt-8 flex flex-wrap gap-3">
            <a href="#vitrines" className="rounded-xl bg-[#F97316] text-[#1B2630] font-bold px-6 py-3 hover:bg-[#EA580C]">Explorar vitrines</a>
            <a href="#aplicacoes" className="rounded-xl border border-white/40 text-white font-bold px-6 py-3 hover:bg-white/10">Conhecer os módulos</a>
          </div>
        </div>
        <div className="rounded-[28px] border border-white/20 bg-white/10 p-7 backdrop-blur-sm shadow-2xl">
          <div className="text-sm font-bold tracking-[.15em] text-[#FED7AA]">A SUA IDENTIDADE VITALEEVO</div>
          <div className="mt-6 flex gap-3 items-center"><div className="grid place-items-center rounded-2xl bg-[#F97316] text-[#30103F] text-2xl font-black h-14 w-14">V</div><div><div className="font-bold text-lg">Uma única conta</div><div className="text-sm text-white/70">Vários papéis, múltiplas empresas</div></div></div>
          <div className="mt-6 grid grid-cols-2 gap-3 text-sm">
            {["Profissional","Empresa","Revendedor","Aluno"].map(x=><div key={x} className="rounded-xl border border-white/15 bg-white/10 px-4 py-3">✓ {x}</div>)}
          </div>
          <div className="mt-6 text-xs text-white/60">O acesso a cada aplicação é activado conforme as funcionalidades disponíveis.</div>
        </div>
      </div>
    </section>

    <section id="aplicacoes" className="mx-auto max-w-7xl px-5 py-20">
      <div className="text-[#1D4ED8] text-sm font-black uppercase tracking-widest">Seis aplicações, uma plataforma</div>
      <h2 className="mt-3 text-3xl md:text-4xl font-black">Escolha o próximo passo do seu negócio</h2>
      <p className="mt-3 text-slate-600 max-w-2xl">Cada aplicação funciona com a mesma identidade Vitaleevo. As etiquetas distinguem a infraestrutura disponível das funcionalidades ainda em desenvolvimento.</p>
      {busy&&<p role="status" className="mt-8 text-slate-500">A carregar aplicações…</p>}
      <div className="mt-10 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
        {modules.map(m=> {
          const style=accents[m.code]||{letter:"V",tone:"bg-slate-100 text-slate-700",description:m.summary};
          return <article key={m.code} className="rounded-3xl border border-[#DBEAFE] bg-white p-7 shadow-sm hover:shadow-xl transition-shadow flex flex-col">
            <div className="flex items-start justify-between gap-4">
              <div className={style.tone+" rounded-2xl h-14 w-14 grid place-items-center text-2xl font-black"}>{style.letter}</div>
              <span className="rounded-full bg-[#EFF6FF] px-3 py-1.5 text-xs font-bold text-[#604670]">{m.phase==="existing"?"Base existente":m.phase==="foundation"?"Vitrines em construção":"Planeado"}</span>
            </div>
            <h3 className="mt-7 text-xl font-black">{m.name}</h3>
            <p className="mt-2 text-slate-600 leading-relaxed grow">{style.description}</p>
            <div className="mt-6 pt-5 border-t border-slate-100 text-sm font-bold text-[#2563EB]">{m.code==="jobs"||m.code==="market"?"Vitrines e catálogo disponíveis →":m.code==="holycrm"?"Aceder ao workspace →":"Disponível em fase futura"}</div>
            {m.code==="jobs"?<Link href="/jobs" className="mt-4 inline-flex w-fit rounded-xl bg-[#155EEF] px-4 py-2.5 text-sm font-bold text-white">Abrir Vitaleevo Jobs →</Link>:m.code==="market"?<a href="#vitrines" onClick={()=>setApp(m.code)} className="mt-4 inline-flex text-sm font-bold text-[#155EEF]">Explorar vitrines Market →</a>:null}
          </article>;
        })}
      </div>
    </section>

    <section id="vitrines" className="bg-white border-y border-[#DBEAFE]">
      <div className="mx-auto max-w-7xl px-5 py-20">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div><div className="text-[#1D4ED8] text-sm font-black uppercase tracking-widest">Descobrir e contratar</div><h2 className="mt-3 text-3xl md:text-4xl font-black">Vitrines profissionais e comerciais</h2><p className="mt-3 text-slate-600">Resultados publicados por pessoas e empresas registadas na plataforma.</p></div>
          {authenticated&&<button className="rounded-xl bg-[#2563EB] px-5 py-3 font-bold text-white" onClick={()=>setCreating(v=>!v)}>{creating?"Cancelar":"Criar a minha vitrine"}</button>}
        </div>
        <form onSubmit={search} className="mt-8 grid md:grid-cols-[1fr_180px_auto] gap-3 rounded-2xl border border-[#DBEAFE] p-3 bg-[#F8FAFC]">
          <label className="sr-only" htmlFor="search-vitrine">Pesquisar</label>
          <input id="search-vitrine" value={query} onChange={e=>setQuery(e.target.value)} placeholder="Pesquisar profissionais, serviços ou lojas" className="rounded-xl border border-[#DBEAFE] px-4 py-3 bg-white min-w-0"/>
          <label className="sr-only" htmlFor="filter-vitrine">Categoria</label>
          <select id="filter-vitrine" value={app} onChange={e=>setApp(e.target.value)} className="rounded-xl border border-[#DBEAFE] px-4 py-3 bg-white"><option value="">Todas as vitrines</option><option value="jobs">Profissionais</option><option value="market">Comércio</option></select>
          <button className="rounded-xl bg-[#2563EB] px-7 py-3 font-bold text-white">Pesquisar</button>
        </form>

        {error&&<div role="alert" className="mt-5 rounded-xl bg-red-50 border border-red-200 px-5 py-4 text-red-800">{error}</div>}
        {message&&<div role="status" className="mt-5 rounded-xl bg-green-50 border border-green-200 px-5 py-4 text-green-800">{message}</div>}

        {creating&&<Modal title="Criar a minha vitrine" description="Pode gerir esta vitrine com a mesma conta Vitaleevo." onClose={()=>setCreating(false)}><form onSubmit={createStorefront} className="grid md:grid-cols-2 gap-4">
          {error&&<p role="alert" className="md:col-span-2 rounded-xl bg-red-50 p-3 text-sm text-red-800">{error}</p>}
          <h3 className="md:col-span-2 text-lg font-bold">Informações da vitrine</h3>
          <label className="text-sm font-semibold">Tipo de vitrine<select name="app_code" required className="field mt-2"><option value="jobs">Serviços e profissionais</option><option value="market">Comércio e revenda</option></select></label>
          <label className="text-sm font-semibold">Titular<select name="owner_kind" required className="field mt-2"><option value="personal">Eu, como pessoa</option><option value="business">A minha empresa actual</option></select></label>
          <label className="text-sm font-semibold">Nome público<input name="name" required maxLength={160} className="field mt-2" placeholder="Ex.: Técnico João / Loja Central"/></label>
          <label className="text-sm font-semibold">Província<input name="province" maxLength={100} className="field mt-2" placeholder="Ex.: Luanda"/></label>
          <label className="text-sm font-semibold md:col-span-2">Descrição<textarea name="description" rows={3} maxLength={1800} className="field mt-2" placeholder="O que oferece e em que região atende?"/></label>
          <button className="rounded-xl bg-[#2563EB] text-white py-3 px-5 font-bold">Guardar vitrine como rascunho</button>
        </form></Modal>}
        {!busy && stores.length===0&&<div className="mt-8 rounded-2xl border border-dashed border-[#BFDBFE] bg-[#F8FAFC] p-10 text-center text-slate-600"><div className="font-bold text-lg">Ainda não existem vitrines publicadas nesta pesquisa.</div><p className="mt-2">Se é um profissional ou comerciante, pode criar a primeira vitrine depois de entrar na plataforma.</p></div>}
        <div className="mt-8 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
          {stores.map(s=><article key={s.id} className="rounded-2xl border border-[#DBEAFE] p-6 shadow-sm">
            <div className="flex justify-between items-center gap-3"><div className="text-xs font-black text-[#1D4ED8] uppercase tracking-wide">{s.app_code==="jobs"?"Serviços profissionais":"Comércio e revenda"}</div><div className="h-10 w-10 grid place-items-center rounded-xl bg-[#EFF6FF] text-[#2563EB] font-black">{s.name.charAt(0).toUpperCase()}</div></div>
            <h3 className="mt-4 font-black text-xl">{s.name}</h3>
            <p className="mt-2 text-sm text-slate-600 line-clamp-3">{s.description||"Conheça os serviços e produtos desta vitrine."}</p>
            <div className="mt-4 text-sm text-slate-500">{[s.municipality,s.province].filter(Boolean).join(", ")||"Localização por informar"}</div>
            <button className="mt-6 font-bold text-[#2563EB]" onClick={()=>openShop(s.slug)}>Ver catálogo →</button>
          </article>)}
        </div>
        {selected&&<section className="mt-10 rounded-3xl border border-[#BFDBFE] bg-[#F8FAFC] p-6 md:p-8" aria-label="Detalhe da vitrine">
          <div className="flex flex-wrap justify-between gap-4"><div><div className="text-sm text-[#1D4ED8] font-black uppercase">Vitrine publicada</div><h3 className="text-2xl font-black mt-2">{selected.storefront.name}</h3><p className="text-slate-600 mt-2">{selected.storefront.description}</p></div><button onClick={()=>setSelected(null)} className="font-bold text-[#2563EB] self-start">Fechar ×</button></div>
          <div className="mt-6 grid md:grid-cols-3 gap-3">{selected.listings.map(x=><div key={x.id} className="rounded-xl bg-white border border-[#DBEAFE] p-5"><h4 className="font-bold">{x.title}</h4><p className="mt-2 text-sm text-slate-600">{x.description}</p><div className="mt-4 text-sm font-black text-[#2563EB]">{x.price_mode==="fixed"&&x.price_kz!==null?currency(x.price_kz):"Sob orçamento"}</div></div>)}</div>
          {selected.listings.length===0&&<p className="mt-5 text-slate-500">Esta vitrine ainda não tem itens publicados.</p>}
          <p className="mt-6 text-xs text-slate-500">Nesta fase, os pagamentos e contratação directa ainda não estão activos. Não envie pagamentos fora da plataforma com base nestes anúncios.</p>
        </section>}
      </div>
    </section>

    <section className="mx-auto max-w-7xl px-5 py-20">
      <div className="flex flex-wrap justify-between gap-4 items-end"><div><div className="text-[#1D4ED8] uppercase tracking-widest text-sm font-black">Gestão da conta</div><h2 className="mt-2 text-3xl font-black">As minhas vitrines</h2><p className="mt-2 text-slate-600">Publique os seus serviços e produtos com uma só conta Vitaleevo.</p></div>{!authenticated&&<Link className="rounded-xl bg-[#2563EB] px-5 py-3 text-white font-bold" href="/">Entrar ou criar conta</Link>}</div>
      {authenticated ? <div className="mt-7 grid md:grid-cols-2 gap-4">
        {mine.length===0&&<div className="rounded-2xl border border-dashed border-[#CBD5E1] p-8 text-slate-600 md:col-span-2">Ainda não criou nenhuma vitrine. Utilize o botão «Criar a minha vitrine» na secção acima.</div>}
        {mine.map(s=><article key={s.id} className="rounded-2xl border border-[#DBEAFE] bg-white p-6">
          <div className="flex items-center justify-between gap-4"><span className="text-xs uppercase font-bold text-[#1D4ED8]">{s.app_code==="jobs"?"Profissionais":"Comércio"} · {s.owner_kind==="personal"?"Pessoal":"Empresa"}</span><span className="rounded-full bg-[#EFF6FF] px-3 py-1 text-xs font-bold">{s.status==="published"?"Publicada":"Rascunho"}</span></div>
          <h3 className="text-lg font-black mt-3">{s.name}</h3>
          <div className="flex flex-wrap gap-3 mt-5">
            <button className="rounded-lg border border-[#CBD5E1] px-4 py-2 font-semibold text-sm" onClick={()=>setEditingListing(editingListing===s.id?null:s.id)}>Adicionar item</button>
            <button className="rounded-lg bg-[#2563EB] px-4 py-2 text-white font-semibold text-sm" onClick={()=>publish(s)}>{s.status==="published"?"Retirar publicação":"Publicar vitrine"}</button>
            {s.status==="published"&&<button className="text-sm font-bold text-[#2563EB]" onClick={()=>openShop(s.slug)}>Ver pública</button>}
          </div>
          {editingListing===s.id&&<Modal title={s.app_code==="jobs"?"Adicionar serviço":"Adicionar produto"} description={"Vitrine: "+s.name} onClose={()=>setEditingListing(null)} maxWidth="md"><form onSubmit={e=>addListing(e,s.id)} className="grid gap-3">
            {error&&<p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-800">{error}</p>}
            <input required name="title" className="field" placeholder={s.app_code==="jobs"?"Nome do serviço":"Nome do produto"}/>
            <textarea name="description" className="field" rows={2} placeholder="Descrição do item"/>
            <select name="price_mode" className="field"><option value="quote">Sob orçamento</option><option value="fixed">Preço fixo</option></select>
            <input name="price_kz" type="number" min={0} className="field" placeholder="Preço em Kz (se aplicável)"/>
            <button className="rounded-xl bg-[#F97316] px-4 py-3 font-bold text-[#1B2630]">Guardar item</button>
          </form></Modal>}
        </article>)}
      </div>:<div className="mt-6 text-slate-600">Entre na sua conta Vitaleevo para criar e administrar vitrines.</div>}
    </section>
    <footer className="border-t border-[#DBEAFE] bg-white"><div className="max-w-7xl mx-auto px-5 py-8 flex flex-wrap justify-between gap-4 text-sm text-slate-500"><span>© 2026 Vitaleevo Ecosystem · Identidade, Tecnologia, Operações e Dados</span><span><Link href="/termos" className="hover:underline">Termos</Link> · <Link href="/privacidade" className="hover:underline">Privacidade</Link></span></div></footer>
  </main>;
}
