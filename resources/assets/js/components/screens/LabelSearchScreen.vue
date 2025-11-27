<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="simple" class="text-center" header-image="/VantaNova-Logo.svg">
        <template #subtitle>
          Find music from specific record labels
        </template>
      </ScreenHeader>
    </template>

    <div class="wrapper">
      <div class="search-form max-w-[67rem] mx-auto space-y-6">
        <!-- Search Input -->
        <div class="relative">
          <div class="flex justify-center">
            <div class="flex w-full max-w-3xl">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search for a Record Label"
                class="flex-1 py-3 pl-4 pr-4 bg-white/10 rounded-l-lg border-0 focus:outline-none text-white text-lg search-input"
                @keyup.enter="performSearch"
              >
              <button
                class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 text-white rounded-r-lg transition-colors flex items-center justify-center"
                :disabled="!searchQuery.trim() || isLoading"
                @click="performSearch"
              >
                <Icon :icon="faSearch" class="w-5 h-5" />
              </button>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="flex items-center justify-center gap-32">
          <!-- Fresh Drops Toggle -->
          <div class="flex items-center gap-x-3">
            <div class="flex flex-col items-center">
              <span class="text-base font-medium text-white whitespace-nowrap mt-4">Fresh Drops</span>
              <span class="text-xs text-white/60 whitespace-nowrap">(2 weeks)</span>
            </div>
            <button
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none" :class="[
                freshDropsFilter ? 'bg-k-accent' : 'bg-gray-600',
              ]"
              @click="handleFreshDropsToggle"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="[
                  freshDropsFilter ? 'translate-x-5' : 'translate-x-0',
                ]"
              />
            </button>
          </div>

          <!-- Release Year Filter -->
          <div class="flex items-center gap-3">
            <label class="text-base font-medium text-white/80 whitespace-nowrap">Release Year</label>
            <input
              v-model="releaseYearFilter"
              type="text"
              placeholder="Type a year"
              class="py-2.5 px-3 bg-white/10 rounded border-0 focus:outline-none text-white placeholder-white/40 placeholder:text-xs text-xs w-24 text-center"
              @input="handleReleaseYearChange"
            >
          </div>

          <!-- Hidden Gems Toggle -->
          <div class="flex items-center gap-x-3">
            <div class="flex flex-col items-center ">
              <span class="text-base font-medium text-white whitespace-nowrap">Hidden Gems</span>
            </div>
            <button
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none" :class="[
                popularityFilter ? 'bg-k-accent' : 'bg-gray-600',
              ]"
              @click="popularityFilter = !popularityFilter"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="[
                  popularityFilter ? 'translate-x-5' : 'translate-x-0',
                ]"
              />
            </button>
          </div>
        </div>

        <!-- Search Button -->
        <div class="flex justify-center gap-4">
          <button
            :disabled="!searchQuery.trim() || isLoading"
            class="px-6 py-2 bg-k-accent text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-500 transition-colors"
            @click="performSearch"
          >
            Search
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-white mb-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        </div>
      </div>

      <!-- Error Message -->
      <div v-else-if="errorMessage" class="mt-6 p-4 bg-red-500/20 border border-red-500/30 rounded-lg text-red-200 text-center">
        {{ errorMessage }}
      </div>

      <!-- Results Table -->
      <div v-else-if="displayTracks.length > 0" class="mt-8">
        <!-- Info Message -->
        <div class="text-center mb-4">
          <p class="text-k-text-secondary text-sm">
            Save or ban tracks to mark them. Use Load More to see additional results.
          </p>
        </div>

        <div class="bg-white/5 rounded-lg overflow-hidden">
          <div class="overflow-x-auto scrollbar-hide">
            <table class="w-full">
              <thead>
                <tr class="border-b border-white/10">
                  <th class="text-left py-7 px-3 font-medium" />
                  <th class="text-left px-3 py-7 font-medium w-auto min-w-48">Artist(s)</th>
                  <th class="text-left px-3 font-medium">Title</th>
                  <th class="text-center px-3 font-medium">Popularity</th>
                  <th class="text-center px-3 font-medium whitespace-nowrap">Followers</th>
                  <th class="text-center px-3 font-medium whitespace-nowrap">Release Date</th>
                  <th class="text-center px-3 font-medium" />
                  <th class="text-center px-3 font-medium" />
                </tr>
              </thead>
              <tbody>
                <template v-for="(track, index) in displayTracks" :key="track.spotify_id">
                  <tr
                    class="transition h-16 border-b border-white/5" :class="[
                      expandedTrackId === getTrackKey(track) ? 'bg-white/5' : 'hover:bg-white/5'
                    ]"
                  >
                    <!-- Index -->
                    <td class="p-3 align-middle">
                      <span class="text-white/60">{{ index + 1 }}</span>
                    </td>

                    <!-- Artist -->
                    <td class="p-3 align-middle">
                      <button
                        class="font-medium text-gray-300 hover:text-gray-100 transition-colors cursor-pointer text-left leading-none"
                        :title="`View ${track.artist_name} on Spotify`"
                        @click="openSpotifyArtistPage(track)"
                      >
                        {{ track.artist_name }}
                      </button>
                    </td>

                    <!-- Release Title -->
                    <td class="p-3 align-middle">
                      <button
                        class="text-gray-300 hover:text-gray-100 transition-colors cursor-pointer text-left"
                        :title="track.is_single_track ? `View '${track.track_name}' on Spotify` : `View '${track.release_name}' album on Spotify`"
                        @click="openSpotifyReleasePage(track)"
                      >
                        {{ track.release_name || track.track_name }}
                        <span v-if="!track.is_single_track && track.track_count > 1" class="text-white/50 text-xs ml-1">({{ track.track_count }} tracks)</span>
                      </button>
                    </td>

                    <!-- Popularity -->
                    <td class="p-3 align-middle text-center">
                      <span class="text-white/80 font-medium">{{ track.popularity }}%</span>
                    </td>

                    <!-- Followers -->
                    <td class="p-3 align-middle text-center">
                      <span class="text-white/80 text-sm">{{ formatFollowers(track.followers || 0) }}</span>
                    </td>

                    <!-- Release Date -->
                    <td class="p-3 align-middle text-center">
                      <span class="text-white/80 text-sm">{{ formatDate(track.release_date) }}</span>
                    </td>

                    <!-- Actions -->
                    <td class="pl-3 align-middle">
                      <div class="flex gap-2 justify-center">
                        <!-- Save Button (24h) -->
                        <button
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="track.isSaved
                            ? 'bg-green-600 hover:bg-green-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'"
                          class="w-10 h-10 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center min-h-[34px]"
                          :title="track.isSaved ? 'Click to unsave track' : 'Save the Track (24h)'"
                          @click="saveTrack(track)"
                        >
                          <Icon :icon="faHeart" class="text-sm" />
                        </button>

                        <!-- Blacklist Button -->
                        <button
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="track.isBanned
                            ? 'bg-red-600 hover:bg-red-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'"
                          class="w-10 h-10 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center min-h-[34px]"
                          :title="track.isBanned ? 'Click to unblock track' : 'Ban the Track'"
                          @click="banTrack(track)"
                        >
                          <Icon :icon="faBan" class="text-sm" />
                        </button>
                      </div>
                    </td>

                    <!-- Preview Button -->
                    <td class="pr-3 pl-4 align-middle text-center">
                      <button
                        :disabled="processingTrack === getTrackKey(track)"
                        class="px-3 py-2 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 w-[100px] h-[34px] justify-center mx-auto" :class="[
                          (expandedTrackId === getTrackKey(track) || listenedTracks.has(getTrackKey(track)))
                            ? 'bg-[#868685] hover:bg-[#6d6d6d] text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white',
                        ]"
                        :title="expandedTrackId === getTrackKey(track) ? 'Close preview' : 'Preview track'"
                        @click="toggleSpotifyPlayer(track)"
                      >
                        <!-- Loading spinner when processing -->
                        <svg v-if="processingTrack === getTrackKey(track) && isPreviewProcessing" class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                          <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                        </svg>
                        <!-- Regular icon when not processing -->
                        <img v-if="expandedTrackId !== getTrackKey(track) && !(processingTrack === getTrackKey(track) && isPreviewProcessing)" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                        <Icon v-else-if="expandedTrackId === getTrackKey(track) && !(processingTrack === getTrackKey(track) && isPreviewProcessing)" :icon="faTimes" class="w-3 h-3" />
                        <span :class="processingTrack === getTrackKey(track) && isPreviewProcessing ? '' : 'ml-1'">{{ processingTrack === getTrackKey(track) && isPreviewProcessing ? 'Loading...' : (expandedTrackId === getTrackKey(track) ? 'Close' : (listenedTracks.has(getTrackKey(track)) ? 'Listened' : 'Preview')) }}</span>
                      </button>
                    </td>
                  </tr>

                  <!-- Spotify Player Dropdown Row -->
                  <tr v-if="expandedTrackId === getTrackKey(track)" :key="`spotify-${getTrackKey(track)}-${index}`" class="border-b border-white/5 player-row">
                    <td colspan="8" class="p-0 overflow-hidden">
                      <div class="p-4 bg-white/5 relative pb-8">
                        <div class="max-w-6xl mx-auto">
                          <div v-if="track.spotify_id && track.spotify_id !== 'NO_TRACK_FOUND'">
                            <iframe
                              :key="track.is_single_track ? track.spotify_id : track.album_id"
                              :src="track.is_single_track
                                ? `https://open.spotify.com/embed/track/${track.spotify_id}?utm_source=generator&theme=0`
                                : `https://open.spotify.com/embed/album/${track.album_id}?utm_source=generator&theme=0`"
                              :title="track.is_single_track
                                ? `${track.artist_name} - ${track.track_name}`
                                : `${track.artist_name} - ${track.release_name || track.album_name} (${track.track_count} tracks)`"
                              class="w-full spotify-embed"
                              :style="track.is_single_track
                                ? 'height: 152px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);'
                                : 'height: 152px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);'"
                              frameBorder="0"
                              scrolling="no"
                              allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                              loading="lazy"
                              @load="(event) => { event.target.style.opacity = '1' }"
                            />
                          </div>
                          <div v-else class="flex items-center justify-center bg-white/5" style="height: 80px; border-radius: 15px;">
                            <div class="text-center text-white/60">
                              <div class="text-sm font-medium">No Spotify preview available</div>
                            </div>
                          </div>
                        </div>

                        <!-- Spotify Login Link -->
                        <div class="absolute bottom-2 right-4">
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
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Load More -->
        <div v-if="hasMoreTracks" class="flex items-center justify-center mt-8">
          <button
            class="px-4 py-2 bg-k-accent text-white rounded hover:bg-k-accent/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            @click="loadMore"
          >
            Load More<span v-if="remainingTracksCount > 0"> ({{ remainingTracksCount }} left)</span>
          </button>
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="hasSearched && !isLoading" class="mt-8 text-center text-gray-400">
        <p>No releases found for "{{ lastSearchQuery }}"</p>
        <p class="text-sm mt-2">Try different search criteria</p>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faBan, faCheck, faChevronDown, faHeart, faPause, faPlay, faSearch, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { http } from '@/services/http'
