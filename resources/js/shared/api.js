import { adminUrl } from './admin-path';

/**
 * Every call to this app's JSON endpoints goes through here.
 *
 * The Accept header is the important part. Without it, an expired session
 * gets the auth middleware's HTML redirect to /login instead of a JSON 401,
 * response.json() throws on the HTML, and whichever `loading` ref was in
 * flight never clears.
 */
export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class ApiError extends Error {
    constructor(message, { status, body } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.body = body;
    }

    /** First validation message Laravel returned, if this was a 422. */
    get validationMessage() {
        return this.body?.errors ? Object.values(this.body.errors)[0]?.[0] ?? null : null;
    }
}

// Every caller would handle an expired session the same way, so it is handled
// once. Guarded so the login page cannot bounce to itself.
const returnToLogin = () => {
    const login = adminUrl('/login');

    if (window.location.pathname !== login) {
        window.location.href = login;
    }
};

/**
 * @param {string} url
 * @param {{method?: string, body?: unknown, message?: string}} options
 *   `message` is the error text thrown when the request fails; a validation
 *   message from the server wins over it when there is one.
 */
export async function apiFetch(url, { method = 'GET', body, message } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        headers,
        ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });

    // An error response, or a 204, need not carry a JSON body.
    const payload = await response.json().catch(() => null);

    if (response.status === 401) {
        returnToLogin();
    }

    if (!response.ok) {
        // A field-level message beats the caller's generic one.
        const validation = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;

        throw new ApiError(validation ?? payload?.message ?? message ?? 'Something went wrong.', {
            status: response.status,
            body: payload,
        });
    }

    return payload;
}
