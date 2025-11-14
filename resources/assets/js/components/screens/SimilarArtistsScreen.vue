<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="simple" class="text-center" header-image="/VantaNova-Logo.svg">
        <template #subtitle>
          <div class="rounded-lg px-4 mr-16">
            <div class="max-w-4xl mx-auto text-center">
              <span v-if="selectedArtist" class="text-text-secondary">
                Similar to: {{ selectedArtist.name }}
              </span>
              <span v-else>Find artists similar to your seed artist</span>
            </div>
          </div>
        </template>
      </ScreenHeader>
    </template>

    <div class="similar-artists-screen">
      <!-- Search Container -->
      <div class="seed-selection mb-8">
        <div class="search-container mb-6">
          <div class="rounded-lg p-4">
            <div class="max-w-[39rem] mx-auto">
              <div ref="searchContainer" class="relative">
                <div class="flex">
                  <input
                    v-model="searchQuery"
                    type="text"
                    class="flex-1 py-3 pl-4 pr-4 bg-white/10 rounded-l-lg focus:outline-none text-white text-lg search-input"
                    placeholder="Search for a Seed Artist"
                    @keydown.enter="performSearch"
                    @input="onSearchInput"
                  >
                  <button
                    class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 text-white rounded-r-lg transition-colors flex items-center justify-center"
                    :disabled="!searchQuery.trim() || searchLoading"
                    @click="performSearch"
                  >
                    <Icon :icon="faSearch" class="w-5 h-5" />
                  </button>
                </div>

                <!-- Loading Animation -->
                <div
                  v-if="searchLoading && searchQuery.trim()"
                  class="absolute z-50 w-full border border-k-border rounded-lg mt-1 shadow-xl"
                  style="background-color: #302f30;"
                >
                  <div class="flex items-center justify-center py-8">
                    <div class="flex items-center gap-3">
                      <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
                      <span class="text-k-text-secondary">Searching for artists...</span>
                    </div>
                  </div>
                </div>

                <!-- Search Dropdown -->
                <div
                  v-if="searchResults.length > 0 && !searchLoading"
                  class="absolute z-50 w-full border border-k-border rounded-lg mt-1 shadow-xl"
                  style="background-color: #302f30;"
                >
                  <div class="max-h-80 rounded-lg overflow-hidden overflow-y-auto">
                    <div v-for="(artist, index) in searchResults.slice(0, 10)" :key="`suggestion-${artist.mbid || artist.name}-${index}`">
                      <div
                        class="flex items-center justify-between px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors group border-b border-k-border/30 last:border-b-0"
                        :class="{
                          'bg-k-accent/10': selectedArtist && selectedArtist.name === artist.name,
                        }"
                        @click="handleArtistClick(artist)"
                      >
                        <!-- Artist Info -->
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-k-text-primary group-hover:text-gray-200 transition-colors truncate">
                            {{ artist.name }}
                          </div>
                        </div>
                      </div>
                    </div>

                    <div v-if="searchResults.length > 10" class="px-4 py-3 text-center text-k-text-tertiary text-sm border-t border-k-border bg-k-bg-tertiary/20">
                      <Icon :icon="faMusic" class="mr-1 opacity-50" />
                      {{ searchResults.length - 10 }} more artists found
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Selected Seed Track Display - Compact -->
      <div v-if="selectedArtist" class="selected-seed mb-8 relative z-20">
        <div class="max-w-[39rem] mx-auto">
          <div class="text-sm font-medium mb-2">Seed Artist:</div>
          <div class="bg-k-bg-secondary/50 border border-k-border rounded-lg px-3 py-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2 flex-1 min-w-0">
                <Icon :icon="faCheck" class="w-4 h-4 text-k-accent flex-shrink-0" />
                <span class="text-k-text-primary font-medium truncate">{{ selectedArtist.name }}</span>
              </div>
              <button
                class="p-1 hover:bg-red-600/20 text-k-text-tertiary hover:text-red-400 rounded transition-colors flex-shrink-0 ml-2"
                title="Clear seed artist"
                @click="clearSeedArtist"
              >
                <Icon :icon="faTimes" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Similar Artists Results -->
      <div v-if="isLoading" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-white mb-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        </div>
      </div>

      <div v-else-if="errorMessage" class="error-section flex flex-col items-center justify-center py-16 space-y-4">
        <div class="w-16 h-16 bg-red-500/10 rounded-full flex items-center justify-center">
          <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-center space-y-2">
          <div class="text-red-400 font-medium">Something went wrong</div>
          <div class="text-k-text-secondary text-sm max-w-md">{{ errorMessage }}</div>
          <button
            class="mt-4 px-4 py-2 bg-k-accent hover:bg-k-accent/90 text-white rounded-lg text-sm font-medium transition-colors"
            @click="selectedArtist && findSimilarArtists()"
          >
            Try Again
          </button>
        </div>
      </div>

      <div v-else-if="displayedArtists.length > 0" class="results-section">
        <!-- Info Message -->
        <div class="text-center mb-4 max-w-4xl mx-auto">
          <p class="text-k-text-secondary text-sm">
            Ban or save artists to add new ones at the list's end.
          </p>
        </div>

        <div class="bg-white/5 rounded-lg overflow-hidden relative z-10 max-w-4xl mx-auto">
          <div class="overflow-x-auto scrollbar-hide">
            <table class="w-full relative z-10">
              <thead>
                <tr class="border-b border-white/10">
                  <th class="text-left p-3 font-medium" />
                  <th class="text-left p-3 py-7 font-medium w-1/3">Artist</th>
                  <th class="text-right p-3 font-medium whitespace-nowrap">Followers</th>
                  <th class="text-left p-3 font-medium" />
                </tr>
              </thead>
              <tbody>
                <template v-for="(slot, index) in displayedSlots" :key="`artist-${slot.artist.mbid || slot.artist.name}`">
                  <!-- Artist Row -->
                  <tr
                    class="hover:bg-white/5 transition h-16 border-b border-white/5"
                    :class="[
                      currentlyPreviewingArtist === slot.artist.name ? 'bg-white/5' : '',
                      slot.artist && (slot.artist as any).__leaving ? 'row-slide-out' : '',
                    ]"
                  >
                    <!-- Index -->
                    <td class="p-3 align-middle">
                      <span class="text-white/60">{{ index + 1 }}</span>
                    </td>

                    <!-- Artist Name -->
                    <td class="p-3 align-middle">
                      <span class="font-medium text-white whitespace-nowrap">
                        {{ slot.artist.name }}
                      </span>
                    </td>

                    <!-- Followers -->
                    <td class="p-3 px-6 align-middle text-right">
                      <span class="text-white/80">{{ formatFollowers(slot.artist.followers || 0) }}</span>
                    </td>

                    <!-- Actions -->
                    <td class="px-4 align-middle">
                      <div class="flex gap-2 justify-end">
                        <!-- Ban Artist (local-only, icon button) -->
                        <button
                          :class="isArtistBanned(slot.artist)
                            ? 'bg-red-600 hover:bg-red-700 text-white'
                            : 'bg-[#484948] hover:bg-gray-500 text-white'"
                          class="h-[34px] w-[34px] rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                          :title="isArtistBanned(slot.artist) ? 'Click to unban artist' : 'Ban the Artist (Similar Artists only)'"
                          @click="banArtist(slot.artist)"
                        >
                          <Icon :icon="faBan" class="text-sm" />
                        </button>
                        <button
                          class="px-3 ml-2 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center text-white"
                          title="Find Similar Artists"
                          @click="findSimilarArtists(slot.artist)"
                        >
                          <Icon :icon="faSearch" class="w-4 h-4 mr-2" />
                          <span>Similars</span>
                        </button>
                        <button
                          :disabled="loadingPreviewArtist === slot.artist.name"
                          class="px-3 py-2 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center" :class="[
                            (currentlyPreviewingArtist === slot.artist.name || hasListenedTracks(slot.artist))
                              ? 'bg-[#868685] hover:bg-[#6d6d6d] text-white'
                              : 'bg-[#484948] hover:bg-gray-500 text-white',
                          ]"
                          :title="loadingPreviewArtist === slot.artist.name ? 'Loading...' : (currentlyPreviewingArtist === slot.artist.name ? 'Close preview' : (hasListenedTracks(slot.artist) ? 'Tracks have been listened to' : 'Preview artist tracks'))"
                          @click="previewArtist(slot.artist)"
                        >
                          <!-- Regular icon when not processing -->
                          <img v-if="currentlyPreviewingArtist !== slot.artist.name" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                          <Icon v-else :icon="faTimes" class="w-3 h-3" />
                          <span :class="loadingPreviewArtist === slot.artist.name ? '' : 'ml-1'">{{ loadingPreviewArtist === slot.artist.name ? 'Loading...' : (currentlyPreviewingArtist === slot.artist.name ? 'Close' : (hasListenedTracks(slot.artist) ? 'Listened' : 'Preview')) }}</span>
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Spotify Preview Section -->
                  <tr v-if="currentlyPreviewingArtist === slot.artist.name" class="bg-white/5 border-b border-white/5">
                    <td colspan="8" class="p-0">
                      <div class="spotify-player-container p-6 bg-white/3 relative">
                        <!-- Loading State -->
                        <div v-if="loadingPreviewArtist === slot.artist.name" class="flex items-center justify-center" style="height: 80px;">
                          <div class="flex items-center gap-3">
                            <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
                            <span class="text-k-text-secondary">Loading tracks...</span>
                          </div>
                        </div>

                        <!-- Tracks Display -->
                        <div v-else-if="slot.artist.spotifyTracks && slot.artist.spotifyTracks.length > 0" class="max-w-4xl mx-auto">
                          <div
                            v-for="track in slot.artist.spotifyTracks.slice(0, 1)"
                            :key="track.id"
                            class="w-full"
                          >
                            <div v-if="track.id && track.id !== 'NO_TRACK_FOUND'" class="flex gap-2 items-center">
                              <!-- Save/Ban Buttons -->
                              <div class="flex flex-col gap-2 flex-shrink-0">
                                <!-- Save Button -->
                                <button
                                  :disabled="processingTrack === getTrackKey(track)"
                                  :class="isTrackSaved(track)
                                    ? 'bg-green-600 hover:bg-green-700 text-white'
                                    : 'bg-[#484948] hover:bg-gray-500 text-white'"
                                  class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                                  :title="isTrackSaved(track) ? 'Click to unsave track' : 'Save the Track (24h)'"
                                  @click="saveTrack(track)"
                                >
                                  <Icon :icon="faHeart" class="text-sm" />
                                </button>

                                <!-- Ban Button -->
                                <button
                                  :disabled="processingTrack === getTrackKey(track)"
                                  :class="isTrackBanned(track)
                                    ? 'bg-orange-600 hover:bg-orange-700 text-white'
                                    : 'bg-[#484948] hover:bg-gray-500 text-white'"
                                  class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                                  :title="isTrackBanned(track) ? 'Click to unblock track' : 'Ban the Track'"
                                  @click="banTrack(track)"
                                >
                                  <Icon :icon="faBan" class="text-sm" />
                                </button>
                              </div>

                              <!-- Spotify Embed -->
                              <iframe
                                :key="track.id"
                                :src="`https://open.spotify.com/embed/${track.embed_type || 'track'}/${track.id}?utm_source=generator&theme=0`"
                                :title="`${track.artists?.[0]?.name || 'Unknown'} - ${track.name}`"
                                class="flex-1 spotify-embed"
                                style="height: 80px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);"
                                frameBorder="0"
                                scrolling="no"
                                allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                                loading="lazy"
                                @load="(event) => { event.target.style.opacity = '1' }"
                                @error="() => {}"
                              />
                            </div>
                            <div v-else class="flex gap-2 items-center">
                              <!-- Save/Ban Buttons for No Preview -->
                              <div class="flex flex-col gap-2 flex-shrink-0">
                                <!-- Save Button -->
                                <button
                                  :disabled="processingTrack === getTrackKey(track)"
                                  :class="isTrackSaved(track)
                                    ? 'bg-green-600 hover:bg-green-700 text-white'
                                    : 'bg-[#484948] hover:bg-gray-500 text-white'"
                                  class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                                  :title="isTrackSaved(track) ? 'Click to unsave track' : 'Save the Track (24h)'"
                                  @click="saveTrack(track)"
                                >
                                  <Icon :icon="faHeart" class="text-sm" />
                                </button>

                                <!-- Ban Button -->
                                <button
                                  :disabled="processingTrack === getTrackKey(track)"
                                  :class="isTrackBanned(track)
                                    ? 'bg-orange-600 hover:bg-orange-700 text-white'
                                    : 'bg-[#484948] hover:bg-gray-500 text-white'"
                                  class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                                  :title="isTrackBanned(track) ? 'Click to unblock track' : 'Ban the Track'"
                                  @click="banTrack(track)"
                                >
                                  <Icon :icon="faBan" class="text-sm" />
                                </button>
                              </div>

                              <!-- No Preview Available -->
                              <div class="flex-1 flex items-center justify-center" style="height: 80px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);">
                                <div class="text-center text-white/60">
                                  <div class="text-sm font-medium">No Spotify preview available</div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- No Tracks Found -->
                        <div v-else class="flex items-center justify-center py-8">
                          <div class="text-center text-white/60">
                            <div class="text-sm font-medium">No tracks found for this artist</div>
                          </div>
                        </div>

                        <!-- Spotify Login Link -->
                        <div class="absolute bottom-0 right-6">
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
      </div>

      <div v-else-if="selectedArtist && !isLoading" class="no-results text-center py-12">
        <div class="text-gray-400">No similar artists found.</div>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { http } from '@/services/http'
