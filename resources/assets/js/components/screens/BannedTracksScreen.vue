<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader header-image="/VantaNova-Logo.svg" />
    </template>

    <div class="banned-tracks-screen">
      <!-- Search Container -->
      <div class="search-container mb-8">
        <div class="rounded-lg p-4">
          <div class="max-w-4xl mx-auto">
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg focus:outline-none text-white text-lg search-input"
                placeholder="Search for a banned track"
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <div class="animate-spin rounded-full h-8 w-8 border-2 border-k-accent border-t-transparent mb-4" />
          <span class="text-k-text-secondary">Loading banned tracks...</span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredTracks.length === 0 && !isLoading" class="text-center py-12">
        <h4 class="text-lg font-medium text-k-text-primary mb-2">
          {{ searchQuery ? 'No tracks found' : 'No Banned Tracks' }}
        </h4>
        <p class="text-k-text-secondary">
          {{ searchQuery ? 'Try adjusting your search terms' : 'Tracks you ban will appear here' }}
        </p>
      </div>

      <!-- Banned Tracks Table -->
      <div v-else class="bg-white/5 rounded-lg overflow-hidden max-w-4xl mx-auto">
        <div class="overflow-x-auto scrollbar-hide">
          <table class="w-full table-fixed">
            <thead>
              <tr class="border-b border-white/10">
                <th class="text-left py-4 pl-3 font-medium w-10" />
                <th class="text-center pr-3 font-medium w-20 whitespace-nowrap" />
                <th class="text-left px-3 py-4 pl-10 font-medium w-64">Artist(s)</th>
                <th class="text-left px-3 pl-24 font-medium">Title</th>
                <th class="text-center px-3 font-medium w-24 whitespace-nowrap">Unban</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(track, index) in paginatedTracks"
                :key="track.id"
                class="transition h-12 border-b border-white/5 hover:bg-white/5"
              >
                <!-- Index -->
                <td class="px-3 py-2 align-middle">
                  <span class="text-white/60 text-sm">{{ (currentPage - 1) * tracksPerPage + index + 1 }}</span>
                </td>

                <!-- Ban Artist Button -->
                <td class="px-3 py-2 align-middle">
                  <div class="flex items-center justify-center">
                    <button
                      :disabled="isProcessing"
                      class="w-8 h-8 rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                      :class="[
                        isArtistBanned(track.artist_name)
                          ? 'bg-red-600 hover:bg-red-700 text-white'
                          : 'bg-[#484948] hover:bg-gray-500 text-white',
                      ]" :title="isArtistBanned(track.artist_name) ? 'Click to unban this artist' : 'Ban this artist'"
                      @click="banArtist(track)"
                    >
                      <Icon :icon="faUserSlash" class="text-xs" />
                    </button>
                  </div>
                </td>

                <!-- Artist -->
                <td class="px-3 pl-10 py-2 align-middle">
                  <span class="font-medium text-k-text-primary text-sm truncate block">{{ track.artist_name }}</span>
                </td>

                <!-- Track Title -->
                <td class="px-3 pl-24 py-2 align-middle">
                  <span class="text-k-text-secondary text-sm truncate block">{{ track.track_name }}</span>
                </td>

                <!-- Unban Track Button -->
                <td class="px-3 py-2 align-middle">
                  <div class="flex items-center justify-center">
                    <button
                      :disabled="isProcessing"
                      class="p-2 rounded-full transition-colors text-gray-300 hover:text-gray-100 hover:bg-white/10"
                      title="Remove track from blacklist"
                      @click="unbanTrack(track)"
                    >
                      <Icon :icon="faTrash" class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination Controls -->
      <div v-if="totalPages > 1 && !isLoading" class="flex items-center justify-center gap-2 mt-8">
        <button
          :disabled="currentPage === 1"
          class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="currentPage = Math.max(1, currentPage - 1)"
        >
          Previous
        </button>

        <div class="flex items-center gap-1">
          <button
            v-for="page in visiblePages"
            :key="page"
            :class="page === currentPage ? 'bg-k-accent text-white' : 'bg-k-bg-primary text-gray-300 hover:bg-white/10'"
            class="w-10 h-10 flex items-center justify-center rounded transition-colors"
            @click="currentPage = page"
          >
            {{ page }}
          </button>
        </div>

        <button
          :disabled="currentPage === totalPages"
          class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          @click="currentPage = Math.min(totalPages, currentPage + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { faBan, faSearch, faTrash, faUserSlash } from '@fortawesome/free-solid-svg-icons'
import { http } from '@/services/http'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { useRouter } from '@/composables/useRouter'
import { eventBus } from '@/utils/eventBus'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

// Watch search query to reset pagination
import { watch } from 'vue'

// Types
interface BlacklistedTrack {
  id: number
  isrc: string
  track_name: string
  artist_name: string
  spotify_id: string
  created_at: string
}

// Initialize shared blacklist state
const {
  isArtistBlacklisted,
  addArtistToBlacklist,
  removeArtistFromBlacklist,
  loadBlacklistedItems,
} = useBlacklistFiltering()

// Initialize router
const { onRouteChanged } = useRouter()

// State
const isLoading = ref(false)
const isProcessing = ref(false)
const searchQuery = ref('')
const currentPage = ref(1)
const tracksPerPage = 20
const tracks = ref<BlacklistedTrack[]>([])

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

