<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="simple" class="text-center" header-image="/VantaNova-Logo.svg">
        <template #subtitle>
          Search tracks by genre and year
        </template>
      </ScreenHeader>
    </template>

    <div class="p-6 space-y-6">
      <!-- Search Controls - Matching Image Layout (constrained width) -->
      <div class="max-w-4xl mx-auto w-full">
        <div class="space-y-4">
          <!-- Top Row: Genre (left) and Year (right) -->
          <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-10">
            <!-- Genre Field -->
            <div>
              <label class="block text-sm font-medium mb-2 text-white/80">Genre</label>
              <input
                v-model="searchQuery"
                type="text"
                class="w-full p-3 bg-white/10 rounded focus:outline-none text-white"
                placeholder="Search for a genre..."
                @keyup.enter="performSearch"
              >
            </div>

            <!-- Year Field - Smaller width -->
            <div class="w-32">
              <label class="block text-sm font-medium mb-2 text-white/80">Year</label>
              <input
                v-model="yearFilter"
                type="text"
                placeholder="Type a year"
                class="w-full p-3 bg-white/10 rounded focus:outline-none text-white"
                @keyup.enter="performSearch"
              >
            </div>
          </div>

          <!-- Second Row: Min/Max Followers (below Genre) and Popularity (below Year) -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Left Column: Min/Max Followers -->
            <div class="grid grid-cols-2 gap-4">
              <!-- Minimum Followers -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Min. Followers</label>
                <input
                  v-model.number="followersMin"
                  type="number"
                  placeholder="0"
                  class="w-full p-3 bg-white/10 rounded focus:outline-none text-white"
                  min="0"
                  @keyup.enter="performSearch"
                >
              </div>

              <!-- Maximum Followers -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Max. Followers</label>
                <input
                  v-model.number="followersMax"
                  type="number"
                  placeholder="00"
                  class="w-full p-3 bg-white/10 rounded focus:outline-none text-white"
                  min="0"
                  @keyup.enter="performSearch"
                >
              </div>
            </div>

            <!-- Right Column: Tracks Popularity -->
            <div class="flex flex-col justify-end">
              <label class="block text-sm font-medium mb-2 text-white/80">
                Tracks Popularity: {{ popularityMin }}% - {{ popularityMax }}%
              </label>
              <div class="mt-2">
                <DualRangeSlider
                  :min="0"
                  :max="100"
                  :from="popularityMin"
                  :to="popularityMax"
                  class="popularity-slider-white"
                  @update:from="popularityMin = $event"
                  @update:to="popularityMax = $event"
                />
              </div>
            </div>
          </div>

          <!-- Search Button - Centered -->
          <div class="flex justify-center gap-3 mt-6">
            <button
              v-if="!((emptySlotCount > 0 || userHasBannedItems || queueExhausted) && hasSearched && tracks.length > 0)"
              :disabled="!searchQuery.trim() || isLoading"
              class="px-8 py-3 bg-white/10 hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-medium transition flex items-center gap-2 text-white"
              @click="performSearch"
            >
              <Icon :icon="faSearch" />
              <span>Search</span>
            </button>

            <!-- Search Again Button -->
            <button
              v-if="(emptySlotCount > 0 || userHasBannedItems || queueExhausted) && hasSearched && (tracks.length > 0 || searchAgainNoResults)"
              :disabled="(queueExhausted && searchAgainNoResults) || isLoading"
              class="px-8 py-3 bg-white/10 hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-medium transition flex items-center gap-2 text-white"
              @click="handleSearchAgain"
            >
              <Icon :icon="faSearch" />
              <span v-if="queueExhausted && searchAgainNoResults">No tracks found</span>
              <span v-else-if="queueExhausted">Click to search again</span>
              <span v-else-if="emptySlotCount > 0">Search Again</span>
              <span v-else>Search Again</span>
            </button>
          </div>
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

      <!-- Enhanced Results Table -->
      <div v-else-if="filteredTracks.length > 0">
        <!-- Ban Listened Tracks Toggle -->
        <div class="flex items-center gap-3 mb-4 justify-end">
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

        <div class="bg-white/5 rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full max-w-full">
              <thead>
                <tr class="border-b border-white/10">
                  <th class="text-left p-3 font-medium" />
                  <th class="text-left p-3 font-medium w-12">Ban Artist</th>
                  <th class="text-left p-3 font-medium min-w-[200px] pl-10">Artist(s)</th>
                  <th class="text-left p-3 font-medium">Title</th>
                  <th class="text-center p-3 font-medium whitespace-nowrap">Followers</th>
                  <th class="text-center p-3 font-medium whitespace-nowrap">Release Date</th>
                  <th class="text-center pl-3 font-medium whitespace-nowrap" />
                  <th class="text-center pr-3 font-medium whitespace-nowrap" />
                </tr>
              </thead>
              <tbody>
                <template v-for="(track, index) in filteredTracks" :key="track.spotify_id">
                  <tr
                    class="hover:bg-white/5 transition h-16"
                    :class="[
                      expandedTrackId === getTrackKey(track) ? 'bg-white/5' : 'border-b border-white/5',
                    ]"
                  >
                    <!-- Index -->
                    <td class="p-3 align-middle">
                      <span class="text-white/60">{{ index + 1 }}</span>
                    </td>

                    <!-- Ban Artist Button -->
                    <td class="p-3 align-middle">
                      <div class="flex items-center justify-center">
                        <button
                          class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center" :class="[
                            isArtistBanned(track)
                              ? 'bg-red-600 hover:bg-red-700 text-white'
                              : 'bg-[#484948] hover:bg-gray-500 text-white',
                          ]"
                          :title="isArtistBanned(track) ? 'Click to unban this artist' : 'Ban this artist'"
                          @click="banArtist(track)"
                        >
                          <Icon :icon="faUserSlash" class="text-xs" />
                        </button>
                      </div>
                    </td>

                    <!-- Artist(s) -->
                    <td class="p-3 align-middle pl-10">
                      <button
                        class="font-medium text-white hover:text-k-accent transition cursor-pointer"
                        :title="`View ${track.artist_name} on Spotify`"
                        @click="openSpotifyArtistPage(track)"
                      >
                        {{ track.artist_name }}
                      </button>
                    </td>

                    <!-- Track Title -->
                    <td class="p-3 align-middle">
                      <button
                        class="font-medium text-white hover:text-k-accent transition cursor-pointer"
                        :title="`View '${track.track_name}' on Spotify`"
                        @click="openSpotifyTrackPage(track)"
                      >
                        {{ track.track_name }}
                      </button>
                    </td>

                    <!-- Followers Count -->
                    <td class="p-3 align-middle text-center">
                      <div class="flex items-center justify-center">
                        <span class="text-white/60 text-sm">
                          {{ formatFollowers(track.followers || 0) }}
                        </span>
                      </div>
                    </td>

                    <!-- Release Date -->
                    <td class="p-3 align-middle text-center">
                      <div class="flex items-center justify-center">
                        <span class="text-white/60 text-sm">
                          {{ formatDate(track.release_date) }}
                        </span>
                      </div>
                    </td>

                    <!-- Save/Ban Actions -->
                    <td class="pl-3 align-middle">
                      <div class="flex gap-2 justify-center">
                        <!-- Save Button (24h) -->
                        <button
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="track.is_saved
                            ? 'bg-green-600 hover:bg-green-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'"
                          class="h-[34px] w-[34px] rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                          :title="track.is_saved ? 'Click to unsave track' : 'Save the Track (24h)'"
                          @click="saveTrack(track)"
                        >
                          <Icon :icon="faHeart" class="text-sm" />
                        </button>

                        <!-- Blacklist Button -->
                        <button
                          :disabled="processingTrack === getTrackKey(track)"
                          :class="track.is_banned
                            ? 'bg-orange-600 hover:bg-orange-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'"
                          class="h-[34px] w-[34px] rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                          :title="track.is_banned ? 'Click to unblock track' : 'Ban the Track'"
                          @click="banTrack(track)"
                        >
                          <Icon :icon="faBan" class="text-sm" />
                        </button>
                      </div>
                    </td>

                    <!-- Related/Preview Actions -->
                    <td class="pr-3 align-middle">
                      <div class="flex gap-2 justify-center -ml-4">
                        <!-- Related Track Button -->
                        <button
                          :disabled="processingTrack === getTrackKey(track)"
                          class="px-3 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center"
                          title="Find Related Tracks"
                          @click="getRelatedTracks(track)"
                        >
                          <Icon :icon="faSearch" class="w-4 h-4 mr-2" />
                          <span>Related</span>
                        </button>

                        <!-- Preview Button -->
                        <button
                          :disabled="processingTrack === getTrackKey(track)"
                          class="px-3 py-2 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center" :class="[
                            (expandedTrackId === getTrackKey(track) || listenedTracks.has(getTrackKey(track)))
                              ? 'bg-green-600 hover:bg-green-700 text-white'
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
                          <img v-else-if="expandedTrackId !== getTrackKey(track) && !(processingTrack === getTrackKey(track) && isPreviewProcessing)" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                          <Icon v-else-if="expandedTrackId === getTrackKey(track) && !(processingTrack === getTrackKey(track) && isPreviewProcessing)" :icon="faTimes" class="w-3 h-3" />
                          <span :class="(processingTrack === getTrackKey(track) && isPreviewProcessing) ? '' : 'ml-1'">{{ (processingTrack === getTrackKey(track) && isPreviewProcessing) ? 'Loading...' : (expandedTrackId === getTrackKey(track) ? 'Close' : (listenedTracks.has(getTrackKey(track)) ? 'Listened' : 'Preview')) }}</span>
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Spotify Player Dropdown Row -->
                  <transition
                    name="player-expand"
                    @enter="onEnter"
                    @after-enter="onAfterEnter"
                    @leave="onLeave"
                    @after-leave="onAfterLeave"
                  >
                    <tr v-if="expandedTrackId === getTrackKey(track)" :key="`spotify-${getTrackKey(track)}-${index}`" class="border-b border-white/5 player-row">
                      <td colspan="9" class="p-0 overflow-hidden">
                        <div class="p-4 bg-white/5 relative pb-8">
                          <div class="max-w-4xl mx-auto">
                            <div v-if="track.spotify_id && track.spotify_id !== 'NO_TRACK_FOUND'">
                              <iframe
                                :key="track.spotify_id"
                                :src="`https://open.spotify.com/embed/track/${track.spotify_id}?utm_source=generator&theme=0`"
                                :title="`${track.artist_name} - ${track.track_name}`"
                                class="w-full spotify-embed"
                                style="height: 152px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);"
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
                  </transition>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="hasSearched && !isLoading" class="mt-8 text-center text-gray-400">
        <p>No tracks found</p>
        <p class="text-sm mt-2">Try different search criteria</p>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faBan, faHeart, faPlay, faSearch, faSpinner, faTimes, faUserSlash } from '@fortawesome/free-solid-svg-icons'