import { useRouter } from '@/composables/useRouter'
import { faArrowUp, faBan, faCheck, faChevronDown, faClock, faFilter, faHeart, faMusic, faPlay, faSearch, faSpinner, faTimes, faUserSlash } from '@fortawesome/free-solid-svg-icons'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

// No global blacklist integration here; Similar Artists bans are local-only

const { onRouteChanged } = useRouter()

interface LastfmArtist {
  name: string
  mbid: string
  url: string
  image: Array<{ '#text': string, 'size': string }>
  listeners?: string
  playcount?: string
  match?: string
  spotifyTracks?: SpotifyTrack[]
  allSpotifyTracks?: SpotifyTrack[] // Store all tracks for re-filtering
  // New Spotify fields
  id?: string // Spotify artist ID
  followers?: number
  popularity?: number
  genres?: string[]
  external_url?: string
  images?: Array<{ url: string, height: number, width: number }>
}

interface SpotifyTrack {
  id: string
  name: string
  artists: Array<{ name: string }>
  preview_url?: string
  external_url?: string
  duration_ms?: number
  oembed?: {
    html: string
    width: number
    height: number
    version: string
    type: string
    provider_name: string
    provider_url: string
    title: string
    author_name: string
    author_url: string
    thumbnail_url: string
    thumbnail_width: number
    thumbnail_height: number
  }
}

// Search state
const searchQuery = ref('')
const searchResults = ref<LastfmArtist[]>([])
const searchLoading = ref(false)
const searchTimeout = ref<ReturnType<typeof setTimeout>>()
const searchContainer = ref<HTMLElement | null>(null)
const initialLoadComplete = ref(false)
const allowAnimations = ref(false)

// Selected artist and results
const selectedArtist = ref<LastfmArtist | null>(null)
const similarArtists = ref<LastfmArtist[]>([])
const filteredArtists = ref<LastfmArtist[]>([])
const displayedArtists = ref<LastfmArtist[]>([])

// Virtual Slot System: Maps slot position (0-19) to artist object or null
const slotMap = ref<Record<number, LastfmArtist | null>>({})

// Artist queue for refilling empty slots
const artistQueue = ref<LastfmArtist[]>([])

// Count empty slots in the slot map
const emptySlotCount = computed(() => {
  return Object.values(slotMap.value).filter(artist => artist === null).length
})

// Computed property for displayed slots (filters out null slots for continuous display)
const displayedSlots = computed(() => {
  const slots: Array<{ slotIndex: number, artist: LastfmArtist }> = []
  for (let i = 0; i < 20; i++) {
    const artist = slotMap.value[i]
    if (artist !== null && artist !== undefined) {
      slots.push({
        slotIndex: i,
        artist,
      })
    }
  }
  return slots
})

// Loading states
const isLoading = ref(false)
const loadingListeners = ref(new Set<string>())
const loadingPageListeners = ref(false)
const errorMessage = ref('')

// Preview management
const currentlyPreviewingArtist = ref<string | null>(null)
const loadingPreviewArtist = ref<string | null>(null)

// Track which tracks have been listened to (previewed)
const listenedTracks = ref(new Set<string>())

// Track locally hidden artists (session-only, not global ban)
const locallyHiddenArtists = ref(new Set<string>())

