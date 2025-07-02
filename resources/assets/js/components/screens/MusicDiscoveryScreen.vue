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
      <SeedTrackSelection
        v-model:selected-track="selectedSeedTrack"
        @track-selected="onTrackSelected"
      />

      <ParameterControls
        v-if="selectedSeedTrack"
        v-model:parameters="parameters"
        v-model:enabled-parameters="enabledParameters"
        :has-enabled-parameters="hasEnabledParameters"
        @discover="discoverMusic"
        :is-discovering="isDiscovering"
      />

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

const selectedSeedTrack = ref<Track | null>(null)
const recommendations = ref<Track[]>([])
const allRecommendations = ref<Track[]>([])
const prefetchedRecommendations = ref<Track[]>([])
const displayedCount = ref(0)
const isDiscovering = ref(false)
const isLoadingMore = ref(false)
const isPrefetching = ref(false)
const errorMessage = ref('')
const hasMoreToLoad = ref(true)
const currentOffset = ref(0)

const INITIAL_LOAD = 10
const LOAD_MORE_BATCH = 10

const parameters = ref<Parameters>({
  bpm_min: 100,
  bmp_min: 100,
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

const hasEnabledParameters = computed(() => {
  return Object.values(enabledParameters.value).some(enabled => enabled)
})

const buildRequestParameters = () => {
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

  return requestParameters
}

const startBackgroundPrefetch = async () => {
  if (!selectedSeedTrack.value || isPrefetching.value) {
    return
  }

  isPrefetching.value = true
  
  try {
    console.log('🔄 Starting background prefetch for next batch...')
    
    const requestParameters = buildRequestParameters()
    
    const response = await http.silently.post('music-discovery/discover', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: LOAD_MORE_BATCH,
      offset: currentOffset.value
    })

    if (response.success) {
      const recs = response.data.recommendations
      const rawRecommendations = Array.isArray(recs) ? recs : Object.values(recs)
      const mappedRecommendations = rawRecommendations.map(track => ({
        ...track,
        image: track.album_image || track.image
      }))
      
      prefetchedRecommendations.value = mappedRecommendations
      
      console.log(`✅ Background prefetched ${mappedRecommendations.length} recommendations`)
      
      if (mappedRecommendations.length >= LOAD_MORE_BATCH) {
        hasMoreToLoad.value = true
      } else {
        hasMoreToLoad.value = false
      }
    } else {
      console.warn('❌ Background prefetch failed')
      hasMoreToLoad.value = false
    }
  } catch (error) {
    console.error('💥 Background prefetch error:', error)
  } finally {
    isPrefetching.value = false
  }
}

const onTrackSelected = (track: Track) => {
  selectedSeedTrack.value = track
  recommendations.value = []
  allRecommendations.value = []
  prefetchedRecommendations.value = []
  displayedCount.value = 0
  hasMoreToLoad.value = true
  currentOffset.value = 0
  errorMessage.value = ''
}

const loadMoreRecommendations = async () => {
  if (isLoadingMore.value) return
  
  isLoadingMore.value = true
  
  try {
    if (prefetchedRecommendations.value.length > 0) {
      console.log('⚡ Using prefetched recommendations for instant load!')
      
      allRecommendations.value.push(...prefetchedRecommendations.value)
      
      recommendations.value = [...allRecommendations.value]
      displayedCount.value = allRecommendations.value.length
      currentOffset.value += LOAD_MORE_BATCH
      
      const usedPrefetch = [...prefetchedRecommendations.value]
      prefetchedRecommendations.value = []
      
      setTimeout(() => {
        if (usedPrefetch.length >= LOAD_MORE_BATCH) {
          startBackgroundPrefetch()
        }
      }, 100)
      
    } else {
      console.log('🔄 No prefetched data, fetching normally...')
      await fetchMoreRecommendations()
    }
    
  } catch (error) {
    console.error('Load more error:', error)
    errorMessage.value = 'Failed to load more recommendations.'
  } finally {
    isLoadingMore.value = false
  }
}

const fetchMoreRecommendations = async () => {
  if (!selectedSeedTrack.value) return

  const requestParameters = buildRequestParameters()

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
    
    allRecommendations.value.push(...newRecommendations)
    
    recommendations.value = [...allRecommendations.value]
    displayedCount.value = allRecommendations.value.length
    currentOffset.value += LOAD_MORE_BATCH
    
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
  prefetchedRecommendations.value = []

  try {
    const requestParameters = buildRequestParameters()

    console.log('🎵 Sending parameters to API:', requestParameters)
    console.log('🌐 Full request payload:', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: INITIAL_LOAD,
    })

    const response = await http.post('music-discovery/discover', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: INITIAL_LOAD,
    })

    console.log('🎯 Discovery API response:', response)

    if (response.success) {
      const recs = response.data.recommendations
      const rawRecommendations = Array.isArray(recs) ? recs : Object.values(recs)
      const mappedRecommendations = rawRecommendations.map(track => ({
        ...track,
        image: track.album_image || track.image
      }))
      
      allRecommendations.value = mappedRecommendations
      recommendations.value = mappedRecommendations
      displayedCount.value = mappedRecommendations.length
      currentOffset.value = INITIAL_LOAD
      
      hasMoreToLoad.value = mappedRecommendations.length >= INITIAL_LOAD
      
      console.log('✅ Final recommendations:', allRecommendations.value)
      console.log(`🚀 FAST: Showing ${mappedRecommendations.length} recommendations instantly!`)

      if (mappedRecommendations.length === 0) {
        errorMessage.value = 'No recommendations found with these parameters. Try adjusting your settings.'
        hasMoreToLoad.value = false
      } else {
        setTimeout(() => {
          startBackgroundPrefetch()
        }, 500)
      }
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