// The workspace prefix is per install (ADMIN_PATH) and comes from a <meta>
// tag, so nothing in the bundle hardcodes it. Read once: the tag is
// server-rendered and never changes.
//
// The fallback applies where the tag is absent — jsdom tests, and pages served
// to someone who cannot reach the workspace. Nothing there follows it.
const configured = document.querySelector('meta[name="admin-path"]')?.content?.trim();

export const adminBase = `/${(configured || 'control-room').replace(/^\/+|\/+$/g, '')}`;

// adminUrl('/tasks') -> '/<prefix>/tasks'; adminUrl() -> '/<prefix>'.
export const adminUrl = (suffix = '') => `${adminBase}${suffix}`;
