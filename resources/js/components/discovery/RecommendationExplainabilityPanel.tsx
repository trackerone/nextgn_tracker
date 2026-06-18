import React, { useEffect, useMemo, useState } from 'react';
import { HelpCircle, Loader2, Lock } from 'lucide-react';
import {
  fetchRecommendationExplainability,
  type RecommendationExplainabilityPayload,
} from '../../lib/recommendationExplainability';

type ExplainabilityState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationExplainabilityPayload };

export default function RecommendationExplainabilityPanel(): React.ReactElement {
  const [state, setState] = useState<ExplainabilityState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationExplainability()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Recommendation explainability is temporarily unavailable.',
          });
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
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400">
          No explanations available.
        </p>
      );
    }

    return (
      <div className="space-y-4">
        {state.payload.explanations.map((explanation) => (
          <article key={explanation.identifier} className="rounded-xl border border-slate-800 bg-slate-950/40 p-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-brand">Recommendation</p>
              <h3 className="mt-1 text-base font-semibold text-white">{explanation.title}</h3>
              <p className="mt-2 text-sm leading-6 text-slate-400">{explanation.summary}</p>
              <p className="mt-2 text-xs text-slate-500">{explanation.match_reason}</p>
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-3">
              <div className="rounded-lg border border-slate-800 p-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Why this recommendation exists</p>
                <p className="mt-2 text-sm text-slate-300">{explanation.signal_summary.reason}</p>
              </div>
              <div className="rounded-lg border border-slate-800 p-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Candidate summary</p>
                <p className="mt-2 text-sm text-slate-300">{explanation.candidate_summary.reason}</p>
              </div>
              <div className="rounded-lg border border-slate-800 p-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Output summary</p>
                <p className="mt-2 text-sm text-slate-300">{explanation.output_summary.reason}</p>
              </div>
            </div>

            <div className="mt-4">
              <h4 className="text-sm font-semibold text-slate-100">Matched metadata</h4>
              <div className="mt-2 flex flex-wrap gap-2">
                {explanation.metadata_reasons.map((reason) => (
                  <span key={reason.field} className="rounded-full border border-slate-700 px-2.5 py-1 text-xs text-slate-300">
                    {reason.field}: {reason.value}
                  </span>
                ))}
              </div>
            </div>

            <div className="mt-4 overflow-hidden rounded-lg border border-slate-800">
              <div className="border-b border-slate-800 px-3 py-2">
                <h4 className="text-sm font-semibold text-slate-100">Matching torrents</h4>
              </div>
              {explanation.matched_torrents.length === 0 ? (
                <p className="p-3 text-sm text-slate-400">No matched torrents.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-slate-800 text-sm">
                    <thead className="bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                      <tr>
                        <th className="px-3 py-2">Torrent</th>
                        <th className="px-3 py-2">Why each torrent matched</th>
                        <th className="px-3 py-2">Metadata matched</th>
                        <th className="px-3 py-2">Missing metadata</th>
                        <th className="px-3 py-2">Weak/partial metadata</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800 text-slate-300">
                      {explanation.matched_torrents.map((match) => (
                        <tr key={match.torrent.id}>
                          <td className="px-3 py-2 font-medium text-slate-100">{match.torrent.name}</td>
                          <td className="px-3 py-2">{match.match_reason}</td>
                          <td className="px-3 py-2">
                            {match.metadata_matched.map((metadata) => `${metadata.field}: ${metadata.value}`).join(', ')}
                          </td>
                          <td className="px-3 py-2">
                            {match.metadata_missing.length === 0
                              ? 'No missing metadata.'
                              : match.metadata_missing.map((metadata) => metadata.field).join(', ')}
                          </td>
                          <td className="px-3 py-2">
                            {match.metadata_weak.length === 0
                              ? 'No weak/partial metadata.'
                              : match.metadata_weak.map((metadata) => `${metadata.field}: ${metadata.value}`).join(', ')}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
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
            <HelpCircle className="h-4 w-4" aria-hidden="true" />
            Recommendation Explainability
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Why recommendations matched torrents</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly explanations for the metadata recommendation pipeline: why a recommendation exists, why torrents matched, and which metadata fields contributed, were missing, or were weak/partial.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Lock className="h-3 w-3" aria-hidden="true" /> Readonly
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading recommendation explainability...
          </div>
        )}

        {state.status === 'error' && (
          <p className="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm leading-6 text-red-200" role="status" aria-live="polite">
            {state.message}
          </p>
        )}

        {state.status === 'ready' && content}
      </div>
    </section>
  );
}
