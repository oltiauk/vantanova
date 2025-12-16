<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader header-image="/VantaNova-Logo.svg">
        Enhanced SoundCloud Search
      </ScreenHeader>
    </template>

    <div class="p-6 space-y-6">
      <!-- Enhanced Search Controls -->
      <div class="rounded-lg p-4">
        <!-- Main Layout: Search Controls Left, Advanced Filters Right -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Left Side: Search Controls (50% width) -->
          <div class="space-y-4">
            <!-- Top Row: Artist & Keywords -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Artist Input -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Search Artist</label>
                <input
                  v-model="artistFilter"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                  placeholder="Search for artists"
                  @keyup.enter="search"
                >
              </div>

              <!-- Search Query Row -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Search Keywords</label>
                <input
                  v-model="searchQuery"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg search-input"
                  placeholder="Search for tracks, albums..."
                  @keyup.enter="search"
                >
                <div class="mt-2 flex flex-wrap gap-1">
                  <button
                    v-for="preset in keywordPresets"
                    :key="preset"
                    class="px-2 py-1 bg-white/10 hover:bg-k-accent/20 rounded text-xs text-white/70 hover:text-k-accent transition"
                    @click="addKeywordPreset(preset)"
                  >
                    {{ preset }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Top Row: Genres and Tags -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Genre Input -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Genre</label>
                <input
                  v-model="selectedGenre"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                  placeholder="Type a genre"
                >
              </div>

              <!-- Tags Input -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Tags</label>
                <input
                  v-model="searchTags"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                  placeholder="Add another genre or keyword"
                >
              </div>
            </div>

            <!-- Second Row: Time Period and Content Type -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Time Period -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Time Period</label>
                <select
                  v-model="timePeriod"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
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

              <!-- Content Type -->
              <div>
                <label class="block text-sm font-medium mb-3 text-white/80">Content Type</label>
                <div class="flex gap-3">
                  <label class="flex-1 cursor-pointer">
                    <input
                      v-model="contentType"
                      type="radio"
                      value="tracks"
                      class="sr-only"
                    >
                    <div
                      class="px-4 py-2.5 rounded-lg border-2 transition-all duration-200 text-center text-sm font-medium"
                      :class="contentType === 'tracks'
                        ? 'border-k-accent bg-k-accent/20 text-white'
                        : 'border-white/20 bg-white/5 text-white/70 hover:border-white/30 hover:bg-white/10'"
                    >
                      Tracks
                    </div>
                  </label>
                  <label class="flex-1 cursor-pointer">
                    <input
                      v-model="contentType"
                      type="radio"
                      value="mixes"
                      class="sr-only"
                    >
                    <div
                      class="px-4 py-2.5 rounded-lg border-2 transition-all duration-200 text-center text-sm font-medium"
                      :class="contentType === 'mixes'
                        ? 'border-k-accent bg-k-accent/20 text-white'
                        : 'border-white/20 bg-white/5 text-white/70 hover:border-white/30 hover:bg-white/10'"
                    >
                      Mixes
                    </div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Side: Advanced Filters (50% width) -->
          <div>
            <div class="p-4 bg-white/5 rounded-lg h-full flex flex-col">
              <div class="flex items-center gap-2 mb-4">
                <Icon :icon="faFilter" class="text-white/80" />
                <h3 class="text-sm font-medium text-white/80">Advanced Filters</h3>
              </div>

              <!-- Min/Max Plays -->
              <div class="grid grid-cols-2 gap-4 mb-auto">
                <!-- Minimum Plays -->
                <div>
                  <label class="block text-sm font-medium mb-2 text-white/80">Minimum Streams</label>
                  <input
                    v-model="minPlaysFormatted"
                    type="text"
                    placeholder="e.g. 10,000"
                    class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                    @input="handleMinPlaysInput"
                  >
                </div>

                <!-- Maximum Plays -->
                <div>
                  <label class="block text-sm font-medium mb-2 text-white/80">Maximum Streams</label>
                  <input
                    v-model="maxPlaysFormatted"
                    type="text"
                    placeholder="e.g. 1,000,000"
                    class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                    @input="handleMaxPlaysInput"
                  >
                </div>
              </div>

              <!-- BPM Range - Pushed to bottom -->
              <div class="mt-auto">
                <label class="block text-sm font-medium mb-2 text-white/80">
                  BPM Range: {{ bpmFrom }} - {{ bpmTo }}
                </label>
                <div class="mt-4">
                  <DualRangeSlider
                    :min="60"
                    :max="200"
                    :from="bpmFrom"
                    :to="bpmTo"
                    class="bpm-slider-white"
                    @update:from="bpmFrom = $event"
                    @update:to="bpmTo = $event"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Search Button -->
        <div class="flex justify-center gap-3 mt-6">
          <button
            :disabled="loading || !hasValidFilters"
            class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-medium transition flex items-center gap-2"
            @click="search"
          >
            <Icon :icon="faSearch" />
            {{ searchButtonText }}
          </button>

          <button
            v-if="hasValidFilters"
            class="px-4 py-3 bg-white/10 hover:bg-white/20 rounded-lg font-medium transition"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Loading Animation -->
      <div v-if="loading" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-white mb-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        </div>
      </div>

      <!-- Enhanced Results Table -->
      <div v-else-if="tracks.length > 0">
        <!-- Sort by Dropdown -->
        <div class="flex justify-end mb-4 relative">
          <button
            class="px-4 py-2 rounded-lg font-medium transition flex items-center gap-2 bg-white/10 text-white/80 hover:bg-white/20"
            style="background-color: rgba(47, 47, 47, 255) !important;"
            @click="toggleLikesRatioDropdown"
            @blur="hideLikesRatioDropdown"
          >
            {{ getSortText() }}
            <Icon :icon="faChevronDown" class="text-xs" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showLikesRatioDropdown"
            class="absolute right-0 mt-12 w-52  rounded-lg shadow-lg z-10"
            style="background-color: rgb(67,67,67,255);"
          >
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2 rounded-t-lg"
              :style="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('newest')"
            >
              Most Recent
            </button>
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2 rounded-b-lg"
              :style="likesRatioFilter === 'streams' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('streams')"
            >
              Most Streams
            </button>
          </div>
        </div>

        <SoundCloudTrackTable
          ref="soundcloudTable"
          :tracks="tracks"
          :start-index="0"
          :allow-animations="allowAnimations"
          :animation-start-index="animationStartIndex"
          @play="playTrack"
          @pause="pauseTrack"
          @seek="seekTrack"
          @related-tracks="openRelatedTracks"
        />

        <!-- Load More -->
        <div v-if="hasMoreResults && !loading" class="flex items-center justify-center mt-8">
          <button
            class="px-4 py-2 bg-k-accent text-white rounded hover:bg-k-accent/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loadingMore"
            @click="loadMoreResults"
          >
            <Icon v-if="loadingMore" :icon="faSpinner" spin class="mr-2" />
            Load More
          </button>
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="searched && tracks.length === 0 && !loading" class="text-center p-12">
        <Icon :icon="faSearch" class="text-4xl text-white/40 mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">No Results Found</h3>
        <p class="text-white/60 mb-4">
          No tracks found with your criteria. Try broadening your filters.
        </p>
        <div class="text-sm text-white/50 max-w-md mx-auto">
          <strong>Try:</strong>
          <ul class="list-disc list-inside mt-2 space-y-1">
            <li>Broadening your BPM range</li>
            <li>Removing BPM range filter</li>
            <li>Using more general tags</li>
            <li>Removing time period restrictions</li>
            <li>Lowering minimum plays/likes</li>
          </ul>
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
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faChevronDown, faExclamationTriangle, faFilter, faSearch, faSpinner } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from '@/composables/useRouter'
import { type SoundCloudFilters, soundcloudService, type SoundCloudTrack } from '@/services/soundcloudService'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { eventBus } from '@/utils/eventBus'
import Router from '@/router'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'

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
const searchQuery = ref('')
const selectedGenre = ref('')
const searchTags = ref('')
const bpmFrom = ref<number>(95)
const bpmTo = ref<number>(172)
// Temporary input states to prevent slider jumping while typing
const bpmFromInput = ref<string>('95')
const bpmToInput = ref<string>('172')
const timePeriod = ref('')
const artistFilter = ref('')
const minPlays = ref<number>()
const maxPlays = ref<number>()
const minPlaysFormatted = ref('')
const maxPlaysFormatted = ref('')
const contentType = ref('tracks') // Default to tracks
const likesRatioFilter = ref<'streams' | 'newest'>('newest') // Sort filter state
const showLikesRatioDropdown = ref(false) // Dropdown visibility
const displayLimit = ref(20)
const animationStartIndex = ref(0)

