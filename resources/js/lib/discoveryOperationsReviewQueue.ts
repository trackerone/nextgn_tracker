import { fetchJson } from './http';

export const DISCOVERY_OPERATIONS_REVIEW_QUEUE_ENDPOINT = '/api/discovery/operations-review-queue' as const;
export const DISCOVERY_OPERATIONS_REVIEW_QUEUE_VERSION = 1 as const;

export type DiscoveryOperationsReviewQueueSeverity = 'critical' | 'warning' | 'note' | 'info';
export type DiscoveryOperationsReviewQueueDiscoveryStatus = 'discovery_ready' | 'weakly_discoverable' | 'missing_core_metadata';
export type DiscoveryOperationsReviewQueueMetadataField = 'category' | 'type' | 'resolution' | 'source' | 'language' | 'audio_language' | 'subtitle_language' | 'release_group' | 'year';
export type DiscoveryOperationsReviewQueuePriorityType = 'missing_core_metadata' | 'low_discovery_readiness' | 'weak_category_or_type_coverage' | 'weak_audio_subtitle_source_coverage' | 'healthy_discovery_condition' | 'no_visible_torrents';
export type DiscoveryOperationsReviewQueueActionHintType = 'review_category_mapping' | 'review_type_mapping' | 'improve_source_extraction' | 'improve_audio_language_extraction' | 'improve_subtitle_language_extraction' | 'inspect_missing_core_metadata' | 'inspect_weakly_discoverable_torrents' | 'no_action_required';

export interface DiscoveryOperationsReviewQueueFilters {
  field: DiscoveryOperationsReviewQueueMetadataField | null;
  status: DiscoveryOperationsReviewQueueDiscoveryStatus | null;
  priority: DiscoveryOperationsReviewQueuePriorityType | null;
  severity: DiscoveryOperationsReviewQueueSeverity | null;
  available_fields: DiscoveryOperationsReviewQueueMetadataField[];
  available_statuses: DiscoveryOperationsReviewQueueDiscoveryStatus[];
  available_priorities: DiscoveryOperationsReviewQueuePriorityType[];
  available_severities: DiscoveryOperationsReviewQueueSeverity[];
}

export interface DiscoveryOperationsReviewQueueSummary {
  total_queue_items: number;
  field: DiscoveryOperationsReviewQueueMetadataField | null;
  status: DiscoveryOperationsReviewQueueDiscoveryStatus | null;
  priority: DiscoveryOperationsReviewQueuePriorityType | null;
  severity: DiscoveryOperationsReviewQueueSeverity | null;
  critical_items: number;
  warning_items: number;
  note_items: number;
  info_items: number;
  recommended_staff_focus: string;
}

export interface DiscoveryOperationsReviewQueueItem {
  id: string;
  torrent_id: number;
  torrent_name: string;
  discovery_status: DiscoveryOperationsReviewQueueDiscoveryStatus;
  metadata_field: DiscoveryOperationsReviewQueueMetadataField;
  priority_type: DiscoveryOperationsReviewQueuePriorityType;
  severity: DiscoveryOperationsReviewQueueSeverity;
  issue_title: string;
  issue_summary: string;
  explanation: string;
  recommended_staff_action: string;
  action_hint_type: DiscoveryOperationsReviewQueueActionHintType;
  readonly: true;
  mutation_allowed: false;
}

export interface DiscoveryOperationsReviewQueueResponse {
  version: typeof DISCOVERY_OPERATIONS_REVIEW_QUEUE_VERSION;
  readonly: true;
  metadata_first: true;
  personalized: false;
  uses_user_history: false;
  uses_download_history: false;
  uses_watch_history: false;
  filters: DiscoveryOperationsReviewQueueFilters;
  summary: DiscoveryOperationsReviewQueueSummary;
  queue: DiscoveryOperationsReviewQueueItem[];
}

export interface DiscoveryOperationsReviewQueueQuery {
  field?: DiscoveryOperationsReviewQueueMetadataField | '';
  status?: DiscoveryOperationsReviewQueueDiscoveryStatus | '';
  priority?: DiscoveryOperationsReviewQueuePriorityType | '';
  severity?: DiscoveryOperationsReviewQueueSeverity | '';
}

export async function fetchDiscoveryOperationsReviewQueue(query: DiscoveryOperationsReviewQueueQuery = {}): Promise<DiscoveryOperationsReviewQueueResponse> {
  const params = new URLSearchParams();

  Object.entries(query).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  const suffix = params.toString();

  return fetchJson<DiscoveryOperationsReviewQueueResponse>(`${DISCOVERY_OPERATIONS_REVIEW_QUEUE_ENDPOINT}${suffix ? `?${suffix}` : ''}`);
}
