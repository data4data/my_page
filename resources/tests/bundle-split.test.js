import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * The public page and the workspace are two bundles, and the whole point is
 * that the public one cannot contain the workspace's code.
 *
 * Today anyone could fetch AdminPage's chunk from the public site and read
 * the private API's endpoint names out of it. One stray import puts it back,
 * and nothing else would notice — the app would still work.
 *
 * This walks the real import graph from each entry point rather than the
 * build output, so it runs on a fresh clone and fails on the import rather
 * than on the bundle.
 */
const root = resolve(process.cwd(), 'resources/js');

// Static `from '../js/x'` and lazy `import('../js/x')` alike — a route component is
// loaded the second way, which is exactly the case that matters here.
const importsIn = (source) => [
    ...source.matchAll(/(?:from|import)\s*\(?\s*['"](\.[^'"]+)['"]/g),
].map((match) => match[1]);

const resolveSpecifier = (fromFile, specifier) => {
    const base = resolve(dirname(fromFile), specifier);

    for (const candidate of [base, `${base}.js`, `${base}.vue`, join(base, 'index.js')]) {
        if (existsSync(candidate) && !candidate.endsWith('/')) {
            return candidate;
        }
    }

    return null;
};

const reachableFrom = (entry) => {
    const seen = new Set();
    const queue = [resolve(root, entry)];

    while (queue.length > 0) {
        const file = queue.pop();

        if (seen.has(file)) {
            continue;
        }

        seen.add(file);

        for (const specifier of importsIn(readFileSync(file, 'utf8'))) {
            const target = resolveSpecifier(file, specifier);

            if (target) {
                queue.push(target);
            }
        }
    }

    return [...seen].map((file) => relative(root, file)).sort();
};

describe('bundle split', () => {
    it('never reaches the workspace from the public entry point', () => {
        const reachable = reachableFrom('app-public.js');

        // Non-empty, or a typo in the entry name would pass by reaching nothing.
        expect(reachable).toContain('pages/public/PublicPage.vue');
        expect(reachable.filter((file) => file.startsWith('pages/admin/'))).toEqual([]);
        expect(reachable.filter((file) => file.startsWith('components/admin/'))).toEqual([]);
        // Every planner request lives here, and only the workspace makes them.
        expect(reachable).not.toContain('shared/planning.js');
        // 493 strings naming what the workspace contains — "Two-step sign-in",
        // "Recovery codes". i18n.js used to import both halves, so they
        // shipped to every visitor even after the bundles were split.
        expect(reachable).not.toContain('shared/i18n-admin.js');
    });

    it('reaches the workspace from the admin entry point', () => {
        const reachable = reachableFrom('app-admin.js');

        expect(reachable).toContain('pages/admin/AdminPage.vue');
        expect(reachable).toContain('pages/admin/LoginPage.vue');
        expect(reachable).toContain('shared/i18n-admin.js');
    });

    // The two entries would otherwise drift on PrimeVue options or the router
    // plumbing, and only one of them would be wrong.
    it('bootstraps both halves through one shared file', () => {
        for (const entry of ['app-public.js', 'app-admin.js']) {
            expect(readFileSync(join(root, entry), 'utf8')).toContain("from './create-app'");
        }
    });
});
