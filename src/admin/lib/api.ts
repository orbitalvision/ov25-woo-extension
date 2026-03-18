const getConfig = () => ({
  base: window.ov25Admin?.restBase || '/wp-json/ov25/v1/',
  nonce: window.ov25Admin?.nonce || '',
});

async function apiFetch<T = unknown>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const { base, nonce } = getConfig();
  const url = `${base}${endpoint}`;

  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...options.headers,
    },
    credentials: 'include',
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || `API error: ${response.status}`);
  }

  return response.json();
}

export const api = {
  getSettings: () => apiFetch('settings'),
  saveSettings: (data: Record<string, unknown>) =>
    apiFetch('settings', { method: 'PUT', body: JSON.stringify(data) }),
  getProductsList: () => apiFetch('products-list'),
  getProductSettings: (id: number) => apiFetch(`product-settings/${id}`),
  saveProductSettings: (id: number, data: Record<string, unknown>) =>
    apiFetch(`product-settings/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
};
