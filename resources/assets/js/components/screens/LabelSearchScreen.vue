<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="simple" class="text-center">
        Label Search
        <template #subtitle>
          Find music from specific record labels
        </template>
      </ScreenHeader>
    </template>

    <div class="wrapper">
      <div class="search-form max-w-2xl mx-auto space-y-6">
        <!-- Search Input -->
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Enter label name (e.g., Warp Records)"
            class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 focus:border-k-accent focus:outline-none"
            @keyup.enter="performSearch"
          />
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- Popularity Toggle -->
          <div class="flex items-center space-x-3">
            <button
              @click="popularityFilter = !popularityFilter"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                popularityFilter ? 'bg-k-accent' : 'bg-gray-600'
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  popularityFilter ? 'translate-x-5' : 'translate-x-0'
                ]"
              />
            </button>
            <span class="text-white/80">Popularity &lt;10%</span>
          </div>

          <!-- Release Date Filter -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Release Date</label>
            <select
              v-model="releaseDateFilter"
              class="w-full p-2 bg-white/10 rounded focus:border-k-accent text-white"
            >
              <option value="" class="bg-gray-800">All Time</option>
              <option value="1w" class="bg-gray-800">Last Week</option>
              <option value="1m" class="bg-gray-800">Last Month</option>
              <option value="3m" class="bg-gray-800">Last 3 Months</option>
              <option value="6m" class="bg-gray-800">Last 6 Months</option>
              <option value="1y" class="bg-gray-800">Last Year</option>
              <option value="2y" class="bg-gray-800">Last 2 Years</option>
              <option value="5y" class="bg-gray-800">Last 5 Years</option>
            </select>
          </div>
        </div>

        <!-- Search Button -->
        <div class="flex justify-center">
          <button
            @click="performSearch"
            :disabled="!searchQuery.trim() || isLoading"
            class="px-6 py-2 bg-k-accent text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-k-accent/90 transition-colors"
          >
            <Icon v-if="isLoading" :icon="faSpinner" spin class="mr-2" />
            {{ isLoading ? 'Searching...' : 'Search' }}
          </button>
        </div>
      </div>

      <!-- Error Message -->
      <div v-if="errorMessage" class="mt-6 p-4 bg-red-500/20 border border-red-500/30 rounded-lg text-red-200 text-center">
        {{ errorMessage }}
      </div>

      <!-- Results Table -->
      <div v-if="tracks.length > 0" class="mt-8"> 
        <h3 class="text-xl font-medium mb-4">
          Found {{ tracks.length }} tracks from "{{ lastSearchQuery }}"
        </h3>

        <div class="bg-white/5 rounded-lg overflow-hidden">
          <div class="overflow-x-auto scrollbar-hide">
            <table class="w-full">
              <thead>
                <tr class="border-b border-white/10">
                  <th class="text-left py-4 px-3 font-medium">#</th>
                  <th class="text-left px-3 font-medium w-auto min-w-48">Artist</th>
                  <th class="text-left px-3 font-medium">Release Name</th>
                  <th class="text-center px-3 font-medium">Popularity</th>
                  <th class="text-center px-3 font-medium whitespace-nowrap">Release Date</th>
                  <th class="text-center px-3 font-medium">Actions</th>
                  <th class="text-center px-3 font-medium">Preview</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(track, index) in tracks" :key="track.spotify_id">
                  <tr
                    class="transition h-16 border-b border-white/5 hover:bg-white/5"
                    :class="expandedTrackId === getTrackKey(track) ? 'bg-white/5' : ''"
                  >
                    <!-- Index -->
                    <td class="p-3 align-middle">
                      <span class="text-white/60">{{ index + 1 }}</span>
                    </td>

                    <!-- Artist -->
                    <td class="p-3 align-middle">
                      <button
                        class="font-medium text-white hover:text-k-accent transition-colors cursor-pointer text-left leading-none"
                        :title="`View ${track.artist_name} on Spotify`"
                        @click="openSpotifyArtistPage(track)"
                      >
                        {{ track.artist_name }}
                      </button>
                    </td>

                    <!-- Release Name -->
                    <td class="p-3 align-middle">
                      <button
                        class="text-white/80 hover:text-k-accent transition-colors cursor-pointer text-left"
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

                    <!-- Release Date -->
                    <td class="p-3 align-middle text-center">
                      <span class="text-white/80 text-sm">{{ formatDate(track.release_date) }}</span>
                    </td>

                    <!-- Actions -->
                    <td class="p-3 align-middle">
                      <div class="flex gap-2 justify-center">
                        <!-- Save Button (24h) -->
                        <button
                          @click="saveTrack(track)"
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="track.isSaved
                            ? 'bg-green-600 hover:bg-green-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'"
                          class="w-10 h-10 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center min-h-[34px]"
                          :title="track.isSaved ? 'Click to unsave track' : 'Save track (24h)'"
                        >
                          <Icon :icon="faHeart" class="text-xs" />
                        </button>

                        <!-- Blacklist Button -->
                        <button
                          @click="banTrack(track)"
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="track.isBanned
                            ? 'bg-orange-600 hover:bg-orange-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'"
                          class="w-10 h-10 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center min-h-[34px]"
                          :title="track.isBanned ? 'Click to unblock track' : 'Block track'"
                        >
                          <Icon :icon="faBan" class="text-xs" />
                        </button>
                      </div>
                    </td>

                    <!-- Preview Button -->
                    <td class="p-3 align-middle text-center">
                      <button
                        :disabled="processingTrack === getTrackKey(track)"
                        class="px-3 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[15px] justify-center mx-auto"
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
                        <span :class="processingTrack === getTrackKey(track) && isPreviewProcessing ? '' : 'ml-1'">{{ processingTrack === getTrackKey(track) && isPreviewProcessing ? 'Loading...' : (expandedTrackId === getTrackKey(track) ? 'Close' : 'Preview') }}</span>
                      </button>
                    </td>
                  </tr>

                  <!-- Spotify Player Dropdown Row -->
                  <Transition name="spotify-dropdown" mode="out-in">
                    <tr v-if="expandedTrackId === getTrackKey(track)" :key="`spotify-${getTrackKey(track)}-${index}`" class="border-b border-white/5 player-row">
                      <td colspan="7" class="p-0 overflow-hidden">
                        <div class="p-4 bg-white/5 relative">
                          <div class="max-w-4xl mx-auto">
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
                        </div>
                      </td>
                    </tr>
                  </Transition>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="hasSearched && !isLoading" class="mt-8 text-center text-gray-400">
        <p>No tracks found for "{{ lastSearchQuery }}"</p>
        <p class="text-sm mt-2">Try adjusting your search criteria or removing filters.</p>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faSpinner, faPlay, faPause, faHeart, faBan, faCheck, faTimes } from '@fortawesome/free-solid-svg-icons'