import { logger } from '@/utils/logger'
import { useRouter } from '@/composables/useRouter'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

// Initialize router
const { onRouteChanged } = useRouter()

// Reactive state
const searchQuery = ref('')
const popularityFilter = ref(false)
const freshDropsFilter = ref(false)
const releaseYearFilter = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const searchSuggestionTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const tracks = ref([])
const hasSearched = ref(false)
const lastSearchQuery = ref('')
const expandedTrackId = ref<string | null>(null)
const processingTrack = ref<string | null>(null)
const isPreviewProcessing = ref(false)
const visibleCount = ref(20)
const INITIAL_VISIBLE_COUNT = 20
const LOAD_MORE_STEP = 20

// Listened tracks tracking (UI only)
const listenedTracks = ref(new Set<string>())
const blacklistedTracks = ref(new Set<string>())

// Audio for previews
let currentAudio: HTMLAudioElement | null = null

const filteredTracks = computed(() => tracks.value)
const displayTracks = computed(() => tracks.value.slice(0, visibleCount.value))
const hasMoreTracks = computed(() => visibleCount.value < tracks.value.length)
const remainingTracksCount = computed(() => Math.max(tracks.value.length - visibleCount.value, 0))

// Handler functions for mutually exclusive filters
const handleFreshDropsToggle = () => {
  freshDropsFilter.value = !freshDropsFilter.value

  // If Fresh Drops is activated, clear Release Year
  if (freshDropsFilter.value && releaseYearFilter.value) {
    releaseYearFilter.value = ''
  }
}

