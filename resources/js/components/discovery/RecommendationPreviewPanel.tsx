import React, { useEffect, useMemo, useState } from 'react';
import { Eye, Loader2, Lock } from 'lucide-react';
import {
  fetchRecommendationPreview,
  type RecommendationPreviewGroup,
  type RecommendationPreviewPayload,
} from '../../lib/recommendationPreview';

type PreviewState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationPreviewPayload };

const PREVIEW_GROUP_LIMIT = 4;
const PREVIEW_ITEM_LIMIT = 3;

function previewGroupLabel(group: RecommendationPreviewGroup): string {
  return [group.group.source, group.group.resolution, group.group.language].filter(Boolean).join(' · ');
}

function hasPreviewItems(payload: RecommendationPreviewPayload): boolean {
  return payload.preview_groups.some((group) => group.items.length > 0);
}

export default function RecommendationPreviewPanel(): React.ReactElement {
  const [state, setState] = useState<PreviewState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationPreview()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Recommendation preview is temporarily unavailable.',
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

    if (!hasPreviewItems(state.payload)) {
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm leading-6 text-slate-400">
          No recommendation preview items are available yet.
        </p>
      );
    }

    return (
      <div className="grid gap-3 lg:grid-cols-2">
        {state.payload.preview_groups.slice(0, PREVIEW_GROUP_LIMIT).map((group) => {
          const label = previewGroupLabel(group);

          return (
            <article key={label} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <div className="flex items-start gap-2">
                <Eye className="mt-0.5 h-4 w-4 shrink-0 text-brand" aria-hidden="true" />
                <div className="min-w-0">
                  <h3 className="truncate text-sm font-semibold text-slate-100" title={label}>{label}</h3>
                  <p className="mt-1 text-xs leading-5 text-slate-500">Readonly preview items matched by system-wide metadata output groups.</p>
                </div>
              </div>

              {group.items.length === 0 ? (
                <p className="mt-3 rounded-lg border border-slate-800 bg-slate-900/60 p-2 text-xs text-slate-400">No visible torrents currently match this preview group.</p>
              ) : (
                <ul className="mt-3 space-y-2">
                  {group.items.slice(0, PREVIEW_ITEM_LIMIT).map((item) => (
                    <li key={item.torrent.id} className="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                      <p className="truncate text-sm font-semibold text-slate-100" title={item.metadata.title ?? item.torrent.name}>{item.metadata.title ?? item.torrent.name}</p>
                      <p className="mt-1 truncate text-xs text-slate-500" title={item.torrent.name}>{item.torrent.name}</p>
                      <dl className="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                        {item.reasons.map((reason) => (
                          <div key={`${item.torrent.id}-${reason.field}`}>
                            <dt className="font-semibold uppercase tracking-wide text-slate-500">{reason.field.replace('_', ' ')}</dt>
                            <dd className="mt-0.5 truncate text-slate-300" title={String(reason.value)}>{reason.value}</dd>
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
            Recommendation Preview
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Readonly metadata preview</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            System-wide preview of visible torrents matched from recommendation output metadata groups. This does not personalize, rank, store history, or execute concrete recommendations.
          </p>
        </div>
        <span className="inline-flex w-fit items-center rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          Preview only
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading recommendation preview...
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
