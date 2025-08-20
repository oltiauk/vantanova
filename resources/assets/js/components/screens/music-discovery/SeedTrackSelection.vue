<template>
  <div class="seed-selection mb-8">
    <!-- Search Container -->
    <div class="search-container mb-6">
      <div class="bg-white/5 rounded-lg p-4">
        <div class="max-w-4xl mx-auto">
          <div class="relative">
            <input
              v-model="searchQuery"
              type="text"
              class="w-full p-4 bg-white/10 rounded-lg border border-white/20 focus:border-k-accent text-white text-lg pr-12"
              placeholder="Search for artists, tracks, albums..."
              @input="onSearchInput"
            />
            <div v-if="isSearching" class="absolute right-4 top-1/2 transform -translate-y-1/2">
              <Icon :icon="faSpinner" spin class="text-white/60" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Selected Seed Track Display - Compact -->
    <div v-if="selectedTrack" class="selected-seed mb-4">
      <div class="bg-k-bg-secondary/50 border border-k-border rounded-lg px-3 py-2">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2 flex-1 min-w-0">
            <Icon :icon="faCheck" class="w-4 h-4 text-k-accent flex-shrink-0" />
            <span class="text-k-text-primary font-medium truncate">{{ selectedTrack.name }}</span>
            <span class="text-k-text-secondary text-sm truncate">by {{ selectedTrack.artist }}</span>
          </div>
          <button
            @click="clearSeedTrack"
            class="p-1 hover:bg-red-600/20 text-k-text-tertiary hover:text-red-400 rounded transition-colors flex-shrink-0 ml-2"
            title="Clear seed track"
          >
            <Icon :icon="faTimes" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- Search Dropdown Suggestions -->
    <div v-if="searchResults.length > 0" class="relative">
      <div class="absolute top-full left-0 right-0 bg-k-bg-secondary/95 backdrop-blur-sm border border-k-border rounded-lg mt-2 max-h-96 overflow-y-auto z-50 shadow-xl">
        <div class="py-2">
          <div v-for="track in filteredSearchResults.slice(0, 10)" :key="`suggestion-${track.id}`">
            <div 
              @click="selectSeedTrack(track)"
              class="flex items-center justify-between px-4 py-3 hover:bg-k-bg-tertiary cursor-pointer transition-colors group"
              :class="{
                'bg-k-accent/10 border-l-2 border-k-accent': selectedTrack && selectedTrack.id === track.id
              }"
            >
              <!-- Track Info -->
              <div class="flex-1 min-w-0">
                <div class="font-medium text-k-text-primary group-hover:text-k-accent transition-colors truncate">{{ track.name }}</div>
                <div class="text-k-text-secondary text-sm truncate">by {{ track.artist }}</div>
                <div v-if="track.album" class="text-k-text-tertiary text-xs truncate mt-0.5">{{ track.album }}</div>
              </div>
              
              <!-- Duration Badge -->
              <div class="bg-k-bg-tertiary px-2 py-1 rounded text-k-text-tertiary text-xs font-mono ml-3">
                {{ formatDuration(track.duration_ms) }}
              </div>
            </div>
          </div>
          
          <div v-if="filteredSearchResults.length > 10" class="px-4 py-2 text-center text-k-text-tertiary text-sm border-t border-k-border bg-k-bg-tertiary/30 mt-1">
            <Icon :icon="faMusic" class="mr-1 opacity-50" />
            {{ filteredSearchResults.length - 10 }} more tracks found
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isSearching && searchResults.length === 0" class="text-center p-12">
      <Icon :icon="faSpinner" spin class="text-4xl text-k-accent mb-4" />
      <h3 class="text-lg font-semibold text-white mb-2">Searching...</h3>
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
          @click="searchError = ''"
          class="ml-auto text-red-400 hover:text-red-300"
        >
          <Icon :icon="faTimes" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Instructions -->
    <div v-if="!selectedTrack && !isSearching && searchResults.length === 0 && !searchQuery.trim()" class="text-center py-12">
      <Icon :icon="faMusic" class="text-6xl text-white/20 mb-6" />
      <h3 class="text-xl font-medium text-white mb-4">Find Your Seed Track</h3>
      <p class="text-white/60 text-lg mb-6">
        Search for a song to use as the starting point for music discovery
      </p>
      <p class="text-white/40 text-sm">
        The algorithm will find similar tracks based on your selected parameters
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { faSearch, faSpinner, faExclamationTriangle, faHeart, faBan, faMusic, faPlay, faTimes, faCheck, faUserPlus, faUserMinus, faRandom } from '@fortawesome/free-solid-svg-icons'
import { ref, computed, onMounted, watch } from 'vue'
import { http } from '@/services/http'

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
}