const handleReleaseYearChange = () => {
  // If Release Year is entered, deactivate Fresh Drops
  if (releaseYearFilter.value && freshDropsFilter.value) {
    freshDropsFilter.value = false
  }
}

// Helper function to get unique track key
const getTrackKey = (track: any): string => {
  return track.spotify_id || `${track.artist_name}-${track.track_name}`.replace(/\s+/g, '-')
}

// Format date helper function
// Rules:
// - Within current day/week/month => "X day(s)/week(s)/month(s) ago" (singular for 1)
// - If release year is not the current year => show just the year (e.g., 2018)
// - If within the current year but older than a month => "X months ago"
const formatDate = (releaseDate?: string): string => {
  if (!releaseDate) {
    return 'Unknown'
  }

  try {
    const dateStr = releaseDate.trim()

    // Parse the date - handle YYYY-MM-DD, YYYY-MM, or YYYY formats
    let date: Date
    if (dateStr.includes('-')) {
      // Full date or date with month
      date = new Date(dateStr)
    } else if (dateStr.length === 4) {
      // Just year - use Jan 1st of that year
      date = new Date(Number.parseInt(dateStr, 10), 0, 1)
    } else {
      return 'Unknown'
    }

    if (isNaN(date.getTime())) {
      // Fallback: just show year if present
      const yearMatch = dateStr.match(/\d{4}/)
      return yearMatch ? yearMatch[0] : 'Unknown'
    }

    const now = new Date()
    const diffTime = Math.abs(now.getTime() - date.getTime())
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))
    const diffWeeks = Math.floor(diffDays / 7)
    const diffMonths = Math.floor(diffDays / 30)
    const currentYear = now.getFullYear()
    const releaseYear = date.getFullYear()

    // Same-day handling
    if (diffDays === 0) {
      return 'Today'
    }

    // Days within the last week
    if (diffDays < 7) {
      return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
    }

    // Weeks within the last month
    if (diffWeeks < 4) {
      return `${diffWeeks} week${diffWeeks === 1 ? '' : 's'} ago`
    }

    // Months within the current year
    if (releaseYear === currentYear && diffMonths < 12) {
      return `${diffMonths} month${diffMonths === 1 ? '' : 's'} ago`
    }

    // Older than current year: show year only
    return releaseYear.toString()
  } catch (error) {
    const yearMatch = releaseDate.match(/\d{4}/)
    return yearMatch ? yearMatch[0] : 'Unknown'
  }
}