import { computed, onMounted, ref, watch } from 'vue'
import { http } from '@/services/http'
import { logger } from '@/utils/logger'
import { useRouter } from '@/composables/useRouter'
import Router from '@/router'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import DualRangeSlider from '@/components/ui/DualRangeSlider.vue'

// Initialize router
const { onRouteChanged } = useRouter()

// Reactive state
const searchQuery = ref('')
const yearFilter = ref('')
const followersMin = ref<number | null>(null)
const followersMax = ref<number | null>(null)
const popularityMin = ref(0)
const popularityMax = ref(100)
const isLoading = ref(false)
const errorMessage = ref('')
const tracks = ref([])
const hasSearched = ref(false)
const lastSearchQuery = ref('')
const lastYearFilter = ref('')
const expandedTrackId = ref<string | null>(null)
const processingTrack = ref<string | null>(null)
const isPreviewProcessing = ref(false)
const bannedArtists = ref(new Set<string>())
const currentOffset = ref(0)
const searchAgainNoResults = ref(false)

// Slot-based virtual table system
const slotMap = ref<Record<number, any | null>>({})
const trackQueue = ref<any[]>([])
const userHasBannedItems = ref(false)
const queueExhausted = ref(false)

// Ban listened tracks feature
const banListenedTracks = ref(false)
const listenedTracks = ref(new Set<string>())
const pendingAutoBannedTracks = ref(new Set<string>())
const blacklistedTracks = ref(new Set<string>())

