import { fetchJson } from './http';
import type { DiscoveryExplanation } from './discoveryExplainability';
import type { DiscoveryHealthPayload } from './discoveryHealth';

export const DISCOVERY_OPERATIONS_OVERVIEW_ENDPOINT = '/api/discovery/operations-overview' as const;
export const DISCOVERY_OPERATIONS_OVERVIEW_VERSION = 1 as const;

export interface DiscoveryOperationsSummary {
  total_visible_torrents: number;
  discovery_ready_torrents: number;
  weakly_discoverable_torrents: number;
  missing_core_metadata_torrents: number;
  discovery_readiness_rate: number;
}

export interface DiscoveryOperationsWeakestMetadataField {
  field: string;
  label: string;
  covered: number;
  missing: number;
  coverage_rate: number;
}

export interface DiscoveryOperationsAttentionItem {
  type: string;
  severity: 'info' | 'note' | 'warning';
  message: string;
}

export interface DiscoveryOperationsOverviewPayload {
  version: typeof DISCOVERY_OPERATIONS_OVERVIEW_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  summary: DiscoveryOperationsSummary;
  health: DiscoveryHealthPayload;
  weakest_metadata_fields: DiscoveryOperationsWeakestMetadataField[];
  attention_items: DiscoveryOperationsAttentionItem[];
  sample_explanations: DiscoveryExplanation[];
}

export async function fetchDiscoveryOperationsOverview(): Promise<DiscoveryOperationsOverviewPayload> {
  return fetchJson<DiscoveryOperationsOverviewPayload>(DISCOVERY_OPERATIONS_OVERVIEW_ENDPOINT);
}
