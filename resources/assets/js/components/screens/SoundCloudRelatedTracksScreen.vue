<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader header-image="/VantaNova-Logo.svg">
        SoundCloud Related Tracks
        <template #meta>
          <span v-if="seedTrack" class="text-text-secondary">
            Related to: {{ seedTrack.title }} by {{ seedTrack.artist }}
          </span>
          <span v-else-if="searchQuery" class="text-text-secondary">
            Search results for: "{{ searchQuery }}"
          </span>
        </template>
      </ScreenHeader>
    </template>

    <div class="p-6">
      <!-- Welcome Message - Only show when no results and no search -->
      <div v-if="!showingSeedResults && !tracks.length && !seedSearchQuery.trim() && !error" class="max-w-2xl mx-auto text-center mb-8">
        <!-- <p class="text-k-text-secondary">
          Search for a seed track to find related tracks, or click "Related Tracks" while playing a SoundCloud song.
        </p> -->
      </div>

      <!-- Search Box for Seed Track - Always visible -->
      <div class="seed-selection mb-8">
        <!-- Search Container -->
        <div class="search-container mb-6">
          <div class="rounded-lg p-4">
            <div class="max-w-4xl mx-auto">
              <div ref="searchContainer" class="relative">
                <!-- Search Input -->
                <div class="flex">
                  <input
                    v-model="seedSearchQuery"
                    type="text"
                    placeholder="Search for a Seed Track"
                    class="flex-1 py-3 pl-4 pr-4 bg-white/10 rounded-l-lg border-0 focus:outline-none text-white text-lg search-input"
                    @input="onSeedSearchInput"
                    @focus="onSeedSearchFocus"
                    @keydown.enter="performSeedSearch"
                  >
                  <button
                    class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 text-white rounded-r-lg transition-colors flex items-center justify-center"
                    :disabled="!seedSearchQuery.trim() || loading"
                    @click="performSeedSearch"
                  >
                    <Icon :icon="faSearch" class="w-5 h-5" />
                  </button>
                </div>

                <!-- Loading Animation -->
                <div
                  v-if="loading && seedSearchQuery.trim()"
                  class="absolute z-50 w-full bg-k-bg-secondary border border-k-border rounded-lg mt-1 shadow-xl"
                >
                  <div class="flex items-center justify-center py-8">
                    <div class="flex items-center gap-3">
                      <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
                      <span class="text-k-text-secondary">Searching for tracks...</span>
                    </div>
                  </div>
                </div>

                <!-- Search Dropdown -->
                <div
                  v-if="!loading && seedSearchResults.length > 0 && showDropdown"
                  class="absolute z-50 w-full border border-k-border rounded-lg mt-1 shadow-xl"
                  style="background-color: #302f30; top: 100%;"
                >
                  <div class="max-h-80 rounded-lg overflow-hidden overflow-y-auto">
                    <div v-for="track in seedSearchResults.slice(0, dropdownDisplayLimit)" :key="`suggestion-${track.id}`">
                      <div
                        class="flex items-center justify-between px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors group border-b border-k-border/30 last:border-b-0"
                        @click="selectSeedTrack(track)"
                      >
                        <!-- Track Info -->
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-k-text-primary group-hover:text-gray-200 transition-colors truncate">
                            {{ track.user?.username || 'Unknown' }} - {{ track.title }}
                          </div>
                        </div>

                        <!-- Duration Badge -->
                        <div class="bg-k-bg-primary/30 px-2 py-1 rounded text-k-text-tertiary text-xs font-mono ml-3 flex-shrink-0">
                          {{ formatDuration(track.duration) }}
                        </div>
                      </div>
                    </div>

                    <!-- Load More Button -->
                    <div v-if="seedSearchResults.length > dropdownDisplayLimit" class="px-4 py-3 text-center border-t border-k-border bg-k-bg-tertiary/20">
                      <button
                        class="px-4 py-2 bg-k-accent hover:bg-k-accent/80 text-white rounded text-sm font-medium transition-colors"
                        @click.stop="loadMoreDropdownResults"
                      >
                        Load More ({{ seedSearchResults.length - dropdownDisplayLimit }} remaining)
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Selected Seed Track Display - Compact -->
      <div v-if="seedTrack" class="selected-seed mb-4 relative z-20">
        <div class="max-w-4xl mx-auto">
          <div class="text-sm font-medium mb-2">Seed Track:</div>
          <div class="bg-k-bg-secondary/50 border border-k-border rounded-lg px-3 py-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 flex-1 min-w-0">
                <Icon :icon="faCheck" class="w-4 h-4 text-k-accent flex-shrink-0" />
                <span class="text-k-text-primary font-medium truncate">{{ seedTrack.artist }} - {{ seedTrack.title }}</span>
              </div>
              <button
                class="p-1 hover:bg-red-600/20 text-k-text-tertiary hover:text-red-400 rounded transition-colors flex-shrink-0 ml-2"
                title="Clear seed track"
                @click="clearSeedTrack"
              >
                <Icon :icon="faTimes" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Error state -->
      <div v-if="error" class="text-center py-6 mb-6">
        <div class="text-red-400 mb-4">{{ error }}</div>
        <button
          class="px-4 py-2 bg-k-accent hover:bg-k-accent/80 rounded text-white transition-colors"
          @click="retry"
        >
          Try Again
        </button>
      </div>

      <!-- Seed Search Results (when searching for seed track) - Table view -->
      <div v-if="showingSeedResults && !loading && seedSearchResults.length > 0">
        <div class="flex items-center gap-3 mb-6 px-3">
          <span class="text-sm text-white/80">Ban listened tracks</span>
          <button
            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
            :class="banListenedTracks ? 'bg-green-500' : 'bg-gray-600'"
            @click="banListenedTracks = !banListenedTracks"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
              :class="banListenedTracks ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>

        <SoundCloudTrackTable
          ref="seedTracksTable"
          :tracks="displayedSeedTracks"
          :start-index="0"
          :saved-tracks="savedTracks"
          :blacklisted-tracks="blacklistedTracks"
          :processing-track="processingTrack"
          :listened-tracks="listenedTracks"
          @play="playTrack"
          @related-tracks="(track) => { console.log('🎵 [EVENT] related-tracks event received:', track); selectSeedTrack(track); }"
          @save-track="saveTrack"
          @blacklist-track="blacklistTrack"
          @mark-listened="markTrackAsListened"
        />

        <div v-if="hasMoreSeedTracks && !loading" class="flex items-center justify-center mt-8">
          <button
            class="px-4 py-2 bg-k-accent text-white rounded hover:bg-k-accent/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loadingMoreSeed"
            @click="loadMoreSeedTracks"
          >
            <Icon v-if="loadingMoreSeed" :icon="faSpinner" spin class="mr-2" />
            Load More
          </button>
        </div>
      </div>

      <!-- Related Tracks Results -->
      <div v-if="!showingSeedResults && tracks.length > 0">
        <!-- Ban Listened Tracks Toggle and Sort by Dropdown -->
        <div class="flex items-end justify-between mb-4 relative">
          <!-- Ban Listened Tracks Toggle - Left aligned -->
          <div class="flex items-end gap-3">
            <span class="text-sm text-white/80">Ban listened tracks</span>
            <button
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
              :class="banListenedTracks ? 'bg-green-500' : 'bg-gray-600'"
              @click="banListenedTracks = !banListenedTracks"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="banListenedTracks ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </div>

          <!-- Sort by Dropdown - Right aligned -->
          <div class="relative">
            <button
              class="px-4 py-2 rounded-lg font-medium transition flex items-center gap-2 bg-white/10 text-white/80 hover:bg-white/20"
              style="background-color: rgba(47, 47, 47, 255) !important;"
              @click="toggleLikesRatioDropdown"
              @blur="hideLikesRatioDropdown"
            >
              {{ getSortText() }}
              <Icon :icon="faChevronDown" class="text-xs" />
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="showLikesRatioDropdown"
              class="absolute right-0 mt-12 w-52 rounded-lg shadow-lg z-50"
              style="background-color: rgb(67,67,67,255);"
            >
              <button
                class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition rounded-t-lg"
                :class="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
                :style="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
                @mousedown.prevent="setLikesRatioFilter('newest')"
              >
                Most Recent
              </button>
              <button
                class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition rounded-b-lg"
                :class="likesRatioFilter === 'none' ? 'background-color: rgb(67,67,67,255)' : ''"
                :style="likesRatioFilter === 'none' ? 'background-color: rgb(67,67,67,255)' : ''"
                @mousedown.prevent="setLikesRatioFilter('none')"
              >
                Most Streams
              </button>
            </div>
          </div>
        </div>

        <SoundCloudTrackTable
          ref="relatedTracksTable"
          :tracks="displayedTracks"
          :start-index="0"
          :saved-tracks="savedTracks"
          :blacklisted-tracks="blacklistedTracks"
          :processing-track="processingTrack"
          :listened-tracks="listenedTracks"
          @play="playTrack"
          @pause="pauseTrack"
          @seek="seekTrack"
          @related-tracks="findRelatedForTrack"
          @save-track="saveTrack"
          @blacklist-track="blacklistTrack"
          @mark-listened="markTrackAsListened"
        />

        <div v-if="hasMoreRelatedTracks && !loading" class="flex items-center justify-center mt-8">
          <button
            class="px-4 py-2 bg-k-accent text-white rounded hover:bg-k-accent/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loadingMoreRelated"
            @click="loadMoreRelatedTracks"
          >
            <Icon v-if="loadingMoreRelated" :icon="faSpinner" spin class="mr-2" />
            Load More
          </button>
        </div>
      </div>

      <!-- Loading Animation for related tracks (only when loading related, not when searching for seeds) -->
      <div v-if="!showingSeedResults && loading && tracks.length === 0 && !seedSearchQuery.trim()" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-white mb-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        </div>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faBan, faCheck, faChevronDown, faHeart, faSearch, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { debounce } from 'lodash'