// Results state
const tracks = ref<SoundCloudTrack[]>([])
const originalTracks = ref<SoundCloudTrack[]>([]) // Store unfiltered tracks
const loading = ref(false)
const searched = ref(false)
const error = ref('')
const searchStats = ref<SearchStats | null>(null)

// Pagination state
const loadingMore = ref(false)
const hasMoreResults = ref(false)
const serverHasMore = ref(false)
const lastSearchFilters = ref<SoundCloudFilters | null>(null)

// Banned artists tracking (shared with Similar Artists and Recommendations)
const bannedArtists = ref(new Set<string>()) // Store artist names

// Initialize global blacklist filtering composable
const {
  loadBlacklistedItems,
} = useBlacklistFiltering()

// Player state (now using global store)
// Note: Player will show in footer when this component is mounted (SoundCloud page)

// Computed properties
const hasValidFilters = computed(() => {
  const hasQuery = searchQuery.value?.trim()
  const hasGenre = selectedGenre.value && selectedGenre.value !== 'All Genres' && selectedGenre.value !== ''
  const hasTags = searchTags.value?.trim()
  const hasBPM = (bpmFrom.value !== 95 || bpmTo.value !== 172)
  const hasArtist = artistFilter.value?.trim()
  const hasAdvanced = minPlays.value || maxPlays.value || (contentType.value !== 'tracks')
  const hasTimePeriod = timePeriod.value && timePeriod.value !== ''

  return hasQuery || hasGenre || hasTags || hasBPM || hasAdvanced || hasTimePeriod || hasArtist
})

