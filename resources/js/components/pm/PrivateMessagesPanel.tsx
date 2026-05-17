import React, { useCallback } from 'react';
import { RefreshCw } from 'lucide-react';
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

  const unreadCount = conversations.filter((conversation) => {
    const latestMessage = conversation.last_message;

    return latestMessage && latestMessage.sender_id !== currentUserId && latestMessage.read_at === null;
  }).length;

  const handleSendMessage = useCallback(async (body: string) => {
    if (!selectedConversation) {
      throw new Error('No conversation selected.');
    }

    await sendMessage(selectedConversation, body);
  }, [selectedConversation, sendMessage]);

  return (
    <section className="space-y-5" id="private-messages" aria-labelledby="private-messages-heading">
      <div className="rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 p-5 shadow-sm">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-emerald-300">Community inbox</p>
            <h2 id="private-messages-heading" className="mt-1 text-2xl font-semibold text-slate-100">Private messages</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Coordinate directly with members while keeping conversation context easy to scan.</p>
          </div>
          <div className="flex items-center gap-3">
            <span className="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs font-semibold text-slate-300">
              {unreadCount} unread
            </span>
            <button
              type="button"
              onClick={() => {
                void refresh();
              }}
              className="inline-flex items-center gap-2 rounded-xl border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 transition hover:border-emerald-500 hover:text-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-400/70"
            >
              <RefreshCw className="h-4 w-4" aria-hidden="true" />
              Refresh
            </button>
          </div>
        </div>
      </div>
      {error && <p className="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200" role="alert">{error}</p>}
      <div className="grid gap-6 xl:grid-cols-[360px,1fr]">
        <div className="space-y-4 xl:sticky xl:top-6">
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
