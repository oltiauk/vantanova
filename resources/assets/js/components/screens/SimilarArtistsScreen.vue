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

        <div class="flex items-center justify-between max-w-4xl mx-auto mb-4 px-1">
          <!-- Ban listened tracks toggle -->
          <div class="flex items-center gap-3">
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

          <!-- Sort by Dropdown -->
          <div class="relative">
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
              class="absolute right-0 mt-2 w-52 rounded-lg shadow-lg z-50"
              style="background-color: rgb(67,67,67,255);"
            >
              <button
                v-for="option in sortOptions"
                :key="option.value"
                class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2"
                :class="option.value === 'match' ? 'rounded-t-lg' : (option.value === sortOptions[sortOptions.length - 1].value ? 'rounded-b-lg' : '')"
                :style="sortBy === option.value ? 'background-color: rgb(67,67,67,255)' : ''"
                @mousedown.prevent="setLikesRatioFilter(option.value)"
              >
                <Icon :icon="getSortIconForOption(option.value)" class="text-xs" />
                {{ option.label }}
              </button>
            </div>
          </div>
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
                <template v-for="(artist, index) in displayedArtists" :key="`artist-${artist.mbid || artist.name}`">
                  <!-- Artist Row -->
                  <tr
                    class="hover:bg-white/5 transition h-16 border-b border-white/5"
                    :class="[
                      currentlyPreviewingArtist === artist.name ? 'bg-white/5' : '',
                    ]"
                  >
                    <!-- Index -->
                    <td class="p-3 align-middle">
                      <span class="text-white/60">{{ index + 1 }}</span>
                    </td>

                    <!-- Artist Name -->
                    <td class="p-3 align-middle">
                      <span class="font-medium text-white whitespace-nowrap">
                        {{ artist.name }}
                      </span>
                    </td>

                    <!-- Followers -->
                    <td class="p-3 px-6 align-middle text-right">
                      <span class="text-white/80">{{ formatFollowers(artist.followers || 0) }}</span>
                    </td>

                    <!-- Actions -->
                    <td class="px-4 align-middle">
                      <div class="flex gap-2 justify-end">
                        <button
                          class="px-3 ml-2 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center text-white"
                          title="Find Similar Artists"
                          @click="findSimilarArtists(artist)"
                        >
                          <Icon :icon="faSearch" class="w-4 h-4 mr-2" />
                          <span>Similars</span>
                        </button>
                        <button
                          :disabled="loadingPreviewArtist === artist.name"
                          class="px-3 py-2 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center" :class="[
                            (currentlyPreviewingArtist === artist.name || hasListenedTracks(artist))
                              ? 'bg-[#868685] hover:bg-[#6d6d6d] text-white'
                              : 'bg-[#484948] hover:bg-gray-500 text-white',
                          ]"
                          :title="loadingPreviewArtist === artist.name ? 'Loading...' : (currentlyPreviewingArtist === artist.name ? 'Close preview' : (hasListenedTracks(artist) ? 'Tracks have been listened to' : 'Preview artist tracks'))"
                          @click="previewArtist(artist)"
                        >
                          <!-- Regular icon when not processing -->
                          <img v-if="currentlyPreviewingArtist !== artist.name" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                          <Icon v-else :icon="faTimes" class="w-3 h-3" />
                          <span :class="loadingPreviewArtist === artist.name ? '' : 'ml-1'">{{ loadingPreviewArtist === artist.name ? 'Loading...' : (currentlyPreviewingArtist === artist.name ? 'Close' : (hasListenedTracks(artist) ? 'Listened' : 'Preview')) }}</span>
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Spotify Preview Section -->
                  <tr v-if="currentlyPreviewingArtist === artist.name" class="bg-white/5 border-b border-white/5">
                    <td colspan="8" class="p-0">
                      <div class="spotify-player-container p-6 bg-white/3 relative">
                        <!-- Loading State -->
                        <div v-if="loadingPreviewArtist === artist.name" class="flex items-center justify-center" style="height: 80px;">
                          <div class="flex items-center gap-3">
                            <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-accent border-t-transparent" />
                            <span class="text-k-text-secondary">Loading tracks...</span>
                          </div>
                        </div>

                        <!-- Tracks Display -->
                        <div v-else-if="artist.spotifyTracks && artist.spotifyTracks.length > 0" class="max-w-[51rem] mx-auto">
                          <div
                            v-for="track in artist.spotifyTracks.slice(0, 1)"
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

        <!-- Load More -->
        <div v-if="hasMoreArtists" class="flex items-center justify-center mt-8">
          <button
            class="px-4 py-2 bg-k-accent text-white rounded hover:bg-k-accent/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            @click="loadMore"
          >
            Load More
          </button>
        </div>
      </div>

      <div v-else-if="selectedArtist && !isLoading" class="no-results text-center py-12">
        <div class="text-gray-400">No similar artists found.</div>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
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

