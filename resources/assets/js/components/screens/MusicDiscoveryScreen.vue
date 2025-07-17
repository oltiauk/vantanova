<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>
        Discover Music - Provider Comparison
        <template #meta>
          <span class="text-k-text-secondary text-lg">Test SoundStats vs ReccoBeats key handling</span>
        </template>
      </ScreenHeader>
    </template>

    <div class="music-discovery-screen">
      <SeedTrackSelection
        v-model:selected-track="selectedSeedTrack"
        @track-selected="onTrackSelected"
      />

      <!-- Seed Track Key Display -->
      <div v-if="selectedSeedTrack && seedTrackKey !== null" class="mb-6 p-4 bg-blue-900/20 border border-blue-500/30 rounded-lg">
        <h3 class="text-blue-300 font-medium mb-2">🎹 Seed Track Analysis</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-k-text-secondary">Key:</span>
            <span class="text-k-text-primary ml-2 font-medium">
              {{ seedTrackKey !== -1 ? keyNames[seedTrackKey] || 'Unknown' : 'No key detected' }}
            </span>
          </div>
          <div>
            <span class="text-k-text-secondary">Mode:</span>
            <span class="text-k-text-primary ml-2 font-medium">
              {{ seedTrackMode === 1 ? 'Major' : seedTrackMode === 0 ? 'Minor' : 'Unknown' }}
            </span>
          </div>
        </div>
        <div class="mt-2 text-xs text-blue-200">
          This is the musical key of your selected track. Watch how each provider handles key recommendations.
        </div>
      </div>

      <ParameterControls
        v-if="selectedSeedTrack"
        v-model:parameters="parameters"
        v-model:enabled-parameters="enabledParameters"
        v-model:selected-key-mode="selectedKeyMode"
        v-model:custom-key="customKey"
        :has-enabled-parameters="hasEnabledParameters"
        :seed-track-key="seedTrackKey"
        :key-names="keyNames"
        :is-discovering="isDiscovering"
        :current-provider="currentProvider"
        @discover-soundstats="discoverMusicSoundStats"
        @discover-reccobeats="discoverMusicReccoBeats"
        @discover-rapidapi="discoverMusicRapidApi"
      />

      <RecommendationsList
        v-if="selectedSeedTrack && recommendations.length > 0"
        :recommendations="recommendations"
        :displayed-count="displayedCount"
        :has-more-to-load="hasMoreToLoad"
        :is-discovering="isDiscovering"
        :is-loading-more="isLoadingMore"
        :error-message="errorMessage"
        :current-provider="currentProvider"
        :seed-track-key="seedTrackKey"
        :key-names="keyNames"
        @clear-error="errorMessage = ''"
        @load-more="loadMoreRecommendations"
        @analyze-track="analyzeTrack"
      />

      <!-- Key Analysis Results -->
      <div v-if="keyAnalysisResults.length > 0" class="mt-6 p-4 bg-green-900/20 border border-green-500/30 rounded-lg">
        <h3 class="text-green-300 font-medium mb-3">🔍 Key Analysis Results</h3>
        <div class="text-sm space-y-2">
          <div>
            <span class="text-k-text-secondary">Provider:</span>
            <span class="text-k-text-primary ml-2 font-medium">{{ currentProvider }}</span>
          </div>
          <div>
            <span class="text-k-text-secondary">Seed Key:</span>
            <span class="text-k-text-primary ml-2 font-medium">
              {{ seedTrackKey !== -1 ? keyNames[seedTrackKey] : 'Unknown' }}
            </span>
          </div>
          <div class="mt-3">
            <div class="text-k-text-secondary mb-2">Recommended Track Keys:</div>
            <div class="grid grid-cols-6 gap-2">
              <div
                v-for="result in keyAnalysisResults.slice(0, 12)" :key="result.id"
                class="p-2 rounded text-xs text-center"
                :class="getKeyMatchClass(result.key)"
              >
                <div class="font-medium">{{ keyNames[result.key] || 'Unknown' }}</div>
                <div class="text-xs opacity-75">{{ result.name.slice(0, 15) }}...</div>
              </div>
            </div>
          </div>
          <div class="mt-3 text-xs">
            <span class="text-green-400">● Same Key</span>
            <span class="text-yellow-400 ml-4">● Compatible Key</span>
            <span class="text-red-400 ml-4">● Different Key</span>
          </div>
        </div>
      </div>
    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { http } from '@/services/http'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import SeedTrackSelection from '@/components/screens/music-discovery/SeedTrackSelection.vue'
