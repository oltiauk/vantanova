<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader header-image="/VantaNova-Logo.svg" />
    </template>

    <div class="artist-watchlist-screen">
      <div class="flex flex-col gap-4 md:flex-row md:items-center">
        <div class="flex-1">
          <div class="rounded-lg p-4">
            <div class="max-w-[39rem] mx-auto">
              <div ref="searchContainer" class="relative">
                <div class="flex">
                  <input
                    v-model="searchQuery"
                    type="text"
                    class="flex-1 py-3 pl-4 pr-4 bg-white/10 rounded-l-lg focus:outline-none text-white text-lg search-input text-center placeholder:text-center"
                    placeholder="Add an artist in the watchlist"
                    @keydown.enter="handleSearchButtonClick"
                    @input="onSearchInput"
                  >
                  <button
                    class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 text-white rounded-r-lg transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="(isSearching || isFetchingReleases) || (!searchQuery.trim() && watchlist.length === 0)"
                    @click="handleSearchButtonClick"
                  >
                    <Icon v-if="!isFetchingReleases" :icon="faSearch" class="w-5 h-5" />
                    <span v-else class="text-sm">Fetching…</span>
                  </button>
                </div>

                <!-- Loading Animation -->
                <div
                  v-if="isSearching && searchQuery.trim()"
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
                  v-if="searchResults.length > 0 && !isSearching"
                  class="absolute z-50 w-full border border-k-border rounded-lg mt-1 shadow-xl"
                  style="background-color: #302f30;"
                >
                  <div class="max-h-80 rounded-lg overflow-hidden overflow-y-auto">
                    <div v-for="(artist, index) in searchResults.slice(0, 10)" :key="`suggestion-${artist.id}-${index}`">
                      <div
                        class="flex items-center justify-between px-4 py-3 hover:bg-white/10 cursor-pointer transition-colors group border-b border-k-border/30 last:border-b-0"
                        @click="addArtist(artist)"
                      >
                        <!-- Artist Info -->
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                          <div class="flex-1 min-w-0">
                            <div class="font-medium text-k-text-primary group-hover:text-gray-200 transition-colors truncate">
                              {{ artist.name }}
                            </div>
                            <div v-if="artist.followers" class="text-xs text-k-text-tertiary">
                              {{ formatNumber(artist.followers) }} followers
                            </div>
                          </div>
                        </div>
                        <span class="font-medium text-k-text-primary group-hover:text-gray-200 transition-colors text-sm">Follow</span>
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

      <div
        v-if="notification"
        class="rounded-lg border border-red-500/30 bg-red-500/10 text-red-100 px-4 py-3 text-sm mt-6"
      >
        {{ notification }}
      </div>

      <!-- Info Message -->
      <div class="text-center mt-6">
        <p class="text-k-text-secondary text-sm">
          Follow artists from the Saved Tracks section, or add them here
        </p>
      </div>

      <div class="flex flex-col gap-6 lg:flex-row">
        <div class="bg-white/5 rounded-xl p-5 w-full lg:w-72 flex flex-col">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-white">Followed Artists</h2>
            <span class="text-sm text-white/60">{{ watchlist.length }}/{{ watchlistLimit }}</span>
          </div>
          <div v-if="watchlist.length" class="space-y-2 overflow-y-auto pr-1" style="max-height: 520px;">
            <div
              v-for="artist in watchlist"
              :key="artist.artist_id"
              class="flex items-center justify-between gap-2 px-3 py-2 bg-white/5 rounded-lg"
            >
              <p class="text-white text-sm truncate">{{ artist.artist_name }}</p>
              <button
                class="text-white/60 hover:text-white transition"
                title="Remove artist"
                @click="removeArtist(artist)"
              >
                <Icon :icon="faTimes" class="w-4 h-4" />
              </button>
            </div>
          </div>
          <p v-else class="text-sm text-white/60">
            No artists followed yet. Use the search above to add your first artist.
          </p>
        </div>

        <div class="flex-1 bg-white/5 rounded-xl">
          <div class="flex items-center justify-between px-6 pt-6 pb-4">
            <div class="flex items-center gap-4">
              <h3 class="text-lg font-semibold text-white">Released this month</h3>
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
            </div>
            <button
              class="flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isFetchingReleases || watchlist.length === 0"
              title="Refresh releases"
              @click="fetchReleases()"
            >
              <Icon :icon="faSync" class="w-4 h-4" :class="{ 'animate-spin': isFetchingReleases }" />
              <span>Refresh</span>
            </button>
          </div>

          <div class="overflow-x-auto scrollbar-hide">
            <table class="w-full">
              <thead>
                <tr class="border-b border-white/10 text-white/80">
                  <th class="text-left px-4 py-4 font-medium w-12">#</th>
                  <th class="text-left px-4 py-4 font-medium">Artist</th>
                  <th class="text-left px-8 py-4 font-medium">Title</th>
                  <th class="text-left px-2 py-4 font-medium w-28">Release Date</th>
                  <th class="text-center pr-3 py-4 font-medium whitespace-nowrap" />
                </tr>
              </thead>
              <tbody>
                <tr v-if="isFetchingReleases" class="text-center">
                  <td colspan="5" class="py-8 text-white/70">Fetching latest releases…</td>
                </tr>
                <tr v-else-if="releases.length === 0" class="text-center">
                  <td colspan="5" class="py-8 text-white/70">No recent releases found. Try refreshing or adding more artists.</td>
                </tr>
                <template v-else>
                  <template v-for="(release, index) in paginatedReleases" :key="getReleaseKey(release, index)">
                    <tr class="border-b border-white/5 hover:bg-white/5 transition">
                      <td class="px-4 py-4 text-white/70">{{ (currentPage - 1) * releasesPerPage + index + 1 }}</td>
                      <td class="px-4 py-4">
                        <p class="text-white font-medium">{{ release.artist_name }}</p>
                      </td>
                      <td class="px-8 py-4">
                        <span class="text-white font-medium">
                          {{ release.release_title || release.track_title }}
                          <span v-if="release.track_count && release.track_count > 1" class="text-white/50 text-xs ml-1">({{ release.track_count }} tracks)</span>
                        </span>
                        <p v-if="release.track_title !== release.release_title" class="text-xs text-white/60">{{ release.track_title }}</p>
                      </td>
                      <td class="px-2 py-4 text-white/80 w-28">
                        {{ formatDate(release.release_date) }}
                      </td>
                      <td class="pr-3 py-4 align-middle">
                        <div class="flex gap-2 justify-end">
                          <button
                            :disabled="release.isSaved"
                            :class="release.isSaved
                              ? 'bg-green-500 hover:bg-green-600 text-white cursor-default'
                              : 'bg-[#484948] hover:bg-gray-500 text-white'"
                            class="h-[34px] w-[34px] rounded text-sm font-medium transition flex items-center justify-center"
                            :title="release.isSaved ? 'Saved' : 'Save track'"
                            @click="saveTrack(release, index)"
                          >
                            <Icon :icon="faHeart" class="text-sm" />
                          </button>
                          <button
                            :class="release.isBanned
                              ? 'bg-red-500 hover:bg-red-600 text-white'
                              : 'bg-[#484948] hover:bg-gray-500 text-white'"
                            class="h-[34px] w-[34px] rounded text-sm font-medium transition flex items-center justify-center"
                            :title="release.isBanned ? 'Unban track' : 'Ban track'"
                            @click="banTrack(release, index)"
                          >
                            <Icon :icon="faBan" class="text-sm" />
                          </button>
                          <button
                            :disabled="!hasValidSeed(release)"
                            class="pr-3 ml-4 py-2 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center bg-[#484948] hover:bg-gray-500 text-white"
                            @click="viewRelatedTracks(release)"
                          >
                            <Icon :icon="faSearch" class="w-4 h-4 mr-2" />
                            <span>Related</span>
                          </button>
                          <button
                            :disabled="processingRelease === getReleaseKey(release, index)"
                            class="px-3 py-2 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center"
                            :class="[
                              (expandedReleaseKey === getReleaseKey(release, index) || isReleaseListened(release))
                                ? 'bg-[#868685] hover:bg-[#6d6d6d] text-white'
                                : 'bg-[#484948] hover:bg-gray-500 text-white'
                            ]"
                            :title="expandedReleaseKey === getReleaseKey(release, index) ? 'Close preview' : (isReleaseListened(release) ? 'Tracks have been listened to' : 'Preview release')"
                            @click="togglePreview(release, index)"
                          >
                            <img v-if="expandedReleaseKey !== getReleaseKey(release, index)" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                            <Icon v-else :icon="faTimes" class="w-3 h-3" />
                            <span>{{ expandedReleaseKey === getReleaseKey(release, index) ? 'Close' : (isReleaseListened(release) ? 'Listened' : 'Preview') }}</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="expandedReleaseKey === getReleaseKey(release, index)" class="bg-white/5 border-b border-white/5">
                      <td colspan="5" class="p-0">
                        <div class="spotify-player-container p-6 bg-white/3 relative">
                          <div
                            v-if="release.spotify_album_id || release.spotify_track_id"
                            class="flex items-center justify-center"
                            :style="release.is_single_track ? 'min-height:80px;' : 'min-height:152px;'"
                          >
                            <iframe
                              :key="`${expandedReleaseKey}-${release.spotify_album_id || release.spotify_track_id}`"
                              class="w-full max-w-6xl rounded-xl spotify-embed"
                              :src="release.spotify_album_id && (!release.spotify_track_id || !release.is_single_track)
                                ? `https://open.spotify.com/embed/album/${release.spotify_album_id}?utm_source=generator&theme=0`
                                : `https://open.spotify.com/embed/track/${release.spotify_track_id}?utm_source=generator&theme=0`"
                              :style="release.is_single_track
                                ? 'height: 80px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);'
                                : 'height: 152px; border-radius: 15px; background-color: rgba(255, 255, 255, 0.05);'"
                              frameborder="0"
                              allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                              loading="lazy"
                              @load="(event) => { event.target.style.opacity = '1' }"
                            />
                          </div>
                          <p v-else class="text-white/70 text-sm py-6 text-center">
                            No preview available for this release.
                          </p>
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
                </template>
              </tbody>
            </table>
          </div>
          <div
            v-if="releases.length > releasesPerPage"
            class="flex items-center justify-end gap-3 px-6 py-4 border-t border-white/5"
          >
            <button
              class="px-3 py-2 rounded bg-white/10 hover:bg-white/20 text-white text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="currentPage === 1"
              @click="goToPage(currentPage - 1)"
            >
              Previous
            </button>
            <span class="text-white/70 text-sm">Page {{ currentPage }} / {{ totalPages }}</span>
            <button
              class="px-3 py-2 rounded bg-white/10 hover:bg-white/20 text-white text-sm disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="currentPage >= totalPages"
              @click="goToPage(currentPage + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { faBan, faHeart, faMusic, faSearch, faSync, faTimes } from '@fortawesome/free-solid-svg-icons'

