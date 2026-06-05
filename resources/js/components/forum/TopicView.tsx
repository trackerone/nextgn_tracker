import React from 'react';
import { Lock, Pin, Trash2, UserCircle2 } from 'lucide-react';
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

const formatTimestamp = (value: string) => new Date(value).toLocaleString();

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
    return (
      <section className="rounded-2xl border border-dashed border-slate-700 bg-slate-900/30 p-8 text-center">
        <p className="text-sm font-medium text-slate-200">Select a topic to read the discussion</p>
        <p className="mt-1 text-sm text-slate-400">Topic details, moderation state and replies will appear here.</p>
      </section>
    );
  }

  return (
    <section className="space-y-6" aria-labelledby="topic-heading">
      <header className="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70 shadow-sm">
        <div className="border-b border-slate-800/80 px-5 py-5">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0 flex-1">
              <div className="mb-3 flex flex-wrap items-center gap-2">
                {topic.is_pinned && (
                  <span className="inline-flex items-center gap-1 rounded-full border border-amber-400/30 bg-amber-400/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-200">
                    <Pin className="h-3 w-3" aria-hidden="true" /> Pinned
                  </span>
                )}
                {topic.is_locked && (
                  <span className="inline-flex items-center gap-1 rounded-full border border-red-400/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-red-200">
                    <Lock className="h-3 w-3" aria-hidden="true" /> Locked
                  </span>
                )}
              </div>
              <h2 id="topic-heading" className="text-2xl font-semibold leading-tight text-slate-50 sm:text-3xl">
                {topic.title}
              </h2>
              <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-400">
                <span className="inline-flex items-center gap-1.5">
                  <UserCircle2 className="h-4 w-4 text-slate-500" aria-hidden="true" />
                  Started by <span className="font-medium text-slate-200">{topic.author.name}</span>
                </span>
                <span aria-hidden="true">•</span>
                <time dateTime={topic.created_at}>{formatTimestamp(topic.created_at)}</time>
                <span aria-hidden="true">•</span>
                <span>{posts.length} {posts.length === 1 ? 'post' : 'posts'}</span>
              </div>
            </div>
          </div>
        </div>
        {session.canModerate && (
          <div className="bg-slate-950/40 px-5 py-4">
            <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Moderation controls</p>
            <div className="flex flex-wrap items-center gap-2">
              {onToggleLock && (
                <button
                  type="button"
                  onClick={() => void onToggleLock?.()}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-medium text-red-200 hover:bg-red-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                >
                  <Lock className="h-3 w-3" aria-hidden="true" /> {topic.is_locked ? 'Unlock replies' : 'Lock replies'}
                </button>
              )}
              {onTogglePin && (
                <button
                  type="button"
                  onClick={() => void onTogglePin?.()}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-amber-500/40 px-3 py-1.5 text-xs font-medium text-amber-200 hover:bg-amber-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
                >
                  <Pin className="h-3 w-3" aria-hidden="true" /> {topic.is_pinned ? 'Remove pin' : 'Pin topic'}
                </button>
              )}
              {session.canAdmin && onDeleteTopic && (
                <button
                  type="button"
                  onClick={() => void onDeleteTopic?.()}
                  className="inline-flex items-center gap-1.5 rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-medium text-red-200 hover:bg-red-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                >
                  <Trash2 className="h-3 w-3" aria-hidden="true" /> Delete empty topic
                </button>
              )}
            </div>
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
          <div className="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-100">
            This topic is locked. Existing posts remain visible, but new replies are closed.
          </div>
        ) : (
          <ReplyForm onSubmit={onReply} />
        )
      ) : (
        <div className="rounded-2xl border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-400">
          You must be a verified user to reply.
        </div>
      )}
    </section>
  );
};

export default TopicView;
