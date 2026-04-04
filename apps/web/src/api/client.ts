const baseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000';

export const resolveApiUrl = (path: string): string => {
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path;
  }

  return `${baseUrl}${path}`;
};

export const apiClient = async <T>(path: string, init?: RequestInit): Promise<T> => {
  const response = await fetch(resolveApiUrl(path), {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(init?.headers ?? {}),
    },
  });

  if (!response.ok) {
    throw new Error(`Request failed with status ${response.status}`);
  }

  return response.json() as Promise<T>;
};