// @ts-ignore - Vue SFC type resolution handled via Vite aliases
import ScreenBase from '@/components/screens/ScreenBase.vue'
// @ts-ignore - Vue SFC type resolution handled via Vite aliases
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
// @ts-ignore - Axios wrapper resolved via Vite aliases
import { http } from '@/services/http'

interface WatchlistArtist {
  id?: number
  artist_id: string
  artist_name: string
  artist_image_url?: string | null
  followers?: number | null
}

interface ArtistSearchResult {
  id: string
  name: string
  image?: string
  followers?: number
}

interface WatchlistRelease {
  artist_id: string
  artist_name: string
  artist_image_url?: string | null
  followers?: number | null
  label?: string | null
  popularity?: number | null
  release_type: string
  release_title: string
  release_name?: string
  track_title: string
  track_count?: number
  is_single_track?: boolean
  isrc?: string | null
  isSaved?: boolean
  isBanned?: boolean
  release_date: string
  share_url?: string
  preview_url?: string
  spotify_track_id?: string | null
  spotify_album_id?: string | null
  spotify_artist_id?: string
  embed_id?: string | null
  embed_type: 'track' | 'album'
  is_listened?: boolean
  isListened?: boolean
}

type WatchlistMutationAction = 'added' | 'removed'

interface WatchlistEventDetail {
  action: WatchlistMutationAction
  artist: WatchlistArtist
}

