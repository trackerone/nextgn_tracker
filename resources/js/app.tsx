import '../css/app.css';
import React from 'react';
import ReactDOM from 'react-dom/client';
import CreateTopicForm from './components/forum/CreateTopicForm';
import TopicList from './components/forum/TopicList';
import TopicView from './components/forum/TopicView';
import DiscoveryLandingWidget from './components/discovery/DiscoveryLandingWidget';
import BrowseDiscoveryTeaser from './components/discovery/BrowseDiscoveryTeaser';
import RssDiscoverySuggestions from './components/discovery/RssDiscoverySuggestions';
import WatchDiscoverySuggestions from './components/discovery/WatchDiscoverySuggestions';
import DiscoveryHealthPanel from './components/discovery/DiscoveryHealthPanel';
import DiscoveryExplainabilityPanel from './components/discovery/DiscoveryExplainabilityPanel';
import DiscoveryOperationsOverviewPanel from './components/discovery/DiscoveryOperationsOverviewPanel';
import RecommendationSignalsPanel from './components/discovery/RecommendationSignalsPanel';
import RecommendationEngineFoundationPanel from './components/discovery/RecommendationEngineFoundationPanel';
import RecommendationCandidatesPanel from './components/discovery/RecommendationCandidatesPanel';
import RecommendationOutputPanel from './components/discovery/RecommendationOutputPanel';
import RecommendationPreviewPanel from './components/discovery/RecommendationPreviewPanel';
import RecommendationTorrentsPanel from './components/discovery/RecommendationTorrentsPanel';
import RecommendationHealthPanel from './components/discovery/RecommendationHealthPanel';
import RecommendationExplainabilityPanel from './components/discovery/RecommendationExplainabilityPanel';
import { MessageCircle } from 'lucide-react';
import { SessionContext } from './components/forum/types';
import { useForum } from './components/forum/useForum';
import PrivateMessagesPanel from './components/pm/PrivateMessagesPanel';

declare global {
  interface Window {
    __APP__?: SessionContext;
  }
}

const defaultSession: SessionContext = {
  authenticated: false,
  canWrite: false,
  canModerate: false,
  canAdmin: false,
  user: null,
};

const App: React.FC = () => {
  const sessionConfig = window.__APP__ ?? defaultSession;
  const {
    session,
    topicsMeta,
    selectedTopic,
    posts,
    error,
    isLoading,
    sortedTopics,
    handleCreateTopic,
    handleSelectTopic,
    handleReply,
    handleToggleLock,
    handleTogglePin,
    handleDeleteTopic,
    handleDeletePost,
    handleRestorePost,
    handleEditPost,
  } = useForum(sessionConfig);

  return (
    <main className="mx-auto flex w-full max-w-6xl flex-col gap-8 px-6 py-10">
      <section className="rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 p-6 shadow-sm">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-brand/30 bg-brand/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand">
              <MessageCircle className="h-3.5 w-3.5" aria-hidden="true" /> Community forum
            </div>
            <h1 className="text-3xl font-semibold text-slate-50">Discuss releases, requests and tracker life</h1>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
              Browse active topics, spot moderation state quickly and keep conversations readable across devices.
            </p>
          </div>
          {topicsMeta && (
            <span className="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-sm font-medium text-slate-300">
              {topicsMeta.total} topics
            </span>
          )}
        </div>
      </section>

      <section id="discovery" className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-brand">Discovery</p>
            <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-400">
              Dedicated landing entry point for the discovery home payload. The widget below stays reusable while the broader
              landing surface evolves.
            </p>
          </div>
          <a
            href="#forum"
            className="rounded-full border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 hover:border-brand/60 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand"
          >
            Back to forum
          </a>
        </div>
        <DiscoveryLandingWidget />
      </section>

      <div className="grid gap-8 lg:grid-cols-[minmax(280px,380px)_1fr] lg:items-start">
        <section id="forum" className="space-y-6 lg:sticky lg:top-6">
          {session.canWrite && (
            <div id="create-topic">
              <CreateTopicForm onSubmit={handleCreateTopic} disabled={!session.authenticated} />
            </div>
          )}
          {error && <p className="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200" role="alert">{error}</p>}
          {isLoading && <p className="text-sm text-slate-400" aria-live="polite">Loading discussion...</p>}
          <TopicList topics={sortedTopics} selectedTopicId={selectedTopic?.id ?? null} onSelectTopic={handleSelectTopic} />
        </section>
        <TopicView
          topic={selectedTopic}
          posts={posts}
          session={session}
          onReply={handleReply}
          onToggleLock={session.canModerate ? handleToggleLock : undefined}
          onTogglePin={session.canModerate ? handleTogglePin : undefined}
          onDeleteTopic={session.canAdmin ? handleDeleteTopic : undefined}
          onPostDelete={handleDeletePost}
          onPostRestore={handleRestorePost}
          onPostEdit={handleEditPost}
        />
      </div>
      {session.user && (
        <PrivateMessagesPanel currentUserId={session.user.id} />
      )}
    </main>
  );
};

