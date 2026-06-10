import React, { useEffect, useMemo, useState } from 'react';
import { Loader2, Radar, Signal } from 'lucide-react';
import {
  fetchRecommendationSignals,
  type RecommendationSignalAggregateItem,
  type RecommendationSignalsPayload,
} from '../../lib/recommendationSignals';

type SignalsState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationSignalsPayload };

type SignalSection = {
  key: 'sources' | 'resolutions' | 'languages' | 'release_groups';
  title: string;
  variant: 'popular' | 'trending';
};

const SIGNAL_LIMIT = 4;

const SECTIONS: SignalSection[] = [
  { key: 'sources', title: 'Popular sources', variant: 'popular' },
  { key: 'resolutions', title: 'Popular resolutions', variant: 'popular' },
  { key: 'languages', title: 'Popular languages', variant: 'popular' },
  { key: 'release_groups', title: 'Trending groups', variant: 'trending' },
];

function formatCount(count: number): string {
  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(count);
}

function sectionItems(payload: RecommendationSignalsPayload, section: SignalSection): RecommendationSignalAggregateItem[] {
  if (section.variant === 'popular') {
    return (payload.signals.popular[section.key] ?? []).slice(0, SIGNAL_LIMIT);
  }

  if (section.key === 'languages') {
    return [];
  }

  return (payload.signals.trending[section.key] ?? []).slice(0, SIGNAL_LIMIT);
}

function hasSignals(payload: RecommendationSignalsPayload): boolean {
  return SECTIONS.some((section) => sectionItems(payload, section).length > 0);
}

export default function RecommendationSignalsPanel(): React.ReactElement {
  const [state, setState] = useState<SignalsState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationSignals()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Discovery signals are temporarily unavailable.',
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

    if (!hasSignals(state.payload)) {
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm leading-6 text-slate-400">
          No discovery signals are available yet.
        </p>
      );
    }

    return (
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        {SECTIONS.map((section) => {
          const items = sectionItems(state.payload, section);

          return (
            <section key={`${section.variant}-${section.key}`} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">{section.title}</h3>

              {items.length === 0 ? (
                <p className="mt-3 text-xs leading-5 text-slate-500">No signals yet.</p>
              ) : (
                <ul className="mt-3 space-y-1.5">
                  {items.map((item) => (
                    <li
                      key={`${section.variant}-${section.key}-${item.value}`}
                      className="flex items-center justify-between gap-2 rounded-full border border-slate-800/80 bg-slate-900/60 px-2.5 py-1 text-xs"
                    >
                      <span className="min-w-0 truncate font-medium text-slate-200" title={item.value}>
                        {item.value}
                      </span>
                      <span className="shrink-0 font-mono text-[11px] text-slate-500">{formatCount(item.count)}</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          );
        })}
      </div>
    );
  }, [state]);

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <Signal className="h-4 w-4" aria-hidden="true" />
            Recommendation Signals
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Metadata-driven discovery signals</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Read-only aggregate signals from catalog metadata. This surface does not personalize, use history, or show torrents.
          </p>
        </div>
        <span className="inline-flex w-fit items-center gap-1.5 rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          <Radar className="h-3.5 w-3.5" aria-hidden="true" />
          Signals only
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading discovery signals...
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
