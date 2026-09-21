import { registerUi } from '../js/shared/i18n';
import { publicUi } from '../js/shared/i18n-public';
import { adminUi } from '../js/shared/i18n-admin';

/**
 * The entry points each register one half of the dictionary (see i18n.js), so
 * the public bundle never carries the workspace's strings. Tests mount
 * components directly and run no entry point, so they register both here.
 */
registerUi(publicUi);
registerUi(adminUi);

/**
 * jsdom implements no matchMedia, and PrimeVue's Select asks for one on mount
 * to follow the viewport's orientation. Anything rendering a real AppSelect —
 * the settings smoke test, for one — dies without this.
 */
if (typeof window !== 'undefined' && !window.matchMedia) {
    window.matchMedia = (query) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
    });
}
