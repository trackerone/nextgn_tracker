import '../css/app.css';
import { Activity } from 'lucide-react';
import React from 'react';
import ReactDOM from 'react-dom/client';

const App: React.FC = () => {
  return (
    <main className="min-h-screen bg-slate-950 text-slate-50">
      <section className="mx-auto flex w-full max-w-3xl flex-col items-center gap-6 px-6 py-24 text-center">
        <span className="inline-flex items-center gap-2 rounded-full border border-slate-800 bg-slate-900/60 px-4 py-1 text-sm font-medium text-slate-300">
          <Activity className="h-4 w-4 text-brand" />
          NextGN Tracker baseline ready
        </span>
        <h1 className="text-4xl font-semibold sm:text-5xl">Laravel 11 + Vite + Tailwind + shadcn/ui</h1>
        <p className="max-w-2xl text-lg text-slate-300">
          Frontend assets are powered by Vite with Tailwind CSS and shadcn/ui foundations. Start building your product from this secure baseline.
        </p>
      </section>
    </main>
  );
};

const element = document.getElementById('app');

if (!element) {
  throw new Error('App root element not found');
}

ReactDOM.createRoot(element).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
