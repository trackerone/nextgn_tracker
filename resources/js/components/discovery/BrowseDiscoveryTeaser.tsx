import React, { useEffect, useMemo, useState } from 'react';
import { ArrowRight, Loader2, Sparkles } from 'lucide-react';
import { fetchDiscoveryHome, type DiscoveryAggregateItem, type DiscoveryHomePayload } from '../../lib/discovery';

type BrowseDiscoveryTeaserProps = {
  discoveryUrl: string;
};

type TeaserState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryHomePayload };

const GROUP_LABELS: Record<'sources' | 'resolutions' | 'release_groups', string> = {
  sources: 'Sources',
  resolutions: 'Resolutions',
  release_groups: 'Release groups',
};

const GROUP_ORDER: Array<'sources' | 'resolutions' | 'release_groups'> = ['sources', 'resolutions', 'release_groups'];

function formatCount(count: number): string {
  return new Intl.NumberFormat('en-US').format(count);
}

function selectItems(
  payload: DiscoveryHomePayload,
  group: 'sources' | 'resolutions' | 'release_groups',
): DiscoveryAggregateItem[] {
  const trendingItems = payload.trending[group] ?? [];
  if (trendingItems.length > 0) {
    return trendingItems.slice(0, 4);
  }

  return (payload.popular[group] ?? []).slice(0, 4);
}

function TeaserSkeleton(): React.ReactElement {
  return (
    <div className="space-y-3">
      <div className="h-4 w-28 rounded bg-slate-800/80" />
      <div className="space-y-2">
        <div className="h-3 w-full rounded bg-slate-800/70" />
        <div className="h-3 w-5/6 rounded bg-slate-800/70" />
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        {GROUP_ORDER.map((group) => (
          <div key={group} className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
            <div className="h-3 w-20 rounded bg-slate-800/80" />
            <div className="mt-3 space-y-2">
              <div className="h-6 rounded-full bg-slate-800/60" />
              <div className="h-6 rounded-full bg-slate-800/60" />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

export default function BrowseDiscoveryTeaser({ discoveryUrl }: BrowseDiscoveryTeaserProps): React.ReactElement {
  const [state, setState] = useState<TeaserState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchDiscoveryHome()
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

    const { payload } = state;
    const popularSummary = payload.summary.popular;
    const trendingSummary = payload.summary.trending;

    return (
      <>
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1">
            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-brand">Discovery</p>
            <h2 className="text-lg font-semibold tracking-tight text-white">Trending now</h2>
            <p className="text-sm leading-6 text-slate-400">
              Discovery-backed signals from the last {payload.summary.trending.window}.
            </p>
          </div>
          <span className="rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
            Read only
          </span>
        </div>

        <div className="grid gap-3 sm:grid-cols-3">
          {GROUP_ORDER.map((group) => {
            const items = selectItems(payload, group);

            return (
              <section key={group} className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                <div className="mb-3 flex items-center justify-between gap-2">
                  <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">{GROUP_LABELS[group]}</h3>
                  <span className="text-[11px] text-slate-500">{group === 'sources' ? popularSummary.sources : group === 'resolutions' ? popularSummary.resolutions : popularSummary.release_groups}</span>
                </div>

                {items.length === 0 ? (
                  <p className="text-xs leading-5 text-slate-500">No discovery signals yet.</p>
                ) : (
                  <ul className="space-y-1.5">
                    {items.map((item) => (
                      <li key={`${group}-${item.value}`} className="flex items-center justify-between gap-2 rounded-full border border-slate-800/80 bg-slate-900/60 px-2.5 py-1 text-xs">
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

        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-950/30 px-3 py-2">
          <p className="text-xs leading-5 text-slate-400">
            Popular coverage spans {formatCount(popularSummary.sources)} sources, {formatCount(popularSummary.resolutions)} resolutions and {formatCount(popularSummary.release_groups)} release groups.
          </p>
          <p className="text-[11px] text-slate-500">
            Trending categories: {formatCount(trendingSummary.sources)} sources, {formatCount(trendingSummary.resolutions)} resolutions, {formatCount(trendingSummary.release_groups)} release groups.
          </p>
        </div>

        <a
          href={discoveryUrl}
          className="inline-flex items-center gap-2 text-sm font-semibold text-brand transition hover:text-brand/80"
        >
          Open Discovery
          <ArrowRight className="h-4 w-4" aria-hidden="true" />
        </a>
      </>
    );
  }, [discoveryUrl, state]);

  return (
    <section className="rounded-xl border border-slate-800 bg-slate-900/75 p-4 shadow-lg shadow-slate-900/20">
      {state.status === 'loading' && (
        <div className="space-y-4" aria-live="polite">
          <div className="flex items-center gap-2 text-sm font-semibold text-slate-300">
            <Sparkles className="h-4 w-4 text-brand" aria-hidden="true" />
            Discovery teaser
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
          </div>
          <TeaserSkeleton />
        </div>
      )}

      {state.status === 'error' && (
        <div className="space-y-3" role="status" aria-live="polite">
          <div className="flex items-center gap-2 text-sm font-semibold text-slate-300">
            <Sparkles className="h-4 w-4 text-brand" aria-hidden="true" />
            Discovery teaser
          </div>
          <p className="text-sm leading-6 text-slate-400">{state.message}</p>
          <a
            href={discoveryUrl}
            className="inline-flex items-center gap-2 text-sm font-semibold text-brand transition hover:text-brand/80"
          >
            Open Discovery
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </a>
        </div>
      )}

      {state.status === 'ready' && content}
    </section>
  );
}