// Initialize blacklist filtering
const { addArtistToBlacklist, loadBlacklistedItems } = useBlacklistFiltering()

// Computed: Display tracks from slot system
const filteredTracks = computed(() => {
  const slots: any[] = []
  for (let i = 0; i < 20; i++) {
    const track = slotMap.value[i]
    if (track !== null && track !== undefined) {
      slots.push(track)
    }
  }
  return slots
})

// Count empty slots
const emptySlotCount = computed(() => {
  let count = 0
  for (let i = 0; i < 20; i++) {
    if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
      count++
    }
  }
  return count
})

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
      return dateString.split('T')[0]
    }
  } catch (error) {
    return dateString.split('T')[0]
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

const performSearch = async (isSearchAgain = false) => {
  if (!searchQuery.value.trim()) {
    return
  }

  // Close any open preview dropdown
  expandedTrackId.value = null

  isLoading.value = true
  errorMessage.value = ''

  // If this is a search again and queue is exhausted, increment offset
  if (isSearchAgain && queueExhausted.value) {
    currentOffset.value += 1
    searchAgainNoResults.value = false // Reset when starting new search
  } else if (!isSearchAgain) {
    // Reset offset for new search
    currentOffset.value = 0
    lastSearchQuery.value = searchQuery.value.trim()
    lastYearFilter.value = yearFilter.value
    searchAgainNoResults.value = false
  }

  try {
    const params: Record<string, any> = {
      genre: searchQuery.value.trim(),
    }

    if (yearFilter.value) {
      params.year = yearFilter.value
    }

    if (popularityMin.value > 0 || popularityMax.value < 100) {
      params.popularity_min = popularityMin.value
      params.popularity_max = popularityMax.value
    }

    if (followersMin.value !== null) {
      params.followers_min = followersMin.value
    }

    if (followersMax.value !== null) {
      params.followers_max = followersMax.value
    }

    if (isSearchAgain && currentOffset.value > 0) {
      params.offset = currentOffset.value
    }

    const queryString = new URLSearchParams(params).toString()
    console.log('Making request to:', `genre-by-year?${queryString}`)

    const response = await http.get(`genre-by-year?${queryString}`)

    // Debug logging
    console.log('Raw response object:', response)
    console.log('Response tracks:', response?.tracks)
    console.log('Response data tracks:', response?.data?.tracks)

    // Ensure we get an array - handle different response structures
    let responseTracks = response?.tracks || response?.data?.tracks || []

    // Safety check: ensure responseTracks is an array
    // If it's an object with numeric keys, convert it to an array
    if (!Array.isArray(responseTracks)) {
      if (responseTracks && typeof responseTracks === 'object') {
        // Convert object with numeric keys to array
        responseTracks = Object.values(responseTracks)
        console.log('Converted object to array, length:', responseTracks.length)
      } else {
        console.error('Response tracks is not an array or object:', responseTracks)
        responseTracks = []
      }
    }

    console.log('Total tracks received:', responseTracks.length)

    // Filter out locally banned artists
    const filteredTracks = responseTracks.filter(track =>
      !track.is_banned
      && !track.is_artist_banned
      && !bannedArtists.value.has(track.artist_name),
    )

    console.log('After filtering banned tracks and artists:', filteredTracks.length)

    const allTracks = filteredTracks.map(track => ({
      ...track,
      isSaved: track.is_saved || false,
      isBanned: track.is_banned || false,
    }))

    if (isSearchAgain && queueExhausted.value) {
      // This is a search again with offset - populate slots directly if empty
      // Check if slots are empty
      let hasEmptySlots = false
      for (let i = 0; i < 20; i++) {
        if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
          hasEmptySlots = true
          break
        }
      }

      if (hasEmptySlots && allTracks.length > 0) {
        // Populate empty slots directly from new tracks
        let trackIndex = 0
        for (let i = 0; i < 20 && trackIndex < allTracks.length; i++) {
          if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
            slotMap.value[i] = allTracks[trackIndex]
            trackIndex++
          }
        }

        // Add remaining tracks to queue
        if (trackIndex < allTracks.length) {
          trackQueue.value = [...trackQueue.value, ...allTracks.slice(trackIndex)]
        }

        // Reset queueExhausted since we have new tracks
        queueExhausted.value = false
      } else {
        // No empty slots or no new tracks - just add to queue
        trackQueue.value = [...trackQueue.value, ...allTracks]
        if (allTracks.length > 0) {
          queueExhausted.value = false
        }
      }
    } else {
      // Initialize slot system: First 20 go in slots, rest go in queue
      slotMap.value = {}
      trackQueue.value = []
      userHasBannedItems.value = false
      queueExhausted.value = false

      for (let i = 0; i < Math.min(20, allTracks.length); i++) {
        slotMap.value[i] = allTracks[i]
      }

      if (allTracks.length > 20) {
        trackQueue.value = allTracks.slice(20)
      }
    }

    // If no results after search again, show message in button
    if (isSearchAgain && allTracks.length === 0) {
      queueExhausted.value = true
      searchAgainNoResults.value = true
      errorMessage.value = ''
    } else {
      searchAgainNoResults.value = false
    }

    // If initial search has 0 results, show message and allow search again
    if (!isSearchAgain && allTracks.length === 0) {
      errorMessage.value = 'No tracks found'
      // Reset state to allow new search when fields are modified
      queueExhausted.value = false
      userHasBannedItems.value = false
      searchAgainNoResults.value = false
    }

    tracks.value = allTracks
    hasSearched.value = true
  } catch (error: any) {
    logger.error('Genre by Year search failed:', error)
    console.error('Error details:', error)
    console.error('Error response:', error.response)

    if (error.response?.status === 503) {
      errorMessage.value = 'Spotify integration is not available. Please contact support.'
    } else if (error.response?.status === 500) {
      errorMessage.value = 'Server error occurred. Please try again in a moment.'
    } else if (error.response?.status === 422) {
      errorMessage.value = 'Invalid search parameters. Please check your input.'
    } else if (error.message?.includes('filter is not a function')) {
      errorMessage.value = 'Invalid response format from server. Please try again.'
    } else {
      errorMessage.value = `Search failed: ${error.message || 'Unknown error'}`
    }
  } finally {
    isLoading.value = false
  }
}

