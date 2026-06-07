import React, { useEffect, useRef, useState } from 'react';
import { AlertTriangle, BarChart3, RefreshCcw, Sparkles, TrendingUp } from 'lucide-react';
import {
  DISCOVERY_AGGREGATE_LIMIT,
  DISCOVERY_HOME_TRENDING_WINDOW,
  DiscoveryAggregateItem,
  DiscoveryAggregateSection,
  DiscoveryHomePayload,
  fetchDiscoveryHome,
} from '../../lib/discovery';

type DiscoveryWidgetStatus = 'loading' | 'ready' | 'empty' | 'error';

type DiscoverySectionKey = keyof DiscoveryAggregateSection;

const PREVIEW_ITEM_LIMIT = Math.min(5, DISCOVERY_AGGREGATE_LIMIT);

const SECTION_TITLES: Record<DiscoverySectionKey, string> = {
  sources: 'Sources',
  resolutions: 'Resolutions',
  release_groups: 'Release groups',
};

const formatCount = (value: number): string => value.toLocaleString();

const hasDiscoveryData = (payload: DiscoveryHomePayload): boolean => {
  const summary = payload.summary;
  const countsAreEmpty =
    summary.metadata.sources === 0 &&
    summary.metadata.resolutions === 0 &&
    summary.metadata.languages === 0 &&
    summary.metadata.audio_languages === 0 &&
    summary.metadata.subtitle_languages === 0 &&
    summary.metadata.release_groups === 0 &&
    summary.popular.sources === 0 &&
    summary.popular.resolutions === 0 &&
    summary.popular.release_groups === 0 &&
    summary.trending.sources === 0 &&
    summary.trending.resolutions === 0 &&
    summary.trending.release_groups === 0;

  const listsAreEmpty =
    payload.popular.sources.length === 0 &&
    payload.popular.resolutions.length === 0 &&
    payload.popular.release_groups.length === 0 &&
    payload.trending.sources.length === 0 &&
    payload.trending.resolutions.length === 0 &&
    payload.trending.release_groups.length === 0;

  return !(countsAreEmpty && listsAreEmpty);
};

interface MetricTileProps {
  label: string;
  value: number;
  tone?: 'neutral' | 'accent' | 'highlight';
}

const MetricTile: React.FC<MetricTileProps> = ({ label, value, tone = 'neutral' }) => {
  const toneClasses = {
    neutral: 'border-slate-800 bg-slate-950/70 text-slate-100',
    accent: 'border-brand/30 bg-brand/10 text-brand',
    highlight: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200',
  }[tone];

  return (
    <div className={`rounded-2xl border p-4 shadow-sm ${toneClasses}`}>
      <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-slate-50">{formatCount(value)}</p>
    </div>
  );
};

interface SectionCardProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  children: React.ReactNode;
}

const SectionCard: React.FC<SectionCardProps> = ({ icon, title, description, children }) => (
  <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5 shadow-sm">
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div>
        <div className="mb-2 inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-950/60 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-300">
          {icon}
          {title}
        </div>
        <p className="text-sm leading-6 text-slate-400">{description}</p>
      </div>
    </div>
    <div className="mt-4">{children}</div>
  </section>
);

interface AggregateColumnProps {
  title: string;
  items: DiscoveryAggregateItem[];
}

const AggregateColumn: React.FC<AggregateColumnProps> = ({ title, items }) => {
  const previewItems = items.slice(0, PREVIEW_ITEM_LIMIT);

  return (
    <div className="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
      <div className="flex items-center justify-between gap-3">
        <h4 className="text-sm font-semibold text-slate-100">{title}</h4>
        <span className="text-xs font-medium text-slate-500">{formatCount(items.length)} items</span>
      </div>
      {previewItems.length > 0 ? (
        <ul className="mt-3 space-y-2" aria-label={title}>
          {previewItems.map((item) => (
            <li
              key={item.value}
              className="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2"
            >
              <span className="min-w-0 truncate text-sm text-slate-200" title={item.value}>
                {item.value}
              </span>
              <span className="shrink-0 text-sm font-semibold text-slate-300">{formatCount(item.count)}</span>
            </li>
          ))}
        </ul>
      ) : (
        <div className="mt-3 rounded-xl border border-dashed border-slate-700 bg-slate-950/40 px-3 py-4 text-sm text-slate-400">
          No {title.toLowerCase()} yet.
        </div>
      )}
      {items.length > previewItems.length && (
        <p className="mt-3 text-xs text-slate-500">
          Showing the top {PREVIEW_ITEM_LIMIT} of up to {DISCOVERY_AGGREGATE_LIMIT} entries.
        </p>
      )}
    </div>
  );
};

