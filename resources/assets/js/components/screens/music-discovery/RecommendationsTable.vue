<template>
  <div class="recommendations-table">
    <!-- Header -->
    <div v-if="recommendations.length > 0 || isDiscovering" class="mb-6">
      <div class="max-w-6xl mx-auto">
        <!-- Ban Listened Tracks Toggle -->
        <div v-if="recommendations.length > 0" class="flex items-center justify-end gap-3 mb-4">
          <span class="text-sm text-white/80">Ban listened tracks</span>
          <button
            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
            :class="banListenedTracks ? 'bg-k-accent' : 'bg-gray-600'"
            @click="banListenedTracks = !banListenedTracks"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
              :class="banListenedTracks ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>
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
      <div class="bg-white/5 rounded-lg overflow-hidden max-w-6xl mx-auto">
        <div class="overflow-x-auto scrollbar-hide">
          <table class="w-full">
            <thead>
              <tr class="border-b border-white/10">
                <th class="text-left pl-3 py-7 font-medium w-10">#</th>
                <th class="text-center pr-3 font-medium w-16 whitespace-nowrap"></th>
                <th class="text-left p-3 font-medium w-auto min-w-64">Artist(s)</th>
                <th class="text-left p-3 font-medium">Title</th>
                <th class="text-center p-3 font-medium whitespace-nowrap">Followers</th>
                <th class="text-center p-3 font-medium whitespace-nowrap">Release Date</th>
                <th class="text-center pl-3 font-medium whitespace-nowrap"></th>
                <th class="text-center pr-3 font-medium whitespace-nowrap"></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(slot, index) in displayRecommendations" :key="`slot-${slot.slotIndex}`">
                <!-- Track Row -->
                <tr
                  :class="[
                    'transition h-16 border-b border-white/5',
                    (expandedTrackId === getTrackKey(slot.track) || (processingTrack === getTrackKey(slot.track) && isPreviewProcessing)) ? 'bg-white/5' : 'hover:bg-white/5'
                  ]"
                >
                  <!-- Index -->
                  <td class="p-3 align-middle">
                    <span class="text-white/60">{{ index + 1 }}</span>
                  </td>

                  <!-- Ban Button -->
                  <td class="p-3 align-middle">
                    <div class="flex items-center justify-center">
                      <button
                        @click="banArtist(slot.track)"
                        :class="[
                          'w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center',
                          isArtistBanned(slot.track)
                            ? 'bg-red-600 hover:bg-red-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'
                        ]"
                        :title="isArtistBanned(slot.track) ? 'Click to unban this artist' : 'Ban this artist'"
                      >
                        <Icon :icon="faUserSlash" class="text-xs" />
                      </button>
                    </div>
                  </td>

                  <!-- Artist -->
                  <td class="p-3 align-middle">
                    <span class="font-medium text-white">
                      {{ slot.track.artist }}
                    </span>
                  </td>

                  <!-- Title -->
                  <td class="p-3 align-middle">
                    <div class="flex items-center gap-2">
                      <span class="text-white/80">{{ slot.track.name }}</span>
                    </div>
                  </td>

                  <!-- Followers Count -->
                  <td class="p-3 align-middle text-center">
                    <div class="flex items-center justify-center">
                      <span class="text-white/60 text-sm">
                        {{ formatFollowers(slot.track.followers) }}
                      </span>
                    </div>
                  </td>

                  <!-- Release Date -->
                  <td class="p-3 align-middle text-center">
                    <div class="flex items-center justify-center">
                      <span class="text-white/60 text-sm">
                        {{ formatReleaseDate(slot.track.release_date) }}
                      </span>
                    </div>
                  </td>

                  <!-- Save/Ban Actions -->
                  <td class="pl-3 align-middle">
                    <div class="flex gap-2 justify-center">
                      <!-- Save Button (24h) -->
                      <button
                        @click="saveTrack(slot.track)"
                        :disabled="processingTrack === getTrackKey(slot.track)"
                        :class="isTrackSaved(slot.track)
                          ? 'bg-green-600 hover:bg-green-700 text-white'
                          : 'bg-[#484948] hover:bg-gray-500 text-white'"
                        class="h-[34px] w-[34px] rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                        :title="isTrackSaved(slot.track) ? 'Click to unsave track' : 'Save the Track (24h)'"
                      >
                        <Icon :icon="faHeart" class="text-sm" />
                      </button>

                      <!-- Blacklist Button -->
                      <button
                        @click="blacklistTrack(slot.track)"
                        :disabled="processingTrack === getTrackKey(slot.track)"
                        :class="isTrackBlacklisted(slot.track)
                          ? 'bg-orange-600 hover:bg-orange-700 text-white'
                          : 'bg-[#484948] hover:bg-gray-500 text-white'"
                        class="h-[34px] w-[34px] rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                        :title="isTrackBlacklisted(slot.track) ? 'Click to unblock track' : 'Ban the Track'"
                      >
                        <Icon :icon="faBan" class="text-sm" />
                      </button>
                    </div>
                  </td>

                  <!-- Related/Preview Actions -->
                  <td class="pr-3 pl-4 align-middle">
                    <div class="flex gap-2 justify-center">
                      <!-- Related Track Button -->
                      <button
                        @click="getRelatedTracks(slot.track)"
                        :disabled="processingTrack === getTrackKey(slot.track)"
                        class="px-3 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center"
                        title="Find Related Tracks"
                      >
                        <Icon :icon="faSearch" class="w-4 h-4 mr-2" />
                        <span>Related</span>
                      </button>

                      <!-- Preview Button -->
                      <button
                        @click="handlePreviewClick(slot.track)"
                        :disabled="processingTrack === getTrackKey(slot.track)"
                        :class="[
                          'px-3 py-2 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center',
                          (expandedTrackId === getTrackKey(slot.track) || listenedTracks.has(getTrackKey(slot.track))) 
                            ? 'bg-green-600 hover:bg-green-700 text-white' 
                            : 'bg-[#484948] hover:bg-gray-500 text-white'
                        ]"
                      >
                        <!-- Regular icon when not processing -->
                        <img v-if="expandedTrackId !== getTrackKey(slot.track) && !(processingTrack === getTrackKey(slot.track) && isPreviewProcessing)" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                        <Icon v-if="expandedTrackId === getTrackKey(slot.track) && !(processingTrack === getTrackKey(slot.track) && isPreviewProcessing)" :icon="faTimes" class="w-3 h-3" />
                        <span :class="(processingTrack === getTrackKey(slot.track) && isPreviewProcessing) ? '' : 'ml-1'">{{ (processingTrack === getTrackKey(slot.track) && isPreviewProcessing) ? 'Loading...' : (expandedTrackId === getTrackKey(slot.track) ? 'Close' : (listenedTracks.has(getTrackKey(slot.track)) ? 'Listened' : 'Preview')) }}</span>
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- Spotify Player Dropdown -->
                <Transition name="spotify-dropdown" mode="out-in">
                  <tr v-if="slot.track && (expandedTrackId === getTrackKey(slot.track) || (processingTrack === getTrackKey(slot.track) && isPreviewProcessing))" :key="`spotify-${getTrackKey(slot.track)}-${index}`">
                    <td colspan="8" class="p-0 bg-white/5 border-b border-white/5">
                      <div class="p-4">
                          <div class="max-w-4xl mx-auto">
                            <!-- Loading State -->
                            <div v-if="processingTrack === getTrackKey(slot.track) && isPreviewProcessing" class="flex items-center justify-center" style="height: 80px;">
                              <div class="flex items-center gap-3">
                                <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
                                <span class="text-k-text-secondary">Loading Track...</span>
                              </div>
                            </div>

                            <!-- Spotify Embed -->
                            <div v-else-if="slot.track.id && slot.track.id !== 'NO_TRACK_FOUND'">
                            <iframe
                              :key="slot.track.id"
                              :src="`https://open.spotify.com/embed/track/${slot.track.id}?utm_source=generator&theme=0`"
                              :title="`${slot.track.artist} - ${slot.track.name}`"
                              class="w-full spotify-embed"
                              style="height: 80px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);"
                              frameBorder="0"
                              scrolling="no"
                              allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                              loading="lazy"
                              @load="(event) => { event.target.style.opacity = '1' }"
                              @error="() => {}"
                            ></iframe>
                          </div>

                            <!-- No Preview Available -->
                            <div v-else class="flex items-center justify-center bg-white/5" style="height: 80px; border-radius: 15px;">
                            <div class="text-center text-white/60">
                              <div class="text-sm font-medium">No Spotify preview available</div>
                            </div>
                          </div>

                          <!-- Spotify Login Link -->
                          <div class="text-right mt-2">
                            <span class="text-xs text-white/50 font-light">
                              <a
                                href="https://accounts.spotify.com/login"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-white/50 hover:text-white/70 transition-colors underline"
                              >
                                Connect</a> to Spotify to listen to the full track
                            </span>
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
import { faSpinner, faExclamationTriangle, faTimes, faHeart, faBan, faUserPlus, faUserMinus, faPlay, faRandom, faInfoCircle, faSearch, faChevronDown, faFilter, faArrowUp, faClock, faUserSlash } from '@fortawesome/free-solid-svg-icons'
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
    followers?: number
  }>
  source?: string  // 'shazam' or 'spotify'
  shazam_id?: string
  spotify_id?: string
  label?: string
  popularity?: number
  followers?: number
  release_date?: string
  lastfm_stats?: {
    playcount: number
    listeners: number
    url?: string
  }
  isPendingBlacklist?: boolean
}

