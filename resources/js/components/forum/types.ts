export interface ForumRole {
  name: string;
}

export interface ForumUser {
  id: number;
  name: string;
  role?: ForumRole | null;
}

export interface TopicSummary {
  id: number;
  slug: string;
  title: string;
  is_locked: boolean;
  is_pinned: boolean;
  created_at: string;
  author: ForumUser;
}

export interface TopicResponse {
  topic: TopicSummary;
  posts: Paginated<PostItemData>;
}

export interface PostItemData {
  id: number;
  body_md: string;
  body_html: string;
  created_at: string;
  edited_at?: string | null;
  deleted_at?: string | null;
  author: ForumUser;
}

export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface SessionContext {
  authenticated: boolean;
  canWrite: boolean;
  canModerate: boolean;
  canAdmin: boolean;
  user?: ForumUser | null;
}