// Handle search again
const handleSearchAgain = async () => {
  // If queue is exhausted, perform new search with offset
  if (queueExhausted.value) {
    await performSearch(true)
  } else {
    // Otherwise, refill from queue
    refillFromQueue()
  }
}

// Refill empty slots from queue
const refillFromQueue = () => {
  // Flush pending auto-bans first (remove them from slots if not already removed)
  flushPendingAutoBans()

  let emptyCount = 0
  for (let i = 0; i < 20; i++) {
    if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
      emptyCount++
    }
  }

  if (emptyCount === 0) {
    userHasBannedItems.value = false
    return
  }

  // Filter queue to remove tracks by banned artists and already blacklisted tracks
  const filteredQueue = trackQueue.value.filter(track => {
    const trackKey = getTrackKey(track)
    return !bannedArtists.value.has(track.artist_name)
      && !blacklistedTracks.value.has(trackKey)
      && !track.is_banned
      && !track.is_artist_banned
  })

  trackQueue.value = filteredQueue

  if (trackQueue.value.length === 0) {
    queueExhausted.value = true
    // If queue is exhausted but we still have empty slots, we'll need to do a new search
    if (emptyCount > 0) {
      userHasBannedItems.value = true
    } else {
      userHasBannedItems.value = false
    }
    return
  }

  // Compact existing tracks to the front, then add new tracks at the end
  const existingTracks: any[] = []
  for (let i = 0; i < 20; i++) {
    const track = slotMap.value[i]
    if (track !== null && track !== undefined) {
      existingTracks.push(track)
    }
  }

  let filledCount = 0
  const tracksNeeded = Math.min(emptyCount, trackQueue.value.length)

  for (let i = 0; i < tracksNeeded; i++) {
    const nextTrack = trackQueue.value.shift()
    if (nextTrack) {
      existingTracks.push(nextTrack)
      filledCount++
    }
  }

  // Rebuild slot map
  slotMap.value = {}
  for (let i = 0; i < Math.min(20, existingTracks.length); i++) {
    slotMap.value[i] = existingTracks[i]
  }

  // Clear remaining slots if we have less than 20 tracks
  for (let i = existingTracks.length; i < 20; i++) {
    slotMap.value[i] = null
  }

  // Update userHasBannedItems based on whether we still have empty slots
  if (emptySlotCount.value === 0) {
    userHasBannedItems.value = false
  }

  if (trackQueue.value.length === 0) {
    queueExhausted.value = true
  }
}

