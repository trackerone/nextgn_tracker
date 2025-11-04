import '../css/app.css';
import React from 'react';
import ReactDOM from 'react-dom/client';
import CreateTopicForm from './components/forum/CreateTopicForm';
import TopicList from './components/forum/TopicList';
import TopicView from './components/forum/TopicView';
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
      <section id="forum" className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-3xl font-semibold text-slate-100">Forum</h1>
          {topicsMeta && (
            <span className="text-sm text-slate-400">{topicsMeta.total} emner</span>
          )}
        </div>
        {session.canWrite && (
          <div id="create-topic">
            <CreateTopicForm onSubmit={handleCreateTopic} disabled={!session.authenticated} />
          </div>
        )}
        {error && <p className="text-sm text-red-400">{error}</p>}
        {isLoading && <p className="text-sm text-slate-400">Indlæser…</p>}
        <TopicList topics={sortedTopics} onSelectTopic={handleSelectTopic} />
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
      {session.user && (
        <PrivateMessagesPanel currentUserId={session.user.id} />
      )}
    </main>
  );
};

const element = document.getElementById('app');

if (!element) {
  throw new Error('App root element not found');
}

ReactDOM.createRoot(element).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
