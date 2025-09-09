<template>
  <div class="recommendations-table">
    <!-- Header -->
    <div v-if="recommendations.length > 0 || isDiscovering" class="mb-6">
      <div class="flex justify-between items-center mb-4">
        <!-- <h3 class="text-lg font-medium text-white">
          {{ isDiscovering ? 'Searching...' : 'Related Tracks' }}
        </h3> -->
      </div>
      
    </div>

    <!-- Loading State -->
    <div v-if="isDiscovering" class="text-center p-12">
      <div class="inline-flex flex-col items-center">
        <svg class="w-8 h-8 animate-spin text-[#9d0cc6] mb-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage && !isDiscovering" class="bg-red-500/20 border border-red-500/40 rounded-lg p-4 mb-6">
      <div class="flex items-start gap-3">
        <Icon :icon="faExclamationTriangle" class="text-red-400 mt-0.5" />
        <div>
          <h4 class="font-medium text-red-200 mb-1">Discovery Failed</h4>
          <p class="text-red-200">{{ errorMessage }}</p>
        </div>
        <button
          @click="$emit('clearError')"
          class="ml-auto text-red-400 hover:text-red-300"
        >
          <Icon :icon="faTimes" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Recommendations Table -->
    <div v-if="recommendations.length > 0 && !isDiscovering">
      <!-- Controls -->
      <div class="mb-4 flex justify-end items-center gap-4">
        <!-- Sort by Dropdown -->
        <div class="relative">
          <button
            @click="toggleLikesRatioDropdown"
            @blur="hideLikesRatioDropdown"
            class="px-4 py-2 rounded-lg font-medium transition flex items-center gap-2 bg-white/10 text-white/80 hover:bg-white/20"
            style="background-color: rgba(47, 47, 47, 255) !important;"
          >
            {{ getSortText() }}
            <Icon :icon="faChevronDown" class="text-xs" />
          </button>
          
          <!-- Dropdown Menu -->
          <div 
            v-if="showLikesRatioDropdown"
            class="absolute right-0 mt-2 w-52 rounded-lg shadow-lg z-50"
            style="background-color: rgb(67,67,67,255);"
          >
            <button
              v-for="option in sortOptions"
              :key="option.value"
              @mousedown.prevent="setLikesRatioFilter(option.value)"
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
              :class="option.value === 'none' ? 'rounded-t-lg' : (option.value === sortOptions[sortOptions.length-1].value ? 'rounded-b-lg' : '')"
              :style="sortBy === option.value ? 'background-color: rgb(67,67,67,255)' : ''"
            >
              {{ option.label }}
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white/5 rounded-lg overflow-hidden">
        <div class="overflow-x-auto scrollbar-hide">
          <table class="w-full">
            <thead>
              <tr class="border-b border-white/10">
                <th class="text-left p-3 font-medium">#</th>
                <th class="text-left p-3 font-medium w-12">Ban Artist</th>
                <th class="text-left p-3 font-medium">Name(s)</th>
                <th class="text-left p-3 font-medium">Title</th>
                <th class="text-left p-3 font-medium">Duration</th>
                <th class="text-left p-3 font-medium">Streams</th>
                <th class="text-left p-3 font-medium">Listeners</th>
                <th class="text-left p-3 font-medium">S/L Ratio</th>
                <th class="text-left p-3 font-medium">Save/Ban<br>Track</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(track, index) in displayRecommendations" :key="`related-${track.id}`">
                <tr
                  class="hover:bg-white/5 transition h-16 border-b border-white/5"
                  :class="[
                    expandedTrackId !== getTrackKey(track) && allowAnimations ? 'track-row' : ''
                  ]"
                  :style="expandedTrackId !== getTrackKey(track) && allowAnimations ? { animationDelay: `${index * 50}ms` } : {}"
                >
                  <!-- Index -->
                  <td class="p-3 align-middle">
                    <span class="text-white/60">{{ (currentPage - 1) * currentTracksPerPage + index + 1 }}</span>
                  </td>

                  <!-- Ban Button -->
                  <td class="p-3 align-middle">
                    <button
                      @click="banArtist(track)"
                      :class="[
                        'p-2 rounded-full transition-colors',
                        isArtistBanned(track) 
                          ? 'text-red-400 hover:text-red-300 hover:bg-red-500/20' 
                          : 'text-[#bcbcbc] hover:text-white hover:bg-white/10'
                      ]"
                      :title="isArtistBanned(track) ? 'Click to unban this artist' : 'Ban this artist'"
                    >
                      <Icon :icon="faBan" class="w-4 h-4" />
                    </button>
                  </td>

                  <!-- Artist -->
                  <td class="p-3 align-middle">
                    <div class="font-medium text-white">{{ track.artist }}</div>
                  </td>

                  <!-- Title -->
                  <td class="p-3 align-middle">
                    <div class="flex items-center gap-2">
                      <span class="text-white/80">{{ track.name }}</span>
                    </div>
                  </td>

                  <!-- Duration -->
                  <td class="p-3 align-middle">
                    <span class="text-white/80" :title="`Duration: ${track.duration_ms}ms`">{{ formatDuration(track.duration_ms) }}</span>
                  </td>

                  <!-- Streams (Playcount) -->
                  <td class="p-3 align-middle">
                    <div v-if="track.lastfm_stats?.playcount" class="text-white/80">
                      {{ formatNumber(track.lastfm_stats.playcount) }}
                    </div>
                    <div v-else-if="lastfmStatsLoading" class="flex items-center justify-center">
                      <svg class="animate-spin h-4 w-4 text-[#9d0cc6]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                    </div>
                    <div v-else-if="lastfmError" class="text-red-400 text-xs" title="LastFM integration not configured">N/A</div>
                    <div v-else class="text-white/30">-</div>
                  </td>

                  <!-- Listeners -->
                  <td class="p-3 align-middle">
                    <div v-if="track.lastfm_stats?.listeners" class="text-white/80">
                      {{ formatNumber(track.lastfm_stats.listeners) }}
                    </div>
                    <div v-else-if="lastfmStatsLoading" class="flex items-center justify-center">
                      <svg class="animate-spin h-4 w-4 text-[#9d0cc6]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                    </div>
                    <div v-else-if="lastfmError" class="text-red-400 text-xs" title="LastFM integration not configured">N/A</div>
                    <div v-else class="text-white/30">-</div>
                  </td>

                  <!-- Streams/Listeners Ratio -->
                  <td class="p-3 align-middle">
                    <div v-if="track.lastfm_stats?.playcount && track.lastfm_stats?.listeners" class="text-white/80">
                      {{ formatRatio(track.lastfm_stats.playcount, track.lastfm_stats.listeners) }}
                    </div>
                    <div v-else-if="lastfmStatsLoading" class="flex items-center justify-center">
                      <svg class="animate-spin h-4 w-4 text-[#9d0cc6]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                    </div>
                    <div v-else-if="lastfmError" class="text-red-400 text-xs" title="LastFM integration not configured">N/A</div>
                    <div v-else class="text-white/30">-</div>
                  </td>

                  <!-- Actions -->
                  <td class="p-3 align-middle">
                    <div class="flex gap-2 items-center">
                      <!-- Save/Ban Group -->
                      <div class="flex gap-2">
                        <!-- Save Button (24h) -->
                        <button
                          @click="saveTrack(track)"
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="isTrackSaved(track) 
                            ? 'bg-green-600 hover:bg-green-700 text-white' 
                            : 'bg-gray-600 hover:bg-gray-500 text-white'"
                          class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                          :title="isTrackSaved(track) ? 'Click to unsave track' : 'Save track (24h)'"
                        >
                          <Icon :icon="faHeart" class="text-xs" />
                        </button>

                        <!-- Blacklist Button -->
                        <button
                          @click="blacklistTrack(track)"
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="isTrackBlacklisted(track) 
                            ? 'bg-orange-600 hover:bg-orange-700 text-white' 
                            : 'bg-gray-600 hover:bg-gray-500 text-white'"
                          class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                          :title="isTrackBlacklisted(track) ? 'Click to unblock track' : 'Block track'"
                        >
                          <Icon :icon="faBan" class="text-xs" />
                        </button>
                      </div>

                      <!-- Spacer -->
                      <div class="w-4"></div>

                      <!-- Related/Preview Group -->
                      <div class="flex gap-2">
                        <!-- Related Track Button -->
                        <button
                          @click="getRelatedTracks(track)"
                          :disabled="processingTrack === getTrackKey(track)"
                          class="px-3 py-1.5 bg-[#9d0cc6] hover:bg-[#c036e8] rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[90px] justify-center"
                          title="Find Related Tracks"
                        >
                          <Icon :icon="faSearch" class="w-3 h-3" />
                          <span>Related</span>
                        </button>
                        
                        <!-- Preview Button -->
                        <button
                          @click="(track.source === 'shazam' || track.source === 'shazam_fallback') ? previewShazamTrack(track) : toggleSpotifyPlayer(track)"
                          :disabled="processingTrack === getTrackKey(track)"
                          class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[90px] justify-center"
                        >
                          <!-- Loading spinner when processing -->
                          <svg v-if="processingTrack === getTrackKey(track) && isPreviewProcessing" class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          <!-- Regular icon when not processing -->
                          <Icon v-else :icon="expandedTrackId === getTrackKey(track) ? faTimes : faPlay" class="w-3 h-3" />
                          <span :class="processingTrack === getTrackKey(track) && isPreviewProcessing ? '' : 'ml-1'">{{ processingTrack === getTrackKey(track) && isPreviewProcessing ? 'Loading...' : (expandedTrackId === getTrackKey(track) ? 'Close' : 'Preview') }}</span>
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>

                <!-- Spotify Player Dropdown Row with Animation -->
                <Transition name="spotify-dropdown" mode="out-in">
                  <tr v-if="expandedTrackId === getTrackKey(track)" :key="`spotify-${track.id}`" class="border-b border-white/5 player-row">
                    <td colspan="9" class="p-0 overflow-hidden">
                      <div class="p-4" style="background-color: rgb(67,67,67);">
                        <div class="max-w-4xl mx-auto">
                          <div v-if="track.id && track.id !== 'NO_TRACK_FOUND'">
                          <iframe
                            :key="track.id"
                            :src="`https://open.spotify.com/embed/track/${track.id}?utm_source=generator&theme=0`"
                            :title="`${track.artist} - ${track.name}`"
                            class="w-full spotify-embed"
                            style="height: 80px; border-radius: 15px; background-color: rgb(67,67,67);"
                            frameBorder="0"
                            scrolling="no"
                            allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                            loading="lazy"
                            @load="(event) => { event.target.style.opacity = '1' }"
                            @error="() => {}"
                          ></iframe>
                        </div>
                          <div v-else class="flex items-center justify-center" style="height: 80px; border-radius: 15px; background-color: rgb(67,67,67);">
                          <div class="text-center text-white/60">
                            <div class="text-sm font-medium">No Spotify preview available</div>
                          </div>
                        </div>
                      </div>
                      </div>
                    </td>
                  </tr>
                </Transition>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination Controls at Bottom -->
      <div v-if="isPaginationMode && currentTotalTracks > currentTracksPerPage" class="pagination-section flex items-center justify-center gap-2 mt-8">
        <button
          :disabled="currentPage === 1"
          class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
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
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          :disabled="currentPage === totalPages"
          class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages }"
          @click="goToPage(currentPage + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick, withDefaults } from 'vue'
