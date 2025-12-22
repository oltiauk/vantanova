<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader header-image="/VantaNova-Logo.svg" />
    </template>

    <div class="label-watchlist-screen">
      <div class="flex flex-col gap-4 md:flex-row md:items-center">
        <div class="flex-1">
          <div class="rounded-lg p-4">
            <div class="max-w-[39rem] mx-auto text-center">
              <p class="text-k-text-secondary text-sm">
                Follow labels from the Label Search results to track their latest releases.
              </p>
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

      <div class="flex flex-col gap-6 lg:flex-row lg:items-start items-start">
        <div class="w-full lg:w-72 lg:self-start lg:flex-none">
          <div class="flex items-center gap-3 mb-3">
            <div class="h-6" />
          </div>
          <div class="bg-white/5 rounded-xl p-5 flex flex-col">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-lg font-semibold text-white">Followed Labels</h2>
              <span class="text-sm text-white/60">{{ watchlist.length }}/{{ watchlistLimit }}</span>
            </div>
            <div v-if="watchlist.length" class="space-y-2 pr-1">
              <div
                v-for="label in watchlist"
                :key="label.normalized_label"
                class="flex items-center justify-between gap-2 px-3 py-2 bg-white/5 rounded-lg"
              >
                <p class="text-white text-sm truncate">{{ label.label }}</p>
                <button
                  class="text-white/60 hover:text-white transition"
                  title="Remove label"
                  @click="removeLabel(label)"
                >
                  <Icon :icon="faTimes" class="w-4 h-4" />
                </button>
              </div>
            </div>
            <p v-else class="text-sm text-white/60">
              No labels followed yet. Use the Follow button in Label Search results to add one.
            </p>
          </div>
        </div>

        <div class="flex-1">
          <div class="flex items-center gap-3 mb-3">
            <span class="text-sm text-white/80">Ban listened tracks</span>
            <button
              class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
              :class="banListenedTracks ? 'bg-green-500' : 'bg-gray-600'"
              @click="banListenedTracks = !banListenedTracks"
            >
              <span
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                :class="banListenedTracks ? 'translate-x-5' : 'translate-x-0'"
              />
            </button>
          </div>
          <div class="bg-white/5 rounded-xl">
            <div class="flex items-center justify-between px-6 pt-6 pb-4">
              <h3 class="text-lg font-semibold text-white">Releases in the past 2 weeks</h3>
              <button
                class="flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-white font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="isFetchingReleases || watchlist.length === 0"
                title="Refresh releases"
                @click="fetchReleases(true)"
              >
                <Icon :icon="faSync" class="w-4 h-4" />
                <span>Refresh</span>
              </button>
            </div>

            <div>
              <div
                v-if="isFetchingReleases"
                class="flex flex-col items-center justify-center py-12 gap-3"
              >
                <span class="h-10 w-10 border-4 border-white/20 border-t-k-accent rounded-full animate-spin" />
                <p class="text-white/70 text-sm">Fetching latest releases…</p>
              </div>
              <template v-else>
                <div class="overflow-x-auto scrollbar-hide">
                  <table class="w-full">
                    <thead>
                      <tr class="border-b border-white/10 text-white/80">
                        <th class="text-left px-4 py-4 font-medium w-12">#</th>
                        <th class="text-left px-4 py-4 font-medium">Label</th>
                        <th class="text-left px-4 py-4 font-medium">Artist</th>
                        <th class="text-left px-8 py-4 font-medium">Title</th>
                        <th class="text-left px-2 py-4 font-medium w-28 whitespace-nowrap">Release Date</th>
                        <th class="text-center pr-3 py-4 font-medium whitespace-nowrap" />
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="releases.length === 0" class="text-center">
                        <td colspan="6" class="py-8 text-white/70">No recent releases found. Try refreshing or following more labels.</td>
                      </tr>
                      <template v-else>
                        <template v-for="(release, index) in paginatedReleases" :key="getReleaseKey(release, index)">
                          <tr class="border-b border-white/5 hover:bg-white/5 transition">
                            <td class="px-4 py-4 text-white/70">{{ (currentPage - 1) * releasesPerPage + index + 1 }}</td>
                            <td class="px-4 py-4">
                              <p class="text-white font-medium">{{ release.label }}</p>
                            </td>
                            <td class="px-4 py-4">
                              <p class="text-white/90 font-medium">{{ release.artist_name }}</p>
                            </td>
                            <td class="px-8 py-4">
                              <span class="text-white/80">
                                {{ release.release_title || release.track_title }}
                                <span v-if="release.track_count && release.track_count > 1" class="text-white/50 text-xs ml-1">({{ release.track_count }} tracks)</span>
                              </span>
                            </td>
                            <td class="px-4 py-4 text-white/70 text-sm w-32 whitespace-nowrap text-center">
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
                                      : 'bg-[#484948] hover:bg-gray-500 text-white',
                                  ]"
                                  :title="expandedReleaseKey === getReleaseKey(release, index) ? 'Close preview' : (isReleaseListened(release) ? 'Tracks have been listened to' : 'Listen to release')"
                                  @click="togglePreview(release, index)"
                                >
                                  <img v-if="expandedReleaseKey !== getReleaseKey(release, index)" src="/public/img/Primary_Logo_White_RGB.svg" alt="Spotify" class="w-[21px] h-[21px] object-contain">
                                  <Icon v-else :icon="faTimes" class="w-3 h-3" />
                                  <span>{{ expandedReleaseKey === getReleaseKey(release, index) ? 'Close' : (isReleaseListened(release) ? 'Listened' : 'Listen') }}</span>
                                </button>
                              </div>
                            </td>
                          </tr>
                          <tr v-if="expandedReleaseKey === getReleaseKey(release, index)" class="bg-white/5 border-b border-white/5">
                            <td colspan="6" class="p-0">
                              <div class="spotify-player-container p-6 bg-white/3 relative">
                                <div class="mx-auto w-full max-w-[98%]">
                                  <div v-if="release.spotify_album_id || release.spotify_track_id">
                                    <iframe
                                      :key="release.track_count && release.track_count > 1 ? (release.spotify_album_id || release.spotify_track_id) : release.spotify_track_id"
                                      :src="release.track_count && release.track_count > 1
                                        ? `https://open.spotify.com/embed/album/${release.spotify_album_id || release.spotify_track_id}?utm_source=generator&theme=0`
                                        : `https://open.spotify.com/embed/track/${release.spotify_track_id}?utm_source=generator&theme=0`"
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
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { faBan, faHeart, faSearch, faSync, faTimes } from '@fortawesome/free-solid-svg-icons'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import { http } from '@/services/http'

