import { useEffect, useMemo, useState } from 'react';
import { Download, Film, Gamepad2, Globe, Headphones, Package, Search, SlidersHorizontal, Tv } from 'lucide-react';
import type { BrowseCategory, BrowseFacetGroup, TorrentRow } from '@nextgn/api-contract';
import { browseFacetGroups, browseRows } from './data/browseMock';
const categories: { label: string; value: BrowseCategory }[] = [
  { label: 'Movies', value: 'movies' },
  { label: 'TV shows', value: 'tv_shows' },
  { label: 'Music', value: 'music' },
  { label: 'Games', value: 'games' },
  { label: 'Software', value: 'software' },
  { label: 'All', value: 'all' },
];
const iconMap: Record<TorrentRow['kind'], JSX.Element> = {
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
export default function App() {
  const [activeCategory, setActiveCategory] = useState<BrowseCategory>('movies');
  const [showFacets, setShowFacets] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  useEffect(() => {
    const handle = window.setTimeout(() => {
      setDebouncedQuery(searchQuery.trim());
    }, 180);
    return () => window.clearTimeout(handle);
  }, [searchQuery]);
  const normalizedQuery = debouncedQuery.toLowerCase();
  const filteredRows = useMemo(
    () =>
      browseRows.filter((row) => {
        if (activeCategory !== 'all' && row.category !== activeCategory) {
          return false;
        }
        if (!normalizedQuery) {
          return true;
        }
        const tagsMatch = row.tags?.some((tag) => tag.toLowerCase().includes(normalizedQuery));
        return row.title.toLowerCase().includes(normalizedQuery) || Boolean(tagsMatch);
      }),
    [activeCategory, normalizedQuery],
  );
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
                {filteredRows.map((row) => (
                  <tr key={row.id} className="hover:bg-slate-900/80">
                    <td className="px-3 py-2">
                      <div className="flex h-8 w-8 items-center justify-center rounded bg-slate-800">
                        {iconMap[row.kind]}
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                          <span className="text-sm text-slate-100">{row.title}</span>
                          {row.freeleech && (
                            <span className="rounded border border-emerald-400/40 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-200">
                              F2L
                            </span>
                          )}
                        </div>
                        <button type="button" className="rounded border border-slate-700 p-1 text-slate-300 hover:border-slate-500">
                          <Download className="h-3 w-3" />
                        </button>
                      </div>
                    </td>
                    <td className="px-3 py-2 text-slate-300">{row.when}</td>
                    <td className="px-3 py-2 text-slate-300">{row.size}</td>
                    <td className="px-3 py-2 text-right text-amber-300">{row.downloads}</td>
                    <td className="px-3 py-2 text-right text-slate-200">{row.seeders}</td>
                    <td className="px-3 py-2 text-right text-slate-400">{row.leechers}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </main>
      {showFacets && (
        <div className="fixed inset-0 z-50 flex lg:hidden">
          <button
            type="button"
            onClick={() => setShowFacets(false)}
            className="absolute inset-0 bg-black/60"
            aria-label="Close filters"
          />
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
}
