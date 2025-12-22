<template>
  <nav
    :class="{ 'collapsed': !expanded, 'tmp-showing': tmpShowing, 'showing': mobileShowing }"
    class="group left-0 top-0 flex flex-col fixed h-full w-full md:relative md:w-k-sidebar-width z-[999] md:z-10"
    @mouseenter="onMouseEnter"
    @mouseleave="onMouseLeave"
  >
    <section class="btn-collapse-block flex md:hidden items-center border-b border-b-transparent h-k-header-height px-6">
      <div class="bg-white/5 rounded-full">
        <SideSheetButton @click.prevent="collapseSidebar">
          <Icon :icon="faTimes" fixed-width />
        </SideSheetButton>
      </div>
    </section>

    <section class="home-search-block p-6">
      <HomeButton />
    </section>

    <section v-koel-overflow-fade class="pt-2 pb-10 overflow-y-auto space-y-8">
      <!-- MUSIC DISCOVERY SECTION - Using proper Koel pattern -->
      <SidebarSection>
        <template #header>
          <SidebarSectionHeader>Discovery</SidebarSectionHeader>
        </template>

        <ul class="menu">
          <SidebarItem :href="url('music-discovery')" screen="MusicDiscovery">
            <template #icon>
              <img src="/public/icons/RelatedTracks-Icon.svg" alt="Related Tracks" class="w-5 h-5 object-contain sidebar-icon-svg">
            </template>
            Related Tracks
          </SidebarItem>

          <SidebarItem :href="url('similar-artists')" screen="SimilarArtists">
            <template #icon>
              <img src="/public/icons/SimilarArtists-Icon.svg" alt="Similar Artists" class="w-5 h-5 object-contain sidebar-icon-svg">
            </template>
            Similar Artists
          </SidebarItem>

          <SidebarItem :href="url('label-search')" screen="LabelSearch">
            <template #icon>
              <img src="/public/icons/LabelSearch-icon.svg" alt="Label Search" class="w-5 h-5 object-contain sidebar-icon-svg">
            </template>
            Label Search
          </SidebarItem>

          <SidebarItem :href="url('label-watchlist')" screen="LabelWatchlist" @click="handleLabelWatchlistClick">
            <template #icon>
              <img src="/public/icons/LabelWatchlist.svg" alt="Labels Watchlist" class="w-5 h-5 object-contain sidebar-icon-svg">
            </template>
            Labels Watchlist
          </SidebarItem>

          <SidebarItem :href="url('artist-watchlist')" screen="ArtistWatchlist" @click="handleArtistWatchlistClick">
            <template #icon>
              <img src="/public/icons/ArtistWatchlist.svg" alt="Artists Watchlist" class="w-5 h-5 object-contain sidebar-icon-svg">
            </template>
            Artists Watchlist
          </SidebarItem>

          <!-- SoundCloud sections with spacing -->
          <li class="soundcloud-section-spacer" />

          <SoundCloudSidebarItem>
            SoundCloud Deep Search
          </SoundCloudSidebarItem>

          <SidebarItem :href="url('soundcloud-related-tracks')" screen="SoundCloudRelatedTracks">
            <template #icon>
              <img src="/public/img/soundcloud-icon.svg" alt="SoundCloud" class="w-5 h-5 object-contain">
            </template>
            SoundCloud Related Tracks
          </SidebarItem>

          <!-- <SidebarItem :href="url('genre-by-year')" screen="GenreByYear">
            <template #icon>
              <img src="/public/icons/TimeExplorer-icon.svg" alt="Time Explorer" class="w-4 h-4 object-contain sidebar-icon-svg">
            </template>
            Time Explorer
          </SidebarItem> -->
        </ul>
      </SidebarSection>

      <SidebarManageSection v-if="showManageSection" />
    </section>

    <SidebarToggleButton
      v-model="expanded"
      class="opacity-0 no-hover:hidden group-hover:opacity-100 transition"
      :class="expanded || 'opacity-100'"
    />
  </nav>
</template>

