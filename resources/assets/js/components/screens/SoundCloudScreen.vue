<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>Enhanced SoundCloud Search</ScreenHeader>
    </template>

    <!-- Header -->
    <div class="flex justify-center items-center gap-4 mb-8 -mt-6">
      <img src="/public/img/soundcloud-ar21.svg" alt="SoundCloud" class="w-36 h-auto" />
      <h2 class="text-4xl font-thin mt-4" style="font-weight: 100;">Advanced Search</h2>
    </div>

    <div class="p-6 space-y-6">
      <!-- Enhanced Search Controls -->
      <div class="bg-white/5 rounded-lg p-4">
        <!-- Main Layout: Search Controls Left, Advanced Filters Right -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Left Side: Search Controls (50% width) -->
          <div class="space-y-4">
            <!-- Search Query Row -->
            <div>
              <label class="block text-sm font-medium mb-2 text-white/80">Search Keywords</label>
              <input
                v-model="searchQuery"
                type="text"
                class="w-full p-3 bg-white/10 rounded focus:outline-none text-white text-lg"
                placeholder="Search for artists, tracks, albums..."
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
              <div class="mt-1 text-xs text-white/50">
                Press Enter to search or use the search button below
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
                  class="w-full p-2 bg-white/10 rounded focus:outline-none text-white"
                  placeholder="Type genre..."
                >
              </div>

              <!-- Tags Input -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Tags</label>
                <input
                  v-model="searchTags"
                  type="text"
                  class="w-full p-2 bg-white/10 rounded focus:outline-none text-white"
                  placeholder="Add another genre, style, or characteristic"
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
                  class="w-full p-2 bg-white/10 rounded focus:outline-none text-white"
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
                  <label class="block text-sm font-medium mb-2 text-white/80">Minimum Plays</label>
                  <input
                    v-model="minPlaysFormatted"
                    type="text"
                    placeholder="e.g. 10,000"
                    class="w-full p-2 bg-white/10 rounded focus:outline-none text-white"
                    @input="handleMinPlaysInput"
                  >
                </div>

                <!-- Maximum Plays -->
                <div>
                  <label class="block text-sm font-medium mb-2 text-white/80">Maximum Plays</label>
                  <input
                    v-model="maxPlaysFormatted"
                    type="text"
                    placeholder="e.g. 1,000,000"
                    class="w-full p-2 bg-white/10 rounded focus:outline-none text-white"
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
            <Icon v-if="loading" :icon="faSpinner" spin />
            <Icon v-else :icon="faSearch" />
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

        <!-- Filter Status -->
        <div v-if="hasValidFilters" class="mt-3 text-center">
          <div class="inline-flex items-center gap-2 text-sm text-white/60">
            <Icon :icon="faFilter" />
            Filters:
            <span v-if="searchQuery" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              "{{ searchQuery.length > 20 ? `${searchQuery.substring(0, 20)}...` : searchQuery }}"
            </span>
            <span v-if="selectedGenre" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              {{ selectedGenre }}
            </span>
            <span v-if="searchTags" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              {{ searchTags.length > 20 ? `${searchTags.substring(0, 20)}...` : searchTags }}
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

      <!-- Loading Animation -->
      <div v-if="loading" class="bg-white/5 rounded-lg p-6">
        <div class="flex items-center justify-center py-8">
          <div class="flex items-center gap-3">
            <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
            <span class="text-k-text-secondary">Searching SoundCloud...</span>
          </div>
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
            <Icon :icon="getSortIcon()" />
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
              :class="likesRatioFilter === 'none' ? 'background-color: rgb(67,67,67,255)' : ''"
              :style="likesRatioFilter === 'none' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('none')"
            >
              <Icon :icon="faFilter" />
              Default (by Plays)
            </button>
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
              :class="likesRatioFilter === 'highest' ? 'background-color: rgb(67,67,67,255)' : ''"
              :style="likesRatioFilter === 'highest' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('highest')"
            >
              <Icon :icon="faArrowUp" />
              Highest Likes Ratio
            </button>
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2 rounded-b-lg"
              :class="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
              :style="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('newest')"
            >
              <Icon :icon="faClock" />
              Newest Releases
            </button>
          </div>
        </div>

        <SoundCloudTrackTable
          ref="soundcloudTable"
          :tracks="tracks"
          :start-index="(currentPage - 1) * 20"
          :allow-animations="allowAnimations"
          @play="playTrack"
          @pause="pauseTrack"
          @seek="seekTrack"
          @related-tracks="openRelatedTracks"
          @ban-artist="banArtist"
        />

        <!-- Pagination Controls -->
        <div v-if="searched && tracks.length > 0" class="pagination-section flex items-center justify-center gap-2 mt-8">
          <button
            :disabled="currentPage <= 1 || loadingMore"
            class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage <= 1 || loadingMore }"
            @click="goToPage(currentPage - 1)"
          >
            Previous
          </button>

          <div class="flex items-center gap-1">
            <button
              v-for="page in visiblePages"
              :key="page"
              :class="page === currentPage ? 'bg-k-accent text-white' : 'bg-k-bg-primary text-gray-300 hover:bg-white/10'"
              class="w-10 h-10 flex items-center justify-center rounded transition-colors"
              :disabled="loadingMore"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
          </div>

          <button
            :disabled="!hasMoreResults || loadingMore"
            class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'opacity-50 cursor-not-allowed': !hasMoreResults || loadingMore }"
            @click="goToPage(currentPage + 1)"
          >
            <Icon v-if="loadingMore" :icon="faSpinner" spin class="mr-1" />
            {{ loadingMore ? 'Loading...' : 'Next' }}
          </button>
        </div>

        <!-- Page Info -->
        <div v-if="searched && tracks.length > 0" class="text-center mt-3">
          <div class="text-sm text-white/60">
            Page {{ currentPage }} • {{ tracks.length }} tracks
            <span v-if="hasMoreResults"> • More pages available</span>
          </div>
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
import { faArrowDown, faArrowUp, faChartLine, faChevronDown, faChevronLeft, faChevronRight, faClock, faExclamationTriangle, faFilter, faPlay, faPlus, faSearch, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from '@/composables/useRouter'
import { type SoundCloudFilters, soundcloudService, type SoundCloudTrack } from '@/services/soundcloudService'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { eventBus } from '@/utils/eventBus'
import Router from '@/router'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { http } from '@/services/http'

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
const minPlays = ref<number>()
const maxPlays = ref<number>()
const minPlaysFormatted = ref('')
const maxPlaysFormatted = ref('')
const contentType = ref('tracks') // Default to tracks
const likesRatioFilter = ref<'none' | 'highest' | 'newest'>('none') // Sort filter state
const showLikesRatioDropdown = ref(false) // Dropdown visibility

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
const currentPage = ref(1)
const totalPages = ref(1)
const lastSearchFilters = ref<SoundCloudFilters | null>(null)

// Page caching to avoid re-fetching visited pages
const pageCache = ref<Map<number, SoundCloudTrack[]>>(new Map())
const pageCacheKey = ref<string>('')

// Banned artists tracking (shared with Similar Artists and Recommendations)
const bannedArtists = ref(new Set<string>()) // Store artist names

// Initialize global blacklist filtering composable
const { 
  addArtistToBlacklist,
  loadBlacklistedItems 
} = useBlacklistFiltering()

// Player state (now using global store)
// Note: Player will show in footer when this component is mounted (SoundCloud page)

// Computed properties
const hasValidFilters = computed(() => {
  const hasQuery = searchQuery.value?.trim()
  const hasGenre = selectedGenre.value && selectedGenre.value !== 'All Genres' && selectedGenre.value !== ''
  const hasTags = searchTags.value?.trim()
  const hasBPM = (bpmFrom.value !== 95 || bpmTo.value !== 172)
  const hasAdvanced = minPlays.value || maxPlays.value || (contentType.value !== 'tracks')
  const hasTimePeriod = timePeriod.value && timePeriod.value !== ''

  return hasQuery || hasGenre || hasTags || hasBPM || hasAdvanced || hasTimePeriod
})

const searchButtonText = computed(() => {
  if (loading.value) {
    return 'Searching...'
  }
  if (searchStats.value) {
    return `Search`
  }
  return 'Search'
})

// Pagination computed properties
const visiblePages = computed(() => {
  const pages = []
  const maxVisiblePages = 5
  let startPage = Math.max(1, currentPage.value - Math.floor(maxVisiblePages / 2))
  const endPage = Math.min(totalPages.value, startPage + maxVisiblePages - 1)

  // Adjust start page if we're near the end
  if (endPage - startPage + 1 < maxVisiblePages) {
    startPage = Math.max(1, endPage - maxVisiblePages + 1)
  }

  for (let i = startPage; i <= endPage; i++) {
    pages.push(i)
  }

  return pages
})

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

const setLikesRatioFilter = (type: 'none' | 'highest' | 'newest') => {
  likesRatioFilter.value = type
  showLikesRatioDropdown.value = false
  applyFiltering()
}

const getSortIcon = () => {
  switch (likesRatioFilter.value) {
    case 'highest': return faArrowUp
    case 'newest': return faClock
    default: return faFilter
  }
}

const getSortText = () => {
  switch (likesRatioFilter.value) {
    case 'highest': return 'Highest Likes Ratio'
    case 'newest': return 'Newest Releases'
    default: return 'Sort by: Plays'
  }
}

const applyFiltering = () => {
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
    console.log('🔍 Applying plays filter with tracks:', filteredTracks.slice(0, 3).map(t => ({
      title: t?.title,
      plays: t?.playback_count,
    })))

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

  // Then apply sorting
  if (likesRatioFilter.value === 'highest') {
    // Sort by likes ratio highest to lowest
    filteredTracks.sort((a, b) => {
      const ratioA = (a.playback_count || 0) > 0 ? (a.favoritings_count || 0) / (a.playback_count || 0) : 0
      const ratioB = (b.playback_count || 0) > 0 ? (b.favoritings_count || 0) / (b.playback_count || 0) : 0
      return ratioB - ratioA
    })
  } else if (likesRatioFilter.value === 'newest') {
    // Sort by creation date newest to oldest
    filteredTracks.sort((a, b) => {
      const dateA = new Date(a.created_at || 0).getTime()
      const dateB = new Date(b.created_at || 0).getTime()
      return dateB - dateA
    })
  } else {
    // Default sort by plays (highest to lowest)
    filteredTracks.sort((a, b) => (b.playback_count || 0) - (a.playback_count || 0))
  }

  // Update displayed tracks - show all filtered tracks
  tracks.value = filteredTracks
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

// Generate a cache key based on search filters
const generateCacheKey = (filters: SoundCloudFilters): string => {
  const keyParts = [
    filters.searchQuery || '',
    filters.searchTags || '',
    filters.genre || '',
    filters.bpmFrom || '',
    filters.bpmTo || '',
    filters.durationFrom || '',
    filters.durationTo || '',
    filters.minPlays || '',
    filters.maxPlays || '',
    filters.timePeriod || '',
    likesRatioFilter.value || 'none',
  ]
  return keyParts.join('|')
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

  currentPage.value = 1

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
      limit: 20,
    }

    // Store filters for pagination
    lastSearchFilters.value = filters

    // Generate cache key and clear cache for new search
    pageCacheKey.value = generateCacheKey(filters)
    pageCache.value.clear()

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

    // Limit to exactly 20 tracks per page (SoundCloud API sometimes returns more)
    const limitedTracks = response.tracks.slice(0, 20)
    
    // Filter out banned artists from NEW search results
    const filteredTracks = filterBannedArtists(limitedTracks)
    console.log(`🚫 SoundCloud: Filtered ${limitedTracks.length - filteredTracks.length} banned artists from new search results`)

    // Cache page 1 results (filtered)
    pageCache.value.set(1, filteredTracks)

    originalTracks.value = filteredTracks
    tracks.value = filteredTracks

    console.log('🎵 DEBUG: After storing:', {
      originalTracksCount: originalTracks.value.length,
      tracksCount: tracks.value.length,
      minPlays: minPlays.value,
      maxPlays: maxPlays.value,
      willFilter: !!(minPlays.value || maxPlays.value),
    })

    // Apply current filtering to all tracks
    applyFiltering()

    // Check if SoundCloud has more pages available - if we got more than 20 tracks, there are definitely more pages
    hasMoreResults.value = response.hasMore || response.tracks.length > 20

    // Set up pagination state
    if (hasMoreResults.value) {
      totalPages.value = 2 // At least 2 pages since we have more
    } else {
      totalPages.value = 1 // Only 1 page
    }

    console.log('🎵 Search complete:', {
      totalTracks: tracks.value.length,
      hasMorePages: hasMoreResults.value,
      currentPage: currentPage.value,
      estimatedTotalPages: totalPages.value,
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
      allowAnimations.value = true
      initialLoadComplete.value = true
      
      // Auto-disable animations after 2 seconds
      setTimeout(() => {
        allowAnimations.value = false
      }, 2000)
    }, 50)
  } catch (err: any) {
    error.value = err.message || 'Failed to search SoundCloud. Please try again.'
    tracks.value = []
    originalTracks.value = []
  } finally {
    loading.value = false
  }
}

