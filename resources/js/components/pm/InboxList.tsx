import React from 'react';
import { Inbox, Mail, MailOpen } from 'lucide-react';
import { ConversationItem } from './types';

interface InboxListProps {
  conversations: ConversationItem[];
  currentUserId?: number | null;
  selectedConversationId?: number | null;
  onSelect: (conversation: ConversationItem) => void;
}

function formatConversationTime(value: string | null): string {
  if (!value) {
    return 'No activity yet';
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value));
}

function previewMessage(value: string | null | undefined): string {
  const trimmed = value?.replace(/\s+/g, ' ').trim();

  return trimmed && trimmed.length > 0 ? trimmed : 'No messages yet.';
}

const InboxList: React.FC<InboxListProps> = ({ conversations, currentUserId = null, selectedConversationId = null, onSelect }) => {
  if (conversations.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-700 bg-slate-950/80 p-5 text-sm text-slate-400">
        <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full border border-slate-700 bg-slate-900 text-slate-300">
          <Inbox className="h-5 w-5" aria-hidden="true" />
        </div>
        <p className="font-medium text-slate-200">No private conversations yet.</p>
        <p className="mt-1 leading-6">Start a direct message when you need to coordinate with another community member.</p>
      </div>
    );
  }

  return (
    <ul className="space-y-2" aria-label="Private message inbox">
      {conversations.map((conversation) => {
        const partner = conversation.user_a_id === currentUserId ? conversation.user_b : conversation.user_a;
        const partnerName = partner?.name ?? 'Unknown user';
        const latestMessage = conversation.last_message;
        const isUnread = Boolean(latestMessage && latestMessage.sender_id !== currentUserId && latestMessage.read_at === null);
        const isSelected = conversation.id === selectedConversationId;
        const preview = previewMessage(latestMessage?.body_md);
        const Icon = isUnread ? Mail : MailOpen;

        return (
          <li key={conversation.id}>
            <button
              type="button"
              onClick={() => onSelect(conversation)}
              aria-current={isSelected ? 'true' : undefined}
              className={`group flex w-full gap-3 rounded-2xl border px-3 py-3 text-left transition focus:outline-none focus:ring-2 focus:ring-emerald-400/70 ${isSelected ? 'border-emerald-400/70 bg-emerald-500/10 shadow-sm shadow-emerald-950/60' : isUnread ? 'border-emerald-500/40 bg-slate-900 text-slate-100 hover:border-emerald-400/70' : 'border-slate-800 bg-slate-950 text-slate-300 hover:border-slate-700 hover:bg-slate-900/80'}`}
            >
              <span className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border ${isUnread ? 'border-emerald-400/60 bg-emerald-500/15 text-emerald-200' : 'border-slate-700 bg-slate-900 text-slate-400 group-hover:text-slate-200'}`}>
                <Icon className="h-4 w-4" aria-hidden="true" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-start justify-between gap-3">
                  <span className={`truncate text-sm ${isUnread ? 'font-semibold text-white' : 'font-medium text-slate-200'}`}>{partnerName}</span>
                  <time className="shrink-0 text-[11px] text-slate-500" dateTime={conversation.last_message_at ?? conversation.updated_at}>
                    {formatConversationTime(conversation.last_message_at)}
                  </time>
                </span>
                <span className={`mt-1 block truncate text-sm leading-5 ${isUnread ? 'font-medium text-slate-100' : 'text-slate-400'}`}>{preview}</span>
                <span className="mt-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  {isUnread && <span className="rounded-full bg-emerald-400 px-2 py-0.5 text-emerald-950">Unread</span>}
                  {isSelected && <span className="text-emerald-300">Open</span>}
                </span>
              </span>
            </button>
          </li>
        );
      })}
    </ul>
  );
};

export default InboxList;