// Mark track as listened and auto-ban if toggle is ON
const markTrackAsListened = async (track: any) => {
  const trackKey = getTrackKey(track)
  listenedTracks.value.add(trackKey)
  listenedTracks.value = new Set(listenedTracks.value)

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
      localStorage.setItem('koel-genre-by-year-listened-tracks', JSON.stringify(keys))
    } catch {}
  }

  // If auto-ban is enabled, ban the track
  if (banListenedTracks.value) {
    try {
      // Generate a fallback ISRC if none exists (required by API)
      const isrcValue = track.isrc || track.spotify_id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/blacklist-track', {
        isrc: isrcValue,
        track_name: track.track_name,
        artist_name: track.artist_name,
        spotify_id: track.spotify_id,
      })

      if (response.success) {
        // Update track's is_banned property so UI shows orange button
        track.is_banned = true
        track.isBanned = true // Also set camelCase for consistency
        blacklistedTracks.value.add(trackKey)

        // Store track ID for deferred removal (will be removed on "Search Again")
        pendingAutoBannedTracks.value.add(track.spotify_id)

        // Emit that user has banned an item (enables "Search Again" button)
        // But DON'T remove from slot yet - track stays visible until Search Again
        userHasBannedItems.value = true
      }
    } catch (error) {
      console.warn('Failed to auto-ban listened track:', error)
    }
  }
}

// Flush pending auto-banned tracks
const flushPendingAutoBans = () => {
  if (pendingAutoBannedTracks.value.size === 0) {
    return
  }

  pendingAutoBannedTracks.value.forEach(trackId => {
    for (let i = 0; i < 20; i++) {
      const track = slotMap.value[i]
      if (track && track.spotify_id === trackId) {
        slotMap.value[i] = null
      }
    }
  })

  pendingAutoBannedTracks.value.clear()
}

const saveTrack = async (track: any) => {
  const trackKey = getTrackKey(track)

  // Close any open preview dropdown when saving/unsaving tracks
  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
  }

  try {
    if (track.isSaved) {
      // UNSAVE TRACK - Update UI immediately
      track.isSaved = false
      try {
        const deleteData = {
          isrc: track.isrc,
          track_name: track.track_name,
          artist_name: track.artist_name,
        }
        const params = new URLSearchParams(deleteData)
        await http.delete(`music-preferences/blacklist-track?${params}`)
      } catch (error) {
        console.warn('Failed to remove track from blacklist on unsave:', error)
      }
    } else {
      // SAVE TRACK - Remove from slot immediately for instant UX
      let removedFromSlot = false
      for (let i = 0; i < 20; i++) {
        if (slotMap.value[i] && getTrackKey(slotMap.value[i]) === trackKey) {
          slotMap.value[i] = null
          removedFromSlot = true
          break
        }
      }

      // Update UI immediately
      track.isSaved = true
      userHasBannedItems.value = true

      // Do backend work in background
      try {
        const savePayload = {
          spotify_id: track.spotify_id,
          isrc: track.isrc,
          track_name: track.track_name,
          artist_name: track.artist_name,
          popularity: track.popularity,
          followers: track.followers,
          release_date: track.release_date,
          preview_url: track.preview_url,
        }

        const response = await http.post('music-preferences/save-track', savePayload)

        if (response.success) {
          localStorage.setItem('track-saved-timestamp', Date.now().toString())

          // Also blacklist the track (for fresh discovery results)
          try {
            // Generate a fallback ISRC if none exists (required by API)
            const isrcValue = track.isrc || track.spotify_id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

            await http.post('music-preferences/blacklist-track', {
              spotify_id: track.spotify_id,
              isrc: isrcValue,
              track_name: track.track_name,
              artist_name: track.artist_name,
            })

            track.isBanned = true
            blacklistedTracks.value.add(trackKey)
          } catch (error) {
            console.error('Error blacklisting track:', error)
            // Don't fail the save operation if blacklisting fails
          }
        } else {
          // Revert UI change if backend failed
          if (removedFromSlot) {
            for (let i = 0; i < 20; i++) {
              if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
                slotMap.value[i] = track
                break
              }
            }
          }
          track.isSaved = false
          if (emptySlotCount.value === 0) {
            userHasBannedItems.value = false
          }
          throw new Error(response.error || 'Failed to save track')
        }
      } catch (error) {
        // Revert UI change if request failed
        if (removedFromSlot) {
          for (let i = 0; i < 20; i++) {
            if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
              slotMap.value[i] = track
              break
            }
          }
        }
        track.isSaved = false
        if (emptySlotCount.value === 0) {
          userHasBannedItems.value = false
        }
        logger.error('Failed to save track:', error)
      }
    }
  } catch (error) {
    logger.error('Failed to toggle save track:', error)
  }
}