const element = document.getElementById('app');

if (element) {
  ReactDOM.createRoot(element).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>,
  );
}

const browseDiscoveryTeaserElement = document.querySelector<HTMLElement>('[data-discovery-browse-teaser]');

if (browseDiscoveryTeaserElement) {
  const discoveryUrl = browseDiscoveryTeaserElement.dataset.discoveryUrl ?? '/account/discovery';

  ReactDOM.createRoot(browseDiscoveryTeaserElement).render(
    <React.StrictMode>
      <BrowseDiscoveryTeaser discoveryUrl={discoveryUrl} />
    </React.StrictMode>,
  );
}

const rssDiscoverySuggestionsElement = document.querySelector<HTMLElement>('[data-rss-discovery-suggestions]');

if (rssDiscoverySuggestionsElement) {
  ReactDOM.createRoot(rssDiscoverySuggestionsElement).render(
    <React.StrictMode>
      <RssDiscoverySuggestions />
    </React.StrictMode>,
  );
}

const watchDiscoverySuggestionsElement = document.querySelector<HTMLElement>('[data-watch-discovery-suggestions]');

if (watchDiscoverySuggestionsElement) {
  ReactDOM.createRoot(watchDiscoverySuggestionsElement).render(
    <React.StrictMode>
      <WatchDiscoverySuggestions />
    </React.StrictMode>,
  );
}

const discoveryOperationsOverviewElement = document.querySelector<HTMLElement>('[data-discovery-operations-overview]');

if (discoveryOperationsOverviewElement) {
  ReactDOM.createRoot(discoveryOperationsOverviewElement).render(
    <React.StrictMode>
      <DiscoveryOperationsOverviewPanel />
    </React.StrictMode>,
  );
}

const discoveryHealthElement = document.querySelector<HTMLElement>('[data-discovery-health]');

if (discoveryHealthElement) {
  ReactDOM.createRoot(discoveryHealthElement).render(
    <React.StrictMode>
      <DiscoveryHealthPanel />
    </React.StrictMode>,
  );
}

const discoveryExplainabilityElement = document.querySelector<HTMLElement>('[data-discovery-explainability]');

if (discoveryExplainabilityElement) {
  ReactDOM.createRoot(discoveryExplainabilityElement).render(
    <React.StrictMode>
      <DiscoveryExplainabilityPanel />
    </React.StrictMode>,
  );
}

const recommendationSignalsElement = document.querySelector<HTMLElement>('[data-recommendation-signals]');

if (recommendationSignalsElement) {
  ReactDOM.createRoot(recommendationSignalsElement).render(
    <React.StrictMode>
      <RecommendationSignalsPanel />
    </React.StrictMode>,
  );
}

const recommendationCandidatesElement = document.querySelector<HTMLElement>('[data-recommendation-candidates]');

if (recommendationCandidatesElement) {
  ReactDOM.createRoot(recommendationCandidatesElement).render(
    <React.StrictMode>
      <RecommendationCandidatesPanel />
    </React.StrictMode>,
  );
}

const recommendationOutputElement = document.querySelector<HTMLElement>('[data-recommendation-output]');

if (recommendationOutputElement) {
  ReactDOM.createRoot(recommendationOutputElement).render(
    <React.StrictMode>
      <RecommendationOutputPanel />
    </React.StrictMode>,
  );
}

const recommendationPreviewElement = document.querySelector<HTMLElement>('[data-recommendation-preview]');

if (recommendationPreviewElement) {
  ReactDOM.createRoot(recommendationPreviewElement).render(
    <React.StrictMode>
      <RecommendationPreviewPanel />
    </React.StrictMode>,
  );
}

const recommendationTorrentsElement = document.querySelector<HTMLElement>('[data-recommendation-torrents]');

if (recommendationTorrentsElement) {
  ReactDOM.createRoot(recommendationTorrentsElement).render(
    <React.StrictMode>
      <RecommendationTorrentsPanel />
    </React.StrictMode>,
  );
}

const recommendationHealthElement = document.querySelector<HTMLElement>('[data-recommendation-health]');

if (recommendationHealthElement) {
  ReactDOM.createRoot(recommendationHealthElement).render(
    <React.StrictMode>
      <RecommendationHealthPanel />
    </React.StrictMode>,
  );
}

const recommendationExplainabilityElement = document.querySelector<HTMLElement>('[data-recommendation-explainability]');

if (recommendationExplainabilityElement) {
  ReactDOM.createRoot(recommendationExplainabilityElement).render(
    <React.StrictMode>
      <RecommendationExplainabilityPanel />
    </React.StrictMode>,
  );
}

const recommendationEngineFoundationElement = document.querySelector<HTMLElement>('[data-recommendation-engine-foundation]');

if (recommendationEngineFoundationElement) {
  ReactDOM.createRoot(recommendationEngineFoundationElement).render(
    <React.StrictMode>
      <RecommendationEngineFoundationPanel />
    </React.StrictMode>,
  );
}