// Helper functions
const getTrackKey = (track: SpotifyTrack): string => {
  const artist = track.artists?.[0]?.name || 'Unknown'
  return `${artist}-${track.name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

const hasListenedTracks = (artist: LastfmArtist): boolean => {
  // Check if any tracks by this artist have been listened to
  if (artist.allSpotifyTracks && artist.allSpotifyTracks.length > 0) {
    return artist.allSpotifyTracks.some(track => listenedTracks.value.has(getTrackKey(track)))
  }
  // Fallback: check if any track key contains the artist name
  const artistName = artist.name.toLowerCase().replace(/[^a-z0-9]/g, '-')
  return Array.from(listenedTracks.value).some(trackKey => trackKey.startsWith(`${artistName}-`))
}

const hideArtist = (artist: LastfmArtist) => {
  locallyHiddenArtists.value.add(artist.name)

  // Close any open preview for this artist
  if (currentlyPreviewingArtist.value === artist.name) {
    closePreview(artist)
  }

  // Find the slot for this artist, mark as leaving for slide-out, then remove after a short delay
  for (const slotIdx of Object.keys(slotMap.value)) {
    const slotNumber = Number(slotIdx)
    const slotArtist = slotMap.value[slotNumber]

    if (slotArtist && slotArtist.name === artist.name) {
      ;(slotArtist as any).__leaving = true
      // Delay removal to allow CSS transition
      setTimeout(() => {
        slotMap.value[slotNumber] = null
        updateDisplayedArtistsFromSlots()
        // Refill after removal
        refillSlotsFromQueue()
      }, 220)
      break
    }
  }
}

const refillSlotsFromQueue = () => {
  console.log(`🔄 [SLOT SYSTEM] Starting refill from queue`)

  // Count how many empty slots we have
  let emptyCount = 0
  for (let i = 0; i < 20; i++) {
    if (slotMap.value[i] === null || slotMap.value[i] === undefined) {
      emptyCount++
    }
  }

  console.log(`🔄 [SLOT SYSTEM] Found ${emptyCount} empty slots to fill`)

  if (emptyCount === 0) {
    console.log(`🔄 [SLOT SYSTEM] No empty slots to refill`)
    return
  }

  const availableInQueue = artistQueue.value.length
  console.log(`🔄 [SLOT SYSTEM] Queue has ${availableInQueue} artists available`)

  if (availableInQueue === 0) {
    console.log(`⚠️ [SLOT SYSTEM] Queue is empty - cannot refill`)
    return
  }

  // Compact existing artists to the front, then add new artists at the end
  const existingArtists: LastfmArtist[] = []
  for (let i = 0; i < 20; i++) {
    const artist = slotMap.value[i]
    if (artist !== null && artist !== undefined) {
      existingArtists.push(artist)
    }
  }

  console.log(`🔄 [SLOT SYSTEM] Compacted ${existingArtists.length} existing artists`)

  // Add new artists from queue to fill up to 20 slots
  let filledCount = 0
  const artistsNeeded = Math.min(emptyCount, artistQueue.value.length)
  const newlyAddedArtists: LastfmArtist[] = []

  for (let i = 0; i < artistsNeeded; i++) {
    const nextArtist = artistQueue.value.shift()
    if (nextArtist) {
      existingArtists.push(nextArtist)
      newlyAddedArtists.push(nextArtist)
      filledCount++
      console.log(`🔄 [SLOT SYSTEM] Adding artist from queue:`, {
        name: nextArtist.name,
        id: nextArtist.id,
        mbid: nextArtist.mbid,
        hasFollowers: nextArtist.followers !== undefined,
        followers: nextArtist.followers,
      })
    }
  }

  // Rebuild slot map with compacted artists (existing first, new at end)
  slotMap.value = {}
  for (let i = 0; i < existingArtists.length; i++) {
    slotMap.value[i] = existingArtists[i]
  }

  // Update displayed artists
  updateDisplayedArtistsFromSlots()

  console.log(`🔄 [SLOT SYSTEM] Refilled with ${filledCount} new artists at bottom, ${artistQueue.value.length} artists remaining in queue`)
  console.log(`🔄 [SLOT SYSTEM] Newly added artists details:`, newlyAddedArtists.map(a => ({
    name: a.name,
    id: a.id,
    mbid: a.mbid,
    followers: a.followers,
  })))

  // Load followers/listeners data for newly added artists
  // Skip sorting because artists from queue are already sorted by followers
  if (filledCount > 0) {
    console.log(`📊 [SLOT SYSTEM] Triggering loadPageListenersCounts() for ${filledCount} newly added artists (skipSort=true)`)
    loadPageListenersCounts(true) // Skip sorting - queue is already sorted
  }
}

// Sorting
const sortBy = ref('listeners-desc')
const dropdownOpen = ref(false)
const showLikesRatioDropdown = ref(false)

// Banned artists tracking
const bannedArtists = ref(new Set<string>()) // Store MBIDs of banned artists

// Track management state (similar to RecommendationsTable.vue)
const savedTracks = ref<Set<string>>(new Set())
const blacklistedTracks = ref<Set<string>>(new Set())
const clientUnsavedTracks = ref<Set<string>>(new Set()) // Tracks unsaved by client
const processingTrack = ref<string | null>(null)

// Helper function to check if an artist is banned (local to Similar Artists)
const isArtistBanned = (artist: LastfmArtist): boolean => {
  // Use Spotify ID as primary identifier, fallback to mbid, then name
  const uniqueId = artist.id || artist.mbid || artist.name
  return bannedArtists.value.has(uniqueId)
}

// Sort options
const sortOptions = [
  { value: 'match', label: 'Best Matches' },
  { value: 'listeners-desc', label: 'Most Followers' },
  { value: 'listeners-asc', label: 'Least Followers' },
  { value: 'ratio-desc', label: 'Best Ratio' },
]

// Clear dropdown when user types
const onSearchInput = () => {
  // Clear search results when user edits the query
  searchResults.value = []
}

// Manual search functionality
const performSearch = () => {
  if (!searchQuery.value.trim()) {
    return
  }

  // Clear any existing timeout
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }

  searchArtists()
}

const searchArtists = async () => {
  if (!searchQuery.value.trim()) {
    searchResults.value = []
    return
  }

  searchLoading.value = true

  try {
    console.log('🔍 [FRONTEND] Starting artist search', {
      query: searchQuery.value,
      timestamp: new Date().toISOString(),
    })

    const response = await http.get('similar-artists/search', {
      params: {
        query: searchQuery.value,
        limit: 20,
      },
    })

    console.log('🔍 [FRONTEND] Search response received', {
      success: response.success,
      dataLength: response.data?.length,
      error: response.error,
    })

    if (response.success && response.data) {
      // Helper: normalize artist names to dedupe variants (e.g., "Eminem", "Eminem - Topic")
      const normalizeArtistName = (name: string): string => {
        return name
          .normalize('NFKD') // split accents
          .replace(/[\u0300-\u036F]/g, '') // remove diacritics
          .replace(/\(.*?\)|\[.*?\]/g, '') // drop parentheticals
          .replace(/\s*-\s*topic$/i, '') // drop trailing "- Topic"
          .replace(/official/gi, '') // drop word "official"
          .replace(/\s+/g, ' ') // collapse spaces
          .trim()
          .toLowerCase()
      }

      // Filter out invalid results
      const validResults = response.data.filter(artist => {
        // Must have a name
        if (!artist || !artist.name || typeof artist.name !== 'string') {
          return false
        }
        return true
      })

      // Deduplicate by normalized name (primary), fallback to Spotify ID
      const seen = new Set<string>()
      const dedupedResults = validResults.filter(a => {
        const normalized = normalizeArtistName(a.name)
        const fallbackId = (a.id && a.id.trim()) || ''
        const key = normalized || fallbackId
        if (!key) {
          return false
        }
        if (seen.has(key)) {
          return false
        }
        seen.add(key)
        return true
      })

      const sortedResults = dedupedResults.sort((a, b) => {
        // First priority: exact match with search query
        const queryLower = searchQuery.value.toLowerCase()
        const aExact = a.name.toLowerCase() === queryLower
        const bExact = b.name.toLowerCase() === queryLower

        if (aExact && !bExact) {
          return -1
        }
        if (!aExact && bExact) {
          return 1
        }

        // Second priority: artists with Spotify IDs (for similarity search capability)
        const aSpotifyId = a.id && a.id.trim()
        const bSpotifyId = b.id && b.id.trim()

        if (aSpotifyId && !bSpotifyId) {
          return -1
        }
        if (!aSpotifyId && bSpotifyId) {
          return 1
        }

        // Third priority: followers count (higher first) - Spotify data
        const aFollowers = a.followers || 0
        const bFollowers = b.followers || 0

        if (aFollowers !== bFollowers) {
          return bFollowers - aFollowers
        }

        // Fourth priority: listener count (higher first) - Last.fm data
        const aListeners = Number.parseInt(a.listeners || '0', 10)
        const bListeners = Number.parseInt(b.listeners || '0', 10)

        if (aListeners !== bListeners) {
          return bListeners - aListeners
        }

        // Final priority: alphabetical by name
        return a.name.localeCompare(b.name)
      })

      searchResults.value = sortedResults
      console.log('🔍 [FRONTEND] Search results processed', {
        originalCount: response.data.length,
        validCount: validResults.length,
        dedupedCount: dedupedResults.length,
        totalResults: sortedResults.length,
        sampleArtists: sortedResults.slice(0, 3).map(a => a.name),
      })
    } else {
      console.warn('🔍 [FRONTEND] Search returned no valid results')
      searchResults.value = []
    }
  } catch (error: any) {
    console.error('🔍 [FRONTEND] Search error:', error)
    searchResults.value = []
  } finally {
    searchLoading.value = false
  }
}

const handleArtistClick = (artist: LastfmArtist) => {
  selectArtist(artist)
  // Clear search dropdown after selection
  searchResults.value = []
}

const selectArtist = (artist: LastfmArtist) => {
  selectedArtist.value = artist
  searchQuery.value = '' // Clear search box text when launching a search
  searchResults.value = []

  // Clear previous results
  similarArtists.value = []
  filteredArtists.value = []
  displayedArtists.value = []
  slotMap.value = {} // Clear the slot map
  artistQueue.value = [] // Clear the artist queue
  currentlyPreviewingArtist.value = null
  errorMessage.value = ''

  // Reset animation state (animations will be triggered when data loads)
  allowAnimations.value = false
  initialLoadComplete.value = false

  // Automatically find similar artists if the artist has an ID (Spotify or MBID)
  if ((artist.id && artist.id.trim()) || (artist.mbid && artist.mbid.trim())) {
    findSimilarArtists(artist)
  }
}

const clearSeedArtist = () => {
  selectedArtist.value = null
  searchQuery.value = ''
  searchResults.value = []
  similarArtists.value = []
  filteredArtists.value = []
  displayedArtists.value = []
  slotMap.value = {} // Clear the slot map
  artistQueue.value = [] // Clear the artist queue
  currentlyPreviewingArtist.value = null
  errorMessage.value = ''
}

// Ban/Unban an artist (local-only). On ban: hide immediately and refill from queue
const banArtist = async (artist: LastfmArtist) => {
  const artistName = artist.name
  const uniqueId = artist.id || artist.mbid || artist.name
  const isCurrentlyBanned = bannedArtists.value.has(uniqueId)

  try {
    console.log(`${isCurrentlyBanned ? '✅ Unbanning' : '🚫 Banning'} (local) artist:`, artistName)

    if (isCurrentlyBanned) {
      // UNBAN artist locally
      bannedArtists.value.delete(uniqueId)
      localStorage.setItem('koel-similar-banned-artists', JSON.stringify(Array.from(bannedArtists.value)))
      // Do not auto-restore into current list; will affect next result build
    } else {
      // BAN artist locally and hide immediately
      bannedArtists.value.add(uniqueId)
      localStorage.setItem('koel-similar-banned-artists', JSON.stringify(Array.from(bannedArtists.value)))

      // Hide from slots and refill from queue
      hideArtist(artist)
      // Try to fill any empty slots right away
      refillSlotsFromQueue()
    }
  } catch (error: any) {
    console.error(`Failed to ${isCurrentlyBanned ? 'unban' : 'ban'} artist:`, error)
    errorMessage.value = `Failed to ${isCurrentlyBanned ? 'unban' : 'ban'} artist: ${error.message || 'Unknown error'}`
  }
}

// Track management functions (from RecommendationsTable.vue)
const saveTrack = async (track: SpotifyTrack) => {
  console.log('🚀 [SIMILAR ARTISTS] saveTrack CALLED!')
  console.log('🚀 [SIMILAR ARTISTS] Track passed in:', track)

  const trackKey = getTrackKey(track)
  console.log('🚀 [SIMILAR ARTISTS] Track key:', trackKey)

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
    // Save track - Update UI immediately for instant feedback
    savedTracks.value.add(trackKey)
    clientUnsavedTracks.value.delete(trackKey)

    // Do backend work in background without blocking UI
    try {
      console.log('🎵 [SIMILAR ARTISTS] Starting to save track:', track.name, 'by', track.artists?.[0]?.name)
      console.log('🎵 [SIMILAR ARTISTS] Track object:', track)

      // Extract metadata from the Spotify track object
      let label = ''
      let popularity = track.popularity || 0
      let followers = 0
      let releaseDate = ''
      let previewUrl = track.preview_url || null

      console.log('🎵 [SIMILAR ARTISTS] Initial metadata - label:', label, 'popularity:', popularity, 'followers:', followers, 'releaseDate:', releaseDate)

      // Check if we need to fetch additional metadata
      const needsEnhancedMetadata = !label || !releaseDate

      console.log('🎵 [SIMILAR ARTISTS] needsEnhancedMetadata:', needsEnhancedMetadata)

      if (needsEnhancedMetadata) {
        try {
          console.log('🎵 [SIMILAR ARTISTS] Fetching enhanced metadata from API...')
          const response = await http.get('music-discovery/track-preview', {
            params: {
              artist_name: track.artists?.[0]?.name || 'Unknown',
              track_title: track.name,
              source: 'spotify',
              track_id: track.id,
            },
          })

          console.log('🎵 [SIMILAR ARTISTS] Enhanced data response:', response)

          if (response.success && response.data && response.data.metadata) {
            const metadata = response.data.metadata
            label = metadata.label || label
            popularity = metadata.popularity || popularity
            followers = metadata.followers || followers
            releaseDate = metadata.release_date || releaseDate
            previewUrl = metadata.preview_url || previewUrl

            console.log('🎵 [SIMILAR ARTISTS] Updated metadata - label:', label, 'popularity:', popularity, 'followers:', followers, 'releaseDate:', releaseDate)
          }
        } catch (error) {
          console.warn('🎵 [SIMILAR ARTISTS] Failed to fetch enhanced metadata, using basic data:', error)
        }
      }

      const savePayload = {
        isrc: track.id,
        track_name: track.name,
        artist_name: track.artists?.[0]?.name || 'Unknown',
        spotify_id: track.id,
        label,
        popularity,
        followers,
        release_date: releaseDate,
        preview_url: previewUrl,
        track_count: 1,
        is_single_track: true,
      }

      console.log('🎵 [SIMILAR ARTISTS] Sending save request with payload:', savePayload)

      const response = await http.post('music-preferences/save-track', savePayload)

      console.log('🎵 [SIMILAR ARTISTS] Save response:', response)

      if (response.success) {
        // Update localStorage
        try {
          const unsavedList = Array.from(clientUnsavedTracks.value)
          localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
        } catch (error) {
          // Failed to update unsaved tracks in localStorage
        }
      } else {
        // Revert UI change if backend failed
        savedTracks.value.delete(trackKey)
        throw new Error(response.error || 'Failed to save track')
      }
    } catch (error: any) {
      console.error('Failed to save track:', error)
      // Revert UI change if request failed
      savedTracks.value.delete(trackKey)
    }
  }
}

// Note: Session-based filtering - tracks stay visible when blacklisted during current session
// Filtering only happens when preview is initially opened, not when tracks are banned during session
const refreshCurrentPreview = () => {
  // Do nothing - tracks should remain visible during current session even when blacklisted
  // Filtering will only happen when preview is closed and reopened

}

const banTrack = async (track: SpotifyTrack) => {
  const trackKey = getTrackKey(track)

  if (isTrackBanned(track)) {
    // Update UI immediately for better UX
    blacklistedTracks.value.delete(trackKey)

    // Re-filter any currently open Spotify previews to include the newly unbanned track
    refreshCurrentPreview()

    // Do backend work in background without blocking UI
    try {
      const deleteData = {
        isrc: track.id,
        track_name: track.name,
        artist_name: track.artists?.[0]?.name || 'Unknown',
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (!response.success) {
        // Revert UI change if backend failed
        blacklistedTracks.value.add(trackKey)
        refreshCurrentPreview()
      }
    } catch (error: any) {
      // Revert UI change if request failed
      blacklistedTracks.value.add(trackKey)
      refreshCurrentPreview()
    }
  } else {
    // Block track - show processing state
    processingTrack.value = trackKey

    try {
      const response = await http.post('music-preferences/blacklist-track', {
        isrc: track.id,
        track_name: track.name,
        artist_name: track.artists?.[0]?.name || 'Unknown',
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)

        // Re-filter any currently open Spotify previews to remove the newly banned track
        refreshCurrentPreview()
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    } catch (error: any) {
      console.error('Failed to blacklist track:', error)
    } finally {
      processingTrack.value = null
    }
  }
}

// Similar artists functionality
const findSimilarArtists = async (artist?: LastfmArtist) => {
  // If called from button click, ignore the event parameter and use selected artist
  const targetArtist = (artist && typeof artist === 'object' && 'name' in artist) ? artist : selectedArtist.value

  if (!targetArtist) {
    errorMessage.value = 'Please select an artist from the search results first'
    return
  }

  // Check if artist has either Spotify ID or MBID
  const hasSpotifyId = targetArtist.id && targetArtist.id.trim()
  const hasMbid = targetArtist.mbid && targetArtist.mbid.trim()

  if (!hasSpotifyId && !hasMbid) {
    const artistName = targetArtist.name || 'the selected artist'
    errorMessage.value = `Sorry, "${artistName}" doesn't have the required music database ID for similarity search. Try searching for a different artist or a more specific artist name.`
    return
  }

  // If we clicked on a different artist, update selected artist
  if (artist && artist !== selectedArtist.value) {
    selectedArtist.value = artist
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    console.log('🎵 [FRONTEND] Starting similar artists search', {
      artistName: targetArtist.name,
      hasSpotifyId,
      hasMbid,
      spotifyId: targetArtist.id,
      mbid: targetArtist.mbid,
    })

    // Try Spotify API first if we have a Spotify ID
    let response
    if (hasSpotifyId) {
      console.log('🎵 [FRONTEND] Using Spotify API for similar artists')
      response = await http.get('similar-artists/similar', {
        params: {
          artist_id: targetArtist.id,
          limit: 50, // Request 50 artists so we have extras for the queue
        },
      })
    } else if (hasMbid) {
      console.log('🎵 [FRONTEND] Using Last.fm API for similar artists')
      // Fallback to Last.fm
      response = await http.get('similar-artists/similar', {
        params: {
          mbid: targetArtist.mbid,
          limit: 50, // Request 50 artists so we have extras for the queue
        },
      })
    }

    console.log('🎵 [FRONTEND] Similar artists response received', {
      success: response.success,
      dataLength: response.data?.length,
      error: response.message,
    })

    if (response.success && response.data) {
      // Filter out artists without IDs, banned artists (local), and locally hidden artists
      const artistsWithId = response.data.filter(artist => {
        const hasAnyId = (artist.id && artist.id.trim()) || (artist.mbid && artist.mbid.trim())
        if (!hasAnyId) {
          return false
        }
        // Use the same unique ID logic as isArtistBanned()
        const uniqueId = artist.id || artist.mbid || artist.name
        return !bannedArtists.value.has(uniqueId) && !locallyHiddenArtists.value.has(artist.name)
      })

      console.log('🎵 [FRONTEND] Similar artists filtered', {
        originalCount: response.data.length,
        filteredCount: artistsWithId.length,
        sampleArtists: artistsWithId.slice(0, 3).map(a => a.name),
      })

      // Enable animations BEFORE updating data to prevent flash on initial load
      allowAnimations.value = true

      similarArtists.value = artistsWithId
      filteredArtists.value = artistsWithId

      // Load followers/listeners count for ALL artists before sorting and populating slots
      console.log('📊 [FRONTEND] Loading followers for ALL artists before sorting', {
        totalArtists: artistsWithId.length,
      })
      await loadAllArtistsFollowers(artistsWithId)

      // Sort all artists by followers (descending) - most followers first
      const sortedArtists = [...artistsWithId].sort((a, b) => {
        const aFollowers = a.followers || 0
        const bFollowers = b.followers || 0
        // If followers are equal, use listeners as tiebreaker
        if (aFollowers === bFollowers) {
          const aListeners = Number.parseInt(a.listeners || '0', 10)
          const bListeners = Number.parseInt(b.listeners || '0', 10)
          return bListeners - aListeners
        }
        return bFollowers - aFollowers
      })

      console.log('📊 [FRONTEND] Sorted all artists by followers', {
        top5: sortedArtists.slice(0, 5).map(a => ({ name: a.name, followers: a.followers })),
        bottom5: sortedArtists.slice(-5).map(a => ({ name: a.name, followers: a.followers })),
      })

      // Initialize slot map with top 20 artists (most followers first)
      const initialArtists = sortedArtists.slice(0, 20)
      slotMap.value = {}
      initialArtists.forEach((artist, index) => {
        slotMap.value[index] = artist
      })

      // Store remaining artists in queue (already sorted by followers, descending)
      artistQueue.value = sortedArtists.slice(20)

      console.log(`✅ [SLOT SYSTEM] Initialized ${initialArtists.length} slots (sorted by followers), ${artistQueue.value.length} artists in queue`)

      // Update displayed artists from slots
      updateDisplayedArtistsFromSlots()

      // Don't apply sorting here - artists are already sorted by followers
      // Just update the display
      displayedArtists.value = initialArtists

      // Set initial load complete
      initialLoadComplete.value = true

      console.log('🎵 [FRONTEND] Similar artists setup complete', {
        totalArtists: similarArtists.value.length,
        displayedArtists: displayedArtists.value.length,
      })

      // Auto-disable animations after 2 seconds
      setTimeout(() => {
        allowAnimations.value = false
      }, 2000)
    } else {
      throw new Error(response.message || 'No similar artists found')
    }
  } catch (error: any) {
    console.error('Error finding similar artists:', error)
    errorMessage.value = error.response?.data?.message || error.message || 'Failed to find similar artists'
    similarArtists.value = []
    filteredArtists.value = []
    displayedArtists.value = []
  } finally {
    isLoading.value = false
  }
}