// Props
interface Props {
  recommendations: Track[]
  slotMap: Record<number, Track | null>
  isDiscovering: boolean
  errorMessage: string
  currentProvider: string
  seedTrack: Track | null
  totalTracks?: number
  currentPage?: number
  tracksPerPage?: number
}

const props = withDefaults(defineProps<Props>(), {
  seedTrack: null,
  slotMap: () => ({})
})

// Emits
const emit = defineEmits<{
  'clearError': []
  'page-change': [page: number]
  'per-page-change': [perPage: number]
  'related-tracks': [track: Track]
  'pending-blacklist': [trackKey: string]
  'user-banned-item': []
  'current-batch-banned-item': []
  'pending-auto-bans-cleared': []
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

// Track which tracks have been listened to (previewed)
const listenedTracks = ref(new Set<string>())
const banListenedTracks = ref(false)

// Store track IDs that were auto-banned but should remain visible until "Search Again"
const pendingAutoBannedTracks = ref(new Set<string>())

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

const getLastFmArtistUrl = (artistName: string): string => {
  // Use the exact artist name and encode it properly for LastFM URLs
  const encodedArtist = encodeURIComponent(artistName.replace(/ /g, '+'))
  return `https://www.last.fm/music/${encodedArtist}`
}

const openLastFmArtistPage = (track: Track): void => {
  // Open the LastFM artist page in a new tab using the exact artist name
  const artistUrl = getLastFmArtistUrl(track.artist)
  window.open(artistUrl, '_blank', 'noopener,noreferrer')
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

const formatFollowers = (followers: number | undefined): string => {
  if (!followers || followers === 0) {
    return 'N/A'
  }
  return formatNumber(followers)
}

const formatReleaseDate = (releaseDate: string | undefined): string => {
  if (!releaseDate) {
    return 'N/A'
  }
  // Handle different date formats (YYYY-MM-DD, YYYY-MM, YYYY)
  const dateStr = releaseDate.trim()
  if (dateStr.length >= 4) {
    // Extract year (first 4 characters)
    return dateStr.substring(0, 4)
  }
  return 'N/A'
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
  // console.log(`[SORT] Changed to ${type} - will apply to current page only`)
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
  // console.log(`[RECOMMENDATIONS DEBUG] Props totalTracks: ${props.totalTracks}, isPaginationMode: ${isPaginationMode.value}`)
  
  // Always use the filtered recommendations count for accurate pagination
  const total = filteredRecommendations.value.length
  // console.log(`[RECOMMENDATIONS DEBUG] Using filtered recommendations total: ${total}`)
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

// Computed property for all filtered recommendations (without sorting - always returns same tracks)
const filteredRecommendations = computed(() => {
  // console.log('🔄 FILTERED RECOMMENDATIONS COMPUTED CALLED')
  // console.log('🔄 Current banned artists:', Array.from(bannedArtists.value))
  
  // Always use original recommendations - sorting is handled per-page in displayRecommendations
  let tracks: Track[] = originalRecommendations.value.length > 0 ? originalRecommendations.value : props.recommendations
  
  // console.log(`[RECOMMENDATIONS DEBUG] Raw tracks: ${tracks.length}, Props recommendations: ${props.recommendations.length}`)
  // console.log(`[RECOMMENDATIONS DEBUG] originalRecommendations: ${originalRecommendations.value.length}, sortedRecommendations: ${sortedRecommendations.value.length}`)
  
  // Backend already handles seed artist filtering, so no need to filter here
  // This prevents double-filtering that reduces the track count
  
  // Don't filter out banned artists immediately - keep them visible in current results
  // Filtering will only happen when new recommendations arrive
  // console.log(`[RECOMMENDATIONS DEBUG] Keeping all tracks visible (including banned): ${tracks.length}`)
  // console.log('🔄 Tracks being returned:', tracks.map(t => t.artist).slice(0, 5))
  return tracks
})

// Computed property for displayed recommendations with slot-based system
// Returns array of objects with slot info: { slotIndex: number, track: Track }
// Filters out null slots so banned tracks are completely removed from display
const displayRecommendations = computed(() => {
  // Create array of slot entries from the slot map (slots 0-19), filtering out empty slots
  const slotEntries: Array<{ slotIndex: number; track: Track }> = []

  for (let i = 0; i < 20; i++) {
    const track = props.slotMap[i]
    // Only include slots that have actual tracks (skip null/undefined)
    if (track !== null && track !== undefined) {
      slotEntries.push({
        slotIndex: i,
        track: track
      })
    }
  }

  return slotEntries
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

    // ALSO remove from blacklist when unsaving from discovery sections
    try {
      const isrcValue = track.external_ids?.isrc || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const deleteData = {
        isrc: isrcValue,
        track_name: track.name,
        artist_name: track.artist
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (response.success) {
        // Update local blacklist state
        blacklistedTracks.value.delete(trackKey)
        console.log('✅ Track removed from blacklist on unsave:', track.name)

        // Trigger BannedTracksScreen refresh
        window.dispatchEvent(new CustomEvent('track-unblacklisted', {
          detail: { track: track, trackKey: trackKey }
        }))
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      } else {
        console.warn('Failed to remove track from blacklist (API returned error):', response.error)
      }
    } catch (error) {
      console.warn('Failed to remove track from blacklist on unsave:', error)
    }

    // Trigger SavedTracksScreen refresh when track is unsaved
    try {
      // Dispatch custom event
      window.dispatchEvent(new CustomEvent('track-unsaved', {
        detail: { track: track, trackKey: trackKey }
      }))

      // Update localStorage timestamp to trigger cross-tab refresh
      localStorage.setItem('track-unsaved-timestamp', Date.now().toString())
    } catch (error) {
      // Event dispatch failed, not critical
    }
  } else {
    // Update UI immediately for instant feedback
    savedTracks.value.add(trackKey)
    clientUnsavedTracks.value.delete(trackKey)
    
    // IMMEDIATELY remove track from table for instant UX (before API calls)
    // Emit pending-blacklist so parent removes it from slot
    emit('pending-blacklist', track.id)
    console.log(`💾 [RECS TABLE] Saved track - immediately removing from display: ${track.name}`)
    
    // Emit that user has done an action (for Search Again functionality)
    emit('user-banned-item')
    emit('current-batch-banned-item')

    try {
      // Generate a fallback ISRC if none exists
      const isrcValue = track.external_ids?.isrc || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      let popularity = track.popularity || null
      let releaseDate = track.release_date || null
      let spotifyId = track.id
      let label = track.label || null
      let followers = track.followers || null
      let previewUrl = track.preview_url || null


      // If we don't have complete metadata, try to get enhanced Spotify data
      const needsEnhancedData = (!popularity || !releaseDate || !label || !followers) &&
                                (track.source === 'shazam' || track.source === 'shazam_fallback' || track.source === 'lastfm' || track.source === 'spotify')

      if (needsEnhancedData) {
        try {
          const cleanedArtist = cleanTrackForQuery(track.artist)
          const cleanedTitle = cleanTrackForQuery(track.name)

          const requestParams = {
            artist_name: cleanedArtist,
            track_title: cleanedTitle,
            original_artist: track.artist,
            original_title: track.name,
            source: track.source
          }

          // For Spotify tracks, include the track ID to avoid unnecessary search
          if (track.source === 'spotify' && track.id) {
            requestParams.track_id = track.id
          }

          const spotifyResponse = await http.get('music-discovery/track-preview', {
            params: requestParams
          })

          if (spotifyResponse.success && spotifyResponse.data && spotifyResponse.data.spotify_track_id) {
            // We got a Spotify equivalent! Extract metadata if available
            spotifyId = spotifyResponse.data.spotify_track_id
            if (spotifyResponse.data.metadata) {
              popularity = spotifyResponse.data.metadata.popularity || popularity
              releaseDate = spotifyResponse.data.metadata.release_date || releaseDate
              label = spotifyResponse.data.metadata.label || null
              followers = spotifyResponse.data.metadata.followers || null
              previewUrl = spotifyResponse.data.metadata.preview_url || null
            }
          }
        } catch (conversionError) {
          // Failed to convert to Spotify, continue with original data
        }
      }


      const response = await http.post('music-preferences/save-track', {
        isrc: isrcValue,
        track_name: track.name,
        artist_name: track.artist,
        spotify_id: spotifyId,
        label: label,
        popularity: popularity,
        followers: followers,
        release_date: releaseDate,
        preview_url: previewUrl,
        track_count: 1,
        is_single_track: true
      })

      if (!response.success) {
        // Revert on failure
        savedTracks.value.delete(trackKey)
        throw new Error(response.error || 'Failed to save track')
      }

      // ALSO add to blacklist when saving (for fresh discovery results)
      try {
        const blacklistResponse = await http.post('music-preferences/blacklist-track', {
          spotify_id: spotifyId,
          isrc: isrcValue,
          track_name: track.name,
          artist_name: track.artist
        })

        if (blacklistResponse.success) {
          // Update local blacklist state
          blacklistedTracks.value.add(trackKey)
          console.log('✅ [RECS TABLE] Track blacklisted in backend:', track.name)

          // Trigger BannedTracksScreen refresh
          window.dispatchEvent(new CustomEvent('track-blacklisted', {
            detail: { track: track, trackKey: trackKey }
          }))
          localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
        } else {
          console.warn('⚠️ [RECS TABLE] Failed to blacklist in backend:', blacklistResponse.error)
        }
      } catch (error) {
        console.error('❌ [RECS TABLE] Error blacklisting track:', error)
        // Don't fail the save operation if blacklisting fails
      }

      // Update localStorage
      try {
        const unsavedList = Array.from(clientUnsavedTracks.value)
        localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
      } catch (error) {
        // Failed to update unsaved tracks in localStorage
      }

      // Trigger SavedTracksScreen refresh
      try {
        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('track-saved', {
          detail: { track: track, trackKey: trackKey }
        }))

        // Update localStorage timestamp to trigger cross-tab refresh
        localStorage.setItem('track-saved-timestamp', Date.now().toString())
      } catch (error) {
        // Event dispatch failed, not critical
      }
    } catch (error: any) {
      // Revert on failure
      savedTracks.value.delete(trackKey)
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
    // UNBAN TRACK - Update UI immediately for better UX
    blacklistedTracks.value.delete(trackKey)

    // Remove from pending auto-bans if it was auto-banned (prevents removal on "Search Again")
    if (pendingAutoBannedTracks.value.has(track.id)) {
      pendingAutoBannedTracks.value.delete(track.id)

      // If all pending auto-bans have been removed, notify parent to hide button
      if (pendingAutoBannedTracks.value.size === 0) {
        emit('pending-auto-bans-cleared')
      }
    }

    // Clear the pending blacklist flag if it was set
    if (track.isPendingBlacklist) {
      track.isPendingBlacklist = false
      console.log(`🔓 Cleared pending blacklist flag for: ${track.artist} - ${track.name}`)
    }
    
    // Do backend work in background without blocking UI
    try {
      // Use the same fallback logic as when blacklisting
      const isrcValue = track.external_ids?.isrc || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const deleteData = {
        isrc: isrcValue,
        track_name: track.name,
        artist_name: track.artist
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)
      
      if (!response.success) {
        // Revert UI change if backend failed
        blacklistedTracks.value.add(trackKey)
        if (track.isPendingBlacklist !== undefined) {
          track.isPendingBlacklist = true
        }
        // Failed to unblock track on backend
      }
    } catch (error: any) {
      // Revert UI change if request failed
      blacklistedTracks.value.add(trackKey)
      if (track.isPendingBlacklist !== undefined) {
        track.isPendingBlacklist = true
      }
      // Failed to unblock track
    }
  } else {
    // Block track - show processing state
    processingTrack.value = trackKey
    
    try {
      // Generate a fallback ISRC if none exists
      const isrcValue = track.external_ids?.isrc || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/blacklist-track', {
        isrc: isrcValue,
        track_name: track.name,
        artist_name: track.artist
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)
        // Emit pending-blacklist event (row stays visible, marked)
        // Pass track ID for unique identification
        emit('pending-blacklist', track.id)
        // Emit that user has banned an item (for Search Again functionality)
        emit('user-banned-item')
        emit('current-batch-banned-item')
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
  console.log('🎵 ToggleSpotifyPlayer called:', trackKey, 'expandedTrackId:', expandedTrackId.value)
  
  if (expandedTrackId.value === trackKey) {
    // Closing the preview
    console.log('🎵 Closing preview...')
    expandedTrackId.value = null
    return
  }

  // Opening preview - mark as listened immediately
  console.log('🎵 Opening preview - marking as listened immediately')
  
  // Mark as listened immediately when preview opens
  markTrackAsListened(track)
  
  // Close any existing preview before opening new one
  expandedTrackId.value = null
  expandedTrackId.value = trackKey
}

// Unified preview click handler (open/close and source-aware)
const handlePreviewClick = (track: Track) => {
  const isOpen = expandedTrackId.value === getTrackKey(track)
  if (isOpen) {
    // Always close via toggle to ensure listened marking
    toggleSpotifyPlayer(track)
    return
  }

  // Open behavior based on source
  if (track.source === 'lastfm') {
    previewLastfmTrack(track)
  } else if (track.source === 'shazam' || track.source === 'shazam_fallback') {
    previewShazamTrack(track)
  } else {
    toggleSpotifyPlayer(track)
  }
}

// Mark track as listened and potentially ban it
const markTrackAsListened = async (track: Track) => {
  const trackKey = getTrackKey(track)

  // Mark as listened (optimistic)
  listenedTracks.value.add(trackKey)
  // Reassign to trigger Vue reactivity for Set mutations
  listenedTracks.value = new Set(listenedTracks.value)

  // Persist listened state
  try {
    await http.post('music-preferences/listened-track', {
      track_key: trackKey,
      track_name: track.name,
      artist_name: track.artist,
      spotify_id: track.id,
      isrc: track.external_ids?.isrc
    })
  } catch (e) {
    // If unauthenticated, store locally
    try {
      const keys = Array.from(listenedTracks.value)
      localStorage.setItem('koel-listened-tracks', JSON.stringify(keys))
    } catch {}
  }

  // If auto-ban is enabled, ban the track
  if (banListenedTracks.value) {
    try {
      // Generate a fallback ISRC if none exists (same logic as blacklistTrack function)
      const isrcValue = track.external_ids?.isrc || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/blacklist-track', {
        isrc: isrcValue,
        track_name: track.name,
        artist_name: track.artist
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)

        // Store track ID for deferred removal (will be removed on "Search Again")
        pendingAutoBannedTracks.value.add(track.id)

        // Emit that user has banned an item (enables "Search Again" button)
        // But DON'T emit 'pending-blacklist' yet - track stays visible until Search Again
        emit('user-banned-item')
        emit('current-batch-banned-item')
      }
    } catch (error) {
      console.warn('Failed to auto-ban listened track:', error)
    }
  }
}

// Flush pending auto-banned tracks (called before "Search Again" refill)
const flushPendingAutoBans = () => {
  if (pendingAutoBannedTracks.value.size === 0) {
    return
  }

  // Emit 'pending-blacklist' for each track that was auto-banned
  pendingAutoBannedTracks.value.forEach(trackId => {
    emit('pending-blacklist', trackId)
  })

  // Clear the pending set
  pendingAutoBannedTracks.value.clear()
}

const getRelatedTracks = (track: Track) => {
  // Close any open preview dropdown when getting related tracks
  expandedTrackId.value = null
  emit('related-tracks', track)
}

// Update current page tracks when page changes
const updateCurrentPageTracks = () => {
  // Use original filtered tracks (unsorted) for consistent pagination
  // Sorting will be applied per-page in displayRecommendations computed
  const allFilteredTracks = filteredRecommendations.value
  const start = (currentPage.value - 1) * currentTracksPerPage.value
  const end = start + currentTracksPerPage.value
  currentPageTracks.value = allFilteredTracks.slice(start, end)
  // console.log(`[PAGE TRACKS] Updated current page tracks: ${currentPageTracks.value.length} tracks for page ${currentPage.value}`)
}

// Fetch stats for current page tracks if they don't have stats yet
const fetchStatsForCurrentPage = async () => {
  // Last.fm stats fetching disabled
  return
}

// Pagination methods
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    // Close any open preview dropdown when changing pages
    expandedTrackId.value = null

    // Enable animations for page change
    allowAnimations.value = true
    initialLoadComplete.value = false

    // Scroll to recommendations table
    const tableElement = document.querySelector('.recommendations-table')
    if (tableElement) {
      tableElement.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      })
    }

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

// Preview Last.fm tracks by converting to Spotify
const previewLastfmTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  
  // Close any existing preview before starting new one
  expandedTrackId.value = null
  
  processingTrack.value = trackKey
  isPreviewProcessing.value = true
  
  try {
    // Clean track and artist names for better matching
    const cleanedArtist = cleanTrackForQuery(track.artist)
    const cleanedTitle = cleanTrackForQuery(track.name)
    
    const response = await http.get('music-discovery/track-preview', {
      params: {
        artist_name: cleanedArtist,
        track_title: cleanedTitle,
        original_artist: track.artist, // Keep originals for fallback
        original_title: track.name,
        source: 'lastfm'
      }
    })

    if (response.success && response.data && response.data.spotify_track_id) {
      // Update the track object with Spotify ID for the player
      track.id = response.data.spotify_track_id
      
      // Now open the Spotify player with the converted track
      toggleSpotifyPlayer(track)
    } else {
      showTrackNotFoundNotification(track)
    }
  } catch (error: any) {
    showPreviewErrorNotification(track, error.response?.data?.error || error.message || 'Network error')
  } finally {
    processingTrack.value = null
    isPreviewProcessing.value = false
  }
}