import { faSpinner, faExclamationTriangle, faTimes, faHeart, faBan, faUserPlus, faUserMinus, faPlay, faRandom, faInfoCircle, faSearch, faChevronDown, faFilter, faArrowUp, faClock } from '@fortawesome/free-solid-svg-icons'
import { http } from '@/services/http'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { useRouter } from '@/composables/useRouter'

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
  external_ids?: {
    isrc?: string
  }
  artists?: Array<{
    id: string
    name: string
  }>
  source?: string  // 'shazam' or 'spotify'
  shazam_id?: string
  spotify_id?: string
  lastfm_stats?: {
    playcount: number
    listeners: number
    url?: string
  }
}

// Props
interface Props {
  recommendations: Track[]
  isDiscovering: boolean
  errorMessage: string
  currentProvider: string
  seedTrack: Track | null
  totalTracks?: number
  currentPage?: number
  tracksPerPage?: number
}

const props = withDefaults(defineProps<Props>(), {
  seedTrack: null
})

// Emits
const emit = defineEmits<{
  'clearError': []
  'page-change': [page: number]
  'per-page-change': [perPage: number]
  'related-tracks': [track: Track]
  'tracks-blacklisted': [trackKeys: string[]]
}>()

// State
const expandedTrackId = ref<string | null>(null)
const processingTrack = ref<string | null>(null)
const isBlacklisting = ref(false)
const lastfmStatsLoading = ref(false)
const lastfmError = ref(false)
const isPreviewProcessing = ref(false)
const sortBy = ref<string>('none')
const sortedRecommendations = ref<Track[]>([])
const originalRecommendations = ref<Track[]>([])
const currentPageTracks = ref<Track[]>([])
const dropdownOpen = ref(false)
const showLikesRatioDropdown = ref(false)
const initialLoadComplete = ref(false)
const isUpdatingStats = ref(false)
const lastRecommendationsCount = ref(0)
const allowAnimations = ref(true)

// Stats fetching tracking - to avoid duplicate API calls
const tracksWithStatsFetched = ref(new Set<string>()) // Track keys that have had stats fetched

// Banned artists tracking (shared with Similar Artists)
const bannedArtists = ref(new Set<string>()) // Store artist names

// Initialize global blacklist filtering composable
const { 
  addArtistToBlacklist,
  loadBlacklistedItems 
} = useBlacklistFiltering()

const { onRouteChanged } = useRouter()

