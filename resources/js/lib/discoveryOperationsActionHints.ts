import { fetchJson } from './http';

export const DISCOVERY_OPERATIONS_ACTION_HINTS_ENDPOINT = '/api/discovery/operations-action-hints' as const;
export const DISCOVERY_OPERATIONS_ACTION_HINTS_VERSION = 1 as const;

export type DiscoveryOperationsActionHintSeverity = 'critical' | 'warning' | 'note' | 'info';
export type DiscoveryOperationsActionHintType = 'review_category_mapping' | 'review_type_mapping' | 'improve_source_extraction' | 'improve_audio_language_extraction' | 'improve_subtitle_language_extraction' | 'inspect_missing_core_metadata' | 'inspect_weakly_discoverable_torrents' | 'no_action_required';
export type DiscoveryOperationsActionHintDiscoveryStatus = 'discovery_ready' | 'weakly_discoverable' | 'missing_core_metadata';
export type DiscoveryOperationsActionHintMetadataField = 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year';
export type DiscoveryOperationsActionHintPriorityType = 'missing_core_metadata' | 'low_discovery_readiness' | 'weak_category_or_type_coverage' | 'weak_audio_subtitle_source_coverage' | 'healthy_discovery_condition' | 'no_visible_torrents';

export interface DiscoveryOperationsActionHintFilters {
  field: DiscoveryOperationsActionHintMetadataField | null;
  status: DiscoveryOperationsActionHintDiscoveryStatus | null;
  priority: DiscoveryOperationsActionHintPriorityType | null;
  available_fields: DiscoveryOperationsActionHintMetadataField[];
  available_statuses: DiscoveryOperationsActionHintDiscoveryStatus[];
  available_priorities: DiscoveryOperationsActionHintPriorityType[];
}

export interface DiscoveryOperationsActionHintSummary {
  total_hints: number;
  field: DiscoveryOperationsActionHintMetadataField | null;
  status: DiscoveryOperationsActionHintDiscoveryStatus | null;
  priority: DiscoveryOperationsActionHintPriorityType | null;
  recommended_staff_focus: string;
  highest_severity: DiscoveryOperationsActionHintSeverity | null;
}

export interface DiscoveryOperationsActionHint {
  id: string;
  type: DiscoveryOperationsActionHintType;
  severity: DiscoveryOperationsActionHintSeverity;
  title: string;
  description: string;
  applies_to_fields: DiscoveryOperationsActionHintMetadataField[];
  applies_to_statuses: DiscoveryOperationsActionHintDiscoveryStatus[];
  applies_to_priorities: DiscoveryOperationsActionHintPriorityType[];
  recommended_staff_action: string;
  reason: string;
  readonly: true;
  mutation_allowed: false;
}

export interface DiscoveryOperationsActionHintsResponse {
  version: typeof DISCOVERY_OPERATIONS_ACTION_HINTS_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  filters: DiscoveryOperationsActionHintFilters;
  summary: DiscoveryOperationsActionHintSummary;
  action_hints: DiscoveryOperationsActionHint[];
}

export interface DiscoveryOperationsActionHintsQuery {
  field?: DiscoveryOperationsActionHintMetadataField | '';
  status?: DiscoveryOperationsActionHintDiscoveryStatus | '';
  priority?: DiscoveryOperationsActionHintPriorityType | '';
}

export async function fetchDiscoveryOperationsActionHints(query: DiscoveryOperationsActionHintsQuery = {}): Promise<DiscoveryOperationsActionHintsResponse> {
  const params = new URLSearchParams();

  Object.entries(query).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const suffix = params.toString();

  return fetchJson<DiscoveryOperationsActionHintsResponse>(`${DISCOVERY_OPERATIONS_ACTION_HINTS_ENDPOINT}${suffix ? `?${suffix}` : ''}`);
}
