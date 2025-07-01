<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>
        Discover Music
        <template #meta>
          <span class="text-k-text-secondary text-lg">Find new music based on your preferences</span>
        </template>
      </ScreenHeader>
    </template>

    <div class="music-discovery-screen">
      <!-- Seed Track Selection Component -->
      <SeedTrackSelection
        v-model:selected-track="selectedSeedTrack"
        @track-selected="onTrackSelected"
      />

      <!-- Parameter Controls Component -->
      <ParameterControls
        v-if="selectedSeedTrack"
        v-model:parameters="parameters"
        v-model:enabled-parameters="enabledParameters"
        :has-enabled-parameters="hasEnabledParameters"
        @discover="discoverMusic"
        :is-discovering="isDiscovering"
      />

      <!-- Recommendations List Component -->
      <RecommendationsList
        v-if="selectedSeedTrack"
        :recommendations="recommendations"
        :displayed-count="displayedCount"
        :has-more-to-load="hasMoreToLoad"
        :is-discovering="isDiscovering"
        :is-loading-more="isLoadingMore"
        :error-message="errorMessage"
        @clear-error="errorMessage = ''"
        @load-more="loadMoreRecommendations"
      />
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

// Types
interface Track {
  id: string
  name: string
  artist: string
  album: string
  preview_url?: string
  external_url?: string
  image?: string
  duration_ms?: number
}

interface Parameters {
  bpm_min: number
  bmp_min: number
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
}

// State - SIMPLIFIED
const selectedSeedTrack = ref<Track | null>(null)
const recommendations = ref<Track[]>([])
const allRecommendations = ref<Track[]>([])
const displayedCount = ref(0)
const isDiscovering = ref(false)
const isLoadingMore = ref(false)
const errorMessage = ref('')
const hasMoreToLoad = ref(true) // Always allow loading more initially
const currentOffset = ref(0)

const INITIAL_LOAD = 10
const LOAD_MORE_BATCH = 10

// Parameters state
const parameters = ref<Parameters>({
  bpm_min: 100,
  bmp_min: 100, // Keep typo for compatibility
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
})

// Computed
const hasEnabledParameters = computed(() => {
  return Object.values(enabledParameters.value).some(enabled => enabled)
})

// Methods
const onTrackSelected = (track: Track) => {
  selectedSeedTrack.value = track
  recommendations.value = []
  allRecommendations.value = []
  displayedCount.value = 0
  hasMoreToLoad.value = true
  currentOffset.value = 0
  errorMessage.value = ''
}

const loadMoreRecommendations = async () => {
  if (isLoadingMore.value) return
  
  isLoadingMore.value = true
  
  try {
    // Fetch next batch immediately when user clicks
    await fetchMoreRecommendations()
    
    // Now show all songs we have (previous + new)
    recommendations.value = [...allRecommendations.value]
    displayedCount.value = allRecommendations.value.length
    
  } catch (error) {
    console.error('Load more error:', error)
    errorMessage.value = 'Failed to load more recommendations.'
  } finally {
    isLoadingMore.value = false
  }
}

// Remove all the background prefetching functions - we don't need them
// const startBackgroundPrefetch = async () => { ... } - REMOVED

const fetchMoreRecommendations = async () => {
  if (!selectedSeedTrack.value) return

  // Build request parameters (same logic as discoverMusic)
  const requestParameters: any = {}
  
  if (enabledParameters.value.tempo) {
    requestParameters.tempo = Math.round((parameters.value.bpm_min + parameters.value.bmp_max) / 2)
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

  console.log(`🔄 Fetching more recommendations (offset: ${currentOffset.value})`)

  const response = await http.post('music-discovery/discover', {
    seed_track_id: selectedSeedTrack.value.id,
    seed_track_name: selectedSeedTrack.value.name,
    seed_track_artist: selectedSeedTrack.value.artist,
    parameters: requestParameters,
    limit: LOAD_MORE_BATCH,
    offset: currentOffset.value
  })

  if (response.success) {
    const recs = response.data.recommendations
    const newRecommendations = (Array.isArray(recs) ? recs : Object.values(recs)).map(track => ({
      ...track,
      image: track.album_image || track.image
    }))
    
    // Add to our cache
    allRecommendations.value.push(...newRecommendations)
    
    // Update displayed
    recommendations.value = allRecommendations.value.slice(0, displayedCount.value + LOAD_MORE_BATCH)
    displayedCount.value = recommendations.value.length
    currentOffset.value += LOAD_MORE_BATCH
    
    // Check if there might be more
    hasMoreToLoad.value = newRecommendations.length >= LOAD_MORE_BATCH
    
    console.log(`✅ Loaded ${newRecommendations.length} more recommendations`)
  } else {
    hasMoreToLoad.value = false
    console.error('❌ Load more API returned success: false', response)
  }
}

const discoverMusic = async () => {
  if (!selectedSeedTrack.value || !hasEnabledParameters.value) {
    return
  }

  isDiscovering.value = true
  errorMessage.value = ''

  try {
    // Only include enabled parameters
    const requestParameters: any = {}

    if (enabledParameters.value.tempo) {
      requestParameters.tempo = Math.round((parameters.value.bpm_min + parameters.value.bpm_max) / 2)
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

    console.log('🎵 Sending parameters to API:', requestParameters)
    console.log('🌐 Full request payload:', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: INITIAL_LOAD, // Only fetch 10 initially for speed!
    })

    const response = await http.post('music-discovery/discover', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: INITIAL_LOAD, // Start with just 10 songs for instant results
    })

    console.log('🎯 Discovery API response:', response)

    if (response.success) {
      const recs = response.data.recommendations
      // Convert object to array if needed and map album_image to image
      const rawRecommendations = Array.isArray(recs) ? recs : Object.values(recs)
      const mappedRecommendations = rawRecommendations.map(track => ({
        ...track,
        image: track.album_image || track.image
      }))
      
      // Store and display initial batch
      allRecommendations.value = mappedRecommendations
      recommendations.value = mappedRecommendations
      displayedCount.value = mappedRecommendations.length
      currentOffset.value = INITIAL_LOAD
      
      // Set up for potential "Load More"
      hasMoreToLoad.value = mappedRecommendations.length >= INITIAL_LOAD
      
      console.log('✅ Final recommendations:', allRecommendations.value)
      console.log(`🚀 FAST: Showing ${mappedRecommendations.length} recommendations instantly!`)

      if (mappedRecommendations.length === 0) {
        errorMessage.value = 'No recommendations found with these parameters. Try adjusting your settings.'
        hasMoreToLoad.value = false
      }
      // NO AUTOMATIC BACKGROUND FETCHING
    } else {
      console.error('❌ Discovery API returned success: false', response)
      errorMessage.value = 'Failed to get recommendations. Please try again.'
    }
  } catch (error) {
    console.error('💥 Discovery error:', error)
    errorMessage.value = 'Failed to get recommendations. Please check your connection.'
  } finally {
    isDiscovering.value = false
  }
}
</script>

<style scoped>
.music-discovery-screen {
  padding: 1.5rem;
}
</style>