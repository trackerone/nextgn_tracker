import { fetchJson } from './http';

export const RECOMMENDATION_CANDIDATES_ENDPOINT = '/api/recommendations/candidates' as const;
export const RECOMMENDATION_CANDIDATES_VERSION = 1 as const;

export interface RecommendationCandidateGroup {
  source: string;
  resolution: string;
}

export interface RecommendationCandidatesPayload {
  version: typeof RECOMMENDATION_CANDIDATES_VERSION;
  readonly: true;
  candidate_groups: RecommendationCandidateGroup[];
}

export async function fetchRecommendationCandidates(): Promise<RecommendationCandidatesPayload> {
  return fetchJson<RecommendationCandidatesPayload>(RECOMMENDATION_CANDIDATES_ENDPOINT);
}