const searchButtonText = computed(() => 'Search')

// Keyword presets that users commonly search for
const keywordPresets = [
  'Remix',
  'Mashup',
  'Free Download',
  'Free DL',
]

const addKeywordPreset = (preset: string) => {
  if (searchQuery.value) {
    // Add space if there are existing keywords
    if (!searchQuery.value.endsWith(' ')) {
      searchQuery.value += ' '
    }
    searchQuery.value += preset
  } else {
    searchQuery.value = preset
  }
}

const formatCount = (count: number): string => {
  if (count >= 1000000) {
    return `${(count / 1000000).toFixed(1)}M`
  } else if (count >= 1000) {
    return `${(count / 1000).toFixed(1)}K`
  }
  return count.toString()
}

const formatDate = (dateString: string): string => {
  if (!dateString) {
    return 'Unknown'
  }
  try {
    const date = new Date(dateString)
    const now = new Date()
    const diffTime = Math.abs(now.getTime() - date.getTime())
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    if (diffDays < 30) {
      return `${diffDays} days ago`
    } else if (diffDays < 365) {
      const months = Math.floor(diffDays / 30)
      return `${months} month${months > 1 ? 's' : ''} ago`
    } else {
      const years = Math.floor(diffDays / 365)
      return `${years} year${years > 1 ? 's' : ''} ago`
    }
  } catch {
    return 'Unknown'
  }
}

const toggleLikesRatioDropdown = () => {
  showLikesRatioDropdown.value = !showLikesRatioDropdown.value
}

const hideLikesRatioDropdown = () => {
  setTimeout(() => {
    showLikesRatioDropdown.value = false
  }, 150) // Small delay to allow click events to register
}

const setLikesRatioFilter = (type: 'streams' | 'newest') => {
  likesRatioFilter.value = type
  showLikesRatioDropdown.value = false
  applyFiltering()
}

const getSortText = () => {
  switch (likesRatioFilter.value) {
    case 'streams': return 'Most Streams'
    default: return 'Most Recent'
  }
}

