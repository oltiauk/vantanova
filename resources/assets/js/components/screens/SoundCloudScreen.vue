<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>Enhanced SoundCloud Search</ScreenHeader>
    </template>

    <div class="p-6 space-y-6">
      <!-- Enhanced Search Controls -->
      <div class="bg-white/5 rounded-lg p-4">
        <!-- Search Quality Indicator -->
        <div v-if="searchStats" class="mb-4 flex items-center justify-between text-sm">
          <div class="flex items-center gap-4 text-white/70">
            <span class="flex items-center gap-1">
              <Icon :icon="faChartLine" />
              {{ searchStats.apiCalls }} Parallel API Calls
            </span>
            <span>{{ searchStats.totalTime }}ms</span>
            <span class="px-2 py-1 rounded text-xs"
              :class="{
                'bg-green-500/20 text-green-400': searchStats.resultQuality === 'high',
                'bg-yellow-500/20 text-yellow-400': searchStats.resultQuality === 'medium',
                'bg-red-500/20 text-red-400': searchStats.resultQuality === 'low'
              }"
            >
              {{ searchStats.resultQuality.toUpperCase() }} Quality
            </span>
          </div>
          <button 
            @click="resetFilters"
            class="px-3 py-1 bg-white/10 hover:bg-white/20 rounded text-xs transition"
          >
            Reset All
          </button>
        </div>

        <!-- Top Row: Genres and Tags -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <!-- Genres Dropdown -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Genre</label>
            <select
              v-model="selectedGenre"
              class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white scrollbar-thin scrollbar-thumb-white/20 scrollbar-track-transparent"
            >
              <option value="" class="bg-gray-800">All Genres</option>
              <option v-for="genre in genres" :key="genre" :value="genre" class="bg-gray-800">{{ genre }}</option>
            </select>
          </div>

          <!-- Tags Input -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Tags</label>
            <input
              v-model="searchTags"
              type="text"
              class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white"
              placeholder="vocal, remix, electronic..."
            />
            <div class="mt-1 flex flex-wrap gap-1">
              <button
                v-for="tag in popularTags"
                :key="tag"
                @click="addTag(tag)"
                class="px-2 py-0.5 bg-white/10 hover:bg-k-accent/20 rounded text-xs text-white/70 hover:text-k-accent transition"
              >
                {{ tag }}
              </button>
            </div>
          </div>
        </div>

        <!-- Second Row: Time Period and BPM Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
          <!-- Time Period -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Time Period</label>
            <select
              v-model="timePeriod"
              class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white"
            >
              <option value="" class="bg-gray-800">All Time</option>
              <option value="1d" class="bg-gray-800">Last Day</option>
              <option value="1w" class="bg-gray-800">Last Week</option>
              <option value="1m" class="bg-gray-800">Last Month</option>
              <option value="3m" class="bg-gray-800">Last 3 Months</option>
              <option value="6m" class="bg-gray-800">Last 6 Months</option>
              <option value="1y" class="bg-gray-800">Last Year</option>
            </select>
          </div>

          <!-- BPM Range -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">
              BPM Range: {{ bpmFrom }} - {{ bpmTo }}
            </label>
            <div class="space-y-2">
              <DualRangeSlider
                :min="60"
                :max="200"
                :from="bpmFrom"
                :to="bpmTo"
                @update:from="bpmFrom = $event"
                @update:to="bpmTo = $event"
              />
              <div class="grid grid-cols-2 gap-2">
                <input
                  v-model.number="bpmFrom"
                  type="number"
                  min="60"
                  max="200"
                  placeholder="Min BPM"
                  class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white text-sm"
                />
                <input
                  v-model.number="bpmTo"
                  type="number"
                  min="60"
                  max="200"
                  placeholder="Max BPM"
                  class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white text-sm"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Advanced Filters Toggle -->
        <div class="mb-4">
          <button
            @click="showAdvancedFilters = !showAdvancedFilters"
            class="flex items-center gap-2 px-3 py-2 bg-white/5 hover:bg-white/10 rounded-lg text-sm text-white/80 transition"
          >
            <Icon :icon="faFilter" />
            Advanced Filters
            <span class="text-xs bg-k-accent/20 text-k-accent px-2 py-0.5 rounded">
              {{ showAdvancedFilters ? 'Hide' : 'Show' }}
            </span>
          </button>
        </div>

        <!-- Advanced Filters -->
        <div v-if="showAdvancedFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4 p-4 bg-white/5 rounded-lg">
          <!-- Duration Range -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Duration (seconds)</label>
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model.number="durationFrom"
                type="number"
                placeholder="Min"
                class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white text-sm"
              />
              <input
                v-model.number="durationTo"
                type="number"
                placeholder="Max"
                class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white text-sm"
              />
            </div>
          </div>

          <!-- Minimum Plays -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Minimum Plays</label>
            <input
              v-model.number="minPlays"
              type="number"
              placeholder="e.g. 10000"
              class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white"
            />
          </div>

          <!-- Minimum Likes -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Minimum Likes</label>
            <input
              v-model.number="minLikes"
              type="number"
              placeholder="e.g. 1000"
              class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white"
            />
          </div>
        </div>

        <!-- Search Button -->
        <div class="flex justify-center gap-3">
          <button
            @click="search"
            :disabled="loading || !hasValidFilters"
            class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-medium transition flex items-center gap-2"
          >
            <Icon v-if="loading" :icon="faSpinner" spin />
            <Icon v-else :icon="faSearch" />
            {{ searchButtonText }}
          </button>
          
          <button
            v-if="hasValidFilters"
            @click="resetFilters"
            class="px-4 py-3 bg-white/10 hover:bg-white/20 rounded-lg font-medium transition"
          >
            Reset
          </button>
        </div>

        <!-- Filter Status -->
        <div v-if="hasValidFilters" class="mt-3 text-center">
          <div class="inline-flex items-center gap-2 text-sm text-white/60">
            <Icon :icon="faFilter" />
            Filters: 
            <span v-if="selectedGenre" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              {{ selectedGenre }}
            </span>
            <span v-if="searchTags" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              {{ searchTags.length > 20 ? searchTags.substring(0, 20) + '...' : searchTags }}
            </span>
            <span v-if="bpmFrom !== 95 || bpmTo !== 172" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              {{ bpmFrom }}-{{ bpmTo }} BPM
            </span>
            <span v-if="timePeriod" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              {{ timePeriod }}
            </span>
          </div>
        </div>
      </div>

      <!-- Enhanced Results Table -->
      <div v-if="tracks.length > 0">
        <SoundCloudTrackTable 
          :tracks="tracks"
          @play="playTrack"
        />
      </div>

      <!-- Empty State -->
      <div v-else-if="!searched && !loading" class="text-center p-12">
        <Icon :icon="faSoundcloud" class="text-6xl text-white/40 mb-4" />
        <h3 class="text-xl font-semibold text-white mb-2">Enhanced SoundCloud Search</h3>
        <p class="text-white/60 max-w-md mx-auto mb-4">
          Search tracks with advanced parallel API calls for higher quality results with actual BPM data - features not available on the official SoundCloud website!
        </p>
        <div class="text-sm text-white/50 space-y-1">
          <div>✅ Parallel API calls for better results</div>
          <div>✅ BPM filtering with real data</div>
          <div>✅ Advanced duration & popularity filters</div>
          <div>✅ Result quality scoring</div>
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="searched && tracks.length === 0 && !loading" class="text-center p-12">
        <Icon :icon="faSearch" class="text-4xl text-white/40 mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">No Results Found</h3>
        <p class="text-white/60 mb-4">
          The enhanced search uses intersection of multiple API calls for higher quality results.
        </p>
        <div class="text-sm text-white/50 max-w-md mx-auto">
          <strong>Try:</strong>
          <ul class="list-disc list-inside mt-2 space-y-1">
            <li>Broadening your BPM range</li>
            <li>Using more general tags</li>
            <li>Removing time period restrictions</li>
            <li>Lowering minimum plays/likes</li>
          </ul>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="text-center p-12">
        <Icon :icon="faSpinner" spin class="text-4xl text-k-accent mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">Enhanced Search in Progress...</h3>
        <p class="text-white/60">Running parallel API calls for best results</p>
        <div class="mt-4 text-xs text-white/50">
          This may take a few seconds for higher quality results
        </div>
      </div>

      <!-- Error State -->
      <div v-if="error" class="bg-red-500/20 border border-red-500/40 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <Icon :icon="faExclamationTriangle" class="text-red-400 mt-0.5" />
          <div>
            <h4 class="font-medium text-red-200 mb-1">Search Error</h4>
            <p class="text-red-200">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- SoundCloud Player Modal -->
    <teleport to="body">
      <div
        v-if="showPlayer"
        class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
        @click="closePlayer"
      >
        <div
          class="bg-k-bg-secondary rounded-lg p-6 max-w-4xl w-full max-h-[80vh] overflow-auto"
          @click.stop
        >
          <div class="flex justify-between items-center mb-4">
            <div>
              <h3 class="text-xl font-semibold text-white">{{ currentTrack?.title }}</h3>
              <p class="text-white/60">by {{ currentTrack?.user.username }}</p>
              <div class="flex items-center gap-4 mt-1 text-sm text-white/50">
                <span v-if="currentTrack?.bpm">{{ currentTrack.bpm }} BPM</span>
                <span v-if="currentTrack?.genre">{{ currentTrack.genre }}</span>
                <span>{{ formatCount(currentTrack?.playback_count || 0) }} plays</span>
              </div>
            </div>
            <button
              @click="closePlayer"
              class="p-2 hover:bg-white/10 rounded-lg transition"
            >
              <Icon :icon="faTimes" class="text-white" />
            </button>
          </div>
          <iframe
            v-if="embedUrl"
            :src="embedUrl"
            width="100%"
            height="166"
            frameborder="no"
            scrolling="no"
            allow="autoplay"
            class="rounded"
          />
        </div>
      </div>
    </teleport>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faPlay, faSearch, faSpinner, faTimes, faFilter, faChartLine, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { ref, computed } from 'vue'
