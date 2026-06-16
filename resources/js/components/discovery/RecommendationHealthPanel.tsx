import React, { useEffect, useMemo, useState } from 'react';
import { Activity, Loader2, Lock } from 'lucide-react';
import {
  fetchRecommendationHealth,
  type RecommendationHealthPayload,
} from '../../lib/recommendationHealth';

type HealthState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationHealthPayload };

const METRIC_LABELS: Record<keyof RecommendationHealthPayload['metrics'], string> = {
  signals_generated: 'Signals Generated',
  candidates_generated: 'Candidates Generated',
  outputs_generated: 'Outputs Generated',
  torrent_recommendations_generated: 'Torrent Recommendations Generated',
  empty_outputs: 'Empty Outputs',
  empty_recommendation_results: 'Empty Recommendation Results',
  recommendation_match_rate: 'Recommendation Match Rate',
};

function formatMetric(key: keyof RecommendationHealthPayload['metrics'], value: number): string {
  if (key === 'recommendation_match_rate') {
    return `${Math.round(value * 100)}%`;
  }

  return value.toLocaleString();
}

export default function RecommendationHealthPanel(): React.ReactElement {
  const [state, setState] = useState<HealthState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationHealth()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Recommendation health is temporarily unavailable.',
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

    const metricEntries = Object.entries(state.payload.metrics) as [keyof RecommendationHealthPayload['metrics'], number][];

    return (
      <div className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {metricEntries.map(([key, value]) => (
            <div key={key} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {METRIC_LABELS[key]}
              </dt>
              <dd className="mt-2 text-2xl font-semibold text-white">
                {formatMetric(key, value)}
              </dd>
            </div>
          ))}
        </div>

        <div className="overflow-hidden rounded-xl border border-slate-800 bg-slate-950/40">
          <div className="border-b border-slate-800 px-3 py-2">
            <h3 className="text-sm font-semibold text-slate-100">Metadata Coverage</h3>
            <p className="mt-1 text-xs text-slate-500">
              Visible torrent metadata completeness used by recommendation operations intelligence.
            </p>
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
                    <td className="px-3 py-2">
                      {coverage.covered.toLocaleString()} / {coverage.total.toLocaleString()}
                    </td>
                    <td className="px-3 py-2">{coverage.missing.toLocaleString()}</td>
                    <td className="px-3 py-2">
                      {Math.round(coverage.coverage_rate * 100)}%
                    </td>
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
            <Activity className="h-4 w-4" aria-hidden="true" />
            Recommendation Health
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">
            Recommendation operations intelligence
          </h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly health metrics for the metadata recommendation pipeline, including output emptiness, torrent match quality, and metadata coverage quality.
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
            Loading recommendation health...
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
