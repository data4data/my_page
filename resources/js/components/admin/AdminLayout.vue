<script setup>
import { computed } from 'vue';
import { LogOut } from '@lucide/vue';
import AppButton from '../ui/AppButton.vue';
import { copy, lang, LANGUAGES, languageSwitcherShown, setLang } from '../../shared/i18n';
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

// Turning the switcher off hides it here too, not just on the public page.
const showLanguageSwitcher = computed(() => languageSwitcherShown(props.profile));

defineEmits(['navigate']);
</script>

<template>
    <main class="flex min-h-screen flex-col bg-white text-ink">
        <header class="layer-header sticky top-0 shrink-0 border-b border-sand bg-cream/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4">
                <div class="flex flex-wrap items-baseline gap-3">
                    <a href="/" class="admin-header-title text-3xl font-semibold tracking-normal">{{ initials }}</a>
                    <span class="admin-header-title text-3xl font-semibold tracking-normal text-accent">{{ copy('contentStudio') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <div v-if="showLanguageSwitcher" class="flex items-center gap-1">
                        <AppButton
                            v-for="language in LANGUAGES"
                            :key="language.value"
                            variant="lang"
                            size="sm"
                            :active="lang === language.value"
                            @click="setLang(language.value)"
                        >
                            {{ language.value.toUpperCase() }}
                        </AppButton>
                    </div>
                    <form method="POST" :action="adminUrl('/logout')">
                        <input type="hidden" name="_token" :value="csrfToken()">
                        <AppButton variant="secondary" size="sm" type="submit" :aria-label="copy('logout')">
                            <LogOut :size="16" />
                        </AppButton>
                    </form>
                </div>
            </div>
        </header>

        <!-- Chrome (header + this left rail) is visually separated from the
             page content by one continuous border, not just a padded gap:
             the row below is stretched to at least the remaining viewport
             height, so the aside's border runs from directly under the
             header down to the bottom of the viewport on every page, not
             just however far that page's own content happens to reach. -->
        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col lg:flex-row">
            <!-- Same cream as the header, so the chrome (top bar + rail)
                 reads as one surface against the white content area. -->
            <aside class="admin-rail hidden bg-cream/90 px-5 py-8 lg:block lg:w-60 lg:shrink-0 lg:border-r lg:border-sand">
                <nav :aria-label="copy('contentStudio')" class="flex flex-col gap-2">
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        type="button"
                        class="admin-nav-item"
                        :class="{ active: item.key === activeKey }"
                        :aria-current="item.key === activeKey ? 'page' : undefined"
                        @click="$emit('navigate', item.key)"
                    >
                        <component :is="item.icon" :size="18" />
                        {{ item.label }}
                    </button>
                </nav>
            </aside>

            <!-- No left/bottom padding from lg up, so the panel sits flush
                 against the aside's divider and runs off the bottom of the
                 page. flex-col so .admin-panel's lg:flex-1 has a column to
                 grow in. -->
            <div class="admin-content flex min-w-0 flex-1 flex-col px-5 py-8 lg:pb-0 lg:pl-0">
                <slot />
            </div>
        </div>

        <!-- Small screens get a bottom bar instead of the rail: four sections
             is what a bottom bar is for, it stays in reach of a thumb, and the
             stacked rail was spending most of a phone's first screenful on
             navigation before any content appeared. -->
        <nav class="admin-bottom-nav lg:hidden" :aria-label="copy('contentStudio')">
            <button
                v-for="item in navItems"
                :key="item.key"
                type="button"
                class="admin-bottom-nav-item"
                :class="{ active: item.key === activeKey }"
                :aria-current="item.key === activeKey ? 'page' : undefined"
                @click="$emit('navigate', item.key)"
            >
                <component :is="item.icon" :size="20" aria-hidden="true" />
                <span>{{ item.label }}</span>
            </button>
        </nav>

        <slot name="fab" />
    </main>
</template>
