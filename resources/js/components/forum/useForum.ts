import { useCallback, useEffect, useMemo, useState } from 'react';
import { csrfToken, fetchJson } from '../../lib/http';
import { Paginated, PostItemData, SessionContext, TopicResponse, TopicSummary } from './types';
export function useForum(initialSession: SessionContext) {
  const [session] = useState(initialSession);
  const [topics, setTopics] = useState<TopicSummary[]>([]);
  const [topicsMeta, setTopicsMeta] = useState<Paginated<TopicSummary>['meta'] | null>(null);
  const [selectedTopic, setSelectedTopic] = useState<TopicSummary | null>(null);
  const [posts, setPosts] = useState<PostItemData[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const loadTopic = useCallback(async (topic: TopicSummary | string) => {
    setIsLoading(true);
    setError(null);
    try {
      const slug = typeof topic === 'string' ? topic : topic.slug;
      const result = await fetchJson<TopicResponse>(`/topics/${slug}`);
      setSelectedTopic(result.topic);
      setPosts(result.posts.data);
    } catch (loadError) {
      setError(loadError instanceof Error ? loadError.message : 'Kunne ikke hente emnet.');
    } finally {
      setIsLoading(false);
    }
  }, []);
  const loadTopics = useCallback(async () => {
    const data = await fetchJson<{ data: TopicSummary[]; meta: Paginated<TopicSummary>['meta'] }>('/topics');
    setTopics(data.data);
    setTopicsMeta(data.meta);
    if (!selectedTopic && data.data.length > 0) {
      void loadTopic(data.data[0]);
    }
  }, [loadTopic, selectedTopic]);
  useEffect(() => {
    void loadTopics();
  }, [loadTopics]);
  const handleCreateTopic = useCallback(async (payload: { title: string; body_md: string }) => {
    const topic = await fetchJson<TopicSummary>('/topics', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify(payload),
    });
    setTopics((current) => [topic, ...current]);
    setSelectedTopic(topic);
    setPosts([]);
    await loadTopic(topic);
  }, [loadTopic]);
  const handleSelectTopic = useCallback((topic: TopicSummary) => {
    setSelectedTopic(topic);
    void loadTopic(topic);
  }, [loadTopic]);
  const handleReply = useCallback(async (payload: { body_md: string }) => {
    if (!selectedTopic) {
      throw new Error('Ingen emne valgt');
    }
    await fetchJson<PostItemData>(`/topics/${selectedTopic.id}/posts`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify(payload),
    });
    await loadTopic(selectedTopic);
  }, [loadTopic, selectedTopic]);
  const handleToggleLock = useCallback(async () => {
    if (!selectedTopic) {
      return;
    }
    const topic = await fetchJson<TopicSummary>(`/topics/${selectedTopic.id}/lock`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
      },
    });
    setSelectedTopic(topic);
    setTopics((current) => current.map((item) => (item.id === topic.id ? topic : item)));
  }, [selectedTopic]);
  const handleTogglePin = useCallback(async () => {
    if (!selectedTopic) {
      return;
    }
    const topic = await fetchJson<TopicSummary>(`/topics/${selectedTopic.id}/pin`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
      },
    });
    setSelectedTopic(topic);
    setTopics((current) => current.map((item) => (item.id === topic.id ? topic : item)));
  }, [selectedTopic]);
  const handleDeleteTopic = useCallback(async () => {
    if (!selectedTopic) {
      return;
    }
    setError(null);
    try {
      await fetchJson(`/topics/${selectedTopic.id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
        },
      });
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Kunne ikke slette emnet.');
      throw exception;
    }
    setTopics((current) => current.filter((item) => item.id !== selectedTopic.id));
    setSelectedTopic(null);
    setPosts([]);
  }, [selectedTopic]);
  const handleDeletePost = useCallback(async (post: PostItemData) => {
    await fetchJson(`/posts/${post.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
      },
    });
    if (selectedTopic) {
      await loadTopic(selectedTopic);
    }
  }, [loadTopic, selectedTopic]);
  const handleRestorePost = useCallback(async (post: PostItemData) => {
    await fetchJson<PostItemData>(`/posts/${post.id}/restore`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
      },
    });
    if (selectedTopic) {
      await loadTopic(selectedTopic);
    }
  }, [loadTopic, selectedTopic]);
  const handleEditPost = useCallback(async (post: PostItemData) => {
    const nextBody = window.prompt('Redigér indlægget', post.body_md);
    if (!nextBody || nextBody.trim().length < 3) {
      return;
    }
    await fetchJson<PostItemData>(`/posts/${post.id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({ body_md: nextBody.trim() }),
    });
    if (selectedTopic) {
      await loadTopic(selectedTopic);
    }
  }, [loadTopic, selectedTopic]);
  const sortedTopics = useMemo(() => {
    return [...topics].sort((a, b) => {
      if (a.is_pinned && !b.is_pinned) {
        return -1;
      }
      if (!a.is_pinned && b.is_pinned) {
        return 1;
      }
      return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
    });
  }, [topics]);
  return {
    session,
    topicsMeta,
    selectedTopic,
    posts,
    error,
    isLoading,
    sortedTopics,
    handleCreateTopic,
    handleSelectTopic,
    handleReply,
    handleToggleLock,
    handleTogglePin,
    handleDeleteTopic,
    handleDeletePost,
    handleRestorePost,
    handleEditPost,
  } as const;
}
