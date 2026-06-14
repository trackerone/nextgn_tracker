import React, { useEffect, useMemo, useState } from 'react';
import { Cpu, Loader2, Lock, Scale, ShieldCheck } from 'lucide-react';
import {
  fetchRecommendationEngineFoundation,
  type RecommendationEngineFoundationPayload,
  type RecommendationEngineMetadataCategory,
  type RecommendationEngineSignalGroup,
} from '../../lib/recommendationEngine';

type EngineFoundationState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationEngineFoundationPayload };

const CATEGORY_LABELS: Record<RecommendationEngineMetadataCategory, string> = {
  sources: 'Sources',
  resolutions: 'Resolutions',
  languages: 'Languages',
  release_groups: 'Release groups',
};

const SIGNAL_GROUP_LABELS: Record<RecommendationEngineSignalGroup, string> = {
  popular: 'Popular signals',
  trending: 'Trending signals',
};

function formatPercent(weight: number): string {
  return `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(weight)}%`;
}

function engineHasFoundationData(payload: RecommendationEngineFoundationPayload): boolean {
  return payload.metadata_categories.length > 0 || payload.signal_groups.length > 0 || Object.keys(payload.weights).length > 0;
}

export default function RecommendationEngineFoundationPanel(): React.ReactElement {
  const [state, setState] = useState<EngineFoundationState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationEngineFoundation()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Recommendation engine foundation signals are temporarily unavailable.',
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

    if (!engineHasFoundationData(state.payload)) {
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm leading-6 text-slate-400">
          No recommendation engine foundation signals are available yet.
        </p>
      );
    }

    return (
      <div className="grid gap-3 lg:grid-cols-3">
        <section className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
          <h3 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
            <Cpu className="h-3.5 w-3.5" aria-hidden="true" />
            Metadata categories
          </h3>
          <div className="mt-3 flex flex-wrap gap-1.5">
            {state.payload.metadata_categories.length === 0 ? (
              <span className="text-xs text-slate-500">No categories yet.</span>
            ) : state.payload.metadata_categories.map((category) => (
              <span key={category} className="rounded-full border border-slate-800 bg-slate-900/70 px-2.5 py-1 text-xs font-medium text-slate-200">
                {CATEGORY_LABELS[category] ?? category}
              </span>
            ))}
          </div>
        </section>

        <section className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
          <h3 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
            <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
            Signal groups
          </h3>
          <div className="mt-3 flex flex-wrap gap-1.5">
            {state.payload.signal_groups.length === 0 ? (
              <span className="text-xs text-slate-500">No signal groups yet.</span>
            ) : state.payload.signal_groups.map((group) => (
              <span key={group} className="rounded-full border border-slate-800 bg-slate-900/70 px-2.5 py-1 text-xs font-medium text-slate-200">
                {SIGNAL_GROUP_LABELS[group] ?? group}
              </span>
            ))}
          </div>
        </section>

        <section className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
          <h3 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
            <Scale className="h-3.5 w-3.5" aria-hidden="true" />
            Foundation weights
          </h3>
          <dl className="mt-3 grid grid-cols-2 gap-2">
            {Object.entries(state.payload.weights).map(([name, weight]) => (
              <div key={name} className="rounded-lg border border-slate-800 bg-slate-900/70 px-3 py-2">
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{SIGNAL_GROUP_LABELS[name as RecommendationEngineSignalGroup] ?? name}</dt>
                <dd className="mt-1 font-mono text-sm font-semibold text-slate-100">{formatPercent(weight)}</dd>
              </div>
            ))}
          </dl>
        </section>
      </div>
    );
  }, [state]);

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <Lock className="h-4 w-4" aria-hidden="true" />
            Recommendation Engine Foundation
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Readonly metadata signal foundation</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            A compact view of engine foundation inputs only: metadata categories, signal groups, weights, and no-history flags. It does not show torrents.
          </p>
        </div>
        <div className="flex flex-wrap gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <span className="rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1">Readonly</span>
          <span className="rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1">No user history</span>
          <span className="rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1">No download history</span>
          <span className="rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1">No watch history</span>
        </div>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading recommendation engine foundation signals...
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