import { soundcloudService, type SoundCloudTrack, type SoundCloudFilters } from '@/services/soundcloudService'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import DualRangeSlider from '@/components/ui/DualRangeSlider.vue'
import SoundCloudTrackTable from '@/components/ui/soundcloud/SoundCloudTrackTable.vue'

interface SearchStats {
  apiCalls: number
  totalTime: number
  cacheHits: number
  resultQuality: 'high' | 'medium' | 'low'
}

// Search form state
const selectedGenre = ref('')
const searchTags = ref('')
const bpmFrom = ref<number>(95)
const bpmTo = ref<number>(172)
const timePeriod = ref('')
const minPlays = ref<number>()
const minLikes = ref<number>()
const durationFrom = ref<number>()
const durationTo = ref<number>()

// Results state
const tracks = ref<SoundCloudTrack[]>([])
const loading = ref(false)
const searched = ref(false)
const error = ref('')
const searchStats = ref<SearchStats | null>(null)

// Player state
const showPlayer = ref(false)
const currentTrack = ref<SoundCloudTrack | null>(null)
const embedUrl = ref('')

// Advanced filters toggle
const showAdvancedFilters = ref(false)

// Computed properties
const hasValidFilters = computed(() => {
  const hasGenre = selectedGenre.value && selectedGenre.value !== 'All Genres' && selectedGenre.value !== ''
  const hasTags = searchTags.value?.trim()
  const hasBPM = (bpmFrom.value !== 95 || bpmTo.value !== 172)
  const hasAdvanced = minPlays.value || minLikes.value || durationFrom.value || durationTo.value
  
  // Removed debug log to prevent infinite loop
  
  return hasGenre || hasTags || hasBPM || hasAdvanced
})