import { eventBus } from '@/utils/eventBus'
import { soundcloudService, type SoundCloudTrack } from '@/services/soundcloudService'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { useRouter } from '@/composables/useRouter'
import { http } from '@/services/http'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import SoundCloudTrackTable from '@/components/ui/soundcloud/SoundCloudTrackTable.vue'

const { isCurrentScreen, onRouteChanged } = useRouter()

// Animation state management
const initialLoadComplete = ref(false)

// Template refs for SoundCloud tables
const seedTracksTable = ref<{ closeInlinePlayer: () => void } | null>(null)
const relatedTracksTable = ref<{ closeInlinePlayer: () => void } | null>(null)

const loading = ref(false)
const error = ref('')
const tracks = ref<SoundCloudTrack[]>([])
const seedTrack = ref<{ title: string, artist: string, trackUrn: string } | null>(null)
const searchQuery = ref('')
const seedSearchQuery = ref('')
const currentData = ref<any>(null)
const seedSearchResults = ref<SoundCloudTrack[]>([])
const showingSeedResults = ref(false)
const showDropdown = ref(false)
const dropdownDisplayLimit = ref(10) // How many results to show in dropdown
const searchContainer = ref<HTMLElement | null>(null)
const seedHasMore = ref(false)
const seedLastQuery = ref('')
let searchSuggestionTimer: ReturnType<typeof setTimeout> | null = null