// Sort options for the custom dropdown
const sortOptions = [
  { value: 'none', label: 'Random' },
  { value: 'playcount', label: 'Most Streams' },
  { value: 'listeners', label: 'Most Listeners' },
  { value: 'ratio', label: 'Best Ratio (S/L)' }
]

// Music preferences state
const savedTracks = ref<Set<string>>(new Set())
const blacklistedTracks = ref<Set<string>>(new Set())
const savedArtists = ref<Set<string>>(new Set())
const blacklistedArtists = ref<Set<string>>(new Set())
const clientUnsavedTracks = ref<Set<string>>(new Set()) // Tracks unsaved by client

// Helper functions
const getTrackKey = (track: Track): string => {
  return `${track.artist}-${track.name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

const formatDuration = (ms?: number): string => {
  if (!ms) return '0:00'
  const minutes = Math.floor(ms / 60000)
  const seconds = Math.floor((ms % 60000) / 1000)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

const formatNumber = (num: number): string => {
  if (num >= 1000000) {
    return (num / 1000000).toFixed(1) + 'M'
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'K'
  }
  return num.toString()
}

const formatRatio = (playcount: number, listeners: number): string => {
  if (listeners === 0) return '0'
  const ratio = playcount / listeners
  return ratio.toFixed(1)
}

// Helper functions for custom dropdown
const getSortLabel = (value: string): string => {
  const option = sortOptions.find(opt => opt.value === value)
  return option ? option.label : 'Random (Default)'
}

const selectSort = (value: string): void => {
  sortBy.value = value
  dropdownOpen.value = false
  applySorting()
}

const toggleLikesRatioDropdown = () => {
  showLikesRatioDropdown.value = !showLikesRatioDropdown.value
}

const hideLikesRatioDropdown = () => {
  setTimeout(() => {
    showLikesRatioDropdown.value = false
  }, 150) // Small delay to allow click events to register
}

const setLikesRatioFilter = (type: string) => {
  // Close any open preview dropdown when changing sort
  expandedTrackId.value = null
  
  sortBy.value = type
  showLikesRatioDropdown.value = false
  // No need to call applySorting() - displayRecommendations computed will handle per-page sorting
  console.log(`[SORT] Changed to ${type} - will apply to current page only`)
}

const getSortIcon = () => {
  switch (sortBy.value) {
    case 'none': return faFilter
    case 'playcount': return faArrowUp
    case 'listeners': return faArrowUp
    case 'ratio': return faArrowUp
    default: return faFilter
  }
}

const getSortText = () => {
  switch (sortBy.value) {
    case 'none': return 'Sort by: Random'
    case 'playcount': return 'Sort by: Most Streams'
    case 'listeners': return 'Sort by: Most Listeners'
    case 'ratio': return 'Sort by: Best Ratio (S/L)'
    default: return 'Sort by: Random'
  }
}

// Default values for pagination (with fallbacks for legacy mode) 
const currentPage = computed(() => props.currentPage ?? 1)
const currentTracksPerPage = computed(() => props.tracksPerPage ?? 20)
const isPaginationMode = computed(() => props.totalTracks !== undefined)

// Total tracks - use the length of filtered recommendations
const currentTotalTracks = computed(() => {
  console.log(`[RECOMMENDATIONS DEBUG] Props totalTracks: ${props.totalTracks}, isPaginationMode: ${isPaginationMode.value}`)
  
  // Always use the filtered recommendations count for accurate pagination
  const total = filteredRecommendations.value.length
  console.log(`[RECOMMENDATIONS DEBUG] Using filtered recommendations total: ${total}`)
  return total
})

// Pagination computed properties
const totalPages = computed(() => {
  return Math.ceil(currentTotalTracks.value / currentTracksPerPage.value)
})

const visiblePages = computed(() => {
  const pages = []
  const maxVisible = 5
  const total = totalPages.value

  if (total <= maxVisible) {
    // Show all pages if total is small
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Show pages around current page
    const current = currentPage.value
    let start = Math.max(1, current - 2)
    let end = Math.min(total, current + 2)

    // Adjust if we're near the beginning or end
    if (current <= 3) {
      end = Math.min(total, 5)
    } else if (current >= total - 2) {
      start = Math.max(1, total - 4)
    }

    for (let i = start; i <= end; i++) {
      pages.push(i)
    }
  }

  return pages
})

// Computed property for all filtered recommendations (without immediate banned artists filtering)
const filteredRecommendations = computed(() => {
  console.log('🔄 FILTERED RECOMMENDATIONS COMPUTED CALLED')
  console.log('🔄 Current banned artists:', Array.from(bannedArtists.value))
  
  let tracks: Track[]
  
  if (sortBy.value === 'none') {
    // Show tracks in random order when explicitly set to none
    tracks = originalRecommendations.value.length > 0 ? originalRecommendations.value : props.recommendations
  } else {
    // Use sorted recommendations (including default 'playcount' sorting)
    tracks = sortedRecommendations.value.length > 0 ? sortedRecommendations.value : props.recommendations
  }
  
  console.log(`[RECOMMENDATIONS DEBUG] Raw tracks: ${tracks.length}, Props recommendations: ${props.recommendations.length}`)
  console.log(`[RECOMMENDATIONS DEBUG] originalRecommendations: ${originalRecommendations.value.length}, sortedRecommendations: ${sortedRecommendations.value.length}`)
  
  // Filter out tracks from the same artist as the seed track
  if (props.seedTrack) {
    const seedArtist = props.seedTrack.artist.toLowerCase()
    tracks = tracks.filter(track => track.artist.toLowerCase() !== seedArtist)
    console.log(`[RECOMMENDATIONS DEBUG] Filtered out seed artist "${props.seedTrack.artist}": ${tracks.length} tracks remaining`)
  } else {
    console.log('[RECOMMENDATIONS DEBUG] No seed track provided, skipping artist filtering')
  }
  
  // Don't filter out banned artists immediately - keep them visible in current results
  // Filtering will only happen when new recommendations arrive
  console.log(`[RECOMMENDATIONS DEBUG] Keeping all tracks visible (including banned): ${tracks.length}`)
  console.log('🔄 Tracks being returned:', tracks.map(t => t.artist).slice(0, 5))
  return tracks
})

// Computed property for displayed recommendations (current page tracks with per-page sorting)
const displayRecommendations = computed(() => {
  if (!isPaginationMode.value) {
    // Legacy mode - show all filtered tracks
    return filteredRecommendations.value
  }
  
  // Use the stored current page tracks and apply sorting only to them
  if (sortBy.value === 'none') {
    // No sorting - return current page tracks in their original order
    console.log(`[RECOMMENDATIONS] Page ${currentPage.value}: showing ${currentPageTracks.value.length} tracks (unsorted)`)
    return currentPageTracks.value
  } else {
    // Sort only the current page's tracks
    const sortedPageTracks = [...currentPageTracks.value].sort((a, b) => {
      const aStats = a.lastfm_stats
      const bStats = b.lastfm_stats
      
      // Tracks without stats go to the end
      if (!aStats && !bStats) return 0
      if (!aStats) return 1
      if (!bStats) return -1
      
      switch (sortBy.value) {
        case 'playcount':
          return (bStats.playcount || 0) - (aStats.playcount || 0)
        case 'listeners':
          return (bStats.listeners || 0) - (aStats.listeners || 0)
        case 'ratio':
          const ratioA = aStats.listeners > 0 ? aStats.playcount / aStats.listeners : 0
          const ratioB = bStats.listeners > 0 ? bStats.playcount / bStats.listeners : 0
          return ratioB - ratioA
        default:
          return 0
      }
    })
    
    console.log(`[RECOMMENDATIONS] Page ${currentPage.value}: showing ${sortedPageTracks.length} tracks (sorted by ${sortBy.value})`)
    return sortedPageTracks
  }
})

const isTrackSaved = (track: Track): boolean => {
  const trackKey = getTrackKey(track)
  return savedTracks.value.has(trackKey) && !clientUnsavedTracks.value.has(trackKey)
}

const isTrackBlacklisted = (track: Track): boolean => {
  return blacklistedTracks.value.has(getTrackKey(track))
}

const isArtistSaved = (track: Track): boolean => {
  return savedArtists.value.has(track.artist.toLowerCase())
}

const isArtistBlacklisted = (track: Track): boolean => {
  return blacklistedArtists.value.has(track.artist.toLowerCase())
}

const isArtistBanned = (track: Track): boolean => {
  return bannedArtists.value.has(track.artist)
}

// Action handlers
const saveTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  
  // Close any open preview dropdown when saving/unsaving tracks
  if (expandedTrackId.value !== trackKey) {
    expandedTrackId.value = null
  }
  
  if (isTrackSaved(track)) {
    // Unsave track: Update UI immediately for better UX
    savedTracks.value.delete(trackKey)
    
    // Since no DELETE endpoint exists for saved tracks, use client-side tracking
    // This provides the expected UX while tracks will naturally expire in 24h
    clientUnsavedTracks.value.add(trackKey)
    
    // Save to localStorage for persistence across page reloads
    try {
      const unsavedList = Array.from(clientUnsavedTracks.value)
      localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
    } catch (error) {
      // Failed to save unsaved tracks to localStorage
    }
  } else {
    // Save track - show processing state
    processingTrack.value = trackKey
    
    try {
      const response = await http.post('music-preferences/save-track', {
        isrc: track.external_ids?.isrc || track.id,
        track_name: track.name,
        artist_name: track.artist,
        spotify_id: track.id
      })

      if (response.success) {
        savedTracks.value.add(trackKey)
        // Remove from client unsaved tracks if it was previously unsaved
        clientUnsavedTracks.value.delete(trackKey)
        // Update localStorage
        try {
          const unsavedList = Array.from(clientUnsavedTracks.value)
          localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
        } catch (error) {
          // Failed to update unsaved tracks in localStorage
        }
      } else {
        throw new Error(response.error || 'Failed to save track')
      }
    } catch (error: any) {
      // Failed to save track
    } finally {
      processingTrack.value = null
    }
  }
}

const blacklistTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  
  // Close any open preview dropdown when blacklisting tracks
  if (expandedTrackId.value !== trackKey) {
    expandedTrackId.value = null
  }
  
  if (isTrackBlacklisted(track)) {
    // Update UI immediately for better UX
    blacklistedTracks.value.delete(trackKey)
    
    // Do backend work in background without blocking UI
    try {
      const deleteData = {
        isrc: track.external_ids?.isrc || track.id,
        track_name: track.name,
        artist_name: track.artist
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)
      
      if (!response.success) {
        // Revert UI change if backend failed
        blacklistedTracks.value.add(trackKey)
        // Failed to unblock track on backend
      }
    } catch (error: any) {
      // Revert UI change if request failed
      blacklistedTracks.value.add(trackKey)
      // Failed to unblock track
    }
  } else {
    // Block track - show processing state
    processingTrack.value = trackKey
    
    try {
      const response = await http.post('music-preferences/blacklist-track', {
        isrc: track.external_ids?.isrc || track.id,
        track_name: track.name,
        artist_name: track.artist
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)
        // Emit to parent component
        emit('tracks-blacklisted', [trackKey])
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    } catch (error: any) {
      // Failed to blacklist track
    } finally {
      processingTrack.value = null
    }
  }
}

const saveArtist = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artistKey = track.artist.toLowerCase()

    if (isArtistSaved(track)) {
      // Remove from saved artists
      const deleteData = {
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/saved-artist?${params}`)

      if (response.success) {
        savedArtists.value.delete(artistKey)
      } else {
        throw new Error(response.error || 'Failed to unsave artist')
      }
    } else {
      // Save artist
      const response = await http.post('music-preferences/save-artist', {
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
      })

      if (response.success) {
        savedArtists.value.add(artistKey)
      } else {
        throw new Error(response.error || 'Failed to save artist')
      }
    }
  } catch (error: any) {
    // Failed to save artist
  } finally {
    processingTrack.value = null
  }
}