const WATCHLIST_EVENT = 'artist-watchlist-updated' as const

const watchlist = ref<WatchlistArtist[]>([])
const watchlistLimit = ref(30)
const releases = ref<WatchlistRelease[]>([])
const searchResults = ref<ArtistSearchResult[]>([])
const searchQuery = ref('')
const debouncedQuery = ref('')
const isSearching = ref(false)
const isFetchingReleases = ref(false)
const cooldownSeconds = ref(0)
const lastUpdated = ref<string | null>(null)
const expandedReleaseKey = ref<string | null>(null)
const listenedTracks = ref(new Set<string>())
const banListenedTracks = ref(false)
const pendingAutoBannedTracks = ref(new Set<string>())
const sessionBannedReleases = ref(new Set<string>())
const notification = ref<string | null>(null)
const autoRefreshRequested = ref(false)
const currentPage = ref(1)
const releasesPerPage = 20
const processingRelease = ref<string | null>(null)
const searchContainer = ref<HTMLElement | null>(null)

let searchDebounce: number | undefined
let notificationTimeout: number | undefined
let autoRefreshTimer: number | undefined

const sortWatchlist = (entries: WatchlistArtist[]) => {
  return [...entries].sort((a, b) => a.artist_name.localeCompare(b.artist_name))
}

const normalizeWatchlistArtist = (artist: WatchlistArtist): WatchlistArtist => ({
  id: artist.id,
  artist_id: artist.artist_id,
  artist_name: artist.artist_name,
  artist_image_url: artist.artist_image_url ?? null,
  followers: artist.followers ?? null,
})

const applyWatchlistMutation = (action: WatchlistMutationAction, artist: WatchlistArtist) => {
  const normalized = normalizeWatchlistArtist(artist)
  const existingIndex = watchlist.value.findIndex(entry => entry.artist_id === normalized.artist_id)

  if (action === 'added') {
    if (existingIndex === -1) {
      watchlist.value = sortWatchlist([...watchlist.value, normalized])
    } else {
      watchlist.value.splice(existingIndex, 1, { ...watchlist.value[existingIndex], ...normalized })
    }
  } else {
    if (existingIndex !== -1) {
      watchlist.value.splice(existingIndex, 1)
    }
    releases.value = releases.value.filter(release => release.artist_id !== normalized.artist_id)
    if (watchlist.value.length === 0) {
      releases.value = []
    }
  }

  cooldownSeconds.value = 0
  lastUpdated.value = null
}

const emitWatchlistEvent = (action: WatchlistMutationAction, artist: WatchlistArtist) => {
  window.dispatchEvent(new CustomEvent<WatchlistEventDetail>(WATCHLIST_EVENT, {
    detail: {
      action,
      artist: normalizeWatchlistArtist(artist),
    },
  }))
}

const handleWatchlistUpdated = (event: Event) => {
  const detail = (event as CustomEvent<WatchlistEventDetail>).detail
  if (!detail?.artist) {
    return
  }
  applyWatchlistMutation(detail.action, detail.artist)
}

const attemptReleaseRefresh = (force = true) => {
  if (watchlist.value.length === 0) {
    autoRefreshRequested.value = false
    return
  }

  if (isFetchingReleases.value) {
    autoRefreshRequested.value = true
    return
  }

  autoRefreshRequested.value = false
  fetchReleases(force)
}