// Track if we need to reload after current request completes
const pendingListenersReload = ref(false)

// Load followers/listeners counts for ALL artists (used during initial load)
const loadAllArtistsFollowers = async (artists: LastfmArtist[]) => {
  if (artists.length === 0) {
    return
  }

  try {
    // Get artist IDs and MBIDs for all artists
    const artistIds = artists
      .filter(artist => artist.id && artist.id.trim())
      .map(artist => artist.id)

    const mbids = artists
      .filter(artist => artist.mbid && artist.mbid.trim())
      .map(artist => artist.mbid)

    console.log('📊 [FRONTEND] Loading followers for ALL artists', {
      totalArtists: artists.length,
      artistIdsCount: artistIds.length,
      mbidsCount: mbids.length,
    })

    if (artistIds.length > 0 || mbids.length > 0) {
      const response = await http.post('similar-artists/batch-listeners', {
        artist_ids: artistIds,
        mbids,
      })

      if (response.success && response.data) {
        // Update all artists with followers/listeners data
        let updatedCount = 0
        artists.forEach(artist => {
          const artistId = artist.id || artist.mbid
          if (artistId && response.data[artistId]) {
            const data = response.data[artistId]

            // Update with Spotify followers data
            if (data.followers !== undefined) {
              artist.followers = data.followers
            }
            if (data.popularity !== undefined) {
              artist.popularity = data.popularity
            }

            // Update with Last.fm listeners data
            if (data.listeners !== undefined) {
              artist.listeners = data.listeners.toString()
            }
            if (data.playcount !== undefined) {
              artist.playcount = data.playcount?.toString()
            }

            updatedCount++
          }
        })

        console.log(`📊 [FRONTEND] Updated ${updatedCount} artists with followers data`)
      }
    }
  } catch (error: any) {
    console.error('📊 [FRONTEND] Failed to load followers for all artists:', error)
  }
}

