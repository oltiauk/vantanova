<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader 
        layout="collapsed"
        header-image="/HeadersSVG/Soundcloud-RelatedTracks-header.svg"
      >
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
                <!-- Search Icon -->
                <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none z-20 pl-4">
                  <Icon :icon="faSearch" class="w-5 h-5 text-white/40" />
                </div>

                <input
                  v-model="seedSearchQuery"
                  type="text"
                  class="w-full py-3 pl-12 pr-24 bg-white/10 rounded-lg focus:outline-none text-white text-lg"
                  placeholder="Enter track name or artist to find seed track..."
                  @input="onSeedSearchInput"
                  @focus="onSeedSearchFocus"
                  @keypress.enter.prevent="performSeedSearch"
                >

                <!-- Search Button -->
                <button
                  :disabled="!seedSearchQuery.trim() || loading"
                  class="absolute inset-y-0 right-0 flex items-center px-4 bg-k-accent hover:bg-k-accent/80 disabled:opacity-50 disabled:cursor-not-allowed rounded-r-lg text-white font-medium transition-colors"
                  @click="performSeedSearch"
                >
                  <Icon v-if="loading && showingSeedResults" :icon="faSpinner" spin class="w-4 h-4" />
                  <span v-else class="text-sm">Search</span>
                </button>

                <!-- Loading Animation -->
                <div
                  v-if="loading && seedSearchQuery.trim() && showingSeedResults"
                  class="absolute z-50 w-full bg-k-bg-secondary border border-k-border rounded-lg mt-1 shadow-xl"
                >
                  <div class="flex items-center justify-center py-8">
                    <div class="flex items-center gap-3">
                      <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
                      <span class="text-k-text-secondary">Searching for tracks...</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <p class="text-xs text-k-text-secondary text-center mb-4">
          {{ showingSeedResults ? 'Search for different seed track or select one from the table below' : 'First, search for a track to use as your seed. Then we\'ll find related tracks.' }}
        </p>
      </div>

      <!-- Selected Seed Track Display - Compact -->
      <div v-if="seedTrack" class="selected-seed mb-4 relative z-20">
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
        <div class="flex items-center justify-between mb-6">
          <div class="text-k-text-secondary">
            Found {{ totalSeedTracks }} seed tracks (showing {{ displayedSeedTracks.length }})
          </div>
        </div>

        <SoundCloudTrackTable
          ref="seedTracksTable"
          :tracks="displayedSeedTracks"
          :start-index="(currentSeedPage - 1) * tracksPerPage"
          :allow-animations="allowAnimations"
          @play="playTrack"
          @related-tracks="selectSeedTrack"
        />

        <!-- Pagination Controls for Seed Search -->
        <div v-if="totalSeedPages > 1" class="pagination-section w-full mt-8">
          <div class="flex items-center justify-between">
            <button
              :disabled="currentSeedPage === 1"
              class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :class="{ 'opacity-50 cursor-not-allowed': currentSeedPage === 1 }"
              @click="goToSeedPage(currentSeedPage - 1)"
            >
              Previous
            </button>

            <div class="flex items-center gap-1">
              <button
                v-for="page in visibleSeedPages"
                :key="page"
                :class="page === currentSeedPage ? 'bg-k-accent text-white' : 'bg-k-bg-primary text-gray-300 hover:bg-white/10'"
                class="w-10 h-10 flex items-center justify-center rounded transition-colors"
                @click="goToSeedPage(page)"
              >
                {{ page }}
              </button>
            </div>

            <button
              :disabled="currentSeedPage === totalSeedPages"
              class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :class="{ 'opacity-50 cursor-not-allowed': currentSeedPage === totalSeedPages }"
              @click="goToSeedPage(currentSeedPage + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- Related Tracks Results -->
      <div v-if="!showingSeedResults && tracks.length > 0">
        <div class="flex items-center justify-between mb-6">
          <div class="text-k-text-secondary">
            Found {{ totalTracks }} related tracks (showing {{ displayedTracks.length }})
          </div>
          <button
            class="px-4 py-2 bg-k-bg-secondary hover:bg-k-bg-secondary/80 rounded text-sm transition-colors"
            @click="clearResults"
          >
            New Search
          </button>
        </div>

        <!-- Sort by Dropdown -->
        <div class="flex justify-end mb-4 relative">
          <button
            class="px-4 py-2 rounded-lg font-medium transition flex items-center gap-2 bg-white/10 text-white/80 hover:bg-white/20"
            style="background-color: rgba(47, 47, 47, 255) !important;"
            @click="toggleLikesRatioDropdown"
            @blur="hideLikesRatioDropdown"
          >
            <Icon :icon="getSortIcon()" />
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
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2 rounded-t-lg"
              :class="likesRatioFilter === 'none' ? 'background-color: rgb(67,67,67,255)' : ''"
              :style="likesRatioFilter === 'none' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('none')"
            >
              <Icon :icon="faFilter" />
              Default (by Plays)
            </button>
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
              :class="likesRatioFilter === 'highest' ? 'background-color: rgb(67,67,67,255)' : ''"
              :style="likesRatioFilter === 'highest' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('highest')"
            >
              <Icon :icon="faArrowUp" />
              Highest Likes Ratio
            </button>
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2 rounded-b-lg"
              :class="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
              :style="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('newest')"
            >
              <Icon :icon="faClock" />
              Newest Releases
            </button>
          </div>
        </div>

        <SoundCloudTrackTable
          ref="relatedTracksTable"
          :tracks="displayedTracks"
          :start-index="(currentPage - 1) * tracksPerPage"
          :allow-animations="allowAnimations"
          @play="playTrack"
          @pause="pauseTrack"
          @seek="seekTrack"
          @related-tracks="findRelatedForTrack"
        />

        <!-- Pagination Controls -->
        <div v-if="totalPages > 1" class="pagination-section w-full mt-8">
          <div class="flex items-center justify-between">
            <button
              :disabled="currentPage === 1"
              class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
              @click="goToPage(currentPage - 1)"
            >
              Previous
            </button>

            <div class="flex items-center gap-1">
              <button
                v-for="page in visiblePages"
                :key="page"
                :class="page === currentPage ? 'bg-k-accent text-white' : 'bg-k-bg-primary text-gray-300 hover:bg-white/10'"
                class="w-10 h-10 flex items-center justify-center rounded transition-colors"
                @click="goToPage(page)"
              >
                {{ page }}
              </button>
            </div>

            <button
              :disabled="currentPage === totalPages"
              class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages }"
              @click="goToPage(currentPage + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>

      <!-- Loading Animation for related tracks -->
      <div v-if="!showingSeedResults && loading && tracks.length === 0" class="bg-white/5 rounded-lg p-6">
        <div class="flex items-center justify-center py-8">
          <div class="flex items-center gap-3">
            <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
            <span class="text-k-text-secondary">Loading related tracks...</span>
          </div>
        </div>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faArrowUp, faCheck, faChevronDown, faClock, faFilter, faMusic, faSearch, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { debounce } from 'lodash'
