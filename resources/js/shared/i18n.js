import { ref } from 'vue';

// Interface chrome strings (not page content — that comes from the DB as {en, nl}).
export const ui = {
    en: {
        loading: 'Loading OA page...',
        admin: 'Admin',
        work: 'Projects',
        about: 'About',
        expertise: 'Expertise',
        contact: 'Contact',
        scroll: 'Scroll',
        railCta: 'Explore more',
        how: 'How I work',
        fromComplex: 'From complex',
        toSimple: 'to simple.',
        processCopy: 'I break down complexity into clear systems that are easy to use, reliable, and built to scale.',
        architecture: 'System Architecture',
        processDetails: 'Process description',
        featured: 'Featured projects',
        viewAll: 'View GitHub projects',
        quote: 'Simplicity is the ultimate sophistication.',
        contactHeadline: 'Let’s build something exceptional together.',
        contactHeadlineLines: ['Let’s build something', 'exceptional', 'together.'],
        getInTouch: 'Get in touch',
        save: 'Save changes',
        saving: 'Saving...',
        saved: 'Saved. Public page updated.',
        restore: 'Restore default content',
        restoring: 'Restoring...',
        restored: 'Default content restored. You can edit and save it now.',
        restoreHint: 'This replaces the current admin content with the seeded OA defaults.',
        empty: 'No items yet. Add one manually or restore the seeded defaults.',
        error: 'Could not save changes. Please check the fields and try again.',
        studio: 'Content studio for',
        studioCopy: 'Edit the bilingual visit-card content without touching code. Items save in the order shown here.',
        logout: 'Log out',
        forDevelopers: 'For developers',
        connectTitle: 'Say hi, developer to developer',
        connectCopy: 'Working on something interesting or just want to connect? Send a note — this goes straight to me, no public reply.',
        connectName: 'Name',
        connectEmail: 'Email',
        connectMessage: 'Message',
        connectCompany: 'Company / role (optional)',
        connectPortfolio: 'GitHub / portfolio link (optional)',
        connectLinkedin: 'LinkedIn profile (optional)',
        connectSubmit: 'Send message',
        connectSubmitting: 'Sending...',
        connectSuccess: 'Thanks — your message is in. I’ll get back to you.',
        connectError: 'Could not send your message. Please check the fields and try again.',
        connectClose: 'Close',
    },
    nl: {
        loading: 'OA-pagina laden...',
        admin: 'Admin',
        work: 'Projecten',
        about: 'Over mij',
        expertise: 'Expertise',
        contact: 'Contact',
        scroll: 'Scroll',
        railCta: 'Ontdek meer',
        how: 'Zo werk ik',
        fromComplex: 'Van complex',
        toSimple: 'naar simpel.',
        processCopy: 'Ik breng complexiteit terug naar duidelijke systemen die prettig werken, betrouwbaar zijn en kunnen meegroeien.',
        architecture: 'Systeemarchitectuur',
        processDetails: 'Procesbeschrijving',
        featured: 'Uitgelichte projecten',
        viewAll: 'Bekijk GitHub-projecten',
        quote: 'Eenvoud is de ultieme verfijning.',
        contactHeadline: 'Laten we iets uitzonderlijks bouwen.',
        contactHeadlineLines: ['Laten we iets', 'uitzonderlijks', 'bouwen.'],
        getInTouch: 'Neem contact op',
        save: 'Wijzigingen opslaan',
        saving: 'Opslaan...',
        saved: 'Opgeslagen. De publieke pagina is bijgewerkt.',
        restore: 'Standaardcontent herstellen',
        restoring: 'Herstellen...',
        restored: 'Standaardcontent is hersteld. Je kunt deze nu bewerken en opslaan.',
        restoreHint: 'Dit vervangt de huidige admincontent door de standaard OA-seeddata.',
        empty: 'Nog geen items. Voeg er een toe of herstel de standaard seeddata.',
        error: 'Opslaan is niet gelukt. Controleer de velden en probeer opnieuw.',
        studio: 'Contentstudio voor',
        studioCopy: 'Bewerk de tweetalige visit-card content zonder code aan te raken. Items worden opgeslagen in de volgorde die je hier ziet.',
        logout: 'Uitloggen',
        forDevelopers: 'Voor developers',
        connectTitle: 'Zeg hallo, developer tot developer',
        connectCopy: 'Werk je aan iets interessants of wil je gewoon contact? Stuur een bericht — dit komt rechtstreeks bij mij terecht, geen openbare reactie.',
        connectName: 'Naam',
        connectEmail: 'E-mail',
        connectMessage: 'Bericht',
        connectCompany: 'Bedrijf / rol (optioneel)',
        connectPortfolio: 'GitHub- / portfoliolink (optioneel)',
        connectLinkedin: 'LinkedIn-profiel (optioneel)',
        connectSubmit: 'Bericht versturen',
        connectSubmitting: 'Versturen...',
        connectSuccess: 'Bedankt — je bericht is verstuurd. Ik neem contact met je op.',
        connectError: 'Versturen is niet gelukt. Controleer de velden en probeer opnieuw.',
        connectClose: 'Sluiten',
    },
};

// Module-level singleton: the language toggle is global site state, shared
// by whichever page (public/admin) happens to be mounted.
export const lang = ref(localStorage.getItem('oa-language') || 'en');

export const setLang = (value) => {
    lang.value = value;
    localStorage.setItem('oa-language', value);
};

// UI chrome string for the current language.
export const copy = (key) => ui[lang.value][key] ?? ui.en[key] ?? key;

// {en, nl} content field -> string for the current language.
export const t = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value[lang.value] || value.en || value.nl || '';
    }

    return value ?? '';
};