const scheduleAutoRefresh = () => {
  if (autoRefreshTimer) {
    clearTimeout(autoRefreshTimer)
  }
  autoRefreshTimer = window.setTimeout(() => {
    autoRefreshTimer = undefined
    attemptReleaseRefresh(true)
  }, 600)
}

const totalPages = computed(() => {
  if (releases.value.length === 0) {
    return 1
  }
  return Math.max(1, Math.ceil(releases.value.length / releasesPerPage))
})

const paginatedReleases = computed(() => {
  const start = (currentPage.value - 1) * releasesPerPage
  const end = start + releasesPerPage
  return releases.value.slice(start, end)
})

const goToPage = (page: number) => {
  const clamped = Math.min(Math.max(1, page), totalPages.value)
  currentPage.value = clamped
}

const showSearchDropdown = computed(() => searchQuery.value.length >= 2 && searchResults.value.length > 0 && !isSearching.value)

const showNotification = (message: string) => {
  notification.value = message
  if (notificationTimeout) {
    clearTimeout(notificationTimeout)
  }
  notificationTimeout = window.setTimeout(() => {
    notification.value = null
  }, 5000)
}

const loadWatchlist = async () => {
  try {
    const response = await http.get<{ success: boolean, data: WatchlistArtist[], limit: number }>('music-preferences/artist-watchlist')
    if (response.success) {
      watchlist.value = sortWatchlist(response.data)
      watchlistLimit.value = response.limit
    }
  } catch (error) {
    console.error('Failed to load artist watchlist:', error)
  }
}

const loadListenedTracks = async () => {
  try {
    const resp: any = await http.get('music-preferences/listened-tracks')
    if (resp?.success && Array.isArray(resp.data)) {
      listenedTracks.value = new Set(resp.data as string[])
      return
    }
  } catch {}

  try {
    const stored = localStorage.getItem('koel-artist-watchlist-listened-tracks')
    if (stored) {
      const keys: string[] = JSON.parse(stored)
      listenedTracks.value = new Set(keys)
    }
  } catch {}
}