// Format followers helper function
const formatFollowers = (followers: number): string => {
  if (followers >= 1000000) {
    return `${(followers / 1000000).toFixed(1)}M`
  } else if (followers >= 1000) {
    return `${(followers / 1000).toFixed(1)}K`
  }
  return followers.toString()
}

const loadMore = () => {
  visibleCount.value = Math.min(tracks.value.length, visibleCount.value + LOAD_MORE_STEP)
}

const performSearch = async () => {
  if (!searchQuery.value.trim()) {
    return
  }

  // Close any open preview dropdown before new search (prevents animation glitch)
  expandedTrackId.value = null

  isLoading.value = true
  errorMessage.value = ''
  lastSearchQuery.value = searchQuery.value

  try {
    // Build parameters using the working backend format
    const params: Record<string, any> = {
      label: searchQuery.value.trim(),
    }

    // Add filter parameters (backend will convert to Spotify format)
    if (freshDropsFilter.value) {
      params.new = true // Send as boolean, not string
    }

    if (popularityFilter.value) {
      params.hipster = true // Send as boolean, not string
    }

    if (releaseYearFilter.value) {
      params.release_year = releaseYearFilter.value
    }

    const queryString = new URLSearchParams(params).toString()

    console.log('Making request to:', `label-search?${queryString}`)
    console.log('Parameters:', params)

    const response = await http.get(`label-search?${queryString}`)

    // Debug logging
    console.log('Raw response object:', response)
    console.log('Response data:', response?.data)
    console.log('Response tracks:', response?.data?.tracks)

    // Wait a moment and try again if response is empty
    if (!response?.data?.tracks && response?.data !== null) {
      console.log('Response seems incomplete, waiting 100ms...')
      await new Promise(resolve => setTimeout(resolve, 100))
      console.log('Response after wait:', response?.data?.tracks)
    }

    const responseTracks = response?.tracks || response?.data?.tracks || []

    console.log('Searching for label:', searchQuery.value.trim())
    console.log('Total tracks received:', responseTracks.length)

    // Debug: Log the first track to see its structure
    if (responseTracks.length > 0) {
      console.log('First track structure:', responseTracks[0])
      console.log('Track keys:', Object.keys(responseTracks[0]))
    }

    // Filter out backend-banned tracks only
    const filteredTracks = responseTracks.filter(track =>
      !track.is_banned && !track.is_artist_banned,
    )
    console.log('After filtering banned tracks (backend):', filteredTracks.length)

    const allTracks = filteredTracks
      .map(track => ({
        ...track,
        isPlaying: false,
        isSaved: track.is_saved || false,
        isBanned: track.is_banned || false,
      }))
      .sort((a, b) => {
        const dateA = new Date(a.release_date || 0).getTime()
        const dateB = new Date(b.release_date || 0).getTime()
        return dateB - dateA
      })

    console.log('Total tracks after filtering and sorting:', allTracks.length)

    tracks.value = allTracks
    visibleCount.value = Math.min(INITIAL_VISIBLE_COUNT, allTracks.length)

    hasSearched.value = true
  } catch (error) {
    logger.error('Label search failed:', error)
    console.error('Full error object:', error)
    console.error('Error response:', error.response)
    console.error('Error request:', error.request)

    // Both new=true and hipster=true should now be supported

    // More specific error messages
    if (error.response?.status === 503) {
      errorMessage.value = 'Spotify integration is not available. Please contact support.'
    } else if (error.response?.status === 500) {
      errorMessage.value = 'Server error occurred. Please try again in a moment.'
    } else if (error.response?.status === 422) {
      errorMessage.value = 'Invalid search parameters. Please check your input.'
    } else {
      errorMessage.value = `Search failed: ${error.message || 'Unknown error'}`
    }
  } finally {
    isLoading.value = false
  }
}