const blacklistArtist = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artistKey = track.artist.toLowerCase()

    if (isArtistBlacklisted(track)) {
      // Remove from blacklisted artists
      const deleteData = {
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-artist?${params}`)

      if (response.success) {
        blacklistedArtists.value.delete(artistKey)
      } else {
        throw new Error(response.error || 'Failed to unblacklist artist')
      }
    } else {
      // Blacklist artist
      const response = await http.post('music-preferences/blacklist-artist', {
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
      })

      if (response.success) {
        blacklistedArtists.value.add(artistKey)
      } else {
        throw new Error(response.error || 'Failed to blacklist artist')
      }
    }
  } catch (error: any) {
    // Failed to blacklist artist
  } finally {
    processingTrack.value = null
  }
}

// Spotify player functionality
const toggleSpotifyPlayer = (track: Track) => {
  const trackKey = getTrackKey(track)
  
  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

  expandedTrackId.value = trackKey
}

const getRelatedTracks = (track: Track) => {
  // Close any open preview dropdown when getting related tracks
  expandedTrackId.value = null
  emit('related-tracks', track)
}

// Update current page tracks when page changes
const updateCurrentPageTracks = () => {
  const allFilteredTracks = filteredRecommendations.value
  const start = (currentPage.value - 1) * currentTracksPerPage.value
  const end = start + currentTracksPerPage.value
  currentPageTracks.value = allFilteredTracks.slice(start, end)
  console.log(`[PAGE TRACKS] Updated current page tracks: ${currentPageTracks.value.length} tracks for page ${currentPage.value}`)
}

// Fetch stats for current page tracks if they don't have stats yet
const fetchStatsForCurrentPage = async () => {
  const currentTracks = displayRecommendations.value
  if (currentTracks.length === 0) return
  
  // Check if any tracks on current page need stats
  const tracksNeedingStats = currentTracks.filter(track => {
    const trackKey = getTrackKey(track)
    return !tracksWithStatsFetched.value.has(trackKey)
  })
  
  if (tracksNeedingStats.length > 0) {
    console.log(`🎵 LAZY LOADING: Fetching stats for ${tracksNeedingStats.length} tracks on page ${currentPage.value}`)
    await fetchLastFmStatsOptimized(tracksNeedingStats)
  } else {
    console.log(`🎵 LAZY LOADING: All tracks on page ${currentPage.value} already have stats`)
  }
}

// Pagination methods
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    // Close any open preview dropdown when changing pages
    expandedTrackId.value = null
    
    // Enable animations for page change
    allowAnimations.value = true
    initialLoadComplete.value = false
    
    // Disable animations after they complete
    setTimeout(() => {
      allowAnimations.value = false
      initialLoadComplete.value = true
    }, 2000)
    
    emit('page-change', page)
  }
}

const changePerPage = () => {
  emit('per-page-change', props.tracksPerPage)
}

// Enhanced notification functions for better UX
const showTrackNotFoundNotification = (track: Track) => {
  // Create a beautiful notification instead of alert
  const notification = document.createElement('div')
  notification.className = 'fixed top-4 right-4 bg-gradient-to-br from-orange-500 to-red-500 text-white rounded-xl shadow-2xl p-4 max-w-md z-50 animate-slide-in'
  notification.innerHTML = `
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 mt-1">
        <svg class="w-5 h-5 text-orange-100" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
        </svg>
      </div>
      <div class="flex-1">
        <div class="font-semibold text-sm mb-1">Preview Unavailable</div>
        <div class="text-sm text-orange-100 leading-relaxed">
          Could not find "<span class="font-medium">${track.name}</span>" by <span class="font-medium">${track.artist}</span> on Spotify.
        </div>
        <div class="text-xs text-orange-200 mt-2">
          Track preview not available.
        </div>
      </div>
      <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 text-orange-100 hover:text-white transition-colors">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
        </svg>
      </button>
    </div>
  `
  
  document.body.appendChild(notification)
  
  // Auto remove after 6 seconds
  setTimeout(() => {
    notification.style.transform = 'translateX(100%)'
    notification.style.opacity = '0'
    setTimeout(() => notification.remove(), 300)
  }, 6000)
}

const showPreviewErrorNotification = (track: Track, errorMessage: string) => {
  // Create a beautiful error notification
  const notification = document.createElement('div')
  notification.className = 'fixed top-4 right-4 bg-gradient-to-br from-red-600 to-red-700 text-white rounded-xl shadow-2xl p-4 max-w-md z-50 animate-slide-in'
  notification.innerHTML = `
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 mt-1">
        <svg class="w-5 h-5 text-red-100" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
        </svg>
      </div>
      <div class="flex-1">
        <div class="font-semibold text-sm mb-1">Preview Failed</div>
        <div class="text-sm text-red-100 leading-relaxed">
          Failed to preview "<span class="font-medium">${track.name}</span>" by <span class="font-medium">${track.artist}</span>.
        </div>
        <div class="text-xs text-red-200 mt-2">
          ${errorMessage}
        </div>
      </div>
      <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 text-red-100 hover:text-white transition-colors">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
        </svg>
      </button>
    </div>
  `
  
  document.body.appendChild(notification)
  
  // Auto remove after 8 seconds for error (longer than info)
  setTimeout(() => {
    notification.style.transform = 'translateX(100%)'
    notification.style.opacity = '0'
    setTimeout(() => notification.remove(), 300)
  }, 8000)
}

// Track cleaning function for better cross-platform matching
const cleanTrackForQuery = (text: string): string => {
  if (!text) return ''
  
  // Check if this is likely a specific remix/version we want to keep
  const hasSpecificRemix = /\([^)]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^)]*\)/i.test(text) ||
                          /\[[^\]]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^\]]*\]/i.test(text)
  
  if (hasSpecificRemix) {
    // Keep specific remixes (e.g., "Mosca Remix", "David Guetta Remix")
    // Only clean up spacing and formatting
    return text
      .replace(/\s*&\s*/g, ' & ')
      .replace(/\s+/g, ' ')
      .replace(/\s*-\s*$/, '')
      .replace(/^\s*-\s*/, '')
      .trim()
  }
  
  // For non-specific versions, clean more aggressively
  return text
    // Remove generic version content
    .replace(/\s*\([^)]*(?:original|version|radio|extended|edit)(?!\s+remix)[^)]*\)/gi, '')
    .replace(/\s*\[[^\]]*(?:original|version|radio|extended|edit)(?!\s+remix)[^\]]*\]/gi, '')
    // Remove standalone generic words at the end
    .replace(/\s+(?:original|version|radio|extended|edit)$/gi, '')
    // Clean up spacing
    .replace(/\s*&\s*/g, ' & ')
    .replace(/\s+/g, ' ')
    .replace(/\s*-\s*$/, '')
    .replace(/^\s*-\s*/, '')
    .trim()
}

// Preview tracks by converting to Spotify
const previewShazamTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey
  isPreviewProcessing.value = true
  
  try {
    // Clean track and artist names for better matching
    const cleanedArtist = cleanTrackForQuery(track.artist)
    const cleanedTitle = cleanTrackForQuery(track.name)
    
    // console.log('🎵 Converting track to Spotify for preview:', track.name)
    // console.log('🧹 Original:', `"${track.artist}" - "${track.name}"`)
    // console.log('🧹 Cleaned:', `"${cleanedArtist}" - "${cleanedTitle}"`)
    
    const response = await http.get('music-discovery/track-preview', {
      params: {
        artist_name: cleanedArtist,
        track_title: cleanedTitle,
        original_artist: track.artist, // Keep originals for fallback
        original_title: track.name,
        source: 'shazam'
      }
    })

    if (response.success && response.data && response.data.spotify_track_id) {
      // console.log('✅ Found Spotify equivalent:', response.data.spotify_track_id)
      
      // Update the track object with Spotify ID for the player
      track.id = response.data.spotify_track_id
      
      // Now open the Spotify player with the converted track
      toggleSpotifyPlayer(track)
    } else {
      // console.warn('❌ Could not find Spotify equivalent for track')
      showTrackNotFoundNotification(track)
    }
  } catch (error: any) {
    // console.error('❌ Failed to convert track to Spotify:', error)
    showPreviewErrorNotification(track, error.response?.data?.error || error.message || 'Network error')
  } finally {
    processingTrack.value = null
    isPreviewProcessing.value = false
  }
}

// Blacklist all unsaved tracks that are currently displayed
const blacklistUnsavedTracks = async () => {
  if (isBlacklisting.value) return
  
  // Close any open preview dropdown when bulk blacklisting
  expandedTrackId.value = null
  
  isBlacklisting.value = true
  
  try {
    // Get all currently displayed tracks that are not saved
    const unsavedTracks = filteredRecommendations.value.filter(track => !isTrackSaved(track))
    
    if (unsavedTracks.length === 0) {
      // console.log('No unsaved tracks to blacklist')
      return
    }
    
    // console.log(`Starting to blacklist ${unsavedTracks.length} unsaved tracks...`)
    
    // Process tracks in batches to avoid overwhelming the API
    const batchSize = 5
    let processedCount = 0
    
    for (let i = 0; i < unsavedTracks.length; i += batchSize) {
      const batch = unsavedTracks.slice(i, i + batchSize)
      
      await Promise.all(batch.map(async (track) => {
        try {
          const trackKey = getTrackKey(track)
          
          // Skip if already blacklisted
          if (isTrackBlacklisted(track)) {
            // console.log(`Skipping already blacklisted: ${track.artist} - ${track.name}`)
            return
          }
          
          const response = await http.post('music-preferences/blacklist-track', {
            isrc: track.external_ids?.isrc || track.id,
            track_name: track.name,
            artist_name: track.artist
          })
          
          if (response.success) {
            blacklistedTracks.value.add(trackKey)
            processedCount++
            // console.log(`✅ Blacklisted: ${track.artist} - ${track.name}`)
          } else {
            // console.error(`❌ Failed to blacklist: ${track.artist} - ${track.name}`, response.error)
          }
        } catch (error) {
          // console.error(`❌ Error blacklisting: ${track.artist} - ${track.name}`, error)
        }
      }))
      
      // Small delay between batches to be nice to the API
      if (i + batchSize < unsavedTracks.length) {
        await new Promise(resolve => setTimeout(resolve, 100))
      }
    }
    
    // console.log(`✅ Bulk blacklist complete! Processed ${processedCount} tracks`)
    
    // Emit the blacklisted track keys to the parent component
    const blacklistedKeys = unsavedTracks
      .filter(track => blacklistedTracks.value.has(getTrackKey(track)))
      .map(track => getTrackKey(track))
    
    if (blacklistedKeys.length > 0) {
      emit('tracks-blacklisted', blacklistedKeys)
    }
    
  } catch (error) {
    // console.error('❌ Bulk blacklist failed:', error)
  } finally {
    isBlacklisting.value = false
  }
}

// Load banned artists from localStorage (shared with Similar Artists and Related Tracks)
const loadBannedArtists = () => {
  try {
    const stored = localStorage.getItem('koel-banned-artists')
    if (stored) {
      const bannedList = JSON.parse(stored)
      bannedArtists.value = new Set(bannedList)
      // console.log('Loaded banned artists:', bannedList)
    }
  } catch (error) {
    // console.warn('Failed to load banned artists from localStorage:', error)
  }
}

// Load client-side unsaved tracks from localStorage
const loadClientUnsavedTracks = () => {
  try {
    const stored = localStorage.getItem('koel-client-unsaved-tracks')
    if (stored) {
      const unsavedList = JSON.parse(stored)
      clientUnsavedTracks.value = new Set(unsavedList)
      // console.log('Loaded client unsaved tracks:', unsavedList)
    }
  } catch (error) {
    // console.warn('Failed to load client unsaved tracks from localStorage:', error)
  }
}

// Save banned artists to localStorage
const saveBannedArtists = () => {
  try {
    const bannedList = Array.from(bannedArtists.value)
    localStorage.setItem('koel-banned-artists', JSON.stringify(bannedList))
  } catch (error) {
    // console.warn('Failed to save banned artists to localStorage:', error)
  }
}

// Ban/Unban an artist (toggle banned state)
const banArtist = async (track: Track) => {
  // Close any open preview dropdown when banning/unbanning artists
  expandedTrackId.value = null
  
  const artistName = track.artist
  const isCurrentlyBanned = isArtistBanned(track)
  
  if (isCurrentlyBanned) {
    // UNBAN ARTIST
    console.log('🔓 UNBANNING ARTIST - START:', artistName)
    
    // IMMEDIATE UI UPDATE - Remove from banned list right away for instant visual feedback
    bannedArtists.value.delete(artistName)
    saveBannedArtists()
    // Note: We don't remove from global blacklist as other sections might still want it filtered
    
    console.log('🔓 UI updated immediately (unbanned), now doing API call in background')
    
    // Background API call to remove from backend
    try {
      const deleteData = {
        artist_name: artistName,
        spotify_artist_id: track.artists?.[0]?.id || track.id
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-artist?${params}`)
      console.log('✅ Artist removed from global blacklist API:', response)
    } catch (apiError: any) {
      console.error('❌ Failed to remove from API:', apiError)
      // API failed but local/visual changes are already applied
      console.warn('Artist unbanned locally but API removal failed')
    }
  } else {
    // BAN ARTIST
    console.log('🚫 BANNING ARTIST - START:', artistName)
    
    // IMMEDIATE UI UPDATE - Add to banned list right away for instant visual feedback
    bannedArtists.value.add(artistName)
    saveBannedArtists()
    addArtistToBlacklist(artistName)
    
    console.log('🚫 UI updated immediately (banned), now doing API call in background')
    
    // Background API call to save to backend
    try {
      const response = await http.post('music-preferences/blacklist-artist', {
        artist_name: artistName,
        spotify_artist_id: track.artists?.[0]?.id || track.id // Use track ID as fallback if no artist ID
      })
      console.log('✅ Artist saved to global blacklist API:', response)
    } catch (apiError: any) {
      console.error('❌ Failed to save to API:', apiError)
      console.error('❌ API Error details:', apiError.response?.data || apiError.message)
      // API failed but local/visual changes are already applied
      console.warn('Artist banned locally but API save failed - will retry on next session')
    }
  }
}

