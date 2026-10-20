"use client";

import { ReactNode, useEffect, useId, useRef } from "react";

type Props = {
  title: string;
  description?: string;
  onClose: () => void;
  children: ReactNode;
  maxWidth?: "md" | "lg" | "xl";
};

export default function Modal({ title, description, onClose, children, maxWidth = "lg" }: Props) {
  const titleId = useId();
  const descriptionId = useId();
  const dialogRef = useRef<HTMLDivElement>(null);
  const closeRef = useRef(onClose);
  useEffect(() => { closeRef.current = onClose; }, [onClose]);

  useEffect(() => {
    const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    dialogRef.current?.querySelector<HTMLElement>('button, input, select, textarea, [tabindex="0"]')?.focus();
    function handleKey(event: KeyboardEvent) {
      if (event.key === "Escape") { event.preventDefault(); closeRef.current(); }
      if (event.key !== "Tab" || !dialogRef.current) return;
      const elements = [...dialogRef.current.querySelectorAll<HTMLElement>('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href], [tabindex="0"]')]
        .filter(item => item.getClientRects().length > 0);
      if (!elements.length) { event.preventDefault();return; }
      const first = elements[0];
      const last = elements[elements.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault();last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault();first.focus(); }
    }
    document.addEventListener("keydown", handleKey);
    return () => {
      document.removeEventListener("keydown", handleKey);
      document.body.style.overflow = previousOverflow;
      previousFocus?.focus();
    };
  }, []);

  const width = maxWidth === "xl" ? "max-w-3xl" : maxWidth === "md" ? "max-w-lg" : "max-w-2xl";
  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-[#102138]/70 p-3 md:p-6" onMouseDown={event => {
      if (event.target === event.currentTarget) onClose();
    }}>
      <div ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby={titleId}
        aria-describedby={description ? descriptionId : undefined}
        className={`w-full ${width} max-h-[min(90vh,900px)] overflow-y-auto rounded-3xl bg-white text-[#1D2939] shadow-2xl`}>
        <header className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-6 py-5 md:px-8">
          <div><h2 id={titleId} className="text-xl md:text-2xl font-black">{title}</h2>
            {description && <p id={descriptionId} className="mt-1 text-sm text-slate-600">{description}</p>}
          </div>
          <button type="button" onClick={onClose} aria-label="Fechar janela"
            className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 text-2xl text-slate-600 hover:bg-slate-50">×</button>
        </header>
        <div className="p-6 md:p-8">{children}</div>
      </div>
    </div>
  );
}
