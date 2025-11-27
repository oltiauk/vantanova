<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="simple" class="text-center" header-image="/VantaNova-Logo.svg">
        <template #subtitle>
          <div class="rounded-lg px-4 mr-16">
            <div class="max-w-4xl mx-auto text-center">
              Discover music similar to your seed track
            </div>
          </div>
        </template>
      </ScreenHeader>
    </template>

    <div class="music-discovery-screen">
      <SeedTrackSelection
        v-model:selected-track="selectedSeedTrack"
        :has-recommendations="allRecommendations.length > 0 || isDiscovering"
        :has-more-in-queue="hasMoreInQueue"
        :queue-key="currentQueueKey"
        :current-batch-has-banned-items="userHasBannedItems"
        :empty-slot-count="emptySlotCount"
        :queue-exhausted="queueExhausted"
        @track-selected="onTrackSelected"
        @related-tracks="(track, isRefresh) => onRelatedTracksRequested(track, isRefresh)"
        @search-results-changed="onSearchResultsChanged"
        @clear-recommendations="onClearRecommendations"
      />

      <!-- Related Tracks Results Table -->
      <div id="related-tracks-section">
        <RecommendationsTable
          v-if="(allRecommendations.length > 0 || isDiscovering || errorMessage) && selectedSeedTrack"
          ref="recommendationsTableRef"
          :key="`recommendations-${selectedSeedTrack.id}`"
          :recommendations="allRecommendations"
          :is-discovering="isDiscovering"
          :error-message="errorMessage"
          :current-provider="currentProvider"
          :seed-track="selectedSeedTrack"
          @clear-error="errorMessage = ''"
          @related-tracks="onRelatedTracksRequested"
        />
      </div>
    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { http } from '@/services/http'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { useRouter } from '@/composables/useRouter'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import SeedTrackSelection from '@/components/screens/music-discovery/SeedTrackSelection.vue'
import RecommendationsTable from '@/components/screens/music-discovery/RecommendationsTable.vue'

interface Track {
  id: string
  name: string
  artist: string
  album: string
  preview_url?: string
  external_url?: string
  image?: string
  duration_ms?: number
  key?: number
  mode?: number
  isPendingBlacklist?: boolean
  uri?: string
}

interface Parameters {
  bpm_min: number
  bpm_max: number
  popularity: number
  danceability: number
  energy: number
  valence: number
  acousticness: number
  instrumentalness: number
  liveness: number
  speechiness: number
  duration_ms: number
  key_compatibility: boolean
}

interface EnabledParameters {
  tempo: boolean
  popularity: boolean
  danceability: boolean
  energy: boolean
  valence: boolean
  acousticness: boolean
  instrumentalness: boolean
  liveness: boolean
  speechiness: boolean
  duration: boolean
  key_compatibility: boolean
  key_selection: boolean
}

interface ApiResponse<T> {
  success: boolean
  data?: T
  error?: string
  message?: string
  has_more?: boolean
  queue_key?: string
}

// Key mappings
const keyNames = {
  0: 'C',
  1: 'C♯/D♭',
  2: 'D',
  3: 'D♯/E♭',
  4: 'E',
  5: 'F',
  6: 'F♯/G♭',
  7: 'G',
  8: 'G♯/A♭',
  9: 'A',
  10: 'A♯/B♭',
  11: 'B',
}

const selectedSeedTrack = ref<Track | null>(null)
const allRecommendations = ref<Track[]>([])
const recommendationsTableRef = ref<InstanceType<typeof RecommendationsTable> | null>(null)
const isDiscovering = ref(false)
const errorMessage = ref('')
const currentProvider = ref('')
const hasSearchResults = ref(false)

// Helper function to sanitize error messages - remove RapidAPI mentions
const sanitizeErrorMessage = (message: string): string => {
  if (!message) {
    return message
  }
  // Replace RapidAPI with generic terms (case insensitive)
  return message
    .replace(/rapidapi/gi, 'Music Discovery API')
    .replace(/RapidAPI/g, 'Music Discovery API')
    .replace(/rapid-api/gi, 'Music Discovery API')
    .replace(/rapid api/gi, 'Music Discovery API')
}

