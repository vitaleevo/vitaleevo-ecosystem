"use client";

import Link from "next/link";
import { FormEvent, useCallback, useEffect, useState } from "react";
import Modal from "../../components/Modal";

const API = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000";

type Shop = {id:number; slug:string; name:string; description:string|null; province:string|null; municipality:string|null};
type Listing = {id:number; title:string; description:string|null; price_kz:number|null; price_mode:string};
type ShopDetail = {storefront:Shop; listings:Listing[]};
type Category = {id:number;name:string};
type Professional = {id:number;specialty:string;phone:string|null;verification_state:string};
type Job = {id:number; status:string;public_reference:string;request_status:string;description:string;customer_name:string; updated_at:string};
type ProviderRequest = {public_reference:string; status:string;description:string;storefront_name:string;created_at:string};
type OwnerRequest = {id:number;public_reference:string;status:string;customer_name:string;description:string;storefront_name:string;owner_kind:string;listing_title:string|null};
type UserInfo = {user:{name:string;is_platform_owner:boolean;mfa_verified:boolean}};

function readCookie(name:string) {
  const val = document.cookie.split("; ").find(x=>x.startsWith(name+"="));
  return val ? decodeURIComponent(val.slice(name.length+1)) : "";
}
async function api<T>(path:string,options:RequestInit={}) : Promise<T> {
  const method=(options.method||"GET").toUpperCase();
  if(method!=="GET"&&method!=="HEAD"){
    await fetch(API+"/sanctum/csrf-cookie",{credentials:"include"});
  }
  const headers:Record<string,string>={Accept:"application/json",...((options.headers as Record<string,string>)||{})};
  if(options.body) headers["Content-Type"]="application/json";
  const xsrf=readCookie("XSRF-TOKEN");if(xsrf) headers["X-XSRF-TOKEN"]=xsrf;
  const response=await fetch(API+"/api/v1"+path,{...options,headers,credentials:"include"});
  if(!response.ok){
    const err=await response.json().catch(()=>({message:"Não foi possível processar o pedido."}));
    const fields=Object.values(err.errors||{}).flat().join(" ");
    throw new Error(fields||err.message||"Erro "+response.status);
  }
  return response.json();
}
const prices=(number:number)=>new Intl.NumberFormat("pt-AO").format(number)+" Kz";
const stateLabel=(status:string)=>({
  submitted:"Recebido",triage:"Em triagem",assigned:"Encaminhado",accepted:"Aceite",
  in_progress:"Em execução",delivered:"A aguardar validação",completed:"Concluído",
  cancelled:"Cancelado",rejected:"Recusado",pending:"Pendente",verified:"Verificado",
}[status]||status);
const requestBody=(event:FormEvent<HTMLFormElement>)=>Object.fromEntries(new FormData(event.currentTarget).entries());

