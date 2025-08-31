<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>
        Similar Artists
      </ScreenHeader>
    </template>

    <div class="similar-artists-screen">
      <!-- Artist Search Section -->
      <div class="search-section bg-k-bg-secondary rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold mb-2">Search for an Artist</h3>
        
        <div class="relative">
          <input
            v-model="searchQuery"
            @input="onSearchInput"
            @focus="showDropdown = true"
            @blur="onSearchBlur"
            type="text"
            placeholder="Type an artist name..."
            class="w-full p-3 bg-k-bg-primary border border-white/10 rounded-lg text-white placeholder-gray-400 focus:border-k-accent focus:outline-none"
          />
          
          <!-- Search Dropdown -->
          <div
            v-if="showDropdown && searchResults.length > 0"
            class="absolute z-10 w-full mt-2 bg-k-bg-primary border border-white/10 rounded-lg shadow-lg max-h-64 overflow-y-auto"
          >
            <div
              v-for="(artist, index) in searchResults"
              :key="`search-${artist.mbid || artist.name}-${index}`"
              @click="handleArtistClick(artist)"
              class="flex items-center p-3 hover:bg-white/10 cursor-pointer border-b border-white/5 last:border-b-0"
            >
              <div class="flex-1">
                <div class="font-medium">{{ artist.name }}</div>
                <div v-if="artist.listeners" class="text-sm text-gray-400">{{ formatListeners(artist.listeners) }} listeners</div>
              </div>
            </div>
          </div>
        </div>
        
        <div v-if="searchLoading" class="mt-2 text-gray-400">Searching artists...</div>
      </div>

      <!-- Selected Artist Info -->
      <div v-if="selectedArtist" class="selected-artist bg-k-bg-secondary rounded-lg p-6 mb-6">
        <h3 class="text-lg font-semibold mb-3">Selected Artist</h3>
        <div>
          <div class="text-xl font-bold">{{ selectedArtist.name }}</div>
          <div v-if="selectedArtist.listeners" class="text-gray-400">{{ formatListeners(selectedArtist.listeners) }} listeners</div>
        </div>
      </div>

      <!-- Filters and Sort -->
      <div v-if="similarArtists.length > 0" class="controls-section bg-k-bg-secondary rounded-lg p-4 mb-6">
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium">Sort by:</label>
          <select
            v-model="sortBy"
            @change="onSortChange"
            class="px-3 py-2 bg-k-bg-primary border border-white/10 rounded text-white focus:border-k-accent focus:outline-none"
          >
            <option value="match">Best Matches</option>
            <option value="listeners-desc">Most Listeners</option>
            <option value="listeners-asc">Least Listeners</option>
            <option value="ratio-desc">Best Ratio</option>
          </select>
        </div>
      </div>

      <!-- Similar Artists Results -->
      <div v-if="isLoading" class="loading-section flex flex-col items-center justify-center py-16 space-y-4">
        <div class="relative">
          <div class="animate-spin rounded-full h-12 w-12 border-4 border-white/10"></div>
          <div class="absolute top-0 left-0 animate-spin rounded-full h-12 w-12 border-4 border-k-accent border-t-transparent"></div>
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
            @click="selectedArtist && findSimilarArtists()"
            class="mt-4 px-4 py-2 bg-k-accent hover:bg-k-accent/90 text-white rounded-lg text-sm font-medium transition-colors"
          >
            Try Again
          </button>
        </div>
      </div>

      <div v-else-if="displayedArtists.length > 0" class="results-section">
        <h3 class="text-lg font-semibold mb-4">
          Similar Artists ({{ filteredArtists.length }})
          <span v-if="loadingPageListeners" class="loading-dots text-orange-400 text-sm ml-2">Loading listeners<span class="dots"></span></span>
        </h3>
        
        <div class="similar-artists-table bg-k-bg-secondary rounded-lg overflow-hidden">
          <!-- Table Header -->
          <div class="table-header bg-k-bg-primary px-4 py-3 grid grid-cols-12 gap-2 text-sm font-medium text-gray-300">
            <div class="col-span-3">Artist</div>
            <div class="col-span-2 text-center">Listeners</div>
            <div class="col-span-2 text-center">Streams</div>
            <div class="col-span-1 text-center">S/L</div>
            <div class="col-span-2 text-center">Match</div>
            <div class="col-span-2 text-right">Actions</div>
          </div>
          
          <!-- Table Body -->
          <div class="table-body">
            <div
              v-for="(artist, index) in displayedArtists"
              :key="`displayed-${artist.mbid || artist.name}-${index}`"
              class="artist-row border-b border-white/5 last:border-b-0"
              :style="{ animationDelay: `${index * 50}ms` }"
            >
              <div class="px-4 py-3 grid grid-cols-12 gap-2 items-center hover:bg-white/5 transition-colors">
                <!-- Artist Name -->
                <div class="col-span-3">
                  <div class="text-lg font-semibold text-white">{{ artist.name }}</div>
                </div>
                
                <!-- Listeners -->
                <div class="col-span-2 text-center">
                  <span v-if="artist.listeners" class="text-gray-300">{{ formatListeners(artist.listeners) }}</span>
                  <span v-else-if="loadingListeners.has(artist.mbid)" class="loading-dots text-orange-400">Loading<span class="dots"></span></span>
                  <span v-else class="text-gray-500">-</span>
                </div>
                
                <!-- Streams/Playcount -->
                <div class="col-span-2 text-center">
                  <span v-if="artist.playcount" class="text-gray-300">{{ formatPlaycount(artist.playcount) }}</span>
                  <span v-else-if="loadingListeners.has(artist.mbid)" class="loading-dots text-orange-400">Loading<span class="dots"></span></span>
                  <span v-else class="text-gray-500">-</span>
                </div>
                
                <!-- S/L Ratio -->
                <div class="col-span-1 text-center">
                  <span v-if="artist.listeners && artist.playcount" class="text-gray-300 font-medium">{{ calculateSLRatio(artist.playcount, artist.listeners) }}</span>
                  <span v-else class="text-gray-500">-</span>
                </div>
                
                <!-- Match Score -->
                <div class="col-span-2 text-center">
                  <span class="text-k-accent font-medium">{{ Math.round(parseFloat(artist.match) * 100) }}%</span>
                </div>
                
                <!-- Actions -->
                <div class="col-span-2 flex gap-2 justify-end">
                  <button
                    v-if="artist.mbid"
                    @click="findSimilarArtists(artist)"
                    class="px-3 py-1 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors text-sm"
                  >
                    Similars
                  </button>
                  <button
                    @click="previewArtist(artist)"
                    :disabled="loadingPreviewArtist === artist.name"
                    :class="{
                      'bg-red-500 hover:bg-red-600': currentlyPreviewingArtist === artist.name,
                      'bg-orange-500': loadingPreviewArtist === artist.name,
                      'bg-k-accent hover:bg-k-accent/80': currentlyPreviewingArtist !== artist.name && loadingPreviewArtist !== artist.name,
                      'opacity-75 cursor-not-allowed': loadingPreviewArtist === artist.name
                    }"
                    class="px-3 py-1 text-white rounded transition-colors flex items-center gap-2 text-sm"
                  >
                    <span v-if="loadingPreviewArtist === artist.name" class="loading-spinner"></span>
                    {{ loadingPreviewArtist === artist.name ? 'Loading...' : (currentlyPreviewingArtist === artist.name ? 'Close' : 'Preview') }}
                  </button>
                </div>
              </div>
              
              <!-- Spotify Preview Section -->
              <div v-if="artist.spotifyTracks && artist.spotifyTracks.length > 0" class="px-4 pb-4">
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
                    ></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Pagination Controls at Bottom -->
        <div v-if="totalPages > 1" class="pagination-section flex items-center justify-center gap-2 mt-8">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }"
          >
            Previous
          </button>
          
          <div class="flex items-center gap-1">
            <button
              v-for="page in getVisiblePages()"
              :key="page"
              @click="goToPage(page)"
              :class="page === currentPage ? 'bg-k-accent text-white' : 'bg-k-bg-primary text-gray-300 hover:bg-white/10'"
              class="w-10 h-10 flex items-center justify-center rounded transition-colors"
            >
              {{ page }}
            </button>
          </div>
          
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="px-3 py-2 bg-k-bg-primary text-white rounded hover:bg-white/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages }"
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
import { ref, computed, onMounted } from 'vue'
import { http } from '@/services/http'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

