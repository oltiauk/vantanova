<template>
  <nav
    :class="{ 'collapsed': !expanded, 'tmp-showing': tmpShowing, 'showing': mobileShowing }"
    class="group left-0 top-0 flex flex-col fixed h-full w-full md:relative md:w-k-sidebar-width z-[999] md:z-10"
    @mouseenter="onMouseEnter"
    @mouseleave="onMouseLeave"
  >
    <section class="btn-collapse-block flex md:hidden items-center border-b border-b-white/5 h-k-header-height px-6">
      <div class="bg-white/5 rounded-full">
        <SideSheetButton @click.prevent="collapseSidebar">
          <Icon :icon="faTimes" fixed-width />
        </SideSheetButton>
      </div>
    </section>

    <section class="home-search-block p-6 flex gap-2">
      <HomeButton />
      <SearchForm class="flex-1" />
    </section>

    <section v-koel-overflow-fade class="pt-2 pb-10 overflow-y-auto space-y-8">
      <SidebarYourMusicSection />
      
      <!-- MUSIC DISCOVERY SECTION - Using proper Koel pattern -->
      <SidebarSection>
        <template #header>
          <SidebarSectionHeader>Discovery</SidebarSectionHeader>
        </template>
        
        <ul class="menu">
          <SidebarItem :href="url('music-discovery')" screen="MusicDiscovery">
            <template #icon>
              <Icon :icon="faSearch" fixed-width />
            </template>
            Discover Music
          </SidebarItem>
          
          <SidebarItem :href="url('music-preferences')" screen="MusicPreferences">
            <template #icon>
              <Icon :icon="faSliders" fixed-width />
            </template>
            Music Preferences
          </SidebarItem>
          
          <SoundCloudSidebarItem>
            SoundCloud
          </SoundCloudSidebarItem>
        </ul>
      </SidebarSection>
  
      <SidebarPlaylistsSection />
      <SidebarManageSection v-if="showManageSection" />
    </section>

    <section v-if="!isPlus && isAdmin" class="p-6 flex-1 flex flex-col-reverse">
      <BtnUpgradeToPlus />
    </section>

    <SidebarToggleButton
      v-model="expanded"
      class="opacity-0 no-hover:hidden group-hover:opacity-100 transition"
      :class="expanded || 'opacity-100'"
    />
  </nav>
</template>

<script lang="ts" setup>
import { faTimes, faSearch, faSliders } from '@fortawesome/free-solid-svg-icons'
import { computed, ref, watch } from 'vue'
import { eventBus } from '@/utils/eventBus'
import { useAuthorization } from '@/composables/useAuthorization'
import { useKoelPlus } from '@/composables/useKoelPlus'
import { useLocalStorage } from '@/composables/useLocalStorage'
import { useUpload } from '@/composables/useUpload'
import { useRouter } from '@/composables/useRouter'

import BtnUpgradeToPlus from '@/components/koel-plus/BtnUpgradeToPlus.vue'
import HomeButton from '@/components/layout/main-wrapper/sidebar/HomeButton.vue'
import SearchForm from '@/components/ui/SearchForm.vue'
import SideSheetButton from '@/components/layout/main-wrapper/side-sheet/SideSheetButton.vue'
import SidebarManageSection from './SidebarManageSection.vue'
import SidebarPlaylistsSection from './SidebarPlaylistsSection.vue'
import SidebarToggleButton from '@/components/layout/main-wrapper/sidebar/SidebarToggleButton.vue'
import SidebarYourMusicSection from './SidebarYourLibrarySection.vue'
import SidebarSection from '@/components/layout/main-wrapper/sidebar/SidebarSection.vue'
import SidebarSectionHeader from '@/components/layout/main-wrapper/sidebar/SidebarSectionHeader.vue'
import SidebarItem from '@/components/layout/main-wrapper/sidebar/SidebarItem.vue'
import SoundCloudSidebarItem from './SoundCloudSidebarItem.vue'

const { onRouteChanged, url } = useRouter()
const { isAdmin } = useAuthorization()
const { allowsUpload } = useUpload()
const { isPlus } = useKoelPlus()
const { get: lsGet, set: lsSet } = useLocalStorage()

const mobileShowing = ref(false)
const expanded = ref(!lsGet('sidebar-collapsed', false))

watch(expanded, value => lsSet('sidebar-collapsed', !value))

const showManageSection = computed(() => isAdmin.value || allowsUpload.value)

let tmpShowingHandler: number | undefined
const tmpShowing = ref(false)

const onMouseEnter = () => {
  if (expanded.value) {
    return
  }

  tmpShowingHandler = window.setTimeout(() => {
    if (expanded.value) {
      return
    }
    tmpShowing.value = true
  }, 500)
}

const onMouseLeave = (e: MouseEvent) => {
  if (!e.relatedTarget) {
    return
  }

  if (tmpShowingHandler) {
    clearTimeout(tmpShowingHandler)
    tmpShowingHandler = undefined
  }

  tmpShowing.value = false
}

onRouteChanged(_ => (mobileShowing.value = false))

const collapseSidebar = () => (mobileShowing.value = false)

/**
 * Listen to toggle sidebar event to show or hide the sidebar.
 * This should only be triggered on a mobile device.
 */
eventBus.on('TOGGLE_SIDEBAR', () => (mobileShowing.value = !mobileShowing.value))
</script>

<style lang="postcss" scoped>
@import '@/../css/partials/mixins.pcss';

nav {
  @apply bg-k-bg-secondary;
  -ms-overflow-style: -ms-autohiding-scrollbar;
  box-shadow: 0 0 5px 0 rgba(0, 0, 0, 0.1);

  &.collapsed {
    @apply w-[24px] transition-[width] duration-200;

    > *:not(.btn-toggle) {
      @apply hidden;
    }

    &.tmp-showing {
      @apply fixed h-screen bg-k-bg-primary w-k-sidebar-width shadow-2xl z-[999];

      > *:not(.btn-toggle, .btn-collapse-block) {
        @apply block;
      }

      > .home-search-block {
        @apply flex;
      }
    }
  }

  @media screen and (max-width: 768px) {
    @mixin themed-background;

    transform: translateX(-100vw);
    transition: transform 0.2s ease-in-out;

    &.showing {
      transform: translateX(0);
    }
  }
}
</style>