// Props
interface Props {
  selectedTrack?: Track | null
  hasRecommendations?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  selectedTrack: null,
  hasRecommendations: false
})

// Emits
const emit = defineEmits<{
  'update:selectedTrack': [track: Track | null]
  'track-selected': [track: Track]
  'related-tracks': [track: Track]
  'search-results-changed': [hasResults: boolean]
}>()

// State
const searchQuery = ref('')
const searchResults = ref<Track[]>([])
const isSearching = ref(false)
const searchError = ref('')
const currentPage = ref(1)
const expandedTrackId = ref<string | null>(null)
let searchTimeout: NodeJS.Timeout | null = null

// Music preferences state
const savedTracks = ref<Set<string>>(new Set())
const blacklistedTracks = ref<Set<string>>(new Set())
const savedArtists = ref<Set<string>>(new Set())
const blacklistedArtists = ref<Set<string>>(new Set())
const processingTrack = ref<string | null>(null)

// YouTube cache
const youtubeVideoCache = ref<Record<string, string>>({})

// Computed - For seed track selection, show ALL tracks (including blacklisted)
// Blacklisted artists should appear in search so they can be selected as seed tracks
// But they won't appear in the recommendation results
const filteredSearchResults = computed(() => {
  // For seed track selection, show all search results without filtering
  // Only filter out tracks that are completely invalid/broken
  return searchResults.value.filter(track => {
    return track && track.name && track.artist // Basic validation only
  })
})

const displayedTracks = computed(() => {
  const start = (currentPage.value - 1) * 20
  const end = start + 20
  return filteredSearchResults.value.slice(start, end)
})