// Load followers/listeners counts for displayed artists only (used during refill)
const loadPageListenersCounts = async (skipSort = false) => {
  if (loadingPageListeners.value) {
    console.log('📊 [FRONTEND] loadPageListenersCounts() already in progress, will reload after completion')
    pendingListenersReload.value = true
    return
  }

  loadingPageListeners.value = true
  pendingListenersReload.value = false

  try {
    // Get artist IDs for displayed artists (from slots)
    const artistIds = displayedArtists.value
      .filter(artist => artist.id && artist.id.trim())
      .map(artist => artist.id)

    const mbids = displayedArtists.value
      .filter(artist => artist.mbid && artist.mbid.trim())
      .map(artist => artist.mbid)

    console.log('📊 [FRONTEND] Loading followers/listeners for displayed artists', {
      totalDisplayedArtists: displayedArtists.value.length,
      artistIdsCount: artistIds.length,
      mbidsCount: mbids.length,
      artistIds: artistIds.slice(0, 5), // Log first 5 for debugging
      mbids: mbids.slice(0, 5),
      allArtists: displayedArtists.value.map(a => ({
        name: a.name,
        id: a.id,
        mbid: a.mbid,
        currentFollowers: a.followers,
      })),
    })

    if (artistIds.length > 0 || mbids.length > 0) {
      const response = await http.post('similar-artists/batch-listeners', {
        artist_ids: artistIds,
        mbids,
      })

      console.log('📊 [FRONTEND] Batch followers response received', {
        success: response.success,
        dataKeys: Object.keys(response.data || {}),
        dataCount: Object.keys(response.data || {}).length,
      })

      if (response.success && response.data) {
        console.log('📊 [FRONTEND] Batch followers response data keys:', Object.keys(response.data))
        console.log('📊 [FRONTEND] Batch followers response data sample:', Object.entries(response.data).slice(0, 3).map(([key, value]) => ({ key, value })))

        // Update artists in slot map with followers/listeners data
        let updatedCount = 0
        let notFoundCount = 0
        for (let i = 0; i < 20; i++) {
          const artist = slotMap.value[i]
          if (artist) {
            const artistId = artist.id || artist.mbid
            if (artistId && response.data[artistId]) {
              const data = response.data[artistId]
              const beforeFollowers = artist.followers

              // Update with Spotify followers data
              if (data.followers !== undefined) {
                artist.followers = data.followers
              }
              if (data.popularity !== undefined) {
                artist.popularity = data.popularity
              }

              // Update with Last.fm listeners data
              if (data.listeners !== undefined) {
                artist.listeners = data.listeners.toString()
              }
              if (data.playcount !== undefined) {
                artist.playcount = data.playcount?.toString()
              }

              updatedCount++
              console.log(`📊 [FRONTEND] Updated artist ${i}:`, {
                name: artist.name,
                id: artistId,
                beforeFollowers,
                afterFollowers: artist.followers,
                data,
              })
            } else {
              notFoundCount++
              if (artistId) {
                console.log(`⚠️ [FRONTEND] Artist ${i} not found in response:`, {
                  name: artist.name,
                  id: artistId,
                  availableKeys: Object.keys(response.data),
                })
              } else {
                console.log(`⚠️ [FRONTEND] Artist ${i} has no ID/mbid:`, {
                  name: artist.name,
                  id: artist.id,
                  mbid: artist.mbid,
                })
              }
            }
          }
        }

        console.log(`📊 [FRONTEND] Updated ${updatedCount} artists, ${notFoundCount} not found in response`)

        // Update displayed artists from slots
        updateDisplayedArtistsFromSlots()

        // Re-apply sorting if needed (unless skipSort is true, e.g., during refill)
        if (!skipSort) {
          sortArtists()
        }

        console.log('📊 [FRONTEND] Followers/listeners data updated', {
          updatedArtists: displayedArtists.value.filter(a => a.followers || a.listeners).length,
          sampleData: displayedArtists.value.slice(0, 2).map(a => ({
            name: a.name,
            followers: a.followers,
            listeners: a.listeners,
            popularity: a.popularity,
          })),
        })
      }
    } else {
      console.log('📊 [FRONTEND] No artist IDs or MBIDs to fetch data for')
    }
  } catch (error: any) {
    console.error('📊 [FRONTEND] Failed to load followers/listeners:', error)
  } finally {
    loadingPageListeners.value = false
    
    // If a reload was requested while we were loading, trigger it now
    if (pendingListenersReload.value) {
      console.log('📊 [FRONTEND] Pending reload requested, triggering another loadPageListenersCounts()')
      pendingListenersReload.value = false
      // Use nextTick to ensure the state has updated
      await nextTick()
      loadPageListenersCounts()
    }
  }
}