const searchButtonText = computed(() => {
  if (loading.value) return 'Searching...'
  if (searchStats.value) {
    return `Search (${searchStats.value.apiCalls} API calls)`
  }
  return 'Enhanced Search'
})

// Available genres
const genres = [
  'Alternative Rock', 'Ambient', 'Classical', 'Country', 'Dance & EDM',
  'Dancehall', 'Deep House', 'Disco', 'Drum & Bass', 'Dubstep',
  'Electronic', 'Folk & Singer-Songwriter', 'Hip-hop & Rap', 'House',
  'Indie', 'Jazz & Blues', 'Latin', 'Metal', 'Piano', 'Pop',
  'R&B & Soul', 'Reggae', 'Reggaeton', 'Rock', 'Soundtrack',
  'Techno', 'Trance', 'Trap', 'Triphop', 'World'
]

// Popular tags that users commonly search for
const popularTags = [
  'vocal', 'remix', 'instrumental', 'chill', 'upbeat', 'melodic', 'experimental', 'vintage', 'modern', 'dark'
]

const addTag = (tag: string) => {
  if (searchTags.value) {
    // Add comma if there are existing tags
    if (!searchTags.value.endsWith(', ')) {
      searchTags.value += ', '
    }
    searchTags.value += tag
  } else {
    searchTags.value = tag
  }
}