import { eventBus } from '@/utils/eventBus'
import { soundcloudService, type SoundCloudTrack } from '@/services/soundcloudService'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { useRouter } from '@/composables/useRouter'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import SoundCloudTrackTable from '@/components/ui/soundcloud/SoundCloudTrackTable.vue'

const { isCurrentScreen, onRouteChanged } = useRouter()

// Animation state management
const allowAnimations = ref(false)
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
const searchContainer = ref<HTMLElement | null>(null)

// Pagination state
const currentPage = ref(1)
const currentSeedPage = ref(1)
const tracksPerPage = 20

// Sort filter state
const likesRatioFilter = ref<'none' | 'highest' | 'newest'>('none')
const showLikesRatioDropdown = ref(false)

// Initialize global blacklist filtering for SoundCloud
const {
  filterSoundCloudTracks,
  loadBlacklistedItems,
} = useBlacklistFiltering()

// Local banned artists tracking (for Similar Artists compatibility)
const bannedArtists = ref(new Set<string>()) // Store artist names for SoundCloud tracks
const allTracks = ref<SoundCloudTrack[]>([]) // Store all tracks for sorting

// Pagination computed properties
const totalTracks = computed(() => tracks.value.length)
const totalPages = computed(() => Math.ceil(totalTracks.value / tracksPerPage))

const displayedTracks = computed(() => {
  const start = (currentPage.value - 1) * tracksPerPage
  const end = start + tracksPerPage
  return tracks.value.slice(start, end)
})