// Queue state management (legacy placeholders)
const hasMoreInQueue = ref(false) // Track if more results available
const currentQueueKey = ref<string | null>(null) // Session key for queue
const userHasBannedItems = ref(false) // Track if user has banned items since last search
const queueExhausted = ref(false) // Track when all tracks from queue have been displayed


// Removed force close preview feature

// Initialize global blacklist filtering
const {
  filterTracks,
  loadBlacklistedItems,
} = useBlacklistFiltering()

// Initialize router for handling route parameters
const { onRouteChanged } = useRouter()

const seedTrackKey = ref<number | null>(null)
const seedTrackMode = ref<number | null>(null)
const keyAnalysisResults = ref<{ id: string, name: string, key: number }[]>([])

const parameters = ref<Parameters>({
  bpm_min: 100,
  bpm_max: 140,
  popularity: 50,
  danceability: 0.5,
  energy: 0.5,
  valence: 0.5,
  acousticness: 0.5,
  instrumentalness: 0.5,
  liveness: 0.5,
  speechiness: 0.5,
  duration_ms: 240000,
  key_compatibility: false,
})

const enabledParameters = ref<EnabledParameters>({
  tempo: false,
  popularity: false,
  danceability: false,
  energy: false,
  valence: false,
  acousticness: false,
  instrumentalness: false,
  liveness: false,
  speechiness: false,
  duration: false,
  key_compatibility: false,
  key_selection: false,
})

const selectedKeyMode = ref('off')
const customKey = ref(-1)

const hasEnabledParameters = computed(() => {
  return Object.values(enabledParameters.value).some(enabled => enabled)
})

// Count empty slots in the slot map
const emptySlotCount = computed(() => 0)

// Note: Pagination is now handled by the RecommendationsTable component

const onTrackSelected = async (track: Track) => {
  selectedSeedTrack.value = track
  seedTrackKey.value = null
  seedTrackMode.value = null
  keyAnalysisResults.value = []
  allRecommendations.value = []
  errorMessage.value = ''

  if (track) {
    await getSeedTrackKey(track.id)
  }
}

const onSearchResultsChanged = (hasResults: boolean) => {
  hasSearchResults.value = hasResults
}

const onClearRecommendations = () => {
  // Reset all recommendation state to go back to beginning
  allRecommendations.value = []
  errorMessage.value = ''
  currentProvider.value = ''
  isDiscovering.value = false
  hasMoreInQueue.value = false
  currentQueueKey.value = null
  userHasBannedItems.value = false
  queueExhausted.value = false
}

const onRelatedTracksRequested = async (track: Track, isRefresh = false) => {
  console.log('📥 [DISCOVERY] onRelatedTracksRequested received', {
    isRefresh,
    trackId: track?.id,
    artist: track?.artist,
    title: track?.name,
    hasRecommendations: allRecommendations.value.length,
  })

  if (!track) {
    return
  }

  selectedSeedTrack.value = track
  // Clear previous results for new search
  allRecommendations.value = []
  errorMessage.value = ''
  hasMoreInQueue.value = false
  currentQueueKey.value = null
  userHasBannedItems.value = false
  queueExhausted.value = false

  // Get related tracks from API
  await getRelatedTracks(track, isRefresh)

  // Auto-scroll to results after a brief delay
  setTimeout(() => {
    const element = document.getElementById('related-tracks-section')
    if (element) {
      element.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    }
  }, 300)
}

const getSeedTrackKey = async (trackId: string) => {
  try {
    const response: ApiResponse<{ key: number, mode: number }> = await http.get(`music-discovery/track-key/${trackId}`)
    if (response.success && response.data) {
      seedTrackKey.value = response.data.key
      seedTrackMode.value = response.data.mode
    }
  } catch (error: any) {
    seedTrackKey.value = null
    seedTrackMode.value = null
  }
}

// Track key analysis removed to avoid 404 errors

