// The private workspace's URL prefix is chosen per install (ADMIN_PATH in
// .env -> config/admin.php) and injected by the Blade shell as a <meta> tag,
// so nothing in the JS bundle hardcodes it. Read once at module load rather
// than per call: the tag is server-rendered and never changes afterwards.
//
// The fallback matches config/admin.php's own default, and only applies where
// there is no shell to read from (unit tests under jsdom).
const configured = document.querySelector('meta[name="admin-path"]')?.content?.trim();

export const adminBase = `/${(configured || 'control-room').replace(/^\/+|\/+$/g, '')}`;

// adminUrl('/tasks') -> '/<prefix>/tasks'; adminUrl() -> '/<prefix>'.
export const adminUrl = (suffix = '') => `${adminBase}${suffix}`;
