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
      <!-- Seed Track Selection -->
      <div class="seed-selection mb-8">
        <div class="search-container mb-4">
          <div class="flex items-center gap-3">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search for a seed track..."
              class="search-input flex-1 px-4 py-3 bg-k-bg-secondary border border-k-border rounded-lg text-k-text-primary placeholder-k-text-secondary focus:border-k-accent focus:outline-none"
              @input="onSearchInput"
              @keypress.enter="searchTracks"
            >
            <Btn
              :disabled="!searchQuery.trim() || isSearching"
              green
              class="shrink-0"
              @click="searchTracks"
            >
              {{ isSearching ? 'Searching...' : 'Search' }}
            </Btn>
          </div>
        </div>

        <!-- Search Results -->
        <div v-if="searchResults.length > 0" class="search-results mb-4">
          <h3 class="text-lg font-medium text-k-text-primary mb-3">Select a seed track ({{ searchResults.length }} found):</h3>
          <div class="results-list max-h-60 overflow-y-auto space-y-2 border border-k-border rounded-lg bg-k-bg-secondary p-2">
            <div
              v-for="track in searchResults"
              :key="track.id"
              class="result-item flex items-center p-3 bg-k-bg-tertiary hover:bg-k-bg-primary cursor-pointer rounded-lg transition-colors border border-k-border"
              @click="selectSeedTrack(track)"
            >
              <img
                v-if="track.album_image"
                :src="track.album_image"
                :alt="track.album"
                class="w-12 h-12 rounded mr-3"
              >
              <div class="flex-1">
                <div class="text-k-text-primary font-medium">{{ track.name }}</div>
                <div class="text-k-text-secondary text-sm">{{ track.artist }} • {{ track.album }}</div>
              </div>
              <div class="text-k-text-secondary text-sm">{{ track.duration }}</div>
            </div>
          </div>
        </div>

        <!-- Selected Seed Track -->
        <div v-if="selectedSeedTrack" class="selected-seed">
          <div class="seed-track-display flex items-center justify-between p-4 bg-k-bg-secondary rounded-lg border-2 border-k-accent">
            <div class="flex items-center">
              <span class="text-k-accent font-medium mr-2">Seed Track:</span>
              <span class="text-k-text-primary">{{ selectedSeedTrack.name }} - {{ selectedSeedTrack.artist }}</span>
            </div>
            <Btn
              class="!p-2 !min-w-0 !w-8 !h-8 flex items-center justify-center"
              small
              orange
              @click="clearSeedTrack"
            >
              <span class="text-white font-bold text-lg leading-none">✕</span>
            </Btn>
          </div>
        </div>
      </div>

      <!-- Parameters Section -->
      <div v-if="selectedSeedTrack" class="parameters-section mb-8">
        <div class="parameters-container p-6 bg-k-bg-secondary rounded-lg border border-k-border">
          <h3 class="text-lg font-medium text-k-text-primary mb-6">Discovery Parameters</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- BPM/Tempo Range -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">BPM Range</label>
                <CheckBox v-model="enabledParameters.tempo" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.tempo }">
                <div class="space-y-4">
                  <!-- Combined BPM Range Slider -->
                  <div>
                    <label class="text-xs text-k-text-secondary mb-2 block">BPM: {{ parameters.bpm_min }} - {{ parameters.bpm_max }}</label>
                    <div class="bpm-range-container relative">
                      <input
                        v-model.number="parameters.bpm_min"
                        :disabled="!enabledParameters.tempo"
                        type="range"
                        min="60"
                        max="200"
                        class="param-range absolute w-full"
                        style="z-index: 1;"
                      >
                      <input
                        v-model.number="parameters.bpm_max"
                        :disabled="!enabledParameters.tempo"
                        type="range"
                        min="60"
                        max="200"
                        class="param-range absolute w-full"
                        style="z-index: 2;"
                      >
                    </div>
                    <div class="flex justify-between items-center mt-2">
                      <input
                        v-model.number="parameters.bpm_min"
                        :disabled="!enabledParameters.tempo"
                        type="number"
                        min="60"
                        max="200"
                        class="param-input w-16 text-xs"
                      >
                      <span class="text-xs text-k-text-secondary">Average: {{ Math.round((parameters.bpm_min + parameters.bpm_max) / 2) }}</span>
                      <input
                        v-model.number="parameters.bpm_max"
                        :disabled="!enabledParameters.tempo"
                        type="number"
                        min="60"
                        max="200"
                        class="param-input w-16 text-xs"
                      >
                    </div>
                  </div>
                </div>
                <div class="text-xs text-k-text-secondary text-center mt-2 p-2 bg-k-bg-primary rounded">
                  <strong>Note:</strong> SoundStats API uses average BPM ({{ Math.round((parameters.bpm_min + parameters.bpm_max) / 2) }}) - this is how their algorithm works
                </div>
              </div>
            </div>

            <!-- Popularity -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Popularity</label>
                <CheckBox v-model="enabledParameters.popularity" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.popularity }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.popularity"
                    :disabled="!enabledParameters.popularity"
                    type="range"
                    min="0"
                    max="100"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.popularity }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">0 (Niche) - 100 (Mainstream)</div>
              </div>
            </div>

            <!-- Danceability -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Danceability</label>
                <CheckBox v-model="enabledParameters.danceability" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.danceability }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.danceability"
                    :disabled="!enabledParameters.danceability"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.danceability }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">How suitable for dancing</div>
              </div>
            </div>

            <!-- Energy -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Energy</label>
                <CheckBox v-model="enabledParameters.energy" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.energy }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.energy"
                    :disabled="!enabledParameters.energy"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.energy }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">Intensity and activity level</div>
              </div>
            </div>

            <!-- Valence (Mood) -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Valence (Mood)</label>
                <CheckBox v-model="enabledParameters.valence" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.valence }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.valence"
                    :disabled="!enabledParameters.valence"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.valence }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">0 (Sad) - 1 (Happy)</div>
              </div>
            </div>

            <!-- Acousticness -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Acousticness</label>
                <CheckBox v-model="enabledParameters.acousticness" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.acousticness }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.acousticness"
                    :disabled="!enabledParameters.acousticness"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.acousticness }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">Acoustic vs Electronic</div>
              </div>
            </div>

            <!-- Instrumentalness -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Instrumentalness</label>
                <CheckBox v-model="enabledParameters.instrumentalness" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.instrumentalness }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.instrumentalness"
                    :disabled="!enabledParameters.instrumentalness"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.instrumentalness }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">No vocals vs Vocals</div>
              </div>
            </div>

            <!-- Liveness -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Liveness</label>
                <CheckBox v-model="enabledParameters.liveness" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.liveness }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.liveness"
                    :disabled="!enabledParameters.liveness"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.liveness }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">Studio vs Live performance</div>
              </div>
            </div>

            <!-- Speechiness -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Speechiness</label>
                <CheckBox v-model="enabledParameters.speechiness" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.speechiness }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.speechiness"
                    :disabled="!enabledParameters.speechiness"
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    class="param-range flex-1"
                  >
                  <span class="w-12 text-k-text-primary text-center font-medium">{{ parameters.speechiness }}</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">Music vs Speech/Rap</div>
              </div>
            </div>

            <!-- Duration -->
            <div class="parameter-card">
              <div class="flex items-center justify-between mb-3">
                <label class="text-k-accent font-medium">Duration</label>
                <CheckBox v-model="enabledParameters.duration" />
              </div>
              <div class="parameter-content" :class="{ 'opacity-30': !enabledParameters.duration }">
                <div class="flex items-center space-x-4 mb-2">
                  <input
                    v-model.number="parameters.duration_ms"
                    :disabled="!enabledParameters.duration"
                    type="range"
                    min="30000"
                    max="600000"
                    step="10000"
                    class="param-range flex-1"
                  >
                  <span class="w-16 text-k-text-primary text-center font-medium">{{ Math.round(parameters.duration_ms / 1000 / 60 * 10) / 10 }}m</span>
                </div>
                <div class="text-xs text-k-text-secondary text-center">0.5min - 10min</div>
              </div>
            </div>
          </div>

          <!-- Discover Button -->
          <div class="mt-8 text-center">
            <Btn
              :disabled="isDiscovering || !hasEnabledParameters"
              green
              class="px-8 py-3 bg-green-600 hover:bg-green-700 border border-green-500 relative"
              @click="discoverMusic"
            >
              <span v-if="!isDiscovering">Find Similar Tracks</span>
              <div v-else class="flex items-center justify-center">
                <div class="loading-spinner mr-2" />
                <span>Finding recommendations...</span>
              </div>
            </Btn>
          </div>
        </div>
      </div>

      <!-- Results Section -->
      <div v-if="recommendations.length > 0" class="results-section">
        <h2 class="text-2xl font-bold text-k-text-primary mb-6">Recommendations</h2>

        <!-- Modern Card Grid Layout -->
        <div class="recommendations-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div
            v-for="(track, index) in recommendations"
            :key="track.id"
            class="recommendation-card group cursor-pointer bg-k-bg-secondary rounded-xl p-4 border border-k-border hover:border-k-accent transition-all duration-300 hover:shadow-lg hover:shadow-k-accent/20 hover:-translate-y-1"
            @click="playPreview(track)"
          >
            <!-- Album Art -->
            <div class="relative mb-4">
              <img
                v-if="track.album_image"
                :src="track.album_image"
                :alt="track.album"
                class="w-full aspect-square rounded-lg object-cover"
              >
              <div v-else class="w-full aspect-square rounded-lg bg-k-bg-tertiary flex items-center justify-center">
                <span class="text-k-text-secondary text-3xl">🎵</span>
              </div>

              <!-- Play Button Overlay -->
              <div class="absolute inset-0 bg-black/50 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                <div class="w-12 h-12 bg-k-accent rounded-full flex items-center justify-center">
                  <span class="text-white text-xl">▶</span>
                </div>
              </div>

              <!-- Track Number -->
              <div class="absolute top-2 left-2 bg-black/70 text-white text-xs px-2 py-1 rounded-full">
                #{{ index + 1 }}
              </div>

              <!-- Preview Indicator -->
              <div v-if="track.preview_url" class="absolute top-2 right-2 bg-k-accent text-white text-xs px-2 py-1 rounded-full">
                Preview
              </div>
            </div>

            <!-- Track Info -->
            <div class="space-y-2">
              <h3 class="font-semibold text-k-text-primary line-clamp-2 group-hover:text-k-accent transition-colors duration-300">
                {{ track.name }}
              </h3>
              <p class="text-k-text-secondary text-sm line-clamp-1">
                {{ track.artist }}
              </p>
              <p class="text-k-text-secondary text-xs line-clamp-1 opacity-75">
                {{ track.album }}
              </p>

              <!-- Duration and Actions -->
              <div class="flex items-center justify-between pt-2">
                <span class="text-k-text-secondary text-xs">{{ track.duration }}</span>
                <button
                  v-if="track.external_url"
                  class="text-k-accent hover:text-k-accent-hover text-xs px-2 py-1 rounded hover:bg-k-bg-tertiary transition-colors duration-200"
                  @click.stop="window.open(track.external_url, '_blank')"
                >
                  Spotify
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Error Messages -->
      <div v-if="errorMessage" class="error-message p-4 bg-red-800 text-white rounded-lg mb-4 border border-red-600">
        {{ errorMessage }}
      </div>
    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { http } from '@/services/http'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import Btn from '@/components/ui/form/Btn.vue'