// Filter out tracks from banned artists
const filterBannedArtists = (trackList: Track[]) => {
  return trackList.filter(track => !bannedArtists.value.has(track.artist))
}

// Load user preferences on mount
// Click outside handler to close dropdown
const handleClickOutside = (event: Event) => {
  const target = event.target as Element
  if (!target.closest('.relative')) {
    dropdownOpen.value = false
  }
}

onMounted(async () => {
  loadBannedArtists()
  loadClientUnsavedTracks()
  await loadUserPreferences()
  // Load global blacklisted items
  await loadBlacklistedItems()
  document.addEventListener('click', handleClickOutside)
})

// Close Spotify previews when navigating away from any screen containing this component
onRouteChanged((route) => {
  // Close any open preview when navigating away
  expandedTrackId.value = null
  
  // Enable animations when entering Music Discovery screen
  if (route.screen === 'MusicDiscovery' && props.recommendations.length > 0) {
    allowAnimations.value = true
    initialLoadComplete.value = false
    
    // Disable animations after they complete
    setTimeout(() => {
      allowAnimations.value = false
      initialLoadComplete.value = true
    }, 2000)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Fetch LastFM stats for tracks
const fetchLastFmStats = async (tracks: Track[]) => {
  if (tracks.length === 0) return
  
  console.log('🎵 Fetching LastFM stats for tracks:', tracks.length)
  lastfmStatsLoading.value = true
  lastfmError.value = false
  
  try {
    // Process tracks in batches to avoid overwhelming the API
    const batchSize = 20
    const batches = []
    
    for (let i = 0; i < tracks.length; i += batchSize) {
      batches.push(tracks.slice(i, i + batchSize))
    }
    
    console.log(`🎵 Processing ${batches.length} batches of ${batchSize} tracks each`)
    
    // Process batches sequentially to be nice to the API
    for (let batchIndex = 0; batchIndex < batches.length; batchIndex++) {
      const batch = batches[batchIndex]
      const trackData = batch.map(track => ({
        artist: track.artist,
        track: track.name
      }))
      
      try {
        const response = await http.post('lastfm/track-stats', {
          tracks: trackData
        })
        
        if (response.success && response.data) {
          // Update tracks with LastFM stats
          batch.forEach(track => {
            const trackKey = `${track.artist.toLowerCase()}|${track.name.toLowerCase()}`
            const stats = response.data[trackKey]
            
            if (stats && (stats.playcount > 0 || stats.listeners > 0)) {
              track.lastfm_stats = {
                playcount: stats.playcount,
                listeners: stats.listeners,
                url: stats.url
              }
            }
          })
          console.log(`🎵 Processed batch ${batchIndex + 1}/${batches.length}`)
        } else {
          console.warn(`🎵 Batch ${batchIndex + 1} failed:`, response.error)
        }
        
        // Small delay between batches to be nice to the API
        if (batchIndex < batches.length - 1) {
          await new Promise(resolve => setTimeout(resolve, 200))
        }
        
      } catch (batchError) {
        console.error(`🎵 Error processing batch ${batchIndex + 1}:`, batchError)
      }
    }
    
    console.log('🎵 Finished fetching LastFM stats for all tracks')
    
  } catch (error) {
    console.error('🎵 Failed to fetch LastFM stats:', error)
    lastfmError.value = true
  } finally {
    lastfmStatsLoading.value = false
  }
}

// Optimized stats fetching: First batch immediately, rest in background
const fetchLastFmStatsOptimized = async (tracks: Track[]) => {
  if (tracks.length === 0) return
  
  // Filter out tracks that already have stats fetched
  const tracksNeedingStats = tracks.filter(track => {
    const trackKey = getTrackKey(track)
    return !tracksWithStatsFetched.value.has(trackKey)
  })
  
  if (tracksNeedingStats.length === 0) {
    console.log('🎵 STATS FETCH SKIPPED - All tracks already have stats')
    return
  }
  
  console.log('🎵 STATS FETCH START - Total tracks needing stats:', tracksNeedingStats.length, 'out of', tracks.length)
  console.log('🎵 STATS FETCH - Sample tracks:', tracksNeedingStats.slice(0, 3).map(t => `${t.artist} - ${t.name}`))
  isUpdatingStats.value = true
  lastfmStatsLoading.value = true
  lastfmError.value = false
  
  // Mark tracks as having stats fetched (do this early to prevent duplicate calls)
  tracksNeedingStats.forEach(track => {
    const trackKey = getTrackKey(track)
    tracksWithStatsFetched.value.add(trackKey)
  })
  
  try {
    const batchSize = 20
    const firstBatch = tracksNeedingStats.slice(0, batchSize)
    const remainingTracks = tracksNeedingStats.slice(batchSize)
    
    // Fetch first batch immediately (blocks UI to show initial data quickly)
    if (firstBatch.length > 0) {
      console.log('🎵 Fetching first batch immediately:', firstBatch.length, 'tracks')
      
      const trackData = firstBatch.map(track => ({
        artist: track.artist,
        track: track.name
      }))
      
      try {
        const response = await http.post('lastfm/track-stats', {
          tracks: trackData
        })
        
        if (response.success && response.data) {
          firstBatch.forEach(track => {
            const trackKey = `${track.artist.toLowerCase()}|${track.name.toLowerCase()}`
            const stats = response.data[trackKey]
            
            if (stats && (stats.playcount > 0 || stats.listeners > 0)) {
              track.lastfm_stats = {
                playcount: stats.playcount,
                listeners: stats.listeners,
                url: stats.url
              }
            }
          })
          console.log('🎵 ✅ First batch completed immediately')
        }
      } catch (error) {
        console.error('🎵 ❌ First batch failed:', error)
      }
    }
    
    // Set loading to false after first batch so UI can render
    lastfmStatsLoading.value = false
    initialLoadComplete.value = true
    
    // Process remaining tracks in background (non-blocking)
    if (remainingTracks.length > 0) {
      console.log('🎵 Processing remaining', remainingTracks.length, 'tracks in background...')
      
      // Use setTimeout to ensure this runs after the current render cycle
      setTimeout(async () => {
        const batches = []
        for (let i = 0; i < remainingTracks.length; i += batchSize) {
          batches.push(remainingTracks.slice(i, i + batchSize))
        }
        
        for (let batchIndex = 0; batchIndex < batches.length; batchIndex++) {
          const batch = batches[batchIndex]
          const trackData = batch.map(track => ({
            artist: track.artist,
            track: track.name
          }))
          
          try {
            const response = await http.post('lastfm/track-stats', {
              tracks: trackData
            })
            
            if (response.success && response.data) {
              batch.forEach(track => {
                const trackKey = `${track.artist.toLowerCase()}|${track.name.toLowerCase()}`
                const stats = response.data[trackKey]
                
                if (stats && (stats.playcount > 0 || stats.listeners > 0)) {
                  track.lastfm_stats = {
                    playcount: stats.playcount,
                    listeners: stats.listeners,
                    url: stats.url
                  }
                }
              })
              console.log(`🎵 Background batch ${batchIndex + 1}/${batches.length} completed`)
            }
            
            // Small delay between background batches
            if (batchIndex < batches.length - 1) {
              await new Promise(resolve => setTimeout(resolve, 300))
            }
            
          } catch (error) {
            console.error(`🎵 Background batch ${batchIndex + 1} failed:`, error)
          }
        }
        
        console.log('🎵 ✅ All background processing completed')
        isUpdatingStats.value = false
      }, 100) // Small delay to ensure first batch renders first
    }
    
  } catch (error) {
    console.error('🎵 Failed to fetch LastFM stats:', error)
    lastfmError.value = true
    lastfmStatsLoading.value = false
    isUpdatingStats.value = false
  }
}

// Apply sorting to recommendations (now handled per-page in displayRecommendations computed)
const applySorting = () => {
  // Sorting is now handled per-page in the displayRecommendations computed property
  // This function is kept for compatibility but no longer modifies global state
  console.log(`[SORTING] Sort changed to: ${sortBy.value} (will apply to current page only)`)
}

// Shuffle array function to randomize display
const shuffleArray = <T>(array: T[]): T[] => {
  const shuffled = [...array]
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]]
  }
  return shuffled
}