<script lang="ts" setup>
import { faEye, faMusic, faSearch, faSliders, faTimes, faUsers } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { computed, ref, watch } from 'vue'
import { eventBus } from '@/utils/eventBus'
import { useAuthorization } from '@/composables/useAuthorization'
import { useKoelPlus } from '@/composables/useKoelPlus'
import { useLocalStorage } from '@/composables/useLocalStorage'
import { useUpload } from '@/composables/useUpload'
import { useRouter } from '@/composables/useRouter'

import HomeButton from '@/components/layout/main-wrapper/sidebar/HomeButton.vue'
import SideSheetButton from '@/components/layout/main-wrapper/side-sheet/SideSheetButton.vue'
import SidebarManageSection from './SidebarManageSection.vue'
import SidebarToggleButton from '@/components/layout/main-wrapper/sidebar/SidebarToggleButton.vue'
import SidebarSection from '@/components/layout/main-wrapper/sidebar/SidebarSection.vue'
import SidebarSectionHeader from '@/components/layout/main-wrapper/sidebar/SidebarSectionHeader.vue'
import SidebarItem from '@/components/layout/main-wrapper/sidebar/SidebarItem.vue'
import SoundCloudSidebarItem from './SoundCloudSidebarItem.vue'

const { onRouteChanged, url } = useRouter()
const { isPlus } = useKoelPlus()
const { get: lsGet, set: lsSet } = useLocalStorage()

const mobileShowing = ref(false)
const expanded = ref(!lsGet('sidebar-collapsed', false))

watch(expanded, value => lsSet('sidebar-collapsed', !value))

const showManageSection = true // Always show Manage section (Saved/Banned tracks available to all users)

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

const ARTIST_WATCHLIST_REFRESH_KEY = 'koel-artist-watchlist-refresh-request'
const ARTIST_WATCHLIST_LAST_REFRESH_KEY = 'koel-artist-watchlist-last-refresh'
const TWENTY_FOUR_HOURS = 24 * 60 * 60 * 1000
const LABEL_WATCHLIST_REFRESH_KEY = 'koel-label-watchlist-refresh-request'
const LABEL_WATCHLIST_LAST_REFRESH_KEY = 'koel-label-watchlist-last-refresh'

const handleArtistWatchlistClick = () => {
  try {
    const lastRefreshRaw = localStorage.getItem(ARTIST_WATCHLIST_LAST_REFRESH_KEY)
    const lastRefresh = lastRefreshRaw ? Number.parseInt(lastRefreshRaw, 10) : 0
    const stale = Number.isFinite(lastRefresh) ? Date.now() - lastRefresh > TWENTY_FOUR_HOURS : true

    if (stale) {
      localStorage.setItem(ARTIST_WATCHLIST_REFRESH_KEY, Date.now().toString())
      window.dispatchEvent(new Event('artist-watchlist-sidebar-click'))
    }
  } catch (error) {
    console.warn('Failed to prepare artist watchlist refresh request:', error)
  }
}

const handleLabelWatchlistClick = () => {
  try {
    const lastRefreshRaw = localStorage.getItem(LABEL_WATCHLIST_LAST_REFRESH_KEY)
    const lastRefresh = lastRefreshRaw ? Number.parseInt(lastRefreshRaw, 10) : 0
    const stale = Number.isFinite(lastRefresh) ? Date.now() - lastRefresh > TWENTY_FOUR_HOURS : true

    if (stale) {
      localStorage.setItem(LABEL_WATCHLIST_REFRESH_KEY, Date.now().toString())
      window.dispatchEvent(new Event('label-watchlist-sidebar-click'))
    }
  } catch (error) {
    console.warn('Failed to prepare label watchlist refresh request:', error)
  }
}

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
        @apply block;
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

/* Style SVG icons to match FontAwesome icon colors */
:deep(.sidebar-icon-svg) {
  filter: brightness(0) saturate(100%) invert(76%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(100%)
    contrast(100%);
  opacity: 0.7;
}

li.current :deep(.sidebar-icon-svg) {
  filter: brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(100%)
    contrast(100%);
  opacity: 1;
}

/* Spacing for SoundCloud sections */
.soundcloud-section-spacer {
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
  height: 0;
}
</style>
