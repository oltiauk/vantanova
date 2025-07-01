<template>
    <div class="parameter-controls mb-8">
      <!-- Parameters Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-k-accent font-medium text-xl">Discovery Parameters</h3>
        <div class="flex items-center gap-4">
          <span class="text-k-text-secondary text-sm">
            {{ enabledCount }} / {{ totalParameters }} parameters enabled
          </span>
          <Btn
            :disabled="!hasEnabledParameters || isDiscovering"
            green
            size="lg"
            @click="$emit('discover')"
          >
            {{ isDiscovering ? 'Discovering...' : 'Discover Music' }}
          </Btn>
        </div>
      </div>
  
      <!-- Parameters Warning -->
      <div v-if="!hasEnabledParameters" class="parameters-warning mb-6">
        <div class="bg-yellow-900/20 border border-yellow-500/30 rounded-lg p-4">
          <div class="flex items-center">
            <Icon :icon="faExclamationTriangle" class="w-5 h-5 text-yellow-400 mr-3" />
            <div>
              <p class="text-yellow-400 font-medium">No Parameters Enabled</p>
              <p class="text-yellow-300 text-sm">Enable at least one parameter to discover music</p>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Parameters Grid -->
      <div class="parameters-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- BPM Range -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">BPM Range</label>
            <CheckBox v-model="localEnabledParameters.tempo" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.tempo }">
            <div class="space-y-3">
              <div class="flex items-center space-x-4">
                <span class="w-8 text-k-text-secondary text-sm">Min</span>
                <input
                  v-model.number="localParameters.bpm_min"
                  :disabled="!localEnabledParameters.tempo"
                  type="range"
                  min="60"
                  max="200"
                  step="5"
                  class="param-range flex-1"
                >
                <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.bpm_min }}</span>
              </div>
              <div class="flex items-center space-x-4">
                <span class="w-8 text-k-text-secondary text-sm">Max</span>
                <input
                  v-model.number="localParameters.bpm_max"
                  :disabled="!localEnabledParameters.tempo"
                  type="range"
                  min="60"
                  max="200"
                  step="5"
                  class="param-range flex-1"
                >
                <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.bpm_max }}</span>
              </div>
            </div>
            <div class="text-xs text-k-text-secondary text-center mt-2">Beats per minute</div>
          </div>
        </div>
  
        <!-- Popularity -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Popularity</label>
            <CheckBox v-model="localEnabledParameters.popularity" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.popularity }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.popularity"
                :disabled="!localEnabledParameters.popularity"
                type="range"
                min="0"
                max="100"
                step="5"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.popularity }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Mainstream vs Underground</div>
          </div>
        </div>
  
        <!-- Danceability -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Danceability</label>
            <CheckBox v-model="localEnabledParameters.danceability" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.danceability }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.danceability"
                :disabled="!localEnabledParameters.danceability"
                type="range"
                min="0"
                max="1"
                step="0.1"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.danceability }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">How suitable for dancing</div>
          </div>
        </div>
  
        <!-- Energy -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Energy</label>
            <CheckBox v-model="localEnabledParameters.energy" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.energy }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.energy"
                :disabled="!localEnabledParameters.energy"
                type="range"
                min="0"
                max="1"
                step="0.1"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.energy }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Intensity and power</div>
          </div>
        </div>
  
        <!-- Valence -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Valence</label>
            <CheckBox v-model="localEnabledParameters.valence" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.valence }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.valence"
                :disabled="!localEnabledParameters.valence"
                type="range"
                min="0"
                max="1"
                step="0.1"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.valence }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Sad vs Happy mood</div>
          </div>
        </div>
  
        <!-- Acousticness -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Acousticness</label>
            <CheckBox v-model="localEnabledParameters.acousticness" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.acousticness }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.acousticness"
                :disabled="!localEnabledParameters.acousticness"
                type="range"
                min="0"
                max="1"
                step="0.1"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.acousticness }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Acoustic vs Electric</div>
          </div>
        </div>
  
        <!-- Instrumentalness -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Instrumentalness</label>
            <CheckBox v-model="localEnabledParameters.instrumentalness" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.instrumentalness }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.instrumentalness"
                :disabled="!localEnabledParameters.instrumentalness"
                type="range"
                min="0"
                max="1"
                step="0.1"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.instrumentalness }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Vocals vs Instrumental</div>
          </div>
        </div>
  
        <!-- Liveness -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Liveness</label>
            <CheckBox v-model="localEnabledParameters.liveness" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.liveness }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.liveness"
                :disabled="!localEnabledParameters.liveness"
                type="range"
                min="0"
                max="1"
                step="0.1"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.liveness }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Studio vs Live performance</div>
          </div>
        </div>
  
        <!-- Speechiness -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Speechiness</label>
            <CheckBox v-model="localEnabledParameters.speechiness" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.speechiness }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.speechiness"
                :disabled="!localEnabledParameters.speechiness"
                type="range"
                min="0"
                max="1"
                step="0.1"
                class="param-range flex-1"
              >
              <span class="w-12 text-k-text-primary text-center font-medium">{{ localParameters.speechiness }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Music vs Speech/Rap</div>
          </div>
        </div>
  
        <!-- Duration -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Duration</label>
            <CheckBox v-model="localEnabledParameters.duration" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.duration }">
            <div class="flex items-center space-x-4 mb-2">
              <input
                v-model.number="localParameters.duration_ms"
                :disabled="!localEnabledParameters.duration"
                type="range"
                min="30000"
                max="600000"
                step="10000"
                class="param-range flex-1"
              >
              <span class="w-16 text-k-text-primary text-center font-medium text-xs">{{ formatDuration(localParameters.duration_ms) }}</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Track length</div>
          </div>
        </div>
  
        <!-- Key Compatibility -->
        <div class="parameter-card">
          <div class="flex items-center justify-between mb-3">
            <label class="text-k-accent font-medium">Key Compatibility</label>
            <CheckBox v-model="localEnabledParameters.key_compatibility" />
          </div>
          <div class="parameter-content" :class="{ 'opacity-30': !localEnabledParameters.key_compatibility }">
            <div class="flex items-center space-x-4 mb-2">
              <CheckBox 
                v-model="localParameters.key_compatibility"
                :disabled="!localEnabledParameters.key_compatibility"
              />
              <span class="text-k-text-primary">Match musical key for harmonic mixing</span>
            </div>
            <div class="text-xs text-k-text-secondary text-center">Perfect for DJ sets</div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { computed, watch, nextTick, onMounted } from 'vue'
  import { faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'
  
  import Btn from '@/components/ui/form/Btn.vue'
  import CheckBox from '@/components/ui/form/CheckBox.vue'
  
  // Types
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
  
  // Props
  interface Props {
    parameters: Parameters
    enabledParameters: EnabledParameters
    hasEnabledParameters: boolean
    isDiscovering: boolean
  }
  
  const props = defineProps<Props>()
  
  // Emits
  const emit = defineEmits<{
    'update:parameters': [parameters: Parameters]
    'update:enabledParameters': [enabledParameters: EnabledParameters]
    'discover': []
  }>()
  
  // Local reactive copies for v-model
  const localParameters = computed({
    get: () => props.parameters,
    set: (value) => emit('update:parameters', value)
  })
  
  const localEnabledParameters = computed({
    get: () => props.enabledParameters,
    set: (value) => emit('update:enabledParameters', value)
  })
  
  // Computed
  const enabledCount = computed(() => {
    return Object.values(props.enabledParameters).filter(enabled => enabled).length
  })
  
  const totalParameters = computed(() => {
    return Object.keys(props.enabledParameters).length
  })
  
  // Methods
  const formatDuration = (ms: number): string => {
    const minutes = Math.floor(ms / 60000)
    const seconds = Math.floor((ms % 60000) / 1000)
    return `${minutes}:${seconds.toString().padStart(2, '0')}`
  }
  
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
  
  // Watchers to ensure BPM min/max consistency and update sliders
  watch(() => props.parameters.bpm_min, newMin => {
    if (newMin > props.parameters.bpm_max) {
      localParameters.value = { ...localParameters.value, bpm_max: newMin }
    }
    updateRangeProgress()
  })
  
  watch(() => props.parameters.bpm_max, newMax => {
    if (newMax < props.parameters.bmp_min) {
      localParameters.value = { ...localParameters.value, bmp_min: newMax }
    }
    updateRangeProgress()
  })
  
  // Watch all parameters for range updates
  watch(() => [
    props.parameters.popularity,
    props.parameters.danceability,
    props.parameters.energy,
    props.parameters.valence,
    props.parameters.acousticness,
    props.parameters.instrumentalness,
    props.parameters.liveness,
    props.parameters.speechiness,
    props.parameters.duration_ms,
  ], updateRangeProgress)
  
  // Update sliders when component mounts
  onMounted(() => {
    updateRangeProgress()
  })
  </script>
  
  <style scoped>
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
  
  /* Range Input Styling */
  .param-range {
    -webkit-appearance: none;
    appearance: none;
    height: 6px;
    border-radius: 3px;
    outline: none;
    background: #374151;
  }
  
  .param-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--color-highlight);
    cursor: pointer;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }
  
  .param-range::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--color-highlight);
    cursor: pointer;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }
  
  .param-range:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .param-range:disabled::-webkit-slider-thumb {
    cursor: not-allowed;
  }
  
  .param-range:disabled::-moz-range-thumb {
    cursor: not-allowed;
  }
  </style>