interface WatchlistLabel {
  id?: number
  label: string
  normalized_label: string
  metadata?: any
}

interface LabelWatchlistRelease {
  label: string
  label_normalized?: string
  artist_name?: string
  artist_id?: string
  followers?: number | null
  release_type?: string
  release_title?: string
  release_name?: string
  track_title?: string
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
  embed_type?: 'track' | 'album'
  is_listened?: boolean
  isListened?: boolean
}

type WatchlistMutationAction = 'added' | 'removed'

interface WatchlistEventDetail {
  action: WatchlistMutationAction
  label: WatchlistLabel
}

const WATCHLIST_EVENT = 'label-watchlist-updated' as const
const LABEL_WATCHLIST_REFRESH_KEY = 'koel-label-watchlist-refresh-request'
const LABEL_WATCHLIST_LAST_REFRESH_KEY = 'koel-label-watchlist-last-refresh'
const TWENTY_FOUR_HOURS = 24 * 60 * 60 * 1000

const watchlist = ref<WatchlistLabel[]>([])
const watchlistLimit = ref(30)
const releases = ref<LabelWatchlistRelease[]>([])
const notification = ref<string | null>(null)
const isFetchingReleases = ref(false)
const cooldownSeconds = ref(0)
const lastUpdated = ref<string | null>(null)
const expandedReleaseKey = ref<string | null>(null)
const listenedTracks = ref(new Set<string>())
const banListenedTracks = ref(false)
const pendingAutoBannedTracks = ref(new Set<string>())
const sessionBannedReleases = ref(new Set<string>())
const currentPage = ref(1)
const releasesPerPage = 20
const processingRelease = ref<string | null>(null)
const notificationTimeout = ref<number | undefined>()

const sortWatchlist = (entries: WatchlistLabel[]) => [...entries].sort((a, b) => a.label.localeCompare(b.label))