const goToPage = async (page: number) => {
  if (!lastSearchFilters.value || page < 1 || page === currentPage.value) {
    return
  }

  // Check if page is cached first
  if (pageCache.value.has(page)) {
    console.log(`🎵 Navigation: Loading page ${page} from cache`)
    const cachedTracks = pageCache.value.get(page)!

    // Load from cache instantly
    originalTracks.value = cachedTracks
    tracks.value = cachedTracks
    currentPage.value = page

    // Apply current filtering to cached tracks
    applyFiltering()

    console.log(`🎵 Page ${page}: Loaded ${tracks.value.length} tracks from cache`)
    return
  }

  loadingMore.value = true
  error.value = ''

  try {
    console.log(`🎵 Navigation: Fetching page ${page} from API`)

    // Calculate offset based on page number (20 tracks per page)
    const offset = (page - 1) * 20

    const filters = {
      ...lastSearchFilters.value,
      offset,
      limit: 20,
    }

    console.log(`🎵 DEBUG: Requesting page ${page} with filters:`, { offset, limit: 20, query: filters.searchQuery })

    const response = await soundcloudService.searchWithPagination(filters)

    if (response.tracks && response.tracks.length > 0) {
      // Limit to exactly 20 tracks per page (SoundCloud API sometimes returns more)
      const limitedTracks = response.tracks.slice(0, 20)
      
      // Filter out banned artists from NEW page results
      const filteredTracks = filterBannedArtists(limitedTracks)
      console.log(`🚫 SoundCloud Page ${page}: Filtered ${limitedTracks.length - filteredTracks.length} banned artists`)

      console.log(`🎵 DEBUG Page ${page}: First 3 track titles:`, filteredTracks.slice(0, 3).map(t => t.title))
      console.log(`🎵 DEBUG Page ${page}: Track IDs:`, filteredTracks.slice(0, 3).map(t => t.id))

      // Cache the newly fetched page (filtered)
      pageCache.value.set(page, filteredTracks)

      // Replace current tracks with new page tracks (clean slate)
      originalTracks.value = filteredTracks
      tracks.value = filteredTracks
      currentPage.value = page

      // Apply current filtering to new page tracks
      applyFiltering()

      // Update pagination state - if we got more than 20 tracks, there are definitely more pages
      hasMoreResults.value = response.hasMore || response.tracks.length > 20

      // Estimate total pages (this is approximate since we don't know exact total)
      if (hasMoreResults.value) {
        totalPages.value = Math.max(totalPages.value, page + 1)
      } else {
        totalPages.value = page
      }

      console.log(`🎵 Page ${page}: Showing ${tracks.value.length} tracks`)

      // Trigger animations for new page results (only if not from cache)
      setTimeout(() => {
        allowAnimations.value = true
        
        // Auto-disable animations after 2 seconds
        setTimeout(() => {
          allowAnimations.value = false
        }, 2000)
      }, 50)
    } else {
      hasMoreResults.value = false
      totalPages.value = Math.max(1, page - 1)
      console.log(`🎵 Page ${page}: No tracks found`)
    }
  } catch (err: any) {
    console.error('🎵 Page Navigation Error:', err)
    error.value = 'Failed to load page. Please try again.'
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

// Helper function to check if an artist is banned
const isArtistBanned = (track: SoundCloudTrack): boolean => {
  return bannedArtists.value.has(track.user?.username || '')
}

// Ban/Unban an artist (toggle banned state)  
const banArtist = async (track: SoundCloudTrack) => {
  const artistName = track.user?.username || 'Unknown Artist'
  const isCurrentlyBanned = isArtistBanned(track)
  
  try {
    console.log(`${isCurrentlyBanned ? '✅ Unbanning' : '🚫 Banning'} SoundCloud artist:`, artistName)

    if (isCurrentlyBanned) {
      // UNBAN ARTIST - immediate UI update, background API removal
      bannedArtists.value.delete(artistName)
      
      // Save to localStorage immediately
      saveBannedArtists()
      
      // Background API call to remove from blacklist
      try {
        const deleteData = {
          artist_name: artistName,
          spotify_artist_id: `soundcloud:${track.user?.id || track.id}`
        }
        const params = new URLSearchParams(deleteData)
        const response = await http.delete(`music-preferences/blacklist-artist?${params}`)
        console.log('✅ SoundCloud artist removed from global blacklist API:', response)
      } catch (apiError: any) {
        console.error('❌ Failed to remove from API:', apiError)
        // Revert local state if API call fails
        bannedArtists.value.add(artistName)
        saveBannedArtists()
        error.value = `Failed to unban artist: ${apiError.response?.data?.message || apiError.message}`
      }
    } else {
      // BAN ARTIST - immediate UI update, background API save
      bannedArtists.value.add(artistName)
      
      // Save to localStorage immediately
      saveBannedArtists()
      
      // Add to global blacklist (affects other sections)
      addArtistToBlacklist(artistName)
      
      // Background API call to save to blacklist
      try {
        const response = await http.post('music-preferences/blacklist-artist', {
          artist_name: artistName,
          spotify_artist_id: `soundcloud:${track.user?.id || track.id}`,
        })
        console.log('✅ SoundCloud artist saved to global blacklist API:', response)
      } catch (apiError: any) {
        console.error('❌ Failed to save to API:', apiError)
        // Revert local state if API call fails
        bannedArtists.value.delete(artistName)
        saveBannedArtists()
        error.value = `Failed to ban artist: ${apiError.response?.data?.message || apiError.message}`
      }
    }

    // NOTE: We do NOT remove from current results - artists stay visible until next search
    // The filtering happens in the search() function for new searches

    console.log(`${isCurrentlyBanned ? '✅ Unbanned' : '🚫 Banned'} SoundCloud artist "${artistName}" - stays visible in current results`)
  } catch (error: any) {
    console.error(`Failed to ${isCurrentlyBanned ? 'unban' : 'ban'} SoundCloud artist:`, error)
    error.value = `Failed to ${isCurrentlyBanned ? 'unban' : 'ban'} artist: ${error.message || 'Unknown error'}`
  }
}

// Load banned artists from localStorage (shared with Similar Artists and Recommendations)
const loadBannedArtists = () => {
  try {
    const stored = localStorage.getItem('koel-banned-artists')
    if (stored) {
      const bannedList = JSON.parse(stored)
      bannedArtists.value = new Set(bannedList)
      console.log('SoundCloud: Loaded banned artists:', bannedList)
    }
  } catch (error) {
    console.warn('SoundCloud: Failed to load banned artists from localStorage:', error)
  }
}

// Save banned artists to localStorage
const saveBannedArtists = () => {
  try {
    const bannedList = Array.from(bannedArtists.value)
    localStorage.setItem('koel-banned-artists', JSON.stringify(bannedList))
  } catch (error) {
    console.warn('SoundCloud: Failed to save banned artists to localStorage:', error)
  }
}

// Filter out tracks from banned artists (only applied to NEW search results)
const filterBannedArtists = (trackList: SoundCloudTrack[]) => {
  return trackList.filter(track => !bannedArtists.value.has(track.user?.username || ''))
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
  minPlays.value = undefined
  maxPlays.value = undefined
  minPlaysFormatted.value = ''
  maxPlaysFormatted.value = ''
  contentType.value = 'tracks'
  likesRatioFilter.value = 'none'
  showLikesRatioDropdown.value = false
  tracks.value = []
  originalTracks.value = []
  searched.value = false
  error.value = ''
  searchStats.value = null
  hasMoreResults.value = false
  currentPage.value = 1
  totalPages.value = 1
  lastSearchFilters.value = null

  // Clear page cache when resetting filters
  pageCache.value.clear()
  pageCacheKey.value = ''
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
