import React from 'react';
import { ConversationItem } from './types';

interface InboxListProps {
  conversations: ConversationItem[];
  currentUserId?: number | null;
  selectedConversationId?: number | null;
  onSelect: (conversation: ConversationItem) => void;
}

const InboxList: React.FC<InboxListProps> = ({ conversations, currentUserId = null, selectedConversationId = null, onSelect }) => {
  if (conversations.length === 0) {
    return (
      <div className="rounded border border-slate-700 bg-slate-900 p-4 text-sm text-slate-400">
        Ingen samtaler endnu.
      </div>
    );
  }

  return (
    <ul className="space-y-2">
      {conversations.map((conversation) => {
        const partner = conversation.user_a_id === currentUserId ? conversation.user_b : conversation.user_a;
        const partnerName = partner?.name ?? 'Ukendt bruger';
        const preview = conversation.last_message?.body_md ?? 'Ingen beskeder endnu.';
        const isSelected = conversation.id === selectedConversationId;

        return (
          <li key={conversation.id}>
            <button
              type="button"
              onClick={() => onSelect(conversation)}
              className={`flex w-full flex-col rounded border px-3 py-2 text-left transition ${isSelected ? 'border-slate-300 bg-slate-800 text-slate-100' : 'border-slate-800 bg-slate-950 text-slate-300 hover:border-slate-700 hover:bg-slate-900'}`}
            >
              <span className="font-medium">{partnerName}</span>
              <span className="truncate text-xs text-slate-400">{preview}</span>
              {conversation.last_message_at && (
                <span className="mt-1 text-[10px] uppercase tracking-wide text-slate-500">
                  {new Date(conversation.last_message_at).toLocaleString()}
                </span>
              )}
            </button>
          </li>
        );
      })}
    </ul>
  );
};

export default InboxList;
