import React from 'react';
import { Edit, History, RefreshCcw, Trash2, UserCircle2 } from 'lucide-react';
import { PostItemData, SessionContext } from './types';

interface PostItemProps {
  post: PostItemData;
  session: SessionContext;
  onEdit?: (post: PostItemData) => void;
  onDelete?: (post: PostItemData) => void;
  onRestore?: (post: PostItemData) => void;
  isOwner: boolean;
}

const PostItem: React.FC<PostItemProps> = ({ post, session, onEdit, onDelete, onRestore, isOwner }) => {
  const isDeleted = Boolean(post.deleted_at);
  const canModerate = session.canModerate;
  const showActions = !isDeleted && (isOwner || canModerate);
  const showRestore = isDeleted && canModerate;

  return (
    <article className="rounded-lg border border-slate-800 bg-slate-900/40 p-4 shadow-sm">
      <header className="flex items-start justify-between">
        <div className="flex items-center gap-2 text-sm text-slate-300">
          <UserCircle2 className="h-4 w-4" />
          <span>{post.author.name}</span>
          <span className="text-slate-500">•</span>
          <time dateTime={post.created_at}>{new Date(post.created_at).toLocaleString()}</time>
          {post.edited_at && (
            <span className="inline-flex items-center gap-1 text-xs text-slate-400">
              <History className="h-3 w-3" /> edited
            </span>
          )}
          {isDeleted && (
            <span className="text-xs font-semibold uppercase tracking-wide text-red-400">(deleted)</span>
          )}
        </div>
        <div className="flex items-center gap-2">
          {showActions && (
            <>
              {onEdit && (
                <button
                  type="button"
                  className="inline-flex items-center gap-1 rounded border border-slate-800 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800"
                  onClick={() => onEdit(post)}
                >
                  <Edit className="h-3 w-3" /> Edit
                </button>
              )}
              {onDelete && (
                <button
                  type="button"
                  className="inline-flex items-center gap-1 rounded border border-red-500/30 px-2 py-1 text-xs text-red-300 hover:bg-red-500/10"
                  onClick={() => onDelete(post)}
                >
                  <Trash2 className="h-3 w-3" /> Delete
                </button>
              )}
            </>
          )}
          {showRestore && onRestore && (
            <button
              type="button"
              className="inline-flex items-center gap-1 rounded border border-emerald-500/40 px-2 py-1 text-xs text-emerald-300 hover:bg-emerald-500/10"
              onClick={() => onRestore(post)}
            >
              <RefreshCcw className="h-3 w-3" /> Restore
            </button>
          )}
        </div>
      </header>
      <div className="prose prose-invert mt-3 max-w-none text-sm" dangerouslySetInnerHTML={{ __html: post.body_html }} />
    </article>
  );
};

export default PostItem;
