<template>
    <div class="seed-selection mb-8">
      <!-- Search Container -->
      <div class="search-container mb-4">
        <div class="flex items-center gap-3">
          <div class="relative flex-1">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search for a seed track..."
              class="search-input w-full px-4 py-3 bg-k-bg-secondary border border-k-border rounded-lg text-k-text-primary placeholder-k-text-secondary focus:border-k-accent focus:outline-none"
              @input="onSearchInput"
              @keypress.enter="searchTracks"
            >
            <!-- Search Icon -->
            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
              <Icon :icon="faSearch" class="w-5 h-5 text-k-text-secondary" />
            </div>
          </div>
        </div>
      </div>
  
      <!-- Selected Seed Track Display -->
      <div v-if="selectedTrack" class="selected-seed mb-6">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-k-accent font-medium text-lg">Selected Seed Track</h3>
          <Btn
            size="sm"
            red
            @click="clearSeedTrack"
          >
            <Icon :icon="faTimes" class="w-4 h-4 mr-2" />
            Clear
          </Btn>
        </div>
        <TrackCard :track="selectedTrack" />
      </div>
  
      <!-- Search Results -->
      <div v-if="searchResults.length > 0" class="search-results">
        <h3 class="text-k-accent font-medium text-lg mb-4">Search Results</h3>
        <div class="space-y-3">
          <div
            v-for="track in searchResults"
            :key="track.id"
            class="cursor-pointer"
            @click="selectSeedTrack(track)"
          >
            <TrackCard :track="track" />
          </div>
        </div>
      </div>
  
      <!-- Loading State -->
      <div v-if="isSearching" class="search-loading">
        <div class="flex items-center justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-k-accent"></div>
          <span class="ml-3 text-k-text-secondary">Searching for tracks...</span>
        </div>
      </div>
  
      <!-- Empty State -->
      <div v-if="!selectedTrack && !isSearching && searchResults.length === 0 && searchQuery.trim()" class="empty-state">
        <div class="text-center py-8">
          <Icon :icon="faSearch" class="w-12 h-12 text-k-text-tertiary mx-auto mb-4" />
          <p class="text-k-text-secondary text-lg">No tracks found</p>
          <p class="text-k-text-tertiary text-sm">Try a different search term</p>
        </div>
      </div>
  
      <!-- Search Error -->
      <div v-if="searchError" class="search-error mt-4">
        <div class="bg-red-900/20 border border-red-500/30 rounded-lg p-4">
          <div class="flex items-center">
            <Icon :icon="faExclamationTriangle" class="w-5 h-5 text-red-400 mr-3" />
            <div>
              <p class="text-red-400 font-medium">Search Error</p>
              <p class="text-red-300 text-sm">{{ searchError }}</p>
            </div>
            <Btn
              size="sm"
              class="ml-auto"
              @click="searchError = ''"
            >
              <Icon :icon="faTimes" class="w-4 h-4" />
            </Btn>
          </div>
        </div>
      </div>
  
      <!-- Instructions -->
      <div v-if="!selectedTrack && !isSearching && searchResults.length === 0 && !searchQuery.trim()" class="instructions">
        <div class="text-center py-12">
          <Icon :icon="faMusic" class="w-16 h-16 text-k-text-tertiary mx-auto mb-6" />
          <h3 class="text-k-text-primary text-xl font-medium mb-2">Find Your Seed Track</h3>
          <p class="text-k-text-secondary text-lg mb-4">
            Search for a song to use as the starting point for music discovery
          </p>
          <p class="text-k-text-tertiary text-sm">
            The algorithm will find similar tracks based on your selected parameters
          </p>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { ref, defineEmits, defineProps, withDefaults } from 'vue'
  import { faSearch, faMusic, faTimes, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'
  import { http } from '@/services/http'
  
  import Btn from '@/components/ui/form/Btn.vue'
  import TrackCard from '@/components/screens/music-discovery/TrackCard.vue'
  
  // Types
  interface Track {
    id: string
    name: string
    artist: string
    album: string
    preview_url?: string
    external_url?: string
    image?: string
    duration_ms?: number
  }
  
  // Props
  interface Props {
    selectedTrack?: Track | null
  }
  
  const props = withDefaults(defineProps<Props>(), {
    selectedTrack: null
  })
  
  // Emits
  const emit = defineEmits<{
    'update:selectedTrack': [track: Track | null]
    'trackSelected': [track: Track]
  }>()
  
  // State
  const searchQuery = ref('')
  const searchResults = ref<Track[]>([])
  const isSearching = ref(false)
  const searchError = ref('')
  let searchTimeout: NodeJS.Timeout | null = null
  
  // Methods
  const onSearchInput = () => {
    if (searchTimeout) {
      clearTimeout(searchTimeout)
    }
  
    // Debounce search
    searchTimeout = setTimeout(() => {
      if (searchQuery.value.trim().length > 2) {
        searchTracks()
      }
    }, 300)
  }
  
  const searchTracks = async () => {
    if (!searchQuery.value.trim() || isSearching.value) {
      return
    }
  
    isSearching.value = true
    searchError.value = ''
    searchResults.value = []
  
    try {
      const response = await http.post('music-discovery/search-seed', {
        query: searchQuery.value.trim(),
        limit: 20
      })
  
      console.log('🔍 Search API response:', response)
  
      if (response.success) {
        // Map album_image to image for compatibility with TrackCard
        searchResults.value = (response.data || []).map(track => ({
          ...track,
          image: track.album_image || track.image
        }))
        
        if (searchResults.value.length === 0) {
          searchError.value = 'No tracks found. Try a different search term.'
        }
      } else {
        console.error('❌ Search API returned success: false', response)
        searchError.value = 'Search failed. Please try again.'
      }
    } catch (error) {
      console.error('Search error:', error)
      searchError.value = 'Search failed. Please check your connection.'
    } finally {
      isSearching.value = false
    }
  }
  
  const selectSeedTrack = (track: Track) => {
    emit('update:selectedTrack', track)
    emit('trackSelected', track)
    
    // Clear search state
    searchResults.value = []
    searchQuery.value = ''
    searchError.value = ''
  }
  
  const clearSeedTrack = () => {
    emit('update:selectedTrack', null)
    searchResults.value = []
    searchError.value = ''
  }
  </script>
  
  <style scoped>
  .search-input:focus {
    box-shadow: 0 0 0 3px var(--color-highlight);
  }
  
  .cursor-pointer:hover {
    opacity: 0.8;
    transition: opacity 0.2s ease;
  }
  </style>