const banTrack = async (track: any) => {
  const trackKey = getTrackKey(track)

  // Close any open preview dropdown when blacklisting tracks
  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
  }

  try {
    if (track.isBanned) {
      // UNBAN TRACK - Update UI immediately
      track.isBanned = false
      blacklistedTracks.value.delete(trackKey)

      if (pendingAutoBannedTracks.value.has(track.spotify_id)) {
        pendingAutoBannedTracks.value.delete(track.spotify_id)
        if (pendingAutoBannedTracks.value.size === 0 && emptySlotCount.value === 0) {
          userHasBannedItems.value = false
        }
      }

      // Try to restore track to slot if there's an empty slot
      // (This is optional - you might want to keep it removed)
      // Generate a fallback ISRC if none exists
      const isrcValue = track.isrc || track.spotify_id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
      const deleteData = {
        isrc: isrcValue,
        track_name: track.track_name,
        artist_name: track.artist_name,
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (!response.success) {
        track.isBanned = true
        blacklistedTracks.value.add(trackKey)
        logger.error('Failed to remove track from blacklist:', response.error)
      }
    } else {
      // BAN TRACK - Remove from slot immediately for instant UX
      let removedFromSlot = false
      for (let i = 0; i < 20; i++) {
        if (slotMap.value[i] && getTrackKey(slotMap.value[i]) === trackKey) {
          slotMap.value[i] = null
          removedFromSlot = true
          break
        }
      }

      // Update UI immediately
      track.isBanned = true
      blacklistedTracks.value.add(trackKey)
      userHasBannedItems.value = true

      // Do backend work in background
      try {
        // Generate a fallback ISRC if none exists (required by API)
        const isrcValue = track.isrc || track.spotify_id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

        const response = await http.post('music-preferences/blacklist-track', {
          spotify_id: track.spotify_id,
          isrc: isrcValue,
          track_name: track.track_name,
          artist_name: track.artist_name,
        })

        if (!response.success) {
          // Revert UI change if backend failed
          if (removedFromSlot) {
            // Try to restore to first available slot
            for (let i = 0; i < 20; i++) {
              if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
                slotMap.value[i] = track
                break
              }
            }
          }
          track.isBanned = false
          blacklistedTracks.value.delete(trackKey)
          if (emptySlotCount.value === 0) {
            userHasBannedItems.value = false
          }
          throw new Error(response.error || 'Failed to blacklist track')
        }
      } catch (error) {
        // If backend call failed, revert UI
        if (removedFromSlot) {
          for (let i = 0; i < 20; i++) {
            if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
              slotMap.value[i] = track
              break
            }
          }
        }
        track.isBanned = false
        blacklistedTracks.value.delete(trackKey)
        if (emptySlotCount.value === 0) {
          userHasBannedItems.value = false
        }
        logger.error('Failed to blacklist track:', error)
      }
    }
  } catch (error) {
    logger.error('Failed to toggle ban track:', error)
  }
}

// Get related tracks - navigate to Music Discovery
const getRelatedTracks = (track: any) => {
  console.log('Getting related tracks for:', track.track_name)

  // Store track info in localStorage for Music Discovery to use as seed
  // This matches the format expected by MusicDiscoveryScreen
  const seedTrackData = {
    id: track.spotify_id,
    name: track.track_name,
    artist: track.artist_name,
    timestamp: Date.now(),
  }

  localStorage.setItem('koel-music-discovery-seed-track', JSON.stringify(seedTrackData))
  console.log('Stored seed track data for Music Discovery:', seedTrackData)

  // Navigate to Music Discovery screen using hash navigation (same as SavedTracksScreen)
  window.location.hash = '#/discover'
}