import ParameterControls from '@/components/screens/music-discovery/ParameterControls.vue'
import RecommendationsList from '@/components/screens/music-discovery/RecommendationsList.vue'

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
const recommendations = ref<Track[]>([])
const allRecommendations = ref<Track[]>([])
const displayedCount = ref(0)
const isDiscovering = ref(false)
const isLoadingMore = ref(false)
const errorMessage = ref('')
const hasMoreToLoad = ref(true)
const currentProvider = ref('')

// Key analysis
const seedTrackKey = ref<number | null>(null)
const seedTrackMode = ref<number | null>(null)
const selectedKeyMode = ref('any')
const customKey = ref(-1)
const keyAnalysisResults = ref<Array<{ id: string, name: string, key: number }>>([])

const INITIAL_LOAD = 10
const LOAD_MORE_BATCH = 10

const parameters = ref<Parameters>({
  bpm_min: 100,
  bpm_max: 130,
  popularity: 50,
  danceability: 0.5,
  energy: 0.5,
  valence: 0.5,
  acousticness: 0.5,
  instrumentalness: 0.5,
  liveness: 0.5,
  speechiness: 0.5,
  duration_ms: 200000,
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

const hasEnabledParameters = computed(() => {
  return Object.values(enabledParameters.value).some(enabled => enabled)
})

const getCompatibleKeys = (seedKey: number): number[] => {
  if (seedKey === null || seedKey === -1) {
    return []
  }

  return [
    seedKey, // Same key
    (seedKey + 7) % 12, // Perfect 5th
    (seedKey + 5) % 12, // Perfect 4th
    (seedKey + 3) % 12, // Minor 3rd
    (seedKey + 9) % 12, // Major 6th
  ]
}

const getKeyMatchClass = (trackKey: number): string => {
  if (seedTrackKey.value === null || seedTrackKey.value === -1) {
    return 'bg-gray-600'
  }
  if (trackKey === seedTrackKey.value) {
    return 'bg-green-600'
  }
  if (getCompatibleKeys(seedTrackKey.value).includes(trackKey)) {
    return 'bg-yellow-600'
  }
  return 'bg-red-600'
}

const getSeedTrackKey = async (trackId: string) => {
  try {
    const response: ApiResponse<{ key: number, mode: number }> = await http.get(`music-discovery/track-features/${trackId}`)
    if (response.success && response.data) {
      seedTrackKey.value = response.data.key
      seedTrackMode.value = response.data.mode
      console.log('🎹 Seed track key analysis:', response.data)
    }
  } catch (error: any) {
    console.log('Could not get track key:', error)
    seedTrackKey.value = null
    seedTrackMode.value = null
  }
}

// REPLACE THE EXISTING analyzeTrack function around line 318 in MusicDiscoveryScreen.vue

const analyzeTrack = async (trackId: string) => {
  try {
    // Add timeout to prevent hanging requests
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 5000) // 5 second timeout

    const response: ApiResponse<{ key: number }> = await http.get(
      `music-discovery/track-features/${trackId}`,
      { signal: controller.signal },
    )

    clearTimeout(timeoutId)

    if (response.success && response.data) {
      return response.data.key
    }
  } catch (error: any) {
    if (error.name === 'AbortError') {
      console.log('Track analysis timed out:', trackId)
    } else {
      console.log('Could not analyze track:', error)
    }
  }
  return null
}

const buildRequestParameters = () => {
  const requestParameters: any = {}

  if (enabledParameters.value.tempo) {
    requestParameters.bpm_min = parameters.value.bpm_min
    requestParameters.bpm_max = parameters.value.bpm_max
  }
  if (enabledParameters.value.popularity) {
    requestParameters.popularity = parameters.value.popularity
  }
  if (enabledParameters.value.danceability) {
    requestParameters.danceability = parameters.value.danceability
  }
  if (enabledParameters.value.energy) {
    requestParameters.energy = parameters.value.energy
  }
  if (enabledParameters.value.valence) {
    requestParameters.valence = parameters.value.valence
  }
  if (enabledParameters.value.acousticness) {
    requestParameters.acousticness = parameters.value.acousticness
  }
  if (enabledParameters.value.instrumentalness) {
    requestParameters.instrumentalness = parameters.value.instrumentalness
  }
  if (enabledParameters.value.liveness) {
    requestParameters.liveness = parameters.value.liveness
  }
  if (enabledParameters.value.speechiness) {
    requestParameters.speechiness = parameters.value.speechiness
  }
  if (enabledParameters.value.duration) {
    requestParameters.duration_ms = parameters.value.duration_ms
  }
  if (enabledParameters.value.key_compatibility) {
    requestParameters.key_compatibility = parameters.value.key_compatibility
  }

  return requestParameters
}

const onTrackSelected = async (track: Track) => {
  selectedSeedTrack.value = track
  recommendations.value = []
  allRecommendations.value = []
  displayedCount.value = 0
  hasMoreToLoad.value = true
  errorMessage.value = ''
  keyAnalysisResults.value = []
  currentProvider.value = ''

  // Get seed track key analysis
  await getSeedTrackKey(track.id)
}

const analyzeRecommendationKeysBatch = async (tracks: Track[]) => {
  try {
    const trackIds = tracks.map(track => track.id)

    const response: ApiResponse<Record<string, any>> = await http.post('music-discovery/batch-track-features', {
      track_ids: trackIds,
    })

    if (response.success && response.data) {
      keyAnalysisResults.value = Object.entries(response.data).map(([trackId, features]) => {
        const track = tracks.find(t => t.id === trackId)
        return {
          id: trackId,
          name: track?.name || 'Unknown',
          key: features.key,
        }
      }).filter(result => result.key !== null)

      console.log('🔍 Batch key analysis complete:', keyAnalysisResults.value)
    }
  } catch (error: any) {
    console.log('Batch key analysis failed:', error)
  }
}

// SoundStats discovery
const discoverMusicSoundStats = async () => {
  if (!selectedSeedTrack.value || !hasEnabledParameters.value) {
    return
  }

  try {
    isDiscovering.value = true
    errorMessage.value = ''
    currentProvider.value = 'SoundStats'

    const requestParameters = buildRequestParameters()

    const response: ApiResponse<Track[]> = await http.post('music-discovery/discover', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: 50,
    })

    if (response.success && Array.isArray(response.data)) {
      const tracks = response.data || []

      allRecommendations.value = tracks
      recommendations.value = tracks.slice(0, INITIAL_LOAD)
      displayedCount.value = Math.min(INITIAL_LOAD, tracks.length)
      hasMoreToLoad.value = tracks.length > INITIAL_LOAD

      console.log(`✅ SoundStats found ${tracks.length} recommendations`)

      // IMPORTANT: Don't block the UI - analyze keys in background
      // This will complete after the UI updates
      setTimeout(() => {
        analyzeRecommendationKeys(tracks.slice(0, 12))
      }, 0)
    } else {
      throw new Error('Invalid response from SoundStats')
    }
  } catch (error: any) {
    console.error('SoundStats discovery failed:', error)
    errorMessage.value = error.response?.data?.error || error.message || 'SoundStats discovery failed'
    recommendations.value = []
    allRecommendations.value = []
  } finally {
    // Set this immediately after getting recommendations, not after key analysis
    isDiscovering.value = false
  }
}

