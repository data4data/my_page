// The prefix is per install and comes from a meta tag, so nothing in the bundle
// hardcodes it. The fallback applies where the tag is absent — tests, and pages
// served to someone who cannot reach the workspace.
const configured = document.querySelector('meta[name="admin-path"]')?.content?.trim();

export const adminBase = `/${(configured || 'control-room').replace(/^\/+|\/+$/g, '')}`;

// adminUrl('/tasks') -> '/<prefix>/tasks'; adminUrl() -> '/<prefix>'.
export const adminUrl = (suffix = '') => `${adminBase}${suffix}`;