const INITIAL_VISIBLE_COUNT = 20
const LOAD_MORE_STEP = 20

// Search state
const searchQuery = ref('')
const searchResults = ref<LastfmArtist[]>([])
const searchLoading = ref(false)
const searchTimeout = ref<ReturnType<typeof setTimeout>>()
const searchSuggestionTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const searchContainer = ref<HTMLElement | null>(null)
const initialLoadComplete = ref(false)
const allowAnimations = ref(false)

// Selected artist and results
const selectedArtist = ref<LastfmArtist | null>(null)
const similarArtists = ref<LastfmArtist[]>([])
const displayedArtists = ref<LastfmArtist[]>([])
const visibleCount = ref(INITIAL_VISIBLE_COUNT)

// Loading states
const isLoading = ref(false)
const errorMessage = ref('')

// Preview management
const currentlyPreviewingArtist = ref<string | null>(null)
const loadingPreviewArtist = ref<string | null>(null)

// Track which tracks have been listened to (previewed)
const listenedTracks = ref(new Set<string>())
const banListenedTracks = ref(false)
const pendingAutoBannedTracks = ref(new Set<string>())

// Track locally hidden artists (session-only, not global ban)
const locallyHiddenArtists = ref(new Set<string>())

// Helper functions
const getTrackKey = (track: SpotifyTrack): string => {
  const artist = track.artists?.[0]?.name || 'Unknown'
  return `${artist}-${track.name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Gather all known tracks (visible and cached) for bulk operations
const getKnownTracks = (): SpotifyTrack[] => {
  const tracks: SpotifyTrack[] = []
  displayedArtists.value.forEach(artist => {
    if (artist.allSpotifyTracks) {
      tracks.push(...artist.allSpotifyTracks)
    }
    if (artist.spotifyTracks) {
      tracks.push(...artist.spotifyTracks)
    }
  })
  return tracks
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
  { value: 'listeners-desc', label: 'Most Followers' },
  { value: 'listeners-asc', label: 'Least Followers' },
]

const sortedArtists = computed(() => {
  const artists = [...similarArtists.value]

  switch (sortBy.value) {
    case 'listeners-desc':
      return artists.sort((a, b) => {
        const aFollowers = a.followers || 0
        const bFollowers = b.followers || 0
        if (aFollowers !== bFollowers) {
          return bFollowers - aFollowers
        }
        const aListeners = Number.parseInt(a.listeners || '0', 10)
        const bListeners = Number.parseInt(b.listeners || '0', 10)
        return bListeners - aListeners
      })
    case 'listeners-asc':
      return artists.sort((a, b) => {
        const aFollowers = a.followers || 0
        const bFollowers = b.followers || 0
        if (aFollowers !== bFollowers) {
          return aFollowers - bFollowers
        }
        const aListeners = Number.parseInt(a.listeners || '0', 10)
        const bListeners = Number.parseInt(b.listeners || '0', 10)
        return aListeners - bListeners
      })
    case 'ratio-desc':
      return artists.sort((a, b) => {
        const aRatio = (a.listeners && a.playcount)
          ? Number.parseInt(a.playcount, 10) / Number.parseInt(a.listeners, 10)
          : 0
        const bRatio = (b.listeners && b.playcount)
          ? Number.parseInt(b.playcount, 10) / Number.parseInt(b.listeners, 10)
          : 0
        return bRatio - aRatio
      })
    default:
      return artists
  }
})

const applyDisplayedArtists = () => {
  visibleCount.value = Math.min(visibleCount.value, sortedArtists.value.length)
  displayedArtists.value = sortedArtists.value.slice(0, visibleCount.value)
}

const hasMoreArtists = computed(() => visibleCount.value < sortedArtists.value.length)
const remainingArtistsCount = computed(() => Math.max(sortedArtists.value.length - visibleCount.value, 0))

const loadMore = () => {
  visibleCount.value = Math.min(sortedArtists.value.length, visibleCount.value + LOAD_MORE_STEP)
  applyDisplayedArtists()
}

watch([sortedArtists, visibleCount], applyDisplayedArtists, { immediate: true })

// Auto-ban already listened tracks when the toggle is turned on
watch(banListenedTracks, async (newValue, oldValue) => {
  if (newValue && !oldValue) {
    const tracksToBan = getKnownTracks().filter(track => {
      const trackKey = getTrackKey(track)
      return listenedTracks.value.has(trackKey) && !isTrackBanned(track)
    })

    for (const track of tracksToBan) {
      await autoBlacklistListenedTrack(track)
    }
  }
})

// Clear dropdown when user types
const onSearchInput = () => {
  // Clear search results when user edits the query
  searchResults.value = []

  if (searchSuggestionTimer.value) {
    clearTimeout(searchSuggestionTimer.value)
  }

  if (searchQuery.value.trim()) {
    searchSuggestionTimer.value = setTimeout(() => {
      searchArtists()
    }, 2000)
  }
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
  if (searchSuggestionTimer.value) {
    clearTimeout(searchSuggestionTimer.value)
    searchSuggestionTimer.value = null
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
  displayedArtists.value = []
  visibleCount.value = INITIAL_VISIBLE_COUNT
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
  displayedArtists.value = []
  visibleCount.value = INITIAL_VISIBLE_COUNT
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

    try {
      window.dispatchEvent(new CustomEvent('track-unsaved', {
        detail: { track, trackKey }
      }))
    } catch (e) {
      // ignore dispatch errors
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

        try {
          window.dispatchEvent(new CustomEvent('track-saved', {
            detail: { track, trackKey }
          }))
        } catch (e) {
          // ignore dispatch errors
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

// Auto-blacklist a track when the ban listened toggle is enabled
const autoBlacklistListenedTrack = async (track: SpotifyTrack) => {
  const trackKey = getTrackKey(track)
  const identifier = track.id || trackKey

  if (isTrackBanned(track) || pendingAutoBannedTracks.value.has(identifier)) {
    return
  }

  pendingAutoBannedTracks.value.add(identifier)
  pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)

  try {
    const response = await http.post('music-preferences/blacklist-track', {
      isrc: track.id,
      track_name: track.name,
      artist_name: track.artists?.[0]?.name || 'Unknown'
    })

    if (response.success) {
      blacklistedTracks.value.add(trackKey)
      refreshCurrentPreview()
      try {
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      } catch {}
    }
  } catch (error) {
    console.warn('Failed to auto-ban listened track:', error)
  } finally {
    pendingAutoBannedTracks.value.delete(identifier)
    pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)
  }
}

const banTrack = async (track: SpotifyTrack) => {
  const trackKey = getTrackKey(track)
  const trackIdentifier = track.id || trackKey

  if (isTrackBanned(track)) {
    // Update UI immediately for better UX
    blacklistedTracks.value.delete(trackKey)
    pendingAutoBannedTracks.value.delete(trackIdentifier)
    pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)

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
        pendingAutoBannedTracks.value.delete(trackIdentifier)
        pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)

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

      // Load followers/listeners count for ALL artists before sorting and displaying
      console.log('📊 [FRONTEND] Loading followers for ALL artists before sorting', {
        totalArtists: artistsWithId.length,
      })
      await loadAllArtistsFollowers(artistsWithId)

      // Sort by followers (desc) with listeners as tiebreaker for default view
      const defaultSorted = [...artistsWithId].sort((a, b) => {
        const aFollowers = a.followers || 0
        const bFollowers = b.followers || 0
        if (aFollowers === bFollowers) {
          const aListeners = Number.parseInt(a.listeners || '0', 10)
          const bListeners = Number.parseInt(b.listeners || '0', 10)
          return bListeners - aListeners
        }
        return bFollowers - aFollowers
      })

      // Store full list and reset visible count
      similarArtists.value = defaultSorted
      visibleCount.value = Math.min(INITIAL_VISIBLE_COUNT, similarArtists.value.length)
      applyDisplayedArtists()

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
    displayedArtists.value = []
    visibleCount.value = INITIAL_VISIBLE_COUNT
  } finally {
    isLoading.value = false
  }
}

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

  if (banListenedTracks.value) {
    autoBlacklistListenedTrack(track)
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

// Sorting and filtering
const sortArtists = () => {
  console.log(`[SORTING] Sort changed to: ${sortBy.value}`)
  applyDisplayedArtists()
}

const onSortChange = async () => {
  sortArtists()
}

// Helper functions for dropdown
const getSortLabel = (value: string): string => {
  const option = sortOptions.find(opt => opt.value === value)
  return option ? option.label : ''
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
    default: return faFilter // match (best matches)
  }
}

const getSortText = () => {
  switch (sortBy.value) {
    case 'listeners-desc': return 'Most Followers'
    case 'listeners-asc': return 'Least Followers'
    default: return ''
  }
}

const getSortIconForOption = (optionValue: string) => {
  switch (optionValue) {
    case 'listeners-desc': return faArrowUp
    case 'listeners-asc': return faArrowUp
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
              displayedArtists.value = []
              visibleCount.value = INITIAL_VISIBLE_COUNT
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
              displayedArtists.value = []
              visibleCount.value = INITIAL_VISIBLE_COUNT
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
  if (searchSuggestionTimer.value) {
    clearTimeout(searchSuggestionTimer.value)
    searchSuggestionTimer.value = null
  }
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
</style>
