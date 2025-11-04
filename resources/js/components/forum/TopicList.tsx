import React from 'react';
import { Lock, Pin, UserCircle2 } from 'lucide-react';
import { TopicSummary } from './types';

interface TopicListProps {
  topics: TopicSummary[];
  onSelectTopic?: (topic: TopicSummary) => void;
}

const TopicList: React.FC<TopicListProps> = ({ topics, onSelectTopic }) => {
  if (topics.length === 0) {
    return <p className="text-sm text-slate-400">Ingen emner endnu. Vær den første til at oprette et.</p>;
  }

  return (
    <ul className="space-y-3">
      {topics.map((topic) => (
        <li key={topic.id} className="rounded-lg border border-slate-800 bg-slate-900/60 p-4 shadow-sm">
          <div className="flex items-start justify-between gap-4">
            <div>
              <button
                type="button"
                onClick={() => onSelectTopic?.(topic)}
                className="text-left text-lg font-semibold text-slate-100 hover:text-brand"
              >
                {topic.title}
              </button>
              <div className="mt-1 flex items-center gap-2 text-xs text-slate-400">
                <UserCircle2 className="h-4 w-4" />
                <span>{topic.author.name}</span>
                <span>•</span>
                <time dateTime={topic.created_at}>{new Date(topic.created_at).toLocaleString()}</time>
              </div>
            </div>
            <div className="flex items-center gap-2">
              {topic.is_pinned && (
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-300">
                  <Pin className="h-3 w-3" /> Pinned
                </span>
              )}
              {topic.is_locked && (
                <span className="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-1 text-xs font-medium text-red-300">
                  <Lock className="h-3 w-3" /> Locked
                </span>
              )}
            </div>
          </div>
        </li>
      ))}
    </ul>
  );
};

export default TopicList;
