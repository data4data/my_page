/**
 * Every call to this app's JSON endpoints goes through here.
 *
 * Two things all of them need and none of them had consistently: an Accept
 * header, and a check on the response before parsing it. Without the header,
 * an expired session takes the auth middleware's HTML redirect to /login
 * rather than a JSON 401 — fetch follows the redirect, response.json() throws
 * on the HTML, and whichever `loading` ref was in flight stays true, leaving
 * the admin on "Loading..." for good with only an unhandled rejection to show
 * for it. bootstrap/app.php already honours expectsJson(); this is the half
 * that asks.
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

// A session that lapsed mid-visit is the one failure every caller would handle
// identically, so it is handled once: send the browser to the login page and
// let it come back. Guarded, because /login itself must not bounce to itself.
const returnToLogin = () => {
    if (window.location.pathname !== '/login') {
        window.location.href = '/login';
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

    // Tolerated rather than assumed: an error response is not guaranteed to
    // carry a JSON body, and neither is a 204.
    const payload = await response.json().catch(() => null);

    if (response.status === 401) {
        returnToLogin();
    }

    if (!response.ok) {
        // A field-level validation message says more than the caller's generic
        // one, so it wins where the server sent one.
        const validation = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;

        throw new ApiError(validation ?? payload?.message ?? message ?? 'Something went wrong.', {
            status: response.status,
            body: payload,
        });
    }

    return payload;
}
