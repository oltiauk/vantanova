<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader header-image="/VantaNova-Logo.svg" />
    </template>

    <div class="saved-tracks-screen">
      <!-- Search Container -->
      <div class="search-container mb-8">
        <div class="rounded-lg p-4">
          <div class="max-w-4xl mx-auto">
            <div class="relative">
              <!-- Search Icon -->
              <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none z-20 pl-4">
                <Icon :icon="faSearch" class="w-5 h-5 text-white/40" />
              </div>

              <input
                v-model="searchQuery"
                type="text"
                class="w-full py-3 pl-12 pr-12 bg-white/10 rounded-lg focus:outline-none text-white text-lg search-input"
                placeholder="Search for a saved track"
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Sort Controls -->
      <div v-if="sortedTracks.length > 0 && !isLoading" class="flex justify-end items-center mb-4">
        <!-- Sort Dropdown -->
        <div class="relative">
          <button
            class="px-4 py-2 rounded-lg font-medium transition flex items-center gap-2 bg-white/10 text-white/80 hover:bg-white/20"
            style="background-color: rgba(47, 47, 47, 255) !important;"
            @click="toggleSortDropdown"
            @blur="hideSortDropdown"
          >
            {{ getSortText() }}
            <Icon :icon="faChevronDown" class="text-xs" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showSortDropdown"
            class="absolute right-0 mt-2 w-52 rounded-lg shadow-lg z-50"
            style="background-color: rgba(47, 47, 47, 255);"
          >
            <button
              v-for="option in sortOptions"
              :key="option.value"
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
              :class="option.value === sortOptions[0].value ? 'rounded-t-lg' : (option.value === sortOptions[sortOptions.length - 1].value ? 'rounded-b-lg' : '')"
              :style="sortBy === option.value ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setSortFilter(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <div class="animate-spin rounded-full h-8 w-8 border-2 border-k-accent border-t-transparent mb-4" />
          <span class="text-k-text-secondary">Loading liked tracks...</span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="sortedTracks.length === 0 && !isLoading" class="text-center py-12">
        <h4 class="text-lg font-medium text-k-text-primary mb-2">
          {{ searchQuery ? 'No tracks found' : 'No Liked Tracks' }}
        </h4>
        <p class="text-k-text-secondary">
          {{ searchQuery ? 'Try adjusting your search terms' : 'Tracks you save will appear here' }}
        </p>
      </div>

      <!-- Liked Tracks Table -->
      <div v-if="sortedTracks.length > 0 && !isLoading">
        <div class="text-center mb-4">
          <p class="text-k-text-secondary text-sm">
            Liked tracks remain in the list for 24h only.
          </p>
        </div>
        <div class="bg-white/5 rounded-lg overflow-visible relative z-20">
          <div class="overflow-x-auto overflow-y-visible scrollbar-hide" style="overflow-y: visible !important;">
            <table class="w-full">
              <thead>
                <tr class="border-b border-white/10">
                  <th class="text-left py-7 px-2 font-medium" />
                  <th class="text-center px-2 font-medium w-12" />
                  <th class="text-center px-2 font-medium w-12" />
                  <th class="text-left px-2 py-7 font-medium w-auto min-w-48">Artist(s)</th>
                  <th class="text-left px-2 font-medium">Title</th>
                  <th class="text-center px-2 font-medium">Record Label</th>
                  <th class="text-center px-2 font-medium">Followers</th>
                  <th class="text-center px-2 font-medium whitespace-nowrap">Release Date</th>
                  <th class="text-center px-2 font-medium whitespace-nowrap" />
                  <th class="text-center px-1 font-medium whitespace-nowrap w-20" />
                  <th class="text-center px-1 font-medium w-20" />
                </tr>
              </thead>
              <tbody>
                <template v-for="(track, index) in paginatedTracks" :key="track.id">
                  <tr
                    class="transition h-16 border-b border-white/5" :class="[
                      expandedTrackId === getTrackKey(track) ? 'bg-white/5' : 'hover:bg-white/5',
                    ]"
                  >
                    <!-- Index -->
                    <td class="p-3 align-middle">
                      <span class="text-white/60">{{ (currentPage - 1) * tracksPerPage + index + 1 }}</span>
                    </td>

                    <!-- Clipboard -->
                    <td class="p-3 align-middle text-center">
                      <button
                        class="transition disabled:opacity-50 text-gray-300 hover:text-gray-100"
                        title="Copy artist and title"
                        @click="copyTrackInfo(track)"
                      >
                        <Icon
                          :icon="copiedTrackId === track.id ? faCheck : faCopy"
                          class="w-4 h-4"
                        />
                      </button>
                    </td>

                    <!-- Watchlist -->
                    <td class="p-3 align-middle text-center">
                      <button
                        :disabled="followInProgress === getTrackKey(track)"
                        class="watchlist-btn"
                        :class="track.artist_followed
                          ? 'bg-green-600 hover:bg-green-700 rounded-md'
                          : 'bg-[#484948] hover:bg-gray-500 rounded-md'"
                        title="Add the artist to the watchlist"
                        aria-label="Add the artist to the watchlist"
                        @click="followArtist(track)"
                      >
                        <svg
                          v-if="followInProgress === getTrackKey(track)"
                          class="animate-spin h-3 w-3 text-white"
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                        >
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        <img
                          v-else
                          src="/public/icons/FollowArtist.svg"
                          alt="Add artist"
                          :class="['watchlist-icon', track.artist_followed ? 'watchlist-icon-active' : '']"
                        >
                      </button>
                    </td>

                    <!-- Artist -->
                    <td class="p-3 align-middle">
                      <span class="font-medium text-gray-300 leading-none">
                        {{ track.artist_name }}
                      </span>
                    </td>

                    <!-- Track Title -->
                    <td class="p-3 align-middle">
                      <span class="text-gray-300 leading-tight">
                        {{ track.track_name }}
                        <span v-if="track.track_count && track.track_count > 1" class="text-white/50 text-xs ml-1">({{ track.track_count }} tracks)</span>
                      </span>
                    </td>

                    <!-- Label -->
                    <td class="p-3 align-middle text-center">
                      <template v-if="track.label && track.label !== 'Unknown Label'">
                        <template v-for="(label, labelIndex) in track.label.split('/')" :key="labelIndex">
                          <button
                            class="text-gray-300 text-sm hover:text-gray-100 transition-colors cursor-pointer"
                            :title="`Search for ${label.trim()} label`"
                            @click="searchByLabel(label.trim())"
                          >
                            {{ label.trim() }}
                          </button>
                          <span v-if="labelIndex < track.label.split('/').length - 1" class="text-white/60 text-sm mx-1">/</span>
                        </template>
                      </template>
                      <span v-else class="text-white/80 text-sm">{{ track.label || '-' }}</span>
                    </td>

                    <!-- Followers -->
                    <td class="p-3 align-middle text-center">
                      <span class="text-white/80 font-medium">{{ track.followers ? formatNumber(track.followers) : '-' }}</span>
                    </td>

                    <!-- Release Date -->
                    <td class="p-3 align-middle text-center">
                      <span class="text-white/80 text-sm">{{ formatDate(track.release_date) }}</span>
                    </td>

                    <!-- Countdown -->
                    <td class="p-3 align-middle text-center">
                      <div class="flex flex-col items-center mt-4">
                        <span class="text-white/80 font-medium text-sm">
                          {{ getTimeRemaining(track.expires_at) }}
                        </span>
                        <span class="text-white/40 text-xs">remaining</span>
                      </div>
                    </td>

                    <!-- Actions Dropdown -->
                    <td class="px-1 py-3 align-middle">
                      <div class="flex items-center justify-center relative">
                        <button
                          :disabled="isProcessing"
                          class="px-3 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center"
                          title="Actions"
                          @click="toggleActionsDropdown(track.id)"
                          @blur="hideActionsDropdown(track.id)"
                        >
                          <span>Searches</span>
                          <Icon :icon="faChevronDown" class="text-xs ml-1" />
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                          v-if="openActionsDropdown === track.id"
                          class="absolute right-0 w-48 rounded-lg shadow-lg z-[9999] bottom-full mb-1"
                          style="background-color: rgb(67,67,67,255);"
                        >
                          <button
                            class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
                            :class="hasLabel(track) ? 'rounded-t-lg' : 'rounded-t-lg'"
                            @mousedown.prevent="viewRelatedTracks(track)"
                          >
                            <Icon :icon="faSearch" class="w-4 h-4" />
                            Related Tracks
                          </button>
                          <button
                            v-if="hasLabel(track)"
                            class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
                            @mousedown.prevent="searchByLabel((track.label || '').split('/')[0].trim())"
                          >
                            <Icon :icon="faSearch" class="w-4 h-4" />
                            Label Search
                          </button>
                          <button
                            class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
                            :class="hasLabel(track) ? 'rounded-b-lg' : 'rounded-b-lg'"
                            @mousedown.prevent="viewSimilarArtists(track)"
                          >
                            <Icon :icon="faSearch" class="w-4 h-4" />
                            Similar Artists
                          </button>
                        </div>
                      </div>
                    </td>

                    <!-- Actions -->
                    <td class="px-1 py-3 align-middle">
                      <div class="flex items-center justify-center gap-2">
                        <!-- Preview -->
                        <button
                          :disabled="processingTrack === getTrackKey(track)"
                          class="px-3 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center"
                          title="Preview Track"
                          @click="toggleSpotifyPlayer(track)"
                        >
                          <!-- Loading spinner when processing -->
                          <svg v-if="processingTrack === getTrackKey(track) && isPreviewProcessing" class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                          </svg>
                          <!-- Regular icon when not processing -->
                          <img v-if="expandedTrackId !== getTrackKey(track)" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                          <Icon v-else :icon="faTimes" class="w-3 h-3" />
                          <span :class="processingTrack === getTrackKey(track) && isPreviewProcessing ? '' : 'ml-1'">{{ processingTrack === getTrackKey(track) && isPreviewProcessing ? 'Loading...' : (expandedTrackId === getTrackKey(track) ? 'Close' : 'Preview') }}</span>
                        </button>

                        <!-- Remove Track -->
                        <button
                          :disabled="isProcessing"
                          class="p-2 rounded-full transition-colors text-gray-300 hover:text-gray-100 hover:bg-white/10 disabled:opacity-50"
                          title="Remove from liked tracks"
                          @click="unsaveTrack(track)"
                        >
                          <Icon :icon="faTrash" class="w-4 h-4" />
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Spotify Player Dropdown Row with Animation -->
                  <Transition name="spotify-dropdown" mode="out-in">
                  <tr v-if="expandedTrackId === getTrackKey(track)" :key="`spotify-${getTrackKey(track)}-${index}`" class="border-b border-white/5 player-row">
                    <td colspan="12" class="p-0 overflow-hidden">
                      <div class="spotify-player-container p-6 bg-white/3 relative">
                          <div class="mx-auto w-full max-w-[94%]">
                            <div v-if="(track.album_id || track.spotify_id) && (track.album_id || track.spotify_id) !== 'NO_TRACK_FOUND'">
                              <iframe
                                :key="track.track_count && track.track_count > 1 ? (track.album_id || track.spotify_id) : track.spotify_id"
                                :src="track.track_count && track.track_count > 1
                                  ? `https://open.spotify.com/embed/album/${track.album_id || track.spotify_id}?utm_source=generator&theme=0`
                                  : `https://open.spotify.com/embed/track/${track.spotify_id}?utm_source=generator&theme=0`"
                                :title="track.track_count && track.track_count > 1
                                  ? `${track.artist_name} - ${track.track_name} (${track.track_count} tracks)`
                                  : `${track.artist_name} - ${track.track_name}`"
                                class="w-full spotify-embed flex-shrink-0"
                                style="height: 80px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);"
                                frameBorder="0"
                                scrolling="no"
                                allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                                loading="lazy"
                                @load="(event) => { event.target.style.opacity = '1' }"
                                @error="() => {}"
                              />
                            </div>
                            <div v-else class="flex items-center justify-center bg-white/5" style="height: 80px; border-radius: 15px;">
                              <div class="text-center text-white/60">
                                <div class="text-sm font-medium">No Spotify preview available</div>
                              </div>
                            </div>

                            <!-- Spotify Login Link -->
                            <div class="absolute bottom-0 left-0 right-0 text-center">
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

        <!-- Pagination Controls -->
        <div v-if="totalPages > 1 && !isLoading" class="flex items-center justify-center gap-2 mt-8">
          <button
            :disabled="currentPage === 1"
            class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            @click="goToPage(Math.max(1, currentPage - 1))"
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
            @click="goToPage(Math.min(totalPages, currentPage + 1))"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import {
  faCheck,
  faChevronDown,
  faClipboard,
  faClipboardCheck,
  faCopy,
  faHeart,
  faList,
  faPlay,
  faSearch,
  faStop,
  faTimes,
  faTrash,
  faUsers,
} from '@fortawesome/free-solid-svg-icons'
import { http } from '@/services/http'
import { useRouter } from '@/composables/useRouter'
import Router from '@/router'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

