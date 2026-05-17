import React from 'react';
import { Lock, MessageCircle, Pin, UserCircle2 } from 'lucide-react';
import { TopicSummary } from './types';

interface TopicListProps {
  topics: TopicSummary[];
  selectedTopicId?: number | null;
  onSelectTopic?: (topic: TopicSummary) => void;
}

const formatTimestamp = (value: string) => new Date(value).toLocaleString();

const TopicList: React.FC<TopicListProps> = ({ topics, selectedTopicId = null, onSelectTopic }) => {
  if (topics.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-6 text-center">
        <MessageCircle className="mx-auto h-8 w-8 text-slate-500" />
        <p className="mt-3 text-sm font-medium text-slate-200">No topics yet</p>
        <p className="mt-1 text-sm text-slate-400">Start the first conversation and help shape the community.</p>
      </div>
    );
  }

  return (
    <ul className="space-y-3" aria-label="Forum topics">
      {topics.map((topic) => {
        const isSelected = selectedTopicId === topic.id;

        return (
          <li
            key={topic.id}
            className={`rounded-2xl border bg-slate-900/70 p-4 shadow-sm transition hover:border-slate-600 hover:bg-slate-900 ${
              isSelected ? 'border-brand/70 ring-1 ring-brand/40' : 'border-slate-800'
            }`}
          >
            <div className="flex items-start justify-between gap-4">
              <div className="min-w-0 flex-1">
                <div className="mb-2 flex flex-wrap items-center gap-2">
                  {topic.is_pinned && (
                    <span className="inline-flex items-center gap-1 rounded-full border border-amber-400/30 bg-amber-400/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-amber-200">
                      <Pin className="h-3 w-3" aria-hidden="true" /> Pinned
                    </span>
                  )}
                  {topic.is_locked && (
                    <span className="inline-flex items-center gap-1 rounded-full border border-red-400/30 bg-red-500/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-red-200">
                      <Lock className="h-3 w-3" aria-hidden="true" /> Locked
                    </span>
                  )}
                  {isSelected && (
                    <span className="rounded-full bg-brand/10 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-brand">
                      Open
                    </span>
                  )}
                </div>
                <button
                  type="button"
                  onClick={() => onSelectTopic?.(topic)}
                  className="block max-w-full truncate text-left text-lg font-semibold leading-6 text-slate-50 hover:text-brand focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-brand"
                  aria-current={isSelected ? 'true' : undefined}
                >
                  {topic.title}
                </button>
                <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400">
                  <span className="inline-flex items-center gap-1.5">
                    <UserCircle2 className="h-4 w-4 text-slate-500" aria-hidden="true" />
                    <span className="font-medium text-slate-300">{topic.author.name}</span>
                  </span>
                  <span aria-hidden="true">•</span>
                  <time dateTime={topic.created_at}>Started {formatTimestamp(topic.created_at)}</time>
                </div>
              </div>
            </div>
          </li>
        );
      })}
    </ul>
  );
};

export default TopicList;