const DiscoveryLandingWidget: React.FC = () => {
  const requestCounter = useRef(0);
  const [payload, setPayload] = useState<DiscoveryHomePayload | null>(null);
  const [status, setStatus] = useState<DiscoveryWidgetStatus>('loading');
  const [error, setError] = useState<string | null>(null);

  const loadDiscoveryHome = async () => {
    const requestId = ++requestCounter.current;

    setStatus('loading');
    setError(null);

    try {
      const nextPayload = await fetchDiscoveryHome();

      if (requestId !== requestCounter.current) {
        return;
      }

      setPayload(nextPayload);
      setStatus(hasDiscoveryData(nextPayload) ? 'ready' : 'empty');
    } catch (loadError) {
      if (requestId !== requestCounter.current) {
        return;
      }

      setPayload(null);
      setStatus('error');
      setError(loadError instanceof Error ? loadError.message : 'Could not load discovery data.');
    }
  };

  useEffect(() => {
    void loadDiscoveryHome();
  }, []);

  return (
    <section className="rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 p-6 shadow-sm" aria-busy={status === 'loading'}>
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-brand/30 bg-brand/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand">
            <BarChart3 className="h-3.5 w-3.5" aria-hidden="true" /> Discovery landing
          </div>
          <h2 className="text-2xl font-semibold text-slate-50">Discovery home widget</h2>
          <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
            First frontend consumer of the discovery home payload. This keeps the landing surface small while the API contract
            settles.
          </p>
        </div>
        <button
          type="button"
          onClick={() => void loadDiscoveryHome()}
          disabled={status === 'loading'}
          className="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-950/70 px-3 py-2 text-sm font-medium text-slate-200 hover:border-slate-600 hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
        >
          <RefreshCcw className={`h-4 w-4 ${status === 'loading' ? 'animate-spin' : ''}`} aria-hidden="true" />
          Refresh
        </button>
      </div>

      {status === 'loading' && (
        <div className="mt-6 space-y-4 animate-pulse" aria-live="polite">
          <div className="grid gap-3 md:grid-cols-3">
            <div className="h-28 rounded-2xl border border-slate-800 bg-slate-900/60" />
            <div className="h-28 rounded-2xl border border-slate-800 bg-slate-900/60" />
            <div className="h-28 rounded-2xl border border-slate-800 bg-slate-900/60" />
          </div>
          <div className="grid gap-4 xl:grid-cols-2">
            <div className="h-72 rounded-2xl border border-slate-800 bg-slate-900/60" />
            <div className="h-72 rounded-2xl border border-slate-800 bg-slate-900/60" />
          </div>
        </div>
      )}

      {status === 'error' && (
        <div className="mt-6 rounded-2xl border border-red-500/30 bg-red-500/10 p-5 text-sm text-red-100" role="alert">
          <div className="flex items-start gap-3">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-red-200" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="font-semibold text-red-50">Discovery home failed to load</p>
              <p className="mt-1 text-red-100/90">{error ?? 'The discovery landing widget could not load right now.'}</p>
              <button
                type="button"
                onClick={() => void loadDiscoveryHome()}
                className="mt-4 inline-flex items-center gap-2 rounded-lg border border-red-400/40 px-3 py-2 text-sm font-medium text-red-100 hover:bg-red-400/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
              >
                <RefreshCcw className="h-4 w-4" aria-hidden="true" /> Try again
              </button>
            </div>
          </div>
        </div>
      )}

      {status === 'empty' && (
        <div className="mt-6 rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-6 text-sm text-slate-400" role="status">
          <div className="flex items-start gap-3">
            <Sparkles className="mt-0.5 h-5 w-5 shrink-0 text-slate-500" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="font-semibold text-slate-200">Discovery data is empty for now</p>
              <p className="mt-1 text-slate-400">
                Summary, trending, and popular sections will appear here once the backend has visible metadata to aggregate.
              </p>
              <button
                type="button"
                onClick={() => void loadDiscoveryHome()}
                className="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 hover:border-slate-600 hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand"
              >
                <RefreshCcw className="h-4 w-4" aria-hidden="true" /> Reload
              </button>
            </div>
          </div>
        </div>
      )}

      {status === 'ready' && payload && (
        <div className="mt-6 space-y-6">
          <SectionCard
            icon={<BarChart3 className="h-3.5 w-3.5 text-brand" aria-hidden="true" />}
            title="Discovery summary"
            description="Quick counts for the discovery metadata and the aggregate windows that back this landing surface."
          >
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              <MetricTile label="Sources" value={payload.summary.metadata.sources} tone="accent" />
              <MetricTile label="Resolutions" value={payload.summary.metadata.resolutions} tone="accent" />
              <MetricTile label="Languages" value={payload.summary.metadata.languages} tone="neutral" />
              <MetricTile label="Audio languages" value={payload.summary.metadata.audio_languages} tone="neutral" />
              <MetricTile label="Subtitle languages" value={payload.summary.metadata.subtitle_languages} tone="neutral" />
              <MetricTile label="Release groups" value={payload.summary.metadata.release_groups} tone="highlight" />
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              <MetricTile label="Popular sources" value={payload.summary.popular.sources} tone="neutral" />
              <MetricTile label="Popular resolutions" value={payload.summary.popular.resolutions} tone="neutral" />
              <MetricTile label="Popular release groups" value={payload.summary.popular.release_groups} tone="neutral" />
              <MetricTile label={`Trending sources (${DISCOVERY_HOME_TRENDING_WINDOW})`} value={payload.summary.trending.sources} tone="accent" />
              <MetricTile label={`Trending resolutions (${DISCOVERY_HOME_TRENDING_WINDOW})`} value={payload.summary.trending.resolutions} tone="accent" />
              <MetricTile label={`Trending release groups (${DISCOVERY_HOME_TRENDING_WINDOW})`} value={payload.summary.trending.release_groups} tone="highlight" />
            </div>
          </SectionCard>

          <SectionCard
            icon={<TrendingUp className="h-3.5 w-3.5 text-brand" aria-hidden="true" />}
            title="Trending metadata"
            description={`The default ${DISCOVERY_HOME_TRENDING_WINDOW} window from the backend, with a compact preview of capped aggregate lists.`}
          >
            <div className="grid gap-4 xl:grid-cols-3">
              <AggregateColumn title={SECTION_TITLES.sources} items={payload.trending.sources} />
              <AggregateColumn title={SECTION_TITLES.resolutions} items={payload.trending.resolutions} />
              <AggregateColumn title={SECTION_TITLES.release_groups} items={payload.trending.release_groups} />
            </div>
          </SectionCard>

          <SectionCard
            icon={<Sparkles className="h-3.5 w-3.5 text-brand" aria-hidden="true" />}
            title="Popular metadata"
            description="All-time popular metadata shown in the same compact list pattern so the first landing consumer stays easy to scan."
          >
            <div className="grid gap-4 xl:grid-cols-3">
              <AggregateColumn title={SECTION_TITLES.sources} items={payload.popular.sources} />
              <AggregateColumn title={SECTION_TITLES.resolutions} items={payload.popular.resolutions} />
              <AggregateColumn title={SECTION_TITLES.release_groups} items={payload.popular.release_groups} />
            </div>
          </SectionCard>
        </div>
      )}
    </section>
  );
};

export default DiscoveryLandingWidget;
