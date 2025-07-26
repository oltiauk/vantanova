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

        <!-- Search Query Row -->
        <div class="mb-4">
          <label class="block text-sm font-medium mb-2 text-white/80">Search Keywords</label>
          <input
            v-model="searchQuery"
            type="text"
            class="w-full p-3 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white text-lg"
            placeholder="Search for artists, tracks, albums..."
            @keyup.enter="search"
          />
          <div class="mt-1 text-xs text-white/50">
            Press Enter to search or use the search button below
          </div>
        </div>

        <!-- Top Row: Genres and Tags -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <!-- Genres Input/Dropdown -->
          <div class="relative">
            <label class="block text-sm font-medium mb-2 text-white/80">Genre</label>
            <input
              v-model="selectedGenre"
              type="text"
              class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white"
              placeholder="Type genre or select from suggestions..."
              @focus="showGenreDropdown = true"
              @blur="hideGenreDropdown"
            />
            <div 
              v-if="showGenreDropdown && filteredGenres.length > 0"
              class="absolute z-10 w-full mt-1 bg-gray-800 border border-white/20 rounded max-h-48 overflow-y-auto"
            >
              <button
                v-for="genre in filteredGenres"
                :key="genre"
                @mousedown.prevent="selectGenre(genre)"
                class="w-full px-3 py-2 text-left text-white hover:bg-k-accent/20 transition"
              >
                {{ genre }}
              </button>
            </div>
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
                  v-model="bpmFromInput"
                  @input="handleBpmFromInput"
                  type="number"
                  min="60"
                  max="200"
                  placeholder="Min BPM"
                  class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white text-sm"
                />
                <input
                  v-model="bpmToInput"
                  @input="handleBpmToInput"
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
          <!-- Track vs Mix Selection -->
          <div>
            <label class="block text-sm font-medium mb-3 text-white/80">Content Type</label>
            <div class="flex gap-3">
              <label class="flex-1 cursor-pointer">
                <input
                  v-model="contentType"
                  type="radio"
                  value="tracks"
                  class="sr-only"
                />
                <div class="px-4 py-2.5 rounded-lg border-2 transition-all duration-200 text-center text-sm font-medium"
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
                />
                <div class="px-4 py-2.5 rounded-lg border-2 transition-all duration-200 text-center text-sm font-medium"
                     :class="contentType === 'mixes' 
                       ? 'border-k-accent bg-k-accent/20 text-white' 
                       : 'border-white/20 bg-white/5 text-white/70 hover:border-white/30 hover:bg-white/10'"
                >
                  Mixes
                </div>
              </label>
            </div>
          </div>

          <!-- Minimum Plays -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Minimum Plays</label>
            <input
              v-model="minPlaysFormatted"
              type="text"
              placeholder="e.g. 10,000"
              @input="handleMinPlaysInput"
              class="w-full p-2 bg-white/10 rounded border border-white/20 focus:border-k-accent text-white"
            />
          </div>

          <!-- Maximum Plays -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Maximum Plays</label>
            <input
              v-model="maxPlaysFormatted"
              type="text"
              placeholder="e.g. 1,000,000"
              @input="handleMaxPlaysInput"
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
            <span v-if="searchQuery" class="bg-k-accent/20 text-k-accent px-2 py-1 rounded text-xs">
              "{{ searchQuery.length > 20 ? searchQuery.substring(0, 20) + '...' : searchQuery }}"
            </span>
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
          @view-artist="viewArtist"
        />
        
        <!-- Load More Button -->
        <div v-if="hasMoreResults" class="text-center mt-6">
          <button
            @click="loadMore"
            :disabled="loadingMore"
            class="px-6 py-3 bg-white/10 hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-medium transition flex items-center gap-2 mx-auto"
          >
            <Icon v-if="loadingMore" :icon="faSpinner" spin />
            <Icon v-else :icon="faPlus" />
            {{ loadingMore ? 'Loading...' : 'Load More' }}
          </button>
        </div>
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

    <!-- Artist Details Modal -->
    <teleport to="body">
      <div
        v-if="showArtistModal"
        class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
        @click="closeArtistModal"
      >
        <div
          class="bg-k-bg-secondary rounded-lg p-6 max-w-2xl w-full max-h-[80vh] overflow-auto"
          @click.stop
        >
          <div class="flex justify-between items-center mb-4">
            <div>
              <h3 class="text-xl font-semibold text-white">{{ currentArtist?.username }}</h3>
              <p class="text-white/60">Artist Details</p>
            </div>
            <button
              @click="closeArtistModal"
              class="p-2 hover:bg-white/10 rounded-lg transition"
            >
              <Icon :icon="faTimes" class="text-white" />
            </button>
          </div>
          
          <div v-if="loadingArtist" class="text-center py-8">
            <Icon :icon="faSpinner" spin class="text-2xl text-k-accent mb-2" />
            <p class="text-white/60">Loading artist details...</p>
          </div>
          
          <div v-else-if="artistDetails" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-white/5 p-4 rounded">
                <div class="text-k-accent font-semibold">Followers</div>
                <div class="text-2xl text-white">{{ formatCount(artistDetails.followers_count || 0) }}</div>
              </div>
              <div class="bg-white/5 p-4 rounded">
                <div class="text-k-accent font-semibold">Following</div>
                <div class="text-2xl text-white">{{ formatCount(artistDetails.followings_count || 0) }}</div>
              </div>
              <div class="bg-white/5 p-4 rounded">
                <div class="text-k-accent font-semibold">Tracks</div>
                <div class="text-2xl text-white">{{ formatCount(artistDetails.track_count || 0) }}</div>
              </div>
              <div class="bg-white/5 p-4 rounded">
                <div class="text-k-accent font-semibold">Playlists</div>
                <div class="text-2xl text-white">{{ formatCount(artistDetails.playlist_count || 0) }}</div>
              </div>
            </div>
            
            <div v-if="artistDetails.description" class="bg-white/5 p-4 rounded">
              <div class="text-k-accent font-semibold mb-2">About</div>
              <div class="text-white/80 whitespace-pre-wrap">{{ artistDetails.description }}</div>
            </div>
            
            <div class="flex gap-2">
              <a
                v-if="artistDetails.permalink_url"
                :href="artistDetails.permalink_url"
                target="_blank"
                class="px-4 py-2 bg-k-accent hover:bg-k-accent/80 rounded text-white font-medium transition"
              >
                View on SoundCloud
              </a>
              <a
                v-if="artistDetails.website"
                :href="artistDetails.website"
                target="_blank"
                class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded text-white font-medium transition"
              >
                {{ artistDetails.website_title || 'Website' }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </teleport>

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
import { faPlay, faSearch, faSpinner, faTimes, faFilter, faChartLine, faExclamationTriangle, faPlus } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { ref, computed, watch } from 'vue'
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

// Results state
const tracks = ref<SoundCloudTrack[]>([])
const allFetchedTracks = ref<SoundCloudTrack[]>([]) // Store all 100 tracks from API
const displayedTrackCount = ref(20) // How many tracks we're currently showing
const loading = ref(false)
const searched = ref(false)
const error = ref('')
const searchStats = ref<SearchStats | null>(null)

// Pagination state
const loadingMore = ref(false)
const hasMoreResults = ref(false)
const currentOffset = ref(0)
const lastSearchFilters = ref<SoundCloudFilters | null>(null)

// Player state
const showPlayer = ref(false)
const currentTrack = ref<SoundCloudTrack | null>(null)
const embedUrl = ref('')

// Artist modal state
const showArtistModal = ref(false)
const currentArtist = ref<any>(null)
const artistDetails = ref<any>(null)
const loadingArtist = ref(false)

// Advanced filters toggle
const showAdvancedFilters = ref(false)

// Genre dropdown state
const showGenreDropdown = ref(false)

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
  if (loading.value) return 'Searching...'
  if (searchStats.value) {
    return `Search`
  }
  return 'Search'
})