// Spotify preview functionality
const previewArtist = async (artist: LastfmArtist) => {
  console.log('Previewing artist:', artist.name)

  // If this artist is already being previewed, close it
  if (currentlyPreviewingArtist.value === artist.name) {
    closePreview(artist)
    return
  }

  // Close any currently open preview
  if (currentlyPreviewingArtist.value) {
    const currentArtist = displayedArtists.value.find(a => a.name === currentlyPreviewingArtist.value)
    if (currentArtist) {
      closePreview(currentArtist)
    }
  }

  // Immediately open the dropdown for instant feedback
  currentlyPreviewingArtist.value = artist.name

  // Set loading state
  loadingPreviewArtist.value = artist.name

  try {
    console.log('🎵 [FRONTEND] Getting Spotify preview for artist', {
      artistName: artist.name,
      hasSpotifyId: !!(artist.id && artist.id.trim()),
      spotifyId: artist.id,
    })

    // Try with Spotify artist ID first, then fallback to artist name
    const params: any = {}
    if (artist.id && artist.id.trim()) {
      params.artist_id = artist.id
      console.log('🎵 [FRONTEND] Using Spotify artist ID for preview')
    } else {
      params.artist_name = artist.name
      console.log('🎵 [FRONTEND] Using artist name for preview')
    }

    const response = await http.get('similar-artists/spotify-preview', {
      params,
    })

    console.log('🎵 [FRONTEND] Spotify preview response received', {
      success: response.success,
      tracksCount: response.data?.tracks?.length,
      error: response.message,
    })

    if (response.success && response.data && response.data.tracks.length > 0) {
      // Filter out blacklisted tracks when initially opening preview (not during session)
      const filteredTracks = response.data.tracks.filter(track => !isTrackBanned(track))
      const tracksToShow = filteredTracks.slice(0, 1)

      console.log('🎵 [FRONTEND] Spotify preview tracks processed', {
        totalTracks: response.data.tracks.length,
        filteredTracks: filteredTracks.length,
        tracksToShow: tracksToShow.length,
        sampleTracks: tracksToShow.map(t => t.name),
      })

      // Store all original tracks for reference and show filtered tracks
      artist.allSpotifyTracks = response.data.tracks // Store all tracks
      artist.spotifyTracks = tracksToShow // Show first 1 non-blacklisted track

      // Mark tracks as listened immediately when preview opens
      tracksToShow.forEach(track => {
        markTrackAsListened(track)
      })

      // Stop any currently playing Spotify tracks
      stopAllSpotifyPlayers()
    } else {
      console.warn('🎵 [FRONTEND] No tracks found for preview, closing')
      // If no tracks found, close the preview
      closePreview(artist)
    }
  } catch (error: any) {
    console.error('Failed to get Spotify preview for', artist.name, error)
    // If error occurred, close the preview
    closePreview(artist)
  } finally {
    // Clear loading state
    loadingPreviewArtist.value = null
  }
}

const closePreview = (artist: LastfmArtist) => {
  // Stop any playing Spotify tracks in this preview
  stopSpotifyPlayersForArtist(artist)

  // Remove the preview tracks
  artist.spotifyTracks = undefined

  // Clear the currently previewing state if this was the active one
  if (currentlyPreviewingArtist.value === artist.name) {
    currentlyPreviewingArtist.value = null
  }
}

// Mark track as listened and persist state
const markTrackAsListened = async (track: SpotifyTrack) => {
  const trackKey = getTrackKey(track)

  // Mark as listened (optimistic)
  listenedTracks.value.add(trackKey)
  // Reassign to trigger Vue reactivity for Set mutations
  listenedTracks.value = new Set(listenedTracks.value)

  // Persist listened state
  try {
    const response = await http.post('music-preferences/listened-track', {
      track_key: trackKey,
      track_name: track.name,
      artist_name: track.artists?.[0]?.name || 'Unknown',
      spotify_id: track.id,
      isrc: undefined, // Spotify tracks don't have ISRC in this context
    })
  } catch (e) {
    // If unauthenticated, store locally
    try {
      const keys = Array.from(listenedTracks.value)
      localStorage.setItem('koel-listened-tracks', JSON.stringify(keys))
    } catch {}
  }
}

const stopAllSpotifyPlayers = () => {
  // Find all Spotify iframes and pause them
  const spotifyIframes = document.querySelectorAll('.spotify-oembed iframe')
  spotifyIframes.forEach((iframe: any) => {
    try {
      // Send pause message to Spotify embed
      iframe.contentWindow?.postMessage(JSON.stringify({
        command: 'pause',
      }), 'https://open.spotify.com')
    } catch (error) {
      // Spotify embeds don't always support programmatic control
      console.log('Could not pause Spotify player:', error)
    }
  })
}

