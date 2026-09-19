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
