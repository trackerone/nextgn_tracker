import React, { useEffect, useMemo, useState } from 'react';
import { Boxes, Loader2, Lock } from 'lucide-react';
import {
  fetchRecommendationOutputGroups,
  type RecommendationOutputGroup,
  type RecommendationOutputGroupsPayload,
} from '../../lib/recommendationOutput';

type OutputState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationOutputGroupsPayload };

const OUTPUT_GROUP_LIMIT = 6;

function outputGroupLabel(group: RecommendationOutputGroup): string {
  return [group.source, group.resolution, group.language].filter(Boolean).join(' · ');
}

function hasOutputGroups(payload: RecommendationOutputGroupsPayload): boolean {
  return payload.recommendation_groups.length > 0;
}

export default function RecommendationOutputPanel(): React.ReactElement {
  const [state, setState] = useState<OutputState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationOutputGroups()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Recommendation output groups are temporarily unavailable.',
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

    if (!hasOutputGroups(state.payload)) {
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm leading-6 text-slate-400">
          No recommendation output groups are available yet.
        </p>
      );
    }

    return (
      <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        {state.payload.recommendation_groups.slice(0, OUTPUT_GROUP_LIMIT).map((group) => {
          const label = outputGroupLabel(group);

          return (
            <li key={`${group.source}-${group.resolution}-${group.language}`} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <div className="flex items-start gap-2">
                <Boxes className="mt-0.5 h-4 w-4 shrink-0 text-brand" aria-hidden="true" />
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-slate-100" title={label}>{label}</p>
                  <dl className="mt-2 grid grid-cols-3 gap-2 text-xs">
                    <div>
                      <dt className="font-semibold uppercase tracking-wide text-slate-500">Source</dt>
                      <dd className="mt-0.5 truncate text-slate-300" title={group.source}>{group.source}</dd>
                    </div>
                    <div>
                      <dt className="font-semibold uppercase tracking-wide text-slate-500">Resolution</dt>
                      <dd className="mt-0.5 truncate text-slate-300" title={group.resolution}>{group.resolution}</dd>
                    </div>
                    <div>
                      <dt className="font-semibold uppercase tracking-wide text-slate-500">Language</dt>
                      <dd className="mt-0.5 truncate text-slate-300" title={group.language}>{group.language}</dd>
                    </div>
                  </dl>
                </div>
              </div>
            </li>
          );
        })}
      </ul>
    );
  }, [state]);

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-xl shadow-slate-950/20">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-brand">
            <Lock className="h-4 w-4" aria-hidden="true" />
            Recommendation Output
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Metadata output groups</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly system-wide output groups built from metadata combinations. This surface lists groups only; it does not show torrents or tailor results to individual users.
          </p>
        </div>
        <span className="inline-flex w-fit items-center rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          Groups only
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading recommendation output groups...
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
