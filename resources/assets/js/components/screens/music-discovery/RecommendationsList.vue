<template>
    <div class="recommendations-section">
      <!-- Modern Results Header -->
      <div v-if="recommendations.length > 0 || isDiscovering" class="results-header mb-8">
        <div class="bg-gradient-to-r from-indigo-500/10 to-purple-500/10 border border-indigo-500/20 rounded-xl p-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
              <div class="p-3 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl">
                <Icon :icon="faMusic" class="w-6 h-6 text-white" />
              </div>
              <div>
                <h3 class="text-white font-bold text-2xl">
                  {{ isDiscovering ? 'Discovering Music...' : 'Similar Tracks Found' }}
                </h3>
                <p class="text-white/70 text-sm">
                  {{ isDiscovering ? 'Analyzing your preferences with AI' : `${currentProvider} • ${displayedCount} tracks` }}
                </p>
              </div>
            </div>
            
            <div v-if="recommendations.length > 0" class="flex items-center gap-3">
              <div class="px-4 py-2 bg-white/10 rounded-lg backdrop-blur-sm">
                <span class="text-white font-medium text-sm">
                  {{ displayedCount }} tracks
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Enhanced Loading State -->
      <div v-if="isDiscovering" class="discovering-state">
        <div class="bg-gradient-to-br from-purple-900/20 to-blue-900/20 border border-purple-500/20 rounded-2xl p-12">
          <div class="flex flex-col items-center justify-center text-center">
            <div class="relative mb-8">
              <!-- Outer spinning ring -->
              <div class="animate-spin rounded-full h-20 w-20 border-4 border-purple-500/20 border-t-purple-500"></div>
              <!-- Inner pulsing circle -->
              <div class="absolute inset-2 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full animate-pulse flex items-center justify-center">
                <Icon :icon="faMusic" class="w-8 h-8 text-white" />
              </div>
              <!-- Floating particles -->
              <div class="absolute -top-2 -left-2 w-2 h-2 bg-purple-400 rounded-full animate-ping"></div>
              <div class="absolute -bottom-2 -right-2 w-2 h-2 bg-pink-400 rounded-full animate-ping" style="animation-delay: 0.5s;"></div>
            </div>
            
            <h4 class="text-white text-xl font-bold mb-3">Discovering Similar Tracks</h4>
            <p class="text-white/70 text-base mb-4 max-w-md">
              Our AI is analyzing Spotify and Shazam databases to find tracks that match your taste
            </p>
            
            <!-- Progress indicators -->
            <div class="flex items-center gap-2 text-sm text-white/50">
              <div class="flex items-center gap-1">
                <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                <span>Spotify API</span>
              </div>
              <span>•</span>
              <div class="flex items-center gap-1">
                <div class="w-2 h-2 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.3s;"></div>
                <span>Shazam API</span>
              </div>
              <span>•</span>
              <div class="flex items-center gap-1">
                <div class="w-2 h-2 bg-purple-400 rounded-full animate-pulse" style="animation-delay: 0.6s;"></div>
                <span>AI Processing</span>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Error State -->
      <div v-if="errorMessage && !isDiscovering" class="error-state mb-6">
        <div class="bg-red-900/20 border border-red-500/30 rounded-lg p-4">
          <div class="flex items-center">
            <Icon :icon="faExclamationTriangle" class="w-5 h-5 text-red-400 mr-3 shrink-0" />
            <div class="flex-1">
              <p class="text-red-400 font-medium">Discovery Failed</p>
              <p class="text-red-300 text-sm">{{ errorMessage }}</p>
            </div>
            <Btn
              size="sm"
              class="ml-4"
              @click="$emit('clearError')"
            >
              <Icon :icon="faTimes" class="w-4 h-4" />
            </Btn>
          </div>
        </div>
      </div>
  
      <!-- Modern Recommendations Grid -->
      <div v-if="recommendations.length > 0 && !isDiscovering" class="recommendations-list">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
          <TrackCard
            v-for="(track, index) in recommendations"
            :key="`${track.id}-${index}`"
            :track="track"
            class="transform transition-all duration-300 hover:scale-[1.02] hover:shadow-xl"
          />
        </div>
  
        <!-- Enhanced Load More Loading State -->
        <div v-if="isLoadingMore" class="load-more-loading mt-8">
          <div class="bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/20 rounded-xl p-6">
            <div class="flex items-center justify-center gap-4">
              <div class="relative">
                <div class="animate-spin rounded-full h-8 w-8 border-3 border-blue-500/20 border-t-blue-500"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full opacity-20 animate-pulse"></div>
              </div>
              <span class="text-white font-medium text-lg">Loading more amazing tracks...</span>
            </div>
          </div>
        </div>
  
        <!-- Modern Results Footer -->
        <div class="results-footer mt-10">
          <div class="bg-gradient-to-r from-gray-800/50 to-gray-900/50 border border-gray-700/50 rounded-xl p-6 backdrop-blur-sm">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                  <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                  <span class="text-white font-medium">
                    {{ displayedCount }} tracks discovered
                  </span>
                </div>
                <div class="h-4 w-px bg-gray-600"></div>
                <div class="text-white/60 text-sm">
                  Powered by Spotify + Shazam APIs
                </div>
              </div>
              
              <div class="flex items-center gap-4">
                <!-- Load More Button -->
                <button
                  v-if="hasMoreToLoad"
                  :disabled="isLoadingMore"
                  @click="$emit('loadMore')"
                  class="group px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-semibold text-white transition-all duration-300 transform hover:scale-105 hover:shadow-lg"
                >
                  <div class="flex items-center gap-2">
                    <Icon v-if="isLoadingMore" :icon="faSpinner" spin />
                    <Icon v-else :icon="faPlus" class="group-hover:rotate-90 transition-transform duration-300" />
                    <span>{{ isLoadingMore ? 'Loading...' : 'Load More' }}</span>
                  </div>
                </button>
                
                <div v-else class="px-4 py-2 bg-white/10 rounded-lg">
                  <span class="text-white/70 text-sm font-medium">All tracks loaded</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  
      <!-- Empty State (no results) -->
      <div v-if="recommendations.length === 0 && !isDiscovering && !errorMessage" class="empty-results">
        <div class="text-center py-12">
          <Icon :icon="faSearch" class="w-16 h-16 text-k-text-tertiary mx-auto mb-6" />
          <h3 class="text-k-text-primary text-xl font-medium mb-2">Ready to Discover</h3>
          <p class="text-k-text-secondary text-lg mb-4">
            Select a seed track and enable some parameters to find new music
          </p>
          <div class="text-k-text-tertiary text-sm space-y-1">
            <p>• Choose a song you like as your starting point</p>
            <p>• Enable parameters that matter to you</p>
            <p>• Let our AI find similar tracks</p>
          </div>
        </div>
      </div>
  
      <!-- No Results Found State -->
      <div v-if="recommendations.length === 0 && !isDiscovering && errorMessage && errorMessage.includes('No recommendations found')" class="no-results">
        <div class="text-center py-12">
          <Icon :icon="faExclamationCircle" class="w-16 h-16 text-k-text-tertiary mx-auto mb-6" />
          <h3 class="text-k-text-primary text-xl font-medium mb-2">No Matches Found</h3>
          <p class="text-k-text-secondary text-lg mb-6">
            Try adjusting your parameters to find more tracks
          </p>
          <div class="bg-k-bg-tertiary border border-k-border rounded-lg p-4 max-w-md mx-auto">
            <p class="text-k-text-primary font-medium mb-2">Suggestions:</p>
            <ul class="text-k-text-secondary text-sm space-y-1 text-left">
              <li>• Widen your BPM range</li>
              <li>• Adjust popularity settings</li>
              <li>• Enable fewer parameters</li>
              <li>• Try a different seed track</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { ref } from 'vue'
  import { faMusic, faSearch, faExclamationTriangle, faExclamationCircle, faTimes, faSpinner, faPlus } from '@fortawesome/free-solid-svg-icons'
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
    external_ids?: {
      isrc?: string
    }
  }
  
  // Props - SIMPLIFIED
  interface Props {
    recommendations: Track[]
    displayedCount: number
    hasMoreToLoad: boolean
    isDiscovering: boolean
    isLoadingMore: boolean
    errorMessage: string
  }
  
  const props = defineProps<Props>()
  
  // Emits
  defineEmits<{
    'clearError': []
    'loadMore': []
  }>()

  // State
  const isBlacklistingUnsaved = ref(false)

  // Methods
  const blacklistUnsavedTracks = async () => {
    if (isBlacklistingUnsaved.value || props.recommendations.length === 0) return

    isBlacklistingUnsaved.value = true

    try {
      // Extract track data for blacklisting
      const tracksToBlacklist = props.recommendations
        .map(track => ({
          isrc: track.external_ids?.isrc,
          track_name: track.name,
          artist_name: track.artist,
          spotify_id: track.id
        }))
        .filter(track => track.isrc) // Only include tracks with ISRC

      if (tracksToBlacklist.length === 0) {
        console.warn('No tracks with ISRC found to blacklist')
        return
      }

      const response = await http.post('music-preferences/blacklist-unsaved-tracks', {
        tracks: tracksToBlacklist
      })

      if (response.success) {
        console.log(`✅ Successfully blacklisted ${response.blacklisted_count} unsaved tracks`)
        // Could emit event to parent to show success message or refresh recommendations
      }
    } catch (error) {
      console.error('Failed to blacklist unsaved tracks:', error)
    } finally {
      isBlacklistingUnsaved.value = false
    }
  }
  </script>
  
  <style scoped>
  .recommendations-list {
    animation: fadeIn 0.3s ease-in-out;
  }
  
  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .discovering-state {
    animation: pulse 2s ease-in-out infinite;
  }
  
  @keyframes pulse {
    0%, 100% {
      opacity: 1;
    }
    50% {
      opacity: 0.8;
    }
  }
  </style>