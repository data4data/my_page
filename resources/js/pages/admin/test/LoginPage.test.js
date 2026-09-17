import { mount, flushPromises } from '@vue/test-utils';
import { afterAll, beforeEach, describe, expect, it, vi } from 'vitest';

const apiFetch = vi.fn();
vi.mock('../../../shared/api', () => ({ apiFetch: (...args) => apiFetch(...args) }));

// admin-path.js reads the tag once at module load, so the shell has to be in
// place before LoginPage imports it.
document.head.innerHTML = '<meta name="admin-path" content="test-workspace">'
    + '<meta name="csrf-token" content="test-token">';

const LoginPage = (await import('../LoginPage.vue')).default;

// jsdom does not navigate, so an assigned href would silently stay as it was.
const realLocation = window.location;

const stubs = {
    AppInput: {
        name: 'AppInput',
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    },
    AppCheckbox: { template: '<input type="checkbox" />' },
    AppButton: { template: '<button><slot /></button>' },
};

const mountPage = () => mount(LoginPage, { global: { stubs } });

const signIn = async (wrapper) => {
    await wrapper.find('form').trigger('submit');
    await flushPromises();
};

describe('LoginPage two-step sign-in', () => {
    beforeEach(() => {
        apiFetch.mockReset();
        // The decorative initials lookup on mount.
        apiFetch.mockResolvedValue({ profile: { initials: 'AB' } });
        delete window.location;
        window.location = { href: '', pathname: '/test-workspace/login' };
    });

    afterAll(() => {
        window.location = realLocation;
    });

    it('posts the password to the workspace login path, not /login', async () => {
        const wrapper = mountPage();
        await flushPromises();

        apiFetch.mockResolvedValueOnce({ redirect: '/test-workspace' });
        await signIn(wrapper);

        const [url, options] = apiFetch.mock.calls.at(-1);
        expect(url).toBe('/test-workspace/login');
        expect(options.method).toBe('POST');
    });

    // The password was right but nothing is signed in yet.
    it('shows the code step instead of navigating when a second factor is required', async () => {
        const wrapper = mountPage();
        await flushPromises();

        apiFetch.mockResolvedValueOnce({ two_factor: true });
        await signIn(wrapper);

        expect(window.location.href).toBe('');
        expect(wrapper.text()).toContain('Enter your code');
    });

    it('sends the code to the challenge endpoint and then lands', async () => {
        const wrapper = mountPage();
        await flushPromises();

        apiFetch.mockResolvedValueOnce({ two_factor: true });
        await signIn(wrapper);

        const input = wrapper.findAllComponents({ name: 'AppInput' }).at(-1);
        await input.vm.$emit('update:modelValue', '123456');

        apiFetch.mockResolvedValueOnce({ redirect: '/test-workspace' });
        await signIn(wrapper);

        const [url, options] = apiFetch.mock.calls.at(-1);
        expect(url).toBe('/test-workspace/two-factor-challenge');
        expect(options.body).toEqual({ code: '123456' });
        expect(window.location.href).toBe('/test-workspace');
    });

    it('can switch to a recovery code and sends it under its own key', async () => {
        const wrapper = mountPage();
        await flushPromises();

        apiFetch.mockResolvedValueOnce({ two_factor: true });
        await signIn(wrapper);

        await wrapper.find('button[type="button"]').trigger('click');

        const input = wrapper.findAllComponents({ name: 'AppInput' }).at(-1);
        await input.vm.$emit('update:modelValue', 'abcde-fghij');

        apiFetch.mockResolvedValueOnce({ redirect: '/test-workspace' });
        await signIn(wrapper);

        const [, options] = apiFetch.mock.calls.at(-1);
        expect(options.body).toEqual({ recovery_code: 'abcde-fghij' });
    });

    it('keeps the code step open and shows why when the code is wrong', async () => {
        const wrapper = mountPage();
        await flushPromises();

        apiFetch.mockResolvedValueOnce({ two_factor: true });
        await signIn(wrapper);

        apiFetch.mockRejectedValueOnce(new Error('That code is not valid.'));
        await signIn(wrapper);

        expect(window.location.href).toBe('');
        expect(wrapper.text()).toContain('That code is not valid.');
        expect(wrapper.text()).toContain('Enter your code');
    });
});
