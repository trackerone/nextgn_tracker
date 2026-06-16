import React, { useEffect, useMemo, useState } from 'react';
import { Layers3, Loader2, Lock } from 'lucide-react';
import {
  fetchRecommendationCandidates,
  type RecommendationCandidateGroup,
  type RecommendationCandidatesPayload,
} from '../../lib/recommendationCandidates';

type CandidatesState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; payload: RecommendationCandidatesPayload };

const CANDIDATE_LIMIT = 6;

function candidateLabel(candidate: RecommendationCandidateGroup): string {
  return [candidate.source, candidate.resolution].filter(Boolean).join(' · ');
}

function hasCandidateGroups(payload: RecommendationCandidatesPayload): boolean {
  return payload.candidate_groups.length > 0;
}

export default function RecommendationCandidatesPanel(): React.ReactElement {
  const [state, setState] = useState<CandidatesState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    void fetchRecommendationCandidates()
      .then((payload) => {
        if (!cancelled) {
          setState({ status: 'ready', payload });
        }
      })
      .catch(() => {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Recommendation candidate groups are temporarily unavailable.',
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

    if (!hasCandidateGroups(state.payload)) {
      return (
        <p className="rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm leading-6 text-slate-400">
          No recommendation candidate groups are available yet.
        </p>
      );
    }

    return (
      <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        {state.payload.candidate_groups.slice(0, CANDIDATE_LIMIT).map((candidate) => {
          const label = candidateLabel(candidate);

          return (
            <li key={`${candidate.source}-${candidate.resolution}`} className="rounded-xl border border-slate-800 bg-slate-950/40 p-3">
              <div className="flex items-start gap-2">
                <Layers3 className="mt-0.5 h-4 w-4 shrink-0 text-brand" aria-hidden="true" />
                <div className="min-w-0">
                  <p className="truncate text-sm font-semibold text-slate-100" title={label}>{label}</p>
                  <dl className="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <div>
                      <dt className="font-semibold uppercase tracking-wide text-slate-500">Source</dt>
                      <dd className="mt-0.5 truncate text-slate-300" title={candidate.source}>{candidate.source}</dd>
                    </div>
                    <div>
                      <dt className="font-semibold uppercase tracking-wide text-slate-500">Resolution</dt>
                      <dd className="mt-0.5 truncate text-slate-300" title={candidate.resolution}>{candidate.resolution}</dd>
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
            Recommendation Candidates
          </div>
          <h2 className="mt-2 text-lg font-semibold text-white">Metadata candidate groups</h2>
          <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
            Readonly source and resolution combinations generated from system-wide metadata signals. These are candidate groups only, without user-specific output, scoring, or final picks.
          </p>
        </div>
        <span className="inline-flex w-fit items-center rounded-full border border-slate-700 bg-slate-950/60 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          Candidates only
        </span>
      </div>

      <div className="mt-4">
        {state.status === 'loading' && (
          <div className="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-400" aria-live="polite">
            <Loader2 className="h-4 w-4 animate-spin text-slate-500" aria-hidden="true" />
            Loading recommendation candidate groups...
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
