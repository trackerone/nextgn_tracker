import React, { useState } from 'react';

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
      setError('Svar skal være mindst 3 tegn.');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      await onSubmit({ body_md: body.trim() });
      setBody('');
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : 'Kunne ikke gemme svaret.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded-lg border border-slate-800 bg-slate-900/40 p-4">
      <div>
        <label className="block text-sm font-medium text-slate-300" htmlFor="reply-body">
          Svar (Markdown)
        </label>
        <textarea
          id="reply-body"
          value={body}
          onChange={(event) => setBody(event.target.value)}
          disabled={disabled || isSubmitting}
          className="mt-2 h-32 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 focus:border-brand focus:outline-none"
          placeholder="Skriv dit svar"
        />
      </div>
      {error && <p className="text-sm text-red-400">{error}</p>}
      <div className="flex justify-end">
        <button
          type="submit"
          disabled={disabled || isSubmitting}
          className="inline-flex items-center rounded bg-brand px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-brand/90 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {isSubmitting ? 'Gemmer…' : 'Send svar'}
        </button>
      </div>
    </form>
  );
};

export default ReplyForm;