const getFilteredTracks = () => {
  let filteredTracks = [...originalTracks.value]
  const originalCount = filteredTracks.length

  console.log('🔍 applyFiltering called:', {
    originalCount,
    minPlays: minPlays.value,
    maxPlays: maxPlays.value,
    hasPlayFilters: !!(minPlays.value || maxPlays.value),
  })

  // Apply plays filtering first
  if (minPlays.value || maxPlays.value) {
    filteredTracks = filteredTracks.filter(track => {
      const playCount = track.playback_count || 0

      if (minPlays.value && playCount < minPlays.value) {
        return false
      }

      if (maxPlays.value && playCount > maxPlays.value) {
        return false
      }

      return true
    })

    console.log('🔍 After filtering:', {
      originalCount,
      filteredCount: filteredTracks.length,
      sampleResults: filteredTracks.slice(0, 3).map(t => ({
        title: t?.title,
        plays: t?.playback_count,
      })),
    })
  }

  // Artist filter (matches artist/account/title)
  if (artistFilter.value?.trim()) {
    const artistQuery = artistFilter.value.trim().toLowerCase()
    filteredTracks = filteredTracks.filter(track => {
      const fields = [
        track.user?.username,
        track.user?.permalink_url,
        track.title,
      ]

      return fields.some(field => typeof field === 'string' && field.toLowerCase().includes(artistQuery))
    })
  }

  // Then apply sorting
  if (likesRatioFilter.value === 'newest') {
    // Sort by creation date newest to oldest
    filteredTracks.sort((a, b) => {
      const dateA = new Date(a.created_at || 0).getTime()
      const dateB = new Date(b.created_at || 0).getTime()
      return dateB - dateA
    })
  } else {
    // Sort by streams (highest to lowest)
    filteredTracks.sort((a, b) => (b.playback_count || 0) - (a.playback_count || 0))
  }

  return filteredTracks
}

const applyFiltering = () => {
  const filteredTracks = getFilteredTracks()
  updateVisibleTracks(filteredTracks, false)
}

const updateVisibleTracks = (filteredTracks: SoundCloudTrack[], append: boolean) => {
  if (append) {
    const start = tracks.value.length
    const end = Math.min(filteredTracks.length, start + 20)
    if (end <= start) {
      hasMoreResults.value = serverHasMore.value || filteredTracks.length > start
      return
    }
    const nextChunk = filteredTracks.slice(start, end)
    tracks.value.push(...nextChunk)
    displayLimit.value = tracks.value.length
  } else {
    displayLimit.value = Math.max(20, displayLimit.value)
    tracks.value = filteredTracks.slice(0, displayLimit.value)
  }

  hasMoreResults.value = serverHasMore.value || filteredTracks.length > tracks.value.length
}

const triggerRowAnimations = (startIndex: number) => {
  animationStartIndex.value = startIndex
  allowAnimations.value = true

  setTimeout(() => {
    allowAnimations.value = false
  }, 2000)
}

// Number formatting helpers for plays inputs
const formatNumberWithCommas = (num: number): string => {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

const parseNumberFromFormatted = (str: string): number | undefined => {
  const cleaned = str.replace(/[,\s]/g, '')
  const num = Number.parseInt(cleaned, 10)
  return isNaN(num) ? undefined : num
}

const handleMinPlaysInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value
  const cursorPosition = target.selectionStart || 0

  // Remove all non-digits first
  const digitsOnly = value.replace(/\D/g, '')

  if (digitsOnly === '') {
    minPlaysFormatted.value = ''
    minPlays.value = undefined
    return
  }

  // Parse and format with commas
  const num = Number.parseInt(digitsOnly, 10)
  const formatted = formatNumberWithCommas(num)

  minPlaysFormatted.value = formatted
  minPlays.value = num

  // Restore cursor position (adjust for added commas)
  setTimeout(() => {
    const commasBeforeCursor = (formatted.substring(0, cursorPosition).match(/,/g) || []).length
    const newPosition = Math.min(cursorPosition + commasBeforeCursor, formatted.length)
    target.setSelectionRange(newPosition, newPosition)
  }, 0)
}

const handleMaxPlaysInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value
  const cursorPosition = target.selectionStart || 0

  // Remove all non-digits first
  const digitsOnly = value.replace(/\D/g, '')

  if (digitsOnly === '') {
    maxPlaysFormatted.value = ''
    maxPlays.value = undefined
    return
  }

  // Parse and format with commas
  const num = Number.parseInt(digitsOnly, 10)
  const formatted = formatNumberWithCommas(num)

  maxPlaysFormatted.value = formatted
  maxPlays.value = num

  // Restore cursor position (adjust for added commas)
  setTimeout(() => {
    const commasBeforeCursor = (formatted.substring(0, cursorPosition).match(/,/g) || []).length
    const newPosition = Math.min(cursorPosition + commasBeforeCursor, formatted.length)
    target.setSelectionRange(newPosition, newPosition)
  }, 0)
}

