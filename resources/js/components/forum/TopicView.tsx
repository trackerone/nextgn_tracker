import React from 'react';
import { Lock, Pin, Trash2 } from 'lucide-react';
import PostList from './PostList';
import ReplyForm from './ReplyForm';
import { PostItemData, SessionContext, TopicSummary } from './types';

interface TopicViewProps {
  topic?: TopicSummary | null;
  posts: PostItemData[];
  session: SessionContext;
  onReply: (payload: { body_md: string }) => Promise<void>;
  onToggleLock?: () => Promise<void>;
  onTogglePin?: () => Promise<void>;
  onDeleteTopic?: () => Promise<void>;
  onPostEdit?: (post: PostItemData) => void;
  onPostDelete?: (post: PostItemData) => void;
  onPostRestore?: (post: PostItemData) => void;
}

const TopicView: React.FC<TopicViewProps> = ({
  topic,
  posts,
  session,
  onReply,
  onToggleLock,
  onTogglePin,
  onDeleteTopic,
  onPostEdit,
  onPostDelete,
  onPostRestore,
}) => {
  if (!topic) {
    return <p className="text-sm text-slate-400">Vælg et emne for at se indholdet.</p>;
  }

  return (
    <section className="space-y-6">
      <header className="rounded-lg border border-slate-800 bg-slate-900/60 p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 className="text-2xl font-semibold text-slate-100">{topic.title}</h2>
            <div className="mt-2 flex items-center gap-3 text-sm text-slate-400">
              <span>Startet af {topic.author.name}</span>
              <span>•</span>
              <time dateTime={topic.created_at}>{new Date(topic.created_at).toLocaleString()}</time>
            </div>
          </div>
          <div className="flex items-center gap-2">
            {topic.is_pinned && (
              <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-300">
                <Pin className="h-3 w-3" /> Pinned
              </span>
            )}
            {topic.is_locked && (
              <span className="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-1 text-xs font-medium text-red-300">
                <Lock className="h-3 w-3" /> Locked
              </span>
            )}
          </div>
        </div>
        {session.canModerate && (
          <div className="mt-4 flex flex-wrap items-center gap-2">
            {onToggleLock && (
              <button
                type="button"
                onClick={() => void onToggleLock?.()}
                className="inline-flex items-center gap-1 rounded border border-red-500/40 px-3 py-1 text-xs text-red-300 hover:bg-red-500/10"
              >
                <Lock className="h-3 w-3" /> {topic.is_locked ? 'Unlock' : 'Lock'}
              </button>
            )}
            {onTogglePin && (
              <button
                type="button"
                onClick={() => void onTogglePin?.()}
                className="inline-flex items-center gap-1 rounded border border-amber-500/40 px-3 py-1 text-xs text-amber-300 hover:bg-amber-500/10"
              >
                <Pin className="h-3 w-3" /> {topic.is_pinned ? 'Unpin' : 'Pin'}
              </button>
            )}
            {session.canAdmin && onDeleteTopic && (
              <button
                type="button"
                onClick={() => void onDeleteTopic?.()}
                className="inline-flex items-center gap-1 rounded border border-red-500/40 px-3 py-1 text-xs text-red-300 hover:bg-red-500/10"
              >
                <Trash2 className="h-3 w-3" /> Delete topic
              </button>
            )}
          </div>
        )}
      </header>

      <PostList
        posts={posts}
        session={session}
        currentUserId={session.user?.id ?? null}
        onEdit={onPostEdit}
        onDelete={onPostDelete}
        onRestore={onPostRestore}
      />

      {session.canWrite ? (
        topic.is_locked ? (
          <p className="text-sm text-slate-400">Dette emne er låst og accepterer ikke nye svar.</p>
        ) : (
          <ReplyForm onSubmit={onReply} />
        )
      ) : (
        <p className="text-sm text-slate-400">Du skal være verificeret bruger for at svare.</p>
      )}
    </section>
  );
};

export default TopicView;
