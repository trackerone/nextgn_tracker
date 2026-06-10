import { fetchJson } from './http';

export const RECOMMENDATION_SIGNALS_ENDPOINT = '/api/recommendations/signals' as const;
export const RECOMMENDATION_SIGNALS_VERSION = 1 as const;
export const RECOMMENDATION_SIGNALS_ENGINE = 'metadata_signals_foundation' as const;
export const RECOMMENDATION_SIGNALS_TRENDING_WINDOW = '30d' as const;

export interface RecommendationSignalAggregateItem {
  value: string;
  count: number;
}

export interface RecommendationSignalsPopularSection {
  sources: RecommendationSignalAggregateItem[];
  resolutions: RecommendationSignalAggregateItem[];
  languages: RecommendationSignalAggregateItem[];
  release_groups: RecommendationSignalAggregateItem[];
}

export interface RecommendationSignalsTrendingSection {
  window: typeof RECOMMENDATION_SIGNALS_TRENDING_WINDOW;
  sources: RecommendationSignalAggregateItem[];
  resolutions: RecommendationSignalAggregateItem[];
  release_groups: RecommendationSignalAggregateItem[];
}

export interface RecommendationSignalsPayload {
  version: typeof RECOMMENDATION_SIGNALS_VERSION;
  engine: typeof RECOMMENDATION_SIGNALS_ENGINE;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  signals: {
    popular: RecommendationSignalsPopularSection;
    trending: RecommendationSignalsTrendingSection;
  };
}

export async function fetchRecommendationSignals(): Promise<RecommendationSignalsPayload> {
  return fetchJson<RecommendationSignalsPayload>(RECOMMENDATION_SIGNALS_ENDPOINT);
}
