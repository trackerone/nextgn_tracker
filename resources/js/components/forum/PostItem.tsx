import React from 'react';
import { Edit, History, RefreshCcw, ShieldAlert, Trash2, UserCircle2 } from 'lucide-react';
import { PostItemData, SessionContext } from './types';
import DOMPurify from 'dompurify';

interface PostItemProps {
  post: PostItemData;
  session: SessionContext;
  onEdit?: (post: PostItemData) => void;
  onDelete?: (post: PostItemData) => void;
  onRestore?: (post: PostItemData) => void;
  isOwner: boolean;
  isOriginalPost?: boolean;
  position: number;
}

function sanitizeHtml(html: string | null | undefined) {
  return html
    ? DOMPurify.sanitize(html, {
        ALLOWED_TAGS: ['a', 'blockquote', 'br', 'code', 'em', 'li', 'ol', 'p', 'pre', 'span', 'strong', 'ul'],
        ALLOWED_ATTR: ['class', 'href', 'rel', 'target'],
      })
    : '';
}

const formatTimestamp = (value: string) => new Date(value).toLocaleString();

const PostItem: React.FC<PostItemProps> = ({
  post,
  session,
  onEdit,
  onDelete,
  onRestore,
  isOwner,
  isOriginalPost = false,
  position,
}) => {
  const isDeleted = Boolean(post.deleted_at);
  const canModerate = session.canModerate;
  const showActions = !isDeleted && (isOwner || canModerate);
  const showRestore = isDeleted && canModerate;

  return (
    <article
      className={`rounded-2xl border bg-slate-900/55 shadow-sm ${
        isOriginalPost ? 'border-brand/40 ring-1 ring-brand/10' : 'border-slate-800'
      } ${isDeleted ? 'border-red-500/40 bg-red-950/10' : ''}`}
    >
      <header className="flex flex-col gap-3 border-b border-slate-800/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <span
              className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${
                isOriginalPost ? 'bg-brand/10 text-brand' : 'bg-slate-800 text-slate-300'
              }`}
            >
              {isOriginalPost ? 'Original post' : `Reply #${position - 1}`}
            </span>
            {isDeleted && (
              <span className="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-red-200">
                <ShieldAlert className="h-3 w-3" aria-hidden="true" /> Deleted
              </span>
            )}
            {post.edited_at && (
              <span className="inline-flex items-center gap-1 text-xs text-slate-400">
                <History className="h-3 w-3" aria-hidden="true" /> edited
              </span>
            )}
          </div>
          <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-400">
            <span className="inline-flex items-center gap-1.5">
              <UserCircle2 className="h-4 w-4 text-slate-500" aria-hidden="true" />
              <span className="font-medium text-slate-200">{post.author.name}</span>
              {post.author.role?.name && <span className="text-xs text-slate-500">{post.author.role.name}</span>}
            </span>
            <span aria-hidden="true">•</span>
            <time dateTime={post.created_at}>{formatTimestamp(post.created_at)}</time>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {showActions && (
            <>
              {onEdit && (
                <button
                  type="button"
                  className="inline-flex items-center gap-1 rounded-lg border border-slate-700 px-2.5 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                  onClick={() => onEdit(post)}
                >
                  <Edit className="h-3 w-3" aria-hidden="true" /> Edit
                </button>
              )}
              {onDelete && (
                <button
                  type="button"
                  className="inline-flex items-center gap-1 rounded-lg border border-red-500/40 px-2.5 py-1.5 text-xs font-medium text-red-200 hover:bg-red-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400"
                  onClick={() => onDelete(post)}
                >
                  <Trash2 className="h-3 w-3" aria-hidden="true" /> Delete
                </button>
              )}
            </>
          )}
          {showRestore && onRestore && (
            <button
              type="button"
              className="inline-flex items-center gap-1 rounded-lg border border-emerald-500/40 px-2.5 py-1.5 text-xs font-medium text-emerald-200 hover:bg-emerald-500/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
              onClick={() => onRestore(post)}
            >
              <RefreshCcw className="h-3 w-3" aria-hidden="true" /> Restore
            </button>
          )}
        </div>
      </header>
      <div
        className="prose prose-invert max-w-none px-4 py-4 text-sm leading-6 prose-a:text-brand prose-a:underline prose-blockquote:border-l-brand prose-blockquote:bg-slate-950/60 prose-blockquote:px-4 prose-blockquote:py-2 prose-blockquote:text-slate-300 prose-code:rounded prose-code:bg-slate-950 prose-code:px-1.5 prose-code:py-0.5 prose-code:text-amber-200 prose-pre:border prose-pre:border-slate-800 prose-pre:bg-slate-950/80"
        dangerouslySetInnerHTML={{ __html: sanitizeHtml(post.body_html) }}
      />
    </article>
  );
};

export default PostItem;
