<template>
  <div class="seed-selection mb-8">
    <!-- Search Container -->
    <div class="search-container mb-6">
      <div class="rounded-lg p-4">
        <div class="max-w-4xl mx-auto">
          <div ref="searchContainer" class="relative">
            <!-- Search Input -->
            <div class="relative">
              <div class="flex">
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search for a Seed Track"
                  class="flex-1 py-3 pl-4 pr-4 bg-white/10 rounded-l-lg border-0 focus:outline-none text-white text-lg search-input"
                  @input="onSearchInput"
                  @keydown.enter="performSearch"
                >
                <button
                  class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 text-white rounded-r-lg transition-colors flex items-center justify-center"
                  :disabled="!searchQuery.trim() || isSearching"
                  @click="performSearch"
                >
                  <Icon :icon="faSearch" class="w-5 h-5" />
                </button>
              </div>

              <!-- Loading Animation for Search Suggestions -->
              <div
                v-if="isSearching && searchQuery.trim()"
                class="absolute z-50 w-full border border-k-border rounded-lg mt-1 shadow-xl"
                style="background-color: #302f30; top: 100%;"
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
                v-if="searchResults.length > 0 && !isSearching"
                class="absolute z-50 w-full border border-k-border rounded-lg mt-1 shadow-xl"
                style="background-color: #302f30; top: 100%;"
              >
                <div class="max-h-80 rounded-lg overflow-hidden overflow-y-auto">
                  <div v-for="track in filteredSearchResults.slice(0, 10)" :key="`suggestion-${track.id}`">
                    <div
                      class="flex items-center justify-between px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors group border-b border-k-border/30 last:border-b-0"
                      :class="{
                        'bg-k-accent/10': pendingTrack && pendingTrack.id === track.id,
                      }"
                      @click="selectSeedTrack(track)"
                    >
                      <!-- Track Info -->
                      <div class="flex-1 min-w-0">
                        <div class="font-medium text-k-text-primary group-hover:text-gray-200 transition-colors truncate">
                          {{ formatArtists(track) }} - {{ track.name }}
                        </div>
                      </div>

                      <!-- Duration Badge -->
                      <div class="bg-k-bg-primary/30 px-2 py-1 rounded text-k-text-tertiary text-xs font-mono ml-3 flex-shrink-0">
                        {{ formatDuration(track.duration_ms) }}
                      </div>
                    </div>
                  </div>

                  <div v-if="filteredSearchResults.length > 10" class="px-4 py-3 text-center text-k-text-tertiary text-sm border-t border-k-border bg-k-bg-tertiary/20">
                    <Icon :icon="faMusic" class="mr-1 opacity-50" />
                    {{ filteredSearchResults.length - 10 }} more tracks found
                  </div>
                </div>
              </div>
            </div>

            <!-- Search Button - Show only for manual text search (not for track selection) -->
            <div v-if="!hasRecommendations && searchQuery.trim() && !selectedTrack && !pendingTrack" class="flex justify-center mt-6">
              <button
                :disabled="isSearching"
                class="px-6 py-2 bg-k-accent text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-500 transition-colors flex items-center gap-2"
                @click="performSearch"
              >
                <span>Search</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Selected Seed Track Display - Compact -->
    <div v-if="selectedTrack" class="selected-seed mb-4 relative z-20">
      <div class="max-w-4xl mx-auto">
        <div class="text-sm font-medium mb-2">Seed Track:</div>
        <div class="bg-k-bg-secondary/50 border border-k-border rounded-lg px-3 py-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 flex-1 min-w-0">
              <Icon :icon="faCheck" class="w-4 h-4 text-k-accent flex-shrink-0" />
              <span class="text-k-text-primary font-medium truncate">{{ formatArtists(selectedTrack) }} - {{ selectedTrack.name }}</span>
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

    <!-- Error State -->
    <div v-if="searchError" class="bg-red-500/20 border border-red-500/40 rounded-lg p-4 max-w-2xl mx-auto">
      <div class="flex items-start gap-3">
        <Icon :icon="faExclamationTriangle" class="text-red-400 mt-0.5" />
        <div>
          <h4 class="font-medium text-red-200 mb-1">Search Error</h4>
          <p class="text-red-200">{{ searchError }}</p>
        </div>
        <button
          class="ml-auto text-red-400 hover:text-red-300"
          @click="searchError = ''"
        >
          <Icon :icon="faTimes" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Instructions -->
    <!-- <div v-if="!selectedTrack && searchResults.length === 0 && !searchQuery.trim() && !isSearching" class="text-center py-12">
      <h3 class="text-lg font-medium text-white mb-4">Find Your Seed Track</h3>
      <p class="text-white/60 text-md mb-6">
        Search for a song to use as the starting point for music discovery
      </p>
      <p class="text-white/40 text-sm">
        The algorithm will find similar tracks based on your selected parameters
      </p>
    </div> -->
  </div>
