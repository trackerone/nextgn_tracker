import { fetchJson } from './http';

export const DISCOVERY_HOME_ENDPOINT = '/api/discovery/home' as const;
export const DISCOVERY_HOME_TRENDING_WINDOW = '30d' as const;
export const DISCOVERY_AGGREGATE_LIMIT = 25 as const;

export interface DiscoveryAggregateItem {
  value: string;
  count: number;
}

export interface DiscoveryMetadataSummary {
  sources: number;
  resolutions: number;
  languages: number;
  audio_languages: number;
  subtitle_languages: number;
  release_groups: number;
}

export interface DiscoveryAggregateSummary {
  sources: number;
  resolutions: number;
  release_groups: number;
}

export interface DiscoveryTrendingSummary extends DiscoveryAggregateSummary {
  window: typeof DISCOVERY_HOME_TRENDING_WINDOW;
}

export interface DiscoveryTrendingWindow {
  window: typeof DISCOVERY_HOME_TRENDING_WINDOW;
}

export interface DiscoveryAggregateSection {
  sources: DiscoveryAggregateItem[];
  resolutions: DiscoveryAggregateItem[];
  release_groups: DiscoveryAggregateItem[];
}

export interface DiscoveryHomeSummary {
  metadata: DiscoveryMetadataSummary;
  popular: DiscoveryAggregateSummary;
  trending: DiscoveryTrendingSummary;
}

export interface DiscoveryHomePayload {
  summary: DiscoveryHomeSummary;
  trending: DiscoveryTrendingWindow & DiscoveryAggregateSection;
  popular: DiscoveryAggregateSection;
}

export async function fetchDiscoveryHome(): Promise<DiscoveryHomePayload> {
  return fetchJson<DiscoveryHomePayload>(DISCOVERY_HOME_ENDPOINT);
}