const searchArtists = async (query: string) => {
  isSearching.value = true
  try {
    const response = await http.post<{ success: boolean, data: ArtistSearchResult[] }>('music-preferences/artist-watchlist/search', {
      query,
    })
    if (response.success) {
      searchResults.value = response.data
    } else {
      searchResults.value = []
    }
  } catch (error) {
    console.error('Artist search failed:', error)
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}

const onSearchInput = () => {
  // Clear search results when user edits the query
  // searchResults.value = []
}

const performSearch = () => {
  const query = searchQuery.value.trim()
  if (query.length < 2) {
    searchResults.value = []
    return
  }
  searchArtists(query)
}

const handleSearchButtonClick = () => {
  const query = searchQuery.value.trim()
  if (query.length >= 2) {
    // If there's a search query, perform the search
    performSearch()
  } else if (watchlist.value.length > 0) {
    // If no search query but there are artists in watchlist, fetch releases
    fetchReleases()
  }
}

const fetchReleases = async (force = false) => {
  if (watchlist.value.length === 0) {
    releases.value = []
    cooldownSeconds.value = 0
    return
  }

  isFetchingReleases.value = true
  try {
    const response = await http.post<{
      success: boolean
      data: WatchlistRelease[]
      cached: boolean
      cooldown_seconds: number
      last_executed_at?: string
      api_error?: boolean
      message?: string
      api_partial_failure?: boolean
      failed_artists?: number
    }>('music-preferences/artist-watchlist/releases', {
      force_refresh: force,
    })

    if (response.success) {
      // Build a map of previous states using the same identifier logic
      const previousStates = new Map<string, { isSaved?: boolean, isBanned?: boolean, isListened?: boolean }>()
      releases.value.forEach(rel => {
        const key = getReleaseIdentifier(rel)
        previousStates.set(key, { isSaved: rel.isSaved, isBanned: rel.isBanned, isListened: rel.isListened ?? rel.is_listened })
      })

      releases.value = (response.data ?? []).map(item => {
        const stateKey = getReleaseIdentifier(item as WatchlistRelease)
        const previous = previousStates.get(stateKey) || {}

        // Backend now provides is_banned, is_saved, and is_listened flags
        // Priority: previous UI state > backend state > session state
        // This ensures that UI changes persist across refreshes
        const backendBanned = (item as any).is_banned ?? false
        const sessionBanned = sessionBannedReleases.value.has(stateKey)
        const isBanned = previous.isBanned !== undefined
          ? previous.isBanned
          : (backendBanned || sessionBanned || false)

        const backendSaved = (item as any).is_saved ?? false
        const isSaved = previous.isSaved !== undefined
          ? previous.isSaved
          : backendSaved

        const backendListened = (item as any).is_listened ?? false
        const isListened = previous.isListened !== undefined
          ? previous.isListened
          : backendListened

        // Update listened tracks from backend
        if (isListened) {
          listenedTracks.value.add(stateKey)
        }

        const trackCountRaw = item.track_count ?? 1
        const trackCount = Number.isFinite(Number(trackCountRaw)) ? Number(trackCountRaw) : 1
        const isSingleTrack = item.is_single_track ?? (trackCount === 1 || (!!item.spotify_track_id && !item.spotify_album_id))

        return {
          ...item,
          track_count: trackCount,
          is_single_track: isSingleTrack,
          isSaved,
          isBanned,
          isListened,
          is_listened: isListened,
        }
      })
      console.log('🎨 [WATCHLIST] Releases normalized:', releases.value.slice(0, 5).map(rel => ({
        id: getReleaseIdentifier(rel),
        banned: rel.isBanned,
        saved: rel.isSaved,
        backendFlags: {
          is_banned: (rel as any).is_banned,
          is_saved: (rel as any).is_saved,
          is_listened: (rel as any).is_listened
        }
      })))
      cooldownSeconds.value = response.cooldown_seconds ?? 0
      lastUpdated.value = response.last_executed_at ?? null

      // Show warning if API had errors but we're showing cached data
      if (response.api_error && response.message) {
        console.warn('API Error:', response.message)
      }

      // Show warning if some artists failed
      if (response.api_partial_failure && response.failed_artists) {
        console.warn(`Failed to fetch data for ${response.failed_artists} artist(s)`)
      }
    }
  } catch (error: any) {
    console.error('Failed to fetch releases:', error)
    const errorMessage = error.response?.data?.error || 'Unable to fetch new releases at the moment.'
    showNotification(errorMessage)
  } finally {
    isFetchingReleases.value = false
  }
}

const addArtist = async (artist: ArtistSearchResult) => {
  try {
    const response = await http.post<{ success: boolean, data: WatchlistArtist }>('music-preferences/artist-watchlist', {
      artist_id: artist.id,
      artist_name: artist.name,
      artist_image_url: artist.image,
      followers: artist.followers,
    })

    searchQuery.value = ''
    searchResults.value = []
    if (response.success && response.data) {
      applyWatchlistMutation('added', response.data)
      emitWatchlistEvent('added', response.data)
    }
  } catch (error: any) {
    console.error('Failed to add artist to watchlist:', error)
    showNotification(error.response?.data?.error || 'Unable to add artist to watchlist.')
  }
}

const addFirstSearchResult = () => {
  if (searchResults.value.length > 0) {
    addArtist(searchResults.value[0])
  }
}

const removeArtist = async (artist: WatchlistArtist) => {
  try {
    await http.delete(`music-preferences/artist-watchlist/${artist.artist_id}`)
    applyWatchlistMutation('removed', artist)
    emitWatchlistEvent('removed', artist)
  } catch (error) {
    console.error('Failed to remove artist:', error)
    showNotification('Unable to remove artist from watchlist.')
  }
}

const getReleaseKey = (release: WatchlistRelease, index: number): string => {
  return (
    release.spotify_album_id
    || release.spotify_track_id
    || release.isrc
    || `${release.artist_id}-${release.track_title}-${release.release_date}-${index}`
  )
}

const getReleaseIdentifier = (release: WatchlistRelease): string => {
  return release.spotify_track_id
    || release.isrc
    || release.spotify_album_id
    || `${release.artist_id}-${release.track_title}-${release.release_date}`
}

const isReleaseListened = (release: WatchlistRelease): boolean => {
  const identifier = getReleaseIdentifier(release)
  return !!release.isListened || !!release.is_listened || listenedTracks.value.has(identifier)
}

const loadSessionBanned = () => {
  try {
    const stored = localStorage.getItem('koel-artist-watchlist-banned')
    if (stored) {
      sessionBannedReleases.value = new Set(JSON.parse(stored) as string[])
    }
  } catch {}
}

const persistSessionBanned = () => {
  try {
    localStorage.setItem('koel-artist-watchlist-banned', JSON.stringify(Array.from(sessionBannedReleases.value)))
  } catch {}
}

const togglePreview = (release: WatchlistRelease, index: number) => {
  const key = getReleaseKey(release, index)
  const hasAlbum = !!release.spotify_album_id
  const hasTrack = !!release.spotify_track_id

  console.log('🎧 [WATCHLIST] Toggle preview', {
    key,
    track: release.spotify_track_id,
    album: release.spotify_album_id,
    count: release.track_count,
    single: release.is_single_track,
  })

  if (!hasAlbum && !hasTrack) {
    showNotification('No Spotify identifiers available for preview.')
    return
  }

  const isOpening = expandedReleaseKey.value !== key
  expandedReleaseKey.value = expandedReleaseKey.value === key ? null : key

  if (isOpening) {
    markReleaseAsListened(release)
  }
}

const markReleaseAsListened = async (release: WatchlistRelease) => {
  const identifier = getReleaseIdentifier(release)
  release.isListened = true
  release.is_listened = true
  listenedTracks.value.add(identifier)
  listenedTracks.value = new Set(listenedTracks.value)

  try {
    await http.post('music-preferences/listened-track', {
      track_key: identifier,
      track_name: release.track_title,
      artist_name: release.artist_name,
      spotify_id: release.spotify_track_id,
      isrc: release.isrc
    })
  } catch (e) {
    try {
      localStorage.setItem('koel-artist-watchlist-listened-tracks', JSON.stringify(Array.from(listenedTracks.value)))
    } catch {}
  }

  if (banListenedTracks.value) {
    autoBlacklistListenedRelease(release)
  }
}

const autoBlacklistListenedRelease = async (release: WatchlistRelease) => {
  const identifier = getReleaseIdentifier(release)

  if (release.isBanned || pendingAutoBannedTracks.value.has(identifier)) {
    return
  }

  pendingAutoBannedTracks.value.add(identifier)
  pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)

  try {
    const isrcValue = release.isrc || release.spotify_track_id || release.spotify_album_id || identifier

    const response = await http.post('music-preferences/blacklist-track', {
      spotify_id: release.spotify_track_id || release.spotify_album_id,
      isrc: isrcValue,
      track_name: release.track_title,
      artist_name: release.artist_name
    })

    if (response.success) {
      release.isBanned = true
      try {
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      } catch {}
    }
  } catch (error) {
    console.warn('Failed to auto-ban listened release:', error)
  } finally {
    pendingAutoBannedTracks.value.delete(identifier)
    pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)
  }
}