const visiblePages = computed(() => {
  const pages = []
  const maxVisible = 5
  const total = totalPages.value

  if (total <= maxVisible) {
    // Show all pages if total is small
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Show pages around current page
    const current = currentPage.value
    let start = Math.max(1, current - 2)
    let end = Math.min(total, current + 2)

    // Adjust if we're near the beginning or end
    if (current <= 3) {
      end = Math.min(total, 5)
    } else if (current >= total - 2) {
      start = Math.max(1, total - 4)
    }

    for (let i = start; i <= end; i++) {
      pages.push(i)
    }
  }

  return pages
})

// Seed search pagination computed properties
const totalSeedTracks = computed(() => seedSearchResults.value.length)
const totalSeedPages = computed(() => Math.ceil(totalSeedTracks.value / tracksPerPage))

const displayedSeedTracks = computed(() => {
  const start = (currentSeedPage.value - 1) * tracksPerPage
  const end = start + tracksPerPage
  return seedSearchResults.value.slice(start, end)
})

const visibleSeedPages = computed(() => {
  const pages = []
  const maxVisible = 5
  const total = totalSeedPages.value

  if (total <= maxVisible) {
    // Show all pages if total is small
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Show pages around current page
    const current = currentSeedPage.value
    let start = Math.max(1, current - 2)
    let end = Math.min(total, current + 2)

    // Adjust if we're near the beginning or end
    if (current <= 3) {
      end = Math.min(total, 5)
    } else if (current >= total - 2) {
      start = Math.max(1, total - 4)
    }

    for (let i = start; i <= end; i++) {
      pages.push(i)
    }
  }

  return pages
})

const loadRelatedTracks = async (trackUrn: string) => {
  loading.value = true
  error.value = ''

  try {
    console.log('🎵 Loading related tracks for URN:', trackUrn)
    const relatedTracks = await soundcloudService.getRelatedTracks(trackUrn)

    // Apply global blacklist filtering (tracks + artists)
    const globalFiltered = filterSoundCloudTracks(relatedTracks)

    // Apply local banned artists filtering (for Similar Artists compatibility)
    const localFiltered = filterBannedArtists(globalFiltered)

    allTracks.value = localFiltered // Store all tracks for sorting
    applyFiltering() // Apply current sort filter
    currentPage.value = 1 // Reset to first page when loading new tracks
    console.log('🎵 Loaded', tracks.value.length, 'related tracks (after filtering blacklisted tracks/artists)')
    console.log(`🚫 Filtered out ${relatedTracks.length - tracks.value.length} blacklisted items`)

    // Trigger animations for new related tracks
    setTimeout(() => {
      allowAnimations.value = true
      initialLoadComplete.value = true
      
      // Auto-disable animations after 2 seconds
      setTimeout(() => {
        allowAnimations.value = false
      }, 2000)
    }, 50)
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
  showingSeedResults.value = true
  showDropdown.value = true
  currentSeedPage.value = 1 // Reset to first page for new search

  try {
    console.log('🎵 Searching for seed tracks:', query)
    const searchResults = await soundcloudService.search({
      searchQuery: query,
      limit: 50,
    })

    // For NEW seed track searches: filter out banned artists from new results
    // Apply local banned artists filter (for Similar Artists compatibility)
    const filteredResults = filterBannedArtists(searchResults)

    seedSearchResults.value = filteredResults
    console.log('🎵 Found', seedSearchResults.value.length, 'seed track candidates (after filtering)')

    // Trigger animations for seed search results
    setTimeout(() => {
      allowAnimations.value = true
      initialLoadComplete.value = true
      
      // Auto-disable animations after 2 seconds
      setTimeout(() => {
        allowAnimations.value = false
      }, 2000)
    }, 50)
  } catch (err: any) {
    error.value = `Failed to search seed tracks: ${err.message || 'Unknown error'}`
    console.error('🎵 Seed search error:', err)
  } finally {
    loading.value = false
  }
}

const selectSeedTrack = async (track: SoundCloudTrack) => {
  console.log('🎵 Selected seed track:', track.title, 'by', track.user.username)

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

  // Load related tracks for this seed
  await loadRelatedTracks(`soundcloud:tracks:${track.id}`)
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

    // Navigate to the page containing the previous track
    if (!showingSeedResults.value) {
      const targetPage = Math.floor((currentIndex - 1) / tracksPerPage) + 1
      if (targetPage !== currentPage.value) {
        currentPage.value = targetPage
      }
    }

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

    // Navigate to the page containing the next track
    if (!showingSeedResults.value) {
      const targetPage = Math.floor((currentIndex + 1) / tracksPerPage) + 1
      if (targetPage !== currentPage.value) {
        currentPage.value = targetPage
      }
    }

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

const clearSeedTrack = () => {
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
  currentPage.value = 1
  currentSeedPage.value = 1
  likesRatioFilter.value = 'none'
}

const clearResults = () => {
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
  currentPage.value = 1
  currentSeedPage.value = 1
  likesRatioFilter.value = 'none'
}

// Pagination functions
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page

    // Update navigation for currently playing track if it exists
    const currentTrack = soundcloudPlayerStore.state.currentTrack
    if (currentTrack) {
      updateNavigationState(currentTrack)
    }
  }
}