const getKeyMatchClass = (trackKey: number) => {
  if (seedTrackKey.value === null || seedTrackKey.value === -1) {
    return 'bg-gray-600/20 text-gray-300'
  }

  if (trackKey === seedTrackKey.value) {
    return 'bg-green-600/20 text-green-300 border border-green-500/30'
  }

  const compatibleKeys = getCompatibleKeys(seedTrackKey.value)
  if (compatibleKeys.includes(trackKey)) {
    return 'bg-yellow-600/20 text-yellow-300 border border-yellow-500/30'
  }

  return 'bg-red-600/20 text-red-300 border border-red-500/30'
}

const getCompatibleKeys = (key: number) => {
  if (key === -1) {
    return []
  }

  return [
    (key + 7) % 12, // Perfect 5th
    (key + 5) % 12, // Perfect 4th
    (key + 2) % 12, // Major 2nd (relative)
    (key + 9) % 12, // Major 6th (relative)
  ]
}

// Key analysis batch function removed to avoid 404 errors

const discoverMusicSoundStats = async () => {
  if (!selectedSeedTrack.value) {
    return
  }

  isDiscovering.value = true
  errorMessage.value = ''
  currentProvider.value = 'SoundStats'

  try {
    const response: ApiResponse<Track[]> = await http.post('music-discovery/discover-soundstats', {
      seed_track: selectedSeedTrack.value.id,
      parameters: hasEnabledParameters.value ? parameters.value : null,
      enabled_parameters: enabledParameters.value,
    })

    if (response.success && response.data) {
      // Filter out blacklisted tracks and tracks by blacklisted artists
      const allTracks = response.data
      const filteredTracks = filterTracks(allTracks)

      // console.log(`📋 SoundStats: Filtered out ${allTracks.length - filteredTracks.length} blacklisted tracks/artists`)

      allRecommendations.value = filteredTracks
      // IMPORTANT: Don't block the UI - analyze keys in background
      // This will complete after the UI updates
      setTimeout(() => {
        analyzeRecommendationKeysBatch(tracks.slice(0, 12))
      }, 100)
    }
  } catch (error: any) {
    const rawMessage = error.response?.data?.message || error.message || 'Failed to discover music'
    errorMessage.value = sanitizeErrorMessage(rawMessage)
  } finally {
    isDiscovering.value = false
  }
}

const discoverMusicReccoBeats = async () => {
  if (!selectedSeedTrack.value) {
    return
  }

  isDiscovering.value = true
  errorMessage.value = ''
  currentProvider.value = 'ReccoBeats'

  const reccoBeatsParams: any = {
    seed_track_id: selectedSeedTrack.value.id,
    limit: 50,
  }

  // Add enabled parameters
  if (hasEnabledParameters.value) {
    if (enabledParameters.value.tempo) {
      reccoBeatsParams.min_tempo = parameters.value.bpm_min
      reccoBeatsParams.max_tempo = parameters.value.bpm_max
    }
    if (enabledParameters.value.popularity) {
      reccoBeatsParams.target_popularity = parameters.value.popularity
    }
    if (enabledParameters.value.danceability) {
      reccoBeatsParams.target_danceability = parameters.value.danceability
    }
    if (enabledParameters.value.energy) {
      reccoBeatsParams.target_energy = parameters.value.energy
    }
    if (enabledParameters.value.valence) {
      reccoBeatsParams.target_valence = parameters.value.valence
    }
    if (enabledParameters.value.acousticness) {
      reccoBeatsParams.target_acousticness = parameters.value.acousticness
    }
    if (enabledParameters.value.instrumentalness) {
      reccoBeatsParams.target_instrumentalness = parameters.value.instrumentalness
    }
    if (enabledParameters.value.liveness) {
      reccoBeatsParams.target_liveness = parameters.value.liveness
    }
    if (enabledParameters.value.speechiness) {
      reccoBeatsParams.target_speechiness = parameters.value.speechiness
    }
  }

  // Handle key selection if enabled
  if (enabledParameters.value.key_selection && selectedKeyMode.value !== 'off') {
    if (selectedKeyMode.value === 'same') {
      if (seedTrackKey.value !== null && seedTrackKey.value !== -1) {
        reccoBeatsParams.key = seedTrackKey.value
      }
    } else if (selectedKeyMode.value === 'compatible') {
      if (seedTrackKey.value !== null && seedTrackKey.value !== -1) {
        const compatibleKeys = getCompatibleKeys(seedTrackKey.value)
        reccoBeatsParams.key = compatibleKeys[1] // Perfect 5th
      }
    } else if (selectedKeyMode.value === 'custom') {
      if (customKey.value !== -1) {
        reccoBeatsParams.key = customKey.value
      }
    }
  }

  try {
    const response: ApiResponse<Track[]> = await http.post('music-discovery/discover-reccobeats', reccoBeatsParams)

    if (response.success && response.data) {
      // Filter out blacklisted tracks and tracks by blacklisted artists
      const allTracks = response.data
      const filteredTracks = filterTracks(allTracks)

      // console.log(`📋 ReccoBeats: Filtered out ${allTracks.length - filteredTracks.length} blacklisted tracks/artists`)

      allRecommendations.value = filteredTracks
      // Skip key analysis for now to avoid 404 errors
      // await analyzeRecommendationKeys(tracks.slice(0, 12))
    }
  } catch (error: any) {
    const rawMessage = error.response?.data?.message || error.message || 'Failed to discover music'
    errorMessage.value = sanitizeErrorMessage(rawMessage)
  } finally {
    isDiscovering.value = false
  }
}