const isValidSpotifyId = (id: string | null | undefined): boolean => {
  if (!id) {
    return false
  }
  return !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id) && id.length > 10
}

const getSeedSpotifyId = (release: WatchlistRelease): string | null => {
  return release.spotify_track_id || release.spotify_album_id || null
}

const hasValidSeed = (release: WatchlistRelease): boolean => {
  return isValidSpotifyId(getSeedSpotifyId(release))
}

const viewRelatedTracks = async (release: WatchlistRelease) => {
  // Check if this is an album (has album_id and multiple tracks)
  const isAlbum = release.spotify_album_id && (release.track_count ?? 1) > 1

  let trackId: string | null = null
  let trackName: string = release.track_title

  // For albums, ALWAYS fetch the first track ID from the album API, never use stored track_id or album_id
  if (isAlbum) {
    if (!release.spotify_album_id) {
      showNotification('No album ID available for this release.')
      return
    }

    // Always fetch the first track from the album to ensure we get the correct first track
    try {
      const response = await http.get<{
        success: boolean
        data?: {
          tracks?: {
            items?: Array<{ id?: string, uri?: string, name?: string }>
          }
        }
      }>(`music-preferences/spotify/album/${release.spotify_album_id}`)

      if (response.success && response.data?.tracks?.items && response.data.tracks.items.length > 0) {
        const firstTrack = response.data.tracks.items[0]
        // Extract track ID from id field or from URI (spotify:track:xxxxx)
        trackId = firstTrack.id || (firstTrack.uri?.match(/spotify:track:([a-zA-Z0-9]+)/)?.[1] ?? null)
        // Extract track name from the first track
        trackName = firstTrack.name || release.track_title
      }
    } catch (error) {
      console.error('Failed to fetch album tracks:', error)
      showNotification('Unable to fetch track information for this album. Please try again later.')
      return
    }

    if (!trackId) {
      showNotification('No track ID available for this album. The album may not have track information available.')
      return
    }
  } else {
    // For singles, use track ID (never use album ID for singles either, as it might be wrong)
    trackId = release.spotify_track_id || null

    if (!trackId) {
      showNotification('No track ID available for this release.')
      return
    }
  }

  // Final validation - ensure we have a valid track ID (not an album ID)
  if (!isValidSpotifyId(trackId)) {
    showNotification('No valid Spotify track ID available for this release.')
    return
  }

  const seedTrackData = {
    id: trackId,
    name: trackName,
    artist: release.artist_name,
    source: 'artistWatchlist',
    timestamp: Date.now(),
  }

  localStorage.setItem('koel-music-discovery-seed-track', JSON.stringify(seedTrackData))
  window.location.hash = '#/discover'
}

const openSpotifyReleasePage = (release: WatchlistRelease) => {
  if (release.spotify_album_id) {
    window.open(`https://open.spotify.com/album/${release.spotify_album_id}`, '_blank')
    return
  }
  if (release.spotify_track_id) {
    window.open(`https://open.spotify.com/track/${release.spotify_track_id}`, '_blank')
  }
}