// ReccoBeats discovery - SIMPLIFIED FOR TESTING
const discoverMusicReccoBeats = async () => {
  if (!selectedSeedTrack.value) {
    return
  }

  try {
    isDiscovering.value = true
    errorMessage.value = ''
    currentProvider.value = 'ReccoBeats'

    console.log('Testing ReccoBeats with track:', selectedSeedTrack.value)

    const reccoBeatsParams: any = {
      seed_track_id: selectedSeedTrack.value.id,
      limit: 20,
    }

    // Add enabled parameters in ReccoBeats format
    if (enabledParameters.value.tempo) {
      reccoBeatsParams.tempo = Math.round((parameters.value.bpm_min + parameters.value.bpm_max) / 2)
    }
    if (enabledParameters.value.popularity) {
      reccoBeatsParams.popularity = parameters.value.popularity
    }
    if (enabledParameters.value.danceability) {
      reccoBeatsParams.danceability = parameters.value.danceability
    }
    if (enabledParameters.value.energy) {
      reccoBeatsParams.energy = parameters.value.energy
    }
    if (enabledParameters.value.valence) {
      reccoBeatsParams.valence = parameters.value.valence
    }
    if (enabledParameters.value.acousticness) {
      reccoBeatsParams.acousticness = parameters.value.acousticness
    }
    if (enabledParameters.value.instrumentalness) {
      reccoBeatsParams.instrumentalness = parameters.value.instrumentalness
    }
    if (enabledParameters.value.liveness) {
      reccoBeatsParams.liveness = parameters.value.liveness
    }
    if (enabledParameters.value.speechiness) {
      reccoBeatsParams.speechiness = parameters.value.speechiness
    }

    // Add key parameter based on selection
    if (enabledParameters.value.key_selection) {
      switch (selectedKeyMode.value) {
        case 'same':
          if (seedTrackKey.value !== null && seedTrackKey.value !== -1) {
            reccoBeatsParams.key = seedTrackKey.value
            console.log('🎹 ReccoBeats: Using same key:', keyNames[seedTrackKey.value])
          }
          break
        case 'compatible':
          if (seedTrackKey.value !== null && seedTrackKey.value !== -1) {
            const compatibleKeys = getCompatibleKeys(seedTrackKey.value)
            reccoBeatsParams.key = compatibleKeys[1] // Perfect 5th
            console.log('🎹 ReccoBeats: Using compatible key:', keyNames[compatibleKeys[1]])
          }
          break
        case 'custom':
          if (customKey.value !== -1) {
            reccoBeatsParams.key = customKey.value
            console.log('🎹 ReccoBeats: Using custom key:', keyNames[customKey.value])
          }
          break
      }
    }

    console.log('🎵 Testing ReccoBeats with params:', reccoBeatsParams)

    const response = await http.post('music-discovery/discover-reccobeats', reccoBeatsParams)

    // FIXED: Check for success property correctly
    if (response.success && Array.isArray(response.data)) {
      const tracks = response.data || []

      allRecommendations.value = tracks
      recommendations.value = tracks.slice(0, INITIAL_LOAD)
      displayedCount.value = Math.min(INITIAL_LOAD, tracks.length)
      hasMoreToLoad.value = tracks.length > INITIAL_LOAD

      console.log(`✅ ReccoBeats found ${tracks.length} recommendations`)

      // Skip key analysis for now to avoid 404 errors
      // await analyzeRecommendationKeys(tracks.slice(0, 12))
    } else {
      throw new Error('Invalid response format from ReccoBeats')
    }
  } catch (error: any) {
    console.error('ReccoBeats discovery failed:', error)
    errorMessage.value = error.response?.data?.error || error.message || 'ReccoBeats discovery failed'
    recommendations.value = []
    allRecommendations.value = []
  } finally {
    isDiscovering.value = false
  }
}