const discoverMusicRapidApi = async () => {
  if (!selectedSeedTrack.value) {
    return
  }

  isDiscovering.value = true
  errorMessage.value = ''
  currentProvider.value = 'Music Discovery API'

  try {
    const response: ApiResponse<Track[]> = await http.post('music-discovery/discover-rapidapi', {
      seed_track_uri: selectedSeedTrack.value.uri || `spotify:track:${selectedSeedTrack.value.id}`,
      max_popularity: parameters.value.popularity,
      apply_popularity_filter: enabledParameters.value.popularity,
      limit: 50,
      offset: 0,
      exclude_track_ids: [],
    })

    if (response.success && response.data) {
      // Filter out blacklisted tracks and tracks by blacklisted artists
      const allTracks = response.data
      const filteredTracks = filterTracks(allTracks)

      // console.log(`📋 RapidAPI: Filtered out ${allTracks.length - filteredTracks.length} blacklisted tracks/artists`)

      allRecommendations.value = filteredTracks
      // Key analysis removed to avoid 404 errors
    }
  } catch (error: any) {
    const rawMessage = error.response?.data?.message || error.message || 'Failed to discover music'
    errorMessage.value = sanitizeErrorMessage(rawMessage)
  } finally {
    isDiscovering.value = false
  }
}

const analyzeRecommendationKeys = async (tracks: Track[]) => {
  if (tracks.length === 0) {
    return
  }

  keyAnalysisResults.value = []

  // Run all API calls in parallel instead of sequential
  // Run key analysis in background without blocking UI
  keyAnalysisResults.value = [] // Reset results

  tracks.forEach(async track => {
    try {
      const key = await analyzeTrack(track.id)
      if (key !== null) {
        // Add result as it comes in (non-blocking)
        keyAnalysisResults.value.push({ id: track.id, name: track.name, key })
      }
    } catch (error) {
    }
  })
}

