import React, { useState } from 'react';
import { Send } from 'lucide-react';

interface ReplyFormProps {
  onSubmit: (payload: { body_md: string }) => Promise<void>;
  disabled?: boolean;
}

const ReplyForm: React.FC<ReplyFormProps> = ({ onSubmit, disabled = false }) => {
  const [body, setBody] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (body.trim().length < 3) {
      setError('The reply must be at least 3 characters.');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      await onSubmit({ body_md: body.trim() });
      setBody('');
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : 'Could not save the reply.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 shadow-sm"
      aria-busy={isSubmitting}
    >
      <div className="flex flex-col gap-1">
        <label className="text-sm font-semibold text-slate-100" htmlFor="reply-body">
          Add your reply
        </label>
        <p className="text-xs text-slate-400">Keep it constructive. Markdown, links, quotes and code snippets are welcome.</p>
      </div>
      <textarea
        id="reply-body"
        value={body}
        onChange={(event) => setBody(event.target.value)}
        disabled={disabled || isSubmitting}
        className="h-36 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm leading-6 text-slate-100 placeholder:text-slate-500 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
        placeholder="Share an update, answer the question, or add context..."
      />
      {error && <p className="text-sm text-red-300" role="alert">{error}</p>}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs text-slate-500">Your reply appears in this thread after it is saved.</p>
        <button
          type="submit"
          disabled={disabled || isSubmitting}
          className="inline-flex items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Send className="h-4 w-4" aria-hidden="true" />
          {isSubmitting ? 'Posting reply...' : 'Post reply'}
        </button>
      </div>
    </form>
  );
};

export default ReplyForm;
