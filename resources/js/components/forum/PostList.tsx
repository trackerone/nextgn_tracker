import React from 'react';
import PostItem from './PostItem';
import { PostItemData, SessionContext } from './types';

interface PostListProps {
  posts: PostItemData[];
  session: SessionContext;
  currentUserId?: number | null;
  onEdit?: (post: PostItemData) => void;
  onDelete?: (post: PostItemData) => void;
  onRestore?: (post: PostItemData) => void;
}

const PostList: React.FC<PostListProps> = ({ posts, session, currentUserId, onEdit, onDelete, onRestore }) => {
  if (posts.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-slate-700 bg-slate-900/30 p-6 text-center text-sm text-slate-400">
        This topic is waiting for its first post to load.
      </div>
    );
  }

  return (
    <div className="space-y-5" aria-label="Topic posts">
      {posts.map((post, index) => (
        <PostItem
          key={post.id}
          post={post}
          session={session}
          isOwner={currentUserId === post.author.id}
          position={index + 1}
          isOriginalPost={index === 0}
          onEdit={onEdit}
          onDelete={onDelete}
          onRestore={onRestore}
        />
      ))}
    </div>
  );
};

export default PostList;
