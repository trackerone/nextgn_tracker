import React, { useCallback, useState } from 'react';
import { Send } from 'lucide-react';

interface NewMessageFormProps {
  onSubmit: (payload: { recipient_id: number; body_md: string }) => Promise<void>;
}

const NewMessageForm: React.FC<NewMessageFormProps> = ({ onSubmit }) => {
  const [recipientId, setRecipientId] = useState('');
  const [body, setBody] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = useCallback(async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const parsedRecipient = Number(recipientId);

    if (!Number.isInteger(parsedRecipient) || parsedRecipient <= 0) {
      setError('Enter a valid user ID.');
      return;
    }

    if (body.trim().length < 3) {
      setError('The message must be at least 3 characters.');
      return;
    }

    setError(null);

    try {
      setIsSubmitting(true);
      await onSubmit({ recipient_id: parsedRecipient, body_md: body.trim() });
      setBody('');
      setRecipientId('');
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Could not send the message.');
    } finally {
      setIsSubmitting(false);
    }
  }, [body, onSubmit, recipientId]);

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-slate-800 bg-slate-950 p-4 shadow-sm">
      <div>
        <h3 className="text-sm font-semibold text-slate-100">Start a conversation</h3>
        <p className="mt-1 text-xs leading-5 text-slate-500">Use a member ID to begin a private, community-focused exchange.</p>
      </div>
      {error && <p className="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-200" role="alert">{error}</p>}
      <label className="block text-xs font-medium text-slate-300">
        Recipient ID
        <input
          type="number"
          min={1}
          inputMode="numeric"
          placeholder="Example: 42"
          className="mt-1.5 w-full rounded-xl border border-slate-700 bg-slate-900 p-2.5 text-sm text-slate-100 placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
          value={recipientId}
          onChange={(event) => setRecipientId(event.target.value)}
          disabled={isSubmitting}
        />
      </label>
      <label className="block text-xs font-medium text-slate-300">
        Message
        <textarea
          className="mt-1.5 w-full resize-y rounded-xl border border-slate-700 bg-slate-900 p-2.5 text-sm leading-6 text-slate-100 placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
          value={body}
          onChange={(event) => setBody(event.target.value)}
          rows={4}
          placeholder="Share context, a request, or a friendly note..."
          disabled={isSubmitting}
        />
      </label>
      <div className="flex items-center justify-between gap-3 text-xs text-slate-500">
        <span>{body.trim().length}/3 characters minimum</span>
        <button
          type="submit"
          className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-emerald-50 transition hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-400/70 disabled:cursor-not-allowed disabled:opacity-50"
          disabled={isSubmitting}
        >
          <Send className="h-4 w-4" aria-hidden="true" />
          {isSubmitting ? 'Starting...' : 'Start conversation'}
        </button>
      </div>
    </form>
  );
};

export default NewMessageForm;
