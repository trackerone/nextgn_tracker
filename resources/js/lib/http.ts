const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

async function fetchJson<T>(input: RequestInfo, init?: RequestInit): Promise<T> {
  const response = await fetch(input, init);

  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || 'Request failed');
  }

  if (response.status === 204) {
    return {} as T;
  }

  return (await response.json()) as T;
}

export { csrfToken, fetchJson };