import CheckBox from '@/components/ui/form/CheckBox.vue'

interface Track {
  id: string
  name: string
  artist: string
  artists: string[]
  album: string
  album_image: string | null
  duration: string
  duration_ms: number
  preview_url: string | null
  external_url: string | null
  popularity: number
  release_date: string | null
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
}

// Reactive state
const searchQuery = ref('')
const searchResults = ref<Track[]>([])
const selectedSeedTrack = ref<Track | null>(null)
const recommendations = ref<Track[]>([])
const isSearching = ref(false)
const isDiscovering = ref(false)
const errorMessage = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const parameters = reactive<Parameters>({
  bpm_min: 115,
  bpm_max: 125,
  popularity: 50,
  danceability: 0.5,
  energy: 0.5,
  valence: 0.5,
  acousticness: 0.5,
  instrumentalness: 0.5,
  liveness: 0.2,
  speechiness: 0.1,
  duration_ms: 180000, // 3 minutes in milliseconds
  key_compatibility: false,
})

const enabledParameters = reactive<EnabledParameters>({
  tempo: true,
  popularity: true,
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

// Computed property to check if any parameters are enabled
const hasEnabledParameters = computed(() => {
  return Object.values(enabledParameters).some(enabled => enabled)
})

// Methods - moved before usage
const searchTracks = async () => {
  if (!searchQuery.value.trim()) {
    return
  }

  isSearching.value = true
  errorMessage.value = ''

  try {
    const response = await http.post('music-discovery/search-seed', {
      query: searchQuery.value.trim(),
      limit: 20,
    })

    // eslint-disable-next-line no-console
    console.log('Search response:', response)

    if (response.success) {
      searchResults.value = response.data
      // eslint-disable-next-line no-console
      console.log('Search results:', searchResults.value)
    } else {
      console.error('Search API returned success: false')
      errorMessage.value = 'Search failed. Please try again.'
    }
  } catch (error) {
    console.error('Search error:', error)
    errorMessage.value = 'Search failed. Please check your connection.'
  } finally {
    isSearching.value = false
  }
}

// Update range slider progress
const updateRangeProgress = () => {
  nextTick(() => {
    const ranges = document.querySelectorAll('.param-range')
    ranges.forEach(range => {
      const input = range as HTMLInputElement
      const min = Number.parseFloat(input.min) || 0
      const max = Number.parseFloat(input.max) || 100
      const value = Number.parseFloat(input.value) || 0
      const progress = ((value - min) / (max - min)) * 100
      input.style.setProperty('--range-progress', `${progress}%`)

      // Update background gradient
      input.style.background = `linear-gradient(to right, var(--color-highlight) 0%, var(--color-highlight) ${progress}%, #374151 ${progress}%, #374151 100%)`
    })
  })
}

const onSearchInput = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  // Debounce search
  searchTimeout = setTimeout(() => {
    if (searchQuery.value.trim().length > 2) {
      searchTracks()
    }
  }, 300)
}

const selectSeedTrack = (track: Track) => {
  selectedSeedTrack.value = track
  searchResults.value = []
  searchQuery.value = ''
  recommendations.value = []
}

const clearSeedTrack = () => {
  selectedSeedTrack.value = null
  recommendations.value = []
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

    if (enabledParameters.tempo) {
      // SoundStats API expects 'tempo' as a single value, use average of min/max
      requestParameters.tempo = Math.round((parameters.bpm_min + parameters.bpm_max) / 2)
    }

    if (enabledParameters.popularity) {
      requestParameters.popularity = parameters.popularity
    }

    if (enabledParameters.danceability) {
      requestParameters.danceability = parameters.danceability
    }

    if (enabledParameters.energy) {
      requestParameters.energy = parameters.energy
    }

    if (enabledParameters.valence) {
      requestParameters.valence = parameters.valence
    }

    if (enabledParameters.acousticness) {
      requestParameters.acousticness = parameters.acousticness
    }

    if (enabledParameters.instrumentalness) {
      requestParameters.instrumentalness = parameters.instrumentalness
    }

    if (enabledParameters.liveness) {
      requestParameters.liveness = parameters.liveness
    }

    if (enabledParameters.speechiness) {
      requestParameters.speechiness = parameters.speechiness
    }

    if (enabledParameters.duration) {
      requestParameters.duration_ms = parameters.duration_ms
    }

    if (enabledParameters.key_compatibility) {
      requestParameters.key_compatibility = parameters.key_compatibility
    }

    // eslint-disable-next-line no-console
    console.log('📊 Enabled parameters:', Object.fromEntries(
      Object.entries(enabledParameters).filter(([_key, value]) => value),
    ))
    // eslint-disable-next-line no-console
    console.log('🎵 Sending parameters to API:', requestParameters)
    // eslint-disable-next-line no-console
    console.log('🔄 Parameter count:', Object.keys(requestParameters).length)
    // eslint-disable-next-line no-console
    console.log('🌐 Full request payload:', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: 20,
    })

    // Log the actual values being sent for debugging
    if (Object.keys(requestParameters).length === 0) {
      console.warn('⚠️ No parameters enabled - API will use defaults only')
    }

    const response = await http.post('music-discovery/discover', {
      seed_track_id: selectedSeedTrack.value.id,
      seed_track_name: selectedSeedTrack.value.name,
      seed_track_artist: selectedSeedTrack.value.artist,
      parameters: requestParameters,
      limit: 20,
    })

    // eslint-disable-next-line no-console
    console.log('🎯 Discovery API response:', response)

    if (response.success) {
      const recs = response.data.recommendations
      // Convert object to array if needed
      recommendations.value = Array.isArray(recs) ? recs : Object.values(recs)
      // eslint-disable-next-line no-console
      console.log('✅ Final recommendations:', recommendations.value)

      if (recommendations.value.length === 0) {
        errorMessage.value = 'No recommendations found with these parameters. Try adjusting your settings.'
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

const playPreview = (track: Track) => {
  if (track.preview_url) {
    // Create audio element and play preview
    const audio = new Audio(track.preview_url)
    audio.volume = 0.5
    audio.play().catch(error => {
      console.error('Preview playback failed:', error)
    })

    // Stop after 30 seconds (Spotify preview length)
    setTimeout(() => {
      audio.pause()
    }, 30000)
  } else {
    // If no preview, could open Spotify link
    if (track.external_url) {
      window.open(track.external_url, '_blank')
    }
  }
}

// Watchers to ensure BPM min/max consistency and update sliders
watch(() => parameters.bpm_min, newMin => {
  if (newMin > parameters.bpm_max) {
    parameters.bpm_max = newMin
  }
  updateRangeProgress()
})

watch(() => parameters.bpm_max, newMax => {
  if (newMax < parameters.bmp_min) {
    parameters.bpm_min = newMax
  }
  updateRangeProgress()
})

// Watch all parameters for range updates
watch(() => [
  parameters.popularity,
  parameters.danceability,
  parameters.energy,
  parameters.valence,
  parameters.acousticness,
  parameters.instrumentalness,
  parameters.liveness,
  parameters.speechiness,
  parameters.duration_ms,
], updateRangeProgress)

// Update sliders when component mounts
onMounted(() => {
  updateRangeProgress()
})
</script>

<style scoped>
.music-discovery-screen {
  padding: 1.5rem;
}

.search-input:focus {
  box-shadow: 0 0 0 3px var(--color-highlight);
}

.result-item:hover {
  transform: translateY(-1px);
  transition: all 0.2s ease;
}

/* Parameter Cards */
.parameter-card {
  background: var(--color-bg-tertiary);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 1rem;
  transition: all 0.2s ease;
}

.parameter-content {
  transition: opacity 0.2s ease;
}

.parameter-content.opacity-30 {
  opacity: 0.3;
}

/* Enhanced form inputs for dark background */
.param-input {
  background: var(--color-bg-primary) !important;
  border: 1px solid var(--color-border) !important;
  color: var(--color-text-primary) !important;
  border-radius: 4px;
  padding: 4px 8px;
  text-align: center;
  transition: all 0.2s ease;
}

.param-input:focus {
  border-color: var(--color-highlight) !important;
  box-shadow: 0 0 0 2px rgba(var(--color-highlight-rgb), 0.2) !important;
}

.param-input:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: var(--color-bg-secondary) !important;
}

/* Enhanced range slider styling with proper alignment */
.param-range {
  -webkit-appearance: none;
  height: 6px;
  border-radius: 3px;
  background: #374151;
  outline: none;
  transition: all 0.2s ease;
  position: relative;
}

.param-range:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.param-range::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--color-highlight);
  cursor: pointer;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  border: 2px solid white;
  margin-top: -6px; /* Center the thumb on the track */
  transition: all 0.2s ease;
}

