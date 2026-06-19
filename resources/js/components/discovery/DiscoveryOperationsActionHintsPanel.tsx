import React, { useEffect, useState } from 'react';
import { Lightbulb, Loader2, Lock } from 'lucide-react';
import {
  fetchDiscoveryOperationsActionHints,
  type DiscoveryOperationsActionHintDiscoveryStatus,
  type DiscoveryOperationsActionHintMetadataField,
  type DiscoveryOperationsActionHintPriorityType,
  type DiscoveryOperationsActionHintsResponse,
} from '../../lib/discoveryOperationsActionHints';

type ActionHintsState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryOperationsActionHintsResponse };

const label = (value: string): string => value.replaceAll('_', ' ');

export default function DiscoveryOperationsActionHintsPanel(): React.ReactElement {
  const [field, setField] = useState<DiscoveryOperationsActionHintMetadataField | ''>('');
  const [status, setStatus] = useState<DiscoveryOperationsActionHintDiscoveryStatus | ''>('');
  const [priority, setPriority] = useState<DiscoveryOperationsActionHintPriorityType | ''>('');
  const [state, setState] = useState<ActionHintsState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    setState({ status: 'loading' });
    void fetchDiscoveryOperationsActionHints({ field, status, priority })
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', message: 'Discovery operations action hints are temporarily unavailable or the selected filter is invalid.' });
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
            <Lightbulb className="h-4 w-4" aria-hidden="true" /> Discovery Operations Action Hints
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Discovery Operations Action Hints</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly hints explain the manual staff action recommended for discovery metadata issues. No metadata mutation or fixing is allowed here.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly · mutation not allowed
        </span>
      </div>

      <div className="mt-4 grid gap-3 md:grid-cols-3">
        <label className="text-sm font-medium text-slate-300">
          Field selector
          <select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={field} onChange={(event) => setField(event.target.value as DiscoveryOperationsActionHintMetadataField | '')}>
            <option value="">All fields</option>
            {(filters?.available_fields ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
          </select>
        </label>
        <label className="text-sm font-medium text-slate-300">
          Status selector
          <select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={status} onChange={(event) => setStatus(event.target.value as DiscoveryOperationsActionHintDiscoveryStatus | '')}>
            <option value="">All statuses</option>
            {(filters?.available_statuses ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
          </select>
        </label>
        <label className="text-sm font-medium text-slate-300">
          Priority selector
          <select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={priority} onChange={(event) => setPriority(event.target.value as DiscoveryOperationsActionHintPriorityType | '')}>
            <option value="">All priorities</option>
            {(filters?.available_priorities ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}
          </select>
        </label>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400"><Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> Loading discovery operations action hints...</div>}
        {state.status === 'error' && <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status">{state.message}</p>}
        {state.status === 'ready' && (
          <div className="space-y-4">
            <div className="grid gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300 sm:grid-cols-3">
              <span>Total hints: <strong className="text-white">{state.payload.summary.total_hints}</strong></span>
              <span>Highest severity: <strong className="text-white">{state.payload.summary.highest_severity ? label(state.payload.summary.highest_severity) : 'none'}</strong></span>
              <span>Recommended staff focus: <strong className="text-white">{state.payload.summary.recommended_staff_focus}</strong></span>
            </div>
            {state.payload.action_hints.length === 0 ? (
              <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">No action hints match the selected filters.</div>
            ) : (
              <div className="grid gap-3 md:grid-cols-2">
                {state.payload.action_hints.map((hint) => (
                  <article key={hint.id} className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <h3 className="font-semibold text-white">{hint.title}</h3>
                      <span className="rounded-full border border-slate-700 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-300">Severity: {label(hint.severity)}</span>
                    </div>
                    <p className="mt-2 leading-6">{hint.description}</p>
                    <p className="mt-3"><strong className="text-slate-100">Recommended staff action:</strong> {hint.recommended_staff_action}</p>
                    <p className="mt-2"><strong className="text-slate-100">Reason:</strong> {hint.reason}</p>
                    <p className="mt-3 rounded-lg border border-slate-800 bg-slate-900/80 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Readonly: {hint.readonly ? 'true' : 'false'} · mutation not allowed: {hint.mutation_allowed ? 'false' : 'true'}</p>
                  </article>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  );
}
