import { describe, expect, it } from 'vitest';
import { linksFor, normalizePortfolio, showsIn } from '../../js/shared/portfolio';

describe('showsIn', () => {
    it('reads each placement on its own', () => {
        const link = { in_rail: true, in_footer: false };

        expect(showsIn(link, 'rail')).toBe(true);
        expect(showsIn(link, 'footer')).toBe(false);
    });

    // A missing placement can only be a half-built object in the editor, and
    // reading it as "off" would make a new link invisible without saying so.
    it('treats a missing placement as shown, not hidden', () => {
        expect(showsIn({}, 'rail')).toBe(true);
        expect(showsIn({ in_rail: false }, 'footer')).toBe(true);
    });

    it('survives being handed nothing', () => {
        expect(showsIn(null, 'rail')).toBe(true);
        expect(showsIn(undefined, 'footer')).toBe(true);
    });
});

describe('linksFor', () => {
    const links = [
        { label: 'Both', url: 'https://both.test', in_rail: true, in_footer: true },
        { label: 'Rail', url: 'https://rail.test', in_rail: true, in_footer: false },
        { label: 'Footer', url: 'https://footer.test', in_rail: false, in_footer: true },
        { label: 'Neither', url: 'https://none.test', in_rail: false, in_footer: false },
    ];

    it('draws each place its own set', () => {
        expect(linksFor(links, 'rail').map((l) => l.label)).toEqual(['Both', 'Rail']);
        expect(linksFor(links, 'footer').map((l) => l.label)).toEqual(['Both', 'Footer']);
    });

    // A row with no address yet is one the editor is still filling in.
    it('drops a link with no url, whatever its placements say', () => {
        expect(linksFor([{ label: 'Empty', url: '', in_rail: true }], 'rail')).toEqual([]);
    });

    it('copes with anything that is not a list', () => {
        for (const value of [null, undefined, {}, 'nope']) {
            expect(linksFor(value, 'rail')).toEqual([]);
        }
    });
});

describe('normalizePortfolio', () => {
    const bare = () => ({ profile: {} });

    it('fills every collection, so templates can iterate without guarding', () => {
        const payload = normalizePortfolio(bare());

        for (const key of ['social_links', 'metrics', 'expertise_items', 'projects', 'process_steps']) {
            expect(payload[key], key).toEqual([]);
        }
    });

    // A content field has no dictionary to fall back on, so an absent Dutch
    // half is filled from English rather than rendering blank.
    it('fills a missing Dutch half from the English one', () => {
        const payload = normalizePortfolio({ profile: { headline: { en: 'Only English' } } });

        expect(payload.profile.headline).toEqual({ en: 'Only English', nl: 'Only English' });
    });

    it('turns a plain string into a pair, which is what older rows hold', () => {
        const payload = normalizePortfolio({ profile: { role: 'Developer' } });

        expect(payload.profile.role).toEqual({ en: 'Developer', nl: 'Developer' });
    });

    it('does the same inside every collection', () => {
        const payload = normalizePortfolio({
            profile: {},
            metrics: [{ value: '10+', label: { en: 'Years' } }],
            projects: [{ title: 'One', summary: { nl: 'Samenvatting' } }],
        });

        expect(payload.metrics[0].label).toEqual({ en: 'Years', nl: 'Years' });
        expect(payload.projects[0].title).toEqual({ en: 'One', nl: 'One' });
        // Dutch alone stays Dutch; there is nothing better to fall back to.
        expect(payload.projects[0].summary).toEqual({ en: '', nl: 'Samenvatting' });
    });
});