const filteredGenres = computed(() => {
  if (!selectedGenre.value) {
    return genres
  }
  return genres.filter(genre => 
    genre.toLowerCase().includes(selectedGenre.value.toLowerCase())
  )
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

const selectGenre = (genre: string) => {
  selectedGenre.value = genre
  showGenreDropdown.value = false
}

const hideGenreDropdown = () => {
  setTimeout(() => {
    showGenreDropdown.value = false
  }, 150) // Small delay to allow click events to register
}

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

// Number formatting helpers for plays inputs
const formatNumberWithCommas = (num: number): string => {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

const parseNumberFromFormatted = (str: string): number | undefined => {
  const cleaned = str.replace(/[,\s]/g, '')
  const num = parseInt(cleaned, 10)
  return isNaN(num) ? undefined : num
}

const handleMinPlaysInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value
  const cursorPosition = target.selectionStart || 0
  
  // Remove all non-digits first
  const digitsOnly = value.replace(/[^0-9]/g, '')
  
  if (digitsOnly === '') {
    minPlaysFormatted.value = ''
    minPlays.value = undefined
    return
  }
  
  // Parse and format with commas
  const num = parseInt(digitsOnly, 10)
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
  const digitsOnly = value.replace(/[^0-9]/g, '')
  
  if (digitsOnly === '') {
    maxPlaysFormatted.value = ''
    maxPlays.value = undefined
    return
  }
  
  // Parse and format with commas
  const num = parseInt(digitsOnly, 10)
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
    const numValue = parseInt(value, 10)
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
    const numValue = parseInt(value, 10)
    if (!isNaN(numValue) && numValue >= 60 && numValue <= 200) {
      // Ensure max doesn't go below min with some gap
      bpmTo.value = Math.max(numValue, bpmFrom.value + 5)
    }
    // Don't reset to default when empty - let user type freely
  }, 500) // 500ms debounce
}

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
  // console.log('🎵 DEBUG: search() function called')
  // console.log('🎵 DEBUG: hasValidFilters.value:', hasValidFilters.value)
  // console.log('🎵 DEBUG: selectedGenre.value:', selectedGenre.value)
  
  if (!hasValidFilters.value) {
    // console.log('🎵 DEBUG: Blocking search - no valid filters')
    error.value = 'Please select at least one filter to search with enhanced features.'
    return
  }

  loading.value = true
  error.value = ''
  tracks.value = []
  searchStats.value = null
  hasMoreResults.value = false
  currentOffset.value = 0

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
      limit: 20
    }

    // console.log('🎵 Enhanced SoundCloud Search - Filters:', filters)

    // Store filters for pagination
    lastSearchFilters.value = filters

    const response = await soundcloudService.searchWithPagination(filters)
    const endTime = performance.now()
    
    // Store all tracks from single API call
    allFetchedTracks.value = response.tracks
    
    // Display only first 20 tracks  
    displayedTrackCount.value = 20
    tracks.value = allFetchedTracks.value.slice(0, displayedTrackCount.value)
    
    // Check if we have more tracks OR if SoundCloud has more via next_href
    hasMoreResults.value = allFetchedTracks.value.length > displayedTrackCount.value || response.hasMore
    
    console.log('🎵 DEBUG Load More button:', {
      allFetchedCount: allFetchedTracks.value.length,
      displayedCount: displayedTrackCount.value,
      hasMoreResults: hasMoreResults.value
    })
    
    // Calculate search statistics - now using single API call
    const resultQuality: SearchStats['resultQuality'] = 
      allFetchedTracks.value.length > 15 ? 'high' : 
      allFetchedTracks.value.length > 5 ? 'medium' : 'low'
    
    searchStats.value = {
      apiCalls: response.apiCalls || 1, // Use actual API call count
      totalTime: Math.round(endTime - startTime),
      cacheHits: 0, // TODO: implement cache hit tracking
      resultQuality
    }

    console.log(`🎵 Search complete: ${allFetchedTracks.value.length} tracks fetched, showing first ${tracks.value.length}`)
    currentOffset.value = tracks.value.length
    searched.value = true

    if (tracks.value.length === 0) {
      error.value = 'No tracks found with your criteria. The enhanced search uses intersection of results for higher quality - try broadening your filters.'
    }
  } catch (err: any) {
    // console.error('🎵 Enhanced SoundCloud Search - Error:', err)
    error.value = err.message || 'Failed to search SoundCloud. Please try again.'
    tracks.value = []
  } finally {
    loading.value = false
  }
}