const normalizeLabel = (entry: WatchlistLabel): WatchlistLabel => ({
  id: entry.id,
  label: entry.label,
  normalized_label: entry.normalized_label ?? entry.label.toLowerCase(),
  metadata: entry.metadata ?? null,
})

const applyWatchlistMutation = (action: WatchlistMutationAction, label: WatchlistLabel) => {
  const normalized = normalizeLabel(label)
  const existingIndex = watchlist.value.findIndex(entry => entry.normalized_label === normalized.normalized_label)

  if (action === 'added') {
    if (existingIndex === -1) {
      watchlist.value = sortWatchlist([...watchlist.value, normalized])
      // Reset refresh timestamp when new label is added
      try {
        localStorage.removeItem(LABEL_WATCHLIST_LAST_REFRESH_KEY)
      } catch {}
    } else {
      watchlist.value.splice(existingIndex, 1, { ...watchlist.value[existingIndex], ...normalized })
    }
  } else {
    if (existingIndex !== -1) {
      watchlist.value.splice(existingIndex, 1)
    }
    releases.value = releases.value.filter(release => release.label_normalized !== normalized.normalized_label)
    if (watchlist.value.length === 0) {
      releases.value = []
    }
  }

  cooldownSeconds.value = 0
  lastUpdated.value = null
}

const emitWatchlistEvent = (action: WatchlistMutationAction, label: WatchlistLabel) => {
  window.dispatchEvent(new CustomEvent<WatchlistEventDetail>(WATCHLIST_EVENT, {
    detail: {
      action,
      label: normalizeLabel(label),
    },
  }))
}

const handleWatchlistUpdated = (event: Event) => {
  const detail = (event as CustomEvent<WatchlistEventDetail>).detail
  if (!detail?.label) {
    return
  }
  applyWatchlistMutation(detail.action, detail.label)
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

const showNotification = (message: string) => {
  notification.value = message
  if (notificationTimeout.value) {
    clearTimeout(notificationTimeout.value)
  }
  notificationTimeout.value = window.setTimeout(() => {
    notification.value = null
  }, 5000)
}

const loadWatchlist = async () => {
  try {
    const response = await http.get<{ success: boolean, data: WatchlistLabel[], limit: number }>('music-preferences/label-watchlist')
    if (response.success) {
      watchlist.value = sortWatchlist(response.data.map(normalizeLabel))
      watchlistLimit.value = response.limit
    }
  } catch (error) {
    console.error('Failed to load label watchlist:', error)
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
    const stored = localStorage.getItem('koel-label-watchlist-listened-tracks')
    if (stored) {
      const keys: string[] = JSON.parse(stored)
      listenedTracks.value = new Set(keys)
    }
  } catch {}
}

const refreshIfRequested = async (): Promise<boolean> => {
  try {
    const request = localStorage.getItem(LABEL_WATCHLIST_REFRESH_KEY)
    if (!request) {
      return false
    }

    clearRefreshRequest()

    if (isRefreshStale()) {
      if (isFetchingReleases.value) {
        return false
      }
      await fetchReleases(true)
      return true
    }
  } catch (error) {
    console.warn('Failed to handle label watchlist refresh request:', error)
  }
  return false
}

const clearRefreshRequest = () => {
  try {
    localStorage.removeItem(LABEL_WATCHLIST_REFRESH_KEY)
  } catch {}
}

const isRefreshStale = () => {
  try {
    const raw = localStorage.getItem(LABEL_WATCHLIST_LAST_REFRESH_KEY)
    const last = raw ? Number.parseInt(raw, 10) : 0
    if (!Number.isFinite(last)) {
      return true
    }
    return Date.now() - last > TWENTY_FOUR_HOURS
  } catch {
    return true
  }
}

const handleSidebarRefreshEvent = () => {
  refreshIfRequested()
}

const getReleaseKey = (release: LabelWatchlistRelease, index: number): string => {
  return (
    release.spotify_track_id
    || release.isrc
    || release.spotify_album_id
    || `${release.label_normalized || release.label}-${release.track_title}-${release.release_date}-${index}`
  )
}

const getReleaseIdentifier = (release: LabelWatchlistRelease): string => {
  return release.spotify_track_id
    || release.isrc
    || release.spotify_album_id
    || `${release.label_normalized || release.label}-${release.track_title}-${release.release_date}`
}

const isReleaseListened = (release: LabelWatchlistRelease): boolean => {
  const identifier = getReleaseIdentifier(release)
  return !!release.isListened || !!release.is_listened || listenedTracks.value.has(identifier)
}

const loadSessionBanned = () => {
  try {
    const stored = localStorage.getItem('koel-label-watchlist-banned')
    if (stored) {
      sessionBannedReleases.value = new Set(JSON.parse(stored) as string[])
    }
  } catch {}
}

const persistSessionBanned = () => {
  try {
    localStorage.setItem('koel-label-watchlist-banned', JSON.stringify(Array.from(sessionBannedReleases.value)))
  } catch {}
}

const togglePreview = (release: LabelWatchlistRelease, index: number) => {
  const key = getReleaseKey(release, index)
  const hasAlbum = !!release.spotify_album_id
  const hasTrack = !!release.spotify_track_id

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

const markReleaseAsListened = async (release: LabelWatchlistRelease) => {
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
      isrc: release.isrc,
    })
  } catch (e) {
    try {
      localStorage.setItem('koel-label-watchlist-listened-tracks', JSON.stringify(Array.from(listenedTracks.value)))
    } catch {}
  }

  if (banListenedTracks.value) {
    autoBlacklistListenedRelease(release)
  }
}

