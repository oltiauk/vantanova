<template>
  <section
    id="mainContent"
    class="flex-1 relative overflow-hidden"
  >
    <!--
      Most of the views are render-expensive and have their own UI states (viewport/scroll position), e.g. the song
      lists), so we use v-show.
      For those that don't need to maintain their own UI state, we use v-if and enjoy some code-splitting juice.
    -->
    <VisualizerScreen v-if="screen === 'Visualizer'" />
    <AlbumArtOverlay v-if="showAlbumArtOverlay && currentSong && isSong(currentSong)" :album="currentSong?.album_id" />

    <HomeScreen v-show="screen === 'Home'" />
    <QueueScreen v-show="screen === 'Queue'" />
    <AllSongsScreen v-show="screen === 'Songs'" />
    <AlbumListScreen v-show="screen === 'Albums'" />
    <ArtistListScreen v-show="screen === 'Artists'" />
    <PlaylistScreen v-show="screen === 'Playlist'" />
    <FavoritesScreen v-show="screen === 'Favorites'" />
    <RecentlyPlayedScreen v-show="screen === 'RecentlyPlayed'" />
    <UploadScreen v-show="screen === 'Upload'" />
    <SearchExcerptsScreen v-show="screen === 'Search.Excerpt'" />
    <GenreScreen v-show="screen === 'Genre'" />
    <PodcastListScreen v-show="screen === 'Podcasts'" />
    <MediaBrowser v-if="useMediaBrowser" v-show="screen === 'MediaBrowser'" />
    
    <!-- Music Discovery and Preferences -->
    <MusicDiscoveryScreen v-show="screen === 'MusicDiscovery'" />
    <MusicPreferencesScreen v-show="screen === 'MusicPreferences'" />
    <SoundCloudScreen v-show="screen === 'SoundCloud'" />

    <GenreListScreen v-if="screen === 'Genres'" />
    <SearchSongResultsScreen v-if="screen === 'Search.Songs'" />
    <AlbumScreen v-if="screen === 'Album'" />
    <ArtistScreen v-if="screen === 'Artist'" />
    <SettingsScreen v-if="screen === 'Settings'" />
    <ProfileScreen v-if="screen === 'Profile'" />
    <PodcastScreen v-if="screen === 'Podcast'" />
    <EpisodeScreen v-if="screen === 'Episode'" />
    <UserListScreen v="screen === 'Users'" />
    <YouTubeScreen v-if="useYouTube" v-show="screen === 'YouTube'" />
    <NotFoundScreen v-if="screen === '404'" />
  </section>
</template>

<script lang="ts" setup>
import { onMounted, ref, toRef } from 'vue'
import { isSong } from '@/utils/typeGuards'
import { defineAsyncComponent, requireInjection } from '@/utils/helpers'
import { preferenceStore } from '@/stores/preferenceStore'
import { useRouter } from '@/composables/useRouter'
import { useThirdPartyServices } from '@/composables/useThirdPartyServices'
import { CurrentPlayableKey } from '@/symbols'

import HomeScreen from '@/components/screens/HomeScreen.vue'
import QueueScreen from '@/components/screens/QueueScreen.vue'
import AlbumListScreen from '@/components/screens/AlbumListScreen.vue'
import ArtistListScreen from '@/components/screens/ArtistListScreen.vue'
import GenreListScreen from '@/components/screens/GenreListScreen.vue'
import AllSongsScreen from '@/components/screens/AllSongsScreen.vue'
import PlaylistScreen from '@/components/screens/PlaylistScreen.vue'
import FavoritesScreen from '@/components/screens/FavoritesScreen.vue'
import RecentlyPlayedScreen from '@/components/screens/RecentlyPlayedScreen.vue'
import UploadScreen from '@/components/screens/UploadScreen.vue'
import SearchExcerptsScreen from '@/components/screens/search/SearchExcerptsScreen.vue'
import PodcastListScreen from '@/components/screens/PodcastListScreen.vue'
import MusicDiscoveryScreen from '@/components/screens/MusicDiscoveryScreen.vue'
import MusicPreferencesScreen from '@/components/screens/MusicPreferencesScreen.vue'
import SoundCloudScreen from '@/components/screens/SoundCloudScreen.vue'
import { commonStore } from '@/stores/commonStore'

const UserListScreen = defineAsyncComponent(() => import('@/components/screens/UserListScreen.vue'))
const AlbumArtOverlay = defineAsyncComponent(() => import('@/components/ui/AlbumArtOverlay.vue'))
const AlbumScreen = defineAsyncComponent(() => import('@/components/screens/AlbumScreen.vue'))
const ArtistScreen = defineAsyncComponent(() => import('@/components/screens/ArtistScreen.vue'))
const GenreScreen = defineAsyncComponent(() => import('@/components/screens/GenreScreen.vue'))
const PodcastScreen = defineAsyncComponent(() => import('@/components/screens/PodcastScreen.vue'))
const EpisodeScreen = defineAsyncComponent(() => import('@/components/screens/EpisodeScreen.vue'))
const SettingsScreen = defineAsyncComponent(() => import('@/components/screens/SettingsScreen.vue'))
const ProfileScreen = defineAsyncComponent(() => import('@/components/screens/ProfileScreen.vue'))
const YouTubeScreen = defineAsyncComponent(() => import('@/components/screens/YouTubeScreen.vue'))
const SearchSongResultsScreen = defineAsyncComponent(() => import('@/components/screens/search/SearchSongResultsScreen.vue'))
const NotFoundScreen = defineAsyncComponent(() => import('@/components/screens/NotFoundScreen.vue'))
const VisualizerScreen = defineAsyncComponent(() => import('@/components/screens/VisualizerScreen.vue'))
const MediaBrowser = defineAsyncComponent(() => import('@/components/screens/MediaBrowserScreen.vue'))

const { useYouTube } = useThirdPartyServices()
const { onRouteChanged, getCurrentScreen } = useRouter()

const currentSong = requireInjection(CurrentPlayableKey, ref(undefined))

const showAlbumArtOverlay = toRef(preferenceStore.state, 'show_album_art_overlay')
const screen = ref<ScreenName>('Home')

onRouteChanged(route => (screen.value = route.screen))

onMounted(() => (screen.value = getCurrentScreen()))

const useMediaBrowser = toRef(commonStore.state, 'uses_media_browser')
</script>