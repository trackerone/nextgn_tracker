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
  return (
    <div className="space-y-4">
      {posts.map((post) => (
        <PostItem
          key={post.id}
          post={post}
          session={session}
          isOwner={currentUserId === post.author.id}
          onEdit={onEdit}
          onDelete={onDelete}
          onRestore={onRestore}
        />
      ))}
    </div>
  );
};

export default PostList;