// Pagination state
const displayLimitRelated = ref(20)
const displayLimitSeed = ref(20)
const loadingMoreRelated = ref(false)
const loadingMoreSeed = ref(false)

// Sort filter state
const likesRatioFilter = ref<'none' | 'newest'>('newest')
const showLikesRatioDropdown = ref(false)

// Initialize global blacklist filtering for SoundCloud
const {
  filterSoundCloudTracks,
  loadBlacklistedItems,
  addTrackToBlacklist,
} = useBlacklistFiltering()

// Local banned artists tracking (for Similar Artists compatibility)
const bannedArtists = ref(new Set<string>()) // Store artist names for SoundCloud tracks
const allTracks = ref<SoundCloudTrack[]>([]) // Store all tracks for sorting

// Music preferences state
const savedTracks = ref<Set<string>>(new Set())
const blacklistedTracks = ref<Set<string>>(new Set())
const clientUnsavedTracks = ref<Set<string>>(new Set()) // Tracks unsaved by client
const processingTrack = ref<string | number | null>(null)
const listenedTracks = ref<Set<string>>(new Set()) // Tracks that have been listened to
const banListenedTracks = ref(false)
const pendingAutoBannedTracks = ref(new Set<string>())

// Load-more computed helpers
const totalTracks = computed(() => tracks.value.length)
const displayedTracks = computed(() => tracks.value.slice(0, displayLimitRelated.value))
const hasMoreRelatedTracks = computed(() => totalTracks.value > displayLimitRelated.value)

const totalSeedTracks = computed(() => seedSearchResults.value.length)
const displayedSeedTracks = computed(() => seedSearchResults.value.slice(0, displayLimitSeed.value))
const hasMoreSeedTracks = computed(() => totalSeedTracks.value > displayLimitSeed.value)

