import React, { useState } from 'react';

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
      setError(submitError instanceof Error ? submitError.message : 'Kunne ikke oprette emnet.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-slate-800 bg-slate-900/40 p-4">
      <div>
        <label className="block text-sm font-medium text-slate-300" htmlFor="topic-title">
          Title
        </label>
        <input
          id="topic-title"
          type="text"
          value={title}
          onChange={(event) => setTitle(event.target.value)}
          disabled={disabled || isSubmitting}
          className="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-brand focus:outline-none"
          placeholder="Emnets titel"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-slate-300" htmlFor="topic-body">
          Body (Markdown)
        </label>
        <textarea
          id="topic-body"
          value={body}
          onChange={(event) => setBody(event.target.value)}
          disabled={disabled || isSubmitting}
          className="mt-2 h-40 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-brand focus:outline-none"
          placeholder="Skriv dit indlæg i Markdown"
        />
      </div>
      {error && <p className="text-sm text-red-400">{error}</p>}
      <div className="flex justify-end">
        <button
          type="submit"
          disabled={disabled || isSubmitting}
          className="inline-flex items-center rounded bg-brand px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {isSubmitting ? 'Gemmer…' : 'Opret emne'}
        </button>
      </div>
    </form>
  );
};

export default CreateTopicForm;