const formatCount = (count: number): string => {
  if (count >= 1000000) {
    return (count / 1000000).toFixed(1) + 'M'
  } else if (count >= 1000) {
    return (count / 1000).toFixed(1) + 'K'
  }
  return count.toString()
}

const search = async () => {
  console.log('🎵 DEBUG: search() function called')
  console.log('🎵 DEBUG: hasValidFilters.value:', hasValidFilters.value)
  console.log('🎵 DEBUG: selectedGenre.value:', selectedGenre.value)
  
  if (!hasValidFilters.value) {
    console.log('🎵 DEBUG: Blocking search - no valid filters')
    error.value = 'Please select at least one filter to search with enhanced features.'
    return
  }

  loading.value = true
  error.value = ''
  tracks.value = []
  searchStats.value = null

  const startTime = performance.now()
  
  try {
    const filters: SoundCloudFilters = {
      searchTags: searchTags.value?.trim() || undefined,
      genre: selectedGenre.value || undefined,
      bpmFrom: bpmFrom.value !== 95 ? bpmFrom.value : undefined,
      bpmTo: bpmTo.value !== 172 ? bpmTo.value : undefined,
      durationFrom: durationFrom.value || undefined,
      durationTo: durationTo.value || undefined,
      minPlays: minPlays.value || undefined,
      minLikes: minLikes.value || undefined,
      timePeriod: timePeriod.value || undefined,
      limit: 20
    }

    console.log('🎵 Enhanced SoundCloud Search - Filters:', filters)

    const results = await soundcloudService.search(filters)
    const endTime = performance.now()
    
    // Calculate search statistics
    let apiCallCount = 0
    if (filters.searchTags) apiCallCount++
    if (filters.genre) apiCallCount++
    if (filters.bpmFrom || filters.bpmTo) apiCallCount++
    if (filters.durationFrom || filters.durationTo) apiCallCount++
    
    const resultQuality: SearchStats['resultQuality'] = 
      results.length > 15 ? 'high' : 
      results.length > 5 ? 'medium' : 'low'
    
    searchStats.value = {
      apiCalls: apiCallCount,
      totalTime: Math.round(endTime - startTime),
      cacheHits: 0, // TODO: implement cache hit tracking
      resultQuality
    }

    tracks.value = results
    searched.value = true

    if (tracks.value.length === 0) {
      error.value = 'No tracks found with your criteria. The enhanced search uses intersection of results for higher quality - try broadening your filters.'
    }
  } catch (err: any) {
    console.error('🎵 Enhanced SoundCloud Search - Error:', err)
    error.value = err.message || 'Failed to search SoundCloud. Please try again.'
    tracks.value = []
  } finally {
    loading.value = false
  }
}

const playTrack = async (track: SoundCloudTrack) => {
  try {
    const embedUrl_new = await soundcloudService.getEmbedUrl(track.id, {
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true,
      visual: true
    })

    currentTrack.value = track
    embedUrl.value = embedUrl_new
    showPlayer.value = true
  } catch (err) {
    error.value = 'Failed to load SoundCloud player.'
  }
}

const resetFilters = () => {
  selectedGenre.value = ''
  searchTags.value = ''
  bpmFrom.value = 95
  bpmTo.value = 172
  timePeriod.value = ''
  minPlays.value = undefined
  minLikes.value = undefined
  durationFrom.value = undefined
  durationTo.value = undefined
  tracks.value = []
  searched.value = false
  error.value = ''
  searchStats.value = null
  showAdvancedFilters.value = false
}

const closePlayer = () => {
  showPlayer.value = false
  currentTrack.value = null
  embedUrl.value = ''
}
</script>

<style scoped>
/* Custom scrollbar for select dropdown */
select::-webkit-scrollbar {
  width: 8px;
}

select::-webkit-scrollbar-track {
  background: transparent;
}

select::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}

select::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.4);
}
</style>