// Watch for recommendation changes and fetch stats
watch(() => props.recommendations, async (newRecommendations, oldRecommendations) => {
  console.log('👀 WATCH TRIGGERED - newRecommendations.length:', newRecommendations.length)
  console.log('👀 WATCH - oldRecommendations?.length:', oldRecommendations?.length)
  console.log('👀 WATCH - lastRecommendationsCount:', lastRecommendationsCount.value)
  
  if (newRecommendations.length > 0) {
    // Check if this is truly new recommendations or just stats being updated
    const isNewRecommendations = newRecommendations.length !== lastRecommendationsCount.value || 
                                !oldRecommendations
    
    console.log('👀 WATCH - isNewRecommendations:', isNewRecommendations)
    
    if (isNewRecommendations) {
      console.log('🎵 New recommendations received - closing previews')
      // Close any open preview dropdown when NEW recommendations are loaded
      expandedTrackId.value = null
      
      // Enable animations for new recommendations (section change)
      allowAnimations.value = true
      initialLoadComplete.value = false
      
      // Disable animations after they complete
      setTimeout(() => {
        allowAnimations.value = false
        initialLoadComplete.value = true
      }, 2000)
      
      // Update the count
      lastRecommendationsCount.value = newRecommendations.length
    } else {
      console.log('🎵 Stats update detected - keeping previews open and no animations')
      // Don't change animation state for stats updates - keep current state
    }
    
    // Debug: Check what data we're receiving
    console.log('🎵 RecommendationsTable received tracks:', newRecommendations.length)
    console.log('🎵 Sample track data:', newRecommendations[0])
    console.log('🎵 Sample track has lastfm_stats?', !!newRecommendations[0]?.lastfm_stats)
    
    // Don't filter out banned artists from current session - only filter on fresh searches
    // Store original recommendations and shuffle them for random display
    console.log('👀 STORING originalRecommendations - before shuffle:', newRecommendations.length)
    originalRecommendations.value = shuffleArray(newRecommendations)
    console.log('👀 STORED originalRecommendations - after shuffle:', originalRecommendations.value.length)
    
    // Update current page tracks
    updateCurrentPageTracks()
    
    // Only fetch stats if this is new recommendations
    if (isNewRecommendations) {
      console.log('🎵 FETCHING STATS for new recommendations')
      // Clear previously tracked stats for completely new recommendation set
      tracksWithStatsFetched.value.clear()
      // Only fetch stats for currently displayed tracks (lazy loading)
      const currentPageDisplayTracks = displayRecommendations.value
      console.log('🎵 Fetching stats for CURRENT PAGE only, tracks:', currentPageDisplayTracks.slice(0, 5).map(t => `${t.artist} - ${t.name}`))
      // Optimized stats fetching: Get first batch immediately, rest in background
      await fetchLastFmStatsOptimized(currentPageDisplayTracks)
    } else {
      console.log('🎵 SKIPPING STATS FETCH - not new recommendations')
    }
    
    console.log('👀 WATCH COMPLETE')
  }
}, { immediate: true })

