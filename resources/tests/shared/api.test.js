import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, apiFetch, errorMessage } from '../../js/shared/api';
import { copy } from '../../js/shared/i18n';

const jsonResponse = (status, body) => ({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(body),
});

describe('apiFetch', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
        window.location.pathname = '/';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('asks for JSON on every request, so an expired session cannot return HTML', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { ok: true }));
        vi.stubGlobal('fetch', fetchMock);

        await apiFetch('/portfolio');

        const [, options] = fetchMock.mock.calls[0];
        expect(options.headers.Accept).toBe('application/json');
        expect(options.headers['X-CSRF-TOKEN']).toBe('test-token');
        // No body, so no Content-Type.
        expect(options.headers['Content-Type']).toBeUndefined();
        expect(options.body).toBeUndefined();
    });

    it('serialises a body and declares its type', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, {}));
        vi.stubGlobal('fetch', fetchMock);

        await apiFetch('/tasks', { method: 'POST', body: { title: 'Write' } });

        const [, options] = fetchMock.mock.calls[0];
        expect(options.method).toBe('POST');
        expect(options.headers['Content-Type']).toBe('application/json');
        expect(JSON.parse(options.body)).toEqual({ title: 'Write' });
    });

    it('throws instead of returning an unparsed error body', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(500, null)));

        await expect(apiFetch('/tasks', { message: 'Could not load the tasks.' }))
            .rejects.toThrow('Could not load the tasks.');
    });

    it('prefers the server field message over the caller generic one', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
            jsonResponse(422, { errors: { title: ['The title field is required.'] } }),
        ));

        await expect(apiFetch('/tasks', { method: 'POST', body: {}, message: 'Could not create the task.' }))
            .rejects.toThrow('The title field is required.');
    });

    it('exposes the status so callers can tell validation from failure', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(422, { message: 'Nope' })));

        await expect(apiFetch('/tasks')).rejects.toBeInstanceOf(ApiError);
        await apiFetch('/tasks').catch((error) => expect(error.status).toBe(422));
    });

    it('survives an error response that carries no JSON at all', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 503,
            json: () => Promise.reject(new SyntaxError('Unexpected token <')),
        }));

        await expect(apiFetch('/tasks', { message: 'Could not load the tasks.' }))
            .rejects.toThrow('Could not load the tasks.');
    });
});

/**
 * One handler decides what a failure reads like, so the same kind of failure
 * says the same thing wherever it happens — and so nothing technical reaches
 * a user. Before this, seven catch blocks threw the real message away for a
 * fixed string, and the ones that did not could print a stack trace's first
 * line straight into a toast.
 */
describe('errorMessage', () => {
    it('prefers the field-level message, which names what to fix', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(422, { errors: { title: ['The title is required.'] } })));

        await expect(apiFetch('/x', { method: 'POST', body: {} })).rejects.toThrow('The title is required.');
    });

    it('passes a 4xx message through, because those are written for a reader', () => {
        expect(errorMessage(new ApiError('That code is not valid.', { status: 422 }))).toBe('That code is not valid.');
    });

    // A 500's message is the exception's own text, which with APP_DEBUG on is
    // the first line of a stack trace.
    it('never shows a server error message, only the caller\u2019s words', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(500, { message: 'SQLSTATE[42S22]: Column not found' })));

        await expect(apiFetch('/x', { message: 'Could not load the report.' }))
            .rejects.toThrow('Could not load the report.');
    });

    it('says nothing for a 401, which is already bouncing to the login page', () => {
        expect(errorMessage(new ApiError('Unauthenticated.', { status: 401 }))).toBeNull();
    });

    it('explains a 419 rather than blaming what was typed', () => {
        expect(errorMessage(new ApiError('CSRF token mismatch.', { status: 419 })))
            .toBe(copy('errorSessionExpired'));
    });

    it('turns a failed fetch into a connection problem', () => {
        expect(errorMessage(new TypeError('Failed to fetch'))).toBe(copy('errorOffline'));
    });

    // Something threw that was not a response at all. Its message reads like
    // "Cannot read properties of undefined" — the strange error this exists
    // to keep off the screen.
    it('hides a bug behind the caller\u2019s wording', () => {
        const logged = vi.spyOn(console, 'error').mockImplementation(() => {});

        expect(errorMessage(new RangeError('bad index'), 'Could not save the task.'))
            .toBe('Could not save the task.');
        expect(logged).toHaveBeenCalled();

        logged.mockRestore();
    });
});
