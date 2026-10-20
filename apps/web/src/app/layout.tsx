import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Vitaleevo Ecosystem",
  description: "CRM, serviços e operação comercial num único ambiente.",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="pt">
      <body>{children}</body>
    </html>
  );
}
