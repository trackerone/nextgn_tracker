import { useEffect, useMemo, useState } from 'react';
import { Download, Film, Gamepad2, Globe, Headphones, Package, Search, SlidersHorizontal, Tv } from 'lucide-react';
import type { BrowseCategory, BrowseFacetGroup, TorrentKind } from '@nextgn/api-contract';
import { apiClient, resolveApiUrl } from './api/client';

const categories: { label: string; value: BrowseCategory }[] = [
  { label: 'Movies', value: 'movies' },
  { label: 'TV shows', value: 'tv_shows' },
  { label: 'Music', value: 'music' },
  { label: 'Games', value: 'games' },
  { label: 'Software', value: 'software' },
  { label: 'All', value: 'all' },
];

const browseFacetGroups: Record<BrowseCategory, BrowseFacetGroup[]> = {
  movies: [
    {
      id: 'quality',
      label: 'Quality',
      options: [
        { id: 'sd', label: 'SD' },
        { id: '720p', label: '720p' },
        { id: '1080p', label: '1080p' },
        { id: '2160p', label: '2160p' },
      ],
    },
  ],
  tv_shows: [
    {
      id: 'quality',
      label: 'Quality',
      options: [
        { id: 'sd', label: 'SD' },
        { id: '720p', label: '720p' },
        { id: '1080p', label: '1080p' },
        { id: '2160p', label: '2160p' },
      ],
    },
  ],
  games: [],
  music: [],
  software: [],
  all: [],
};

type TorrentListItem = {
  id: number;
  slug: string;
  name: string;
  category: { id: number; name: string; slug: string } | null;
  type: TorrentKind;
  size_human: string;
  seeders: number;
  leechers: number;
  completed: number;
  uploaded_at_human: string | null;
};

type TorrentDetails = TorrentListItem & {
  uploader: { id: number; name: string } | null;
  download_url: string;
  magnet_url: string;
};

