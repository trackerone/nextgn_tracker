import { fetchJson } from './http';
import type { DiscoveryOperationsOverviewPayload, DiscoveryOperationsWeakestMetadataField } from './discoveryOperationsOverview';

export const DISCOVERY_OPERATIONS_PRIORITIES_ENDPOINT = '/api/discovery/operations-priorities' as const;
export const DISCOVERY_OPERATIONS_PRIORITIES_VERSION = 1 as const;

export type DiscoveryOperationsPrioritySeverity = 'critical' | 'warning' | 'note' | 'info';

export interface DiscoveryOperationsPriorityExampleTorrent {
  torrent_id: number;
  torrent_name: string;
  discovery_status: string;
  discovery_summary: string;
}

export interface DiscoveryOperationsPriority {
  type: string;
  severity: DiscoveryOperationsPrioritySeverity;
  title: string;
  message: string;
  reason: string;
  affected_fields: DiscoveryOperationsWeakestMetadataField[];
  example_torrents: DiscoveryOperationsPriorityExampleTorrent[];
  recommended_staff_action: string;
}

export interface DiscoveryOperationsPrioritySummary {
  total_priorities: number;
  critical_priorities: number;
  warning_priorities: number;
  note_priorities: number;
  info_priorities: number;
  total_visible_torrents: number;
  discovery_ready_torrents: number;
  weakly_discoverable_torrents: number;
  missing_core_metadata_torrents: number;
  discovery_readiness_rate: number;
}

export interface DiscoveryOperationsPrioritiesPayload {
  version: typeof DISCOVERY_OPERATIONS_PRIORITIES_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  priorities: DiscoveryOperationsPriority[];
  summary: DiscoveryOperationsPrioritySummary;
  source_overview: DiscoveryOperationsOverviewPayload;
}

export async function fetchDiscoveryOperationsPriorities(): Promise<DiscoveryOperationsPrioritiesPayload> {
  return fetchJson<DiscoveryOperationsPrioritiesPayload>(DISCOVERY_OPERATIONS_PRIORITIES_ENDPOINT);
}
