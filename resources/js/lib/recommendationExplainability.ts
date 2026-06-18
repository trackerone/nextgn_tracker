import { fetchJson } from './http';

export const RECOMMENDATION_EXPLAINABILITY_ENDPOINT = '/api/recommendations/explainability' as const;
export const RECOMMENDATION_EXPLAINABILITY_VERSION = 1 as const;

export interface RecommendationExplanationMetadataReason {
  field: string;
  value: string;
  reason: string;
  coverage_rate: number | null;
}

export interface RecommendationExplanationMatchedMetadata {
  field: string;
  value: string | number | null;
}

export interface RecommendationExplanationMissingMetadata {
  field: string;
  reason: string;
}

export interface RecommendationExplanationTorrent {
  torrent: {
    id: number;
    name: string;
  };
  metadata_matched: RecommendationExplanationMatchedMetadata[];
  metadata_missing: RecommendationExplanationMissingMetadata[];
  metadata_weak: RecommendationExplanationMissingMetadata[];
  match_reason: string;
  match_score: number | null;
}

export interface RecommendationExplanation {
  identifier: string;
  title: string;
  summary: string;
  signal_summary: {
    reason: string;
    metadata_fields: string[];
    metadata: Record<string, string>;
  };
  candidate_summary: {
    reason: string;
    metadata: Record<string, string>;
  };
  output_summary: {
    reason: string;
    metadata: Record<string, string>;
    matched_torrent_count: number;
  };
  matched_torrents: RecommendationExplanationTorrent[];
  metadata_reasons: RecommendationExplanationMetadataReason[];
}

export interface RecommendationExplainabilityPayload {
  version: typeof RECOMMENDATION_EXPLAINABILITY_VERSION;
  readonly: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  pipeline: ['signals', 'candidates', 'output', 'preview', 'torrents', 'health', 'explainability'];
  summaries: {
    signal_summary: Record<string, unknown>;
    candidate_summary: Record<string, unknown>;
    output_summary: Record<string, unknown>;
  };
  explanations: RecommendationExplanation[];
}

export async function fetchRecommendationExplainability(): Promise<RecommendationExplainabilityPayload> {
  return fetchJson<RecommendationExplainabilityPayload>(RECOMMENDATION_EXPLAINABILITY_ENDPOINT);
}