const loadMore = async () => {
  if (!lastSearchFilters.value) return
  
  loadingMore.value = true
  error.value = ''

  try {
    // First, check if we have more cached tracks to show
    if (displayedTrackCount.value < allFetchedTracks.value.length) {
      console.log('🎵 Load More: Showing cached tracks')
      
      // Show 20 more tracks from cache
      displayedTrackCount.value = Math.min(displayedTrackCount.value + 20, allFetchedTracks.value.length)
      tracks.value = allFetchedTracks.value.slice(0, displayedTrackCount.value)
      
      // Still more cached tracks or can fetch more from API?
      hasMoreResults.value = displayedTrackCount.value < allFetchedTracks.value.length || 
                             (lastSearchFilters.value && currentOffset.value < 100)
      
      console.log(`🎵 Load More: Now showing ${tracks.value.length} of ${allFetchedTracks.value.length} cached tracks`)
      
    } else {
      // Need to fetch more tracks from API
      console.log('🎵 Load More: Fetching more tracks from API')
      
      const filters = {
        ...lastSearchFilters.value,
        offset: allFetchedTracks.value.length // Use current total as offset
      }

      const response = await soundcloudService.searchWithPagination(filters)
      
      if (response.tracks && response.tracks.length > 0) {
        // Add new tracks to our cache
        allFetchedTracks.value.push(...response.tracks)
        
        // Show next 20 tracks (which includes the new ones)
        displayedTrackCount.value = Math.min(displayedTrackCount.value + 20, allFetchedTracks.value.length)
        tracks.value = allFetchedTracks.value.slice(0, displayedTrackCount.value)
        
        // Check if more tracks are available
        hasMoreResults.value = response.hasMore
        
        console.log(`🎵 Load More: Fetched ${response.tracks.length} new tracks, now showing ${tracks.value.length} total`)
      } else {
        hasMoreResults.value = false
        console.log('🎵 Load More: No more tracks available')
      }
    }
    
    currentOffset.value = tracks.value.length

  } catch (err: any) {
    console.error('🎵 Load More Error:', err)
    error.value = 'Failed to load more results. Please try again.'
  } finally {
    loadingMore.value = false
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
  tracks.value = []
  allFetchedTracks.value = []
  displayedTrackCount.value = 20
  searched.value = false
  error.value = ''
  searchStats.value = null
  showAdvancedFilters.value = false
  hasMoreResults.value = false
  currentOffset.value = 0
  lastSearchFilters.value = null
}

const viewArtist = async (user: any) => {
  currentArtist.value = user
  showArtistModal.value = true
  loadingArtist.value = true
  artistDetails.value = null

  try {
    const details = await soundcloudService.getUserDetails(user.id)
    artistDetails.value = details
  } catch (err) {
    // console.error('Failed to load artist details:', err)
    // Fall back to basic user data
    artistDetails.value = user
  } finally {
    loadingArtist.value = false
  }
}

const closeArtistModal = () => {
  showArtistModal.value = false
  currentArtist.value = null
  artistDetails.value = null
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