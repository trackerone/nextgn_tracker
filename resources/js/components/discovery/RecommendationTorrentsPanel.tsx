import React, { useEffect, useMemo, useState } from 'react';
import { Loader2, Lock, Sparkles } from 'lucide-react';
import {
  fetchRecommendationTorrents,
  type RecommendationTorrentGroup,
  type RecommendationTorrentsPayload,
} from '../../lib/recommendationTorrents';

type TorrentState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationTorrentsPayload };

const RECOMMENDATION_LIMIT = 4;
const TORRENT_LIMIT = 3;

function hasTorrentMatches(payload: RecommendationTorrentsPayload): boolean {
  return payload.recommendations.some((group) => group.torrents.length > 0);
}

function metadataLabel(group: RecommendationTorrentGroup): string {
  const { source, resolution, language } = group.recommendation.metadata;

  return [source, resolution, language].filter(Boolean).join(' · ');
}

export default function RecommendationTorrentsPanel(): React.ReactElement {
  const [state, setState] = useState<TorrentState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationTorrents()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Recommendation torrent matches are temporarily unavailable.',
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

    if (!hasTorrentMatches(state.payload)) {
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm leading-6 text-slate-400">
          No concrete recommended torrents are available yet.
        </p>
      );
    }

    return (
      <div className="grid gap-3 lg:grid-cols-2">
        {state.payload.recommendations.slice(0, RECOMMENDATION_LIMIT).map((group) => {
          const label = metadataLabel(group);

          return (
            <article key={group.recommendation.identifier} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <div className="flex items-start gap-2">
                <Sparkles className="mt-0.5 h-4 w-4 shrink-0 text-brand" aria-hidden="true" />
                <div className="min-w-0">
                  <h3 className="truncate text-sm font-semibold text-slate-100" title={group.recommendation.title}>{group.recommendation.title}</h3>
                  <p className="mt-1 text-xs leading-5 text-slate-500">{group.recommendation.explanation}</p>
                  <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400" title={label}>{label}</p>
                </div>
              </div>

              {group.torrents.length === 0 ? (
                <p className="mt-3 rounded-lg border border-slate-800 bg-slate-900/60 p-2 text-xs text-slate-400">No visible torrents currently satisfy this recommendation output.</p>
              ) : (
                <ul className="mt-3 space-y-2">
                  {group.torrents.slice(0, TORRENT_LIMIT).map((match) => (
                    <li key={match.torrent.id} className="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                      <p className="truncate text-sm font-semibold text-slate-100" title={match.metadata.title ?? match.torrent.name}>{match.metadata.title ?? match.torrent.name}</p>
                      <p className="mt-1 truncate text-xs text-slate-500" title={match.torrent.name}>{match.torrent.name}</p>
                      <p className="mt-2 text-xs leading-5 text-slate-400">{match.match_reason}</p>
                      <dl className="mt-2 grid grid-cols-3 gap-2 text-xs">
                        {match.matched_fields.map((field) => (
                          <div key={`${match.torrent.id}-${field.field}`}>
                            <dt className="font-semibold uppercase tracking-wide text-slate-500">{field.field.replace('_', ' ')}</dt>
                            <dd className="mt-0.5 truncate text-slate-300" title={String(field.value)}>{field.value}</dd>
                          </div>
                        ))}
                      </dl>
                    </li>
                  ))}
                </ul>
              )}
            </article>
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
            <Lock className="h-4 w-4" aria-hidden="true" />
            Concrete Recommended Torrents
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Readonly torrent resolution</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Resolves recommendation output into visible torrents using metadata taxonomy matches only. This is system-wide, readonly, and does not use personalization or user history.
          </p>
        </div>
        <span className="inline-flex w-fit items-center rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          Metadata only
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading concrete recommended torrents...
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
