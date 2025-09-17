<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>
        <div class="text-center">
          Banned Artists
        </div>
        <template #meta>
          <div class="text-center">
            <span class="text-k-text-secondary text-lg">Manage your blacklisted artists</span>
          </div>
        </template>
      </ScreenHeader>
    </template>

    <div class="banned-artists-screen">
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
                class="w-full py-3 pl-12 pr-12 bg-white/10 rounded-lg focus:outline-none text-white text-lg text-center"
                placeholder="Search for an artist..."
              >
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <div class="animate-spin rounded-full h-8 w-8 border-2 border-k-accent border-t-transparent mb-4"></div>
          <span class="text-k-text-secondary">Loading banned artists...</span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredArtists.length === 0 && !isLoading" class="text-center py-12">
        <Icon :icon="faUserSlash" class="w-16 h-16 text-k-text-tertiary mx-auto mb-4" />
        <h4 class="text-lg font-medium text-k-text-primary mb-2">
          {{ searchQuery ? 'No artists found' : 'No Banned Artists' }}
        </h4>
        <p class="text-k-text-secondary">
          {{ searchQuery ? 'Try adjusting your search terms' : 'Artists you ban will appear here' }}
        </p>
      </div>

      <!-- Banned Artists Table -->
      <div v-else class="bg-white/5 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-white/10">
                <th class="text-left p-3 py-7 font-medium">#</th>
                <th class="text-left p-3 font-medium">Artist Name</th>
                <th class="text-center p-3 font-medium">Unban Artist</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(artist, index) in paginatedArtists"
                :key="artist.id"
                :class="[
                  'transition h-16 border-b border-white/5 hover:bg-white/5',
                  'artist-row'
                ]"
                :style="{ animationDelay: `${index * 50}ms` }"
              >
                <!-- Index -->
                <td class="p-3 align-middle">
                  <span class="text-white/60">{{ (currentPage - 1) * artistsPerPage + index + 1 }}</span>
                </td>

                <!-- Artist Name -->
                <td class="p-3 align-middle">
                  <span class="font-medium text-k-text-primary">{{ artist.artist_name }}</span>
                </td>

                <!-- Unban Artist Button -->
                <td class="p-3 align-middle">
                  <div class="flex items-center justify-center">
                    <button
                      @click="unbanArtist(artist)"
                      :disabled="isProcessing"
                      class="p-2 rounded-full transition-colors text-red-400 hover:text-red-300 hover:bg-red-500/20"
                      title="Remove artist from blacklist"
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
      <div v-if="totalPages > 1" class="flex items-center justify-center gap-2 mt-8">
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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { faSearch, faUserSlash, faTrash } from '@fortawesome/free-solid-svg-icons'
import { http } from '@/services/http'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { useRouter } from '@/composables/useRouter'
import { eventBus } from '@/utils/eventBus'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

// Types
interface BlacklistedArtist {
  id: number
  spotify_artist_id: string
  artist_name: string
  created_at: string
}

// Initialize shared blacklist state
const {
  removeArtistFromBlacklist,
  loadBlacklistedItems
} = useBlacklistFiltering()

// Initialize router
const { onRouteChanged } = useRouter()

// State
const isLoading = ref(false)
const isProcessing = ref(false)
const searchQuery = ref('')
const currentPage = ref(1)
const artistsPerPage = 20
const artists = ref<BlacklistedArtist[]>([])

// Computed properties
const filteredArtists = computed(() => {
  if (!searchQuery.value.trim()) return artists.value

  const query = searchQuery.value.toLowerCase()
  return artists.value.filter(artist =>
    artist.artist_name.toLowerCase().includes(query)
  )
})

const paginatedArtists = computed(() => {
  const start = (currentPage.value - 1) * artistsPerPage
  const end = start + artistsPerPage
  return filteredArtists.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredArtists.value.length / artistsPerPage)
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

// Methods
const loadArtists = async () => {
  isLoading.value = true

  try {
    const response = await http.get('music-preferences/blacklisted-artists')
    if (response.success && response.data) {
      // Sort by most recent first (created_at descending)
      artists.value = response.data.sort((a: BlacklistedArtist, b: BlacklistedArtist) =>
        new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
      )
    } else {
      artists.value = []
    }
  } catch (error) {
    console.error('Failed to load banned artists:', error)
    artists.value = []
  } finally {
    isLoading.value = false
  }
}

const unbanArtist = async (artist: BlacklistedArtist) => {
  if (isProcessing.value) return

  isProcessing.value = true

  try {
    const deleteData = {
      spotify_artist_id: artist.spotify_artist_id,
      artist_name: artist.artist_name
    }
    const params = new URLSearchParams(deleteData)
    const response = await http.delete(`music-preferences/blacklist-artist?${params}`)

    if (response.success) {
      // Update shared state
      removeArtistFromBlacklist(artist.artist_name)

      // Remove from local state
      artists.value = artists.value.filter(a => a.id !== artist.id)

      // Reset to first page if current page becomes empty
      if (paginatedArtists.value.length === 0 && currentPage.value > 1) {
        currentPage.value = Math.max(1, currentPage.value - 1)
      }

      console.log(`Artist ${artist.artist_name} has been unbanned from Banned Artists screen`)
    } else {
      throw new Error(response.error || 'Failed to unban artist')
    }
  } catch (error: any) {
    console.error('Failed to unban artist:', error)
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
  // Listen for artist ban events - refresh the list to show newly banned artists
  eventBus.on('ARTIST_BANNED', async (artistName: string) => {
    console.log(`🔄 Received ARTIST_BANNED event for: ${artistName}`)
    // Refresh the artist list to include the newly banned artist
    await loadArtists()
  })

  // Listen for artist unban events - UI should already be updated by local state
  eventBus.on('ARTIST_UNBANNED', (artistName: string) => {
    console.log(`🔄 Received ARTIST_UNBANNED event for: ${artistName}`)
    // State is already updated by the shared composable and local state, UI will automatically update
  })
}

const cleanupEventListeners = () => {
  eventBus.off('ARTIST_BANNED')
  eventBus.off('ARTIST_UNBANNED')
}

// Lifecycle
onMounted(async () => {
  setupEventListeners()
  await Promise.all([
    loadArtists(),
    loadBlacklistedItems() // Load shared blacklist state
  ])
})

onUnmounted(() => {
  cleanupEventListeners()
})

// Refresh data when navigating back to this screen
onRouteChanged((route) => {
  if (route.screen === 'BannedArtists') {
    // Refresh the artists data when coming back to this screen
    loadArtists()
  }
})

// Watch search query to reset pagination
watch(searchQuery, resetPagination)
</script>

<style scoped>
/* Artist rows progressive display animation */
.artist-row {
  animation: fadeInUp 0.6s ease-out both;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>