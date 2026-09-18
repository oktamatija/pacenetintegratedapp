/**
 * Pacenet Standard API & Auth Client
 * Automatically attaches localStorage Bearer token and credentials: 'include'
 */

export const getAuthToken = () => {
  return localStorage.getItem('pacenet_token') || '';
};

export const getAuthHeaders = (extraHeaders = {}) => {
  const token = getAuthToken();
  const headers = {
    'Content-Type': 'application/json',
    ...extraHeaders
  };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  return headers;
};

export const authFetch = async (url, options = {}) => {
  const headers = getAuthHeaders(options.headers || {});
  return fetch(url, {
    credentials: 'include',
    ...options,
    headers
  });
};