const saveTrack = async (release: WatchlistRelease, index: number) => {
  // Optimistically update the UI immediately
  release.isSaved = true
  localStorage.setItem('track-saved-timestamp', Date.now().toString())

  // Process in the background
  const key = getReleaseKey(release, index)
  processingRelease.value = key

  try {
    // Check if this is an album (has album_id and multiple tracks)
    const isAlbum = release.spotify_album_id && (release.track_count ?? 1) > 1

    let trackId: string | null = release.spotify_track_id
    let trackName: string = release.track_title
    let isrc: string | null = release.isrc || null

    // For albums, or when we have album_id but no track_id, fetch the first track details from the album API
    if (release.spotify_album_id && !trackId) {
      if (!release.spotify_album_id) {
        release.isSaved = false
        showNotification('No album ID available for this release.')
        processingRelease.value = null
        return
      }

      try {
        const response = await http.get<{
          success: boolean
          data?: {
            tracks?: {
              items?: Array<{
                id?: string
                uri?: string
                name?: string
                external_ids?: { isrc?: string }
              }>
            }
          }
        }>(`music-preferences/spotify/album/${release.spotify_album_id}`)

        if (response.success && response.data?.tracks?.items && response.data.tracks.items.length > 0) {
          const firstTrack = response.data.tracks.items[0]
          // Extract track ID from id field or from URI (spotify:track:xxxxx)
          trackId = firstTrack.id || (firstTrack.uri?.match(/spotify:track:([a-zA-Z0-9]+)/)?.[1] ?? null)
          // Extract track name from the first track
          trackName = firstTrack.name || release.track_title
          // Extract ISRC from the first track
          isrc = firstTrack.external_ids?.isrc || null
        }
      } catch (error) {
        release.isSaved = false
        console.error('Failed to fetch album tracks:', error)
        showNotification('Unable to fetch track information for this album. Please try again later.')
        processingRelease.value = null
        return
      }

      if (!trackId) {
        release.isSaved = false
        showNotification('No track ID available for this album. The album may not have track information available.')
        processingRelease.value = null
        return
      }
    }

    // Fallback: try embed_id when no track ID present
    if (!trackId && release.embed_type === 'track' && release.embed_id) {
      trackId = release.embed_id
    }

    // Must have at least a Spotify ID or ISRC for backend validation
    if (!trackId && !isrc) {
      release.isSaved = false
      showNotification('Cannot save track: missing identifiers (Spotify ID/ISRC).')
      processingRelease.value = null
      return
    }

    const payload = {
      spotify_id: trackId,
      isrc,
      track_name: trackName,
      artist_name: release.artist_name,
      label: release.label,
      popularity: release.popularity,
      followers: release.followers,
      release_date: release.release_date,
      preview_url: release.preview_url,
      track_count: release.track_count ?? 1,
      is_single_track: release.is_single_track !== false,
      album_id: release.spotify_album_id ?? null,
    }

    const saveResponse = await http.post('music-preferences/save-track', payload)

    if (!saveResponse.success) {
      throw new Error(saveResponse.error || 'Failed to save track')
    }

    // Dispatch event to update sidebar count
    window.dispatchEvent(new CustomEvent('track-saved'))
  } catch (error: any) {
    // Revert the optimistic update on error
    release.isSaved = false
    console.error('Failed to save track:', error)
    showNotification(error.response?.data?.error || error.message || 'Unable to save track.')

    // Dispatch event to revert sidebar count
    window.dispatchEvent(new CustomEvent('track-unsaved'))
  } finally {
    processingRelease.value = null
  }
}

const banTrack = async (release: WatchlistRelease, index: number) => {
  // Store the original state for potential rollback
  const originalBannedState = release.isBanned
  const identifier = getReleaseIdentifier(release)
  console.log('🎨 [WATCHLIST] Ban toggle clicked', {
    identifier,
    previous: originalBannedState,
    releaseBanned: release.isBanned,
    backendFlag: (release as any).is_banned
  })

  // Optimistically update the UI immediately
  release.isBanned = !release.isBanned
  const timestamp = Date.now().toString()
  if (release.isBanned) {
    localStorage.setItem('track-blacklisted-timestamp', timestamp)
    sessionBannedReleases.value.add(identifier)
    persistSessionBanned()
  } else {
    localStorage.setItem('track-unblacklisted-timestamp', timestamp)
    pendingAutoBannedTracks.value.delete(identifier)
    pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)
    sessionBannedReleases.value.delete(identifier)
    persistSessionBanned()
  }

  // Process in the background
  const key = getReleaseKey(release, index)
  processingRelease.value = key

  try {
    // Check if this is an album (has album_id and multiple tracks)
    const isAlbum = release.spotify_album_id && (release.track_count ?? 1) > 1

    let trackId: string | null = release.spotify_track_id
    let trackName: string = release.track_title
    let isrc: string | null = release.isrc || null

    // For albums, or when we have album_id but no track_id, fetch the first track details from the album API
    if (release.spotify_album_id && !trackId) {
      if (!release.spotify_album_id) {
        release.isBanned = originalBannedState
        showNotification('No album ID available for this release.')
        processingRelease.value = null
        return
      }

      try {
        const response = await http.get<{
          success: boolean
          data?: {
            tracks?: {
              items?: Array<{
                id?: string
                uri?: string
                name?: string
                external_ids?: { isrc?: string }
              }>
            }
          }
        }>(`music-preferences/spotify/album/${release.spotify_album_id}`)

        if (response.success && response.data?.tracks?.items && response.data.tracks.items.length > 0) {
          const firstTrack = response.data.tracks.items[0]
          // Extract track ID from id field or from URI (spotify:track:xxxxx)
          trackId = firstTrack.id || (firstTrack.uri?.match(/spotify:track:([a-zA-Z0-9]+)/)?.[1] ?? null)
          // Extract track name from the first track
          trackName = firstTrack.name || release.track_title
          // Extract ISRC from the first track
          isrc = firstTrack.external_ids?.isrc || null
        }
      } catch (error) {
        release.isBanned = originalBannedState
        console.error('Failed to fetch album tracks:', error)
        showNotification('Unable to fetch track information for this album. Please try again later.')
        processingRelease.value = null
        return
      }

      if (!trackId) {
        release.isBanned = originalBannedState
        showNotification('No track ID available for this album. The album may not have track information available.')
        processingRelease.value = null
        return
      }
    }

    if (originalBannedState) {
      // Was banned, now unbanning
      const params = new URLSearchParams({
        isrc: isrc || '',
        track_name: trackName,
        artist_name: release.artist_name,
      })
      const response = await http.delete(`music-preferences/blacklist-track?${params.toString()}`)
      if (!response.success) {
        throw new Error(response.error || 'Failed to unban track')
      }
      pendingAutoBannedTracks.value.delete(identifier)
      pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)
      sessionBannedReleases.value.delete(identifier)
      persistSessionBanned()
    } else {
      // Was not banned, now banning
      // Fallback: try embed_id when no track ID present
      if (!trackId && release.embed_type === 'track' && release.embed_id) {
        trackId = release.embed_id
      }

      // Must have at least a Spotify ID or ISRC for backend validation
      if (!trackId && !isrc) {
        release.isBanned = originalBannedState
        showNotification('Cannot ban track: missing identifiers (Spotify ID/ISRC).')
        processingRelease.value = null
        return
      }

      const response = await http.post('music-preferences/blacklist-track', {
        spotify_id: trackId,
        isrc,
        track_name: trackName,
        artist_name: release.artist_name,
      })

      if (!response.success) {
        throw new Error(response.error || 'Failed to ban track')
      }

      pendingAutoBannedTracks.value.delete(identifier)
      pendingAutoBannedTracks.value = new Set(pendingAutoBannedTracks.value)
      sessionBannedReleases.value.add(identifier)
      persistSessionBanned()
      console.log('🎨 [WATCHLIST] Ban applied', { identifier, isBanned: release.isBanned })
    }
  } catch (error: any) {
    // Revert the optimistic update on error
    release.isBanned = originalBannedState
    console.error('Failed to toggle ban track:', error)
    showNotification(error.response?.data?.error || error.message || 'Unable to update ban status.')
  } finally {
    processingRelease.value = null
    console.log('🎨 [WATCHLIST] Ban toggle final state', { identifier, isBanned: release.isBanned })
  }
}

