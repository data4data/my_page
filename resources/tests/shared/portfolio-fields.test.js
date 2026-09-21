import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import { translatableProfile, translatableItemFields, VISUAL_STYLES } from '../../js/shared/portfolio';

/**
 * Which fields are {en, nl} pairs is written twice — once in PHP, where the
 * validation rules are built from it, and once in JavaScript, where the editor
 * normalises them. JavaScript cannot import a PHP constant, so this reads the
 * constant instead: a field added to one side and not the other fails here
 * rather than silently arriving untranslated.
 */
const php = readFileSync(join(process.cwd(), 'app/Support/PortfolioFields.php'), 'utf8');

// The body of one `public const NAME = [ ... ];` block.
const constantBody = (name) => {
    const match = php.match(new RegExp(`const ${name} = \\[([\\s\\S]*?)\\n {4}\\];`));
    expect(match, `PortfolioFields::${name} not found`).toBeTruthy();

    return match[1];
};

const stringList = (body) => [...body.matchAll(/'([^']+)'/g)].map((match) => match[1]);

describe('the translated field lists match their PHP constants', () => {
    it('agrees on the profile fields', () => {
        expect(stringList(constantBody('TRANSLATED_PROFILE'))).toEqual(translatableProfile);
    });

    it('agrees on the child fields, collection by collection', () => {
        const body = constantBody('TRANSLATED_CHILDREN');

        const fromPhp = Object.fromEntries(
            // One line per collection: 'projects' => ['title', 'summary', 'result'],
            [...body.matchAll(/'([a-z_]+)'\s*=>\s*\[([^\]]*)\]/g)]
                .map(([, collection, fields]) => [collection, stringList(fields)]),
        );

        expect(fromPhp).toEqual(translatableItemFields);
    });
});

/**
 * The same problem one file over: the project's visual style is a list in PHP,
 * where it is validated, and a list in JavaScript, where the select is built
 * from it. A case added to one side alone fails here.
 */
describe('the visual styles match the PHP enum', () => {
    it('offers exactly the cases VisualStyle declares', () => {
        const enumFile = readFileSync(join(process.cwd(), 'app/Enums/VisualStyle.php'), 'utf8');
        const cases = [...enumFile.matchAll(/case \w+ = '([^']+)';/g)].map((match) => match[1]);

        expect(cases).toEqual(VISUAL_STYLES);
    });
});
