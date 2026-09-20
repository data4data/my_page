import { ref, watch } from 'vue';

// Two states, not three: a visitor who has chosen nothing follows the OS.
export const THEMES = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
];

// One preference for the whole site: a theme that flips as you cross from the
// visit card into the workspace reads as broken.
const STORAGE_KEY = 'site-theme';

const storedTheme = () => {
    const value = localStorage.getItem(STORAGE_KEY);

    return value === 'light' || value === 'dark' ? value : null;
};

const systemPrefersDark = () => window.matchMedia?.('(prefers-color-scheme: dark)');

// A module-level singleton, like `lang`.
export const theme = ref(storedTheme() ?? (systemPrefersDark()?.matches ? 'dark' : 'light'));

// Follows the OS until someone picks a side; the first explicit choice stops
// that for good, which is what storedTheme() tests.
systemPrefersDark()?.addEventListener?.('change', (event) => {
    if (!storedTheme()) {
        theme.value = event.matches ? 'dark' : 'light';
    }
});

export const setTheme = (value) => {
    theme.value = value;
    localStorage.setItem(STORAGE_KEY, value);
};

// A count, not a boolean: a route change can mount the next layout before the
// previous one tears down.
let holders = 0;

const applyTheme = () => document.documentElement.setAttribute('data-theme', theme.value);

// Module scope, not inside holdTheme(): a watcher created in a component's
// setup dies with that component, even while a second holder is still there.
watch(theme, () => {
    if (holders > 0) {
        applyTheme();
    }
});

// `data-theme` picks the half of every light-dark() in theme.css. Held rather
// than set once: with no holder it comes off and color-scheme returns to
// normal, which is what the login page and the first paint get.
export const holdTheme = () => {
    holders += 1;
    applyTheme();
};

export const releaseTheme = () => {
    holders = Math.max(0, holders - 1);

    if (holders === 0) {
        document.documentElement.removeAttribute('data-theme');
    }
};