// Helper functions
const getTrackKey = (track: Track): string => {
  return `${track.artist}-${track.name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

const isTrackSaved = (track: Track): boolean => {
  return savedTracks.value.has(getTrackKey(track))
}

const isTrackBlacklisted = (track: Track): boolean => {
  return blacklistedTracks.value.has(getTrackKey(track))
}

const isArtistSaved = (track: Track): boolean => {
  return savedArtists.value.has(track.artist.toLowerCase())
}

const isArtistBlacklisted = (track: Track): boolean => {
  return blacklistedArtists.value.has(track.artist.toLowerCase())
}

const formatDuration = (ms?: number): string => {
  if (!ms) return '0:00'
  const minutes = Math.floor(ms / 60000)
  const seconds = Math.floor((ms % 60000) / 1000)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

// Auto-search functionality with debouncing
const onSearchInput = () => {
  // Clear existing timeout
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  // Clear results immediately if query is empty
  if (!searchQuery.value.trim()) {
    searchResults.value = []
    searchError.value = ''
    return
  }

  // Search works normally - recommendations will be hidden when search results show

  // Set new timeout for search
  searchTimeout = setTimeout(() => {
    searchTracks()
  }, 500) // Wait 500ms after user stops typing
}

// Search functionality
const searchTracks = async () => {
  if (!searchQuery.value.trim() || isSearching.value) return

  isSearching.value = true
  searchError.value = ''
  searchResults.value = []
  currentPage.value = 1

  try {
    // Use the updated search-seed endpoint with Spotify fallback
    const response = await http.post('music-discovery/search-seed', {
      query: searchQuery.value.trim(),
      limit: 100
    })
    
    if (response.success && response.data && Array.isArray(response.data)) {
      searchResults.value = response.data
      console.log(`Found ${response.data.length} tracks from search`)
    } else {
      throw new Error(response.error || 'Invalid response format from backend')
    }
  } catch (err: any) {
    console.error('Search failed:', err)
    searchError.value = err.message || 'Failed to search tracks. Please try again.'
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}

// Seed track management
const selectSeedTrack = (track: Track) => {
  emit('update:selectedTrack', track)
  emit('track-selected', track)
  // Clear search results after selection
  searchResults.value = []
  searchQuery.value = ''
  // Automatically find related tracks
  getRelatedTracks(track)
}

const clearSeedTrack = () => {
  emit('update:selectedTrack', null)
}

const getRelatedTracks = (track: Track) => {
  // Clear search results when getting related tracks to prevent overlay
  searchResults.value = []
  searchQuery.value = ''
  
  // Just emit the related tracks request without setting as seed track
  // (the track should already be set as seed track if called from selectSeedTrack)
  emit('related-tracks', track)
}


// Music preferences
const saveTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artist = track.artist
    const title = track.name

    if (isTrackSaved(track)) {
      // Remove from saved (using DELETE request)
      const response = await http.delete('music-preferences/blacklist-track', {
        isrc: track.id,
        track_name: title,
        artist_name: artist
      })

      if (response.success) {
        savedTracks.value.delete(trackKey)
        console.log('Track unsaved successfully')
      } else {
        throw new Error(response.error || 'Failed to unsave track')
      }
    } else {
      // Save track
      const response = await http.post('music-preferences/save-track', {
        isrc: track.id,
        track_name: title,
        artist_name: artist,
        duration: Math.floor((track.duration_ms || 0) / 1000)
      })

      if (response.success) {
        savedTracks.value.add(trackKey)
        console.log('Track saved successfully')
      } else {
        throw new Error(response.error || 'Failed to save track')
      }
    }
  } catch (error: any) {
    console.error('Failed to save track:', error)
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
        isrc: track.id,
        track_name: title,
        artist_name: artist
      })

      if (response.success) {
        blacklistedTracks.value.delete(trackKey)
        console.log('Track unblocked successfully')
      } else {
        throw new Error(response.error || 'Failed to unblock track')
      }
    } else {
      // Block track
      const response = await http.post('music-preferences/blacklist-track', {
        isrc: track.id,
        track_name: title,
        artist_name: artist
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)
        console.log('Track blacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    }
  } catch (error: any) {
    console.error('Failed to toggle blacklist:', error)
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
      const response = await http.delete('music-preferences/blacklist-artist', {
        spotify_artist_id: track.id, // Use track ID as fallback if no artist ID
        artist_name: artistName
      })

      if (response.success) {
        savedArtists.value.delete(artistKey)
        console.log('Artist unsaved successfully')
      } else {
        throw new Error(response.error || 'Failed to unsave artist')
      }
    } else {
      // Save artist
      const response = await http.post('music-preferences/save-artist', {
        spotify_artist_id: track.id, // Use track ID as fallback if no artist ID  
        artist_name: artistName
      })

      if (response.success) {
        savedArtists.value.add(artistKey)
        console.log('Artist saved successfully')
      } else {
        throw new Error(response.error || 'Failed to save artist')
      }
    }
  } catch (error: any) {
    console.error('Failed to save artist:', error)
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

    if (isArtistBlacklisted(track)) {
      // Remove from blacklisted artists
      const response = await http.delete('music-preferences/blacklist-artist', {
        spotify_artist_id: track.id, // Use track ID as fallback if no artist ID
        artist_name: artistName
      })

      if (response.success) {
        blacklistedArtists.value.delete(artistKey)
        console.log('Artist unblacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to unblacklist artist')
      }
    } else {
      // Blacklist artist
      const response = await http.post('music-preferences/blacklist-artist', {
        spotify_artist_id: track.id, // Use track ID as fallback if no artist ID
        artist_name: artistName
      })

      if (response.success) {
        blacklistedArtists.value.add(artistKey)
        console.log('Artist blacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to blacklist artist')
      }
    }
  } catch (error: any) {
    console.error('Failed to blacklist artist:', error)
    if (error.response?.status === 401 || error.message.includes('Unauthenticated') || error.message.includes('Authentication required')) {
      searchError.value = 'Please log in to manage artist preferences'
    } else {
      searchError.value = `Failed to blacklist artist: ${error.response?.data?.error || error.message}`
    }
  } finally {
    processingTrack.value = null
  }
}

// YouTube functionality
const toggleYouTubePlayer = async (track: Track) => {
  const trackKey = getTrackKey(track)
  
  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

  expandedTrackId.value = trackKey

  if (!youtubeVideoCache.value[trackKey]) {
    // Try to get actual YouTube search results for the real song
    const query = `${track.artist} ${track.name}`
    
    try {
      console.log('🔍 Searching YouTube for:', query)
      const response = await http.get('youtube/search', {
        params: {
          q: query
        }
      })
      
      console.log('YouTube API response:', response)
      
      if (response && response.data && response.items?.length > 0) {
        // Handle YouTube API response format
        const video = response.items[0]
        if (video?.id?.videoId) {
          youtubeVideoCache.value[trackKey] = video.id.videoId
          console.log('✅ Found YouTube video (API) for:', query, '| ID:', video.id.videoId)
          return
        }
      } else if (response && response.data && Array.isArray(response.data)) {
        // Handle scraping response format  
        const video = response.data[0]
        if (video?.id) {
          youtubeVideoCache.value[trackKey] = video.id
          console.log('✅ Found YouTube video (scraping) for:', query, '| ID:', video.id)
          return
        }
      } else if (response && response.items?.length > 0) {
        // Handle direct response format
        const video = response.items[0]
        if (video?.id?.videoId) {
          youtubeVideoCache.value[trackKey] = video.id.videoId
          console.log('✅ Found YouTube video (direct) for:', query, '| ID:', video.id.videoId)
          return
        }
      }
      
    } catch (error) {
      console.error('YouTube search failed for:', query, error)
    }
    
    // If search fails, don't play anything - show error message instead
    youtubeVideoCache.value[trackKey] = 'NO_VIDEO_FOUND'
    console.log('❌ No YouTube video found for:', query)
  }
}

// No more fallback system - search for the actual song or show error

// Pagination
const nextPage = () => {
  if (currentPage.value * 20 < filteredSearchResults.value.length) {
    currentPage.value++
  }
}

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

// Watch search results and emit changes to parent
watch(searchResults, (newResults) => {
  emit('search-results-changed', newResults.length > 0)
})

// Watch for recommendations to clear search results and prevent overlay
watch(() => props.hasRecommendations, (hasRecommendations, wasRecommendations) => {
  // Only clear when recommendations first appear (transition from false to true)
  if (hasRecommendations && !wasRecommendations) {
    // Clear search results when recommendations first appear to prevent overlay
    searchResults.value = []
    searchQuery.value = ''
  }
})

// Load saved preferences on mount
onMounted(async () => {
  await loadUserPreferences()
})

// Load user's saved tracks and blacklisted items
const loadUserPreferences = async () => {
  try {
    // Load blacklisted tracks
    const blacklistedTracksResponse = await http.get('music-preferences/blacklisted-tracks')
    if (blacklistedTracksResponse.success && blacklistedTracksResponse.data) {
      blacklistedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        blacklistedTracks.value.add(trackKey)
      })
      console.log(`Loaded ${blacklistedTracks.value.size} blacklisted tracks`)
    }

    // Load saved tracks  
    const savedTracksResponse = await http.get('music-preferences/saved-tracks')
    if (savedTracksResponse.success && savedTracksResponse.data) {
      savedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        savedTracks.value.add(trackKey)
      })
      console.log(`Loaded ${savedTracks.value.size} saved tracks`)
    }

    // Load blacklisted artists
    const blacklistedArtistsResponse = await http.get('music-preferences/blacklisted-artists')
    if (blacklistedArtistsResponse.success && blacklistedArtistsResponse.data) {
      blacklistedArtistsResponse.data.forEach((artist: any) => {
        blacklistedArtists.value.add(artist.artist_name.toLowerCase())
      })
      console.log(`Loaded ${blacklistedArtists.value.size} blacklisted artists`)
    }

    // Load saved artists
    const savedArtistsResponse = await http.get('music-preferences/saved-artists')
    if (savedArtistsResponse.success && savedArtistsResponse.data) {
      savedArtistsResponse.data.forEach((artist: any) => {
        savedArtists.value.add(artist.artist_name.toLowerCase())
      })
      console.log(`Loaded ${savedArtists.value.size} saved artists`)
    }

  } catch (error) {
    console.log('Could not load user preferences (user may not be logged in)')
  }
}
</script>

<style scoped>
.seed-selection {
  max-width: 100%;
}

/* YouTube Dropdown Animations - SoundCloud style */
.youtube-dropdown-enter-active {
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.youtube-dropdown-leave-active {
  transition: all 0.25s cubic-bezier(0.55, 0.06, 0.68, 0.19);
}

.youtube-dropdown-enter-from {
  opacity: 0;
  transform: translateY(-10px) scaleY(0.8);
}

.youtube-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-5px) scaleY(0.9);
}

.youtube-dropdown-enter-to,
.youtube-dropdown-leave-from {
  opacity: 1;
  transform: translateY(0) scaleY(1);
}

/* Container animation for smooth height transitions */
.youtube-player-container {
  animation: slideDown 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
    max-height: 0;
  }
  to {
    opacity: 1;
    transform: translateY(0);
    max-height: 400px;
  }
}

/* Enhanced loading and error states */
.aspect-video {
  transition: all 0.2s ease;
}

.aspect-video:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}
</style>