// RapidAPI discovery - NEW
const discoverMusicRapidApi = async () => {
  if (!selectedSeedTrack.value) {
    return
  }

  try {
    isDiscovering.value = true
    errorMessage.value = ''
    currentProvider.value = 'RapidAPI'

    console.log('🚀 Testing RapidAPI with track:', selectedSeedTrack.value)

    const response = await http.post('music-discovery/discover-rapidapi', {
      seed_track_uri: selectedSeedTrack.value.uri || `spotify:track:${selectedSeedTrack.value.id}`,
      max_popularity: parameters.value.popularity,
      apply_popularity_filter: enabledParameters.value.popularity, // Only filter if enabled
      limit: 50,
      offset: 0, // Always start fresh
      _cache_bust: Date.now() // Prevent caching for dynamic results
    })

    if (response.success && Array.isArray(response.data)) {
      const tracks = response.data || []

      allRecommendations.value = tracks
      recommendations.value = tracks.slice(0, INITIAL_LOAD)
      displayedCount.value = Math.min(INITIAL_LOAD, tracks.length)
      hasMoreToLoad.value = tracks.length > INITIAL_LOAD

      console.log(`✅ RapidAPI found ${tracks.length} recommendations`)
      console.log('📊 RapidAPI stats:', {
        total_found: response.total_found,
        after_filtering: response.after_filtering,
        playlist_id: response.playlist_id
      })

      // Analyze keys in background
      setTimeout(() => {
        analyzeRecommendationKeys(tracks.slice(0, 12))
      }, 0)
    } else {
      throw new Error('Invalid response format from RapidAPI')
    }
  } catch (error) {
    console.error('RapidAPI discovery failed:', error)
    errorMessage.value = error.response?.data?.error || error.message || 'RapidAPI discovery failed'
    recommendations.value = []
    allRecommendations.value = []
  } finally {
    isDiscovering.value = false
  }
}