const formatNumber = (num: number | null | undefined): string => {
  if (!num) {
    return '0'
  }
  if (num >= 1_000_000) {
    return `${(num / 1_000_000).toFixed(1)}M`
  }
  if (num >= 1_000) {
    return `${(num / 1_000).toFixed(1)}K`
  }
  return num.toString()
}

const formatDate = (dateString: string): string => {
  if (!dateString) {
    return 'Unknown'
  }

  const date = new Date(dateString)
  if (Number.isNaN(date.getTime())) {
    return 'Unknown'
  }

  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffDays = Math.max(0, Math.floor(diffMs / (1000 * 60 * 60 * 24)))

  if (diffDays === 0) {
    return 'Today'
  }

  if (diffDays === 1) {
    return '1 day ago'
  }

  if (diffDays < 7) {
    return `${diffDays} days ago`
  }

  const diffWeeks = Math.max(1, Math.floor(diffDays / 7))
  return diffWeeks === 1 ? '1 week ago' : `${diffWeeks} weeks ago`
}

const formatCooldown = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  if (hours > 0) {
    return `${hours}h ${minutes}m`
  }
  return `${minutes}m`
}

const formatRelativeTime = (timestamp: string) => {
  const date = new Date(timestamp)
  const diff = Date.now() - date.getTime()

  const minutes = Math.floor(diff / 60000)
  if (minutes < 60) {
    return `${minutes} minute${minutes === 1 ? '' : 's'} ago`
  }

  const hours = Math.floor(minutes / 60)
  if (hours < 24) {
    return `${hours} hour${hours === 1 ? '' : 's'} ago`
  }

  const days = Math.floor(hours / 24)
  return `${days} day${days === 1 ? '' : 's'} ago`
}

watch(searchQuery, value => {
  if (searchDebounce) {
    clearTimeout(searchDebounce)
  }

  searchDebounce = window.setTimeout(() => {
    debouncedQuery.value = value.trim()
  }, 300)
})

watch(debouncedQuery, value => {
  if (value.length < 2) {
    searchResults.value = []
    return
  }
  searchArtists(value)
})

watch(releases, () => {
  currentPage.value = 1
})

watch(banListenedTracks, async (newValue, oldValue) => {
  if (newValue && !oldValue) {
    const targets = paginatedReleases.value.filter(release => {
      const identifier = getReleaseIdentifier(release)
      return listenedTracks.value.has(identifier) && !release.isBanned
    })

    for (const release of targets) {
      await autoBlacklistListenedRelease(release)
    }
  }
})

watch(isFetchingReleases, value => {
  if (!value && autoRefreshRequested.value) {
    attemptReleaseRefresh(true)
  }
})

onMounted(async () => {
  window.addEventListener(WATCHLIST_EVENT, handleWatchlistUpdated as EventListener)
  await loadWatchlist()
  loadSessionBanned()
  await loadListenedTracks()
  await fetchReleases()
})

onUnmounted(() => {
  window.removeEventListener(WATCHLIST_EVENT, handleWatchlistUpdated as EventListener)
  if (autoRefreshTimer) {
    clearTimeout(autoRefreshTimer)
    autoRefreshTimer = undefined
  }
})
</script>

<style scoped lang="postcss">
.artist-watchlist-screen {
  @apply space-y-8;
}

.spotify-embed {
  background-color: rgba(255, 255, 255, 0.05) !important;
  border: none;
  overflow: hidden;
  opacity: 0;
  transition: opacity 0.3s ease-in-out;
}

.spotify-embed::-webkit-scrollbar {
  display: none;
}

.spotify-embed:loaded,
.spotify-embed[data-loaded='true'] {
  opacity: 1;
}
</style>