// Types
interface SavedTrack {
  id: number
  isrc: string
  track_name: string
  artist_name: string
  artist_followed?: boolean
  spotify_id: string | null
  spotify_artist_id?: string | null
  created_at: string
  expires_at: string
  // Additional Spotify data
  label?: string
  followers?: number
  popularity?: number
  release_date?: string
  preview_url?: string
  track_count?: number
  is_single_track?: boolean
  album_id?: string
}


interface WatchlistEventArtist {
  id?: number
  artist_id: string
  artist_name: string
  artist_image_url?: string | null
  followers?: number | null
}

interface WatchlistEventDetail {
  action: 'added'
  artist: WatchlistEventArtist
}

const WATCHLIST_EVENT = 'artist-watchlist-updated' as const

// Initialize router
const { onRouteChanged, go } = useRouter()

// State
const isLoading = ref(false)
const isProcessing = ref(false)
const followInProgress = ref<string | null>(null)
const searchQuery = ref('')
const sortBy = ref('most_recent')
const currentPage = ref(1)
const tracksPerPage = 20
const tracks = ref<SavedTrack[]>([])
const currentAudio = ref<HTMLAudioElement | null>(null)
const currentPlayingTrack = ref<SavedTrack | null>(null)
const showSortDropdown = ref(false)
const clientUnsavedTracks = ref<Set<string>>(new Set())
const allowAnimations = ref(true)
const initialLoadComplete = ref(false)
const copiedTrackId = ref<number | null>(null)
const expandedTrackId = ref<string | null>(null)
const processingTrack = ref<string | null>(null)
const isPreviewProcessing = ref(false)
const openActionsDropdown = ref<number | null>(null)