const analyzeRecommendationKeys = async (tracks: Track[]) => {
  keyAnalysisResults.value = []

  // Run all API calls in parallel instead of sequential
  const keyPromises = tracks.map(async track => {
    try {
      const key = await analyzeTrack(track.id)
      return key !== null ? { id: track.id, name: track.name, key } : null
    } catch (error) {
      console.log(`Could not analyze track ${track.id}:`, error)
      return null
    }
  })

  // Wait for all requests to complete
  const results = await Promise.all(keyPromises)
  keyAnalysisResults.value = results.filter(result => result !== null)

  console.log('🔍 Key analysis complete:', keyAnalysisResults.value)
}

const loadMoreRecommendations = async () => {
  if (!hasMoreToLoad.value || isLoadingMore.value) {
    return
  }

  isLoadingMore.value = true

  const startIndex = displayedCount.value
  const endIndex = Math.min(startIndex + LOAD_MORE_BATCH, allRecommendations.value.length)

  // Check if we've shown all current tracks
  if (startIndex >= allRecommendations.value.length) {
    // For RapidAPI, get fresh radio playlist
    if (currentProvider.value === 'RapidAPI') {
      await loadMoreRapidApiRecommendations()
    } else {
      hasMoreToLoad.value = false
    }
  } else {
    // Show more tracks from current batch
    const newTracks = allRecommendations.value.slice(startIndex, endIndex)
    recommendations.value.push(...newTracks)
    displayedCount.value = endIndex
    hasMoreToLoad.value = endIndex < allRecommendations.value.length || currentProvider.value === 'RapidAPI'
  }

  isLoadingMore.value = false
}

const loadMoreRapidApiRecommendations = async () => {
  if (!selectedSeedTrack.value) {
    return
  }

  try {
    console.log('🔄 Getting fresh RapidAPI radio playlist...')
    
    const response = await http.post('music-discovery/discover-rapidapi', {
      seed_track_uri: selectedSeedTrack.value.uri || `spotify:track:${selectedSeedTrack.value.id}`,
      max_popularity: parameters.value.popularity,
      apply_popularity_filter: enabledParameters.value.popularity,
      limit: 50,
      offset: 0, // Fresh start
      _cache_bust: Date.now()
    })

    if (response.success && Array.isArray(response.data)) {
      const newTracks = response.data || []
      
      if (newTracks.length > 0) {
        // Add new tracks to the pool
        allRecommendations.value.push(...newTracks)
        
        // Show first batch from new tracks
        const tracksToShow = newTracks.slice(0, LOAD_MORE_BATCH)
        recommendations.value.push(...tracksToShow)
        displayedCount.value += tracksToShow.length
        
        console.log(`✅ RapidAPI loaded ${newTracks.length} fresh recommendations`)
      } else {
        hasMoreToLoad.value = false
        console.log('📭 No more RapidAPI tracks available')
      }
    }
  } catch (error) {
    console.error('Failed to load more RapidAPI recommendations:', error)
    hasMoreToLoad.value = false
  }
}
</script>

<style scoped>
.music-discovery-screen {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

@media (max-width: 768px) {
  .music-discovery-screen {
    padding: 0 0.5rem;
  }
}
</style>