const stopSpotifyPlayersForArtist = (artist: LastfmArtist) => {
  // Find Spotify iframes specifically for this artist and pause them
  const artistCards = document.querySelectorAll('.artist-card')
  artistCards.forEach((card: any) => {
    const artistName = card.querySelector('.text-lg.font-semibold')?.textContent?.trim()
    if (artistName === artist.name) {
      const spotifyIframes = card.querySelectorAll('.spotify-oembed iframe')
      spotifyIframes.forEach((iframe: any) => {
        try {
          iframe.contentWindow?.postMessage(JSON.stringify({
            command: 'pause',
          }), 'https://open.spotify.com')
        } catch (error) {
          console.log('Could not pause Spotify player for artist:', error)
        }
      })
    }
  })
}

// Utility functions
const getLastFmArtistUrl = (artistName: string): string => {
  // Use the exact artist name and encode it properly for LastFM URLs
  const encodedArtist = encodeURIComponent(artistName.replace(/ /g, '+'))
  return `https://www.last.fm/music/${encodedArtist}`
}

const openLastFmArtistPage = (artist: LastfmArtist): void => {
  // Open the LastFM artist page in a new tab using the exact artist name
  const artistUrl = getLastFmArtistUrl(artist.name)
  window.open(artistUrl, '_blank', 'noopener,noreferrer')
}

const formatListeners = (listeners: string | number): string => {
  const num = typeof listeners === 'string' ? Number.parseInt(listeners, 10) : listeners
  if (num >= 1000000) {
    return `${(num / 1000000).toFixed(1)}M`
  } else if (num >= 1000) {
    return `${(num / 1000).toFixed(1)}K`
  }
  return num.toString()
}

const formatFollowers = (followers: number): string => {
  if (followers >= 1000000) {
    return `${(followers / 1000000).toFixed(1)}M`
  } else if (followers >= 1000) {
    return `${(followers / 1000).toFixed(1)}K`
  }
  return followers.toString()
}

const formatPlaycount = (playcount: string | number): string => {
  const num = typeof playcount === 'string' ? Number.parseInt(playcount, 10) : playcount
  if (num >= 1000000000) {
    return `${(num / 1000000000).toFixed(1)}B`
  } else if (num >= 1000000) {
    return `${(num / 1000000).toFixed(1)}M`
  } else if (num >= 1000) {
    return `${(num / 1000).toFixed(1)}K`
  }
  return num.toString()
}

const calculateSLRatio = (playcount: string | number, listeners: string | number): string => {
  const streams = typeof playcount === 'string' ? Number.parseInt(playcount, 10) : playcount
  const uniqueListeners = typeof listeners === 'string' ? Number.parseInt(listeners, 10) : listeners

  if (!streams || !uniqueListeners || uniqueListeners === 0) {
    return '-'
  }

  const ratio = streams / uniqueListeners

  if (ratio >= 100) {
    return Math.round(ratio).toString()
  } else if (ratio >= 10) {
    return ratio.toFixed(1)
  } else {
    return ratio.toFixed(2)
  }
}

const formatDuration = (durationMs: number): string => {
  const minutes = Math.floor(durationMs / 60000)
  const seconds = Math.floor((durationMs % 60000) / 1000)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

// Track management helper functions (from RecommendationsTable.vue)
// getTrackKey is already defined above

const isTrackSaved = (track: SpotifyTrack): boolean => {
  const trackKey = getTrackKey(track)
  return savedTracks.value.has(trackKey) && !clientUnsavedTracks.value.has(trackKey)
}

const isTrackBanned = (track: SpotifyTrack): boolean => {
  return blacklistedTracks.value.has(getTrackKey(track))
}

const updateDisplayedArtistsFromSlots = () => {
  // Update displayed artists from slot map (filters out null slots)
  displayedArtists.value = Object.values(slotMap.value).filter(artist => artist !== null) as LastfmArtist[]
  console.log(`[SLOT SYSTEM] Updated displayed artists from slots: ${displayedArtists.value.length} artists visible, ${emptySlotCount.value} empty slots`)
}

const updateDisplayedArtists = () => {
  // Get artists from slots first
  const artistsFromSlots = Object.values(slotMap.value).filter(artist => artist !== null) as LastfmArtist[]

  // Apply sorting if needed
  if (sortBy.value === 'match') {
    // Default sort - use artists in their original order from slots
    displayedArtists.value = [...artistsFromSlots]
    console.log(`[DISPLAY] Showing ${displayedArtists.value.length} artists (match order)`)
  } else {
    // Sort the displayed artists
    const sortedArtists = [...artistsFromSlots].sort((a, b) => {
      switch (sortBy.value) {
        case 'listeners-desc':
          // Prioritize Spotify followers, then Last.fm listeners
          const aFollowers = a.followers || 0
          const bFollowers = b.followers || 0
          if (aFollowers !== bFollowers) {
            return bFollowers - aFollowers
          }
          const aListeners = Number.parseInt(a.listeners || '0', 10)
          const bListeners = Number.parseInt(b.listeners || '0', 10)
          return bListeners - aListeners
        case 'listeners-asc':
          // Prioritize Spotify followers, then Last.fm listeners
          const aFollowersAsc = a.followers || 0
          const bFollowersAsc = b.followers || 0
          if (aFollowersAsc !== bFollowersAsc) {
            return aFollowersAsc - bFollowersAsc
          }
          const aListenersAsc = Number.parseInt(a.listeners || '0', 10)
          const bListenersAsc = Number.parseInt(b.listeners || '0', 10)
          return aListenersAsc - bListenersAsc
        case 'ratio-desc':
          const aRatio = (a.listeners && a.playcount)
            ? Number.parseInt(a.playcount, 10) / Number.parseInt(a.listeners, 10)
            : 0
          const bRatio = (b.listeners && b.playcount)
            ? Number.parseInt(b.playcount, 10) / Number.parseInt(b.listeners, 10)
            : 0
          return bRatio - aRatio
        default:
          return 0
      }
    })

    // Update slot map with sorted order (maintain slot positions but reorder)
    slotMap.value = {}
    sortedArtists.forEach((artist, index) => {
      slotMap.value[index] = artist
    })

    displayedArtists.value = sortedArtists
    console.log(`[DISPLAY] Showing ${displayedArtists.value.length} artists (sorted by ${sortBy.value})`)
  }

  // Set initial load complete when artists are first displayed
  if (displayedArtists.value.length > 0 && !initialLoadComplete.value) {
    setTimeout(() => {
      initialLoadComplete.value = true
    }, 100)
  }
}

// Sorting and filtering
const sortArtists = () => {
  console.log(`[SORTING] Sort changed to: ${sortBy.value}`)
  updateDisplayedArtists()
}

const onSortChange = async () => {
  sortArtists()
  // Load listeners data for displayed artists
  await loadPageListenersCounts()
}

// Helper functions for dropdown
const getSortLabel = (value: string): string => {
  const option = sortOptions.find(opt => opt.value === value)
  return option ? option.label : 'Best Matches'
}

const selectSort = (value: string) => {
  sortBy.value = value
  dropdownOpen.value = false
  onSortChange()
}

// Sort dropdown functions (matching SoundCloudScreen.vue)
const toggleLikesRatioDropdown = () => {
  showLikesRatioDropdown.value = !showLikesRatioDropdown.value
}

const hideLikesRatioDropdown = () => {
  setTimeout(() => {
    showLikesRatioDropdown.value = false
  }, 150) // Small delay to allow click events to register
}

const setLikesRatioFilter = (type: string) => {
  sortBy.value = type
  showLikesRatioDropdown.value = false
  // No need to call onSortChange() - sortArtists() will handle per-page sorting
  sortArtists()
  console.log(`[SORT] Changed to ${type} - will apply to current page only`)
}

const getSortIcon = () => {
  switch (sortBy.value) {
    case 'listeners-desc': return faArrowUp
    case 'listeners-asc': return faArrowUp // Will be rotated in CSS if needed
    case 'ratio-desc': return faArrowUp
    default: return faFilter // match (best matches)
  }
}

const getSortText = () => {
  switch (sortBy.value) {
    case 'listeners-desc': return 'Most Followers'
    case 'listeners-asc': return 'Least Followers'
    case 'ratio-desc': return 'Best Ratio'
    default: return 'Best Matches'
  }
}

const getSortIconForOption = (optionValue: string) => {
  switch (optionValue) {
    case 'listeners-desc': return faArrowUp
    case 'listeners-asc': return faArrowUp
    case 'ratio-desc': return faArrowUp
    default: return faFilter
  }
}

// Close dropdown when clicking outside
const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as HTMLElement

  // Close sort dropdown if clicking outside of it
  if (!target.closest('.relative')) {
    dropdownOpen.value = false
  }

  // Close search dropdown if clicking outside of search container
  if (searchContainer.value && !searchContainer.value.contains(target)) {
    searchResults.value = []
  }
}

