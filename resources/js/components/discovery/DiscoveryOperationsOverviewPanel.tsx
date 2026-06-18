import React, { useEffect, useMemo, useState } from 'react';
import { ClipboardList, Loader2, Lock } from 'lucide-react';
import { fetchDiscoveryOperationsOverview, type DiscoveryOperationsOverviewPayload } from '../../lib/discoveryOperationsOverview';

type OperationsState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryOperationsOverviewPayload };

const SUMMARY_LABELS: Record<keyof DiscoveryOperationsOverviewPayload['summary'], string> = {
  total_visible_torrents: 'Visible Torrents',
  discovery_ready_torrents: 'Discovery Ready',
  weakly_discoverable_torrents: 'Weakly Discoverable',
  missing_core_metadata_torrents: 'Missing Core Metadata',
  discovery_readiness_rate: 'Readiness Rate',
};

function formatSummary(key: keyof DiscoveryOperationsOverviewPayload['summary'], value: number): string {
  if (key === 'discovery_readiness_rate') {
    return `${Math.round(value * 100)}%`;
  }

  return value.toLocaleString();
}

export default function DiscoveryOperationsOverviewPanel(): React.ReactElement {
  const [state, setState] = useState<OperationsState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchDiscoveryOperationsOverview()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', message: 'Discovery operations overview is temporarily unavailable.' });
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

    if (state.payload.summary.total_visible_torrents === 0) {
      return <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">No discovery operations data available.</div>;
    }

    const summaryEntries = Object.entries(state.payload.summary) as [keyof DiscoveryOperationsOverviewPayload['summary'], number][];

    return (
      <div className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {summaryEntries.map(([key, value]) => (
            <div key={key} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{SUMMARY_LABELS[key]}</dt>
              <dd className="mt-2 text-2xl font-semibold text-white">{formatSummary(key, value)}</dd>
            </div>
          ))}
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <div className="overflow-hidden rounded-xl border border-slate-800 bg-slate-950/40">
            <div className="border-b border-slate-800 px-3 py-2">
              <h3 className="text-sm font-semibold text-slate-100">Weakest Metadata Fields</h3>
              <p className="mt-1 text-xs text-slate-500">Lowest coverage fields from discovery health metadata coverage.</p>
            </div>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-slate-800 text-sm">
                <thead className="bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <tr>
                    <th className="px-3 py-2">Field</th>
                    <th className="px-3 py-2">Covered</th>
                    <th className="px-3 py-2">Missing</th>
                    <th className="px-3 py-2">Coverage</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800 text-slate-300">
                  {state.payload.weakest_metadata_fields.map((field) => (
                    <tr key={field.field}>
                      <td className="px-3 py-2 font-medium text-slate-100">{field.label}</td>
                      <td className="px-3 py-2">{field.covered.toLocaleString()}</td>
                      <td className="px-3 py-2">{field.missing.toLocaleString()}</td>
                      <td className="px-3 py-2">{Math.round(field.coverage_rate * 100)}%</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
            <h3 className="text-sm font-semibold text-slate-100">Attention Items</h3>
            {state.payload.attention_items.length === 0 ? (
              <p className="mt-3 text-sm text-slate-400">No immediate discovery operations attention items.</p>
            ) : (
              <ul className="mt-3 space-y-2 text-sm text-slate-300">
                {state.payload.attention_items.map((item) => (
                  <li key={`${item.type}-${item.message}`} className="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                    <span className="text-xs font-semibold uppercase tracking-wide text-brand">{item.severity}</span>
                    <p className="mt-1 leading-6">{item.message}</p>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
          <h3 className="text-sm font-semibold text-slate-100">Sample Explanations</h3>
          {state.payload.sample_explanations.length === 0 ? (
            <p className="mt-3 text-sm text-slate-400">No sample explanations available.</p>
          ) : (
            <div className="mt-3 grid gap-3 lg:grid-cols-2">
              {state.payload.sample_explanations.map((explanation) => (
                <article key={explanation.torrent_id} className="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                  <h4 className="text-sm font-semibold text-white">{explanation.torrent_name}</h4>
                  <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-brand">{explanation.discovery_status.replaceAll('_', ' ')}</p>
                  <p className="mt-2 text-sm leading-6 text-slate-300">{explanation.discovery_summary}</p>
                </article>
              ))}
            </div>
          )}
        </div>
      </div>
    );
  }, [state]);

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <ClipboardList className="h-4 w-4" aria-hidden="true" /> Discovery Operations Overview
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Discovery Operations Overview</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly operations control surface combining discovery health, metadata gaps, and sample explainability signals.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" /> Loading discovery operations overview...
          </div>
        )}
        {state.status === 'error' && <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status" aria-live="polite">{state.message}</p>}
        {state.status === 'ready' && content}
      </div>
    </section>
  );
}
