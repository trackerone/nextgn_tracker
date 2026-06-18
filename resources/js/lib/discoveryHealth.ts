import { fetchJson } from './http';

export const DISCOVERY_HEALTH_ENDPOINT = '/api/discovery/health' as const;
export const DISCOVERY_HEALTH_VERSION = 1 as const;

export interface DiscoveryHealthMetrics {
  total_visible_torrents: number;
  torrents_with_core_metadata: number;
  missing_core_metadata_torrents: number;
  discovery_ready_torrents: number;
  weakly_discoverable_torrents: number;
  discovery_readiness_rate: number;
}

export interface DiscoveryMetadataCoverageSummary {
  field: 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year';
  label: string;
  total: number;
  covered: number;
  missing: number;
  coverage_rate: number;
}

export interface DiscoveryHealthIndicators {
  has_visible_torrents: boolean;
  has_discovery_ready_torrents: boolean;
  has_weakly_discoverable_torrents: boolean;
  has_metadata_gaps: boolean;
  metadata_first: true;
}

export interface DiscoveryHealthPayload {
  version: typeof DISCOVERY_HEALTH_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  metrics: DiscoveryHealthMetrics;
  metadata_coverage: DiscoveryMetadataCoverageSummary[];
  indicators: DiscoveryHealthIndicators;
}

export async function fetchDiscoveryHealth(): Promise<DiscoveryHealthPayload> {
  return fetchJson<DiscoveryHealthPayload>(DISCOVERY_HEALTH_ENDPOINT);
}