interface LastfmArtist {
  name: string
  mbid: string
  url: string
  image: Array<{ '#text': string; size: string }>
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
const showDropdown = ref(false)
const searchTimeout = ref<number>()

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

// Search functionality
const onSearchInput = () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value)
  }
  
  if (!searchQuery.value.trim()) {
    searchResults.value = []
    showDropdown.value = false
    return
  }
  
  searchTimeout.value = setTimeout(() => {
    searchArtists()
  }, 1000)
}

const searchArtists = async () => {
  if (!searchQuery.value.trim()) return
  
  searchLoading.value = true
  try {
    const response = await http.get('similar-artists/search', {
      params: { query: searchQuery.value }
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
        
        if (aExact && !bExact) return -1
        if (!aExact && bExact) return 1
        
        // Second priority: artists with MBIDs (for similarity search capability)
        const aMbid = a.mbid && a.mbid.trim()
        const bMbid = b.mbid && b.mbid.trim()
        
        if (aMbid && !bMbid) return -1
        if (!aMbid && bMbid) return 1
        
        // Third priority: listener count (higher first)
        const aListeners = parseInt(a.listeners || '0')
        const bListeners = parseInt(b.listeners || '0')
        
        if (aListeners !== bListeners) {
          return bListeners - aListeners
        }
        
        // Final priority: alphabetical by name
        return a.name.localeCompare(b.name)
      })
      
      searchResults.value = sortedResults.slice(0, 10) // Limit to 10 results
      showDropdown.value = searchResults.value.length > 0
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

const onSearchBlur = () => {
  // Delay hiding dropdown to allow clicks
  setTimeout(() => {
    showDropdown.value = false
  }, 500)
}

const handleArtistClick = (artist: LastfmArtist) => {
  selectArtist(artist)
}

const selectArtist = (artist: LastfmArtist) => {
  selectedArtist.value = artist
  searchQuery.value = artist.name || 'Unknown Artist'
  searchResults.value = []
  showDropdown.value = false
  
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
      params: { mbid: targetArtist.mbid }
    })
    
    if (response.success && response.data) {
      similarArtists.value = response.data
      filteredArtists.value = response.data
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
      mbids: mbids
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
      params: { artist_name: artist.name }
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
        command: 'pause'
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
            command: 'pause'
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
  const num = typeof listeners === 'string' ? parseInt(listeners) : listeners
  if (num >= 1000000) {
    return `${(num / 1000000).toFixed(1)}M`
  } else if (num >= 1000) {
    return `${(num / 1000).toFixed(1)}K`
  }
  return num.toString()
}

const formatPlaycount = (playcount: string | number): string => {
  const num = typeof playcount === 'string' ? parseInt(playcount) : playcount
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
  const streams = typeof playcount === 'string' ? parseInt(playcount) : playcount
  const uniqueListeners = typeof listeners === 'string' ? parseInt(listeners) : listeners
  
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
  if (page < 1 || page > totalPages.value) return
  
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
      sorted.sort((a, b) => parseFloat(b.match || '0') - parseFloat(a.match || '0'))
      break
    case 'listeners-desc':
      sorted.sort((a, b) => {
        const aListeners = parseInt(a.listeners || '0')
        const bListeners = parseInt(b.listeners || '0')
        return bListeners - aListeners
      })
      break
    case 'listeners-asc':
      sorted.sort((a, b) => {
        const aListeners = parseInt(a.listeners || '0')
        const bListeners = parseInt(b.listeners || '0')
        return aListeners - bListeners
      })
      break
    case 'ratio-desc':
      sorted.sort((a, b) => {
        const aRatio = (a.listeners && a.playcount) 
          ? parseInt(a.playcount) / parseInt(a.listeners) 
          : 0
        const bRatio = (b.listeners && b.playcount) 
          ? parseInt(b.playcount) / parseInt(b.listeners) 
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

/* Similar artists table styling */
.similar-artists-table {
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.table-header {
  font-weight: 600;
  letter-spacing: 0.025em;
}

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

.artist-row:hover .text-white {
  color: var(--color-text-primary);
}

/* Responsive table adjustments */
@media (max-width: 768px) {
  .table-header,
  .artist-row .grid {
    grid-template-columns: 1fr auto auto;
    gap: 2;
  }
  
  .table-header .col-span-4,
  .artist-row .col-span-4 {
    grid-column: span 1;
  }
  
  .table-header .col-span-2,
  .artist-row .col-span-2 {
    grid-column: span 1;
  }
  
  .table-header div:nth-child(2),
  .table-header div:nth-child(3) {
    display: none;
  }
  
  .artist-row .col-span-2:nth-child(2),
  .artist-row .col-span-2:nth-child(3) {
    display: none;
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

/* Clean Spotify oembed styling - crop aggressively to remove borders */
.spotify-oembed iframe {
  width: calc(100% + 4px) !important;
  height: 154px !important;
  border-radius: 10px !important;
  border: none !important;
  overflow: hidden !important;
  display: block !important;
  margin: -2px 0 0 -2px !important;
  transform: scale(1.01) !important;
}

.spotify-oembed {
  width: 100%;
  height: 150px;
  overflow: hidden;
  border-radius: 10px;
  position: relative;
  background: transparent;
}

.spotify-embed-container {
  /* Just a clean container with no visible styling */
  width: 100%;
  min-height: 150px;
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
  0% { content: ''; }
  25% { content: '.'; }
  50% { content: '..'; }
  75% { content: '...'; }
  100% { content: ''; }
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