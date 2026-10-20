import path from "node:path";
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  cacheComponents: true,
  partialPrefetching: true,
  // Em Docker no Windows, os eventos de ficheiros do bind mount não chegam
  // ao watcher. Polling garante hot reload sem rebuild.
  watchOptions: {
    pollIntervalMs: 1000,
  },
  turbopack: {
    // Define a raiz explicitamente: o projeto está dentro do diretório home
    // (C:\Users\alexa\...), e sem isto o Turbopack procura a raiz até lá acima.
    root: path.resolve(__dirname),
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