// Mark track as listened
const markTrackAsListened = async (track: any) => {
  const trackKey = getTrackKey(track)
  listenedTracks.value.add(trackKey)
  listenedTracks.value = new Set(listenedTracks.value)

  // Persist listened state
  try {
    await http.post('music-preferences/listened-track', {
      track_key: trackKey,
      track_name: track.track_name,
      artist_name: track.artist_name,
      spotify_id: track.spotify_id,
      isrc: track.isrc,
    })
  } catch (e) {
    try {
      const keys = Array.from(listenedTracks.value)
      localStorage.setItem('koel-label-listened-tracks', JSON.stringify(keys))
    } catch {}
  }

  // Auto-ban removed
}

// No-op (auto-ban removed)
const flushPendingAutoBans = () => {}

const togglePreview = track => {
  // Stop any currently playing preview
  if (currentAudio) {
    currentAudio.pause()
    currentAudio = null
    // Reset all playing states
    tracks.value.forEach(t => t.isPlaying = false)
  }

  if (!track.isPlaying) {
    // Start playing this track
    currentAudio = new Audio(track.preview_url)
    currentAudio.volume = 0.5
    currentAudio.play()
    track.isPlaying = true

    // Stop when audio ends
    currentAudio.addEventListener('ended', () => {
      track.isPlaying = false
      currentAudio = null
    })
  }
}

