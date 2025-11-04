import React, { useCallback } from 'react';
import InboxList from './InboxList';
import NewMessageForm from './NewMessageForm';
import ThreadView from './ThreadView';
import { usePrivateMessages } from './usePrivateMessages';

interface PrivateMessagesPanelProps {
  currentUserId?: number | null;
}

const PrivateMessagesPanel: React.FC<PrivateMessagesPanelProps> = ({ currentUserId = null }) => {
  const {
    conversations,
    selectedConversation,
    messages,
    isLoading,
    error,
    refresh,
    selectConversation,
    startConversation,
    sendMessage,
  } = usePrivateMessages();

  const handleSendMessage = useCallback(async (body: string) => {
    if (!selectedConversation) {
      throw new Error('Ingen samtale valgt');
    }

    await sendMessage(selectedConversation, body);
  }, [selectedConversation, sendMessage]);

  return (
    <section className="space-y-4" id="private-messages">
      <div className="flex items-center justify-between">
        <h2 className="text-2xl font-semibold text-slate-100">Private beskeder</h2>
        <button
          type="button"
          onClick={() => {
            void refresh();
          }}
          className="rounded border border-slate-700 px-3 py-1 text-sm text-slate-200 transition hover:border-emerald-500 hover:text-emerald-300"
        >
          Opdater
        </button>
      </div>
      {error && <p className="text-sm text-red-400">{error}</p>}
      <div className="grid gap-6 lg:grid-cols-[280px,1fr]">
        <div className="space-y-4">
          <InboxList
            conversations={conversations}
            currentUserId={currentUserId ?? undefined}
            selectedConversationId={selectedConversation?.id ?? null}
            onSelect={(conversation) => selectConversation(conversation.id)}
          />
          <NewMessageForm onSubmit={startConversation} />
        </div>
        <ThreadView
          conversation={selectedConversation}
          messages={messages}
          currentUserId={currentUserId}
          isLoading={isLoading}
          onSendMessage={handleSendMessage}
        />
      </div>
    </section>
  );
};

export default PrivateMessagesPanel;
