import { fetchJson } from './http';
import type { DiscoveryOperationsSummary, DiscoveryOperationsWeakestMetadataField, DiscoveryOperationsAttentionItem } from './discoveryOperationsOverview';

export const DISCOVERY_OPERATIONS_COMMAND_CENTER_ENDPOINT = '/api/discovery/operations-command-center' as const;
export const DISCOVERY_OPERATIONS_COMMAND_CENTER_VERSION = 1 as const;

export type DiscoveryOperationsCommandCenterSeverity = 'critical' | 'warning' | 'note' | 'info';
export type DiscoveryOperationsCommandCenterDiscoveryStatus = 'discovery_ready' | 'weakly_discoverable' | 'missing_core_metadata';
export type DiscoveryOperationsCommandCenterMetadataField = 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year';
export type DiscoveryOperationsCommandCenterPriorityType = 'missing_core_metadata' | 'low_discovery_readiness' | 'weak_category_or_type_coverage' | 'weak_audio_subtitle_source_coverage' | 'healthy_discovery_condition' | 'no_visible_torrents';
export type DiscoveryOperationsCommandCenterActionHintType = 'review_category_mapping' | 'review_type_mapping' | 'improve_source_extraction' | 'improve_audio_language_extraction' | 'improve_subtitle_language_extraction' | 'inspect_missing_core_metadata' | 'inspect_weakly_discoverable_torrents' | 'no_action_required';
export type DiscoveryOperationsCommandCenterFocusSource = 'review_queue' | 'priority' | 'action_hint' | 'health' | 'none';

export interface DiscoveryOperationsCommandCenterFilters {
  field: DiscoveryOperationsCommandCenterMetadataField | null;
  status: DiscoveryOperationsCommandCenterDiscoveryStatus | null;
  priority: DiscoveryOperationsCommandCenterPriorityType | null;
  severity: DiscoveryOperationsCommandCenterSeverity | null;
  available_fields: DiscoveryOperationsCommandCenterMetadataField[];
  available_statuses: DiscoveryOperationsCommandCenterDiscoveryStatus[];
  available_priorities: DiscoveryOperationsCommandCenterPriorityType[];
  available_severities: DiscoveryOperationsCommandCenterSeverity[];
}

export interface DiscoveryOperationsCommandCenterSummary {
  total_visible_torrents: number;
  discovery_readiness_rate: number;
  total_priorities: number;
  total_action_hints: number;
  total_queue_items: number;
  critical_queue_items: number;
  warning_queue_items: number;
  note_queue_items: number;
  info_queue_items: number;
  highest_severity: DiscoveryOperationsCommandCenterSeverity | null;
  recommended_staff_focus: string;
}

export interface DiscoveryOperationsCommandCenterNextStaffFocus {
  severity: DiscoveryOperationsCommandCenterSeverity;
  title: string;
  recommended_staff_action: string;
  reason: string;
  source: DiscoveryOperationsCommandCenterFocusSource;
}

export interface DiscoveryOperationsCommandCenterPriority {
  type: DiscoveryOperationsCommandCenterPriorityType;
  severity: DiscoveryOperationsCommandCenterSeverity;
  title: string;
  message: string;
  reason: string;
  recommended_staff_action: string;
}

export interface DiscoveryOperationsCommandCenterActionHint {
  id: string;
  type: DiscoveryOperationsCommandCenterActionHintType;
  severity: DiscoveryOperationsCommandCenterSeverity;
  title: string;
  recommended_staff_action: string;
  reason: string;
  readonly: true;
  mutation_allowed: false;
}

export interface DiscoveryOperationsCommandCenterReviewQueueItem {
  id: string;
  torrent_id: number;
  torrent_name: string;
  discovery_status: DiscoveryOperationsCommandCenterDiscoveryStatus;
  metadata_field: DiscoveryOperationsCommandCenterMetadataField;
  priority_type: DiscoveryOperationsCommandCenterPriorityType;
  severity: DiscoveryOperationsCommandCenterSeverity;
  issue_title: string;
  issue_summary: string;
  explanation: string;
  recommended_staff_action: string;
  action_hint_type: DiscoveryOperationsCommandCenterActionHintType;
  readonly: true;
  mutation_allowed: false;
}

export interface DiscoveryOperationsCommandCenterResponse {
  version: typeof DISCOVERY_OPERATIONS_COMMAND_CENTER_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  filters: DiscoveryOperationsCommandCenterFilters;
  summary: DiscoveryOperationsCommandCenterSummary;
  health: { readonly: true; metadata_first: true; personalized: false; metrics: Record<string, number | boolean>; indicators: Record<string, boolean> };
  overview: { readonly: true; summary: DiscoveryOperationsSummary; weakest_metadata_fields: DiscoveryOperationsWeakestMetadataField[]; attention_items: DiscoveryOperationsAttentionItem[] };
  priorities: DiscoveryOperationsCommandCenterPriority[];
  action_hints: DiscoveryOperationsCommandCenterActionHint[];
  review_queue: DiscoveryOperationsCommandCenterReviewQueueItem[];
  next_staff_focus: DiscoveryOperationsCommandCenterNextStaffFocus;
}

export interface DiscoveryOperationsCommandCenterQuery {
  field?: DiscoveryOperationsCommandCenterMetadataField | '';
  status?: DiscoveryOperationsCommandCenterDiscoveryStatus | '';
  priority?: DiscoveryOperationsCommandCenterPriorityType | '';
  severity?: DiscoveryOperationsCommandCenterSeverity | '';
}

export async function fetchDiscoveryOperationsCommandCenter(query: DiscoveryOperationsCommandCenterQuery = {}): Promise<DiscoveryOperationsCommandCenterResponse> {
  const params = new URLSearchParams();

  Object.entries(query).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const suffix = params.toString();

  return fetchJson<DiscoveryOperationsCommandCenterResponse>(`${DISCOVERY_OPERATIONS_COMMAND_CENTER_ENDPOINT}${suffix ? `?${suffix}` : ''}`);
}
