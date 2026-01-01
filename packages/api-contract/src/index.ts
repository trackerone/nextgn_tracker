export interface TorrentDto {
  id: number;
  name: string;
  status: 'approved' | 'rejected' | 'soft_deleted';
}

export type BrowseCategory = 'movies' | 'tv_shows' | 'music' | 'games' | 'software' | 'all';

export interface BrowseFacetOption {
  id: string;
  label: string;
}

export interface BrowseFacetGroup {
  id: string;
  label: string;
  options: BrowseFacetOption[];
}

export type TorrentKind = 'movie' | 'tv' | 'music' | 'game' | 'software' | 'other';

export interface TorrentRow {
  id: number;
  title: string;
  category: BrowseCategory;
  when: string;
  size: string;
  uploads: number;
  downloads: number;
  seeders: number;
  leechers: number;
  kind: TorrentKind;
  tags?: string[];
  freeleech?: boolean;
}
