"use client";

import Link from "next/link";
import { FormEvent, useEffect, useRef, useState } from "react";

const API = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

type Category = { id: number; name: string };
type Result = { reference: string; status: string; updated_at?: string };

async function csrf() {
  await fetch(`${API}/sanctum/csrf-cookie`, { credentials: "include" });
}

function cookie(name: string) {
  const hit = document.cookie.split("; ").find((value) => value.startsWith(`${name}=`));
  return hit ? decodeURIComponent(hit.split("=").slice(1).join("=")) : "";
}

async function post<T>(path: string, body: object, idempotencyKey?: string): Promise<T> {
  await csrf();
  const response = await fetch(`${API}/api/v1${path}`, {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-XSRF-TOKEN": cookie("XSRF-TOKEN"),
      ...(idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {}),
    },
    body: JSON.stringify(body),
  });
  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: "Não foi possível concluir o pedido." }));
    throw new Error(error.message || "Não foi possível concluir o pedido.");
  }
  return response.json();
}

export default function PedidoPage() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [result, setResult] = useState<Result | null>(null);
  const [statusResult, setStatusResult] = useState<Result | null>(null);
  const [error, setError] = useState("");
  const requestKey = useRef<string | null>(null);

  useEffect(() => {
    fetch(`${API}/api/v1/service-categories`, { headers: { Accept: "application/json" } })
      .then((response) => response.json())
      .then(setCategories)
      .catch(() => setError("Não foi possível carregar as categorias."));
  }, []);

  async function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError("");
    const data = Object.fromEntries(new FormData(e.currentTarget).entries());
    try {
      requestKey.current ||= globalThis.crypto.randomUUID();
      const response = await post<Result>("/public/service-requests", {
        ...data,
        category_id: Number(data.category_id),
        consent: true,
      }, requestKey.current);
      setResult(response);
      requestKey.current = null;
      e.currentTarget.reset();
    } catch (err) {
      setError((err as Error).message);
    }
  }

  async function checkStatus(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError("");
    try {
      const data = Object.fromEntries(new FormData(e.currentTarget).entries());
      setStatusResult(await post<Result>("/public/service-requests/status", data));
    } catch (err) {
      setError((err as Error).message);
    }
  }

  return (
    <main className="min-h-screen bg-slate-950 text-white p-5 md:p-10">
      <div className="max-w-6xl mx-auto">
        <Link href="/" className="font-black text-xl">VITALEEVO</Link>
        <div className="grid lg:grid-cols-[1.3fr_.7fr] gap-6 mt-10">
          <section className="bg-white text-slate-950 rounded-3xl p-6 md:p-10">
            <p className="text-sm font-bold text-slate-500">SOLICITAR SERVIÇO</p>
            <h1 className="text-4xl font-black mt-2">Diga-nos o que precisa.</h1>
            <p className="text-slate-500 mt-3 mb-7">A equipa Vitaleevo recebe o pedido, faz a triagem e encaminha para a operação adequada.</p>
            {error && <div className="mb-5 p-3 rounded-xl bg-red-50 text-red-700">{error}</div>}
            {result && <div className="mb-5 p-4 rounded-xl bg-emerald-50 text-emerald-800"><b>Pedido recebido.</b><br />Referência: <b>{result.reference}</b></div>}
            <form onSubmit={submit} className="grid md:grid-cols-2 gap-4">
              <input className="field" name="customer_name" placeholder="Nome" required />
              <input className="field" name="customer_phone" placeholder="Telefone" required />
              <input className="field" name="customer_email" type="email" placeholder="Email (opcional)" />
              <select className="field" name="category_id" required><option value="">Categoria</option>{categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>
              <textarea className="field md:col-span-2 min-h-36" name="description" placeholder="Descreva o serviço de que precisa" required />
              <label className="md:col-span-2 text-sm text-slate-600"><input type="checkbox" required className="mr-2" />Autorizo o tratamento dos meus dados para responder a este pedido.</label>
              <button className="btn md:col-span-2">Enviar pedido</button>
            </form>
          </section>
          <aside className="rounded-3xl border border-slate-800 p-6 md:p-8 h-fit">
            <p className="text-sm font-bold text-slate-400">ACOMPANHAR PEDIDO</p>
            <h2 className="text-2xl font-black mt-2">Consulte o estado.</h2>
            <form onSubmit={checkStatus} className="space-y-3 mt-5">
              <input className="field text-slate-950" name="reference" placeholder="Ex.: SR-XXXXXXXXXX" required />
              <input className="field text-slate-950" name="customer_phone" placeholder="Mesmo telefone do pedido" required />
              <button className="btn w-full bg-white !text-slate-950">Consultar</button>
            </form>
            {statusResult && <div className="mt-5 p-4 bg-slate-900 rounded-xl"><div className="text-slate-400 text-sm">{statusResult.reference}</div><div className="text-xl font-black mt-1">{statusResult.status}</div></div>}
          </aside>
        </div>
      </div>
    </main>
  );
}
