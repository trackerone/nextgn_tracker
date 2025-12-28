import { useEffect, useState } from 'react';
import { Activity } from 'lucide-react';
import type { TorrentDto } from '@nextgn/api-contract';
import { apiClient } from './api/client';

type HealthResponse = {
  status: string;
  message?: string;
};

const healthPath = '/api/health';
// TODO: Switch to /api/v1/health if the backend uses versioned health endpoints.

const sampleTorrent: TorrentDto = {
  id: 1,
  name: 'Sample Torrent',
  status: 'approved',
};

export default function App() {
  const [health, setHealth] = useState<HealthResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;

    apiClient<HealthResponse>(healthPath)
      .then((data) => {
        if (active) {
          setHealth(data);
        }
      })
      .catch((err: Error) => {
        if (active) {
          setError(err.message);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  return (
    <div className="min-h-screen px-6 py-10">
      <header className="mx-auto flex max-w-3xl items-center gap-3">
        <Activity className="h-8 w-8 text-emerald-400" />
        <div>
          <h1 className="text-2xl font-semibold">NextGN Web</h1>
          <p className="text-sm text-slate-400">Variant B: Frontend + API contract</p>
        </div>
      </header>

      <main className="mx-auto mt-10 max-w-3xl space-y-6 rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
        <section className="space-y-2">
          <h2 className="text-lg font-semibold">API Health</h2>
          {error && <p className="text-sm text-red-400">Error: {error}</p>}
          {!error && !health && <p className="text-sm text-slate-400">Loading...</p>}
          {health && (
            <p className="text-sm text-emerald-300">
              Status: {health.status} {health.message ? `- ${health.message}` : ''}
            </p>
          )}
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-semibold">Contract Preview</h2>
          <p className="text-sm text-slate-400">Sample DTO from @nextgn/api-contract:</p>
          <pre className="overflow-x-auto rounded-lg bg-slate-950 p-4 text-xs text-slate-200">
            {JSON.stringify(sampleTorrent, null, 2)}
          </pre>
        </section>
      </main>
    </div>
  );
}