export default function JobsPage() {
  const [shops,setShops]=useState<Shop[]>([]);
  const [categories,setCategories]=useState<Category[]>([]);
  const [query,setQuery]=useState("");
  const [province,setProvince]=useState("");
  const [selected,setSelected]=useState<ShopDetail|null>(null);
  const [requestListing,setRequestListing]=useState<Listing|null>(null);
  const [requestKey,setRequestKey]=useState("");
  const [requestShop,setRequestShop]=useState<Shop|null>(null);
  const [showApply,setShowApply]=useState(false);
  const [showTracking,setShowTracking]=useState(false);
  const [tracking,setTracking]=useState<{reference:string;status:string;updated_at?:string}|null>(null);
  const [submitted,setSubmitted]=useState<{reference:string;status:string}|null>(null);
  const [me,setMe]=useState<UserInfo|null>(null);
  const [profile,setProfile]=useState<Professional|null>(null);
  const [assignments,setAssignments]=useState<Job[]>([]);
  const [requests,setRequests]=useState<ProviderRequest[]>([]);
  const [operator,setOperator]=useState<OwnerRequest[]>([]);
  const [busy,setBusy]=useState(false);
  const [loading,setLoading]=useState(true);
  const [message,setMessage]=useState("");
  const [error,setError]=useState("");

  const discover=useCallback(async(search="",region="")=>{
    const params=new URLSearchParams({app:"jobs"});
    if(search.trim())params.set("q",search.trim());
    if(region.trim())params.set("province",region.trim());
    const result=await api<{data:Shop[]}>("/ecosystem/storefronts?"+params.toString());
    setShops(result.data);
  },[]);
  const loadPrivate=useCallback(async()=>{
    try{
      const info=await api<UserInfo>("/me");
      setMe(info);
      const [p,j,r]=await Promise.all([
        api<{profile:Professional|null}>("/jobs/professional/me"),
        api<{data:Job[]}>("/professional/jobs").catch(()=>({data:[]})),
        api<{data:ProviderRequest[]}>("/jobs/my-requests"),
      ]);
      setProfile(p.profile);
      setAssignments(j.data);
      setRequests(r.data);
      if(info.user.is_platform_owner&&info.user.mfa_verified){
        const op=await api<{data:OwnerRequest[]}>("/platform/jobs/requests");
        setOperator(op.data);
      }
    }catch{setMe(null);setProfile(null);setAssignments([]);setRequests([]);setOperator([]);}
  },[]);
  useEffect(()=>{
    const timer=window.setTimeout(()=>{
      Promise.all([discover(),api<Category[]>("/service-categories").then(setCategories),loadPrivate()])
        .catch(e=>setError((e as Error).message)).finally(()=>setLoading(false));
    },0);
    return ()=>window.clearTimeout(timer);
  },[discover,loadPrivate]);

  async function doSearch(event:FormEvent<HTMLFormElement>){
    event.preventDefault();setError("");setLoading(true);
    try{await discover(query,province);}catch(e){setError((e as Error).message);}
    finally{setLoading(false);}
  }
  async function showShop(slug:string){
    setError("");
    try{setSelected(await api<ShopDetail>("/ecosystem/storefronts/"+encodeURIComponent(slug)));}
    catch(e){setError((e as Error).message);}
  }
  function requestFor(shop:Shop,listing:Listing|null=null){
    setSelected(null);setSubmitted(null);setError("");
    setRequestShop(shop);setRequestListing(listing);setRequestKey(crypto.randomUUID());
  }
  async function sendRequest(event:FormEvent<HTMLFormElement>){
    event.preventDefault();if(!requestShop)return;
    setBusy(true);setError("");
    const body=requestBody(event);
    try{
      const result=await api<{reference:string;status:string}>("/jobs/requests",{
        method:"POST",headers:{"Idempotency-Key":requestKey},body:JSON.stringify({
          ...body,storefront_id:requestShop.id,
          listing_id:requestListing?.id,
          consent:Boolean(body.consent),
        }),
      });
      setSubmitted(result);setMessage("O pedido foi registado com sucesso.");
    }catch(e){setError((e as Error).message);}
    finally{setBusy(false);}
  }
  async function track(event:FormEvent<HTMLFormElement>){
    event.preventDefault();setBusy(true);setError("");
    try{
      const result=await api<{reference:string;status:string;updated_at:string}>("/public/service-requests/status",{
        method:"POST",body:JSON.stringify(requestBody(event)),
      });
      setTracking(result);
    }catch(e){setError((e as Error).message);}
    finally{setBusy(false);}
  }
  async function apply(event:FormEvent<HTMLFormElement>){
    event.preventDefault();setBusy(true);setError("");
    try{
      const result=await api<{profile:Professional;message:string}>("/jobs/professional/apply",{
        method:"POST",body:JSON.stringify(requestBody(event)),
      });
      setProfile(result.profile);setShowApply(false);setMessage(result.message);
    }catch(e){setError((e as Error).message);}
    finally{setBusy(false);}
  }
  async function jobAction(job:Job,newStatus:string){
    if(!window.confirm("Confirmar alteração para: "+stateLabel(newStatus)+"?"))return;
    setBusy(true);setError("");
    try{
      await api("/professional/jobs/"+job.id,{
        method:"PATCH",body:JSON.stringify({status:newStatus}),
      });
      setMessage("Trabalho actualizado.");await loadPrivate();
    }catch(e){setError((e as Error).message);}
    finally{setBusy(false);}
  }
  async function operatorAction(item:OwnerRequest,action:"route"|"complete"){
    const prompt=action==="route"?"Encaminhar este pedido ao profissional ou empresa da vitrine?":"Validar a entrega e concluir o pedido?";
    if(!window.confirm(prompt))return;
    setBusy(true);setError("");
    try{
      await api("/platform/jobs/requests/"+item.id+"/"+action,{method:"POST"});
      setMessage(action==="route"?"Pedido encaminhado.":"Entrega validada. O cliente já pode avaliar.");
      await loadPrivate();
    }catch(e){setError((e as Error).message);}
    finally{setBusy(false);}
  }

  return <main className="min-h-screen bg-[#F8FAFC] text-[#152538]">
    <header className="bg-white border-b border-slate-200">
      <div className="mx-auto max-w-7xl px-5 py-5 flex flex-wrap gap-4 justify-between items-center">
        <Link href="/ecossistema" className="font-black text-xl tracking-tight text-[#155EEF]">VITALEEVO<span className="text-[#F97316]">.</span> <span className="ml-2 text-[#152538] font-semibold">Jobs</span></Link>
        <nav className="flex flex-wrap gap-4 items-center text-sm font-semibold" aria-label="Navegação Jobs">
          <a href="#profissionais" className="hover:text-[#155EEF]">Profissionais</a>
          <a href="#minha-area" className="hover:text-[#155EEF]">Minha área</a>
          <button type="button" onClick={()=>{setShowTracking(true);setTracking(null);setError("");}} className="rounded-xl border border-slate-200 px-4 py-2.5 hover:bg-slate-50">Acompanhar pedido</button>
          <Link href="/" className="rounded-xl bg-[#155EEF] px-4 py-2.5 text-white">{me?"Meu workspace":"Entrar"}</Link>
        </nav>
      </div>
    </header>
    <section className="relative overflow-hidden bg-[#112A4C] text-white">
      <div aria-hidden="true" className="absolute -right-32 -top-48 w-[500px] h-[500px] rounded-full bg-[#246DE2]/30 blur-3xl"/>
      <div className="mx-auto max-w-7xl px-5 py-20 md:py-24 relative grid gap-10 md:grid-cols-[1.4fr_.6fr] items-center">
        <div>
          <div className="inline-flex rounded-full border border-white/25 px-4 py-2 text-xs font-bold uppercase tracking-wider text-[#B9D4FF]">A rede de serviços profissionais da Vitaleevo</div>
          <h1 className="mt-6 text-4xl md:text-6xl leading-tight font-black max-w-3xl">Encontre o profissional certo para o seu próximo projecto.</h1>
          <p className="text-white/75 mt-5 max-w-2xl text-lg leading-relaxed">Explore vitrines reais, peça um orçamento e acompanhe a execução do serviço. A Vitaleevo coordena o encaminhamento de cada pedido.</p>
          <div className="flex flex-wrap gap-3 mt-7"><a href="#profissionais" className="rounded-xl bg-[#F97316] px-6 py-3 text-[#18283A] font-bold hover:bg-[#FDBA74]">Procurar profissionais</a>
            <button className="rounded-xl border border-white/40 px-6 py-3 font-bold hover:bg-white/10" onClick={()=>{setShowTracking(true);setError("");}}>Tenho um pedido</button></div>
        </div>
        <div className="rounded-3xl border border-white/20 bg-white/10 p-7 backdrop-blur-sm">
          <div className="text-[#FED7AA] text-sm uppercase tracking-widest font-bold">Como funciona</div>
          <div className="mt-6 space-y-5">
            {[["01","Escolha","Pesquise profissionais ou empresas por localização e serviço."],["02","Solicite","Preencha o pedido numa janela simples e segura."],["03","Acompanhe","A Vitaleevo valida, encaminha e acompanha a execução."]].map(([n,t,d])=>
              <div key={n} className="flex gap-4"><span className="font-black text-2xl text-[#FDBA74]">{n}</span><div><h3 className="font-bold">{t}</h3><p className="text-sm text-white/70 mt-1">{d}</p></div></div>)}
          </div>
        </div>
      </div>
    </section>

    <section id="profissionais" className="max-w-7xl mx-auto px-5 py-16 md:py-20">
      <div className="flex flex-wrap gap-4 justify-between items-end">
        <div><div className="text-xs font-black tracking-widest text-[#155EEF] uppercase">Directório de serviços</div><h2 className="text-3xl md:text-4xl font-black mt-2">Profissionais e empresas</h2><p className="mt-2 text-slate-600">Vitrines públicas registadas no Vitaleevo Jobs.</p></div>
        <Link className="inline-flex rounded-xl border border-slate-200 bg-white px-5 py-3 font-bold text-[#155EEF] hover:bg-blue-50" href="/ecossistema#vitrines">Criar uma vitrine →</Link>
      </div>
      <form onSubmit={doSearch} className="grid gap-3 md:grid-cols-[1fr_230px_auto] rounded-2xl border border-slate-200 bg-white p-3 mt-8 shadow-sm">
        <label className="sr-only" htmlFor="jobs-query">Pesquisar serviços ou profissionais</label>
        <input id="jobs-query" value={query} onChange={e=>setQuery(e.target.value)} className="rounded-xl border border-slate-200 px-4 py-3 min-w-0" placeholder="Ex.: electricista, design gráfico, fotografia"/>
        <label className="sr-only" htmlFor="jobs-province">Província</label>
        <input id="jobs-province" value={province} onChange={e=>setProvince(e.target.value)} className="rounded-xl border border-slate-200 px-4 py-3" placeholder="Província (opcional)"/>
        <button className="rounded-xl bg-[#155EEF] px-7 py-3 text-white font-bold">Pesquisar</button>
      </form>
      {error&&<div role="alert" className="rounded-xl mt-5 bg-red-50 border border-red-200 p-4 text-sm text-red-700">{error}</div>}
      {message&&<div role="status" className="rounded-xl mt-5 bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{message}</div>}
      {loading?<p role="status" className="mt-7 text-slate-500">A carregar vitrines…</p>:
      shops.length===0?<div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 mt-7 text-center"><h3 className="font-black text-lg">Não encontramos vitrines nesta pesquisa.</h3><p className="mt-2 text-slate-500">Experimenta pesquisar noutra especialidade ou localidade.</p></div>:
      <div className="mt-7 grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
        {shops.map(shop=><article key={shop.id} className="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col">
          <div className="h-28 bg-gradient-to-br from-[#DCE8FF] via-[#EAF0FA] to-[#FDE9D7] relative">
            <div className="absolute -bottom-7 left-6 h-16 w-16 grid place-items-center rounded-2xl border-4 border-white bg-[#155EEF] text-white font-black text-3xl">{shop.name[0].toUpperCase()}</div>
          </div>
          <div className="p-6 pt-11 grow flex flex-col">
            <div className="text-xs font-bold text-[#155EEF] uppercase">Prestação de serviços</div>
            <h3 className="font-black text-xl mt-2">{shop.name}</h3>
            <p className="text-sm text-slate-600 mt-3 line-clamp-3 grow">{shop.description||"Conheça os serviços deste prestador."}</p>
            <p className="text-sm text-slate-500 mt-4">⌖ {[shop.municipality,shop.province].filter(Boolean).join(", ")||"Localização por informar"}</p>
            <div className="flex flex-wrap gap-2 mt-6">
              <button onClick={()=>showShop(shop.slug)} className="rounded-xl border border-slate-200 px-4 py-2.5 font-bold text-sm hover:bg-slate-50">Ver serviços</button>
              <button onClick={()=>requestFor(shop)} className="rounded-xl bg-[#155EEF] px-4 py-2.5 font-bold text-white text-sm hover:bg-[#0847BC]">Solicitar orçamento</button>
            </div>
          </div>
        </article>)}
      </div>}
    </section>

    <section id="minha-area" className="bg-white border-y border-slate-200">
      <div className="mx-auto max-w-7xl px-5 py-16 md:py-20">
        <div className="text-xs font-black tracking-widest text-[#155EEF] uppercase">Conta Vitaleevo</div>
        <h2 className="mt-2 text-3xl font-black">O meu espaço Jobs</h2>
        {!me?<div className="mt-5 rounded-2xl bg-[#F4F7FE] border border-blue-100 p-7"><p className="text-slate-600">Entre na sua conta para criar um perfil profissional, gerir vitrines e acompanhar os trabalhos atribuídos.</p>
          <Link className="inline-flex mt-5 rounded-xl bg-[#155EEF] text-white px-5 py-3 font-bold" href="/">Entrar ou criar conta</Link></div>:
        <>
          <div className="mt-7 flex flex-wrap justify-between gap-4 items-center rounded-2xl border border-slate-200 bg-[#F8FAFC] p-6">
            <div><h3 className="font-black text-xl">Olá, {me.user.name}</h3><p className="text-slate-600 mt-1">{profile ? "Estado da validação profissional: "+stateLabel(profile.verification_state) : "Ainda não tem perfil profissional validado."}</p></div>
            <div className="flex flex-wrap gap-3">
              <button onClick={()=>{setShowApply(true);setError("");}} className="rounded-xl bg-[#155EEF] text-white font-bold px-5 py-3">{profile?"Actualizar perfil":"Registar-me como profissional"}</button>
              <Link className="rounded-xl border border-slate-200 bg-white font-bold px-5 py-3" href="/ecossistema#vitrines">Gerir vitrines</Link>
            </div>
          </div>
          <div className="grid xl:grid-cols-2 gap-5 mt-6">
            <div className="rounded-2xl border border-slate-200 p-6"><h3 className="text-xl font-black">Trabalhos atribuídos</h3>
              <p className="mt-1 text-sm text-slate-500">Apenas trabalhos encaminhados pela operação Vitaleevo.</p>
              {assignments.length===0?<p className="text-sm mt-5 text-slate-500">Ainda não existem trabalhos atribuídos.</p>:
              <div className="space-y-3 mt-5">{assignments.map(job=><div key={job.id} className="rounded-xl border border-slate-200 p-4">
                <div className="flex flex-wrap gap-2 justify-between"><b>{job.public_reference}</b><span className="text-xs rounded-full bg-blue-50 text-blue-800 px-3 py-1">{stateLabel(job.status)}</span></div>
                <p className="mt-2 text-sm text-slate-600">{job.description}</p>
                <div className="flex flex-wrap gap-2 mt-4">
                  {job.status==="assigned"&&<><button disabled={busy} className="rounded-lg bg-[#155EEF] text-white px-3 py-2 text-sm font-bold" onClick={()=>jobAction(job,"accepted")}>Aceitar</button>
                    <button disabled={busy} className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold" onClick={()=>jobAction(job,"rejected")}>Recusar</button></>}
                  {job.status==="accepted"&&<button disabled={busy} className="rounded-lg bg-[#155EEF] text-white px-3 py-2 text-sm font-bold" onClick={()=>jobAction(job,"in_progress")}>Iniciar serviço</button>}
                  {job.status==="in_progress"&&<button disabled={busy} className="rounded-lg bg-[#155EEF] text-white px-3 py-2 text-sm font-bold" onClick={()=>jobAction(job,"completed")}>Entregar trabalho</button>}
                  {job.status==="completed"&&<span className="text-xs text-slate-500">Entrega submetida para validação da Vitaleevo.</span>}
                </div>
              </div>)}</div>}
            </div>
            <div className="rounded-2xl border border-slate-200 p-6"><h3 className="text-xl font-black">Pedidos para as minhas vitrines</h3>
              <p className="mt-1 text-sm text-slate-500">Resumo sem contacto privado do cliente. O encaminhamento é validado pela operação.</p>
              {requests.length===0?<p className="text-sm mt-5 text-slate-500">Ainda não há pedidos nas suas vitrines Jobs.</p>:
              <div className="space-y-3 mt-5">{requests.map(item=><div key={item.public_reference} className="rounded-xl bg-[#F8FAFC] p-4">
                <div className="flex flex-wrap justify-between gap-2"><b>{item.public_reference}</b><span className="text-xs text-[#155EEF] font-bold">{stateLabel(item.status)}</span></div>
                <div className="text-sm font-semibold mt-1">{item.storefront_name}</div>
                <p className="text-sm mt-2 text-slate-600">{item.description}</p>
              </div>)}</div>}
            </div>
          </div>
          {me.user.is_platform_owner&&me.user.mfa_verified&&<section className="mt-8 rounded-2xl border border-orange-200 bg-orange-50/30 p-6">
            <h3 className="text-xl font-black">Operação Vitaleevo · Triagem de Jobs</h3>
            <p className="mt-2 text-slate-600 text-sm">Pedidos dirigidos a vitrines. Atribuição individual requer profissional verificado. <Link href="/" className="font-bold text-[#155EEF] underline">Verificar profissionais no painel Owner →</Link></p>
            {operator.length===0?<p className="mt-5 text-sm text-slate-500">Não há pedidos a encaminhar.</p>:
            <div className="grid md:grid-cols-2 gap-3 mt-5">{operator.map(item=><article key={item.id} className="bg-white border border-orange-100 rounded-xl p-4">
              <div className="flex gap-2 justify-between flex-wrap"><b>{item.public_reference}</b><span className="text-xs font-bold text-[#155EEF]">{stateLabel(item.status)}</span></div>
              <p className="mt-1 text-sm font-semibold">{item.storefront_name} · {item.owner_kind==="personal"?"Profissional":"Empresa"}</p>
              <p className="text-sm text-slate-600 mt-1">{item.listing_title||item.description}</p>
              <div className="text-sm text-slate-600 mt-2">Cliente: {item.customer_name}</div>
              <div className="flex flex-wrap gap-2 mt-4">
                {["submitted","triage"].includes(item.status)&&<button disabled={busy} className="bg-[#155EEF] text-white rounded-lg px-4 py-2 text-sm font-bold" onClick={()=>operatorAction(item,"route")}>Validar e encaminhar</button>}
                {item.status==="delivered"&&<button disabled={busy} className="bg-emerald-700 text-white rounded-lg px-4 py-2 text-sm font-bold" onClick={()=>operatorAction(item,"complete")}>Validar conclusão</button>}
              </div>
            </article>)}</div>}
          </section>}
        </>}
      </div>
    </section>

    <footer className="max-w-7xl mx-auto px-5 py-7 text-sm text-slate-500 flex flex-wrap justify-between gap-3"><span>© 2026 Vitaleevo Jobs</span><span>Intermediação e acompanhamento de serviços · <Link className="underline" href="/termos">Termos</Link> · <Link href="/privacidade" className="underline">Privacidade</Link></span></footer>

    {selected&&<Modal title={selected.storefront.name} description="Serviços disponíveis nesta vitrine." onClose={()=>setSelected(null)} maxWidth="xl">
      <p className="text-slate-600">{selected.storefront.description||"Conheça os serviços deste prestador."}</p>
      <p className="text-sm text-slate-500 mt-2">{[selected.storefront.municipality,selected.storefront.province].filter(Boolean).join(", ")}</p>
      <div className="mt-6 grid gap-3">{selected.listings.map(item=><article key={item.id} className="rounded-2xl border border-slate-200 p-4 flex flex-wrap gap-4 justify-between items-center">
        <div className="flex-1"><h3 className="font-black">{item.title}</h3><p className="text-sm text-slate-600 mt-1">{item.description}</p><p className="text-sm font-bold mt-2 text-[#155EEF]">{item.price_mode==="fixed"&&item.price_kz!==null?prices(item.price_kz):"Preço sob orçamento"}</p></div>
        <button className="rounded-xl bg-[#155EEF] px-4 py-2.5 text-white text-sm font-bold" onClick={()=>requestFor(selected.storefront,item)}>Solicitar</button>
      </article>)}</div>
      {selected.listings.length===0&&<p className="mt-5 text-sm text-slate-500">Ainda não há serviços catalogados. Pode solicitar uma proposta geral.</p>}
      <button className="mt-6 rounded-xl border border-slate-200 px-5 py-3 font-bold" onClick={()=>requestFor(selected.storefront)}>Pedir orçamento geral</button>
    </Modal>}

    {requestShop&&<Modal title={submitted?"Pedido recebido":"Solicitar serviço"} description={requestShop.name+(requestListing?" · "+requestListing.title:" · Pedido geral")} onClose={()=>{setRequestShop(null);setError("");}} maxWidth="lg">
      {submitted?<div role="status" className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
        <h3 className="text-xl font-black text-emerald-900">Pedido registado!</h3>
        <p className="text-emerald-800 mt-2">Guarde a referência e o número de telefone utilizado para consultar o estado.</p>
        <div className="mt-5 rounded-xl bg-white border border-emerald-100 text-center text-2xl font-black tracking-wider p-4">{submitted.reference}</div>
        <button className="mt-5 rounded-xl bg-[#155EEF] px-5 py-3 text-white font-bold w-full" onClick={()=>{setRequestShop(null);setTracking(null);setShowTracking(true);}}>Acompanhar este pedido</button>
      </div>:<form onSubmit={sendRequest} className="grid gap-4">
        {error&&<p role="alert" className="rounded-lg bg-red-50 text-red-800 p-3 text-sm">{error}</p>}
        <label className="text-sm font-bold">Categoria de serviço<select name="category_id" required className="field mt-2" defaultValue=""><option value="" disabled>Escolha uma categoria</option>{categories.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}</select></label>
        {categories.length===0&&<p className="text-sm text-amber-700">O catálogo de categorias ainda está em preparação. O formulário será disponibilizado quando existir uma categoria activa.</p>}
        <label className="text-sm font-bold">Nome completo<input name="customer_name" autoComplete="name" maxLength={160} required className="field mt-2" placeholder="Nome do cliente"/></label>
        <div className="grid sm:grid-cols-2 gap-3">
          <label className="text-sm font-bold">Telemóvel<input name="customer_phone" autoComplete="tel" maxLength={40} required className="field mt-2" placeholder="+244 9xx xxx xxx"/></label>
          <label className="text-sm font-bold">Email (opcional)<input name="customer_email" type="email" autoComplete="email" className="field mt-2" placeholder="email@exemplo.com"/></label>
        </div>
        <label className="text-sm font-bold">Descreva o que precisa<textarea name="description" required minLength={10} maxLength={5000} rows={4} className="field mt-2" placeholder="Local, serviço pretendido e outros detalhes importantes"/></label>
        <label className="flex items-start gap-3 text-sm text-slate-600"><input name="consent" type="checkbox" required className="mt-1"/><span>Autorizo o tratamento dos dados necessários ao contacto e à gestão deste pedido, conforme a <Link className="underline" href="/privacidade" target="_blank">Política de Privacidade</Link>.</span></label>
        <div className="rounded-xl bg-blue-50 p-4 text-sm text-blue-900">Não há cobrança nesta etapa. A Vitaleevo irá validar o pedido antes de encaminhar o serviço.</div>
        <button disabled={busy||categories.length===0} className="rounded-xl bg-[#155EEF] px-5 py-3 font-black text-white disabled:opacity-50">{busy?"A enviar…":"Enviar pedido"}</button>
      </form>}
    </Modal>}

    {showTracking&&<Modal title="Acompanhar pedido" description="Consulte o estado com a referência e o telefone do pedido." onClose={()=>{setShowTracking(false);setError("");}} maxWidth="md">
      <form className="grid gap-4" onSubmit={track}>
        {error&&<p role="alert" className="rounded-xl bg-red-50 text-red-700 p-3 text-sm">{error}</p>}
        <label className="text-sm font-bold">Referência<input name="reference" required maxLength={40} placeholder="JB-XXXXXXXXXX" className="field mt-2" defaultValue={submitted?.reference||""}/></label>
        <label className="text-sm font-bold">Telemóvel utilizado<input name="customer_phone" required maxLength={40} className="field mt-2" placeholder="Número indicado no pedido"/></label>
        <button disabled={busy} className="rounded-xl bg-[#155EEF] text-white font-bold py-3">{busy?"A consultar…":"Consultar estado"}</button>
        {tracking&&<div role="status" className="border border-blue-100 rounded-xl bg-blue-50 p-5"><div className="font-bold">{tracking.reference}</div><p className="text-lg font-black mt-2">{stateLabel(tracking.status)}</p></div>}
      </form>
    </Modal>}

    {showApply&&<Modal title="Perfil profissional" description="Registe a sua especialidade para validação pela equipa Vitaleevo." onClose={()=>{setShowApply(false);setError("");}} maxWidth="md">
      <form onSubmit={apply} className="grid gap-4">
        {error&&<p role="alert" className="rounded-xl bg-red-50 text-red-700 p-3 text-sm">{error}</p>}
        {profile&&<div className="text-sm rounded-xl bg-blue-50 text-blue-900 p-3">Validação actual: <b>{stateLabel(profile.verification_state)}</b>. Alterar dados da candidatura pode exigir nova verificação.</div>}
        <label className="text-sm font-bold">Especialidade<input name="specialty" maxLength={160} required defaultValue={profile?.specialty||""} className="field mt-2" placeholder="Ex.: Fotografia, electricidade, CCTV"/></label>
        <label className="text-sm font-bold">Telemóvel profissional<input name="phone" required maxLength={40} defaultValue={profile?.phone||""} className="field mt-2" placeholder="+244 9xx xxx xxx"/></label>
        <p className="text-xs text-slate-500">A publicação da vitrine não significa que o perfil esteja validado. A distribuição de trabalhos exige aprovação.</p>
        <button disabled={busy} className="rounded-xl bg-[#155EEF] px-5 py-3 font-bold text-white">{busy?"A guardar…":"Submeter candidatura"}</button>
      </form>
    </Modal>}
  </main>;
}
