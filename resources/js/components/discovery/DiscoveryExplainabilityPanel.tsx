import React, { useEffect, useMemo, useState } from 'react';
import { FileQuestion, Loader2, Lock } from 'lucide-react';
import { fetchDiscoveryExplainability, type DiscoveryExplainabilityPayload, type DiscoveryExplainabilityStatus } from '../../lib/discoveryExplainability';

type ExplainabilityState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryExplainabilityPayload };

const STATUS_LABELS: Record<DiscoveryExplainabilityStatus, string> = {
  discovery_ready: 'Discovery Ready',
  weakly_discoverable: 'Weakly Discoverable',
  missing_core_metadata: 'Missing Core Metadata',
};

function FieldList({ title, fields }: { title: string; fields: { field: string; label: string; value?: string | number }[] }): React.ReactElement {
  return (
    <div>
      <h4 className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</h4>
      {fields.length === 0 ? (
        <p className="mt-2 text-xs text-slate-500">None</p>
      ) : (
        <div className="mt-2 flex flex-wrap gap-2">
          {fields.map((field) => (
            <span key={field.field} className="rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-xs text-slate-200">
              {field.label}{field.value !== undefined ? `: ${field.value}` : ''}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}

export default function DiscoveryExplainabilityPanel(): React.ReactElement {
  const [state, setState] = useState<ExplainabilityState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchDiscoveryExplainability()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', message: 'Discovery explainability is temporarily unavailable.' });
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

    if (state.payload.explanations.length === 0) {
      return <div className="rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-300">No discovery explanations available.</div>;
    }

    return (
      <div className="grid gap-4 xl:grid-cols-2">
        {state.payload.explanations.map((explanation) => (
          <article key={explanation.torrent_id} className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h3 className="text-sm font-semibold text-white">{explanation.torrent_name}</h3>
                <p className="mt-1 text-sm leading-6 text-slate-400">{explanation.discovery_summary}</p>
              </div>
              <span className="inline-flex w-fit rounded-full border border-brand/40 bg-brand/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-brand">
                {STATUS_LABELS[explanation.discovery_status]}
              </span>
            </div>
            <p className="mt-3 text-sm leading-6 text-slate-300">{explanation.explanation}</p>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <FieldList title="Present Metadata" fields={explanation.metadata_present} />
              <FieldList title="Missing Metadata" fields={explanation.metadata_missing} />
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
            <FileQuestion className="h-4 w-4" aria-hidden="true" /> Discovery Explainability
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Why discovery is strong or weak</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly explanations for visible torrents using the same metadata-first discovery readiness fields as discovery health.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" /> Loading discovery explainability...
          </div>
        )}
        {state.status === 'error' && <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status" aria-live="polite">{state.message}</p>}
        {state.status === 'ready' && content}
      </div>
    </section>
  );
}