</template>

<script setup lang="ts">
import { faBan, faCheck, faExclamationTriangle, faHeart, faMusic, faPlay, faRandom, faSearch, faTimes, faUserMinus, faUserPlus } from '@fortawesome/free-solid-svg-icons'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { http } from '@/services/http'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'

interface Track {
  id: string
  name: string
  artist: string
  album: string
  duration_ms?: number
  external_url?: string
  preview_url?: string
  image?: string
  uri?: string
  artists?: Array<{
    id: string
    name: string
  }>
}

// Props
interface Props {
  selectedTrack?: Track | null
  hasRecommendations?: boolean
  hasMoreInQueue?: boolean
  queueKey?: string | null
  currentBatchHasBannedItems?: boolean
  emptySlotCount?: number
  queueExhausted?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  selectedTrack: null,
  hasRecommendations: false,
  hasMoreInQueue: false,
  queueKey: null,
  currentBatchHasBannedItems: false,
  emptySlotCount: 0,
  queueExhausted: false,
})

// Emits
const emit = defineEmits<{
  'update:selectedTrack': [track: Track | null]
  'track-selected': [track: Track]
  'related-tracks': [track: Track, isRefresh?: boolean]
  'search-results-changed': [hasResults: boolean]
  'clear-recommendations': []
  'current-batch-banned-item': []
}>()

// State
const searchQuery = ref('')
const searchResults = ref<Track[]>([])
const searchError = ref('')
const currentPage = ref(1)
const isSearching = ref(false)
const searchContainer = ref<HTMLElement | null>(null)
const pendingTrack = ref<Track | null>(null) // Track selected from dropdown, pending search button click

// Queue state management
const hasMoreInQueue = ref(false) // Track if more results available
const currentQueueKey = ref<string | null>(null) // Session key for queue

// Music preferences state
const savedTracks = ref<Set<string>>(new Set())
const blacklistedTracks = ref<Set<string>>(new Set())
const savedArtists = ref<Set<string>>(new Set())
const blacklistedArtists = ref<Set<string>>(new Set())
const processingTrack = ref<string | null>(null)

// Initialize blacklist filtering composable
const {
  isTrackBlacklisted,
  isArtistBlacklisted,
  isTrackOrArtistBlacklisted,
  loadBlacklistedItems,
} = useBlacklistFiltering()

// Computed - For seed track selection, filter out blacklisted TRACKS but allow blacklisted ARTISTS
// This allows users to select tracks by blacklisted artists as seed tracks
// but prevents blacklisted individual tracks from appearing
const filteredSearchResults = computed(() => {
  // Normalize a string: lowercase, remove non-alphanum (keep spaces), squash spaces
  const normalize = (s: string) => s.toLowerCase().replace(/[^a-z0-9\s]/g, '').replace(/\s+/g, ' ').trim()

  // Build query tokens from the user's input
  const rawQuery = searchQuery.value || ''
  const normalizedQuery = normalize(rawQuery)
  const queryTokens = normalizedQuery.length ? normalizedQuery.split(' ').filter(Boolean) : []

  // Deduplicate by normalized artist + title to avoid duplicate entries from backend/providers
  const seen = new Set<string>()

  return searchResults.value.filter(track => {
    // Basic validation
    if (!track || !track.name || !track.artist) {
      return false
    }

    // Filter out blacklisted tracks (but allow tracks by blacklisted artists)
    if (isTrackBlacklisted(track)) {
      return false
    }

    // Dedup key by normalized artist + title
    const normalizedArtist = normalize(track.artist)
    const normalizedTitle = normalize(track.name)
    const key = `${normalizedArtist}|${normalizedTitle}`
    if (seen.has(key)) {
      return false
    }

    // Optional content filter: require all typed words to appear in artist/title
    if (queryTokens.length) {
      // If track has an artists array, include them; otherwise use single artist
      const altArtists = (track as any).artists?.map((a: any) => a?.name || '')?.join(' ') || ''
      const haystack = normalize(`${altArtists} ${track.artist} ${track.name}`)
      // Looser match: require at least one token to appear somewhere
      const anyTokenPresent = queryTokens.some(tok => haystack.includes(tok))
      if (!anyTokenPresent) {
        return false
      }
    }

    seen.add(key)
    return true
  })
})

