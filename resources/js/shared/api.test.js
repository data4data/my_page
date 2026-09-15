import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, apiFetch } from './api';

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
