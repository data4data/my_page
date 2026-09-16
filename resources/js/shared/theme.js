import { ref, watch } from 'vue';

// Light and dark, in the order the rail offers them. Two states, not three:
// the design's switcher is a binary pill, and a visitor who has chosen
// nothing simply starts on whatever the OS asks for (see below).
export const THEMES = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
];

// Named like `site-language` in i18n.js, and for the same reason: nothing in
// this app is tied to one owner, so a key naming a particular person would
// outlive a fork that changed everything else.
const STORAGE_KEY = 'workspace-theme';

const storedTheme = () => {
    const value = localStorage.getItem(STORAGE_KEY);

    return value === 'light' || value === 'dark' ? value : null;
};

const systemPrefersDark = () => window.matchMedia?.('(prefers-color-scheme: dark)');

// Module-level singleton, like `lang`. The rail's switcher and whatever CSS
// reads `data-theme` are both looking at this one ref.
export const theme = ref(storedTheme() ?? (systemPrefersDark()?.matches ? 'dark' : 'light'));

// Until the owner picks a side, the workspace keeps following the OS — so a
// machine that flips to dark at sunset carries the workspace with it. The
// first explicit choice stops that for good, which is what `stored` tests:
// once something is in storage this listener stops assigning.
systemPrefersDark()?.addEventListener?.('change', (event) => {
    if (!storedTheme()) {
        theme.value = event.matches ? 'dark' : 'light';
    }
});

export const setTheme = (value) => {
    theme.value = value;
    localStorage.setItem(STORAGE_KEY, value);
};

// How many components currently want the workspace theme applied. A count
// rather than a boolean because a route change can mount the next layout
// before the previous one unmounts — with a boolean, that teardown would
// strip the attribute off the layout that had just asked for it.
let holders = 0;

const applyTheme = () => document.documentElement.setAttribute('data-theme', theme.value);

// Declared here, at module scope, rather than started inside holdTheme(). A
// watcher created during a component's setup or onMounted belongs to that
// component's effect scope and Vue stops it when that component unmounts —
// which would kill the watcher on the first layout's teardown even though a
// second one is still holding, leaving the switcher inert. A singleton's
// watcher has to outlive every one of its holders.
watch(theme, () => {
    if (holders > 0) {
        applyTheme();
    }
});

// `data-theme` is what picks the half of every `light-dark()` in theme.css,
// so holding it is the same as being in dark mode. Only the workspace holds
// it: the public visit card shares forms.css and overlays that append to
// <body>, and both have to stay light whatever the owner chose in here.
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