// Format duration from milliseconds to mm:ss
const formatDuration = (ms?: number): string => {
  if (!ms) {
    return '0:00'
  }
  const minutes = Math.floor(ms / 60000)
  const seconds = Math.floor((ms % 60000) / 1000)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

// Deduplicate seed tracks by ID
const dedupeSeedTracks = (incoming: SoundCloudTrack[]): SoundCloudTrack[] => {
  const existingIds = new Set(seedSearchResults.value.map(t => t.id))
  return incoming.filter(t => !existingIds.has(t.id))
}

const ensureTrackVisible = (targetIndex: number, isSeed: boolean) => {
  const step = 20
  if (isSeed) {
    if (targetIndex >= displayLimitSeed.value) {
      const nextLimit = Math.ceil((targetIndex + 1) / step) * step
      displayLimitSeed.value = Math.min(nextLimit, seedSearchResults.value.length)
    }
  } else {
    if (targetIndex >= displayLimitRelated.value) {
      const nextLimit = Math.ceil((targetIndex + 1) / step) * step
      displayLimitRelated.value = Math.min(nextLimit, tracks.value.length)
    }
  }
}

const loadRelatedTracks = async (trackUrn: string) => {
  loading.value = true
  error.value = ''

  try {
    console.log('🎵 Loading related tracks for URN:', trackUrn)
    const relatedTracks = await soundcloudService.getRelatedTracks(trackUrn)

    // Apply global blacklist filtering (tracks + artists)
    const globalFiltered = filterSoundCloudTracks(relatedTracks)

    // Apply local banned artists filtering (for Similar Artists compatibility)
    const bannedArtistsFiltered = filterBannedArtists(globalFiltered)

    // Apply local blacklisted tracks filtering (user preferences)
    const localFiltered = filterBlacklistedTracks(bannedArtistsFiltered)

    allTracks.value = localFiltered // Store all tracks for sorting
    applyFiltering() // Apply current sort filter
    displayLimitRelated.value = Math.min(20, tracks.value.length || 0) // Reset visible count when loading new tracks
    console.log('🎵 Loaded', tracks.value.length, 'related tracks (after filtering blacklisted tracks/artists)')
    console.log(`🚫 Filtered out ${relatedTracks.length - tracks.value.length} blacklisted items`)
    initialLoadComplete.value = true
  } catch (err: any) {
    error.value = `Failed to load related tracks: ${err.message || 'Unknown error'}`
    console.error('🎵 Related tracks error:', err)
  } finally {
    loading.value = false
  }
}

const searchTracks = async (query: string) => {
  loading.value = true
  error.value = ''

  try {
    console.log('🎵 Searching for seed tracks:', query)
    const searchResults = await soundcloudService.search({
      searchQuery: query,
      limit: 50,
    })
    tracks.value = searchResults
    console.log('🎵 Found', searchResults.length, 'search results')
  } catch (err: any) {
    error.value = `Failed to search tracks: ${err.message || 'Unknown error'}`
    console.error('🎵 Search error:', err)
  } finally {
    loading.value = false
  }
}

const searchSeedTracks = async (query: string) => {
  loading.value = true
  error.value = ''
  displayLimitSeed.value = 20 // Reset visible count for new search
  dropdownDisplayLimit.value = 10 // Reset dropdown display limit for new search
  seedHasMore.value = false
  seedLastQuery.value = query

  try {
    console.log('🎵 Searching for seed tracks:', query)
    const pageLimit = 200
    const response = await soundcloudService.searchWithPagination({
      searchQuery: query,
      limit: pageLimit,
      offset: 0,
    })

    // For NEW seed track searches: filter out banned artists and blacklisted tracks
    // Apply global blacklist filtering (tracks + artists)
    const globalFiltered = filterSoundCloudTracks(response.tracks || [])

    // Apply local banned artists filter (for Similar Artists compatibility)
    const bannedArtistsFiltered = filterBannedArtists(globalFiltered)

    // Apply local blacklisted tracks filtering (user preferences)
    const filteredResults = filterBlacklistedTracks(bannedArtistsFiltered)

    seedSearchResults.value = filteredResults
    seedHasMore.value = response.hasMore || (response.tracks?.length || 0) >= pageLimit
    displayLimitSeed.value = Math.min(20, seedSearchResults.value.length || 0)

    // Show dropdown automatically when results are available
    showDropdown.value = seedSearchResults.value.length > 0 && query.trim() === seedSearchQuery.value.trim()

    console.log('🎵 Found', seedSearchResults.value.length, 'seed track candidates (after filtering)')
    initialLoadComplete.value = true
  } catch (err: any) {
    error.value = `Failed to search seed tracks: ${err.message || 'Unknown error'}`
    console.error('🎵 Seed search error:', err)
    showDropdown.value = false
  } finally {
    loading.value = false
  }
}

const selectSeedTrack = async (track: SoundCloudTrack) => {
  console.log('🎵 [SEED SELECT] Selected seed track:', track.title, 'by', track.user.username)
  console.log('🎵 [SEED SELECT] Track object:', track)

  // Clear search suggestion timer
  if (searchSuggestionTimer) {
    clearTimeout(searchSuggestionTimer)
    searchSuggestionTimer = null
  }

  // Set the seed track info
  seedTrack.value = {
    title: track.title,
    artist: track.user.username,
    trackUrn: `soundcloud:tracks:${track.id}`,
  }

  console.log('🎵 [SEED SELECT] seedTrack.value set to:', seedTrack.value)
  console.log('🎵 [SEED SELECT] seedTrack.value is truthy?', !!seedTrack.value)

  // Clear seed search results and show related tracks loading state
  showingSeedResults.value = false
  showDropdown.value = false
  seedSearchResults.value = []
  seedSearchQuery.value = ''

  // Clear existing tracks so loading animation will show
  tracks.value = []
  allTracks.value = []

  console.log('🎵 [SEED SELECT] About to load related tracks for:', `soundcloud:tracks:${track.id}`)

  // Load related tracks for this seed
  await loadRelatedTracks(`soundcloud:tracks:${track.id}`)

  console.log('🎵 [SEED SELECT] After loadRelatedTracks, seedTrack.value:', seedTrack.value)
}

const playTrack = async (track: SoundCloudTrack) => {
  try {
    console.log('🎵 [RELATED] Starting playTrack for:', track.title)

    // Check if this track is already current and just paused
    const currentTrack = soundcloudPlayerStore.state.currentTrack
    if (currentTrack && currentTrack.id === track.id) {
      console.log('🎵 [RELATED] Resuming current track')
      soundcloudPlayerStore.setPlaying(true)
      return
    }

    // Show player immediately with loading state
    soundcloudPlayerStore.show(track, '')

    // Update navigation state based on track position
    updateNavigationState(track)

    console.log('🎵 Loading SoundCloud player for track:', track.title)

    const embedUrl = await soundcloudService.getEmbedUrl(track.id, {
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true,
    })

    console.log('🎵 Got embed URL:', embedUrl)
    soundcloudPlayerStore.setEmbedUrl(embedUrl)
    console.log('🎵 SoundCloud player loaded successfully')
  } catch (err: any) {
    console.error('🎵 Failed to load SoundCloud player:', err)
    error.value = `Failed to load SoundCloud player: ${err.message || 'Unknown error'}`
    soundcloudPlayerStore.hide() // Close player on error
  }
}

const pauseTrack = (track?: SoundCloudTrack) => {
  console.log('🎵 [RELATED] Pausing track:', track?.title || 'current')
  soundcloudPlayerStore.setPlaying(false)
}

const seekTrack = (position: number) => {
  console.log('🎵 [RELATED] Seeking to position:', `${position}%`)
  // Here you could implement actual seek functionality if needed
  // For now, this is just for UI feedback
}

const playTrackAndFindRelated = async (track: SoundCloudTrack) => {
  console.log('🎵 Playing track and finding related tracks for:', track.title)

  // First, play the track
  await playTrack(track)

  // Then, find related tracks for this song
  await findRelatedForTrack(track)
}

const findRelatedForTrack = async (track: SoundCloudTrack) => {
  console.log('🎵 Finding related tracks for:', track.title)

  // Set the seed track info
  seedTrack.value = {
    title: track.title,
    artist: track.user.username,
    trackUrn: `soundcloud:tracks:${track.id}`,
  }

  // Clear seed search results and show related tracks loading state
  showingSeedResults.value = false
  showDropdown.value = false
  seedSearchResults.value = []
  seedSearchQuery.value = ''

  // Clear existing tracks so loading animation will show
  tracks.value = []
  allTracks.value = []

  // Load related tracks for this seed
  await loadRelatedTracks(`soundcloud:tracks:${track.id}`)

  // After loading related tracks, if this track is currently playing, update navigation
  const currentTrack = soundcloudPlayerStore.state.currentTrack
  if (currentTrack && currentTrack.id === track.id) {
    updateNavigationState(track)
  }
}

const updateNavigationState = (track: SoundCloudTrack) => {
  // For navigation, we need to consider the full tracks array, not just the current page
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  const currentIndex = currentTracksList.findIndex(t => t.id === track.id)
  const canSkipPrevious = currentIndex > 0
  const canSkipNext = currentIndex >= 0 && currentIndex < currentTracksList.length - 1

  soundcloudPlayerStore.setNavigationState(canSkipPrevious, canSkipNext)
  console.log('🎵 Navigation state updated:', { canSkipPrevious, canSkipNext, currentIndex, totalTracks: currentTracksList.length })
}

const getCurrentTrackIndex = () => {
  const currentTrack = soundcloudPlayerStore.state.currentTrack
  if (!currentTrack) {
    console.log('🎵 [DEBUG] No current track in store')
    return -1
  }

  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  const index = currentTracksList.findIndex(t => t.id === currentTrack.id)
  console.log('🎵 [DEBUG] getCurrentTrackIndex:', {
    currentTrackId: currentTrack.id,
    currentTrackTitle: currentTrack.title,
    foundIndex: index,
    totalTracks: currentTracksList.length,
    showingSeedResults: showingSeedResults.value,
  })
  return index
}

const skipToPrevious = () => {
  const currentIndex = getCurrentTrackIndex()
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value

  if (currentIndex > 0) {
    const previousTrack = currentTracksList[currentIndex - 1]
    console.log('🎵 Skipping to previous track:', previousTrack.title)

    ensureTrackVisible(currentIndex - 1, showingSeedResults.value)

    // Always just play the track, don't select it as seed
    playTrack(previousTrack)
  } else {
    console.log('🎵 Cannot skip to previous: already at first track')
  }
}

const skipToNext = () => {
  const currentIndex = getCurrentTrackIndex()
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value

  if (currentIndex >= 0 && currentIndex < currentTracksList.length - 1) {
    const nextTrack = currentTracksList[currentIndex + 1]
    console.log('🎵 Skipping to next track:', nextTrack.title)

    ensureTrackVisible(currentIndex + 1, showingSeedResults.value)

    // Always just play the track, don't select it as seed
    playTrack(nextTrack)
  } else {
    console.log('🎵 Cannot skip to next: already at last track')
  }
}

const retry = () => {
  if (currentData.value) {
    handleScreenLoad(currentData.value)
  }
}

const handleScreenLoad = (data: any) => {
  currentData.value = data

  if (data.type === 'related') {
    // Load related tracks for a specific track
    seedTrack.value = {
      title: data.trackTitle,
      artist: data.artist,
      trackUrn: data.trackUrn,
    }
    searchQuery.value = ''
    loadRelatedTracks(data.trackUrn)
  } else if (data.type === 'search') {
    // Search for seed tracks
    seedTrack.value = null
    searchQuery.value = data.searchQuery
    searchTracks(data.searchQuery)
  }
}

// Helper function to get track key
const getTrackKey = (track: SoundCloudTrack): string => {
  const artist = track.user?.username || 'Unknown'
  const title = track.title || 'Untitled'
  return `${artist}-${title}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Check if track is saved
const isTrackSaved = (track: SoundCloudTrack): boolean => {
  const trackKey = getTrackKey(track)
  return savedTracks.value.has(trackKey) && !clientUnsavedTracks.value.has(trackKey)
}

// Check if track is blacklisted
const isTrackBlacklisted = (track: SoundCloudTrack): boolean => {
  return blacklistedTracks.value.has(getTrackKey(track))
}

// Check if ban button should be active (red)
const isBanButtonActive = (track: SoundCloudTrack): boolean => {
  return isTrackBlacklisted(track) && !isTrackSaved(track)
}

// Save track function
const saveTrack = async (track: SoundCloudTrack) => {
  const trackKey = getTrackKey(track)

  if (isTrackSaved(track)) {
    // Unsave track: Update UI immediately for better UX
    savedTracks.value.delete(trackKey)

    // Since no DELETE endpoint exists for saved tracks, use client-side tracking
    clientUnsavedTracks.value.add(trackKey)

    // Save to localStorage for persistence across page reloads
    try {
      const unsavedList = Array.from(clientUnsavedTracks.value)
      localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
    } catch (error) {
      // Failed to save unsaved tracks to localStorage
    }

    // ALSO remove from blacklist when unsaving
    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const deleteData = {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (response.success) {
        blacklistedTracks.value.delete(trackKey)
        console.log('✅ Track removed from blacklist on unsave:', track.title)

        window.dispatchEvent(new CustomEvent('track-unblacklisted', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      }
    } catch (error) {
      console.warn('Failed to remove track from blacklist on unsave:', error)
    }

    // Trigger SavedTracksScreen refresh
    try {
      window.dispatchEvent(new CustomEvent('track-unsaved', {
        detail: { track, trackKey },
      }))
      localStorage.setItem('track-unsaved-timestamp', Date.now().toString())
    } catch (error) {
      // Event dispatch failed, not critical
    }
  } else {
    // Reload client unsaved tracks from localStorage first to get the latest list
    // This ensures we don't lose tracks that were trashed in other screens
    try {
      const stored = localStorage.getItem('koel-client-unsaved-tracks')
      if (stored) {
        const unsavedList = JSON.parse(stored)
        clientUnsavedTracks.value = new Set(unsavedList)
      }
    } catch (error) {
      // Failed to load client unsaved tracks
    }

    // Update UI immediately for instant feedback
    savedTracks.value.add(trackKey)
    clientUnsavedTracks.value.delete(trackKey)

    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/save-track', {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
        spotify_id: null, // SoundCloud tracks don't have Spotify IDs
        label: null,
        popularity: null,
        followers: track.user?.followers_count || null,
        streams: track.playback_count || null,
        release_date: track.created_at || null,
        preview_url: null,
        track_count: 1,
        is_single_track: true,
      })

      if (!response.success) {
        savedTracks.value.delete(trackKey)
        throw new Error(response.error || 'Failed to save track')
      }

      // ALSO add to blacklist when saving
      try {
        const blacklistResponse = await http.post('music-preferences/blacklist-track', {
          spotify_id: null,
          isrc: isrcValue,
          track_name: track.title,
          artist_name: track.user?.username || 'Unknown',
        })

        if (blacklistResponse.success) {
          blacklistedTracks.value.add(trackKey)
          addTrackToBlacklist({
            id: String(track.id),
            name: track.title,
            artist: track.user?.username || 'Unknown',
          } as any)
          console.log('✅ Track blacklisted in backend:', track.title)

          window.dispatchEvent(new CustomEvent('track-blacklisted', {
            detail: { track, trackKey },
          }))
          localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
        }
      } catch (error) {
        console.error('❌ Error blacklisting track:', error)
      }

      // Update localStorage
      try {
        const unsavedList = Array.from(clientUnsavedTracks.value)
        localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
      } catch (error) {
        // Failed to update unsaved tracks in localStorage
      }

      // Trigger SavedTracksScreen refresh
      try {
        window.dispatchEvent(new CustomEvent('track-saved', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-saved-timestamp', Date.now().toString())
      } catch (error) {
        // Event dispatch failed, not critical
      }
    } catch (error: any) {
      savedTracks.value.delete(trackKey)
    }
  }
}

// Blacklist track function
const blacklistTrack = async (track: SoundCloudTrack) => {
  const trackKey = getTrackKey(track)
  const trackIdentifier = track.id || trackKey

  if (isTrackBlacklisted(track)) {
    // UNBAN TRACK - Update UI immediately
    blacklistedTracks.value.delete(trackKey)

    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const deleteData = {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (!response.success) {
        blacklistedTracks.value.add(trackKey)
      } else {
        window.dispatchEvent(new CustomEvent('track-unblacklisted', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      }
    } catch (error: any) {
      blacklistedTracks.value.add(trackKey)
    }
  } else {
    // Block track - show processing state
    processingTrack.value = track.id

    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/blacklist-track', {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)
        addTrackToBlacklist({
          id: String(track.id),
          name: track.title,
          artist: track.user?.username || 'Unknown',
        } as any)

        window.dispatchEvent(new CustomEvent('track-blacklisted', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    } catch (error: any) {
      // Failed to blacklist track
    } finally {
      processingTrack.value = null
    }
  }
}

// Mark track as listened
const markTrackAsListened = async (track: SoundCloudTrack) => {
  const trackKey = getTrackKey(track)

  // Mark as listened (optimistic)
  listenedTracks.value.add(trackKey)
  // Reassign to trigger Vue reactivity for Set mutations
  listenedTracks.value = new Set(listenedTracks.value)

  // Persist listened state
  try {
    await http.post('music-preferences/listened-track', {
      track_key: trackKey,
      track_name: track.title,
      artist_name: track.user?.username || 'Unknown',
      spotify_id: null,
      isrc: `soundcloud:${track.id}`,
    })
  } catch (e) {
    // If unauthenticated, store locally
    try {
      const keys = Array.from(listenedTracks.value)
      localStorage.setItem('koel-listened-tracks', JSON.stringify(keys))
    } catch {}
  }

  if (banListenedTracks.value) {
    autoBlacklistListenedTrack(track)
  }
}

// Auto-blacklist a track when "Ban listened tracks" is enabled
const autoBlacklistListenedTrack = async (track: SoundCloudTrack) => {
  const trackKey = getTrackKey(track)
  const identifier = `soundcloud:${track.id}` || trackKey

  if (blacklistedTracks.value.has(trackKey) || pendingAutoBannedTracks.value.has(identifier)) {
    return
  }

  pendingAutoBannedTracks.value.add(identifier)
  pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)

  try {
    const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

    const response = await http.post('music-preferences/blacklist-track', {
      spotify_id: null,
      isrc: isrcValue,
      track_name: track.title,
      artist_name: track.user?.username || 'Unknown',
    })

    if (response.success) {
      blacklistedTracks.value.add(trackKey)
      addTrackToBlacklist({
        id: String(track.id),
        name: track.title,
        artist: track.user?.username || 'Unknown',
      } as any)

      window.dispatchEvent(new CustomEvent('track-blacklisted', {
        detail: { track, trackKey },
      }))
      localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
    }
  } catch (error) {
    console.warn('Failed to auto-ban listened track:', error)
  } finally {
    pendingAutoBannedTracks.value.delete(identifier)
    pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)
  }
}

// Load user preferences
const loadUserPreferences = async () => {
  try {
    // Load blacklisted tracks
    const blacklistedTracksResponse = await http.get('music-preferences/blacklisted-tracks')
    if (blacklistedTracksResponse.success && blacklistedTracksResponse.data) {
      blacklistedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        blacklistedTracks.value.add(trackKey)
      })
    }

    // Load saved tracks
    const savedTracksResponse = await http.get('music-preferences/saved-tracks')
    if (savedTracksResponse.success && savedTracksResponse.data) {
      savedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        savedTracks.value.add(trackKey)
      })
    }

    // Load client unsaved tracks from localStorage
    try {
      const stored = localStorage.getItem('koel-client-unsaved-tracks')
      if (stored) {
        const unsavedList = JSON.parse(stored)
        clientUnsavedTracks.value = new Set(unsavedList)
      }
    } catch (error) {
      // Failed to load client unsaved tracks
    }

    // Load listened tracks from server (fall back to localStorage if unauthenticated)
    try {
      const resp: any = await http.get('music-preferences/listened-tracks')
      if (resp?.success && Array.isArray(resp.data)) {
        listenedTracks.value = new Set(resp.data as string[])
      }
    } catch (e) {
      // Fallback to localStorage per device
      try {
        const stored = localStorage.getItem('koel-listened-tracks')
        if (stored) {
          const keys: string[] = JSON.parse(stored)
          listenedTracks.value = new Set(keys)
        }
      } catch {}
    }
  } catch (error) {
    // Could not load user preferences (user may not be logged in)
  }
}

const clearSeedTrack = () => {
  // Clear search suggestion timer
  if (searchSuggestionTimer) {
    clearTimeout(searchSuggestionTimer)
    searchSuggestionTimer = null
  }

  seedTrack.value = null
  tracks.value = []
  allTracks.value = []
  seedSearchQuery.value = ''
  seedSearchResults.value = []
  showingSeedResults.value = false
  showDropdown.value = false
  showLikesRatioDropdown.value = false
  error.value = ''
  currentData.value = null
  displayLimitRelated.value = 20
  displayLimitSeed.value = 20
  dropdownDisplayLimit.value = 10
  loadingMoreRelated.value = false
  loadingMoreSeed.value = false
  seedHasMore.value = false
  seedLastQuery.value = ''
  likesRatioFilter.value = 'newest'
}

const clearResults = () => {
  // Clear search suggestion timer
  if (searchSuggestionTimer) {
    clearTimeout(searchSuggestionTimer)
    searchSuggestionTimer = null
  }

  tracks.value = []
  allTracks.value = []
  seedTrack.value = null
  searchQuery.value = ''
  seedSearchQuery.value = ''
  seedSearchResults.value = []
  showingSeedResults.value = false
  showDropdown.value = false
  showLikesRatioDropdown.value = false
  error.value = ''
  currentData.value = null
  displayLimitRelated.value = 20
  displayLimitSeed.value = 20
  dropdownDisplayLimit.value = 10
  loadingMoreRelated.value = false
  loadingMoreSeed.value = false
  seedHasMore.value = false
  seedLastQuery.value = ''
  likesRatioFilter.value = 'newest'
}

const loadMoreRelatedTracks = () => {
  if (loadingMoreRelated.value || !hasMoreRelatedTracks.value) {
    return
  }
  loadingMoreRelated.value = true
  displayLimitRelated.value = Math.min(displayLimitRelated.value + 20, tracks.value.length)
  setTimeout(() => {
    loadingMoreRelated.value = false
  }, 50)
}

const loadMoreSeedTracks = () => {
  if (loadingMoreSeed.value || !hasMoreSeedTracks.value) {
    return
  }
  loadingMoreSeed.value = true

  // If we still have local results hidden, just reveal them
  if (displayLimitSeed.value < seedSearchResults.value.length) {
    displayLimitSeed.value = Math.min(displayLimitSeed.value + 20, seedSearchResults.value.length)
    setTimeout(() => {
      loadingMoreSeed.value = false
    }, 50)
    return
  }

  // Otherwise fetch the next batch from the API
  if (!seedHasMore.value || !seedLastQuery.value) {
    loadingMoreSeed.value = false
    return
  }

  const offset = seedSearchResults.value.length
  const pageLimit = 200

  soundcloudService.searchWithPagination({
    searchQuery: seedLastQuery.value,
    limit: pageLimit,
    offset,
  })
    .then(response => {
      // Apply global blacklist filtering (tracks + artists)
      const globalFiltered = filterSoundCloudTracks(response.tracks || [])

      // Apply local banned artists filter
      const bannedArtistsFiltered = filterBannedArtists(globalFiltered)

      // Apply local blacklisted tracks filtering
      const blacklistedFiltered = filterBlacklistedTracks(bannedArtistsFiltered)

      const unique = dedupeSeedTracks(blacklistedFiltered)
      seedSearchResults.value = [...seedSearchResults.value, ...unique]
      seedHasMore.value = response.hasMore || (response.tracks?.length || 0) >= pageLimit
      displayLimitSeed.value = Math.min(displayLimitSeed.value + 20, seedSearchResults.value.length)
    })
    .catch(err => {
      console.error('🎵 Seed load more error:', err)
      error.value = 'Failed to load more seed tracks. Please try again.'
    })
    .finally(() => {
      loadingMoreSeed.value = false
    })
}

const performSearch = () => {
  if (searchQuery.value.trim()) {
    searchTracks(searchQuery.value.trim())
  }
}

const performSeedSearch = () => {
  // Clear automatic search timer when manually searching
  if (searchSuggestionTimer) {
    clearTimeout(searchSuggestionTimer)
    searchSuggestionTimer = null
  }

  if (seedSearchQuery.value.trim()) {
    searchSeedTracks(seedSearchQuery.value.trim())
  }
}

const onSearchInput = debounce(() => {
  // Auto-search as user types (optional - can be removed if not desired)
  // performSearch()
}, 500)

const onSeedSearchInput = () => {
  // Clear previous timer
  if (searchSuggestionTimer) {
    clearTimeout(searchSuggestionTimer)
    searchSuggestionTimer = null
  }

  const currentQuery = seedSearchQuery.value.trim()

  // Clear old results if query is empty
  if (!currentQuery) {
    seedSearchResults.value = []
    showDropdown.value = false
    seedLastQuery.value = ''
    return
  }

  // If query changed from the last searched query, clear old results
  // This prevents showing stale suggestions when user deletes and types new text
  if (seedLastQuery.value && currentQuery !== seedLastQuery.value.trim()) {
    seedSearchResults.value = []
    showDropdown.value = false
  } else if (seedSearchResults.value.length > 0 && currentQuery === seedLastQuery.value.trim()) {
    // Only show dropdown if we have results AND they match the current query exactly
    showDropdown.value = true
  } else {
    showDropdown.value = false
  }

  // Schedule automatic search after user pauses typing for 2 seconds
  searchSuggestionTimer = setTimeout(() => {
    if (seedSearchQuery.value.trim()) {
      searchSeedTracks(seedSearchQuery.value.trim())
    }
  }, 2000)
}

const onSeedSearchFocus = () => {
  // Show dropdown on focus if we have results
  if (seedSearchResults.value.length > 0 && seedSearchQuery.value.trim()) {
    showDropdown.value = true
  }
}

// Load more results in the dropdown
const loadMoreDropdownResults = () => {
  dropdownDisplayLimit.value = Math.min(dropdownDisplayLimit.value + 10, seedSearchResults.value.length)
}

// Load banned artists from localStorage
const loadBannedArtists = () => {
  try {
    const savedBanned = localStorage.getItem('koel-banned-artists')
    if (savedBanned) {
      const bannedArray = JSON.parse(savedBanned)
      // For SoundCloud tracks, we'll use artist names instead of MBIDs
      bannedArtists.value = new Set(bannedArray)
    }
  } catch (error) {
    console.error('Failed to load banned artists:', error)
  }
}
// Filter function to remove banned artists from tracks
const filterBannedArtists = (trackList: SoundCloudTrack[]): SoundCloudTrack[] => {
  return trackList.filter(track => {
    const artistName = track.user?.username
    return artistName && !bannedArtists.value.has(artistName)
  })
}

// Filter function to remove blacklisted tracks
const filterBlacklistedTracks = (trackList: SoundCloudTrack[]): SoundCloudTrack[] => {
  return trackList.filter(track => {
    const trackKey = getTrackKey(track)
    return !blacklistedTracks.value.has(trackKey)
  })
}

// Click outside handler to close dropdown
const handleClickOutside = (event: MouseEvent) => {
  if (searchContainer.value && !searchContainer.value.contains(event.target as Node)) {
    // Only close the dropdown, not the entire results display
    if (showDropdown.value) {
      showDropdown.value = false
    }
  }
}

// Sort dropdown functions
const toggleLikesRatioDropdown = () => {
  showLikesRatioDropdown.value = !showLikesRatioDropdown.value
}

const hideLikesRatioDropdown = () => {
  setTimeout(() => {
    showLikesRatioDropdown.value = false
  }, 150) // Small delay to allow click events to register
}

const setLikesRatioFilter = (type: 'none' | 'newest') => {
  likesRatioFilter.value = type
  showLikesRatioDropdown.value = false
  applyFiltering()
}

const getSortText = () => {
  switch (likesRatioFilter.value) {
    case 'newest': return 'Most Recent'
    default: return 'Most Streams'
  }
}

// Apply filtering and sorting
const applyFiltering = () => {
  const filteredTracks = [...allTracks.value]

  // Apply sorting
  if (likesRatioFilter.value === 'newest') {
    // Sort by creation date newest to oldest
    filteredTracks.sort((a, b) => {
      const dateA = new Date(a.created_at || 0).getTime()
      const dateB = new Date(b.created_at || 0).getTime()
      return dateB - dateA
    })
  }
  // 'none' keeps the default order by plays (already sorted by SoundCloud API)

  tracks.value = filteredTracks
  displayLimitRelated.value = Math.min(displayLimitRelated.value, tracks.value.length || 0) || 0
}

// Watch seedTrack changes for debugging
watch(seedTrack, (newValue, oldValue) => {
  console.log('🎵 [WATCH] seedTrack changed:', {
    old: oldValue,
    new: newValue,
    isTruthy: !!newValue,
  })
}, { immediate: true })

// Auto-ban already listened tracks when toggle is turned on
watch(banListenedTracks, async (newValue, oldValue) => {
  if (newValue && !oldValue) {
    // Get tracks from either displayed tracks (related) or displayed seed tracks (seed search)
    const currentTracks = showingSeedResults.value ? displayedSeedTracks.value : displayedTracks.value
    const targets = currentTracks.filter(track => {
      const trackKey = getTrackKey(track)
      return listenedTracks.value.has(trackKey) && !blacklistedTracks.value.has(trackKey)
    })

    for (const track of targets) {
      await autoBlacklistListenedTrack(track)
    }
  }
})

onMounted(() => {
  console.log('🎵 [MOUNT] Component mounted, seedTrack initial value:', seedTrack.value)

  // Listen for related tracks data from other screens
  eventBus.on('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)

  // Listen for skip events from the SoundCloud player
  eventBus.on('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.on('SOUNDCLOUD_SKIP_NEXT', skipToNext)

  // Load local banned artists
  loadBannedArtists()

  // Load global blacklisted items
  loadBlacklistedItems()

  // Load user preferences
  loadUserPreferences()

  // Add click outside listener
  document.addEventListener('click', handleClickOutside)
})

// Close SoundCloud player when navigating away from this screen
onRouteChanged(route => {
  if (route.screen !== 'SoundCloudRelatedTracks') {
    // Close any inline players first
    if (seedTracksTable.value?.closeInlinePlayer) {
      seedTracksTable.value.closeInlinePlayer()
    }
    if (relatedTracksTable.value?.closeInlinePlayer) {
      relatedTracksTable.value.closeInlinePlayer()
    }
    // Then hide the global player
    soundcloudPlayerStore.hide()
  }
})

onUnmounted(() => {
  eventBus.off('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)
  eventBus.off('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.off('SOUNDCLOUD_SKIP_NEXT', skipToNext)

  // Clear search suggestion timer
  if (searchSuggestionTimer) {
    clearTimeout(searchSuggestionTimer)
    searchSuggestionTimer = null
  }

  // Remove click outside listener
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.seed-selection {
  max-width: 100%;
}

/* Hide placeholders on focus */
input:focus::placeholder {
  opacity: 0;
}

/* Center placeholder text in search input */
.search-input::placeholder {
  text-align: center;
}
</style>
