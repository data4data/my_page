import { registerUi } from './shared/i18n';
import { publicUi } from './shared/i18n-public';
import { mountApp } from './create-app';
import router from './router-public';

// Only this half's strings, so the other half's never reach this bundle.
registerUi(publicUi);

mountApp(router);
