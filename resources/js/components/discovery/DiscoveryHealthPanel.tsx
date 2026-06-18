import React, { useEffect, useMemo, useState } from 'react';
import { Activity, Loader2, Lock } from 'lucide-react';
import { fetchDiscoveryHealth, type DiscoveryHealthPayload } from '../../lib/discoveryHealth';

type HealthState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryHealthPayload };

const METRIC_LABELS: Record<keyof DiscoveryHealthPayload['metrics'], string> = {
  total_visible_torrents: 'Visible Torrents',
  torrents_with_core_metadata: 'Core Metadata Complete',
  missing_core_metadata_torrents: 'Missing Core Metadata',
  discovery_ready_torrents: 'Discovery Ready',
  weakly_discoverable_torrents: 'Weakly Discoverable',
  discovery_readiness_rate: 'Discovery Readiness',
};

function formatMetric(key: keyof DiscoveryHealthPayload['metrics'], value: number): string {
  if (key === 'discovery_readiness_rate') {
    return `${Math.round(value * 100)}%`;
  }

  return value.toLocaleString();
}

export default function DiscoveryHealthPanel(): React.ReactElement {
  const [state, setState] = useState<HealthState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchDiscoveryHealth()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', message: 'Discovery health is temporarily unavailable.' });
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

    if (state.payload.metrics.total_visible_torrents === 0) {
      return (
        <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">
          No discovery health data available.
        </div>
      );
    }

    const metricEntries = Object.entries(state.payload.metrics) as [keyof DiscoveryHealthPayload['metrics'], number][];

    return (
      <div className="space-y-4">
        {state.payload.indicators.has_weakly_discoverable_torrents && (
          <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm leading-6 text-amber-100">
            Weak discovery state detected: some visible torrents are missing enough structured metadata for reliable discovery.
          </div>
        )}

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {metricEntries.map(([key, value]) => (
            <div key={key} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">{METRIC_LABELS[key]}</dt>
              <dd className="mt-2 text-2xl font-semibold text-white">{formatMetric(key, value)}</dd>
            </div>
          ))}
        </div>

        <div className="overflow-hidden rounded-xl border border-slate-800 bg-slate-950/40">
          <div className="border-b border-slate-800 px-3 py-2">
            <h3 className="text-sm font-semibold text-slate-100">Metadata Coverage</h3>
            <p className="mt-1 text-xs text-slate-500">Core metadata field coverage across visible torrents.</p>
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
                {state.payload.metadata_coverage.map((coverage) => (
                  <tr key={coverage.field}>
                    <td className="px-3 py-2 font-medium text-slate-100">{coverage.label}</td>
                    <td className="px-3 py-2">{coverage.covered.toLocaleString()} / {coverage.total.toLocaleString()}</td>
                    <td className="px-3 py-2">{coverage.missing.toLocaleString()}</td>
                    <td className="px-3 py-2">{Math.round(coverage.coverage_rate * 100)}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    );
  }, [state]);

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <Activity className="h-4 w-4" aria-hidden="true" /> Discovery Health
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Discovery operations intelligence</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly catalog health for metadata-first discovery, highlighting readiness, weak discoverability, and core field coverage.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" /> Loading discovery health...
          </div>
        )}

        {state.status === 'error' && (
          <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status" aria-live="polite">{state.message}</p>
        )}

        {state.status === 'ready' && content}
      </div>
    </section>
  );
}