// BPM input handlers with debouncing to prevent slider jumping
let bpmFromTimeout: NodeJS.Timeout | null = null
let bpmToTimeout: NodeJS.Timeout | null = null

const handleBpmFromInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value

  // Update the input display immediately - DON'T interfere with user typing
  bpmFromInput.value = value

  // Clear existing timeout
  if (bpmFromTimeout) {
    clearTimeout(bpmFromTimeout)
  }

  // Debounce the actual BPM update to prevent slider jumping
  bpmFromTimeout = setTimeout(() => {
    const numValue = Number.parseInt(value, 10)
    if (!isNaN(numValue) && numValue >= 60 && numValue <= 200) {
      // Ensure min doesn't exceed max with some gap
      bpmFrom.value = Math.min(numValue, bpmTo.value - 5)
    }
    // Don't reset to default when empty - let user type freely
  }, 500) // 500ms debounce
}

const handleBpmToInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value

  // Update the input display immediately - DON'T interfere with user typing
  bpmToInput.value = value

  // Clear existing timeout
  if (bpmToTimeout) {
    clearTimeout(bpmToTimeout)
  }

  // Debounce the actual BPM update to prevent slider jumping
  bpmToTimeout = setTimeout(() => {
    const numValue = Number.parseInt(value, 10)
    if (!isNaN(numValue) && numValue >= 60 && numValue <= 200) {
      // Ensure max doesn't go below min with some gap
      bpmTo.value = Math.max(numValue, bpmFrom.value + 5)
    }
    // Don't reset to default when empty - let user type freely
  }, 500) // 500ms debounce
}

// Watch for plays filter changes to re-apply filtering
watch([minPlays, maxPlays], () => {
  // Only re-filter if we have tracks AND a search has been performed
  if (originalTracks.value.length > 0 && searched.value && !loading.value) {
    applyFiltering()
  }
})

// Watch for slider changes to update input fields (only when not actively typing)
watch([bpmFrom, bpmTo], ([newFrom, newTo]) => {
  // Only update input fields if they don't already contain the same value
  // This prevents interference while user is typing
  if (bpmFromInput.value !== newFrom.toString()) {
    bpmFromInput.value = newFrom.toString()
  }
  if (bpmToInput.value !== newTo.toString()) {
    bpmToInput.value = newTo.toString()
  }
})

// Watch artist filter to re-apply filtering on existing results
watch(artistFilter, () => {
  if (originalTracks.value.length > 0 && searched.value && !loading.value) {
    applyFiltering()
  }
})

