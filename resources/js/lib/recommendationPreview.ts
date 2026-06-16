import { fetchJson } from './http';

export const RECOMMENDATION_PREVIEW_ENDPOINT = '/api/recommendations/preview' as const;
export const RECOMMENDATION_PREVIEW_VERSION = 1 as const;

export interface RecommendationPreviewGroupDescriptor {
  source: string;
  resolution: string;
  language: string;
}

export interface RecommendationPreviewTorrent {
  id: number;
  name: string;
}

export interface RecommendationPreviewMetadata {
  title: string | null;
  year: number | string | null;
  type: string | null;
  resolution: string | null;
  source: string | null;
  release_group: string | null;
  language: string | null;
  audio_language: string | null;
  subtitle_language: string | null;
  subtitles: string | null;
  imdb_id: string | null;
  tmdb_id: string | null;
  nfo: string | null;
}

export interface RecommendationPreviewReason {
  field: 'source' | 'resolution' | 'language' | 'release_group';
  value: string | number;
}

export interface RecommendationPreviewItem {
  torrent: RecommendationPreviewTorrent;
  metadata: RecommendationPreviewMetadata;
  reasons: RecommendationPreviewReason[];
}

export interface RecommendationPreviewGroup {
  group: RecommendationPreviewGroupDescriptor;
  items: RecommendationPreviewItem[];
}

export interface RecommendationPreviewPayload {
  version: typeof RECOMMENDATION_PREVIEW_VERSION;
  readonly: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  preview_groups: RecommendationPreviewGroup[];
}

export async function fetchRecommendationPreview(): Promise<RecommendationPreviewPayload> {
  return fetchJson<RecommendationPreviewPayload>(RECOMMENDATION_PREVIEW_ENDPOINT);
}
