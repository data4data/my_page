import { adminUrl } from './admin-path';
import { copy } from './i18n';
import { useToast } from './toast';

export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export class ApiError extends Error {
    constructor(message, { status, body } = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.body = body;
    }

}

// Guarded, so the login page cannot bounce to itself.
const returnToLogin = () => {
    const login = adminUrl('/login');

    if (window.location.pathname !== login) {
        window.location.href = login;
    }
};

/**
 * The part of a failed response that is safe to show. A validation message
 * wins, since it names what to fix. A 5xx message never is: that is the
 * exception's own text, and with APP_DEBUG on it is a stack trace.
 */
const serverMessage = (status, payload) => {
    const validation = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;

    if (validation) {
        return validation;
    }

    return status >= 400 && status < 500 ? (payload?.message ?? null) : null;
};

/**
 * What to tell the user about a failure, or null when there is nothing to say.
 * Every catch block goes through this, so one failure reads the same anywhere.
 *
 * @param {unknown} error     Whatever was thrown.
 * @param {string} [fallback] Shown when the failure carries nothing better.
 */
export const errorMessage = (error, fallback) => {
    // fetch() itself rejected: no network. Its own message says nothing useful.
    if (error instanceof TypeError) {
        return copy('errorOffline');
    }

    if (!(error instanceof ApiError)) {
        // Not a response at all, so a bug: the console gets it, the user gets
        // the caller's own words.
        console.error(error);

        return fallback || copy('error');
    }

    // Already being bounced to the login page; a toast would flash and go.
    if (error.status === 401) {
        return null;
    }

    // Laravel's CSRF/session-expiry status, not a fault in what they typed.
    if (error.status === 419) {
        return copy('errorSessionExpired');
    }

    return error.message || fallback || copy('error');
};

/** errorMessage(), shown. The one line a catch block needs. */
export const reportError = (error, fallback) => {
    const message = errorMessage(error, fallback);

    if (message) {
        useToast().error(message);
    }
};

/**
 * Every call to this app's JSON endpoints goes through here. The Accept header
 * is load-bearing: without it an expired session takes the auth middleware's
 * HTML redirect instead of a JSON 401, and the loading ref never clears.
 *
 * @param {string} url
 * @param {{method?: string, body?: unknown, message?: string}} options
 *   `message` is the error text thrown when the request fails; a validation
 *   message from the server wins over it.
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
        throw new ApiError(serverMessage(response.status, payload) ?? message ?? copy('error'), {
            status: response.status,
            body: payload,
        });
    }

    return payload;
}