// Sort options for the dropdown
const sortOptions = [
  { value: 'most_recent', label: 'Recently saved' },
  { value: 'latest_releases', label: 'Latest Releases' },
  { value: 'most_followers', label: 'Most Followers' },
]

// Computed properties
const filteredTracks = computed(() => {
  if (!searchQuery.value.trim()) {
    return tracks.value
  }

  const query = searchQuery.value.toLowerCase()
  return tracks.value.filter(track =>
    track.artist_name.toLowerCase().includes(query)
    || track.track_name.toLowerCase().includes(query),
  )
})

const sortedTracks = computed(() => {
  const tracksToSort = [...filteredTracks.value]

  switch (sortBy.value) {
    case 'latest_releases':
      return tracksToSort.sort((a, b) => {
        const dateA = a.release_date && a.release_date !== 'Unknown' ? new Date(a.release_date).getTime() : 0
        const dateB = b.release_date && b.release_date !== 'Unknown' ? new Date(b.release_date).getTime() : 0
        return dateB - dateA
      })
    case 'most_followers':
      return tracksToSort.sort((a, b) => (b.followers || 0) - (a.followers || 0))
    case 'most_recent':
    default:
      return tracksToSort.sort((a, b) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
      )
  }
})

const paginatedTracks = computed(() => {
  const start = (currentPage.value - 1) * tracksPerPage
  const end = start + tracksPerPage
  return sortedTracks.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(sortedTracks.value.length / tracksPerPage)
})

