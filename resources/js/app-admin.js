import { registerUi } from './shared/i18n';
import { adminUi } from './shared/i18n-admin';
import { mountApp } from './create-app';
import router from './router-admin';

// Only this half's strings, so the other half's never reach this bundle.
registerUi(adminUi);

mountApp(router);
