import { fetchJson } from './http';

export const RECOMMENDATION_HEALTH_ENDPOINT = '/api/recommendations/health' as const;
export const RECOMMENDATION_HEALTH_VERSION = 1 as const;

export interface RecommendationHealthMetrics {
  signals_generated: number;
  candidates_generated: number;
  outputs_generated: number;
  torrent_recommendations_generated: number;
  empty_outputs: number;
  empty_recommendation_results: number;
  recommendation_match_rate: number;
}

export interface RecommendationMetadataCoverageSummary {
  field: 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year';
  label: string;
  total: number;
  covered: number;
  missing: number;
  coverage_rate: number;
}

export interface RecommendationHealthIndicators {
  has_signals: boolean;
  has_candidates: boolean;
  has_outputs: boolean;
  has_torrent_matches: boolean;
  metadata_first: true;
}

export interface RecommendationHealthPayload {
  version: typeof RECOMMENDATION_HEALTH_VERSION;
  readonly: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  pipeline: ['signals', 'candidates', 'output', 'preview', 'torrents', 'health'];
  metrics: RecommendationHealthMetrics;
  metadata_coverage: RecommendationMetadataCoverageSummary[];
  indicators: RecommendationHealthIndicators;
}

export async function fetchRecommendationHealth(): Promise<RecommendationHealthPayload> {
  return fetchJson<RecommendationHealthPayload>(RECOMMENDATION_HEALTH_ENDPOINT);
}