// Watch for page changes and update current page tracks
watch([currentPage, currentTracksPerPage], async () => {
  updateCurrentPageTracks()
  // Lazy load stats for new page
  await fetchStatsForCurrentPage()
}, { immediate: false })

// Load user's saved tracks and blacklisted items
const loadUserPreferences = async () => {
  try {
    // Load blacklisted tracks
    const blacklistedTracksResponse = await http.get('music-preferences/blacklisted-tracks')
    if (blacklistedTracksResponse.success && blacklistedTracksResponse.data) {
      blacklistedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        blacklistedTracks.value.add(trackKey)
      })
      // console.log(`Loaded ${blacklistedTracks.value.size} blacklisted tracks`)
    }

    // Load saved tracks  
    const savedTracksResponse = await http.get('music-preferences/saved-tracks')
    if (savedTracksResponse.success && savedTracksResponse.data) {
      savedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        savedTracks.value.add(trackKey)
      })
      // console.log(`Loaded ${savedTracks.value.size} saved tracks`)
    }

    // Load blacklisted artists
    const blacklistedArtistsResponse = await http.get('music-preferences/blacklisted-artists')
    if (blacklistedArtistsResponse.success && blacklistedArtistsResponse.data) {
      blacklistedArtistsResponse.data.forEach((artist: any) => {
        blacklistedArtists.value.add(artist.artist_name.toLowerCase())
      })
      // console.log(`Loaded ${blacklistedArtists.value.size} blacklisted artists`)
    }

    // Load saved artists
    const savedArtistsResponse = await http.get('music-preferences/saved-artists')
    if (savedArtistsResponse.success && savedArtistsResponse.data) {
      savedArtistsResponse.data.forEach((artist: any) => {
        savedArtists.value.add(artist.artist_name.toLowerCase())
      })
      // console.log(`Loaded ${savedArtists.value.size} saved artists`)
    }

  } catch (error) {
    // console.log('Could not load user preferences (user may not be logged in)')
  }
}
</script>