.param-range:disabled::-webkit-slider-thumb {
  background: #6b7280;
  cursor: not-allowed;
}

.param-range::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--color-highlight);
  cursor: pointer;
  border: 2px solid white;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  transition: all 0.2s ease;
}

.param-range:disabled::-moz-range-thumb {
  background: #6b7280;
  cursor: not-allowed;
}

/* Track styling for proper alignment */
.param-range::-webkit-slider-track {
  height: 6px;
  border-radius: 3px;
  background: #374151;
}

.param-range::-moz-range-track {
  height: 6px;
  border-radius: 3px;
  background: #374151;
  border: none;
}

/* BPM Range specific styling */
.bpm-range-container {
  height: 24px;
}

.bpm-range-container .param-range:first-child {
  opacity: 0.7;
}

.bmp-range-container .param-range:last-child {
  opacity: 1;
}

.track-row:hover {
  background-color: var(--color-bg-tertiary);
  transition: background-color 0.2s ease;
}

.track-row:hover .text-k-text-primary {
  color: var(--color-highlight);
  transition: color 0.2s ease;
}

/* Enhanced button styling */
.btn {
  border: 1px solid transparent !important;
}

.btn.green {
  background: #16a34a !important;
  border-color: #15803d !important;
  color: white !important;
}

.btn.green:hover:not(:disabled) {
  background: #15803d !important;
  border-color: #166534 !important;
}