// Helper functions
const getTrackKey = (track: Track): string => {
  return `${track.artist}-${track.name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Local helper functions for saved tracks/artists (separate from global blacklist)
const isTrackSaved = (track: Track): boolean => {
  return savedTracks.value.has(getTrackKey(track))
}

const isArtistSaved = (track: Track): boolean => {
  return savedArtists.value.has(track.artist.toLowerCase())
}

// Note: Using global blacklist functions from composable instead of local ones

const formatDuration = (ms?: number): string => {
  if (!ms) {
    return '0:00'
  }
  const minutes = Math.floor(ms / 60000)
  const seconds = Math.floor((ms % 60000) / 1000)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

const formatArtists = (track: Track): string => {
  // If track has multiple artists array, use that; otherwise fall back to single artist string
  if (track.artists && track.artists.length > 0) {
    return track.artists.map(artist => artist.name).join(', ')
  }
  return track.artist
}

// Fill search bar with selected track (don't search yet) - DEPRECATED: now directly selects
const fillSearchBar = (track: Track) => {
  // This function is kept for compatibility but now directly selects the track
  selectSeedTrack(track)
}

// Handle search input - clear pending track when user types
const onSearchInput = () => {
  // Clear pending track when user types
  pendingTrack.value = null
  // Clear search results when user types
  searchResults.value = []
}

// Manual search functionality
const performSearch = () => {
  console.log('🔎 [SEED] performSearch clicked', {
    hasPendingTrack: !!pendingTrack.value,
    hasQuery: !!searchQuery.value.trim(),
    hasSelectedTrack: !!props.selectedTrack,
    hasRecommendations: !!props.hasRecommendations,
    emptySlotCount: props.emptySlotCount,
    currentBatchHasBannedItems: props.currentBatchHasBannedItems,
  })
  // If we have a pending track from dropdown, select it and search for related tracks
  if (pendingTrack.value) {
    console.log('🔎 [SEED] Selecting pending track and starting related search', {
      trackId: pendingTrack.value.id,
      artist: pendingTrack.value.artist,
      title: pendingTrack.value.name,
    })
    selectSeedTrack(pendingTrack.value)
    pendingTrack.value = null
    return
  }

  // If user has typed a search query, prioritize searching for new tracks
  if (searchQuery.value.trim()) {
    console.log('🔎 [SEED] Manual query search path')
    searchTracks()
  }

  // If there's already a selected seed track and recommendations, this is a refresh search
  // This only triggers when search query is empty
  // No Search Again behavior
}

// Search functionality
const searchTracks = async () => {
  if (!searchQuery.value.trim()) {
    searchResults.value = []
    searchError.value = ''
    return
  }

  console.log('🔍 [FRONTEND] Starting search for:', searchQuery.value.trim())

  isSearching.value = true
  searchError.value = ''
  searchResults.value = []
  currentPage.value = 1

  try {
    console.log('🔍 [FRONTEND] Making API call to music-discovery/search-seed')

    // Use the updated search-seed endpoint with Spotify fallback
    const response = await http.post('music-discovery/search-seed', {
      query: searchQuery.value.trim(),
      limit: 50, // Fetch more for better suggestions
    })

    console.log('🔍 [FRONTEND] API Response received:', {
      success: response.success,
      dataLength: response.data?.length,
      error: response.error,
    })

    if (response.success && response.data && Array.isArray(response.data)) {
      searchResults.value = response.data
      console.log(`🔍 [FRONTEND] Found ${response.data.length} tracks from search`)
      console.log('🔍 [FRONTEND] Sample track data:', response.data[0]) // Debug the structure
      console.log('🔍 [FRONTEND] All tracks:', response.data.map(t => `${t.artist} - ${t.name}`))
    } else {
      throw new Error(response.error || 'Invalid response format from backend')
    }
  } catch (err: any) {
    console.error('🔍 [FRONTEND] Search failed:', err)
    searchError.value = err.message || 'Failed to search tracks. Please try again.'
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}

// Seed track management
const selectSeedTrack = (track: Track) => {
  // Clear previous recommendations when selecting a new seed track
  emit('clear-recommendations')
  emit('update:selectedTrack', track)
  emit('track-selected', track)
  // Clear search results after selection
  searchResults.value = []
  searchQuery.value = ''
  // Always get related tracks for the newly selected seed track
  getRelatedTracks(track, false) // false = not a refresh
}

const clearSeedTrack = () => {
  emit('update:selectedTrack', null)
  // Clear search results to return to initial state
  searchResults.value = []
  searchQuery.value = ''
  // Emit event to clear recommendations
  emit('clear-recommendations')
}

const getRelatedTracks = (track: Track, isRefresh = false) => {
  console.log('📨 [SEED] Emitting related-tracks', {
    isRefresh,
    trackId: track?.id,
    artist: track?.artist,
    title: track?.name,
  })
  // Clear search results when getting related tracks to prevent overlay
  searchResults.value = []
  searchQuery.value = ''

  // Just emit the related tracks request without setting as seed track
  // (the track should already be set as seed track if called from selectSeedTrack)
  // Pass isRefresh to indicate if this is a refresh search or a new search
  emit('related-tracks', track, isRefresh)
}

// Note: currentBatchHasBannedItems is now managed by parent via props (Search Again removed)

// Music preferences
const saveTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artist = track.artist
    const title = track.name

    if (isTrackSaved(track)) {
      // Remove from saved (using DELETE request)
      const response = await http.delete('music-preferences/saved-track', {
        data: {
          isrc: track.id,
          track_name: title,
          artist_name: artist,
        },
      })

      if (response.success) {
        savedTracks.value.delete(trackKey)
        // console.log('Track unsaved successfully')
      } else {
        throw new Error(response.error || 'Failed to unsave track')
      }
    } else {
      // Save track
      const response = await http.post('music-preferences/save-track', {
        isrc: track.id,
        track_name: title,
        artist_name: artist,
        duration: Math.floor((track.duration_ms || 0) / 1000),
        track_count: 1,
        is_single_track: true,
      })

      if (response.success) {
        savedTracks.value.add(trackKey)
        // console.log('Track saved successfully')
      } else {
        throw new Error(response.error || 'Failed to save track')
      }
    }
  } catch (error: any) {
    // console.error('Failed to save track:', error)
    if (error.response?.status === 401 || error.message.includes('Unauthenticated') || error.message.includes('Authentication required')) {
      searchError.value = 'Please log in to save tracks'
    } else {
      searchError.value = `Failed to save track: ${error.response?.data?.error || error.message}`
    }
  } finally {
    processingTrack.value = null
  }
}

const toggleBlacklistTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artist = track.artist
    const title = track.name

    if (isTrackBlacklisted(track)) {
      // Unblock track
      const response = await http.delete('music-preferences/blacklist-track', {
        data: {
          isrc: track.id,
          track_name: title,
          artist_name: artist,
        },
      })

      if (response.success) {
        blacklistedTracks.value.delete(trackKey)
        // console.log('Track unblocked successfully')
      } else {
        throw new Error(response.error || 'Failed to unblock track')
      }
    } else {
      // Block track
      const response = await http.post('music-preferences/blacklist-track', {
        isrc: track.id,
        track_name: title,
        artist_name: artist,
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)
        // console.log('Track blacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    }
  } catch (error: any) {
    // console.error('Failed to toggle blacklist:', error)
    if (error.response?.status === 401 || error.message.includes('Unauthenticated') || error.message.includes('Authentication required')) {
      searchError.value = 'Please log in to manage track preferences'
    } else {
      searchError.value = `Failed to toggle blacklist: ${error.response?.data?.error || error.message}`
    }
  } finally {
    processingTrack.value = null
  }
}

// Artist preferences
const saveArtist = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artistName = track.artist
    const artistKey = artistName.toLowerCase()

    if (isArtistSaved(track)) {
      // Remove from saved artists
      const response = await http.delete('music-preferences/saved-artist', {
        data: {
          spotify_artist_id: track.id, // Use track ID as fallback if no artist ID
          artist_name: artistName,
        },
      })

      if (response.success) {
        savedArtists.value.delete(artistKey)
        // console.log('Artist unsaved successfully')
      } else {
        throw new Error(response.error || 'Failed to unsave artist')
      }
    } else {
      // Save artist
      const response = await http.post('music-preferences/save-artist', {
        spotify_artist_id: track.id, // Use track ID as fallback if no artist ID
        artist_name: artistName,
      })

      if (response.success) {
        savedArtists.value.add(artistKey)
        // console.log('Artist saved successfully')
      } else {
        throw new Error(response.error || 'Failed to save artist')
      }
    }
  } catch (error: any) {
    // console.error('Failed to save artist:', error)
    if (error.response?.status === 401 || error.message.includes('Unauthenticated') || error.message.includes('Authentication required')) {
      searchError.value = 'Please log in to save artists'
    } else {
      searchError.value = `Failed to save artist: ${error.response?.data?.error || error.message}`
    }
  } finally {
    processingTrack.value = null
  }
}

const blacklistArtist = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artistName = track.artist
    const artistKey = artistName.toLowerCase()

    if (isArtistBlacklisted(track.artist)) {
      // Remove from blacklisted artists
      const response = await http.delete('music-preferences/blacklist-artist', {
        data: {
          spotify_artist_id: track.id, // Use track ID as fallback if no artist ID
          artist_name: artistName,
        },
      })

      if (response.success) {
        blacklistedArtists.value.delete(artistKey)
        // console.log('Artist unblacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to unblacklist artist')
      }
    } else {
      // Blacklist artist
      const response = await http.post('music-preferences/blacklist-artist', {
        spotify_artist_id: track.id, // Use track ID as fallback if no artist ID
        artist_name: artistName,
      })

      if (response.success) {
        blacklistedArtists.value.add(artistKey)
        // console.log('Artist blacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to blacklist artist')
      }
    }
  } catch (error: any) {
    // console.error('Failed to blacklist artist:', error)
    if (error.response?.status === 401 || error.message.includes('Unauthenticated') || error.message.includes('Authentication required')) {
      searchError.value = 'Please log in to manage artist preferences'
    } else {
      searchError.value = `Failed to blacklist artist: ${error.response?.data?.error || error.message}`
    }
  } finally {
    processingTrack.value = null
  }
}

// Watch search results and emit changes to parent
watch(searchResults, newResults => {
  emit('search-results-changed', newResults.length > 0)
})

// Watch for recommendations to clear search results and prevent overlay
watch(() => props.hasRecommendations, (hasRecommendations, wasRecommendations) => {
  // Only clear when recommendations first appear (transition from false to true)
  if (hasRecommendations && !wasRecommendations) {
    // Clear search results when recommendations first appear to prevent overlay
    searchResults.value = []
    searchQuery.value = ''
    // Parent will reset currentBatchHasBannedItems flag when new recommendations arrive
  }
})

// Click outside handler to close dropdown
const handleClickOutside = (event: MouseEvent) => {
  if (searchContainer.value && !searchContainer.value.contains(event.target as Node)) {
    searchResults.value = []
  }
}

// Load saved preferences on mount
onMounted(async () => {
  await loadUserPreferences()
  document.addEventListener('click', handleClickOutside)
})

// Clean up event listener
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Load user's saved tracks and blacklisted items
const loadUserPreferences = async () => {
  try {
    // Load global blacklisted items (tracks + artists)
    await loadBlacklistedItems()

    // Load saved tracks (local to this component)
    const savedTracksResponse = await http.get('music-preferences/saved-tracks')
    if (savedTracksResponse.success && savedTracksResponse.data) {
      savedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        savedTracks.value.add(trackKey)
      })
      // console.log(`Loaded ${savedTracks.value.size} saved tracks`)
    }

    // Load saved artists (local to this component)
    const savedArtistsResponse = await http.get('music-preferences/saved-artists')
    if (savedArtistsResponse.success && savedArtistsResponse.data) {
      savedArtistsResponse.data.forEach((artist: any) => {
        savedArtists.value.add(artist.artist_name.toLowerCase())
      })
      // console.log(`Loaded ${savedArtists.value.size} saved artists`)
    }
  } catch (error) {
    //  console.log('Could not load user preferences (user may not be logged in)')
  }
}
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