const saveTrack = async track => {
  // Close any open preview dropdown before saving (prevents animation glitch)
  expandedTrackId.value = null

  try {
    if (track.isSaved) {
      // Remove from saved tracks - update UI immediately for better UX
      track.isSaved = false

      // Since there's no DELETE endpoint for saved tracks (they auto-expire in 24h),
      // we'll handle this client-side by marking it as unsaved
      // This provides the expected toggle UX while tracks naturally expire

      // ALSO remove from blacklist when unsaving from discovery sections
      try {
        const deleteData = {
          isrc: track.isrc,
          track_name: track.track_name,
          artist_name: track.artist_name,
        }
        const params = new URLSearchParams(deleteData)
        const response = await http.delete(`music-preferences/blacklist-track?${params}`)

        if (response.success) {
          // Update local state if we're tracking it
          if (track.isBanned !== undefined) {
            track.isBanned = false
          }

          console.log('✅ Track removed from blacklist on unsave:', track.track_name)

          // Trigger BannedTracksScreen refresh
          window.dispatchEvent(new CustomEvent('track-unblacklisted', {
            detail: { track, trackKey: getTrackKey(track) },
          }))
          localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
        } else {
          console.warn('Failed to remove track from blacklist (API returned error):', response.error)
        }
      } catch (error) {
        console.warn('Failed to remove track from blacklist on unsave:', error)
      }

      // Note: We could implement a DELETE endpoint in the future if needed,
      // but for now this client-side approach works well since tracks expire anyway
    } else {
      // Save track - Update UI immediately for instant feedback
      track.isSaved = true

      // Do backend work in background without blocking UI
      try {
        console.log('🎵 [LABEL SEARCH] Starting to save track:', track.track_name, 'by', track.artist_name)
        console.log('🎵 [LABEL SEARCH] Track object:', track)

        // Extract metadata from the track object
        let label = track.label || ''
        let popularity = track.popularity || 0
        let followers = track.followers || 0
        let releaseDate = track.release_date || ''
        let previewUrl = track.preview_url || null

        console.log('🎵 [LABEL SEARCH] Initial metadata - label:', label, 'popularity:', popularity, 'followers:', followers, 'releaseDate:', releaseDate)

        // Check if we need to fetch additional metadata
        // Always try to fetch followers data since label search doesn't provide it
        const needsEnhancedMetadata = !followers || followers === 0

        console.log('🎵 [LABEL SEARCH] needsEnhancedMetadata:', needsEnhancedMetadata)
        console.log('🎵 [LABEL SEARCH] Current data - label:', label, 'releaseDate:', releaseDate, 'followers:', followers)

        if (needsEnhancedMetadata) {
          try {
            console.log('🎵 [LABEL SEARCH] Fetching enhanced metadata from API...')
            const response = await http.get('music-discovery/track-preview', {
              params: {
                artist_name: track.artist_name || 'Unknown',
                track_title: track.track_name,
                source: 'spotify',
                track_id: track.spotify_id,
              },
            })

            console.log('🎵 [LABEL SEARCH] Enhanced data response:', response)
            console.log('🎵 [LABEL SEARCH] Response success:', response.success)
            console.log('🎵 [LABEL SEARCH] Response data:', response.data)

            if (response.success && response.data && response.data.metadata) {
              const metadata = response.data.metadata
              console.log('🎵 [LABEL SEARCH] Metadata received:', metadata)

              label = metadata.label || label
              popularity = metadata.popularity || popularity
              followers = metadata.followers || followers
              releaseDate = metadata.release_date || releaseDate
              previewUrl = metadata.preview_url || previewUrl

              console.log('🎵 [LABEL SEARCH] Updated metadata - label:', label, 'popularity:', popularity, 'followers:', followers, 'releaseDate:', releaseDate)
            } else {
              console.log('🎵 [LABEL SEARCH] No metadata in response or API call failed')
            }
          } catch (error) {
            console.warn('🎵 [LABEL SEARCH] Failed to fetch enhanced metadata, using basic data:', error)
          }
        }

        const savePayload = {
          spotify_id: track.spotify_id,
          isrc: track.isrc,
          track_name: track.track_name,
          artist_name: track.artist_name,
          label,
          popularity,
          followers,
          release_date: releaseDate,
          preview_url: previewUrl,
          track_count: track.track_count || 1,
          is_single_track: track.is_single_track !== false,
          album_id: track.album_id || null,
        }

        console.log('🎵 [LABEL SEARCH] Sending save request with payload:', savePayload)

        const response = await http.post('music-preferences/save-track', savePayload)

        console.log('🎵 [LABEL SEARCH] Save response:', response)

        if (response.success) {
          // Update localStorage timestamp to trigger cross-tab refresh
          localStorage.setItem('track-saved-timestamp', Date.now().toString())

          // Blacklist the track in backend (UI already updated)
          console.log('🎵 [LABEL SEARCH] Track saved successfully, blacklisting in backend...')

          try {
            const blacklistResponse = await http.post('music-preferences/blacklist-track', {
              spotify_id: track.spotify_id,
              isrc: track.isrc,
              track_name: track.track_name,
              artist_name: track.artist_name,
            })

            if (blacklistResponse.success) {
              track.isBanned = true
              blacklistedTracks.value.add(trackKey)
              console.log('✅ [LABEL SEARCH] Track blacklisted in backend:', track.track_name)

              // Trigger BannedTracksScreen refresh
              window.dispatchEvent(new CustomEvent('track-blacklisted', {
                detail: { track, trackKey },
              }))
              localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
            } else {
              console.warn('⚠️ [LABEL SEARCH] Failed to blacklist in backend:', blacklistResponse.error)
            }
          } catch (error) {
            console.error('❌ [LABEL SEARCH] Error blacklisting track:', error)
          }
        } else {
          // Revert UI change on failure
          track.isSaved = false
          throw new Error(response.error || 'Failed to save track')
        }
      } catch (error) {
        // Revert UI change on failure
        track.isSaved = false
        throw error
      }
    }
  } catch (error) {
    logger.error('Failed to toggle save track:', error)
    // You could show a toast notification here if available
  }
}

