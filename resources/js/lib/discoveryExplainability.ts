import { fetchJson } from './http';

export const DISCOVERY_EXPLAINABILITY_ENDPOINT = '/api/discovery/explainability' as const;
export const DISCOVERY_EXPLAINABILITY_VERSION = 1 as const;

export type DiscoveryExplainabilityStatus = 'discovery_ready' | 'weakly_discoverable' | 'missing_core_metadata';

export type DiscoveryExplainabilityField =
  | 'category'
  | 'type'
  | 'resolution'
  | 'source'
  | 'language'
  | 'audio_language'
  | 'subtitle_language'
  | 'release_group'
  | 'year';

export interface DiscoveryExplainabilityMetadataPresent {
  field: DiscoveryExplainabilityField;
  label: string;
  value: string | number;
}

export interface DiscoveryExplainabilityMetadataMissing {
  field: DiscoveryExplainabilityField;
  label: string;
}

export interface DiscoveryExplainabilityMetadataWeak extends DiscoveryExplainabilityMetadataMissing {
  reason: string;
}

export interface DiscoveryExplanation {
  torrent_id: number;
  torrent_name: string;
  discovery_status: DiscoveryExplainabilityStatus;
  discovery_summary: string;
  metadata_present: DiscoveryExplainabilityMetadataPresent[];
  metadata_missing: DiscoveryExplainabilityMetadataMissing[];
  metadata_weak: DiscoveryExplainabilityMetadataWeak[];
  explanation: string;
}

export interface DiscoveryExplainabilityPayload {
  version: typeof DISCOVERY_EXPLAINABILITY_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  explanations: DiscoveryExplanation[];
}

export async function fetchDiscoveryExplainability(): Promise<DiscoveryExplainabilityPayload> {
  return fetchJson<DiscoveryExplainabilityPayload>(DISCOVERY_EXPLAINABILITY_ENDPOINT);
}
