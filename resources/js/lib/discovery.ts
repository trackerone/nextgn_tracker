import { fetchJson } from './http';

export const DISCOVERY_HOME_ENDPOINT = '/api/discovery/home' as const;
export const DISCOVERY_RSS_SUGGESTIONS_ENDPOINT = '/api/discovery/rss-suggestions' as const;
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

export interface DiscoveryAggregateSection {
  sources: DiscoveryAggregateItem[];
  resolutions: DiscoveryAggregateItem[];
  release_groups: DiscoveryAggregateItem[];
}

export interface DiscoveryHomeTrendingSection extends DiscoveryAggregateSection {
  window: typeof DISCOVERY_HOME_TRENDING_WINDOW;
}

export interface DiscoveryHomeSummary {
  metadata: DiscoveryMetadataSummary;
  popular: DiscoveryAggregateSummary;
  trending: DiscoveryTrendingSummary;
}

export interface DiscoveryHomePayload {
  summary: DiscoveryHomeSummary;
  trending: DiscoveryHomeTrendingSection;
  popular: DiscoveryAggregateSection;
}

export type DiscoveryRssSuggestionCategory = 'sources' | 'resolutions' | 'languages' | 'release_groups';

export interface DiscoveryRssSuggestionsPayload {
  sources: DiscoveryAggregateItem[];
  resolutions: DiscoveryAggregateItem[];
  languages: DiscoveryAggregateItem[];
  release_groups: DiscoveryAggregateItem[];
}

export interface DiscoveryRssSuggestionsOptions<TCategory extends DiscoveryRssSuggestionCategory = DiscoveryRssSuggestionCategory> {
  category?: TCategory;
}

function discoveryRssSuggestionsUrl(category?: DiscoveryRssSuggestionCategory): string {
  if (!category) {
    return DISCOVERY_RSS_SUGGESTIONS_ENDPOINT;
  }

  const params = new URLSearchParams({ category });

  return `${DISCOVERY_RSS_SUGGESTIONS_ENDPOINT}?${params.toString()}`;
}

export async function fetchDiscoveryHome(): Promise<DiscoveryHomePayload> {
  return fetchJson<DiscoveryHomePayload>(DISCOVERY_HOME_ENDPOINT);
}

export async function fetchDiscoveryRssSuggestions(): Promise<DiscoveryRssSuggestionsPayload>;
export async function fetchDiscoveryRssSuggestions<TCategory extends DiscoveryRssSuggestionCategory>(
  options: DiscoveryRssSuggestionsOptions<TCategory>,
): Promise<Pick<DiscoveryRssSuggestionsPayload, TCategory>>;
export async function fetchDiscoveryRssSuggestions(
  options: DiscoveryRssSuggestionsOptions = {},
): Promise<DiscoveryRssSuggestionsPayload | Partial<DiscoveryRssSuggestionsPayload>> {
  return fetchJson<DiscoveryRssSuggestionsPayload | Partial<DiscoveryRssSuggestionsPayload>>(
    discoveryRssSuggestionsUrl(options.category),
  );
}
