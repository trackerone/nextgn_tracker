import React, { useCallback, useState } from 'react';
import { ConversationItem, MessageItem } from './types';

interface ThreadViewProps {
  conversation: ConversationItem | null;
  messages: MessageItem[];
  currentUserId?: number | null;
  isLoading: boolean;
  onSendMessage: (body: string) => Promise<void>;
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
      <div className="rounded border border-slate-800 bg-slate-950 p-6 text-sm text-slate-400">
        Vælg en samtale for at se beskeder.
      </div>
    );
  }

  const partner = conversation.user_a_id === currentUserId ? conversation.user_b : conversation.user_a;
  const partnerName = partner?.name ?? 'Ukendt bruger';

  return (
    <div className="flex h-full flex-col rounded border border-slate-800 bg-slate-950">
      <header className="border-b border-slate-800 px-4 py-3">
        <h3 className="text-lg font-semibold text-slate-100">{partnerName}</h3>
      </header>
      <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
        {messages.length === 0 && (
          <p className="text-sm text-slate-400">Ingen beskeder endnu.</p>
        )}
        {messages.map((message) => {
          const isOwn = message.sender_id === currentUserId;

          return (
            <article
              key={message.id}
              className={`rounded border px-3 py-2 text-sm ${isOwn ? 'border-emerald-600 bg-emerald-950 text-emerald-100' : 'border-slate-700 bg-slate-900 text-slate-100'}`}
            >
              <header className="mb-1 flex items-center justify-between text-xs uppercase tracking-wide text-slate-400">
                <span>{message.sender?.name ?? (isOwn ? 'Dig' : 'Ukendt')}</span>
                <time dateTime={message.created_at}>{new Date(message.created_at).toLocaleString()}</time>
              </header>
              <div className="prose prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: message.body_html }} />
            </article>
          );
        })}
      </div>
      <form onSubmit={handleSubmit} className="border-t border-slate-800 px-4 py-3">
        <label className="block text-sm text-slate-300">
          Skriv en besked
          <textarea
            className="mt-2 w-full rounded border border-slate-700 bg-slate-900 p-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
            value={body}
            onChange={(event) => setBody(event.target.value)}
            rows={3}
            disabled={isLoading || isSubmitting}
          />
        </label>
        <div className="mt-2 flex justify-end gap-2">
          <button
            type="submit"
            className="rounded bg-emerald-600 px-3 py-1 text-sm font-medium text-emerald-50 transition hover:bg-emerald-500 disabled:opacity-50"
            disabled={isLoading || isSubmitting || !body.trim()}
          >
            Send
          </button>
        </div>
      </form>
    </div>
  );
};

export default ThreadView;
