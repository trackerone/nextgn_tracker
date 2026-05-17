import React, { useState } from 'react';
import { PenLine } from 'lucide-react';

interface CreateTopicFormProps {
  onSubmit: (payload: { title: string; body_md: string }) => Promise<void>;
  disabled?: boolean;
}

const CreateTopicForm: React.FC<CreateTopicFormProps> = ({ onSubmit, disabled = false }) => {
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (title.trim().length < 3 || body.trim().length < 3) {
      setError('Title and body must be at least 3 characters.');
      return;
    }

    setError(null);
    setIsSubmitting(true);

    try {
      await onSubmit({ title: title.trim(), body_md: body.trim() });
      setTitle('');
      setBody('');
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : 'Could not create the topic.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-5 shadow-sm"
      aria-busy={isSubmitting}
    >
      <div>
        <p className="text-sm font-semibold text-slate-100">Start a community topic</p>
        <p className="mt-1 text-xs text-slate-400">Use a clear title and include enough detail for other members to jump in.</p>
      </div>
      <div>
        <label className="block text-sm font-medium text-slate-300" htmlFor="topic-title">
          Topic title
        </label>
        <input
          id="topic-title"
          type="text"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          disabled={disabled || isSubmitting}
          className="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
          placeholder="What should the community discuss?"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-slate-300" htmlFor="topic-body">
          Opening post (Markdown)
        </label>
        <textarea
          id="topic-body"
          value={body}
          onChange={(event) => setBody(event.target.value)}
          disabled={disabled || isSubmitting}
          className="mt-2 h-40 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm leading-6 text-slate-100 placeholder:text-slate-500 focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
          placeholder="Add context, examples, links, or a question to guide replies..."
        />
      </div>
      {error && <p className="text-sm text-red-300" role="alert">{error}</p>}
      <div className="flex justify-end">
        <button
          type="submit"
          disabled={disabled || isSubmitting}
          className="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-50"
        >
          <PenLine className="h-4 w-4" aria-hidden="true" />
          {isSubmitting ? 'Creating topic...' : 'Create topic'}
        </button>
      </div>
    </form>
  );
};

export default CreateTopicForm;