import { ref, reactive } from 'vue'
import { http } from '@/services/http'
import { logger } from '@/utils/logger'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

// Reactive state
const searchQuery = ref('')
const popularityFilter = ref(false)
const releaseDateFilter = ref('')
const isLoading = ref(false)
const errorMessage = ref('')
const tracks = ref([])
const hasSearched = ref(false)
const lastSearchQuery = ref('')
const expandedTrackId = ref<string | null>(null)
const processingTrack = ref<string | null>(null)
const isPreviewProcessing = ref(false)

// Audio for previews
let currentAudio: HTMLAudioElement | null = null

// Helper function to get unique track key
const getTrackKey = (track: any): string => {
  return track.spotify_id || `${track.artist_name}-${track.track_name}`.replace(/\s+/g, '-')
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
      const years = Math.floor(diffDays / 365)
      return `${years} year${years === 1 ? '' : 's'} ago`
    }
  } catch (error) {
    return dateString.split('T')[0] // Return just the date part if parsing fails
  }
}

const performSearch = async () => {
  if (!searchQuery.value.trim()) return

  isLoading.value = true
  errorMessage.value = ''
  lastSearchQuery.value = searchQuery.value

  try {
    const params = new URLSearchParams({
      label: searchQuery.value.trim(),
      ...(popularityFilter.value && { hipster: '1' }),
      ...(releaseDateFilter.value && { release_date: releaseDateFilter.value })
    })

    console.log('Making request to:', `label-search?${params}`)

    const response = await http.get(`label-search?${params}`)

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

    // Handle response safely - tracks are directly on response object in Koel
    const responseTracks = response?.tracks || response?.data?.tracks || []

    // Filter out blacklisted tracks from the new search results
    const filteredTracks = responseTracks.filter(track => !track.is_banned)

    tracks.value = filteredTracks.map(track => ({
      ...track,
      isPlaying: false,
      isSaved: track.is_saved || false,
      isBanned: track.is_banned || false
    }))

    hasSearched.value = true
  } catch (error) {
    logger.error('Label search failed:', error)
    console.error('Full error object:', error)
    console.error('Error response:', error.response)
    console.error('Error request:', error.request)

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

const togglePreview = (track) => {
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

const saveTrack = async (track) => {
  try {
    if (track.isSaved) {
      // Remove from saved tracks - update UI immediately for better UX
      track.isSaved = false

      // Since there's no DELETE endpoint for saved tracks (they auto-expire in 24h),
      // we'll handle this client-side by marking it as unsaved
      // This provides the expected toggle UX while tracks naturally expire

      // Note: We could implement a DELETE endpoint in the future if needed,
      // but for now this client-side approach works well since tracks expire anyway

    } else {
      // Save track
      const response = await http.post('music-preferences/save-track', {
        spotify_id: track.spotify_id,
        isrc: track.isrc,
        track_name: track.track_name,
        artist_name: track.artist_name,
        label: track.label,
        popularity: track.popularity,
        release_date: track.release_date,
        preview_url: track.preview_url
      })

      if (response.success) {
        track.isSaved = true
      } else {
        throw new Error(response.error || 'Failed to save track')
      }
    }
  } catch (error) {
    logger.error('Failed to toggle save track:', error)
    // You could show a toast notification here if available
  }
}

const banTrack = async (track) => {
  try {
    if (track.isBanned) {
      // Remove from blacklist - update UI immediately for better UX
      track.isBanned = false

      // Backend API call to remove
      const deleteData = {
        isrc: track.isrc,
        track_name: track.track_name,
        artist_name: track.artist_name
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (!response.success) {
        // Revert UI change if backend failed
        track.isBanned = true
        logger.error('Failed to remove track from blacklist:', response.error)
      }
    } else {
      // Add to blacklist
      const response = await http.post('music-preferences/blacklist-track', {
        spotify_id: track.spotify_id,
        isrc: track.isrc,
        track_name: track.track_name,
        artist_name: track.artist_name
      })

      if (response.success) {
        track.isBanned = true
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    }
  } catch (error) {
    logger.error('Failed to toggle ban track:', error)
    // You could show a toast notification here if available
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
const toggleSpotifyPlayer = async (track) => {
  const trackKey = getTrackKey(track)

  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

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
const openSpotifyArtistPage = (track) => {
  if (track.spotify_artist_url) {
    window.open(track.spotify_artist_url, '_blank')
  }
}

const openSpotifyTrackPage = (track) => {
  if (track.spotify_track_url) {
    window.open(track.spotify_track_url, '_blank')
  }
}

// Open appropriate Spotify page based on release type
const openSpotifyReleasePage = (track) => {
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
const showTrackNotFoundNotification = (track) => {
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
</script>

<style lang="postcss" scoped>
.wrapper {
  @apply p-6 max-w-7xl mx-auto;
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
</style>