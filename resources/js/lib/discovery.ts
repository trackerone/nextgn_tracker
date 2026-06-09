import { fetchJson } from './http';

export const DISCOVERY_HOME_ENDPOINT = '/api/discovery/home' as const;
export const DISCOVERY_RSS_SUGGESTIONS_ENDPOINT = '/api/discovery/rss-suggestions' as const;
export const DISCOVERY_WATCH_PRESET_SUGGESTIONS_ENDPOINT = '/api/discovery/watch-preset-suggestions' as const;
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

export type DiscoverySuggestionCategory = 'sources' | 'resolutions' | 'languages' | 'release_groups';
export type DiscoveryRssSuggestionCategory = DiscoverySuggestionCategory;
export type DiscoveryWatchPresetSuggestionCategory = DiscoverySuggestionCategory;

export interface DiscoverySuggestionsPayload {
  sources: DiscoveryAggregateItem[];
  resolutions: DiscoveryAggregateItem[];
  languages: DiscoveryAggregateItem[];
  release_groups: DiscoveryAggregateItem[];
}

export interface DiscoveryRssSuggestionsPayload extends DiscoverySuggestionsPayload {}

export interface DiscoveryWatchPresetSuggestionsPayload extends DiscoverySuggestionsPayload {}

export interface DiscoverySuggestionsOptions<TCategory extends DiscoverySuggestionCategory = DiscoverySuggestionCategory> {
  category?: TCategory;
}

export interface DiscoveryRssSuggestionsOptions<TCategory extends DiscoveryRssSuggestionCategory = DiscoveryRssSuggestionCategory>
  extends DiscoverySuggestionsOptions<TCategory> {}

export interface DiscoveryWatchPresetSuggestionsOptions<
  TCategory extends DiscoveryWatchPresetSuggestionCategory = DiscoveryWatchPresetSuggestionCategory,
> extends DiscoverySuggestionsOptions<TCategory> {}

function discoverySuggestionsUrl(endpoint: string, category?: DiscoverySuggestionCategory): string {
  if (!category) {
    return endpoint;
  }

  const params = new URLSearchParams({ category });

  return `${endpoint}?${params.toString()}`;
}

function discoveryRssSuggestionsUrl(category?: DiscoveryRssSuggestionCategory): string {
  return discoverySuggestionsUrl(DISCOVERY_RSS_SUGGESTIONS_ENDPOINT, category);
}

function discoveryWatchPresetSuggestionsUrl(category?: DiscoveryWatchPresetSuggestionCategory): string {
  return discoverySuggestionsUrl(DISCOVERY_WATCH_PRESET_SUGGESTIONS_ENDPOINT, category);
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

export async function fetchDiscoveryWatchPresetSuggestions(): Promise<DiscoveryWatchPresetSuggestionsPayload>;
export async function fetchDiscoveryWatchPresetSuggestions<TCategory extends DiscoveryWatchPresetSuggestionCategory>(
  options: DiscoveryWatchPresetSuggestionsOptions<TCategory>,
): Promise<Pick<DiscoveryWatchPresetSuggestionsPayload, TCategory>>;
export async function fetchDiscoveryWatchPresetSuggestions(
  options: DiscoveryWatchPresetSuggestionsOptions = {},
): Promise<DiscoveryWatchPresetSuggestionsPayload | Partial<DiscoveryWatchPresetSuggestionsPayload>> {
  return fetchJson<DiscoveryWatchPresetSuggestionsPayload | Partial<DiscoveryWatchPresetSuggestionsPayload>>(
    discoveryWatchPresetSuggestionsUrl(options.category),
  );
}
