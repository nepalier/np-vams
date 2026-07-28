/**
 * Thin wrapper around fetch() that attaches the Sanctum bearer token from
 * localStorage and redirects to /login on a 401 -- every authenticated
 * page (Assignments, and everything that follows it) should call the API
 * through this rather than raw fetch(), so token handling and session
 * expiry are handled in exactly one place.
 */
export async function apiFetch<T = unknown>(path: string, options: RequestInit = {}): Promise<T> {
  const token = localStorage.getItem('npvams_token');

  const response = await fetch(path, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers ?? {}),
    },
  });

  if (response.status === 401) {
    localStorage.removeItem('npvams_token');
    window.location.href = '/login';
    throw new Error('Unauthenticated');
  }

  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    const message = body?.errors?.[0]?.detail ?? `Request to ${path} failed (${response.status}).`;
    throw new Error(message);
  }

  return response.json();
}
