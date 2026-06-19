import React, { useEffect, useState } from 'react';
import { ClipboardList, Loader2, Lock } from 'lucide-react';
import {
  fetchDiscoveryOperationsReviewQueue,
  type DiscoveryOperationsReviewQueueDiscoveryStatus,
  type DiscoveryOperationsReviewQueueMetadataField,
  type DiscoveryOperationsReviewQueuePriorityType,
  type DiscoveryOperationsReviewQueueResponse,
  type DiscoveryOperationsReviewQueueSeverity,
} from '../../lib/discoveryOperationsReviewQueue';

type ReviewQueueState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryOperationsReviewQueueResponse };

const label = (value: string): string => value.replaceAll('_', ' ');

export default function DiscoveryOperationsReviewQueuePanel(): React.ReactElement {
  const [field, setField] = useState<DiscoveryOperationsReviewQueueMetadataField | ''>('');
  const [status, setStatus] = useState<DiscoveryOperationsReviewQueueDiscoveryStatus | ''>('');
  const [priority, setPriority] = useState<DiscoveryOperationsReviewQueuePriorityType | ''>('');
  const [severity, setSeverity] = useState<DiscoveryOperationsReviewQueueSeverity | ''>('');
  const [state, setState] = useState<ReviewQueueState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    setState({ status: 'loading' });
    void fetchDiscoveryOperationsReviewQueue({ field, status, priority, severity })
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', message: 'Discovery operations review queue is temporarily unavailable or the selected filter is invalid.' });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [field, status, priority, severity]);

  const filters = state.status === 'ready' ? state.payload.filters : null;

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <ClipboardList className="h-4 w-4" aria-hidden="true" /> Discovery Operations Review Queue
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Discovery Operations Review Queue</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly staff review queue for metadata issues assembled from discovery operations drilldown, priorities, and action hints.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly · mutation not allowed
        </span>
      </div>

      <div className="mt-4 grid gap-3 md:grid-cols-4">
        <label className="text-sm font-medium text-slate-300">Field selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={field} onChange={(event) => setField(event.target.value as DiscoveryOperationsReviewQueueMetadataField | '')}><option value="">All fields</option>{(filters?.available_fields ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
        <label className="text-sm font-medium text-slate-300">Status selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={status} onChange={(event) => setStatus(event.target.value as DiscoveryOperationsReviewQueueDiscoveryStatus | '')}><option value="">All statuses</option>{(filters?.available_statuses ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
        <label className="text-sm font-medium text-slate-300">Priority selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={priority} onChange={(event) => setPriority(event.target.value as DiscoveryOperationsReviewQueuePriorityType | '')}><option value="">All priorities</option>{(filters?.available_priorities ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
        <label className="text-sm font-medium text-slate-300">Severity selector<select className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 p-2 text-sm text-slate-100" value={severity} onChange={(event) => setSeverity(event.target.value as DiscoveryOperationsReviewQueueSeverity | '')}><option value="">All severities</option>{(filters?.available_severities ?? []).map((item) => <option key={item} value={item}>{label(item)}</option>)}</select></label>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400"><Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> Loading discovery operations review queue...</div>}
        {state.status === 'error' && <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status">{state.message}</p>}
        {state.status === 'ready' && (
          <div className="space-y-4">
            <div className="grid gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300 sm:grid-cols-3">
              <span>Total queue items: <strong className="text-white">{state.payload.summary.total_queue_items}</strong></span>
              <span>Critical/warning/note/info: <strong className="text-white">{state.payload.summary.critical_items}/{state.payload.summary.warning_items}/{state.payload.summary.note_items}/{state.payload.summary.info_items}</strong></span>
              <span>Recommended staff focus: <strong className="text-white">{state.payload.summary.recommended_staff_focus}</strong></span>
            </div>
            {state.payload.queue.length === 0 ? (
              <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">No review queue items match the selected filters.</div>
            ) : (
              <div className="overflow-hidden rounded-xl border border-slate-800">
                {state.payload.queue.map((item) => (
                  <article key={item.id} className="border-b border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300 last:border-b-0">
                    <div className="flex flex-wrap items-center justify-between gap-2"><h3 className="font-semibold text-white">{item.issue_title}</h3><span className="rounded-full border border-slate-700 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-300">Severity: {label(item.severity)}</span></div>
                    <p className="mt-2">Affected torrent: <strong className="text-slate-100">{item.torrent_name}</strong> #{item.torrent_id} · Metadata field: <strong className="text-slate-100">{label(item.metadata_field)}</strong></p>
                    <p className="mt-2">Issue summary: {item.issue_summary}</p>
                    <p className="mt-2">Explanation: {item.explanation}</p>
                    <p className="mt-3"><strong className="text-slate-100">Recommended staff action:</strong> {item.recommended_staff_action}</p>
                    <p className="mt-3 rounded-lg border border-slate-800 bg-slate-900/80 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Readonly: {item.readonly ? 'true' : 'false'} · mutation not allowed: {item.mutation_allowed ? 'false' : 'true'}</p>
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