// Preview tracks by converting to Spotify
const previewShazamTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  
  // Close any existing preview before starting new one
  expandedTrackId.value = null
  
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
    
    // Bulk blacklist: emit each individually as pending
    blacklistedKeys.forEach(key => {
      emit('pending-blacklist', key)
    })
    
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
    
    // Clear pending blacklist flag for all tracks by this artist
    const clearedCount = displayRecommendations.value.filter(t => {
      if (t.artist === artistName && t.isPendingBlacklist) {
        t.isPendingBlacklist = false
        return true
      }
      return false
    }).length
    
    if (clearedCount > 0) {
      console.log(`🔓 Cleared pending blacklist flag for ${clearedCount} tracks by ${artistName}`)
    }
    
    // Note: We don't remove from global blacklist as other sections might still want it filtered
    
    console.log('🔓 UI updated immediately (unbanned), now doing API call in background')
    
    // Background API call to remove from backend
    try {
      // Generate a fallback spotify_artist_id if none exists
      const spotifyArtistId = track.artists?.[0]?.id || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const deleteData = {
        artist_name: artistName,
        spotify_artist_id: spotifyArtistId
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-artist?${params}`)
      // console.log('✅ Artist removed from global blacklist API:', response)
    } catch (apiError: any) {
      console.error('❌ Failed to remove from API:', apiError)
      // API failed but local/visual changes are already applied
      console.warn('Artist unbanned locally but API removal failed')
    }
  } else {
    // BAN ARTIST
    // console.log('🚫 BANNING ARTIST - START:', artistName)
    
    // IMMEDIATE UI UPDATE - Add to banned list right away for instant visual feedback
    bannedArtists.value.add(artistName)
    saveBannedArtists()
    addArtistToBlacklist(artistName)
    
    // Emit pending-blacklist with artist name so parent can mark all tracks by this artist
    const artistKey = artistName.toLowerCase().replace(/[^a-z0-9]/g, '-')
    emit('pending-blacklist', artistKey)
    
    // Emit that user has banned an item (for Search Again functionality)
    emit('user-banned-item')
    emit('current-batch-banned-item')
    // Emit that user has banned an item from current batch
    emit('current-batch-banned-item')
    
    // console.log('🚫 UI updated immediately (banned), now doing API call in background')
    
    // Background API call to save to backend
    try {
      // Generate a fallback spotify_artist_id if none exists
      const spotifyArtistId = track.artists?.[0]?.id || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/blacklist-artist', {
        artist_name: artistName,
        spotify_artist_id: spotifyArtistId
      })
      // console.log('✅ Artist saved to global blacklist API:', response)
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
  // Load listened tracks from server (fall back to localStorage if unauthenticated)
  try {
    const resp: any = await http.get('music-preferences/listened-tracks')
    if (resp?.success && Array.isArray(resp.data)) {
      listenedTracks.value = new Set(resp.data as string[])
    }
  } catch (e) {
    // Fallback to localStorage per device
    try {
      const stored = localStorage.getItem('koel-listened-tracks')
      if (stored) {
        const keys: string[] = JSON.parse(stored)
        listenedTracks.value = new Set(keys)
      }
    } catch {}
  }
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
  
  // console.log('🎵 Fetching LastFM stats for tracks:', tracks.length)
  lastfmStatsLoading.value = true
  lastfmError.value = false
  
  try {
    // Process tracks in batches to avoid overwhelming the API
    const batchSize = 20
    const batches = []
    
    for (let i = 0; i < tracks.length; i += batchSize) {
      batches.push(tracks.slice(i, i + batchSize))
    }
    
    // console.log(`🎵 Processing ${batches.length} batches of ${batchSize} tracks each`)
    
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
          // console.log(`🎵 Processed batch ${batchIndex + 1}/${batches.length}`)
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
    
    // console.log('🎵 Finished fetching LastFM stats for all tracks')
    
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
    // console.log('🎵 STATS FETCH SKIPPED - All tracks already have stats')
    return
  }
  
  // console.log('🎵 STATS FETCH START - Total tracks needing stats:', tracksNeedingStats.length, 'out of', tracks.length)
  // console.log('🎵 STATS FETCH - Sample tracks:', tracksNeedingStats.slice(0, 3).map(t => `${t.artist} - ${t.name}`))
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
      // console.log('🎵 Fetching first batch immediately:', firstBatch.length, 'tracks')
      
      const trackData = firstBatch.map(track => ({
        artist: track.artist,
        track: track.name
      }))
      
      try {
        const response = await http.post('lastfm/track-stats', {
          tracks: trackData
        })
        
        if (response.success && response.data) {
          // ADD THIS LINE to see what we actually get back
          console.log('🔍 LastFM API Response Sample:', Object.entries(response.data).slice(0, 2))
          
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
          // console.log('🎵 ✅ First batch completed immediately')
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
      // console.log('🎵 Processing remaining', remainingTracks.length, 'tracks in background...')
      
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
              // console.log(`🎵 Background batch ${batchIndex + 1}/${batches.length} completed`)
            }
            
            // Small delay between background batches
            if (batchIndex < batches.length - 1) {
              await new Promise(resolve => setTimeout(resolve, 300))
            }
            
          } catch (error) {
            console.error(`🎵 Background batch ${batchIndex + 1} failed:`, error)
          }
        }
        
        // console.log('🎵 ✅ All background processing completed')
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
  // console.log(`[SORTING] Sort changed to: ${sortBy.value} (will apply to current page only)`)
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
  // console.log('👀 WATCH TRIGGERED - newRecommendations.length:', newRecommendations.length)
  // console.log('👀 WATCH - oldRecommendations?.length:', oldRecommendations?.length)
  // console.log('👀 WATCH - lastRecommendationsCount:', lastRecommendationsCount.value)
  
  if (newRecommendations.length > 0) {
    // Check if this is truly new recommendations or just stats being updated
    const isNewRecommendations = newRecommendations.length !== lastRecommendationsCount.value || 
                                !oldRecommendations
    
    // Do not clear listened tracks on new recommendations; keep for persistent memory
    
    console.log('👀 RECOMMENDATIONS UPDATED', newRecommendations.length, 'tracks')
    // Sample a few tracks to log structure including match scores
    const samples = newRecommendations.slice(0, 3).map(track => ({
      name: track.name,
      artist: track.artist,
      source: track.source,
      match: track.match,
      hasMatch: track.match !== undefined
    }))
    console.log('👀 SAMPLE TRACKS WITH MATCH', samples)
    
    // console.log('👀 WATCH - isNewRecommendations:', isNewRecommendations)
    
    if (isNewRecommendations) {
      // console.log('🎵 New recommendations received - closing previews')
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
      // console.log('🎵 Stats update detected - keeping previews open and no animations')   
      // Don't change animation state for stats updates - keep current state
    }
    
    // Debug: Check what data we're receiving
    // console.log('🎵 RecommendationsTable received tracks:', newRecommendations.length)
    // console.log('🎵 Sample track data:', newRecommendations[0])
    // console.log('🎵 Sample track has lastfm_stats?', !!newRecommendations[0]?.lastfm_stats)
    
    // Don't filter out banned artists from current session - only filter on fresh searches
    // Store original recommendations and shuffle them for random display
    // console.log('👀 STORING originalRecommendations - before shuffle:', newRecommendations.length)
    originalRecommendations.value = shuffleArray(newRecommendations)
    // console.log('👀 STORED originalRecommendations - after shuffle:', originalRecommendations.value.length)
    
    // Update current page tracks
    updateCurrentPageTracks()
    
    // Last.fm stats fetching disabled
    // No longer fetching Last.fm stats
    
    // console.log('👀 WATCH COMPLETE')
  }
}, { immediate: true })

// Watch for page changes and update current page tracks
watch([currentPage, currentTracksPerPage], async () => {
  updateCurrentPageTracks()
  // Lazy load stats for new page
  await fetchStatsForCurrentPage()
}, { immediate: false })

// Watch for "Ban listened tracks" toggle being turned ON
// Auto-ban all tracks that are already in "Listened" state
watch(banListenedTracks, async (newValue, oldValue) => {
  // Only act when toggle changes from OFF to ON
  if (newValue === true && oldValue === false) {
    console.log('🎵 Ban listened tracks toggle turned ON - auto-banning all listened tracks')

    // Get all currently displayed tracks from slot map
    const tracksToAutoBan: Track[] = []
    for (let i = 0; i < 20; i++) {
      const track = props.slotMap[i]
      if (track && listenedTracks.value.has(getTrackKey(track))) {
        tracksToAutoBan.push(track)
      }
    }

    console.log(`🎵 Found ${tracksToAutoBan.length} listened tracks to auto-ban`)

    // Ban each listened track
    for (const track of tracksToAutoBan) {
      const trackKey = getTrackKey(track)

      // Skip if already blacklisted
      if (blacklistedTracks.value.has(trackKey)) {
        continue
      }

      try {
        const isrcValue = track.external_ids?.isrc || track.id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

        const response = await http.post('music-preferences/blacklist-track', {
          isrc: isrcValue,
          track_name: track.name,
          artist_name: track.artist
        })

        if (response.success) {
          blacklistedTracks.value.add(trackKey)
          pendingAutoBannedTracks.value.add(track.id)
          console.log(`🎵 Auto-banned listened track: ${track.name}`)
        }
      } catch (error) {
        console.warn(`Failed to auto-ban listened track: ${track.name}`, error)
      }
    }

    // If any tracks were banned, emit events to show "Search Again" button
    if (tracksToAutoBan.length > 0) {
      emit('user-banned-item')
      emit('current-batch-banned-item')
    }
  }
})

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

// Expose method for parent component to call before "Search Again"
defineExpose({
  flushPendingAutoBans
})
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

/* Prevent layout shifts during player animations */
.player-row {
  position: relative;
  contain: layout;
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
  background-color: rgba(255, 255, 255, 0.05) !important;
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
  background-color: rgba(255, 255, 255, 0.05);
  border: none;
}
</style>