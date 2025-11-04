import { useCallback, useEffect, useMemo, useState } from 'react';
import { csrfToken, fetchJson } from '../../lib/http';
import { ConversationItem, MessageItem, NewConversationResponse, StartConversationPayload } from './types';

function sortConversations(conversations: ConversationItem[]): ConversationItem[] {
  return [...conversations].sort((a, b) => {
    const aTime = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
    const bTime = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;

    return bTime - aTime;
  });
}

export function usePrivateMessages() {
  const [conversations, setConversations] = useState<ConversationItem[]>([]);
  const [selectedConversation, setSelectedConversation] = useState<ConversationItem | null>(null);
  const [messages, setMessages] = useState<MessageItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadConversations = useCallback(async () => {
    setError(null);

    try {
      const result = await fetchJson<ConversationItem[]>('/pm');
      setConversations(sortConversations(result));
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Kunne ikke hente beskeder.');
    }
  }, []);

  const loadConversation = useCallback(async (conversationId: number) => {
    setIsLoading(true);
    setError(null);

    try {
      const conversation = await fetchJson<ConversationItem>(`/pm/${conversationId}`);
      setSelectedConversation(conversation);
      setMessages(conversation.messages ?? []);
      setConversations((current) => {
        const updated = current.map((item) => (item.id === conversation.id ? { ...conversation, messages: undefined } : item));
        return sortConversations(updated);
      });
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Kunne ikke hente tråden.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadConversations();
  }, [loadConversations]);

  const handleSelectConversation = useCallback((conversationId: number) => {
    void loadConversation(conversationId);
  }, [loadConversation]);

  const handleStartConversation = useCallback(async (payload: StartConversationPayload) => {
    setError(null);

    try {
      const response = await fetchJson<NewConversationResponse>('/pm', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(payload),
      });

      setConversations((current) => {
        const exists = current.some((item) => item.id === response.conversation.id);
        const base = exists
          ? current.map((item) => (item.id === response.conversation.id ? response.conversation : item))
          : [response.conversation, ...current];

        return sortConversations(base);
      });

      setSelectedConversation({ ...response.conversation, messages: [response.message] });
      setMessages([response.message]);
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Kunne ikke starte besked.');
      throw exception;
    }
  }, []);

  const handleSendMessage = useCallback(async (conversation: ConversationItem, body: string) => {
    setError(null);

    try {
      const message = await fetchJson<MessageItem>(`/pm/${conversation.id}/messages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ body_md: body }),
      });

      setMessages((current) => [...current, message]);
      setConversations((current) => {
        const updated = current.map((item) => (item.id === conversation.id
          ? { ...item, last_message: message, last_message_at: message.created_at }
          : item));

        return sortConversations(updated);
      });
      setSelectedConversation((current) => (current && current.id === conversation.id
        ? { ...current, last_message: message, last_message_at: message.created_at }
        : current));

      return message;
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Kunne ikke sende besked.');
      throw exception;
    }
  }, []);

  return {
    conversations: useMemo(() => sortConversations(conversations), [conversations]),
    selectedConversation,
    messages,
    isLoading,
    error,
    refresh: loadConversations,
    selectConversation: handleSelectConversation,
    startConversation: handleStartConversation,
    sendMessage: handleSendMessage,
  } as const;
}