<style scoped>
/* Spotify Dropdown Animations */
.spotify-dropdown-enter-active {
  transition:
    opacity 0.2s ease-out,
    transform 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.spotify-dropdown-leave-active {
  transition:
    opacity 0.15s ease-in,
    transform 0.15s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.spotify-dropdown-enter-from {
  opacity: 0;
  transform: translateY(-4px);
}

.spotify-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.spotify-dropdown-enter-to,
.spotify-dropdown-leave-from {
  opacity: 1;
  transform: translateY(0);
}

.spotify-player-container {
  animation: slideDown 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-12px);
    max-height: 0;
  }
  to {
    opacity: 1;
    transform: translateY(0);
    max-height: 200px;
  }
}

/* Track rows progressive display animation */
.track-row {
  animation: fadeInUp 0.6s ease-out both;
}

/* Prevent layout shifts during player animations */
.player-row {
  position: relative;
  contain: layout;
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

/* Animation for notification slide-in */
:global(.animate-slide-in) {
  animation: slideInFromRight 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes slideInFromRight {
  from {
    opacity: 0;
    transform: translateX(100%);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* Hide scrollbars */
.scrollbar-hide {
  -ms-overflow-style: none; /* Internet Explorer 10+ */
  scrollbar-width: none; /* Firefox */
}

.scrollbar-hide::-webkit-scrollbar {
  display: none; /* Safari and Chrome */
}

/* Fix iframe white flash and scrollbars */
.spotify-embed {
  background-color: rgb(67, 67, 67) !important;
  border: none;
  overflow: hidden;
  opacity: 0;
  transition: opacity 0.3s ease-in-out;
}

/* Show iframe after it loads */
.spotify-embed:loaded,
.spotify-embed[data-loaded='true'] {
  opacity: 1;
}

/* Ensure iframe content doesn't show scrollbars */
.spotify-embed::-webkit-scrollbar {
  display: none;
}

/* Additional iframe styling to prevent white flash */
iframe {
  background-color: rgb(67, 67, 67);
  border: none;
}
</style>