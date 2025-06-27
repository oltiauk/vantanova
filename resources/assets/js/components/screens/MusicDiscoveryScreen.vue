<template>
    <ScreenBase>
      <template #header>
        <ScreenHeader>
          Discover Music
          <template #meta>
            <span class="text-k-text-secondary">Find new music based on your preferences</span>
          </template>
        </ScreenHeader>
      </template>
  
      <div class="music-discovery-screen">
        <!-- Seed Track Selection -->
        <div class="seed-selection mb-8">
          <div class="search-container mb-4">
            <input
              v-model="searchQuery"
              @input="onSearchInput"
              @keypress.enter="searchTracks"
              type="text"
              placeholder="Search for a seed track..."
              class="search-input w-full px-4 py-3 bg-k-bg-secondary border border-k-border rounded-lg text-k-text-primary placeholder-k-text-secondary focus:border-k-accent focus:outline-none"
            />
            <Btn
              @click="searchTracks"
              :disabled="!searchQuery.trim() || isSearching"
              class="mt-2"
              green
            >
              {{ isSearching ? 'Searching...' : 'Search' }}
            </Btn>
          </div>
  
          <!-- Search Results -->
          <div v-if="searchResults.length > 0" class="search-results mb-4">
            <h3 class="text-lg font-medium text-k-text-primary mb-3">Select a seed track ({{ searchResults.length }} found):</h3>
            <div class="results-list max-h-60 overflow-y-auto space-y-2">
              <div
                v-for="track in searchResults"
                :key="track.id"
                @click="selectSeedTrack(track)"
                class="result-item flex items-center p-3 bg-k-bg-secondary hover:bg-k-bg-tertiary cursor-pointer rounded-lg transition-colors"
              >
                <img
                  v-if="track.album_image"
                  :src="track.album_image"
                  :alt="track.album"
                  class="w-12 h-12 rounded mr-3"
                />
                <div class="flex-1">
                  <div class="text-k-text-primary font-medium">{{ track.name }}</div>
                  <div class="text-k-text-secondary text-sm">{{ track.artist }} • {{ track.album }}</div>
                </div>
                <div class="text-k-text-secondary text-sm">{{ track.duration }}</div>
              </div>
            </div>
          </div>
  
          <!-- Selected Seed Track -->
          <div v-if="selectedSeedTrack" class="selected-seed">
            <div class="seed-track-display flex items-center justify-between p-4 bg-k-bg-secondary rounded-lg border-2 border-k-accent">
              <div class="flex items-center">
                <span class="text-k-accent font-medium mr-2">Seed Track:</span>
                <span class="text-k-text-primary">{{ selectedSeedTrack.name }} - {{ selectedSeedTrack.artist }}</span>
              </div>
              <Btn
                @click="clearSeedTrack"
                class="!p-1"
                small
                orange
              >
                ✕
              </Btn>
            </div>
          </div>
        </div>
  
        <!-- Parameters Section -->
        <div v-if="selectedSeedTrack" class="parameters-section mb-8">
          <div class="parameters-container p-6 bg-k-bg-secondary rounded-lg border border-k-border">
            <h3 class="text-lg font-medium text-k-text-primary mb-4">Discovery Parameters</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              
              <!-- BPM Range -->
              <div class="parameter-group">
                <label class="block text-k-accent font-medium mb-3">BPM Range</label>
                <div class="flex items-center space-x-4">
                  <input
                    v-model.number="parameters.bpm_min"
                    type="number"
                    min="60"
                    max="200"
                    class="w-20 px-2 py-1 bg-k-bg-tertiary border border-k-border rounded text-k-text-primary text-center"
                  />
                  <div class="flex-1 relative">
                    <input
                      v-model.number="parameters.bpm_max"
                      type="range"
                      min="60"
                      max="200"
                      class="w-full accent-k-accent"
                    />
                  </div>
                  <input
                    v-model.number="parameters.bmp_max"
                    type="number"
                    min="60"
                    max="200"
                    class="w-20 px-2 py-1 bg-k-bg-tertiary border border-k-border rounded text-k-text-primary text-center"
                  />
                </div>
                <div class="flex justify-between text-xs text-k-text-secondary mt-1">
                  <span>{{ parameters.bpm_min }}</span>
                  <span>{{ parameters.bpm_max }}</span>
                </div>
              </div>
  
              <!-- Popularity -->
              <div class="parameter-group">
                <label class="block text-k-accent font-medium mb-3">Popularity</label>
                <div class="flex items-center space-x-4">
                  <input
                    v-model.number="parameters.popularity"
                    type="range"
                    min="0"
                    max="100"
                    class="flex-1 accent-k-accent"
                  />
                  <span class="w-12 text-k-text-primary text-center">{{ parameters.popularity }}</span>
                </div>
              </div>
  
              <!-- Key Compatibility -->
              <div class="parameter-group">
                <label class="block text-k-accent font-medium mb-3">Key Compatibility</label>
                <div class="flex items-center">
                  <CheckBox v-model="parameters.key_compatibility" />
                  <span class="ml-2 text-k-text-secondary text-sm">Match musical key</span>
                </div>
              </div>
            </div>
  
            <!-- Discover Button -->
            <div class="mt-6 text-center">
              <Btn
                @click="discoverMusic"
                :disabled="isDiscovering"
                green
                class="px-8"
              >
                {{ isDiscovering ? 'Finding Similar Tracks...' : 'Find Similar Tracks' }}
              </Btn>
            </div>
          </div>
        </div>
  
        <!-- Results Section -->
        <div v-if="recommendations.length > 0" class="results-section">
          <h2 class="text-xl font-bold text-k-text-primary mb-4">Recommendations</h2>
          
          <!-- Simple results table instead of SongList for now -->
          <div class="results-table bg-k-bg-secondary rounded-lg overflow-hidden">
            <!-- Table Header -->
            <div class="table-header grid grid-cols-12 gap-4 p-4 bg-k-bg-tertiary text-k-text-secondary text-sm font-medium">
              <div class="col-span-1">#</div>
              <div class="col-span-4">Title</div>
              <div class="col-span-3">Artist</div>
              <div class="col-span-3">Album</div>
              <div class="col-span-1">⏱</div>
            </div>
  
            <!-- Table Body -->
            <div class="table-body">
              <div
                v-for="(track, index) in recommendations"
                :key="track.id"
                @click="playPreview(track)"
                class="track-row grid grid-cols-12 gap-4 p-4 hover:bg-k-bg-tertiary cursor-pointer border-b border-k-border last:border-b-0"
              >
                <div class="col-span-1 text-k-text-secondary">{{ index + 1 }}</div>
                <div class="col-span-4 flex items-center">
                  <img
                    v-if="track.album_image"
                    :src="track.album_image"
                    :alt="track.album"
                    class="w-10 h-10 rounded mr-3"
                  />
                  <div>
                    <div class="text-k-text-primary font-medium">{{ track.name }}</div>
                    <div v-if="track.preview_url" class="text-xs text-k-accent">Preview available</div>
                  </div>
                </div>
                <div class="col-span-3 text-k-text-primary">{{ track.artist }}</div>
                <div class="col-span-3 text-k-text-primary">{{ track.album }}</div>
                <div class="col-span-1 text-k-text-secondary text-sm">{{ track.duration }}</div>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Loading States -->
        <div v-if="isDiscovering" class="loading-state text-center py-8">
          <div class="text-k-text-primary">Finding recommendations...</div>
        </div>
  
        <!-- Error Messages -->
        <div v-if="errorMessage" class="error-message p-4 bg-red-800 text-white rounded-lg mb-4">
          {{ errorMessage }}
        </div>
      </div>
    </ScreenBase>
  </template>
  
  <script setup lang="ts">
  import { ref, reactive, watch } from 'vue'
  import { http } from '@/services/http'
  
  import ScreenBase from "@/components/screens/ScreenBase.vue";
  import ScreenHeader from '@/components/ui/ScreenHeader.vue'
  import Btn from '@/components/ui/form/Btn.vue'
  import CheckBox from '@/components/ui/form/CheckBox.vue'
  
  interface Track {
    id: string
    name: string
    artist: string
    artists: string[]
    album: string
    album_image: string | null
    duration: string
    duration_ms: number
    preview_url: string | null
    external_url: string | null
    popularity: number
    release_date: string | null
  }
  
  interface Parameters {
    bpm_min: number
    bpm_max: number
    popularity: number
    key_compatibility: boolean
  }
  
  // Reactive state
  const searchQuery = ref('')
  const searchResults = ref<Track[]>([])
  const selectedSeedTrack = ref<Track | null>(null)
  const recommendations = ref<Track[]>([])
  const isSearching = ref(false)
  const isDiscovering = ref(false)
  const errorMessage = ref('')
  let searchTimeout: ReturnType<typeof setTimeout> | null = null
  
  const parameters = reactive<Parameters>({
    bpm_min: 115,
    bpm_max: 125,
    popularity: 32,
    key_compatibility: false
  })
  
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
    if (!searchQuery.value.trim()) return
    
    isSearching.value = true
    errorMessage.value = ''
    
    try {
      const response = await http.post('music-discovery/search-seed', {
        query: searchQuery.value.trim(),
        limit: 20
      })
      
      console.log('Search response:', response)
      
      if (response.success) {
        searchResults.value = response.data
        console.log('Search results:', searchResults.value)
      } else {
        console.error('Search API returned success: false')
        errorMessage.value = 'Search failed. Please try again.'
      }
    } catch (error) {
      console.error('Search error:', error)
      errorMessage.value = 'Search failed. Please check your connection.'
    } finally {
      isSearching.value = false
    }
  }
  
  const selectSeedTrack = (track: Track) => {
    selectedSeedTrack.value = track
    searchResults.value = []
    searchQuery.value = ''
    recommendations.value = []
  }
  
  const clearSeedTrack = () => {
    selectedSeedTrack.value = null
    recommendations.value = []
  }
  
  const discoverMusic = async () => {
    if (!selectedSeedTrack.value) return
    
    isDiscovering.value = true
    errorMessage.value = ''
    
    try {
      const response = await http.post('music-discovery/discover', {
        seed_track_id: selectedSeedTrack.value.id,
        seed_track_name: selectedSeedTrack.value.name,
        seed_track_artist: selectedSeedTrack.value.artist,
        parameters: parameters,
        limit: 20
      })
      
      console.log('Discovery response:', response)
      
      if (response.success) {
        const recs = response.data.recommendations
        // Convert object to array if needed
        recommendations.value = Array.isArray(recs) ? recs : Object.values(recs)
        console.log('Recommendations:', recommendations.value)
        
        if (recommendations.value.length === 0) {
          errorMessage.value = 'No recommendations found with these parameters. Try adjusting your settings.'
        }
      } else {
        console.error('Discovery API returned success: false', response)
        errorMessage.value = 'Failed to get recommendations. Please try again.'
      }
    } catch (error) {
      console.error('Discovery error:', error)
      errorMessage.value = 'Failed to get recommendations. Please check your connection.'
    } finally {
      isDiscovering.value = false
    }
  }
  
  const playPreview = (track: Track) => {
    if (track.preview_url) {
      // Create audio element and play preview
      const audio = new Audio(track.preview_url)
      audio.volume = 0.5
      audio.play().catch(error => {
        console.error('Preview playback failed:', error)
      })
      
      // Stop after 30 seconds (Spotify preview length)
      setTimeout(() => {
        audio.pause()
      }, 30000)
    } else {
      // If no preview, could open Spotify link
      if (track.external_url) {
        window.open(track.external_url, '_blank')
      }
    }
  }
  
  // Watchers to ensure BPM min/max consistency
  watch(() => parameters.bpm_min, (newMin) => {
    if (newMin > parameters.bpm_max) {
      parameters.bpm_max = newMin
    }
  })
  
  watch(() => parameters.bmp_max, (newMax) => {
    if (newMax < parameters.bpm_min) {
      parameters.bpm_min = newMax
    }
  })
  </script>
  
  <style scoped>
  .music-discovery-screen {
    padding: 1.5rem;
  }
  
  .search-input:focus {
    box-shadow: 0 0 0 3px var(--color-highlight);
  }
  
  .result-item:hover {
    transform: translateY(-1px);
    transition: all 0.2s ease;
  }
  
  /* Custom range slider styling */
  input[type="range"] {
    -webkit-appearance: none;
    height: 6px;
    border-radius: 3px;
    background: var(--color-bg-tertiary);
    outline: none;
  }
  
  input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--color-highlight);
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }
  
  input[type="range"]::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--color-highlight);
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }
  
  .track-row:hover {
    background-color: var(--color-bg-tertiary);
    transition: background-color 0.2s ease;
  }
  
  .track-row:hover .text-k-text-primary {
    color: var(--color-highlight);
    transition: color 0.2s ease;
  }
  </style>