const search = async () => {
  if (!hasValidFilters.value) {
    error.value = 'Please select at least one filter to search with enhanced features.'
    return
  }

  loading.value = true
  error.value = ''
  tracks.value = []
  originalTracks.value = []
  searchStats.value = null
  hasMoreResults.value = false
  loadingMore.value = false
  animationStartIndex.value = 0

  const startTime = performance.now()

  try {
    // Convert contentType to duration filters
    let durationFromFilter: number | undefined
    let durationToFilter: number | undefined

    if (contentType.value === 'tracks') {
      // Tracks: max 1000 seconds (16 minutes 40 seconds)
      durationToFilter = 1000
    } else if (contentType.value === 'mixes') {
      // Mixes: min 1000 seconds
      durationFromFilter = 1000
    }

    const filters: SoundCloudFilters = {
      searchQuery: searchQuery.value?.trim() || undefined,
      searchTags: searchTags.value?.trim() || undefined,
      genre: selectedGenre.value || undefined,
      bpmFrom: bpmFrom.value !== 95 ? bpmFrom.value : undefined,
      bpmTo: bpmTo.value !== 172 ? bpmTo.value : undefined,
      durationFrom: durationFromFilter,
      durationTo: durationToFilter,
      minPlays: minPlays.value || undefined,
      maxPlays: maxPlays.value || undefined,
      timePeriod: timePeriod.value || undefined,
      limit: 200,
    }

    // Store filters for pagination
    lastSearchFilters.value = filters

    console.log('🎵 DEBUG: Filters being sent to API:', filters)

    const response = await soundcloudService.searchWithPagination(filters)
    const endTime = performance.now()

    // Store original tracks and display them
    // Store original tracks and display them
    console.log('🎵 DEBUG: Response received:', {
      responseTracksCount: response.tracks?.length || 0,
      firstTrack: response.tracks?.[0] || null,
      samplePlayCounts: (response.tracks || []).slice(0, 5).map(t => ({
        title: t?.title,
        plays: t?.playback_count,
      })),
    })

    const pageLimit = filters.limit || 200
    const limitedTracks = response.tracks.slice(0, pageLimit)

    // Filter out banned artists from NEW search results
    const filteredTracks = filterBannedArtists(limitedTracks)
    console.log(`🚫 SoundCloud: Filtered ${limitedTracks.length - filteredTracks.length} banned artists from new search results`)

    const uniqueTracks = dedupeTracks(filteredTracks)
    originalTracks.value = uniqueTracks
    tracks.value = uniqueTracks

    console.log('🎵 DEBUG: After storing:', {
      originalTracksCount: originalTracks.value.length,
      tracksCount: tracks.value.length,
      minPlays: minPlays.value,
      maxPlays: maxPlays.value,
      willFilter: !!(minPlays.value || maxPlays.value),
    })

    serverHasMore.value = response.hasMore || response.tracks.length >= pageLimit

    // Reset visible count to first 20
    displayLimit.value = 20

    // Apply current filtering to visible tracks (updates hasMoreResults)
    applyFiltering()

    console.log('🎵 Search complete:', {
      totalTracks: tracks.value.length,
      hasMorePages: hasMoreResults.value,
    })

    // Calculate search statistics
    const resultQuality: SearchStats['resultQuality']
      = tracks.value.length > 15
        ? 'high'
        : tracks.value.length > 5 ? 'medium' : 'low'

    searchStats.value = {
      apiCalls: response.apiCalls || 1,
      totalTime: Math.round(endTime - startTime),
      cacheHits: 0,
      resultQuality,
    }

    searched.value = true

    // Trigger animations for new search results
    setTimeout(() => {
      triggerRowAnimations(0)
      initialLoadComplete.value = true
    }, 50)
  } catch (err: any) {
    error.value = err.message || 'Failed to search SoundCloud. Please try again.'
    tracks.value = []
    originalTracks.value = []
  } finally {
    loading.value = false
  }
}

const loadMoreResults = async () => {
  if (!lastSearchFilters.value || loadingMore.value || !hasMoreResults.value) {
    return
  }

  const currentVisibleCount = tracks.value.length
  const filteredTracks = getFilteredTracks()

  // If we already have more locally, just reveal the next chunk
  if (displayLimit.value < filteredTracks.length) {
    updateVisibleTracks(filteredTracks, true)
    if (tracks.value.length > currentVisibleCount) {
      setTimeout(() => {
        triggerRowAnimations(currentVisibleCount)
      }, 50)
    }
    return
  }

  // Otherwise fetch the next batch from the API
  loadingMore.value = true
  error.value = ''

  try {
    const offset = originalTracks.value.length
    const limit = lastSearchFilters.value.limit || 200

    const filters = {
      ...lastSearchFilters.value,
      offset,
      limit,
    }

    console.log(`🎵 DEBUG: Loading more SoundCloud results offset ${offset}, limit ${limit}`)

    const response = await soundcloudService.searchWithPagination(filters)

    if (response.tracks && response.tracks.length > 0) {
      const limitedTracks = response.tracks.slice(0, limit)
      const filteredTracksApi = filterBannedArtists(limitedTracks)
      const uniqueTracks = dedupeTracks(filteredTracksApi)

      originalTracks.value = [...originalTracks.value, ...uniqueTracks]
      serverHasMore.value = response.hasMore || response.tracks.length >= limit

      const refreshedFiltered = getFilteredTracks()
      updateVisibleTracks(refreshedFiltered, true)

      // Trigger animations for newly added results
      if (tracks.value.length > currentVisibleCount) {
        setTimeout(() => {
          triggerRowAnimations(currentVisibleCount)
        }, 50)
      }
    } else {
      serverHasMore.value = false
      hasMoreResults.value = false
    }
  } catch (err: any) {
    console.error('🎵 Load more error:', err)
    error.value = 'Failed to load more tracks. Please try again.'
  } finally {
    loadingMore.value = false
  }
}