const banTrack = async track => {
  const trackKey = getTrackKey(track)

  // Close any open preview dropdown
  expandedTrackId.value = null

  try {
    if (track.isBanned) {
      // UNBAN TRACK
      track.isBanned = false
      blacklistedTracks.value.delete(trackKey)

      // Pending auto-ban logic removed

      // Backend API call to remove
      const deleteData = {
        isrc: track.isrc,
        track_name: track.track_name,
        artist_name: track.artist_name,
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (!response.success) {
        // Revert UI change if backend failed
        track.isBanned = true
        blacklistedTracks.value.add(trackKey)
        logger.error('Failed to remove track from blacklist:', response.error)
      }
    } else {
      // BAN TRACK - Manual ban removes immediately
      const response = await http.post('music-preferences/blacklist-track', {
        spotify_id: track.spotify_id,
        isrc: track.isrc,
        track_name: track.track_name,
        artist_name: track.artist_name,
      })

      if (response.success) {
        track.isBanned = true
        blacklistedTracks.value.add(trackKey)

      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    }
  } catch (error) {
    logger.error('Failed to toggle ban track:', error)
  }
}

// Helper function to check if Spotify ID is valid (not a Koel UUID)
const isValidSpotifyId = (id: string | null): boolean => {
  if (!id) {
    return false
  }
  // Spotify IDs are base62 encoded and typically 22 characters long
  // Koel UUIDs are 36 characters with dashes
  return !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id) && id.length > 10
}

// Spotify player functionality
const toggleSpotifyPlayer = async track => {
  const trackKey = getTrackKey(track)

  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

  // Mark as listened when opening preview
  markTrackAsListened(track)

  // If track doesn't have a valid Spotify ID, try to find one
  if (!track.spotify_id || !isValidSpotifyId(track.spotify_id)) {
    processingTrack.value = trackKey
    isPreviewProcessing.value = true

    try {
      // Try to find Spotify equivalent for this track from label search
      const response = await http.get('music-discovery/track-preview', {
        params: {
          artist_name: track.artist_name,
          track_title: track.track_name,
          source: 'label-search',
        },
      })

      if (response.success && response.data && response.data.spotify_track_id) {
        // Update track with Spotify ID
        track.spotify_id = response.data.spotify_track_id
        // Now expand the player
        expandedTrackId.value = trackKey
      } else {
        // Show notification that preview is not available
        showTrackNotFoundNotification(track)
      }
    } catch (error: any) {
      showPreviewErrorNotification(track, error.response?.data?.error || error.message || 'Network error')
    } finally {
      processingTrack.value = null
      isPreviewProcessing.value = false
    }
  } else {
    // Track has valid Spotify ID, show player immediately
    expandedTrackId.value = trackKey
  }
}

// Open Spotify pages
const openSpotifyArtistPage = track => {
  if (track.spotify_artist_url) {
    window.open(track.spotify_artist_url, '_blank')
  }
}

const openSpotifyTrackPage = track => {
  if (track.spotify_track_url) {
    window.open(track.spotify_track_url, '_blank')
  }
}

// Open appropriate Spotify page based on release type
const openSpotifyReleasePage = track => {
  if (track.is_single_track) {
    // For single tracks, open the track page
    if (track.spotify_track_url) {
      window.open(track.spotify_track_url, '_blank')
    }
  } else {
    // For multi-track albums, open the album page
    if (track.spotify_album_url) {
      window.open(track.spotify_album_url, '_blank')
    }
  }
}

// Enhanced notification functions for better UX
const showTrackNotFoundNotification = track => {
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

const showPreviewErrorNotification = (track, errorMessage: string) => {
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

// Removed ban artist logic for Label Search screen

// Clear search state function
const clearSearchState = () => {
  tracks.value = []
  visibleCount.value = INITIAL_VISIBLE_COUNT
  hasSearched.value = false
  lastSearchQuery.value = ''
  errorMessage.value = ''
  expandedTrackId.value = null
  console.log('🏷️ [LABEL SEARCH] Search state cleared')
}

// Check for label search query from other screens
const checkForStoredLabelQuery = () => {
  try {
    const storedData = localStorage.getItem('koel-label-search-query')
    if (storedData) {
      const labelSearchData = JSON.parse(storedData)

      // Only use if it's recent (within last 5 seconds)
      if (Date.now() - labelSearchData.timestamp < 5000) {
        console.log('🏷️ [LABEL SEARCH] Found stored label query:', labelSearchData.query)

        // Clear previous search results first
        clearSearchState()

        searchQuery.value = labelSearchData.query

        // Reset all filters when populating from external source
        freshDropsFilter.value = false
        popularityFilter.value = false
        releaseYearFilter.value = ''

        // Only populate the search bar, don't auto-perform search
        // This allows users to select options before searching
        console.log('🏷️ [LABEL SEARCH] Search query populated, previous results cleared, and filters reset')

        // Clear the stored data after using it
        localStorage.removeItem('koel-label-search-query')
      } else {
        // Clear stale data
        localStorage.removeItem('koel-label-search-query')
      }
    }
  } catch (error) {
    console.error('Failed to load label search query from localStorage:', error)
  }
}

// Removed auto-ban listened tracks watcher

// Watch search query - clear results when user modifies it
watch(searchQuery, (newValue, oldValue) => {
  // Only clear if there are existing results and the query actually changed
  if (hasSearched.value && oldValue !== '' && newValue !== oldValue) {
    console.log('🏷️ [LABEL SEARCH] Search query changed - clearing previous results')
    clearSearchState()
  }
})

// Watch Fresh Drops filter - clear results when toggled
watch(freshDropsFilter, (newValue, oldValue) => {
  // Only clear if there are existing results
  if (hasSearched.value && newValue !== oldValue) {
    console.log('🏷️ [LABEL SEARCH] Fresh Drops filter changed - clearing previous results')
    clearSearchState()
  }
})

// Watch Release Year filter - clear results when changed
watch(releaseYearFilter, (newValue, oldValue) => {
  // Only clear if there are existing results and the value actually changed
  if (hasSearched.value && newValue !== oldValue) {
    console.log('🏷️ [LABEL SEARCH] Release Year filter changed - clearing previous results')
    clearSearchState()
  }
})

// Watch Hidden Gems (popularity) filter - clear results when toggled
watch(popularityFilter, (newValue, oldValue) => {
  // Only clear if there are existing results
  if (hasSearched.value && newValue !== oldValue) {
    console.log('🏷️ [LABEL SEARCH] Hidden Gems filter changed - clearing previous results')
    clearSearchState()
  }
})

// Auto-search suggestions after 2s pause while typing
watch(searchQuery, newValue => {
  if (isLoading.value) {
    return
  }
  if (searchSuggestionTimer.value) {
    clearTimeout(searchSuggestionTimer.value)
  }
  if (newValue.trim()) {
    searchSuggestionTimer.value = setTimeout(() => {
      performSearch()
    }, 2000)
  }
})

// Check on mount
onMounted(async () => {
  checkForStoredLabelQuery()

  // Load listened tracks from server (fall back to localStorage if unauthenticated)
  try {
    const resp: any = await http.get('music-preferences/listened-tracks')
    if (resp?.success && Array.isArray(resp.data)) {
      listenedTracks.value = new Set(resp.data as string[])
      console.log('🎵 [LABEL SEARCH] Loaded listened tracks from server:', listenedTracks.value.size)
    }
  } catch (e) {
    // Fallback to localStorage per device
    try {
      const stored = localStorage.getItem('koel-label-listened-tracks')
      if (stored) {
        const keys: string[] = JSON.parse(stored)
        listenedTracks.value = new Set(keys)
        console.log('🎵 [LABEL SEARCH] Loaded listened tracks from localStorage:', listenedTracks.value.size)
      }
    } catch {}
  }
})

onUnmounted(() => {
  if (searchSuggestionTimer.value) {
    clearTimeout(searchSuggestionTimer.value)
    searchSuggestionTimer.value = null
  }
})

// Reset filters to default state
const resetFilters = () => {
  freshDropsFilter.value = false
  popularityFilter.value = false
  releaseYearFilter.value = ''
  console.log('🏷️ [LABEL SEARCH] Filters reset to default state')
}

// Also check when navigating to this screen
onRouteChanged(route => {
  console.log('🏷️ [LABEL SEARCH] onRouteChanged called - route.screen:', route.screen)

  // Close dropdown immediately when navigating to ANY screen (including when leaving or returning)
  if (expandedTrackId.value) {
    expandedTrackId.value = null
    console.log('🏷️ [LABEL SEARCH] Closed preview dropdown on navigation')
  }

  if (route.screen === 'LabelSearch') {
    console.log('🏷️ [LABEL SEARCH] Navigated to LabelSearch screen')

    // Reset filters first to ensure clean state
    resetFilters()

    // Check for stored query AFTER resetting filters
    checkForStoredLabelQuery()
  }

  console.log('🏷️ [LABEL SEARCH] onRouteChanged handler finished')
})
</script>

<style lang="postcss" scoped>
.wrapper {
  @apply p-6 max-w-7xl mx-auto;
}

/* Hide placeholders on focus */
input:focus::placeholder {
  opacity: 0;
}

/* Center placeholder text in search input */
.search-input::placeholder {
  text-align: center;
}

.spotify-dropdown-enter-active,
.spotify-dropdown-leave-active {
  transition: all 0.3s ease;
}

.spotify-dropdown-enter-from,
.spotify-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

.spotify-embed {
  opacity: 0;
  transition: opacity 0.5s ease;
}

.player-row {
  background-color: rgba(255, 255, 255, 0.02);
}

.scrollbar-hide {
  /* Hide scrollbar for Chrome, Safari and Opera */
  &::-webkit-scrollbar {
    display: none;
  }
  /* Hide scrollbar for IE, Edge and Firefox */
  -ms-overflow-style: none;
  scrollbar-width: none;
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

/* Dropdown transition */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

</style>
