import React, { useEffect, useMemo, useState } from 'react';
import { Loader2, Sparkles } from 'lucide-react';
import {
  fetchDiscoveryWatchPresetSuggestions,
  type DiscoveryAggregateItem,
  type DiscoveryWatchPresetSuggestionCategory,
  type DiscoveryWatchPresetSuggestionsPayload,
} from '../../lib/discovery';

type SuggestionsState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: DiscoveryWatchPresetSuggestionsPayload };

type SuggestionSection = {
  key: DiscoveryWatchPresetSuggestionCategory;
  title: string;
};

const SUGGESTION_LIMIT = 5;

const SECTIONS: SuggestionSection[] = [
  { key: 'sources', title: 'Sources' },
  { key: 'resolutions', title: 'Resolutions' },
  { key: 'languages', title: 'Languages' },
  { key: 'release_groups', title: 'Release groups' },
];

function formatCount(count: number): string {
  return new Intl.NumberFormat('en-US').format(count);
}

function hasSuggestions(payload: DiscoveryWatchPresetSuggestionsPayload): boolean {
  return SECTIONS.some((section) => (payload[section.key] ?? []).length > 0);
}

function cappedItems(items: DiscoveryAggregateItem[]): DiscoveryAggregateItem[] {
  return items.slice(0, SUGGESTION_LIMIT);
}

export default function WatchDiscoverySuggestions(): React.ReactElement {
  const [state, setState] = useState<SuggestionsState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchDiscoveryWatchPresetSuggestions()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Watch discovery suggestions are temporarily unavailable.',
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

    if (!hasSuggestions(state.payload)) {
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm leading-6 text-slate-400">
          No watch discovery suggestions are available yet.
        </p>
      );
    }

    return (
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        {SECTIONS.map((section) => {
          const items = cappedItems(state.payload[section.key] ?? []);

          return (
            <section key={section.key} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400">{section.title}</h3>

              {items.length === 0 ? (
                <p className="mt-3 text-xs leading-5 text-slate-500">No suggestions yet.</p>
              ) : (
                <ul className="mt-3 space-y-1.5">
                  {items.map((item) => (
                    <li
                      key={`${section.key}-${item.value}`}
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
            <Sparkles className="h-4 w-4" aria-hidden="true" />
            Discovery suggestions
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Watch preset ideas</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Popular metadata signals that may help you decide which notification filters to type below. Suggestions are read-only and do not change the preset.
          </p>
        </div>
        <span className="w-fit rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          Read only
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading watch discovery suggestions...
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