const playTrack = async (track: SoundCloudTrack) => {
  try {
    console.log('🎵 [SCREEN] Starting playTrack for:', track.title)
    console.log('🎵 [SCREEN] Track ID:', track.id)

    // Check if this track is already current and just paused
    const currentTrack = soundcloudPlayerStore.state.currentTrack
    if (currentTrack && currentTrack.id === track.id) {
      console.log('🎵 [SCREEN] Resuming current track')
      soundcloudPlayerStore.setPlaying(true)
      return
    }

    // Show player immediately with loading state
    soundcloudPlayerStore.show(track, '')
    console.log('🎵 [SCREEN] Player store shown, isVisible:', soundcloudPlayerStore.isVisible)

    // Update navigation state based on track position
    updateNavigationState(track)

    console.log('🎵 Loading SoundCloud player for track:', track.title)

    const embedUrl = await soundcloudService.getEmbedUrl(track.id, {
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true,
    })

    console.log('🎵 [DEBUG] Got embed URL:', embedUrl)
    soundcloudPlayerStore.setEmbedUrl(embedUrl)
    console.log('🎵 [DEBUG] Embed URL set in store')
    console.log('🎵 SoundCloud player loaded successfully')
  } catch (err: any) {
    console.error('🎵 [ERROR] Failed to load SoundCloud player:', err)
    error.value = `Failed to load SoundCloud player: ${err.message || 'Unknown error'}`
    soundcloudPlayerStore.hide() // Close player on error
  }
}

// Track navigation functions
const getCurrentTrackIndex = () => {
  const currentTrack = soundcloudPlayerStore.track
  if (!currentTrack) {
    return -1
  }
  return tracks.value.findIndex(track => track.id === currentTrack.id)
}

const updateNavigationState = (track: SoundCloudTrack) => {
  const currentIndex = tracks.value.findIndex(t => t.id === track.id)
  const canSkipPrevious = currentIndex > 0
  const canSkipNext = currentIndex >= 0 && currentIndex < tracks.value.length - 1

  soundcloudPlayerStore.setNavigationState(canSkipPrevious, canSkipNext)
  console.log('🎵 Navigation state updated:', { canSkipPrevious, canSkipNext, currentIndex, totalTracks: tracks.value.length })
}

const skipToPrevious = () => {
  const currentIndex = getCurrentTrackIndex()
  if (currentIndex > 0) {
    const previousTrack = tracks.value[currentIndex - 1]
    console.log('🎵 Skipping to previous track:', previousTrack.title)
    playTrack(previousTrack) // This will automatically update navigation state
  }
}

const skipToNext = () => {
  const currentIndex = getCurrentTrackIndex()
  if (currentIndex >= 0 && currentIndex < tracks.value.length - 1) {
    const nextTrack = tracks.value[currentIndex + 1]
    console.log('🎵 Skipping to next track:', nextTrack.title)
    playTrack(nextTrack) // This will automatically update navigation state
  }
}

const pauseTrack = (track?: SoundCloudTrack) => {
  console.log('🎵 [SCREEN] Pausing track:', track?.title || 'current')
  soundcloudPlayerStore.setPlaying(false)
}

const seekTrack = (position: number) => {
  console.log('🎵 Seeking to position:', `${position}%`)
  // Here you could implement actual seek functionality if needed
  // For now, this is just for UI feedback
}

const openRelatedTracks = (track: SoundCloudTrack) => {
  console.log('🎵 Opening Related Tracks for:', track.title)

  // Store the related tracks data for the next screen
  eventBus.emit('SOUNDCLOUD_RELATED_TRACKS_DATA', {
    type: 'related',
    trackUrn: `soundcloud:tracks:${track.id}`,
    trackTitle: track.title,
    artist: track.user?.username || 'Unknown Artist',
  })

  // Navigate to the SoundCloud Related Tracks screen using router
  Router.go('soundcloud-related-tracks')
}

// Load banned artists from localStorage (shared with Similar Artists and Recommendations)
const loadBannedArtists = () => {
  try {
    const stored = localStorage.getItem('koel-banned-artists')
    if (stored) {
      const bannedList = JSON.parse(stored)
      bannedArtists.value = new Set(bannedList)
      // console.log('SoundCloud: Loaded banned artists:', bannedList)
    }
  } catch (error) {
    console.warn('SoundCloud: Failed to load banned artists from localStorage:', error)
  }
}