const paginatedTracks = computed(() => {
  const start = (currentPage.value - 1) * tracksPerPage
  const end = start + tracksPerPage
  return filteredTracks.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredTracks.value.length / tracksPerPage)
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

// Helper function to check if an artist is banned (using shared state)
const isArtistBanned = (artistName: string): boolean => {
  return isArtistBlacklisted(artistName)
}

// Methods
const loadTracks = async () => {
  isLoading.value = true

  try {
    const response = await http.get('music-preferences/blacklisted-tracks')
    if (response.success && response.data) {
      // Sort by most recent first (created_at descending)
      tracks.value = response.data.sort((a: BlacklistedTrack, b: BlacklistedTrack) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
      )
    } else {
      tracks.value = []
    }
  } catch (error) {
    console.error('Failed to load banned tracks:', error)
    tracks.value = []
  } finally {
    isLoading.value = false
  }
}

const unbanTrack = async (track: BlacklistedTrack) => {
  if (isProcessing.value) {
    return
  }

  isProcessing.value = true

  try {
    const deleteData = {
      isrc: track.isrc || track.id.toString(),
      track_name: track.track_name,
      artist_name: track.artist_name,
    }
    const params = new URLSearchParams(deleteData)
    const response = await http.delete(`music-preferences/blacklist-track?${params}`)

    if (response.success) {
      // Remove from local state
      tracks.value = tracks.value.filter(t => t.id !== track.id)

      // Reset to first page if current page becomes empty
      if (paginatedTracks.value.length === 0 && currentPage.value > 1) {
        currentPage.value = Math.max(1, currentPage.value - 1)
      }
    } else {
      throw new Error(response.error || 'Failed to unban track')
    }
  } catch (error: any) {
    console.error('Failed to unban track:', error)
  } finally {
    isProcessing.value = false
  }
}

const banArtist = async (track: BlacklistedTrack) => {
  if (isProcessing.value) {
    return
  }

  const artistName = track.artist_name
  const isCurrentlyBanned = isArtistBanned(artistName)

  isProcessing.value = true

  try {
    if (isCurrentlyBanned) {
      // Unban the artist
      const deleteData = {
        spotify_artist_id: track.spotify_id || track.id.toString(),
        artist_name: artistName,
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-artist?${params}`)

      if (response.success) {
        // Update shared state
        removeArtistFromBlacklist(artistName)
        console.log(`Artist ${artistName} has been unbanned`)
      } else {
        throw new Error(response.error || 'Failed to unban artist')
      }
    } else {
      // Ban the artist
      const response = await http.post('music-preferences/blacklist-artist', {
        artist_name: artistName,
        spotify_artist_id: track.spotify_id || track.id.toString(),
      })

      if (response.success) {
        // Update shared state
        addArtistToBlacklist(artistName)
        console.log(`Artist ${artistName} has been banned`)
      } else {
        throw new Error(response.error || 'Failed to ban artist')
      }
    }
  } catch (error: any) {
    console.error('Failed to ban/unban artist:', error)
  } finally {
    isProcessing.value = false
  }
}

// Reset pagination when search changes
const resetPagination = () => {
  currentPage.value = 1
}

// Event listeners for real-time updates
const setupEventListeners = () => {
  // Listen for artist ban/unban events from other components
  eventBus.on('ARTIST_BANNED', (artistName: string) => {
    console.log(`🔄 Received ARTIST_BANNED event for: ${artistName}`)
    // State is already updated by the shared composable, UI will automatically update
  })

  eventBus.on('ARTIST_UNBANNED', (artistName: string) => {
    console.log(`🔄 Received ARTIST_UNBANNED event for: ${artistName}`)
    // State is already updated by the shared composable, UI will automatically update
  })

  // Listen for track blacklist events from other screens (e.g., when saving tracks)
  const handleTrackBlacklisted = (event: CustomEvent) => {
    console.log('🔄 Track blacklisted from another screen, refreshing list...')
    loadTracks()
  }

  const handleTrackUnblacklisted = (event: CustomEvent) => {
    console.log('🔄 Track removed from blacklist from another screen, refreshing list...')
    loadTracks()
  }

  // Listen for localStorage changes (cross-tab communication)
  const handleStorageChange = (event: StorageEvent) => {
    if (event.key === 'track-blacklisted-timestamp') {
      console.log('🔄 Track blacklist updated in another tab, refreshing...')
      loadTracks()
    }
  }

  window.addEventListener('track-blacklisted', handleTrackBlacklisted as EventListener)
  window.addEventListener('storage', handleStorageChange)
}

const cleanupEventListeners = () => {
  eventBus.off('ARTIST_BANNED')
  eventBus.off('ARTIST_UNBANNED')
  window.removeEventListener('track-blacklisted', () => {})
  window.removeEventListener('storage', () => {})
}

// Lifecycle
onMounted(async () => {
  setupEventListeners()
  await Promise.all([
    loadTracks(),
    loadBlacklistedItems(), // Load shared blacklist state
  ])
})

onUnmounted(() => {
  cleanupEventListeners()
})

// Refresh data when navigating back to this screen
onRouteChanged(route => {
  if (route.screen === 'BannedTracks') {
    // Refresh the tracks data when coming back to this screen
    loadTracks()
  }
})

watch(searchQuery, resetPagination)
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
</style>
