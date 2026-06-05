import React, { useCallback, useState } from 'react';
import { MessageSquare, Send } from 'lucide-react';
import DOMPurify from 'dompurify';
import { ConversationItem, MessageItem } from './types';

interface ThreadViewProps {
  conversation: ConversationItem | null;
  messages: MessageItem[];
  currentUserId?: number | null;
  isLoading: boolean;
  onSendMessage: (body: string) => Promise<void>;
}

function sanitizeHtml(html: string | null | undefined) {
  return html
    ? DOMPurify.sanitize(html, {
        ALLOWED_TAGS: ['span', 'p'],
        ALLOWED_ATTR: ['class'],
      })
    : '';
}

function formatMessageTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

const ThreadView: React.FC<ThreadViewProps> = ({ conversation, messages, currentUserId = null, isLoading, onSendMessage }) => {
  const [body, setBody] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = useCallback(async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!body.trim()) {
      return;
    }

    try {
      setIsSubmitting(true);
      await onSendMessage(body.trim());
      setBody('');
    } finally {
      setIsSubmitting(false);
    }
  }, [body, onSendMessage]);

  if (!conversation) {
    return (
      <div className="flex min-h-[28rem] flex-col items-center justify-center rounded-3xl border border-dashed border-slate-700 bg-slate-950 p-8 text-center text-sm text-slate-400">
        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-slate-700 bg-slate-900 text-slate-300">
          <MessageSquare className="h-6 w-6" aria-hidden="true" />
        </div>
        <p className="text-base font-semibold text-slate-100">Choose a conversation</p>
        <p className="mt-2 max-w-sm leading-6">Select a thread from the inbox or start a new message to keep tracker coordination moving.</p>
      </div>
    );
  }

  const partner = conversation.user_a_id === currentUserId ? conversation.user_b : conversation.user_a;
  const partnerName = partner?.name ?? 'Unknown user';

  return (
    <div className="flex min-h-[32rem] flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-950 shadow-sm">
      <header className="border-b border-slate-800 bg-slate-900/60 px-4 py-4 sm:px-5">
        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Conversation with</p>
        <h3 className="mt-1 text-xl font-semibold text-slate-100">{partnerName}</h3>
      </header>
      <div className="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-5" aria-live="polite">
        {isLoading && <p className="text-sm text-slate-400">Loading thread...</p>}
        {!isLoading && messages.length === 0 && (
          <div className="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 p-5 text-sm text-slate-400">
            <p className="font-medium text-slate-200">No messages in this conversation yet.</p>
            <p className="mt-1 leading-6">Write a short, clear first reply below.</p>
          </div>
        )}
        {messages.map((message) => {
          const isOwn = message.sender_id === currentUserId;
          const authorName = message.sender?.name ?? (isOwn ? 'You' : 'Unknown member');

          return (
            <article key={message.id} className={`flex ${isOwn ? 'justify-end' : 'justify-start'}`}>
              <div className={`max-w-[min(42rem,100%)] rounded-2xl border px-4 py-3 text-sm shadow-sm ${isOwn ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-50' : 'border-slate-700 bg-slate-900 text-slate-100'}`}>
                <header className="mb-2 flex flex-col gap-1 border-b border-white/5 pb-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                  <span className={`font-semibold ${isOwn ? 'text-emerald-100' : 'text-slate-100'}`}>{authorName}</span>
                  <time className="text-xs text-slate-500" dateTime={message.created_at}>{formatMessageTime(message.created_at)}</time>
                </header>
                <div className="prose prose-invert max-w-none leading-6" dangerouslySetInnerHTML={{ __html: sanitizeHtml(message.body_html) }} />
                {message.read_at && isOwn && (
                  <p className="mt-2 text-right text-[11px] font-medium uppercase tracking-wide text-emerald-300/80">Read</p>
                )}
              </div>
            </article>
          );
        })}
      </div>
      <form onSubmit={handleSubmit} className="border-t border-slate-800 bg-slate-900/40 px-4 py-4 sm:px-5">
        <label className="block text-sm font-medium text-slate-300">
          Reply to {partnerName}
          <textarea
            className="mt-2 w-full resize-y rounded-2xl border border-slate-700 bg-slate-950 p-3 text-sm leading-6 text-slate-100 placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
            value={body}
            onChange={(event) => setBody(event.target.value)}
            rows={4}
            placeholder="Write a respectful, helpful reply..."
            disabled={isLoading || isSubmitting}
          />
        </label>
        <div className="mt-3 flex flex-col gap-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
          <span>Messages are sent as sanitized community text.</span>
          <button
            type="submit"
            className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-50 transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400/70 disabled:cursor-not-allowed disabled:opacity-50"
            disabled={isLoading || isSubmitting || !body.trim()}
          >
            <Send className="h-4 w-4" aria-hidden="true" />
            {isSubmitting ? 'Sending...' : 'Send reply'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default ThreadView;
