import React, { useEffect, useState } from 'react';
import {
  fetchDiscoveryOperationsCommandCenter,
  type DiscoveryOperationsCommandCenterDiscoveryStatus,
  type DiscoveryOperationsCommandCenterMetadataField,
  type DiscoveryOperationsCommandCenterPriorityType,
  type DiscoveryOperationsCommandCenterResponse,
  type DiscoveryOperationsCommandCenterSeverity,
} from '../../lib/discoveryOperationsCommandCenter';

type State = { status: 'loading' } | { status: 'error'; message: string } | { status: 'ready'; payload: DiscoveryOperationsCommandCenterResponse };

const label = (value: string): string => value.replaceAll('_', ' ');

export default function DiscoveryOperationsCommandCenterPanel(): React.ReactElement {
  const [field, setField] = useState<DiscoveryOperationsCommandCenterMetadataField | ''>('');
  const [status, setStatus] = useState<DiscoveryOperationsCommandCenterDiscoveryStatus | ''>('');
  const [priority, setPriority] = useState<DiscoveryOperationsCommandCenterPriorityType | ''>('');
  const [severity, setSeverity] = useState<DiscoveryOperationsCommandCenterSeverity | ''>('');
  const [state, setState] = useState<State>({ status: 'loading' });

  useEffect(() => {
    setState({ status: 'loading' });
    void fetchDiscoveryOperationsCommandCenter({ field, status, priority, severity })
      .then((payload) => setState({ status: 'ready', payload }))
      .catch(() => setState({ status: 'error', message: 'Discovery Operations Command Center is temporarily unavailable or the selected filter is invalid.' }));
  }, [field, status, priority, severity]);

  const filters = state.status === 'ready' ? state.payload.filters : null;

  return <section className="rounded-3xl border border-slate-800 bg-slate-950/80 p-6 shadow-sm">
    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
      <div><p className="text-xs font-semibold uppercase tracking-wide text-brand">Slice 100</p><h2 className="text-2xl font-semibold text-slate-50">Discovery Operations Command Center</h2><p className="mt-2 text-sm text-slate-400">Readonly consolidated health, priorities, action hints, and review queue preview.</p></div>
      <span className="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">Readonly · mutation not allowed</span>
    </div>
    <div className="mt-5 grid gap-3 md:grid-cols-4">
      <label className="text-sm font-medium text-slate-300">Field selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={field} onChange={(event) => setField(event.target.value as DiscoveryOperationsCommandCenterMetadataField | '')}><option value="">All fields</option>{(filters?.available_fields ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
      <label className="text-sm font-medium text-slate-300">Status selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={status} onChange={(event) => setStatus(event.target.value as DiscoveryOperationsCommandCenterDiscoveryStatus | '')}><option value="">All statuses</option>{(filters?.available_statuses ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
      <label className="text-sm font-medium text-slate-300">Priority selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={priority} onChange={(event) => setPriority(event.target.value as DiscoveryOperationsCommandCenterPriorityType | '')}><option value="">All priorities</option>{(filters?.available_priorities ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
      <label className="text-sm font-medium text-slate-300">Severity selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={severity} onChange={(event) => setSeverity(event.target.value as DiscoveryOperationsCommandCenterSeverity | '')}><option value="">All severities</option>{(filters?.available_severities ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
    </div>
    {state.status === 'loading' && <p className="mt-4 text-sm text-slate-400">Loading command center...</p>}
    {state.status === 'error' && <p className="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">{state.message}</p>}
    {state.status === 'ready' && <div className="mt-5 space-y-5">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{[['Visible torrents', state.payload.summary.total_visible_torrents], ['Readiness rate', `${Math.round(state.payload.summary.discovery_readiness_rate * 100)}%`], ['Priorities', state.payload.summary.total_priorities], ['Queue items', state.payload.summary.total_queue_items]].map(([title, value]) => <div key={title} className="rounded-2xl border border-slate-800 bg-slate-900/70 p-4"><p className="text-xs uppercase tracking-wide text-slate-500">{title}</p><p className="mt-1 text-2xl font-semibold text-slate-50">{value}</p></div>)}</div>
      <article className="rounded-2xl border border-brand/30 bg-brand/10 p-4"><p className="text-xs font-semibold uppercase tracking-wide text-brand">Next staff focus</p><h3 className="mt-1 text-lg font-semibold text-slate-50">{state.payload.next_staff_focus.title}</h3><p className="mt-2 text-sm text-slate-300">Recommended staff action: {state.payload.next_staff_focus.recommended_staff_action}</p><p className="mt-1 text-xs text-slate-400">Severity: {state.payload.next_staff_focus.severity} · Source: {state.payload.next_staff_focus.source} · Reason: {state.payload.next_staff_focus.reason}</p></article>
      {state.payload.summary.total_queue_items === 0 && state.payload.summary.total_priorities === 0 && <p className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-100">Healthy/empty state: No immediate discovery operation issue.</p>}
      <div className="grid gap-5 lg:grid-cols-3"><div><h3 className="text-sm font-semibold uppercase tracking-wide text-slate-300">Top priorities</h3><ul className="mt-2 space-y-2">{state.payload.priorities.slice(0, 3).map((item) => <li key={item.type} className="rounded-xl border border-slate-800 p-3 text-sm text-slate-300"><strong className="text-slate-100">{item.title}</strong><br />{item.recommended_staff_action}</li>)}</ul></div><div><h3 className="text-sm font-semibold uppercase tracking-wide text-slate-300">Action hints</h3><ul className="mt-2 space-y-2">{state.payload.action_hints.slice(0, 4).map((item) => <li key={item.id} className="rounded-xl border border-slate-800 p-3 text-sm text-slate-300">{item.title}<br /><span className="text-xs text-slate-500">Readonly · mutation not allowed</span></li>)}</ul></div><div><h3 className="text-sm font-semibold uppercase tracking-wide text-slate-300">Review queue preview</h3>{state.payload.review_queue.length === 0 ? <p className="mt-2 rounded-xl border border-slate-800 p-3 text-sm text-slate-400">No review queue items match the selected filters.</p> : <ul className="mt-2 space-y-2">{state.payload.review_queue.slice(0, 5).map((item) => <li key={item.id} className="rounded-xl border border-slate-800 p-3 text-sm text-slate-300"><strong className="text-slate-100">{item.issue_title}</strong><br />Affected torrent: {item.torrent_name}<br />Metadata field: {label(item.metadata_field)}<br />Recommended staff action: {item.recommended_staff_action}</li>)}</ul>}</div></div>
    </div>}
  </section>;
}
