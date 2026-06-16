import { fetchJson } from './http';

export const RECOMMENDATION_OUTPUT_GROUPS_ENDPOINT = '/api/recommendations/output' as const;
export const RECOMMENDATION_OUTPUT_GROUPS_VERSION = 1 as const;

export interface RecommendationOutputGroup {
  source: string;
  resolution: string;
  language: string;
}

export interface RecommendationOutputGroupsPayload {
  version: typeof RECOMMENDATION_OUTPUT_GROUPS_VERSION;
  readonly: true;
  recommendation_groups: RecommendationOutputGroup[];
}

export async function fetchRecommendationOutputGroups(): Promise<RecommendationOutputGroupsPayload> {
  return fetchJson<RecommendationOutputGroupsPayload>(RECOMMENDATION_OUTPUT_GROUPS_ENDPOINT);
}
