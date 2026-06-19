import React, { useEffect, useState } from 'react';
import { Loader2, Lock, Search } from 'lucide-react';
import {
  fetchDiscoveryOperationsDrilldown,
  type DiscoveryOperationsDrilldownResponse,
  type DiscoveryOperationsDrilldownStatus,
  type DiscoveryOperationsMetadataField,
  type DiscoveryOperationsPriorityType,
} from '../../lib/discoveryOperationsDrilldown';

type DrilldownState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryOperationsDrilldownResponse };

const label = (value: string): string => value.replaceAll('_', ' ');

export default function DiscoveryOperationsDrilldownPanel(): React.ReactElement {
  const [field, setField] = useState<DiscoveryOperationsMetadataField | ''>('');
  const [status, setStatus] = useState<DiscoveryOperationsDrilldownStatus | ''>('');
  const [priority, setPriority] = useState<DiscoveryOperationsPriorityType | ''>('');
  const [state, setState] = useState<DrilldownState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    setState({ status: 'loading' });
    void fetchDiscoveryOperationsDrilldown({ field, status, priority })
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', message: 'Discovery operations drilldown is temporarily unavailable or the selected filter is invalid.' });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [field, status, priority]);

  const filters = state.status === 'ready' ? state.payload.filters : null;

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <Search className="h-4 w-4" aria-hidden="true" /> Discovery Operations Drilldown
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Discovery Operations Drilldown</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly drilldown for affected torrents, missing metadata, explanations, and recommended staff action.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly
        </span>
      </div>

      <div className="mt-4 grid gap-3 md:grid-cols-3">
        <label className="text-sm font-medium text-slate-300">
          Field selector
          <select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={field} onChange={(event) => setField(event.target.value as DiscoveryOperationsMetadataField | '')}>
            <option value="">All fields</option>
            {(filters?.available_fields ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
          </select>
        </label>
        <label className="text-sm font-medium text-slate-300">
          Status selector
          <select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={status} onChange={(event) => setStatus(event.target.value as DiscoveryOperationsDrilldownStatus | '')}>
            <option value="">All statuses</option>
            {(filters?.available_statuses ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
          </select>
        </label>
        <label className="text-sm font-medium text-slate-300">
          Priority selector
          <select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={priority} onChange={(event) => setPriority(event.target.value as DiscoveryOperationsPriorityType | '')}>
            <option value="">All priorities</option>
            {(filters?.available_priorities ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
          </select>
        </label>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400"><Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> Loading discovery operations drilldown...</div>}
        {state.status === 'error' && <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status">{state.message}</p>}
        {state.status === 'ready' && (
          <div className="space-y-4">
            <div className="grid gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300 sm:grid-cols-4">
              <span>Affected torrents: <strong className="text-white">{state.payload.summary.total_matching_torrents}</strong></span>
              <span>Missing: <strong className="text-white">{state.payload.summary.missing_count}</strong></span>
              <span>Present: <strong className="text-white">{state.payload.summary.present_count}</strong></span>
              <span>Recommended staff action: <strong className="text-white">{state.payload.summary.recommended_staff_action}</strong></span>
            </div>
            {state.payload.rows.length === 0 ? (
              <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">No affected torrents match the selected drilldown filters.</div>
            ) : (
              <div className="overflow-x-auto rounded-xl border border-slate-800">
                <table className="min-w-full divide-y divide-slate-800 text-left text-sm">
                  <thead className="bg-slate-950/70 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th className="px-3 py-2">Affected torrents</th><th className="px-3 py-2">Field</th><th className="px-3 py-2">Status</th><th className="px-3 py-2">Explanation</th><th className="px-3 py-2">Recommended staff action</th></tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800 bg-slate-950/30 text-slate-300">
                    {state.payload.rows.map((row) => (
                      <tr key={`${row.torrent_id}-${row.metadata_field}`}>
                        <td className="px-3 py-3 font-medium text-slate-100">{row.torrent_name}</td>
                        <td className="px-3 py-3">{label(row.metadata_field)}</td>
                        <td className="px-3 py-3">{label(row.discovery_status)}</td>
                        <td className="px-3 py-3">{row.explanation}</td>
                        <td className="px-3 py-3">{row.recommended_staff_action}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  );
}