.btn.green:disabled {
  background: #6b7280 !important;
  border-color: #6b7280 !important;
  cursor: not-allowed !important;
}

.btn.orange {
  background: #ea580c !important;
  border-color: #dc2626 !important;
  color: white !important;
}

.btn.orange:hover:not(:disabled) {
  background: #dc2626 !important;
  border-color: #b91c1c !important;
}

/* Modern recommendation cards */
.recommendation-card {
  backdrop-filter: blur(10px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.recommendation-card:hover {
  transform: translateY(-4px);
  box-shadow:
    0 20px 25px -5px rgba(0, 0, 0, 0.1),
    0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.line-clamp-1 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
}

.line-clamp-2 {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.recommendations-grid {
  animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.recommendation-card:nth-child(n) {
  animation: slideInCard 0.5s ease-out forwards;
  animation-delay: calc(var(--card-index, 0) * 0.1s);
}

@keyframes slideInCard {
  from {
    opacity: 0;
    transform: translateY(30px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Enhanced loading spinner */
.loading-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid transparent;
  border-top: 2px solid currentColor;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

/* Responsive design improvements */
@media (max-width: 768px) {
  .parameters-container {
    padding: 1rem;
  }

  .parameter-card {
    padding: 0.75rem;
  }

  .grid.lg\:grid-cols-3 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }

  .grid.md\:grid-cols-2 {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }
}

/* Better checkbox styling for dark theme */
.checkbox-wrapper input[type='checkbox'] {
  background-color: var(--color-bg-primary);
  border: 1px solid var(--color-border);
}

.checkbox-wrapper input[type='checkbox']:checked {
  background-color: var(--color-highlight);
  border-color: var(--color-highlight);
}

/* Enhanced parameter card hover effects */
.parameter-card:hover {
  border-color: var(--color-highlight);
  transition: border-color 0.2s ease;
}

.parameter-card:hover .text-k-accent {
  color: var(--color-highlight);
  transition: color 0.2s ease;
}
</style>