// Spotify player functionality
const toggleSpotifyPlayer = async (track: any) => {
  const trackKey = getTrackKey(track)

  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

  markTrackAsListened(track)

  if (!track.spotify_id) {
    processingTrack.value = trackKey
    isPreviewProcessing.value = true

    try {
      const response = await http.get('music-discovery/track-preview', {
        params: {
          artist_name: track.artist_name,
          track_title: track.track_name,
          source: 'genre-by-year',
        },
      })

      if (response.success && response.data && response.data.spotify_track_id) {
        track.spotify_id = response.data.spotify_track_id
        expandedTrackId.value = trackKey
      }
    } catch (error: any) {
      console.error('Failed to get track preview:', error)
    } finally {
      processingTrack.value = null
      isPreviewProcessing.value = false
    }
  } else {
    expandedTrackId.value = trackKey
  }
}

// Open Spotify pages
const openSpotifyArtistPage = (track: any) => {
  if (track.spotify_artist_url) {
    window.open(track.spotify_artist_url, '_blank')
  }
}

const openSpotifyTrackPage = (track: any) => {
  if (track.spotify_track_url) {
    window.open(track.spotify_track_url, '_blank')
  }
}

// Check if artist is banned
const isArtistBanned = (track: any): boolean => {
  return bannedArtists.value.has(track.artist_name)
}

// Load banned artists from localStorage
const loadBannedArtists = () => {
  try {
    const stored = localStorage.getItem('koel-banned-artists')
    if (stored) {
      const bannedList = JSON.parse(stored)
      bannedArtists.value = new Set(bannedList)
    }
  } catch (error) {
    console.warn('Failed to load banned artists from localStorage:', error)
  }
}

// Save banned artists to localStorage
const saveBannedArtists = () => {
  try {
    const bannedList = Array.from(bannedArtists.value)
    localStorage.setItem('koel-banned-artists', JSON.stringify(bannedList))
  } catch (error) {
    console.warn('Failed to save banned artists to localStorage:', error)
  }
}

// Ban/Unban an artist
const banArtist = async (track: any) => {
  expandedTrackId.value = null

  const artistName = track.artist_name
  const isCurrentlyBanned = isArtistBanned(track)

  if (isCurrentlyBanned) {
    bannedArtists.value.delete(artistName)
    bannedArtists.value = new Set(bannedArtists.value)
    saveBannedArtists()

    try {
      const spotifyArtistId = track.spotify_artist_id || track.artist_id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
      const deleteData = {
        artist_name: artistName,
        spotify_artist_id: spotifyArtistId,
      }
      const params = new URLSearchParams(deleteData)
      await http.delete(`music-preferences/blacklist-artist?${params}`)
    } catch (apiError: any) {
      console.error('Failed to remove artist from blacklist:', apiError)
    }
  } else {
    bannedArtists.value.add(artistName)
    bannedArtists.value = new Set(bannedArtists.value)
    saveBannedArtists()

    try {
      const spotifyArtistId = track.spotify_artist_id || track.artist_id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
      await http.post('music-preferences/blacklist-artist', {
        artist_name: artistName,
        spotify_artist_id: spotifyArtistId,
      })
    } catch (apiError: any) {
      console.error('Failed to add artist to blacklist:', apiError)
    }

    // Remove all tracks by this artist from the slot system
    let removedCount = 0
    for (let i = 0; i < 20; i++) {
      const slotTrack = slotMap.value[i]
      if (slotTrack && slotTrack.artist_name === artistName) {
        slotMap.value[i] = null
        removedCount++
      }
    }

    if (removedCount > 0) {
      userHasBannedItems.value = true
    }
  }
}

// Watch for "Ban listened tracks" toggle
// Watch for changes in search fields - reset state if user modifies criteria after no results
watch([searchQuery, yearFilter, followersMin, followersMax, popularityMin, popularityMax], () => {
  // If we had no results and user changes search criteria, reset state to allow new search
  if (hasSearched.value && tracks.value.length === 0) {
    queueExhausted.value = false
    userHasBannedItems.value = false
    errorMessage.value = ''
  }
})

// Watch for "Ban listened tracks" toggle being turned ON
watch(banListenedTracks, async (newValue, oldValue) => {
  if (newValue === true && oldValue === false) {
    console.log('🎵 [GENRE BY YEAR] Ban listened tracks toggle turned ON - auto-banning all listened tracks')

    // Get all currently displayed tracks from slots
    const tracksToAutoBan: any[] = []
    for (let i = 0; i < 20; i++) {
      const track = slotMap.value[i]
      if (track && listenedTracks.value.has(getTrackKey(track))) {
        tracksToAutoBan.push(track)
      }
    }

    console.log(`🎵 [GENRE BY YEAR] Found ${tracksToAutoBan.length} listened tracks to auto-ban`)

    // Ban each listened track
    for (const track of tracksToAutoBan) {
      const trackKey = getTrackKey(track)

      // Skip if already blacklisted
      if (blacklistedTracks.value.has(trackKey)) {
        continue
      }

      try {
        const isrcValue = track.isrc || track.spotify_id || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

        const response = await http.post('music-preferences/blacklist-track', {
          isrc: isrcValue,
          track_name: track.track_name,
          artist_name: track.artist_name,
        })

        if (response.success) {
          // Update track's is_banned property so UI shows orange button
          track.is_banned = true
          track.isBanned = true // Also set camelCase for consistency
          blacklistedTracks.value.add(trackKey)
          pendingAutoBannedTracks.value.add(track.spotify_id)
          console.log(`🎵 [GENRE BY YEAR] Auto-banned listened track: ${track.track_name}`)
        }
      } catch (error) {
        console.warn(`Failed to auto-ban listened track: ${track.track_name}`, error)
      }
    }

    // If any tracks were banned, show Search Again button
    if (tracksToAutoBan.length > 0) {
      userHasBannedItems.value = true
    }
  }
})