const autoBlacklistListenedRelease = async (release: LabelWatchlistRelease) => {
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
      artist_name: release.artist_name,
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

const getSeedSpotifyId = (release: LabelWatchlistRelease): string | null => {
  return release.spotify_track_id || release.spotify_album_id || null
}

const hasValidSeed = (release: LabelWatchlistRelease): boolean => {
  return isValidSpotifyId(getSeedSpotifyId(release))
}

const viewRelatedTracks = (release: LabelWatchlistRelease) => {
  const trackId = getSeedSpotifyId(release)

  if (!trackId || !isValidSpotifyId(trackId)) {
    showNotification('No valid Spotify track ID available for this release.')
    return
  }

  const seedTrackData = {
    id: trackId,
    name: release.track_title,
    artist: release.artist_name,
    source: 'labelWatchlist',
    timestamp: Date.now(),
  }

  localStorage.setItem('koel-music-discovery-seed-track', JSON.stringify(seedTrackData))
  window.location.hash = '#/discover'
}

const saveTrack = async (release: LabelWatchlistRelease, index: number) => {
  release.isSaved = true
  localStorage.setItem('track-saved-timestamp', Date.now().toString())

  const key = getReleaseKey(release, index)
  processingRelease.value = key

  try {
    const isAlbum = release.spotify_album_id && (release.track_count ?? 1) > 1

    let trackId: string | null = release.spotify_track_id
    // Use release_title as fallback, ensure it's never empty for backend validation
    let trackName: string = release.track_title || release.release_title || release.release_name || 'Unknown Track'
    let isrc: string | null = release.isrc || null

    if (release.spotify_album_id && !trackId) {
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
          trackId = firstTrack.id || (firstTrack.uri?.match(/spotify:track:([a-zA-Z0-9]+)/)?.[1] ?? null)
          // Ensure trackName is never empty
          trackName = firstTrack.name || release.track_title || release.release_title || release.release_name || 'Unknown Track'
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

    if (!trackId && release.embed_type === 'track' && release.embed_id) {
      trackId = release.embed_id
    }

    if (!trackId && !isrc) {
      release.isSaved = false
      showNotification('Cannot save track: missing identifiers (Spotify ID/ISRC).')
      processingRelease.value = null
      return
    }

    // Ensure artist_name is never empty for backend validation
    const artistName = release.artist_name || 'Unknown Artist'

    // Validate required fields before sending
    if (!trackName || !artistName) {
      release.isSaved = false
      showNotification('Cannot save track: missing required information (track name or artist name).')
      processingRelease.value = null
      return
    }

    const payload = {
      spotify_id: trackId,
      isrc,
      track_name: trackName,
      artist_name: artistName,
      label: release.label,
      popularity: release.popularity ?? null,
      followers: release.followers ?? null,
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

    window.dispatchEvent(new CustomEvent('track-saved'))
  } catch (error: any) {
    release.isSaved = false
    console.error('Failed to save track:', error)
    showNotification(error.response?.data?.error || error.message || 'Unable to save track.')
    window.dispatchEvent(new CustomEvent('track-unsaved'))
  } finally {
    processingRelease.value = null
  }
}

const banTrack = async (release: LabelWatchlistRelease, index: number) => {
  const originalBannedState = release.isBanned
  const identifier = getReleaseIdentifier(release)

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

  const key = getReleaseKey(release, index)
  processingRelease.value = key

  try {
    const isAlbum = release.spotify_album_id && (release.track_count ?? 1) > 1

    let trackId: string | null = release.spotify_track_id
    let trackName: string = release.track_title || ''
    let isrc: string | null = release.isrc || null

    if (release.spotify_album_id && !trackId) {
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
          trackId = firstTrack.id || (firstTrack.uri?.match(/spotify:track:([a-zA-Z0-9]+)/)?.[1] ?? null)
          trackName = firstTrack.name || release.track_title || ''
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
      const params = new URLSearchParams({
        isrc: isrc || '',
        track_name: trackName,
        artist_name: release.artist_name || '',
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
      if (!trackId && release.embed_type === 'track' && release.embed_id) {
        trackId = release.embed_id
      }

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
    }
  } catch (error: any) {
    release.isBanned = originalBannedState
    console.error('Failed to toggle ban track:', error)
    showNotification(error.response?.data?.error || error.message || 'Unable to update ban status.')
  } finally {
    processingRelease.value = null
  }
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
  if (!value) {

  }
})

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
      data: LabelWatchlistRelease[]
      cached: boolean
      label_count: number
      track_count: number
      cooldown_seconds: number
      last_executed_at?: string
      api_error?: boolean
      message?: string
      api_partial_failure?: boolean
      failed_labels?: number
    }>('music-preferences/label-watchlist/releases', {
      force_refresh: force,
    })

    if (response.success) {
      const previousStates = new Map<string, { isSaved?: boolean, isBanned?: boolean, isListened?: boolean }>()
      releases.value.forEach(rel => {
        const key = getReleaseIdentifier(rel)
        previousStates.set(key, { isSaved: rel.isSaved, isBanned: rel.isBanned, isListened: rel.isListened ?? rel.is_listened })
      })

      releases.value = (response.data ?? []).map(item => {
        const stateKey = getReleaseIdentifier(item as LabelWatchlistRelease)
        const previous = previousStates.get(stateKey) || {}

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

      cooldownSeconds.value = response.cooldown_seconds ?? 0
      lastUpdated.value = response.last_executed_at ?? null

      if (response.api_error && response.message) {
        console.warn('API Error:', response.message)
      }

      if (response.api_partial_failure && response.failed_labels) {
        console.warn(`Failed to fetch data for ${response.failed_labels} label(s)`)
      }

      markLastRefresh()
    }
  } catch (error: any) {
    console.error('Failed to fetch releases:', error)
    const errorMessage = error.response?.data?.error || 'Unable to fetch new releases at the moment.'
    showNotification(errorMessage)
  } finally {
    isFetchingReleases.value = false
  }
}

const markLastRefresh = () => {
  try {
    localStorage.setItem(LABEL_WATCHLIST_LAST_REFRESH_KEY, Date.now().toString())
  } catch {}
}

const removeLabel = async (label: WatchlistLabel) => {
  try {
    await http.delete(`music-preferences/label-watchlist/${encodeURIComponent(label.normalized_label)}`)
    applyWatchlistMutation('removed', label)
    emitWatchlistEvent('removed', label)
  } catch (error) {
    console.error('Failed to remove label:', error)
    showNotification('Unable to remove label from watchlist.')
  }
}

onMounted(async () => {
  window.addEventListener(WATCHLIST_EVENT, handleWatchlistUpdated as EventListener)
  window.addEventListener('label-watchlist-sidebar-click', handleSidebarRefreshEvent)
  await loadWatchlist()
  loadSessionBanned()
  await loadListenedTracks()
  const refreshedFromSidebar = await refreshIfRequested()
  if (!refreshedFromSidebar) {
    await fetchReleases()
  }
})

onUnmounted(() => {
  window.removeEventListener(WATCHLIST_EVENT, handleWatchlistUpdated as EventListener)
  window.removeEventListener('label-watchlist-sidebar-click', handleSidebarRefreshEvent)
})
</script>

<style scoped lang="postcss">
.label-watchlist-screen {
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