const getRelatedTracks = async (track: Track, isRefresh = false) => {
  if (!track) {
    return
  }

  // Set this track as the selected seed track if it isn't already
  if (!selectedSeedTrack.value || selectedSeedTrack.value.id !== track.id) {
    selectedSeedTrack.value = track
  }

  isDiscovering.value = true
  errorMessage.value = ''
  currentProvider.value = 'Related Tracks (Spotify + Shazam + Last.fm)'

  try {
    // console.log('🎵 Getting related tracks for:', track.name, 'by', track.artist)

    const params: any = {
      track_id: track.id,
      artist_name: track.artist,
      track_title: track.name,
      limit: 100,
    }

    const response: ApiResponse<Track[]> = await http.get('music-discovery/related-tracks', {
      params,
    })

    if (response.success && response.data) {
      const allTracks = response.data

      // This is initial search - filter out OLD blacklisted tracks
      console.log(`📋 Initial search: Received ${allTracks.length} tracks from backend`)

      // Filter out OLD blacklisted tracks (pre-existing in user's blacklist)
      // NEW blacklisted tracks (blacklisted during this session) will stay visible until Search Again
      const filteredTracks = filterTracks(allTracks)
      const filteredCount = allTracks.length - filteredTracks.length
      console.log(`🔍 Filtered out ${filteredCount} OLD blacklisted tracks/artists`)

      // If all tracks were filtered out, show error and clear empty slots
      if (filteredTracks.length === 0) {
        console.warn(`⚠️ [SLOT SYSTEM] All ${allTracks.length} tracks were blacklisted - no results available`)
        errorMessage.value = `All ${allTracks.length} recommended tracks are blacklisted. Please unban some tracks or artists to see recommendations.`

        allRecommendations.value = []

        console.log(`✅ No tracks available after filtering`)
        isDiscovering.value = false // Set to false so error message can be displayed
        return
      }

      // Helper function to parse release date for sorting
      const parseReleaseDate = (releaseDate: string | undefined): Date | null => {
        if (!releaseDate) {
          return null
        }

        try {
          const dateStr = releaseDate.trim()

          // Parse the date - handle YYYY-MM-DD, YYYY-MM, or YYYY formats
          let date: Date
          if (dateStr.includes('-')) {
            // Full date or date with month
            date = new Date(dateStr)
          } else if (dateStr.length === 4) {
            // Just year - use January 1st of that year
            date = new Date(Number.parseInt(dateStr), 0, 1)
          } else {
            return null
          }

          // Check if date is valid
          if (isNaN(date.getTime())) {
            return null
          }

          return date
        } catch (error) {
          return null
        }
      }

      // Sort tracks by release date (most recent first)
      console.log('📅 Sorting all tracks by release date (most recent first)')
      const sortedTracks = [...filteredTracks].sort((a, b) => {
        const dateA = parseReleaseDate(a.release_date)
        const dateB = parseReleaseDate(b.release_date)

        // Tracks without dates go to the end
        if (!dateA && !dateB) {
          return 0
        }
        if (!dateA) {
          return 1
        }
        if (!dateB) {
          return -1
        }

        // Most recent first (descending order)
        return dateB.getTime() - dateA.getTime()
      })

      console.log('📅 Sorted all tracks by release date', {
        totalTracks: sortedTracks.length,
        top5: sortedTracks.slice(0, 5).map(t => ({ name: t.name, release_date: t.release_date })),
        bottom5: sortedTracks.slice(-5).map(t => ({ name: t.name, release_date: t.release_date })),
      })

      allRecommendations.value = sortedTracks
      queueExhausted.value = false

      // Auto-scroll to bottom of page after a brief delay
      setTimeout(() => {
        window.scrollTo({
          top: document.documentElement.scrollHeight,
          behavior: 'smooth',
        })
      }, 500)

      // Key analysis removed to avoid 404 errors
    } else {
      throw new Error(response.error || 'Failed to get related tracks')
    }
  } catch (error: any) {
    console.error('Failed to get related tracks:', error)
    const rawMessage = error.response?.data?.message || error.message || 'Failed to get related tracks'
    errorMessage.value = sanitizeErrorMessage(rawMessage)
  } finally {
    isDiscovering.value = false
  }
}

const discoverRelatedTracks = async () => {
  if (!selectedSeedTrack.value) {
    errorMessage.value = 'Please select a seed track first'
    return
  }

  await getRelatedTracks(selectedSeedTrack.value)
}

// Removed loadMoreRecommendations - pagination is now handled by RecommendationsTable

// Load blacklisted tracks on component mount
onMounted(async () => {
  await loadBlacklistedItems()

  // Check for seed track data when component mounts
  await checkForSeedTrackData()
})

