import React, { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, Loader2, Lock } from 'lucide-react';
import { fetchDiscoveryOperationsPriorities, type DiscoveryOperationsPrioritiesPayload } from '../../lib/discoveryOperationsPriorities';

type PrioritiesState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryOperationsPrioritiesPayload };

export default function DiscoveryOperationsPrioritiesPanel(): React.ReactElement {
  const [state, setState] = useState<PrioritiesState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchDiscoveryOperationsPriorities()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', message: 'Discovery operations priorities are temporarily unavailable.' });
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const content = useMemo(() => {
    if (state.status !== 'ready') {
      return null;
    }

    if (state.payload.priorities.length === 0) {
      return <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">No discovery priorities available.</div>;
    }

    return (
      <div className="space-y-3">
        {state.payload.priorities.map((priority) => (
          <article key={priority.type} className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <span className="text-xs font-semibold uppercase tracking-wide text-brand">{priority.severity}</span>
                <h3 className="mt-1 text-base font-semibold text-white">{priority.title}</h3>
              </div>
              <span className="w-fit rounded-full border border-slate-700 bg-slate-900/70 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">{priority.type.replaceAll('_', ' ')}</span>
            </div>
            <p className="mt-3 text-sm leading-6 text-slate-300">{priority.message}</p>
            <p className="mt-2 text-sm leading-6 text-slate-400">{priority.reason}</p>

            <div className="mt-4 grid gap-3 lg:grid-cols-3">
              <div>
                <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Affected Fields</h4>
                {priority.affected_fields.length === 0 ? (
                  <p className="mt-2 text-sm text-slate-400">Discovery condition is healthy.</p>
                ) : (
                  <ul className="mt-2 flex flex-wrap gap-2">
                    {priority.affected_fields.map((field) => (
                      <li key={field.field} className="rounded-full border border-slate-700 bg-slate-900/70 px-2.5 py-1 text-xs font-semibold text-slate-200">
                        {field.label} <span className="text-slate-500">{field.missing.toLocaleString()} missing</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>

              <div>
                <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Example Torrents</h4>
                {priority.example_torrents.length === 0 ? (
                  <p className="mt-2 text-sm text-slate-400">No discovery priorities available.</p>
                ) : (
                  <ul className="mt-2 space-y-2 text-sm text-slate-300">
                    {priority.example_torrents.map((torrent) => (
                      <li key={torrent.torrent_id} className="rounded-lg border border-slate-800 bg-slate-900/60 p-2">
                        <span className="font-medium text-slate-100">{torrent.torrent_name}</span>
                        <p className="mt-1 text-xs text-slate-500">{torrent.discovery_status.replaceAll('_', ' ')}</p>
                      </li>
                    ))}
                  </ul>
                )}
              </div>

              <div>
                <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500">Recommended Staff Action</h4>
                <p className="mt-2 text-sm leading-6 text-slate-300">{priority.recommended_staff_action}</p>
              </div>
            </div>
          </article>
        ))}
      </div>
    );
  }, [state]);

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <AlertTriangle className="h-4 w-4" aria-hidden="true" /> Discovery Operations Priorities
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Discovery Operations Priorities</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly priority list showing what discovery staff should review first, derived from the operations overview.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" /> Loading discovery operations priorities...
          </div>
        )}
        {state.status === 'error' && <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status" aria-live="polite">{state.message}</p>}
        {state.status === 'ready' && content}
      </div>
    </section>
  );
}
