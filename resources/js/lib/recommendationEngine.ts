import { fetchJson } from './http';

export const RECOMMENDATION_ENGINE_FOUNDATION_ENDPOINT = '/api/recommendations/engine' as const;
export const RECOMMENDATION_ENGINE_FOUNDATION_VERSION = 1 as const;
export const RECOMMENDATION_ENGINE_FOUNDATION_NAME = 'metadata_recommendation_engine_foundation' as const;
export const RECOMMENDATION_ENGINE_SIGNALS_TRENDING_WINDOW = '30d' as const;

export type RecommendationEngineMetadataCategory = 'sources' | 'resolutions' | 'languages' | 'release_groups';
export type RecommendationEngineSignalGroup = 'popular' | 'trending';

export interface RecommendationEngineSignalAggregateItem {
  value: string;
  count: number;
}

export interface RecommendationEnginePopularSignals {
  sources: RecommendationEngineSignalAggregateItem[];
  resolutions: RecommendationEngineSignalAggregateItem[];
  languages: RecommendationEngineSignalAggregateItem[];
  release_groups: RecommendationEngineSignalAggregateItem[];
}

export interface RecommendationEngineTrendingSignals {
  window: typeof RECOMMENDATION_ENGINE_SIGNALS_TRENDING_WINDOW;
  sources: RecommendationEngineSignalAggregateItem[];
  resolutions: RecommendationEngineSignalAggregateItem[];
  release_groups: RecommendationEngineSignalAggregateItem[];
}

export interface RecommendationEngineWeights {
  popular: 60;
  trending: 40;
}

export interface RecommendationEngineFoundationPayload {
  version: typeof RECOMMENDATION_ENGINE_FOUNDATION_VERSION;
  engine: typeof RECOMMENDATION_ENGINE_FOUNDATION_NAME;
  readonly: true;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  metadata_categories: RecommendationEngineMetadataCategory[];
  signal_groups: RecommendationEngineSignalGroup[];
  weights: RecommendationEngineWeights;
  signals: {
    popular: RecommendationEnginePopularSignals;
    trending: RecommendationEngineTrendingSignals;
  };
}

export async function fetchRecommendationEngineFoundation(): Promise<RecommendationEngineFoundationPayload> {
  return fetchJson<RecommendationEngineFoundationPayload>(RECOMMENDATION_ENGINE_FOUNDATION_ENDPOINT);
}