// Load banned artists from localStorage (Similar Artists only)
const loadBannedArtists = () => {
  try {
    const savedBanned = localStorage.getItem('koel-similar-banned-artists')
    if (savedBanned) {
      const bannedArray = JSON.parse(savedBanned)
      bannedArtists.value = new Set(bannedArray)
    }
  } catch (error) {
    console.error('Failed to load banned artists:', error)
  }
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
    }

    // Load saved tracks
    const savedTracksResponse = await http.get('music-preferences/saved-tracks')
    if (savedTracksResponse.success && savedTracksResponse.data) {
      savedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        savedTracks.value.add(trackKey)
      })
    }
  } catch (error) {
    // Could not load user preferences (user may not be logged in)
  }
}

onMounted(async () => {
  document.addEventListener('click', handleClickOutside)
  loadBannedArtists()
  loadClientUnsavedTracks()
  await loadUserPreferences()
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
})

// Close Spotify previews when navigating away from this screen
onRouteChanged(async route => {
  if (route.screen !== 'SimilarArtists') {
    // Close any open preview
    if (currentlyPreviewingArtist.value) {
      const currentArtist = displayedArtists.value.find(a => a.name === currentlyPreviewingArtist.value)
      if (currentArtist) {
        closePreview(currentArtist)
      }
    }
    currentlyPreviewingArtist.value = null
  } else {
    // Check for seed artist data when entering Similar Artists screen
    // Use a simple inline function to avoid scoping issues
    const handleSeedArtist = async () => {
      try {
        const seedArtistJson = localStorage.getItem('koel-similar-artists-seed-artist')
        if (seedArtistJson) {
          const seedArtistData = JSON.parse(seedArtistJson)
          console.log('🔍 [SIMILAR ARTISTS] Found seed artist data:', seedArtistData, 'at', new Date().toISOString())

          // Clear the data so it doesn't trigger again
          localStorage.removeItem('koel-similar-artists-seed-artist')

          // Check if data is recent (within last 30 seconds)
          const isRecent = Date.now() - seedArtistData.timestamp < 30000
          if (isRecent && seedArtistData.name) {
            console.log('🔍 [SIMILAR ARTISTS] Setting up seed artist from saved tracks - DIRECT MODE')

            // Search for the artist first to get the MBID
            searchQuery.value = seedArtistData.name
            await searchArtists()

            // Find exact match in search results to get the MBID
            const exactMatch = searchResults.value.find(artist =>
              artist.name.toLowerCase() === seedArtistData.name.toLowerCase(),
            )

            if (exactMatch && exactMatch.mbid) {
              console.log('🔍 [SIMILAR ARTISTS] Found artist with MBID, setting directly:', exactMatch.name)

              // Set the artist directly without showing search dropdown
              selectedArtist.value = exactMatch
              searchQuery.value = '' // Clear search box
              searchResults.value = [] // Clear search dropdown

              // Clear previous results
              similarArtists.value = []
              filteredArtists.value = []
              displayedArtists.value = []
              currentlyPreviewingArtist.value = null
              errorMessage.value = ''

              // Automatically find similar artists
              await findSimilarArtists(exactMatch)

              console.log('🔍 [SIMILAR ARTISTS] Direct setup complete - similarArtists count:', similarArtists.value.length)
            } else if (searchResults.value.length > 0 && searchResults.value[0].mbid) {
              // If no exact match, use first result with MBID
              const firstResult = searchResults.value[0]
              console.log('🔍 [SIMILAR ARTISTS] No exact match, using first result with MBID:', firstResult.name)

              // Set the artist directly without showing search dropdown
              selectedArtist.value = firstResult
              searchQuery.value = '' // Clear search box
              searchResults.value = [] // Clear search dropdown

              // Clear previous results
              similarArtists.value = []
              filteredArtists.value = []
              displayedArtists.value = []
              currentlyPreviewingArtist.value = null
              errorMessage.value = ''

              // Automatically find similar artists
              await findSimilarArtists(firstResult)

              console.log('🔍 [SIMILAR ARTISTS] Direct setup complete - similarArtists count:', similarArtists.value.length)
            } else {
              console.log('🔍 [SIMILAR ARTISTS] No artist found with MBID for:', seedArtistData.name)
              // Show error message that similarity search requires artists with database IDs
              errorMessage.value = `Sorry, "${seedArtistData.name}" doesn't have the required music database ID for similarity search. Try searching manually for a more specific artist name.`
            }
          } else {
            console.log('🔍 [SIMILAR ARTISTS] Seed artist data too old or invalid')
          }
        }
      } catch (error) {
        console.error('🔍 [SIMILAR ARTISTS] Failed to parse seed artist data:', error)
      }
    }

    await handleSeedArtist()

    // Enable animations when entering Similar Artists screen
    console.log('🎵 [SIMILAR] Entering Similar Artists screen with', displayedArtists.value.length, 'artists')
    if (displayedArtists.value.length > 0) {
      console.log('🎵 [SIMILAR] Triggering route change animations')
      allowAnimations.value = true
      initialLoadComplete.value = false

      // Disable animations after they complete
      setTimeout(() => {
        allowAnimations.value = false
        initialLoadComplete.value = true
      }, 2000)
    } else {
      console.log('🎵 [SIMILAR] No artists to animate on route change')
    }
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.search-input::placeholder {
  text-align: center;
}

.search-input:focus::placeholder {
  opacity: 0;
}
.similar-artists-screen {
  width: 100%;
  margin: 0;
  padding: 0 2rem;
}

@media (max-width: 768px) {
  .similar-artists-screen {
    padding: 0 1rem;
  }
}

/* Spotify player container */
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
    max-height: 300px;
  }
}

.spotify-track {
  min-height: 120px;
  animation: fadeInUp 0.4s ease-out;
}

.spotify-track audio {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 6px;
  transition: all 0.2s ease;
}

.spotify-track audio:hover {
  background: rgba(255, 255, 255, 0.08);
}

.spotify-track audio::-webkit-media-controls-panel {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 6px;
}

.spotify-track:hover {
  transform: translateY(-2px);
}

/* Clean Spotify oembed styling - match RecommendationsTable size exactly */
.spotify-oembed iframe {
  width: 100% !important;
  height: 80px !important;
  border-radius: 15px !important;
  border: none !important;
  overflow: hidden !important;
  display: block !important;
  margin: 0 !important;
}

.spotify-oembed {
  width: 100%;
  height: 80px;
  overflow: hidden;
  border-radius: 15px;
  position: relative;
  background: transparent;
}

.spotify-embed-container {
  width: 100%;
  min-height: 80px;
  overflow: hidden;
  border-radius: 15px;
}

/* Smooth transitions for images */
img {
  transition: all 0.2s ease;
}

img:hover {
  transform: scale(1.05);
}

/* Loading spinner improvements */
@keyframes spin-smooth {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin-smooth 1s linear infinite;
}

/* Loading dots animation */
.loading-dots .dots::after {
  content: '';
  animation: loading-dots 1.5s infinite;
}

@keyframes loading-dots {
  0% {
    content: '';
  }
  25% {
    content: '.';
  }
  50% {
    content: '..';
  }
  75% {
    content: '...';
  }
  100% {
    content: '';
  }
}

/* Loading spinner for preview button */
.loading-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top: 2px solid white;
  border-radius: 50%;
  animation: spin-preview 0.8s linear infinite;
}

@keyframes spin-preview {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
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

/* Hide scrollbars */
.scrollbar-hide {
  -ms-overflow-style: none !important; /* Internet Explorer 10+ */
  scrollbar-width: none !important; /* Firefox */
  overflow: -moz-scrollbars-none !important; /* Old Firefox */
}

.scrollbar-hide::-webkit-scrollbar {
  display: none !important; /* Safari and Chrome */
  width: 0 !important;
  height: 0 !important;
}

.scrollbar-hide::-webkit-scrollbar-track {
  display: none !important;
}

.scrollbar-hide::-webkit-scrollbar-thumb {
  display: none !important;
}

/* Quick slide-out animation for removed rows */
.row-slide-out {
  transition:
    transform 0.22s ease,
    opacity 0.22s ease;
  transform: translateX(100%);
  opacity: 0;
  will-change: transform, opacity;
}
</style>
