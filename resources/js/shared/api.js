import { adminUrl } from './admin-path';
import { copy } from './i18n';
import { useToast } from './toast';

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
 * The part of a failed response that is safe to put in front of a person.
 *
 * A field-level validation message beats everything: it names what to fix.
 * Otherwise only a 4xx `message` is used — those are written for a reader,
 * while a 5xx carries the exception's own text, which with APP_DEBUG on is
 * the first line of a stack trace. That is exactly the strange error a user
 * should never see; it stays on the ApiError's `body` for the console.
 */
const serverMessage = (status, payload) => {
    const validation = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;

    if (validation) {
        return validation;
    }

    return status >= 400 && status < 500 ? (payload?.message ?? null) : null;
};

/**
 * What to tell the user about a failure, or null when there is nothing to
 * say. Every catch block in the app goes through this, so one kind of failure
 * reads the same wherever it happens.
 *
 * @param {unknown} error     Whatever was thrown.
 * @param {string} [fallback] Shown when the failure carries nothing better.
 */
export const errorMessage = (error, fallback) => {
    // fetch() itself rejected: no network, DNS, a blocked request. Its own
    // message is "Failed to fetch", which tells a reader nothing.
    if (error instanceof TypeError) {
        return copy('errorOffline');
    }

    if (!(error instanceof ApiError)) {
        // Something threw that was not a response at all — a bug, whose
        // message reads like "Cannot read properties of undefined". That is
        // the strange error a user should never be shown, so it goes to the
        // console and they get the caller's own words.
        console.error(error);

        return fallback || copy('error');
    }

    // Already being bounced to the login page; a toast would flash and go.
    if (error.status === 401) {
        return null;
    }

    // Laravel's CSRF/session-expiry status. The generic text would send
    // someone hunting for a fault in what they typed.
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
        throw new ApiError(serverMessage(response.status, payload) ?? message ?? copy('error'), {
            status: response.status,
            body: payload,
        });
    }

    return payload;
}
