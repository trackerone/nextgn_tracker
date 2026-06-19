import { fetchJson } from './http';

export const DISCOVERY_OPERATIONS_DRILLDOWN_ENDPOINT = '/api/discovery/operations-drilldown' as const;
export const DISCOVERY_OPERATIONS_DRILLDOWN_VERSION = 1 as const;

export type DiscoveryOperationsDrilldownStatus = 'discovery_ready' | 'weakly_discoverable' | 'missing_core_metadata';
export type DiscoveryOperationsMetadataField = 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year';
export type DiscoveryOperationsPriorityType = 'missing_core_metadata' | 'low_discovery_readiness' | 'weak_category_or_type_coverage' | 'weak_audio_subtitle_source_coverage' | 'healthy_discovery_condition';

export interface DiscoveryOperationsDrilldownFilters {
  field: DiscoveryOperationsMetadataField | null;
  status: DiscoveryOperationsDrilldownStatus | null;
  priority: DiscoveryOperationsPriorityType | null;
  available_fields: DiscoveryOperationsMetadataField[];
  available_statuses: DiscoveryOperationsDrilldownStatus[];
  available_priorities: DiscoveryOperationsPriorityType[];
}

export interface DiscoveryOperationsDrilldownSummary {
  total_matching_torrents: number;
  field: DiscoveryOperationsMetadataField | null;
  status: DiscoveryOperationsDrilldownStatus | null;
  priority: DiscoveryOperationsPriorityType | null;
  missing_count: number;
  present_count: number;
  recommended_staff_action: string;
}

export interface DiscoveryOperationsDrilldownRow {
  torrent_id: number;
  torrent_name: string;
  discovery_status: DiscoveryOperationsDrilldownStatus;
  metadata_field: DiscoveryOperationsMetadataField;
  metadata_present: boolean;
  metadata_missing: boolean;
  metadata_weak: boolean;
  explanation: string;
  recommended_staff_action: string;
}

export interface DiscoveryOperationsDrilldownResponse {
  version: typeof DISCOVERY_OPERATIONS_DRILLDOWN_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  filters: DiscoveryOperationsDrilldownFilters;
  summary: DiscoveryOperationsDrilldownSummary;
  rows: DiscoveryOperationsDrilldownRow[];
}

export interface DiscoveryOperationsDrilldownQuery {
  field?: DiscoveryOperationsMetadataField | '';
  status?: DiscoveryOperationsDrilldownStatus | '';
  priority?: DiscoveryOperationsPriorityType | '';
}

export async function fetchDiscoveryOperationsDrilldown(query: DiscoveryOperationsDrilldownQuery = {}): Promise<DiscoveryOperationsDrilldownResponse> {
  const params = new URLSearchParams();

  Object.entries(query).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const suffix = params.toString();

  return fetchJson<DiscoveryOperationsDrilldownResponse>(`${DISCOVERY_OPERATIONS_DRILLDOWN_ENDPOINT}${suffix ? `?${suffix}` : ''}`);
}
