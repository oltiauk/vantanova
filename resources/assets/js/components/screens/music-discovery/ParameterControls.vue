<template>
  <div class="parameter-controls">
    <div class="bg-k-bg-secondary border border-k-border rounded-lg p-6 mb-6">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <h3 class="text-k-text-primary text-lg font-medium">Discovery Parameters</h3>
          <span class="text-k-text-tertiary text-sm bg-k-bg-tertiary px-2 py-1 rounded">
            {{ enabledCount }}/{{ totalParameters }} enabled
          </span>
        </div>
      </div>

      <!-- Provider Comparison Info -->
      <div class="mb-6 p-4 bg-purple-900/20 border border-purple-500/30 rounded-lg">
        <h4 class="text-purple-300 font-medium mb-2">🧪 Provider Comparison Test</h4>
        <div class="text-sm text-purple-200 space-y-1">
          <p><strong>SoundStats:</strong> Uses "key_compatibility" boolean - automatically finds compatible keys</p>
          <p><strong>ReccoBeats:</strong> Uses "key" integer (0-11) - you specify exact key to find</p>
          <p class="text-purple-100 mt-2">💡 Enable parameters below and test both providers to compare key handling</p>
        </div>
      </div>

      <!-- Warning when no parameters enabled -->
      <div v-if="!hasEnabledParameters" class="mb-6 p-4 bg-yellow-900/20 border border-yellow-500/30 rounded-lg">
        <div class="flex items-center gap-2 text-yellow-300">
          <Icon :icon="faExclamationTriangle" class="w-4 h-4" />
          <span class="text-sm font-medium">Enable at least one parameter to test the providers</span>
        </div>
      </div>

      <!-- Parameters Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Tempo -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.tempo" />
              <label class="text-k-text-primary font-medium">Tempo (BPM)</label>
            </div>
            <span v-if="enabledParameters.tempo" class="text-k-text-secondary text-sm">
              {{ parameters.bpm_min }} - {{ parameters.bpm_max }}
            </span>
          </div>
          <div v-if="enabledParameters.tempo" class="space-y-3">
            <div>
              <label class="text-k-text-secondary text-sm mb-1 block">Min BPM: {{ parameters.bpm_min }}</label>
              <input
                v-model.number="localParameters.bpm_min"
                type="range"
                min="60"
                max="200"
                class="param-range w-full"
                @input="updateRangeProgress"
              >
            </div>
            <div>
              <label class="text-k-text-secondary text-sm mb-1 block">Max BPM: {{ parameters.bpm_max }}</label>
              <input
                v-model.number="localParameters.bpm_max"
                type="range"
                min="60"
                max="200"
                class="param-range w-full"
                @input="updateRangeProgress"
              >
            </div>
          </div>
        </div>

        <!-- Popularity -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.popularity" />
              <label class="text-k-text-primary font-medium">Popularity</label>
            </div>
            <span v-if="enabledParameters.popularity" class="text-k-text-secondary text-sm">
              {{ parameters.popularity }}%
            </span>
          </div>
          <div v-if="enabledParameters.popularity">
            <input
              v-model.number="localParameters.popularity"
              type="range"
              min="0"
              max="100"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Underground</span>
              <span>Mainstream</span>
            </div>
          </div>
        </div>

        <!-- Danceability -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.danceability" />
              <label class="text-k-text-primary font-medium">Danceability</label>
            </div>
            <span v-if="enabledParameters.danceability" class="text-k-text-secondary text-sm">
              {{ Math.round(parameters.danceability * 100) }}%
            </span>
          </div>
          <div v-if="enabledParameters.danceability">
            <input
              v-model.number="localParameters.danceability"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Less danceable</span>
              <span>More danceable</span>
            </div>
          </div>
        </div>

        <!-- Energy -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.energy" />
              <label class="text-k-text-primary font-medium">Energy</label>
            </div>
            <span v-if="enabledParameters.energy" class="text-k-text-secondary text-sm">
              {{ Math.round(parameters.energy * 100) }}%
            </span>
          </div>
          <div v-if="enabledParameters.energy">
            <input
              v-model.number="localParameters.energy"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Calm</span>
              <span>Energetic</span>
            </div>
          </div>
        </div>

        <!-- Valence -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.valence" />
              <label class="text-k-text-primary font-medium">Valence</label>
            </div>
            <span v-if="enabledParameters.valence" class="text-k-text-secondary text-sm">
              {{ Math.round(parameters.valence * 100) }}%
            </span>
          </div>
          <div v-if="enabledParameters.valence">
            <input
              v-model.number="localParameters.valence"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Sad/Dark</span>
              <span>Happy/Uplifting</span>
            </div>
          </div>
        </div>

        <!-- Acousticness -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.acousticness" />
              <label class="text-k-text-primary font-medium">Acousticness</label>
            </div>
            <span v-if="enabledParameters.acousticness" class="text-k-text-secondary text-sm">
              {{ Math.round(parameters.acousticness * 100) }}%
            </span>
          </div>
          <div v-if="enabledParameters.acousticness">
            <input
              v-model.number="localParameters.acousticness"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Electronic</span>
              <span>Acoustic</span>
            </div>
          </div>
        </div>

        <!-- Instrumentalness -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.instrumentalness" />
              <label class="text-k-text-primary font-medium">Instrumentalness</label>
            </div>
            <span v-if="enabledParameters.instrumentalness" class="text-k-text-secondary text-sm">
              {{ Math.round(parameters.instrumentalness * 100) }}%
            </span>
          </div>
          <div v-if="enabledParameters.instrumentalness">
            <input
              v-model.number="localParameters.instrumentalness"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Vocals</span>
              <span>Instrumental</span>
            </div>
          </div>
        </div>

        <!-- Liveness -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.liveness" />
              <label class="text-k-text-primary font-medium">Liveness</label>
            </div>
            <span v-if="enabledParameters.liveness" class="text-k-text-secondary text-sm">
              {{ Math.round(parameters.liveness * 100) }}%
            </span>
          </div>
          <div v-if="enabledParameters.liveness">
            <input
              v-model.number="localParameters.liveness"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Studio</span>
              <span>Live</span>
            </div>
          </div>
        </div>

        <!-- Speechiness -->
        <div class="parameter-item">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.speechiness" />
              <label class="text-k-text-primary font-medium">Speechiness</label>
            </div>
            <span v-if="enabledParameters.speechiness" class="text-k-text-secondary text-sm">
              {{ Math.round(parameters.speechiness * 100) }}%
            </span>
          </div>
          <div v-if="enabledParameters.speechiness">
            <input
              v-model.number="localParameters.speechiness"
              type="range"
              min="0"
              max="1"
              step="0.01"
              class="param-range w-full"
              @input="updateRangeProgress"
            >
            <div class="flex justify-between text-k-text-tertiary text-xs mt-1">
              <span>Music</span>
              <span>Speech/Rap</span>
            </div>
          </div>
        </div>

        <!-- SoundStats Key Compatibility -->
        <div class="parameter-item border-2 border-blue-500/30 rounded p-3">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-blue-400 text-xs font-medium">SOUNDSTATS</span>
          </div>
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.key_compatibility" />
              <label class="text-k-text-primary font-medium">Key Compatibility</label>
            </div>
            <span v-if="enabledParameters.key_compatibility" class="text-green-400 text-sm">
              AUTO
            </span>
          </div>
          <div class="text-xs text-blue-200">
            <div v-if="enabledParameters.key_compatibility">
              ✅ Will automatically find tracks in compatible keys with your seed track
            </div>
            <div v-else>
              Toggle to enable automatic key compatibility matching
            </div>
          </div>
        </div>

        <!-- ReccoBeats Key Selection -->
        <div class="parameter-item border-2 border-orange-500/30 rounded p-3">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-orange-400 text-xs font-medium">RECCOBEATS</span>
          </div>
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <CheckBox v-model="localEnabledParameters.key_selection" />
              <label class="text-k-text-primary font-medium">Key Selection</label>
            </div>
            <span v-if="enabledParameters.key_selection" class="text-orange-400 text-sm">
              {{ selectedKeyMode.toUpperCase() }}
            </span>
          </div>
          <div v-if="enabledParameters.key_selection" class="space-y-3">
            <div class="flex gap-2">
              <button
                v-for="mode in keyModes"
                :key="mode.value"
                @click="$emit('update:selectedKeyMode', mode.value)"
                class="flex-1 px-3 py-2 rounded text-sm font-medium transition-colors"
                :class="selectedKeyMode === mode.value 
                  ? 'bg-orange-500 text-white' 
                  : 'bg-k-bg-tertiary text-k-text-secondary hover:bg-k-bg-primary'"
              >
                {{ mode.label }}
              </button>
            </div>
            
            <!-- Key explanation -->
            <div class="text-xs text-orange-200">
              <div v-if="selectedKeyMode === 'any'">
                🎵 Any key - no key restriction
              </div>
              <div v-else-if="selectedKeyMode === 'same'">
                🎹 Same key as seed track
                <span v-if="seedTrackKey !== null && seedTrackKey !== -1">
                  ({{ keyNames[seedTrackKey] }})
                </span>
              </div>
              <div v-else-if="selectedKeyMode === 'compatible'">
                🎼 Compatible keys (perfect 5th, 4th, etc.)
              </div>
              <div v-else-if="selectedKeyMode === 'custom'">
                🎛️ Custom key selection
              </div>
            </div>

            <!-- Custom key selector -->
            <div v-if="selectedKeyMode === 'custom'" class="mt-3">
              <label class="text-k-text-secondary text-sm mb-2 block">Select Key:</label>
              <select
                :value="customKey"
                @change="$emit('update:customKey', parseInt(($event.target as HTMLSelectElement).value))"
                class="w-full p-2 bg-k-bg-tertiary border border-k-border rounded text-k-text-primary"
              >
                <option value="-1">Any Key</option>
                <option v-for="(name, key) in keyNames" :key="key" :value="key">
                  {{ name }}
                </option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Discovery Buttons -->
      <div class="flex gap-4 mt-6">
        <button
          @click="$emit('discover-soundstats')"
          :disabled="!hasEnabledParameters || isDiscovering"
          class="flex-1 px-6 py-3 rounded-lg font-medium transition-colors"
          :class="isDiscovering && currentProvider === 'SoundStats'
            ? 'bg-blue-600 text-white opacity-50 cursor-not-allowed'
            : hasEnabledParameters
            ? 'bg-blue-600 hover:bg-blue-700 text-white'
            : 'bg-gray-600 text-gray-400 cursor-not-allowed'"
        >
          <span v-if="isDiscovering && currentProvider === 'SoundStats'">
            🔄 Testing SoundStats...
          </span>
          <span v-else>
            🎵 Test SoundStats
          </span>
        </button>

        <button
          @click="$emit('discover-reccobeats')"
          :disabled="!hasEnabledParameters || isDiscovering"
          class="flex-1 px-6 py-3 rounded-lg font-medium transition-colors"
          :class="isDiscovering && currentProvider === 'ReccoBeats'
            ? 'bg-orange-600 text-white opacity-50 cursor-not-allowed'
            : hasEnabledParameters
            ? 'bg-orange-600 hover:bg-orange-700 text-white'
            : 'bg-gray-600 text-gray-400 cursor-not-allowed'"
        >
          <span v-if="isDiscovering && currentProvider === 'ReccoBeats'">
            🔄 Testing ReccoBeats...
          </span>
          <span v-else>
            🎧 Test ReccoBeats
          </span>
        </button>

        <button
          @click="$emit('discover-rapidapi')"
          :disabled="isDiscovering"
          class="flex-1 px-6 py-3 rounded-lg font-medium transition-colors"
          :class="isDiscovering && currentProvider === 'RapidAPI'
            ? 'bg-purple-600 text-white opacity-50 cursor-not-allowed'
            : 'bg-purple-600 hover:bg-purple-700 text-white'"
        >
          <span v-if="isDiscovering && currentProvider === 'RapidAPI'">
            🔄 Testing RapidAPI...
          </span>
          <span v-else>
            🚀 Test RapidAPI Radio
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick } from 'vue'
import { faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'

import CheckBox from '@/components/ui/form/CheckBox.vue'

// Types
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

// Props
interface Props {
  parameters: Parameters
  enabledParameters: EnabledParameters
  selectedKeyMode: string
  customKey: number
  hasEnabledParameters: boolean
  seedTrackKey: number | null
  keyNames: Record<number, string>
  isDiscovering: boolean
  currentProvider: string
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  'update:parameters': [parameters: Parameters]
  'update:enabledParameters': [enabledParameters: EnabledParameters]
  'update:selectedKeyMode': [mode: string]
  'update:customKey': [key: number]
  'discover-soundstats': []
  'discover-reccobeats': []
  'discover-rapidapi': []
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

const keyModes = [
  { value: 'any', label: 'Any' },
  { value: 'same', label: 'Same' },
  { value: 'compatible', label: 'Compatible' },
  { value: 'custom', label: 'Custom' }
]

// Methods
const updateRangeProgress = () => {
  nextTick(() => {
    const ranges = document.querySelectorAll('.param-range')
    ranges.forEach(range => {
      const input = range as HTMLInputElement
      const min = Number.parseFloat(input.min) || 0
      const max = Number.parseFloat(input.max) || 100
      const value = Number.parseFloat(input.value) || 0
      const progress = ((value - min) / (max - min)) * 100

      // Update CSS custom property
      input.style.setProperty('--range-progress', `${progress}%`)

      // Update background gradient
      input.style.background = `linear-gradient(to right, var(--color-highlight) 0%, var(--color-highlight) ${progress}%, #374151 ${progress}%, #374151 100%)`
    })
  })
}
</script>

<style scoped>
.parameter-item {
  @apply bg-k-bg-secondary border border-k-border rounded-lg p-4;
}

.param-range {
  -webkit-appearance: none;
  appearance: none;
  height: 6px;
  border-radius: 3px;
  outline: none;
  background: #374151;
  transition: all 0.2s ease;
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
  transition: all 0.2s ease;
}

.param-range::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--color-highlight);
  cursor: pointer;
  border: 2px solid #fff;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  transition: all 0.2s ease;
}

.param-range:hover::-webkit-slider-thumb {
  transform: scale(1.1);
}

.param-range:hover::-moz-range-thumb {
  transform: scale(1.1);
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