type TorrentListResponse = {
  data: TorrentListItem[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

type TorrentDetailsResponse = {
  data: TorrentDetails;
};

const iconMap: Record<TorrentKind, JSX.Element> = {
  movie: <Film className="h-4 w-4 text-amber-300" />,
  tv: <Tv className="h-4 w-4 text-sky-300" />,
  music: <Headphones className="h-4 w-4 text-emerald-300" />,
  game: <Gamepad2 className="h-4 w-4 text-fuchsia-300" />,
  software: <Package className="h-4 w-4 text-slate-300" />,
  other: <Globe className="h-4 w-4 text-slate-300" />,
};

const audioSubtitleBlock = (
  <div className="space-y-3">
    <div className="text-xs uppercase tracking-wide text-slate-400">Audio & subtitles</div>
    <div className="flex flex-wrap gap-2">
      <button type="button" className="rounded border border-slate-700 px-3 py-1 text-xs text-slate-200 hover:border-slate-500">
        Add audio filter
      </button>
      <button type="button" className="rounded border border-slate-700 px-3 py-1 text-xs text-slate-200 hover:border-slate-500">
        Add subtitle filter
      </button>
    </div>
  </div>
);

const FacetContent = ({
  facetGroups,
  showAudioSubtitle,
}: {
  facetGroups: BrowseFacetGroup[];
  showAudioSubtitle: boolean;
}) =>
  facetGroups.length > 0 || showAudioSubtitle ? (
    <div className="space-y-6">
      {facetGroups.map((group) => (
        <details key={group.id} open className="space-y-3">
          <summary className="cursor-pointer text-sm font-semibold text-slate-100">{group.label}</summary>
          <div className="space-y-2">
            {group.options.map((option) => (
              <label key={option.id} className="flex items-center gap-2 text-xs text-slate-300">
                <input type="checkbox" className="h-3 w-3 rounded border-slate-600 bg-slate-900 text-emerald-400" />
                {option.label}
              </label>
            ))}
          </div>
        </details>
      ))}
      {showAudioSubtitle && audioSubtitleBlock}
    </div>
  ) : (
    <p className="text-xs text-slate-500">No filters available.</p>
  );

const resolveCategory = (item: TorrentListItem): BrowseCategory => {
  const slug = item.category?.slug;
  if (slug === 'movies' || slug === 'tv_shows' || slug === 'music' || slug === 'games' || slug === 'software') {
    return slug;
  }

  const typeMap: Record<TorrentKind, BrowseCategory> = {
    movie: 'movies',
    tv: 'tv_shows',
    music: 'music',
    game: 'games',
    software: 'software',
    other: 'all',
  };

  return typeMap[item.type] ?? 'all';
};

const usePath = (): [string, (to: string) => void] => {
  const [path, setPath] = useState(window.location.pathname);

  useEffect(() => {
    const onPopState = () => setPath(window.location.pathname);
    window.addEventListener('popstate', onPopState);

    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  const navigate = (to: string) => {
    window.history.pushState({}, '', to);
    setPath(to);
  };

  return [path, navigate];
};

const BrowsePage = ({ onOpenDetails }: { onOpenDetails: (torrent: string) => void }) => {
  const [activeCategory, setActiveCategory] = useState<BrowseCategory>('movies');
  const [showFacets, setShowFacets] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [page, setPage] = useState(1);
  const [rows, setRows] = useState<TorrentListItem[]>([]);
  const [meta, setMeta] = useState<TorrentListResponse['meta'] | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const handle = window.setTimeout(() => {
      setDebouncedQuery(searchQuery.trim());
      setPage(1);
    }, 180);

    return () => window.clearTimeout(handle);
  }, [searchQuery]);

  useEffect(() => {
    let cancelled = false;

    const fetchRows = async () => {
      setLoading(true);
      setError(null);

      try {
        const query = new URLSearchParams({ page: String(page), per_page: '25' });
        if (debouncedQuery !== '') {
          query.set('q', debouncedQuery);
        }

        const response = await apiClient<TorrentListResponse>(`/api/torrents?${query.toString()}`);

        if (!cancelled) {
          setRows(response.data);
          setMeta(response.meta);
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError instanceof Error ? requestError.message : 'Failed to load torrents.');
          setRows([]);
          setMeta(null);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    void fetchRows();

    return () => {
      cancelled = true;
    };
  }, [debouncedQuery, page]);

  const filteredRows = useMemo(() => {
    if (activeCategory === 'all') {
      return rows;
    }

    return rows.filter((row) => resolveCategory(row) === activeCategory);
  }, [activeCategory, rows]);

  const facetGroups = browseFacetGroups[activeCategory];
  const showAudioSubtitle = activeCategory === 'movies' || activeCategory === 'tv_shows';

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100">
      <header className="border-b border-slate-800 bg-slate-950/80 px-6 py-4">
        <div className="mx-auto flex max-w-6xl items-center justify-between">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-500">NextGN</p>
            <h1 className="text-xl font-semibold text-slate-100">Browse</h1>
          </div>
          <div className="hidden items-center gap-2 text-xs text-slate-400 sm:flex">
            <SlidersHorizontal className="h-4 w-4" />
            <span>v4 tracker layout</span>
          </div>
        </div>
      </header>
      <main className="mx-auto grid max-w-6xl gap-6 px-6 py-6 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside className="hidden lg:block">
          <div className="rounded-lg border border-slate-800 bg-slate-900/60 p-4">
            <p className="mb-4 text-xs uppercase tracking-wide text-slate-400">Filters</p>
            <FacetContent facetGroups={facetGroups} showAudioSubtitle={showAudioSubtitle} />
          </div>
        </aside>
        <section className="space-y-4">
          <div className="flex flex-col gap-3">
            <div className="flex items-center justify-between">
              <div className="relative w-full max-w-xl">
                <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-500" />
                <input
                  type="search"
                  placeholder="Search..."
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                      setSearchQuery('');
                    }
                  }}
                  className="w-full rounded-md border border-slate-800 bg-slate-950/60 py-2 pl-10 pr-3 text-sm text-slate-200 placeholder:text-slate-600 focus:border-emerald-400 focus:outline-none"
                />
              </div>
            </div>
            <div className="flex items-center justify-between">
              <div className="flex flex-wrap gap-2">
                {categories.map((category) => (
                  <button
                    key={category.value}
                    type="button"
                    onClick={() => {
                      setActiveCategory(category.value);
                      setShowFacets(true);
                    }}
                    className={
                      category.value === activeCategory
                        ? 'rounded-full border border-emerald-400 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200'
                        : 'rounded-full border border-slate-700 px-3 py-1 text-xs text-slate-300 hover:border-slate-500'
                    }
                  >
                    {category.label}
                  </button>
                ))}
              </div>
              <button
                type="button"
                onClick={() => setShowFacets(true)}
                className="flex items-center gap-2 rounded border border-slate-700 px-3 py-1 text-xs text-slate-300 hover:border-slate-500 lg:hidden"
              >
                <SlidersHorizontal className="h-4 w-4" />
                Filters
              </button>
            </div>
          </div>

          {error && <div className="rounded border border-rose-700 bg-rose-950/40 px-3 py-2 text-sm text-rose-200">{error}</div>}

          <div className="overflow-hidden rounded-lg border border-slate-800 bg-slate-900/40">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-slate-800 bg-slate-900/80 text-slate-400">
                <tr>
                  <th className="px-3 py-2 font-semibold"></th>
                  <th className="px-3 py-2 font-semibold">Title</th>
                  <th className="px-3 py-2 font-semibold">When</th>
                  <th className="px-3 py-2 font-semibold">Size</th>
                  <th className="px-3 py-2 text-right font-semibold">D</th>
                  <th className="px-3 py-2 text-right font-semibold">S</th>
                  <th className="px-3 py-2 text-right font-semibold">L</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800">
                {loading ? (
                  <tr>
                    <td colSpan={7} className="px-3 py-6 text-center text-slate-400">
                      Loading torrents...
                    </td>
                  </tr>
                ) : filteredRows.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-3 py-6 text-center text-slate-400">
                      No torrents found.
                    </td>
                  </tr>
                ) : (
                  filteredRows.map((row) => (
                    <tr key={row.id} className="hover:bg-slate-900/80">
                      <td className="px-3 py-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded bg-slate-800">{iconMap[row.type] ?? iconMap.other}</div>
                      </td>
                      <td className="px-3 py-2">
                        <div className="flex items-center gap-3">
                          <button type="button" onClick={() => onOpenDetails(row.slug || String(row.id))} className="text-left text-sm text-slate-100 hover:text-emerald-200">
                            {row.name}
                          </button>
                        </div>
                      </td>
                      <td className="px-3 py-2 text-slate-300">{row.uploaded_at_human ?? '-'}</td>
                      <td className="px-3 py-2 text-slate-300">{row.size_human}</td>
                      <td className="px-3 py-2 text-right text-amber-300">{row.completed}</td>
                      <td className="px-3 py-2 text-right text-slate-200">{row.seeders}</td>
                      <td className="px-3 py-2 text-right text-slate-400">{row.leechers}</td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-between text-xs text-slate-400">
            <span>
              Page {meta?.current_page ?? page}
              {meta ? ` / ${meta.last_page}` : ''}
            </span>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setPage((current) => Math.max(1, current - 1))}
                disabled={page <= 1 || loading}
                className="rounded border border-slate-700 px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Previous
              </button>
              <button
                type="button"
                onClick={() => setPage((current) => current + 1)}
                disabled={loading || (meta !== null && page >= meta.last_page)}
                className="rounded border border-slate-700 px-3 py-1 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        </section>
      </main>
      {showFacets && (
        <div className="fixed inset-0 z-50 flex lg:hidden">
          <button type="button" onClick={() => setShowFacets(false)} className="absolute inset-0 bg-black/60" aria-label="Close filters" />
          <div className="relative ml-auto h-full w-72 bg-slate-950 p-4">
            <div className="mb-4 flex items-center justify-between">
              <p className="text-xs uppercase tracking-wide text-slate-400">Filters</p>
              <button type="button" onClick={() => setShowFacets(false)} className="text-xs text-slate-400">
                Close
              </button>
            </div>
            <div className="space-y-6 overflow-y-auto pb-6">
              <FacetContent facetGroups={facetGroups} showAudioSubtitle={showAudioSubtitle} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

const DetailsPage = ({ torrent, onBack }: { torrent: string; onBack: () => void }) => {
  const [details, setDetails] = useState<TorrentDetails | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    const fetchDetails = async () => {
      setLoading(true);
      setError(null);

      try {
        const response = await apiClient<TorrentDetailsResponse>(`/api/torrents/${encodeURIComponent(torrent)}`);
        if (!cancelled) {
          setDetails(response.data);
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError instanceof Error ? requestError.message : 'Failed to load torrent details.');
          setDetails(null);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    void fetchDetails();

    return () => {
      cancelled = true;
    };
  }, [torrent]);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100">
      <header className="border-b border-slate-800 bg-slate-950/80 px-6 py-4">
        <div className="mx-auto flex max-w-4xl items-center justify-between">
          <button type="button" onClick={onBack} className="text-sm text-slate-300 hover:text-slate-100">
            ← Back to browse
          </button>
        </div>
      </header>
      <main className="mx-auto max-w-4xl space-y-4 px-6 py-6">
        {loading && <p className="text-sm text-slate-400">Loading details...</p>}
        {error && <div className="rounded border border-rose-700 bg-rose-950/40 px-3 py-2 text-sm text-rose-200">{error}</div>}

        {details && (
          <div className="space-y-4 rounded-lg border border-slate-800 bg-slate-900/40 p-5">
            <h1 className="text-xl font-semibold text-slate-100">{details.name}</h1>
            <dl className="grid grid-cols-1 gap-3 text-sm text-slate-300 sm:grid-cols-2">
              <div>
                <dt className="text-slate-500">Category</dt>
                <dd>{details.category?.name ?? '-'}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Type</dt>
                <dd>{details.type}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Size</dt>
                <dd>{details.size_human}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Seeders</dt>
                <dd>{details.seeders}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Leechers</dt>
                <dd>{details.leechers}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Completed</dt>
                <dd>{details.completed}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Uploaded</dt>
                <dd>{details.uploaded_at_human ?? '-'}</dd>
              </div>
              <div>
                <dt className="text-slate-500">Uploader</dt>
                <dd>{details.uploader?.name ?? '-'}</dd>
              </div>
            </dl>
            <div className="flex gap-3">
              <a href={resolveApiUrl(details.download_url)} className="inline-flex items-center gap-2 rounded border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:border-slate-500">
                <Download className="h-4 w-4" />
                Download
              </a>
              <a href={details.magnet_url} className="inline-flex items-center gap-2 rounded border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:border-slate-500">
                Magnet
              </a>
            </div>
          </div>
        )}
      </main>
    </div>
  );
};

export default function App() {
  const [path, navigate] = usePath();
  const detailMatch = path.match(/^\/torrents\/([^/]+)$/);

  if (detailMatch?.[1] !== undefined) {
    return <DetailsPage torrent={decodeURIComponent(detailMatch[1])} onBack={() => navigate('/')} />;
  }

  return <BrowsePage onOpenDetails={(torrent) => navigate(`/torrents/${encodeURIComponent(torrent)}`)} />;
}