const goToSeedPage = (page: number) => {
  if (page >= 1 && page <= totalSeedPages.value) {
    currentSeedPage.value = page

    // Update navigation for currently playing track if it exists
    const currentTrack = soundcloudPlayerStore.state.currentTrack
    if (currentTrack) {
      updateNavigationState(currentTrack)
    }
  }
}

const performSearch = () => {
  if (searchQuery.value.trim()) {
    searchTracks(searchQuery.value.trim())
  }
}

const performSeedSearch = () => {
  if (seedSearchQuery.value.trim()) {
    searchSeedTracks(seedSearchQuery.value.trim())
  }
}

const onSearchInput = debounce(() => {
  // Auto-search as user types (optional - can be removed if not desired)
  // performSearch()
}, 500)

const onSeedSearchInput = () => {
  // Show dropdown if we have results and user is typing
  if (seedSearchResults.value.length > 0 && seedSearchQuery.value.trim()) {
    showDropdown.value = true
  } else {
    showDropdown.value = false
  }
}

const onSeedSearchFocus = () => {
  // Show dropdown on focus if we have results
  if (seedSearchResults.value.length > 0 && seedSearchQuery.value.trim()) {
    showDropdown.value = true
  }
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

const setLikesRatioFilter = (type: 'none' | 'highest' | 'newest') => {
  likesRatioFilter.value = type
  showLikesRatioDropdown.value = false
  applyFiltering()
}

const getSortIcon = () => {
  switch (likesRatioFilter.value) {
    case 'highest': return faArrowUp
    case 'newest': return faClock
    default: return faFilter
  }
}

const getSortText = () => {
  switch (likesRatioFilter.value) {
    case 'highest': return 'Highest Likes Ratio'
    case 'newest': return 'Newest Releases'
    default: return 'Sort by: Plays'
  }
}

// Apply filtering and sorting
const applyFiltering = () => {
  const filteredTracks = [...allTracks.value]

  // Apply sorting
  if (likesRatioFilter.value === 'highest') {
    // Sort by likes ratio highest to lowest
    filteredTracks.sort((a, b) => {
      const ratioA = (a.playback_count || 0) > 0 ? (a.favoritings_count || 0) / (a.playback_count || 0) : 0
      const ratioB = (b.playback_count || 0) > 0 ? (b.favoritings_count || 0) / (b.playback_count || 0) : 0
      return ratioB - ratioA
    })
  } else if (likesRatioFilter.value === 'newest') {
    // Sort by creation date newest to oldest
    filteredTracks.sort((a, b) => {
      const dateA = new Date(a.created_at || 0).getTime()
      const dateB = new Date(b.created_at || 0).getTime()
      return dateB - dateA
    })
  }
  // 'none' keeps the default order by plays (already sorted by SoundCloud API)

  tracks.value = filteredTracks
}

onMounted(() => {
  // Listen for related tracks data from other screens
  eventBus.on('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)

  // Listen for skip events from the SoundCloud player
  eventBus.on('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.on('SOUNDCLOUD_SKIP_NEXT', skipToNext)

  // Load local banned artists
  loadBannedArtists()

  // Load global blacklisted items
  loadBlacklistedItems()

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
  } else {
    // Enable animations when entering SoundCloud Related Tracks screen
    if (tracks.value.length > 0 || seedSearchResults.value.length > 0) {
      allowAnimations.value = true
      initialLoadComplete.value = false
      
      // Disable animations after they complete
      setTimeout(() => {
        allowAnimations.value = false
        initialLoadComplete.value = true
      }, 2000)
    }
  }
})

onUnmounted(() => {
  eventBus.off('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)
  eventBus.off('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.off('SOUNDCLOUD_SKIP_NEXT', skipToNext)

  // Remove click outside listener
  document.removeEventListener('click', handleClickOutside)
})
</script>