// Check on mount
onMounted(async () => {
  loadBannedArtists()

  try {
    const resp: any = await http.get('music-preferences/listened-tracks')
    if (resp?.success && Array.isArray(resp.data)) {
      listenedTracks.value = new Set(resp.data as string[])
    }
  } catch (e) {
    try {
      const stored = localStorage.getItem('koel-genre-by-year-listened-tracks')
      if (stored) {
        const keys: string[] = JSON.parse(stored)
        listenedTracks.value = new Set(keys)
      }
    } catch {}
  }
})

// Animation methods for smooth player dropdown
const onEnter = (el: Element) => {
  const htmlEl = el as HTMLElement
  htmlEl.style.height = '0'
  htmlEl.style.opacity = '0'
  htmlEl.style.overflow = 'hidden'
  htmlEl.style.transform = 'scaleY(0)'
  htmlEl.style.transformOrigin = 'top'
}

const onAfterEnter = (el: Element) => {
  const htmlEl = el as HTMLElement
  htmlEl.style.height = ''
  htmlEl.style.opacity = ''
  htmlEl.style.overflow = ''
  htmlEl.style.transform = ''
  htmlEl.style.transformOrigin = ''
}

const onLeave = (el: Element) => {
  const htmlEl = el as HTMLElement
  const height = htmlEl.offsetHeight
  htmlEl.style.height = `${height}px`
  htmlEl.style.opacity = '1'
  htmlEl.style.overflow = 'hidden'
  htmlEl.style.transform = 'scaleY(1)'
  htmlEl.style.transformOrigin = 'top'
  htmlEl.style.transition = 'none'

  // Force reflow
  htmlEl.offsetHeight

  // Re-enable transitions
  htmlEl.style.transition = ''

  requestAnimationFrame(() => {
    htmlEl.style.height = '0'
    htmlEl.style.opacity = '0'
    htmlEl.style.transform = 'scaleY(0.98)'
  })
}

const onAfterLeave = (el: Element) => {
  const htmlEl = el as HTMLElement
  htmlEl.style.height = ''
  htmlEl.style.opacity = ''
  htmlEl.style.overflow = ''
  htmlEl.style.transform = ''
  htmlEl.style.transformOrigin = ''
}

onRouteChanged(route => {
  if (expandedTrackId.value) {
    expandedTrackId.value = null
  }

  if (route.screen === 'GenreByYear') {
    loadBannedArtists()
  }
})
</script>

<style lang="postcss" scoped>
/* Enhanced transition styles for smooth player dropdown */
.player-expand-enter-active {
  transition:
    height 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94),
    opacity 0.2s ease-out 0.05s,
    transform 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  will-change: height, opacity, transform;
}

.player-expand-leave-active {
  transition:
    height 0.18s cubic-bezier(0.25, 0.46, 0.45, 0.94),
    opacity 0.12s ease-out,
    transform 0.18s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  will-change: height, opacity, transform;
}

.player-expand-enter-from {
  height: 0;
  opacity: 0;
  transform: scaleY(0);
  transform-origin: top;
}

.player-expand-leave-to {
  height: 0;
  opacity: 0;
  transform: scaleY(0.98);
  transform-origin: top;
}

/* Smooth table row transitions */
tr {
  transition: background-color 0.15s ease;
}

/* Prevent layout shifts during player animations */
.player-row {
  position: relative;
  contain: layout;
}

.spotify-embed {
  opacity: 0;
  transition: opacity 0.5s ease;
}

/* White Popularity slider styling (like BPM slider in SoundCloud) */
.popularity-slider-white :deep(.track-background) {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.popularity-slider-white :deep(.track-fill) {
  background: rgba(255, 255, 255, 0.6);
}

.popularity-slider-white :deep(.range-input::-webkit-slider-thumb) {
  background: white;
  border: 2px solid rgba(255, 255, 255, 0.8);
  margin-top: 0px;
}

.popularity-slider-white :deep(.range-input::-webkit-slider-thumb:hover) {
  background: rgba(255, 255, 255, 0.9);
  border-color: white;
  box-shadow: 0 4px 8px rgba(255, 255, 255, 0.3);
}

.popularity-slider-white :deep(.range-input::-moz-range-thumb) {
  background: white;
  border: 2px solid rgba(255, 255, 255, 0.8);
  margin-top: 0px;
}
</style>
