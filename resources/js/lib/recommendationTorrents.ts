import { fetchJson } from './http';

export const RECOMMENDATION_TORRENTS_ENDPOINT = '/api/recommendations/torrents' as const;
export const RECOMMENDATION_TORRENTS_VERSION = 1 as const;

export interface RecommendationTorrentMetadataSummary {
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

export interface RecommendationTorrentOutputMetadata {
  source: string;
  resolution: string;
  language: string;
}

export interface RecommendationTorrentRecommendation {
  identifier: string;
  title: string;
  explanation: string;
  metadata: RecommendationTorrentOutputMetadata;
}

export interface RecommendationTorrentReference {
  id: number;
  name: string;
}

export interface RecommendationTorrentMatchedField {
  field: 'source' | 'resolution' | 'language';
  value: string | number;
}

export interface RecommendationTorrentMatch {
  torrent: RecommendationTorrentReference;
  metadata: RecommendationTorrentMetadataSummary;
  match_reason: string;
  matched_fields: RecommendationTorrentMatchedField[];
}

export interface RecommendationTorrentGroup {
  recommendation: RecommendationTorrentRecommendation;
  torrents: RecommendationTorrentMatch[];
}

export interface RecommendationTorrentsPayload {
  version: typeof RECOMMENDATION_TORRENTS_VERSION;
  readonly: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  pipeline: ['signals', 'candidates', 'output', 'preview', 'torrents'];
  recommendations: RecommendationTorrentGroup[];
}

export async function fetchRecommendationTorrents(): Promise<RecommendationTorrentsPayload> {
  return fetchJson<RecommendationTorrentsPayload>(RECOMMENDATION_TORRENTS_ENDPOINT);
}