const visiblePages = computed(() => {
  const pages = []
  const maxVisible = 5
  const total = totalPages.value

  if (total <= maxVisible) {
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    const current = currentPage.value
    let start = Math.max(1, current - 2)
    let end = Math.min(total, current + 2)

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

// Helper functions
const getTrackKey = (track: SavedTrack): string => {
  return `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Format date helper function
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
      return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
    } else if (diffDays < 365) {
      const months = Math.floor(diffDays / 30)
      return `${months} month${months === 1 ? '' : 's'} ago`
    } else {
      return dateString.split('T')[0]
    }
  } catch (error) {
    return dateString.split('T')[0]
  }
}

const formatNumber = (num: number): string => {
  if (num >= 1000000) {
    return `${(num / 1000000).toFixed(1)}M`
  } else if (num >= 1000) {
    return `${(num / 1000).toFixed(1)}K`
  }
  return num.toString()
}

const getTimeRemaining = (expiresAt: string): string => {
  const now = new Date().getTime()
  const expiry = new Date(expiresAt).getTime()
  const diff = expiry - now

  if (diff <= 0) {
    return 'Expired'
  }

  const hours = Math.floor(diff / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))

  if (hours > 0) {
    return `${hours}h ${minutes}m`
  } else {
    return `${minutes}m`
  }
}

const isTrackPlaying = (track: SavedTrack): boolean => {
  return currentPlayingTrack.value?.id === track.id
}

// Sort dropdown functions
const toggleSortDropdown = () => {
  showSortDropdown.value = !showSortDropdown.value
}

const hideSortDropdown = () => {
  setTimeout(() => {
    showSortDropdown.value = false
  }, 150)
}

const toggleActionsDropdown = (trackId: number) => {
  openActionsDropdown.value = openActionsDropdown.value === trackId ? null : trackId
}

const hideActionsDropdown = (trackId: number) => {
  setTimeout(() => {
    if (openActionsDropdown.value === trackId) {
      openActionsDropdown.value = null
    }
  }, 150)
}

const setSortFilter = (value: string) => {
  sortBy.value = value
  showSortDropdown.value = false
  currentPage.value = 1 // Reset to first page when sorting changes
}

// Pagination with animations
const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
    // Close any open preview dropdown when changing pages
    expandedTrackId.value = null
    // Close any open actions dropdown when changing pages
    openActionsDropdown.value = null

    // Enable animations for page change
    allowAnimations.value = true
    initialLoadComplete.value = false

    // Scroll to table
    const tableElement = document.querySelector('.saved-tracks-screen')
    if (tableElement) {
      tableElement.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      })
    }

    // Update page
    currentPage.value = page

    // Disable animations after they complete
    setTimeout(() => {
      allowAnimations.value = false
      initialLoadComplete.value = true
    }, 2000)
  }
}

const getSortText = () => {
  const option = sortOptions.find(opt => opt.value === sortBy.value)
  return option ? `Sort by: ${option.label}` : 'Sort by: Recently saved'
}

const hasLabel = (track: SavedTrack): boolean => {
  return !!track.label && track.label !== 'Unknown Label'
}

// Load client-side unsaved tracks from localStorage
const loadClientUnsavedTracks = () => {
  try {
    const stored = localStorage.getItem('koel-client-unsaved-tracks')
    if (stored) {
      const unsavedList = JSON.parse(stored)
      clientUnsavedTracks.value = new Set(unsavedList)
    }
  } catch (error) {
    console.warn('Failed to load client unsaved tracks from localStorage:', error)
  }
}

// Methods
const loadTracks = async () => {
  isLoading.value = true

  try {
    // Load client-side unsaved tracks first
    loadClientUnsavedTracks()

    const response = await http.get('music-preferences/saved-tracks')
    if (response.success && response.data) {
      // Filter out client-side unsaved tracks
      const filteredTracks = response.data.filter((track: SavedTrack) => {
        const trackKey = getTrackKey(track)
        return !clientUnsavedTracks.value.has(trackKey)
      })

      tracks.value = filteredTracks

      // Set default values for tracks that don't have additional data
      tracks.value.forEach(track => {
        track.artist_followed = !!track.artist_followed
        if (!track.label) {
          track.label = 'Unknown Label'
        }
        if (!track.popularity) {
          track.popularity = 0
        }
        if (!track.release_date) {
          track.release_date = 'Unknown'
        }
        if (!track.followers) {
          track.followers = 0
        }
      })

      // Enable animations when tracks are loaded
      if (filteredTracks.length > 0) {
        allowAnimations.value = true
        initialLoadComplete.value = false

        // Disable animations after they complete
        setTimeout(() => {
          allowAnimations.value = false
          initialLoadComplete.value = true
        }, 2000)
      }
    } else {
      tracks.value = []
    }
  } catch (error) {
    console.error('Failed to load saved tracks:', error)
    tracks.value = []
  } finally {
    isLoading.value = false
  }
}

const loadAdditionalSpotifyData = async () => {
  console.log('🚨 [SAVED TRACKS] loadAdditionalSpotifyData called - DISABLED to prevent API costs!')
  console.log('🚨 [SAVED TRACKS] This function should not be called anymore')
  console.log('🚨 [SAVED TRACKS] Tracks count:', tracks.value.length)
  console.log('🚨 [SAVED TRACKS] Call stack:', new Error().stack)

  // Set default values for all tracks to prevent loading states
  tracks.value.forEach(track => {
    if (!track.label) {
      track.label = 'Unknown Label'
    }
    if (!track.popularity) {
      track.popularity = 0
    }
    if (!track.release_date) {
      track.release_date = 'Unknown'
    }
    if (!track.followers) {
      track.followers = 0
    }
  })

  console.log('🚨 [SAVED TRACKS] Default values set, returning immediately')
}

const checkExistingEmbedRequest = async (track: SavedTrack): Promise<any | null> => {
  try {
    // Check if this track has been embedded before by looking in cache/storage
    // This would check your existing embed cache system
    const cacheKey = `spotify-embed-${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
    const cachedEmbed = localStorage.getItem(cacheKey)

    if (cachedEmbed) {
      const embedData = JSON.parse(cachedEmbed)
      // Extract Spotify ID from embed URL if available
      const spotifyIdMatch = embedData.url?.match(/track\/([a-zA-Z0-9]+)/)
      if (spotifyIdMatch) {
        return { spotify_id: spotifyIdMatch[1] }
      }
    }

    return null
  } catch (error) {
    console.warn('Failed to check existing embed request:', error)
    return null
  }
}

const isValidSpotifyId = (id: string | null): boolean => {
  if (!id) {
    return false
  }
  // Spotify IDs are base62 encoded and typically 22 characters long
  // Koel UUIDs are 36 characters with dashes
  return !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id) && id.length > 10
}

const openSpotifyArtistPage = async (track: SavedTrack) => {
  try {
    // Try to get artist Spotify URL from search
    const response = await http.get('music-discovery/artist-search', {
      params: {
        artist_name: track.artist_name,
      },
    })

    if (response.success && response.data && response.data.spotify_url) {
      window.open(response.data.spotify_url, '_blank', 'noopener,noreferrer')
    } else {
      // Fallback: search on Spotify web with artist name
      const encodedArtist = encodeURIComponent(track.artist_name)
      window.open(`https://open.spotify.com/search/${encodedArtist}`, '_blank', 'noopener,noreferrer')
    }
  } catch (error) {
    // Fallback: search on Spotify web with artist name
    const encodedArtist = encodeURIComponent(track.artist_name)
    window.open(`https://open.spotify.com/search/${encodedArtist}`, '_blank', 'noopener,noreferrer')
  }
}

const openSpotifyTrackPage = (track: SavedTrack) => {
  if (track.spotify_id && isValidSpotifyId(track.spotify_id)) {
    // Direct link to Spotify track
    window.open(`https://open.spotify.com/track/${track.spotify_id}`, '_blank', 'noopener,noreferrer')
  } else {
    // Fallback: search on Spotify web with track and artist
    const encodedSearch = encodeURIComponent(`${track.artist_name} ${track.track_name}`)
    window.open(`https://open.spotify.com/search/${encodedSearch}`, '_blank', 'noopener,noreferrer')
  }
}

const fetchSpotifyTrackData = async (track: SavedTrack) => {
  console.log('🚨 [SAVED TRACKS] fetchSpotifyTrackData called - DISABLED to prevent API costs!')
  console.log('🚨 [SAVED TRACKS] Track:', track.track_name, 'by', track.artist_name)
  console.log('🚨 [SAVED TRACKS] Call stack:', new Error().stack)

  // Set default values only - NO API CALLS
  Object.assign(track, {
    label: 'Unknown Label',
    popularity: 0,
    release_date: 'Unknown',
    followers: 0,
  })

  console.log('🚨 [SAVED TRACKS] Default values set for track, returning immediately')
}

// DISABLED: This function was causing infinite loops and expensive API calls
const searchAndFetchSpotifyData = async (track: SavedTrack) => {
  console.log('Spotify search disabled to prevent API costs')
  // Set default values only
  Object.assign(track, {
    label: 'Unknown Label',
    popularity: 0,
    release_date: 'Unknown',
    followers: 0,
  })
}

const unsaveTrack = async (track: SavedTrack) => {
  if (isProcessing.value) {
    return
  }

  // Close any open preview dropdown when unsaving tracks
  expandedTrackId.value = null
  // Close any open actions dropdown when unsaving tracks
  openActionsDropdown.value = null

  isProcessing.value = true

  try {
    // Remove from local state immediately for better UX
    tracks.value = tracks.value.filter(t => t.id !== track.id)

    // Reset to first page if current page becomes empty
    if (paginatedTracks.value.length === 0 && currentPage.value > 1) {
      currentPage.value = Math.max(1, currentPage.value - 1)
    }

    console.log(`Track ${track.track_name} has been removed from saved tracks`)

    // Since there's no DELETE endpoint for saved tracks in the current implementation,
    // tracks will naturally expire in 24 hours anyway
    // In a full implementation, you would call:
    // await http.delete(`music-preferences/saved-tracks/${track.id}`)
  } catch (error: any) {
    console.error('Failed to unsave track:', error)
    // Restore tracks on error
    await loadTracks()
  } finally {
    isProcessing.value = false
  }
}

const togglePreview = async (track: SavedTrack) => {
  if (!track.preview_url) {
    return
  }

  if (isTrackPlaying(track)) {
    // Stop current preview
    if (currentAudio.value) {
      currentAudio.value.pause()
      currentAudio.value = null
    }
    currentPlayingTrack.value = null
  } else {
    // Stop any currently playing preview
    if (currentAudio.value) {
      currentAudio.value.pause()
    }

    // Start new preview
    currentAudio.value = new Audio(track.preview_url)
    currentPlayingTrack.value = track

    currentAudio.value.addEventListener('ended', () => {
      currentPlayingTrack.value = null
      currentAudio.value = null
    })

    try {
      await currentAudio.value.play()
    } catch (error) {
      console.error('Failed to play preview:', error)
      currentPlayingTrack.value = null
      currentAudio.value = null
    }
  }
}

const fetchFirstTrackFromAlbum = async (albumId: string | undefined | null): Promise<string | null> => {
  if (!albumId || !isValidSpotifyId(albumId)) {
    return null
  }

  try {
    const response = await http.get(`music-preferences/spotify/album/${albumId}`)
    if (response.success && response.data?.tracks?.items?.length) {
      const firstTrack = response.data.tracks.items[0]
      return firstTrack.id || (firstTrack.uri?.match(/spotify:track:([a-zA-Z0-9]+)/)?.[1] ?? null)
    }
  } catch (error) {
    console.warn('🔍 [SAVED TRACKS] Failed to fetch first track from album:', error)
  }

  return null
}

const viewRelatedTracks = async (track: SavedTrack) => {
  console.log('🔍 [SAVED TRACKS] viewRelatedTracks called with track:', track)

  // Close the actions dropdown
  openActionsDropdown.value = null

  // Store seed track data in localStorage for MusicDiscoveryScreen to pick up
  let seedTrackId: string | null = null

  if (track.spotify_id && isValidSpotifyId(track.spotify_id)) {
    seedTrackId = track.spotify_id
  } else if (track.track_count && track.track_count > 1) {
    // Album: get the first track
    seedTrackId = await fetchFirstTrackFromAlbum(track.album_id || track.spotify_id)
  }

  if (!seedTrackId) {
    console.log('🔍 [SAVED TRACKS] No valid Spotify track ID available for related search')
    return
  }

  console.log('🔍 [SAVED TRACKS] Setting up seed track data for music discovery')

  const seedTrackData = {
    id: seedTrackId,
    name: track.track_name,
    artist: track.artist_name,
    source: 'savedTracks',
    timestamp: Date.now(),
  }

  localStorage.setItem('koel-music-discovery-seed-track', JSON.stringify(seedTrackData))
  console.log('🔍 [SAVED TRACKS] Stored seed track data:', seedTrackData)

  // Try direct hash navigation as a fallback
  console.log('🔍 [SAVED TRACKS] Trying direct hash navigation to /discover')
  window.location.hash = '#/discover'
}

const viewSimilarArtists = (track: SavedTrack) => {
  console.log('🔍 [SAVED TRACKS] viewSimilarArtists called with track:', track)

  // Close the actions dropdown
  openActionsDropdown.value = null

  // Store artist data in localStorage for SimilarArtistsScreen to pick up
  const seedArtistData = {
    name: track.artist_name,
    source: 'savedTracks',
    timestamp: Date.now(),
  }

  localStorage.setItem('koel-similar-artists-seed-artist', JSON.stringify(seedArtistData))
  console.log('🔍 [SAVED TRACKS] Stored seed artist data:', seedArtistData)

  // Navigate to similar artists screen
  console.log('🔍 [SAVED TRACKS] Navigating to similar artists screen')
  window.location.hash = '#/similar-artists'
}

// Spotify player functionality
const toggleSpotifyPlayer = async (track: SavedTrack) => {
  const trackKey = getTrackKey(track)

  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

  const hasAlbumId = !!(track.album_id && isValidSpotifyId(track.album_id))
  let previewTrackId: string | null = hasAlbumId
    ? track.album_id as string
    : (track.spotify_id && isValidSpotifyId(track.spotify_id) ? track.spotify_id : null)

  if (!previewTrackId) {
    processingTrack.value = trackKey
    isPreviewProcessing.value = true

    try {
      if (!previewTrackId) {
        // Try to find Spotify equivalent for this saved track
        const response = await http.get('music-discovery/track-preview', {
          params: {
            artist_name: track.artist_name,
            track_title: track.track_name,
            source: 'saved',
          },
        })

        if (response.success && response.data && response.data.spotify_track_id) {
          track.spotify_id = response.data.spotify_track_id
          previewTrackId = track.spotify_id
        } else {
          // Fallback: try checking cached embed requests
          const cached = await checkExistingEmbedRequest(track)
          if (cached && cached.spotify_id && isValidSpotifyId(cached.spotify_id)) {
            track.spotify_id = cached.spotify_id
            previewTrackId = track.spotify_id
          }
        }
      }

      if (!previewTrackId && !hasAlbumId) {
        showTrackNotFoundNotification(track)
        return
      }
    } catch (error: any) {
      showPreviewErrorNotification(track, error.response?.data?.error || error.message || 'Network error')
      return
    } finally {
      processingTrack.value = null
      isPreviewProcessing.value = false
    }
  }

  if (!previewTrackId && !hasAlbumId) {
    showTrackNotFoundNotification(track)
    return
  }

  // Track has preview data ready, show player
  expandedTrackId.value = trackKey
}

// Enhanced notification functions for better UX
const showTrackNotFoundNotification = (track: SavedTrack) => {
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
          Could not find "<span class="font-medium">${track.track_name}</span>" by <span class="font-medium">${track.artist_name}</span> on Spotify.
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

const showPreviewErrorNotification = (track: SavedTrack, errorMessage: string) => {
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
          Failed to preview "<span class="font-medium">${track.track_name}</span>" by <span class="font-medium">${track.artist_name}</span>.
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

const copyTrackInfo = async (track: SavedTrack) => {
  try {
    const textToCopy = `${track.artist_name} - ${track.track_name}`
    await navigator.clipboard.writeText(textToCopy)

    // Show success animation
    copiedTrackId.value = track.id

    // Remove animation after duration
    setTimeout(() => {
      copiedTrackId.value = null
    }, 1000)

    console.log(`Copied to clipboard: ${textToCopy}`)
  } catch (error) {
    console.error('Failed to copy to clipboard:', error)

    // Fallback for older browsers
    try {
      const textArea = document.createElement('textarea')
      textArea.value = `${track.artist_name} - ${track.track_name}`
      document.body.appendChild(textArea)
      textArea.select()
      document.execCommand('copy')
      document.body.removeChild(textArea)

      // Show success animation for fallback too
      copiedTrackId.value = track.id
      setTimeout(() => {
        copiedTrackId.value = null
      }, 1000)

      console.log(`Copied to clipboard (fallback): ${textArea.value}`)
    } catch (fallbackError) {
      console.error('Clipboard fallback also failed:', fallbackError)
    }
  }
}

const searchByLabel = (label: string) => {
  console.log('🏷️ [SAVED TRACKS] Searching by label:', label)

  // Store the label search query in localStorage for LabelSearchScreen to pick up
  const labelSearchData = {
    query: label,
    source: 'savedTracks',
    timestamp: Date.now(),
    autoSearch: true,
  }

  localStorage.setItem('koel-label-search-query', JSON.stringify(labelSearchData))
  console.log('🏷️ [SAVED TRACKS] Stored label search data:', labelSearchData)

  // Navigate to label search screen
  window.location.hash = '#/label-search'
}

// Reset pagination when search or sort changes
const resetPagination = () => {
  currentPage.value = 1
  // Close any open dropdowns when search/sort changes
  openActionsDropdown.value = null
}

// Auto-refresh when tracks are saved or unsaved
const handleTrackSaved = () => {
  console.log('🔄 [SAVED TRACKS] Track saved event detected, refreshing...')
  loadTracks()
}

const handleTrackUnsaved = () => {
  console.log('🔄 [SAVED TRACKS] Track unsaved event detected, refreshing...')
  loadTracks()
}

const handleStorageChange = (e: StorageEvent) => {
  if (e.key === 'track-saved-timestamp' || e.key === 'track-unsaved-timestamp') {
    console.log('🔄 [SAVED TRACKS] Storage change detected, refreshing...')
    loadTracks()
  }
}

const handleVisibilityChange = () => {
  if (!document.hidden) {
    console.log('🔄 [SAVED TRACKS] Page became visible, refreshing...')
    loadTracks()
  }
}

// Lifecycle
onMounted(async () => {
  await loadTracks()

  // Listen for custom events
  window.addEventListener('track-saved', handleTrackSaved)
  window.addEventListener('track-unsaved', handleTrackUnsaved)

  // Listen for localStorage changes (works across tabs)
  window.addEventListener('storage', handleStorageChange)

  // Listen for page visibility changes
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  window.removeEventListener('track-saved', handleTrackSaved)
  window.removeEventListener('track-unsaved', handleTrackUnsaved)
  window.removeEventListener('storage', handleStorageChange)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
})

// Always refresh when navigating to SavedTracks (override the previous logic)
onRouteChanged(route => {
  if (route.screen === 'SavedTracks') {
    console.log('🔄 [SAVED TRACKS] Navigated to SavedTracks, refreshing...')

    // Enable animations when entering SavedTracks screen
    if (tracks.value.length > 0) {
      allowAnimations.value = true
      initialLoadComplete.value = false

      // Disable animations after they complete
      setTimeout(() => {
        allowAnimations.value = false
        initialLoadComplete.value = true
      }, 2000)
    }

    loadTracks()
  }
})

// Watch search query and sort to reset pagination
watch([searchQuery, sortBy], resetPagination)

const emitWatchlistAddition = (artist: WatchlistEventArtist) => {
  window.dispatchEvent(new CustomEvent<WatchlistEventDetail>(WATCHLIST_EVENT, {
    detail: {
      action: 'added',
      artist,
    },
  }))
}

const followArtist = async (track: SavedTrack) => {
  if (track.artist_followed) {
    return
  }

  const trackKey = getTrackKey(track)
  followInProgress.value = trackKey

  try {
    let spotifyArtistId = track.spotify_artist_id || null
    let artistImage: string | null = null
    let followers: number | undefined

    if (!spotifyArtistId) {
      const response = await http.post<{ success: boolean, data: Array<{ id: string, image?: string, followers?: number }> }>('music-preferences/artist-watchlist/search', {
        query: track.artist_name,
      })

      if (response.success && response.data.length > 0) {
        spotifyArtistId = response.data[0].id
        artistImage = response.data[0].image || null
        followers = response.data[0].followers
      }
    }

    if (!spotifyArtistId) {
      throw new Error('Unable to find this artist on Spotify.')
    }

    const watchlistResponse = await http.post<{ success: boolean, data: WatchlistEventArtist }>('music-preferences/artist-watchlist', {
      artist_id: spotifyArtistId,
      artist_name: track.artist_name,
      artist_image_url: artistImage,
      followers,
    })

    if (watchlistResponse.success && watchlistResponse.data) {
      emitWatchlistAddition(watchlistResponse.data)
      track.artist_followed = true
      // Persist artist ID locally for future refreshes
      if (watchlistResponse.data.artist_id) {
        track.spotify_artist_id = watchlistResponse.data.artist_id
      }
    }
  } catch (error: any) {
    console.error('Failed to follow artist:', error)
    alert(error.response?.data?.error || error.message || 'Unable to follow artist right now.')
  } finally {
    followInProgress.value = null
  }
}
</script>

<style scoped>
.search-input::placeholder {
  text-align: center;
}

.search-input:focus::placeholder {
  opacity: 0;
}

/* Hide scrollbars */
.scrollbar-hide {
  -ms-overflow-style: none; /* Internet Explorer 10+ */
  scrollbar-width: none; /* Firefox */
}

.scrollbar-hide::-webkit-scrollbar {
  display: none; /* Safari and Chrome */
}

/* Copy success animation */
.copy-success {
  @apply text-[#9d0cc6];
  animation: tickSuccess 1s ease-out;
}

@keyframes tickSuccess {
  0% {
    transform: scale(1);
    color: rgba(255, 255, 255, 0.4);
  }
  15% {
    transform: scale(1.4);
    color: rgb(157, 12, 198);
  }
  30% {
    transform: scale(1.1);
    color: rgb(157, 12, 198);
  }
  45% {
    transform: scale(1.2);
    color: rgb(157, 12, 198);
  }
  60% {
    transform: scale(1);
    color: rgb(157, 12, 198);
  }
  100% {
    transform: scale(1);
    color: rgb(157, 12, 198);
  }
}

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

.watchlist-btn {
  @apply inline-flex items-center justify-center w-8 h-8 text-white transition disabled:opacity-40 disabled:cursor-not-allowed;
}

.watchlist-icon {
  width: 16px;
  height: 16px;
  filter: brightness(0) saturate(100%) invert(76%) sepia(4%) saturate(342%) hue-rotate(169deg) brightness(92%)
    contrast(88%);
  opacity: 0.85;
  transition:
    opacity 0.2s ease,
    filter 0.2s ease;
}

.watchlist-icon-active {
  filter: brightness(0) saturate(100%) invert(100%);
  opacity: 1;
}

.watchlist-btn:hover .watchlist-icon {
  opacity: 1;
  filter: brightness(0) saturate(100%) invert(100%);
}
</style>