// Handle route changes to set seed track from SavedTracksScreen
onRouteChanged(async route => {
  if (route.screen === 'MusicDiscovery') {
    // Check for seed track data in localStorage
    await checkForSeedTrackData()
  } else {
  }
})

// Check localStorage for seed track data from SavedTracksScreen
const checkForSeedTrackData = async () => {
  try {
    const seedTrackJson = localStorage.getItem('koel-music-discovery-seed-track')
    if (seedTrackJson) {
      const seedTrackData = JSON.parse(seedTrackJson)
      console.log('🔍 [MUSIC DISCOVERY] Found seed track data:', seedTrackData)

      // Clear the data so it doesn't trigger again
      localStorage.removeItem('koel-music-discovery-seed-track')

      // Check if data is recent (within last 30 seconds)
      const isRecent = Date.now() - seedTrackData.timestamp < 30000
      if (isRecent && seedTrackData.id) {
        console.log('🔍 [MUSIC DISCOVERY] Setting up seed track from saved tracks - FAST MODE')
        // Start the process immediately without await to make UI responsive
        handleSpotifyTrackSeed(seedTrackData.id, seedTrackData.name, seedTrackData.artist)
      } else {
        console.log('🔍 [MUSIC DISCOVERY] Seed track data too old or invalid')
      }
    }
  } catch (error) {
    console.error('🔍 [MUSIC DISCOVERY] Failed to parse seed track data:', error)
  }
}

// Handle setting a seed track from Spotify track ID
const handleSpotifyTrackSeed = async (spotifyTrackId: string, trackName?: string, artistName?: string) => {
  try {
    // Use track name and artist from route params if available
    const name = trackName || 'Unknown Track'
    const artist = artistName || 'Unknown Artist'

    // Create seed track object with known information
    const seedTrack: Track = {
      id: spotifyTrackId,
      name,
      artist,
      album: 'Unknown Album',
      external_url: `https://open.spotify.com/track/${spotifyTrackId}`,
    }

    // Set as selected seed track IMMEDIATELY for fast UI update
    selectedSeedTrack.value = seedTrack
    console.log('🔍 [MUSIC DISCOVERY] Seed track set immediately, starting background tasks')

    // Start related tracks loading immediately (don't wait for preview data)
    const relatedTracksPromise = onRelatedTracksRequested(seedTrack)

    // Fetch preview data in background and update when ready
    const previewPromise = http.get('music-discovery/track-preview', {
      params: {
        artist_name: artist,
        track_title: name,
        source: 'spotify',
        track_id: spotifyTrackId,
      },
    }).then(response => {
      if (response.success && response.data) {
        // Update seed track with preview and image data
        if (selectedSeedTrack.value && selectedSeedTrack.value.id === spotifyTrackId) {
          selectedSeedTrack.value.preview_url = response.data.oembed?.preview_url
          selectedSeedTrack.value.image = response.data.oembed?.thumbnail_url
          console.log('🔍 [MUSIC DISCOVERY] Preview data loaded in background')
        }
      }
    }).catch(previewError => {
      console.warn('Failed to load preview data:', previewError)
    })

    // Wait for related tracks (this is the main UI content)
    await relatedTracksPromise

    // Preview data will complete in background when ready
  } catch (error) {
    console.error('Failed to load seed track from Spotify ID:', error)
    errorMessage.value = 'Failed to load track from Spotify'
  }
}

// Handle setting a seed track by searching for artist and track name
const handleSearchTrackSeed = async (artist: string, track: string) => {
  try {
    // Use the existing search functionality (this would need to be implemented)
    // For now, we'll show an error message suggesting manual search
    errorMessage.value = `Please search for "${track}" by ${artist} manually in the search above`
  } catch (error) {
    console.error('Failed to search for seed track:', error)
    errorMessage.value = 'Failed to search for track'
  }
}

// Removed loadMoreRapidApiRecommendations - no longer needed with proper pagination
</script>

<style scoped>
.music-discovery-screen {
  width: 100%;
  margin: 0;
  padding: 0 2rem;
}

@media (max-width: 768px) {
  .music-discovery-screen {
    padding: 0 1rem;
  }
}
</style>
