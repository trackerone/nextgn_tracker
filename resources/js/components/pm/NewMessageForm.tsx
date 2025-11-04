import React, { useCallback, useState } from 'react';

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
      setError('Angiv et gyldigt bruger-ID.');
      return;
    }

    if (body.trim().length < 3) {
      setError('Beskeden skal være mindst 3 tegn.');
      return;
    }

    setError(null);

    try {
      setIsSubmitting(true);
      await onSubmit({ recipient_id: parsedRecipient, body_md: body.trim() });
      setBody('');
      setRecipientId('');
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Kunne ikke sende beskeden.');
    } finally {
      setIsSubmitting(false);
    }
  }, [body, onSubmit, recipientId]);

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded border border-slate-800 bg-slate-950 p-4">
      <h3 className="text-sm font-semibold text-slate-200">Ny besked</h3>
      {error && <p className="text-xs text-red-400">{error}</p>}
      <label className="block text-xs text-slate-400">
        Modtager ID
        <input
          type="number"
          min={1}
          className="mt-1 w-full rounded border border-slate-700 bg-slate-900 p-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
          value={recipientId}
          onChange={(event) => setRecipientId(event.target.value)}
          disabled={isSubmitting}
        />
      </label>
      <label className="block text-xs text-slate-400">
        Besked
        <textarea
          className="mt-1 w-full rounded border border-slate-700 bg-slate-900 p-2 text-sm text-slate-100 focus:border-emerald-500 focus:outline-none"
          value={body}
          onChange={(event) => setBody(event.target.value)}
          rows={3}
          disabled={isSubmitting}
        />
      </label>
      <div className="flex justify-end">
        <button
          type="submit"
          className="rounded bg-emerald-600 px-3 py-1 text-sm font-medium text-emerald-50 transition hover:bg-emerald-500 disabled:opacity-50"
          disabled={isSubmitting}
        >
          Start samtale
        </button>
      </div>
    </form>
  );
};

export default NewMessageForm;
