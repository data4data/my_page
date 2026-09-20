<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ChevronRight, LogOut, Moon, Sun } from '@lucide/vue';
import AppPillSwitch from '../ui/AppPillSwitch.vue';
import { copy, lang, LANGUAGES, languageSwitcherShown, setLang } from '../../shared/i18n';
import { holdTheme, releaseTheme, setTheme, theme } from '../../shared/theme';
import { csrfToken } from '../../shared/api';
import { adminUrl } from '../../shared/admin-path';

const props = defineProps({
    navItems: {
        type: Array,
        required: true,
    },
    activeKey: {
        type: String,
        required: true,
    },
    initials: {
        type: String,
        default: '',
    },
    profile: {
        type: Object,
        default: () => ({}),
    },
});

defineEmits(['navigate']);

const showLanguageSwitcher = computed(() => languageSwitcherShown(props.profile));

// Two groups: destinations at the top, anything flagged `foot` pinned to the
// bottom above the switchers.
const primaryItems = computed(() => props.navItems.filter((item) => !item.foot));
const footItems = computed(() => props.navItems.filter((item) => item.foot));

const languageOptions = computed(() => LANGUAGES.map((language) => ({
    value: language.value,
    label: language.value.toUpperCase(),
})));

// Icon-only, so each half carries its own aria-label.
const themeOptions = computed(() => [
    { value: 'light', icon: Sun, ariaLabel: copy('themeLight') },
    { value: 'dark', icon: Moon, ariaLabel: copy('themeDark') },
]);

// Below lg the rail is icon-only and widens on hover or focus. Touch has
// neither, so the chevron pins it open, and the choice persists.
const PIN_KEY = 'workspace-rail-pinned';
const pinned = ref(localStorage.getItem(PIN_KEY) === '1');

const togglePin = () => {
    pinned.value = !pinned.value;
    localStorage.setItem(PIN_KEY, pinned.value ? '1' : '0');
};

// `data-theme` selects the dark half of every light-dark() in theme.css.
onMounted(holdTheme);
onBeforeUnmount(releaseTheme);
</script>

<template>
    <div class="admin-shell">
        <!-- Ahead of the rail, so a keyboard user reaches the section directly. -->
        <a href="#workspace-content" class="skip-link">{{ copy('skipToContent') }}</a>

        <!-- Reserves the footprint in the flex row; the panel inside is fixed,
             so widening it on hover overlays the sheet instead of reflowing it. -->
        <aside class="admin-rail" :class="{ pinned }">
            <div class="admin-rail-panel">
                <a href="/" class="admin-rail-brand">
                    <span class="admin-rail-badge">{{ initials }}</span>
                    <span class="admin-rail-label admin-rail-caption">{{ copy('contentStudio') }}</span>
                </a>

                <div class="admin-rail-divider"></div>

                <nav class="admin-rail-nav" :aria-label="copy('contentStudio')">
                    <button
                        v-for="item in primaryItems"
                        :key="item.key"
                        type="button"
                        class="admin-nav-item"
                        :class="{ active: item.key === activeKey }"
                        :aria-current="item.key === activeKey ? 'page' : undefined"
                        @click="$emit('navigate', item.key)"
                    >
                        <component :is="item.icon" :size="18" aria-hidden="true" />
                        <span class="admin-rail-label">{{ item.label }}</span>
                        <span v-if="item.count" class="admin-nav-count">{{ item.count }}</span>
                    </button>
                </nav>

                <div class="admin-rail-foot">
                    <button
                        v-for="item in footItems"
                        :key="item.key"
                        type="button"
                        class="admin-nav-item"
                        :class="{ active: item.key === activeKey }"
                        :aria-current="item.key === activeKey ? 'page' : undefined"
                        @click="$emit('navigate', item.key)"
                    >
                        <component :is="item.icon" :size="18" aria-hidden="true" />
                        <span class="admin-rail-label">{{ item.label }}</span>
                    </button>

                    <div class="admin-rail-divider"></div>

                    <div class="admin-rail-utils">
                        <AppPillSwitch
                            v-if="showLanguageSwitcher"
                            :options="languageOptions"
                            :model-value="lang"
                            :aria-label="copy('languageSwitch')"
                            @update:model-value="setLang"
                        />

                        <AppPillSwitch
                            :options="themeOptions"
                            :model-value="theme"
                            :aria-label="copy('themeSwitch')"
                            @update:model-value="setTheme"
                        />

                        <form method="POST" :action="adminUrl('/logout')" class="admin-rail-signout">
                            <input type="hidden" name="_token" :value="csrfToken()">
                            <button type="submit" class="admin-rail-icon-button" :aria-label="copy('logout')" :title="copy('logout')">
                                <LogOut :size="15" aria-hidden="true" />
                            </button>
                        </form>

                        <!-- Hidden from lg up, where the rail never collapses. -->
                        <button
                            type="button"
                            class="admin-rail-icon-button admin-rail-pin"
                            :aria-pressed="pinned"
                            :aria-label="pinned ? copy('navCollapse') : copy('navExpand')"
                            :title="pinned ? copy('navCollapse') : copy('navExpand')"
                            @click="togglePin"
                        >
                            <ChevronRight :size="15" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <div id="workspace-content" tabindex="-1" class="admin-frame">
            <slot />
        </div>
    </div>
</template>
