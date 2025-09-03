<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="collapsed">
        Similar Artists
        <template #meta>
          <span v-if="selectedArtist" class="text-text-secondary">
            Similar to: {{ selectedArtist.name }}
          </span>
        </template>
      </ScreenHeader>
    </template>

    <div class="similar-artists-screen">
      <!-- Welcome Message - Only show when no results and no search -->
      <div v-if="!selectedArtist && !similarArtists.length && !searchQuery.trim() && !errorMessage" class="max-w-2xl mx-auto text-center mb-8">
        <h2 class="text-2xl font-bold mb-2">Similar Artists</h2>
        <p class="text-k-text-secondary">
          Search for an artist to find similar artists.
        </p>
      </div>

      <!-- Search Container -->
      <div class="seed-selection mb-8">
        <div class="search-container mb-6">
          <div class="rounded-lg p-4">
            <div class="max-w-4xl mx-auto">
              <div class="relative" ref="searchContainer">
                <!-- Search Icon -->
                <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none z-20 pl-4">
                  <Icon :icon="faSearch" class="w-5 h-5 text-white/40" />
                </div>
                
                <input
                  v-model="searchQuery"
                  type="text"
                  class="w-full py-3 pl-12 pr-12 bg-white/10 rounded-lg focus:outline-none text-white text-lg"
                  placeholder="Search for an artist"
                  @input="onSearchInput"
                />
                
                <!-- Search Dropdown -->
                <div 
                  v-if="searchResults.length > 0" 
                  class="absolute z-50 w-full bg-k-bg-secondary border border-k-border rounded-lg mt-1 shadow-xl"
                >
                  <div class="max-h-80 rounded-lg overflow-hidden overflow-y-auto">
                    <div v-for="(artist, index) in searchResults.slice(0, 10)" :key="`suggestion-${artist.mbid || artist.name}-${index}`">
                      <div 
                        @click="handleArtistClick(artist)"
                        class="flex items-center justify-between px-4 py-3 hover:bg-k-bg-tertiary cursor-pointer transition-colors group border-b border-k-border/30 last:border-b-0"
                        :class="{
                          'bg-k-accent/10': selectedArtist && selectedArtist.name === artist.name
                        }"
                      >
                        <!-- Artist Info -->
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-k-text-primary group-hover:text-k-accent transition-colors truncate">
                            {{ artist.name }}
                          </div>
                          <div v-if="artist.listeners" class="text-sm text-k-text-tertiary">{{ formatListeners(artist.listeners) }} listeners</div>
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
        <div class="text-sm font-medium mb-2" style="color: #1e6880;">Seed Artist:</div>
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

      <!-- Sort Controls -->
      <div v-if="similarArtists.length > 0" class="flex justify-end mb-4 mt-6">
        <div class="flex items-center gap-3 rounded-lg px-4 py-2 border border-white/10" style="background-color: rgba(47, 47, 47, 255) !important;">
          <span class="text-sm text-white/70 font-medium">Sort by:</span>
          <div class="relative z-[99999]">
            <!-- Custom Dropdown Button -->
            <button
              class="bg-white/10 text-white text-sm rounded-md px-3 py-2 pr-8  hover:border-[#9d0cc6]/50 focus:border-[#9d0cc6] focus:outline-none focus:ring-1 focus:ring-[#9d0cc6]/30 transition-all duration-200 cursor-pointer min-w-[160px] text-left"
              @click="dropdownOpen = !dropdownOpen"
            >
              {{ getSortLabel(sortBy) }}
            </button>

            <!-- Dropdown Arrow -->
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
              <svg class="w-4 h-4 text-white/60 transition-transform duration-200" :class="{ 'rotate-180': dropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </div>

            <!-- Custom Dropdown Options -->
            <div
              v-if="dropdownOpen"
              class="absolute top-full right-0 mt-1 bg-neutral-800  rounded-md shadow-lg overflow-hidden backdrop-blur-sm min-w-[160px]"
              style="z-index: 10000 !important;"
            >
              <div
                v-for="option in sortOptions"
                :key="option.value"
                class="px-3 py-2 text-sm text-white hover:bg-neutral-700 cursor-pointer transition-colors duration-150"
                :class="{ 'bg-neutral-600 text-white': sortBy === option.value }"
                @click="selectSort(option.value)"
              >
                {{ option.label }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Similar Artists Results -->
      <div v-if="isLoading" class="loading-section flex flex-col items-center justify-center py-16 space-y-4">
        <div class="relative">
          <div class="animate-spin rounded-full h-12 w-12 border-4 border-white/10" />
          <div class="absolute top-0 left-0 animate-spin rounded-full h-12 w-12 border-4 border-k-accent border-t-transparent" />
        </div>
        <div class="text-center space-y-2">
          <div class="text-k-text-primary font-medium">{{ selectedArtist ? 'Finding similar artists...' : 'Loading...' }}</div>
          <div class="text-k-text-secondary text-sm">{{ selectedArtist ? `Discovering artists like ${selectedArtist.name}` : 'Please wait' }}</div>
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
        <h3 class="text-lg font-semibold mb-4">
          Similar Artists ({{ filteredArtists.length }})
          <span v-if="loadingPageListeners" class="loading-dots text-orange-400 text-sm ml-2">Loading listeners<span class="dots" /></span>
        </h3>

        <div class="bg-white/5 rounded-lg overflow-hidden relative z-10">
          <div class="overflow-x-auto">
            <table class="w-full relative z-10">
              <thead>
                <tr class="border-b border-white/10">
                  <th class="text-left p-3 font-medium">#</th>
                  <th class="text-left p-3 font-medium w-12">Ban</th>
                  <th class="text-left p-3 font-medium">Artist</th>
                  <th class="text-left p-3 font-medium">Listeners</th>
                  <th class="text-left p-3 font-medium">Streams</th>
                  <th class="text-left p-3 font-medium">S/L Ratio</th>
                  <th class="text-left p-3 font-medium">Match</th>
                  <th class="text-left p-3 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(artist, index) in displayedArtists" :key="`displayed-${artist.mbid || artist.name}-${index}`">
                  <tr
                    class="hover:bg-white/5 transition h-16 border-b border-white/5 artist-row"
                    :style="{ animationDelay: `${index * 50}ms` }"
                  >
                    <!-- Index -->
                    <td class="p-3 align-middle">
                      <span class="text-white/60">{{ index + 1 }}</span>
                    </td>

                    <!-- Ban Button -->
                    <td class="p-3 align-middle">
                      <button
                        class="p-2 text-red-400 hover:text-red-300 hover:bg-red-400/10 rounded-full transition-colors"
                        title="Ban this artist"
                        @click="banArtist(artist)"
                      >
                        <Icon :icon="faBan" class="w-4 h-4" />
                      </button>
                    </td>

                    <!-- Artist Name -->
                    <td class="p-3 align-middle">
                      <div class="font-medium text-white">{{ artist.name }}</div>
                    </td>

                    <!-- Listeners -->
                    <td class="p-3 align-middle">
                      <span v-if="artist.listeners" class="text-white/80">{{ formatListeners(artist.listeners) }}</span>
                      <span v-else-if="loadingListeners.has(artist.mbid)" class="loading-dots text-orange-400">Loading<span class="dots" /></span>
                      <span v-else class="text-white/30">-</span>
                    </td>

                    <!-- Streams/Playcount -->
                    <td class="p-3 align-middle">
                      <span v-if="artist.playcount" class="text-white/80">{{ formatPlaycount(artist.playcount) }}</span>
                      <span v-else-if="loadingListeners.has(artist.mbid)" class="loading-dots text-orange-400">Loading<span class="dots" /></span>
                      <span v-else class="text-white/30">-</span>
                    </td>

                    <!-- S/L Ratio -->
                    <td class="p-3 align-middle">
                      <span v-if="artist.listeners && artist.playcount" class="text-white/80">{{ calculateSLRatio(artist.playcount, artist.listeners) }}</span>
                      <span v-else class="text-white/30">-</span>
                    </td>

                    <!-- Match Score -->
                    <td class="p-3 align-middle">
                      <span class="text-k-accent font-medium">{{ Math.round(parseFloat(artist.match) * 100) }}%</span>
                    </td>

                    <!-- Actions -->
                    <td class="p-3 align-middle">
                      <div class="flex gap-2 relative z-0">
                        <button
                          class="px-3 py-1.5 bg-[#9d0cc6] rounded text-sm font-medium transition relative z-0"
                          title="Find Similar Artists"
                          @click="findSimilarArtists(artist)"
                        >
                          Similars
                        </button>
                        <button
                          :disabled="loadingPreviewArtist === artist.name"
                          :class="{
                            'bg-red-600 hover:bg-red-700': currentlyPreviewingArtist === artist.name,
                            'bg-orange-500': loadingPreviewArtist === artist.name,
                            'bg-gray-600 hover:bg-gray-500': currentlyPreviewingArtist !== artist.name && loadingPreviewArtist !== artist.name,
                            'opacity-50': loadingPreviewArtist === artist.name,
                          }"
                          class="px-3 py-1.5 text-white rounded text-sm font-medium transition flex items-center gap-1 disabled:opacity-50 relative z-0"
                          @click="previewArtist(artist)"
                        >
                          <span v-if="loadingPreviewArtist === artist.name" class="loading-spinner" />
                          {{ loadingPreviewArtist === artist.name ? 'Loading...' : (currentlyPreviewingArtist === artist.name ? 'Close' : 'Preview') }}
                        </button>
                      </div>
                    </td>
                  </tr>

                  <!-- Spotify Preview Section -->
                  <tr v-if="artist.spotifyTracks && artist.spotifyTracks.length > 0" class="border-b border-white/5">
                    <td colspan="8" class="p-0 overflow-hidden">
                      <div class="spotify-player-container bg-green-50/5 p-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                          <div
                            v-for="track in artist.spotifyTracks.slice(0, 3)"
                            :key="track.id"
                            class="spotify-embed-container"
                          >
                            <!-- Spotify oEmbed Player Only -->
                            <div
                              v-if="track.oembed && track.oembed.html"
                              class="spotify-oembed"
                              v-html="track.oembed.html"
                            />
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination Controls at Bottom -->
        <div v-if="totalPages > 1" class="pagination-section flex items-center justify-center gap-2 mt-8">
          <button
            :disabled="currentPage === 1"
            class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
            @click="previousPage"
          >
            Previous
          </button>

          <div class="flex items-center gap-1">
            <button
              v-for="page in getVisiblePages()"
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
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages }"
            @click="nextPage"
          >
            Next
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
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { http } from '@/services/http'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { faBan, faCheck, faMusic, faSearch, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

// Initialize blacklist filtering (but Similar Artists section remains UNFILTERED by design)
const { 
  addArtistToBlacklist,
  loadBlacklistedItems 
} = useBlacklistFiltering()

interface LastfmArtist {
  name: string
  mbid: string
  url: string
  image: Array<{ '#text': string, 'size': string }>
  listeners?: string
  playcount?: string
  match?: string
  spotifyTracks?: SpotifyTrack[]
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

// Selected artist and results
const selectedArtist = ref<LastfmArtist | null>(null)
const similarArtists = ref<LastfmArtist[]>([])
const filteredArtists = ref<LastfmArtist[]>([])
const displayedArtists = ref<LastfmArtist[]>([])

// Pagination
const currentPage = ref(1)
const itemsPerPage = 20
const totalPages = computed(() => Math.ceil(filteredArtists.value.length / itemsPerPage))

// Loading states
const isLoading = ref(false)
const loadingListeners = ref(new Set<string>())
const loadingPageListeners = ref(false)
const errorMessage = ref('')

// Preview management
const currentlyPreviewingArtist = ref<string | null>(null)
const loadingPreviewArtist = ref<string | null>(null)

// Sorting
const sortBy = ref('match')
const dropdownOpen = ref(false)

// Banned artists tracking
const bannedArtists = ref(new Set<string>()) // Store MBIDs of banned artists

// Sort options
const sortOptions = [
  { value: 'match', label: 'Best Matches' },
  { value: 'listeners-desc', label: 'Most Listeners' },
  { value: 'listeners-asc', label: 'Least Listeners' },
  { value: 'ratio-desc', label: 'Best Ratio' },
]

// Search functionality
const onSearchInput = () => {
  // Clear existing timeout
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }

  // Clear results immediately if query is empty
  if (!searchQuery.value.trim()) {
    searchResults.value = []
    return
  }

  // Set new timeout for search
  searchTimeout.value = setTimeout(() => {
    searchArtists()
  }, 500) // Wait 500ms after user stops typing
}


const searchArtists = async () => {
  if (!searchQuery.value.trim()) {
    return
  }

  searchLoading.value = true
  
  try {
    const response = await http.get('similar-artists/search', {
      params: { query: searchQuery.value },
    })

    if (response.success && response.data) {
      // Filter out invalid results and sort: prioritize artists with MBIDs, then by name
      const validResults = response.data.filter(artist => {
        // Must have a name
        if (!artist || !artist.name || typeof artist.name !== 'string') {
          return false
        }
        return true
      })

      const sortedResults = validResults.sort((a, b) => {
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

        // Second priority: artists with MBIDs (for similarity search capability)
        const aMbid = a.mbid && a.mbid.trim()
        const bMbid = b.mbid && b.mbid.trim()

        if (aMbid && !bMbid) {
          return -1
        }
        if (!aMbid && bMbid) {
          return 1
        }

        // Third priority: listener count (higher first)
        const aListeners = Number.parseInt(a.listeners || '0', 10)
        const bListeners = Number.parseInt(b.listeners || '0', 10)

        if (aListeners !== bListeners) {
          return bListeners - aListeners
        }

        // Final priority: alphabetical by name
        return a.name.localeCompare(b.name)
      })

      searchResults.value = sortedResults
    } else {
      searchResults.value = []
    }
  } catch (error: any) {
    console.error('Search error:', error)
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
  currentPage.value = 1
  currentlyPreviewingArtist.value = null
  errorMessage.value = ''

  // Automatically find similar artists if the artist has an MBID
  if (artist.mbid && artist.mbid.trim()) {
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
  currentPage.value = 1
  currentlyPreviewingArtist.value = null
  errorMessage.value = ''
}

const banArtist = async (artist: LastfmArtist) => {
  try {
    console.log('🚫 Banning artist globally:', artist.name)

    // Add to local banned artists set for Similar Artists section
    bannedArtists.value.add(artist.mbid)

    // Add to global blacklist (this will affect ALL other sections)
    addArtistToBlacklist(artist.name)

    // Save to backend API for persistence across sessions
    try {
      const response = await http.post('music-preferences/blacklist-artist', {
        artist_name: artist.name,
        spotify_artist_id: artist.mbid || `lastfm:${artist.name}` // Use MBID or create identifier
      })
      console.log('✅ Artist saved to global blacklist API:', response)
    } catch (apiError: any) {
      console.error('❌ Failed to save to API:', apiError)
      console.error('❌ API Error details:', apiError.response?.data || apiError.message)
      // Show error to user so they know it failed
      errorMessage.value = `Failed to save to preferences: ${apiError.response?.data?.message || apiError.message}`
    }

    // Save to localStorage for persistence (local Similar Artists filtering)
    localStorage.setItem('koel-banned-artists', JSON.stringify(Array.from(bannedArtists.value)))

    // Remove from current Similar Artists results (local to this section only)
    const updatedArtists = similarArtists.value.filter(a => a.mbid !== artist.mbid)
    similarArtists.value = updatedArtists
    filteredArtists.value = updatedArtists
    updateDisplayedArtists()

    console.log(`🚫 Artist "${artist.name}" has been banned globally and locally`)
  } catch (error: any) {
    console.error('Failed to ban artist:', error)
    errorMessage.value = `Failed to ban artist: ${error.message || 'Unknown error'}`
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

  if (!targetArtist.mbid || !targetArtist.mbid.trim()) {
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
    // Get similar artists from Last.fm
    const response = await http.get('similar-artists/similar', {
      params: { mbid: targetArtist.mbid },
    })

    if (response.success && response.data) {
      // Filter out artists without MBID and banned artists
      const artistsWithMbid = response.data.filter(artist =>
        artist.mbid
        && artist.mbid.trim()
        && !bannedArtists.value.has(artist.mbid),
      )

      similarArtists.value = artistsWithMbid
      filteredArtists.value = artistsWithMbid
      currentPage.value = 1

      // Apply initial sorting first (without listeners data)
      sortArtists()

      // Load listeners count for the first page only
      await loadPageListenersCounts()
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

// Load listeners counts for current page artists only
const loadPageListenersCounts = async () => {
  const startIndex = (currentPage.value - 1) * itemsPerPage
  const endIndex = startIndex + itemsPerPage
  const pageArtists = filteredArtists.value.slice(startIndex, endIndex)
  const artistsWithMbids = pageArtists.filter(artist => artist.mbid && artist.mbid.trim() && !artist.listeners)

  if (artistsWithMbids.length === 0) {
    return
  }

  loadingPageListeners.value = true

  // Mark artists as loading
  artistsWithMbids.forEach(artist => {
    loadingListeners.value.add(artist.mbid!)
  })

  try {
    const mbids = artistsWithMbids.map(artist => artist.mbid!)
    const response = await http.post('similar-artists/batch-listeners', {
      mbids,
    })

    if (response.success && response.data) {
      // Update artists with listeners count and playcount
      Object.entries(response.data).forEach(([mbid, data]: [string, any]) => {
        const artist = similarArtists.value.find(a => a.mbid === mbid)
        if (artist && data.listeners) {
          artist.listeners = data.listeners
          artist.playcount = data.playcount
        }
      })

      // Update displayed artists
      updateDisplayedArtists()
    }
  } catch (error: any) {
    console.error('Failed to load listeners counts:', error)
    // Continue without listener data - don't block the UI
  } finally {
    // Clear loading states
    artistsWithMbids.forEach(artist => {
      loadingListeners.value.delete(artist.mbid!)
    })
    loadingPageListeners.value = false
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

  // Set loading state
  loadingPreviewArtist.value = artist.name

  try {
    const response = await http.get('similar-artists/spotify-preview', {
      params: { artist_name: artist.name },
    })

    if (response.success && response.data && response.data.tracks.length > 0) {
      // Add Spotify tracks to the artist object
      artist.spotifyTracks = response.data.tracks
      currentlyPreviewingArtist.value = artist.name

      // Stop any currently playing Spotify tracks
      stopAllSpotifyPlayers()
    }
  } catch (error: any) {
    console.error('Failed to get Spotify preview for', artist.name, error)
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

const formatListeners = (listeners: string | number): string => {
  const num = typeof listeners === 'string' ? Number.parseInt(listeners, 10) : listeners
  if (num >= 1000000) {
    return `${(num / 1000000).toFixed(1)}M`
  } else if (num >= 1000) {
    return `${(num / 1000).toFixed(1)}K`
  }
  return num.toString()
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

// Pagination functionality
const goToPage = async (page: number) => {
  if (page < 1 || page > totalPages.value) {
    return
  }

  // Close any open previews when changing pages
  currentlyPreviewingArtist.value = null

  currentPage.value = page
  updateDisplayedArtists()

  // Load listener data for the new page
  await loadPageListenersCounts()
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    goToPage(currentPage.value + 1)
  }
}

const previousPage = () => {
  if (currentPage.value > 1) {
    goToPage(currentPage.value - 1)
  }
}

const updateDisplayedArtists = () => {
  const startIndex = (currentPage.value - 1) * itemsPerPage
  const endIndex = startIndex + itemsPerPage
  displayedArtists.value = filteredArtists.value.slice(startIndex, endIndex)
}

const getVisiblePages = () => {
  const pages = []
  const maxVisible = 5
  const total = totalPages.value

  if (total <= maxVisible) {
    // Show all pages if total is small
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Show pages around current page
    const current = currentPage.value
    let start = Math.max(1, current - 2)
    let end = Math.min(total, current + 2)

    // Adjust if we're near the beginning or end
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
}

// Sorting and filtering
const sortArtists = () => {
  const sorted = [...filteredArtists.value]

  switch (sortBy.value) {
    case 'match':
      sorted.sort((a, b) => Number.parseFloat(b.match || '0') - Number.parseFloat(a.match || '0'))
      break
    case 'listeners-desc':
      sorted.sort((a, b) => {
        const aListeners = Number.parseInt(a.listeners || '0', 10)
        const bListeners = Number.parseInt(b.listeners || '0', 10)
        return bListeners - aListeners
      })
      break
    case 'listeners-asc':
      sorted.sort((a, b) => {
        const aListeners = Number.parseInt(a.listeners || '0', 10)
        const bListeners = Number.parseInt(b.listeners || '0', 10)
        return aListeners - bListeners
      })
      break
    case 'ratio-desc':
      sorted.sort((a, b) => {
        const aRatio = (a.listeners && a.playcount)
          ? Number.parseInt(a.playcount, 10) / Number.parseInt(a.listeners, 10)
          : 0
        const bRatio = (b.listeners && b.playcount)
          ? Number.parseInt(b.playcount, 10) / Number.parseInt(b.listeners, 10)
          : 0
        return bRatio - aRatio
      })
      break
  }

  filteredArtists.value = sorted
  // Don't reset page when sorting changes - keep current page
  updateDisplayedArtists()
}

const onSortChange = async () => {
  sortArtists()
  // Load listeners data for the new first page
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

// Load banned artists from localStorage
const loadBannedArtists = () => {
  try {
    const savedBanned = localStorage.getItem('koel-banned-artists')
    if (savedBanned) {
      const bannedArray = JSON.parse(savedBanned)
      bannedArtists.value = new Set(bannedArray)
    }
  } catch (error) {
    console.error('Failed to load banned artists:', error)
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  loadBannedArtists()
  // Load global blacklisted items (but don't filter Similar Artists results)
  loadBlacklistedItems()
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
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

/* Similar artists table animations */
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

/* Spotify player container */
.spotify-player-container {
  animation: slideDown 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-20px);
    max-height: 0;
  }
  to {
    opacity: 1;
    transform: translateY(0);
    max-height: 400px;
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

/* Clean Spotify oembed styling - use native oEmbed height */
.spotify-oembed iframe {
  width: calc(100% + 4px) !important;
  height: 152px !important;
  border-radius: 10px !important;
  border: none !important;
  overflow: hidden !important;
  display: block !important;
  margin: -2px 0 0 -2px !important;
  transform: scale(1.01) !important;
}

.spotify-oembed {
  width: 100%;
  height: 148px;
  overflow: hidden;
  border-radius: 10px;
  position: relative;
  background: transparent;
}

.spotify-embed-container {
  /* Just a clean container with no visible styling */
  width: 100%;
  min-height: 148px;
  overflow: hidden;
  border-radius: 10px;
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
</style>