// Save banned artists to localStorage
// Filter out tracks from banned artists (only applied to NEW search results)
const filterBannedArtists = (trackList: SoundCloudTrack[]) => {
  return trackList.filter(track => !bannedArtists.value.has(track.user?.username || ''))
}

// Remove duplicate tracks by id when appending results
const dedupeTracks = (incoming: SoundCloudTrack[]) => {
  const existingIds = new Set(originalTracks.value.map(track => track.id))
  return incoming.filter(track => {
    if (!track || track.id == null) {
      return false
    }
    if (existingIds.has(track.id)) {
      return false
    }
    existingIds.add(track.id)
    return true
  })
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedGenre.value = ''
  searchTags.value = ''
  bpmFrom.value = 95
  bpmTo.value = 172
  bpmFromInput.value = '95'
  bpmToInput.value = '172'
  timePeriod.value = ''
  artistFilter.value = ''
  minPlays.value = undefined
  maxPlays.value = undefined
  minPlaysFormatted.value = ''
  maxPlaysFormatted.value = ''
  contentType.value = 'tracks'
  likesRatioFilter.value = 'newest'
  showLikesRatioDropdown.value = false
  displayLimit.value = 20
  tracks.value = []
  originalTracks.value = []
  searched.value = false
  error.value = ''
  searchStats.value = null
  hasMoreResults.value = false
  serverHasMore.value = false
  lastSearchFilters.value = null
  loadingMore.value = false
  animationStartIndex.value = 0
}

const closePlayer = () => {
  soundcloudPlayerStore.hide()
}

const { onRouteChanged } = useRouter()

// Animation state management
const allowAnimations = ref(false)
const initialLoadComplete = ref(false)

// Template ref for SoundCloud table
const soundcloudTable = ref<{ closeInlinePlayer: () => void } | null>(null)

// Set up event bus listeners when component is mounted
onMounted(() => {
  // Listen for skip events from the footer player
  eventBus.on('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.on('SOUNDCLOUD_SKIP_NEXT', skipToNext)

  // Load banned artists and global blacklist items
  loadBannedArtists()
  loadBlacklistedItems()
})

// Close SoundCloud player when navigating away from this screen
onRouteChanged(route => {
  if (route.screen !== 'SoundCloud') {
    // Close any inline players first
    if (soundcloudTable.value?.closeInlinePlayer) {
      soundcloudTable.value.closeInlinePlayer()
    }
    // Then hide the global player
    soundcloudPlayerStore.hide()
  } else {
    // Enable animations when entering SoundCloud screen
    if (tracks.value.length > 0) {
      allowAnimations.value = true
      initialLoadComplete.value = false

      // Disable animations after they complete
      setTimeout(() => {
        allowAnimations.value = false
        initialLoadComplete.value = true
      }, 2000)
    }
  }
})

// Clean up player and event listeners when component is unmounted (user navigates away)
onBeforeUnmount(() => {
  closePlayer()

  // Remove event listeners
  eventBus.off('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.off('SOUNDCLOUD_SKIP_NEXT', skipToNext)
})
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

/* White BPM slider styling */
.bpm-slider-white :deep(.track-background) {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.bpm-slider-white :deep(.track-fill) {
  background: rgba(255, 255, 255, 0.6);
}

.bpm-slider-white :deep(.range-input::-webkit-slider-thumb) {
  background: white;
  border: 2px solid rgba(255, 255, 255, 0.8);
  margin-top: 0px;
}

.bpm-slider-white :deep(.range-input::-webkit-slider-thumb:hover) {
  background: rgba(255, 255, 255, 0.9);
  border-color: white;
  box-shadow: 0 4px 8px rgba(255, 255, 255, 0.3);
}

.bmp-slider-white :deep(.range-input::-moz-range-thumb) {
  background: white;
  border: 2px solid rgba(255, 255, 255, 0.8);
  margin-top: 0px;
}

/* Ensure SoundCloud iframe is fully interactive */
iframe {
  pointer-events: auto !important;
  user-select: auto !important;
  -webkit-user-select: auto !important;
  -moz-user-select: auto !important;
  -ms-user-select: auto !important;
}

/* Fix any potential overlay issues */
.soundcloud-player-container {
  position: relative;
  z-index: 1;
  pointer-events: auto !important;
}

.soundcloud-player-container iframe {
  position: relative;
  z-index: 2;
}
</style>
