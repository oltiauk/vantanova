<template>
    <div class="recommendations-section">
      <!-- Results Header -->
      <div v-if="recommendations.length > 0 || isDiscovering" class="results-header mb-6">
        <div class="flex items-center justify-between">
          <h3 class="text-k-accent font-medium text-xl">
            {{ isDiscovering ? 'Discovering Music...' : 'Recommendations' }}
          </h3>
          <div v-if="recommendations.length > 0" class="flex items-center gap-3">
            <!-- Blacklist Unsaved Tracks Button -->
            <Btn
              size="sm"
              red
              :disabled="isBlacklistingUnsaved"
              @click="blacklistUnsavedTracks"
              title="Add all currently displayed tracks that aren't saved to your blacklist"
            >
              {{ isBlacklistingUnsaved ? 'Blacklisting...' : 'Blacklist Unsaved Tracks' }}
            </Btn>
            <span class="text-k-text-secondary text-sm">
              Showing {{ displayedCount }} tracks
            </span>
          </div>
        </div>
      </div>
  
      <!-- Loading State -->
      <div v-if="isDiscovering" class="discovering-state">
        <div class="flex flex-col items-center justify-center py-12">
          <div class="relative mb-6">
            <div class="animate-spin rounded-full h-16 w-16 border-4 border-k-bg-tertiary border-t-k-accent"></div>
            <div class="absolute inset-0 flex items-center justify-center">
              <Icon :icon="faMusic" class="w-6 h-6 text-k-accent" />
            </div>
          </div>
          <p class="text-k-text-primary text-lg font-medium mb-2">Analyzing your preferences...</p>
          <p class="text-k-text-secondary text-sm text-center max-w-md">
            Our AI is finding tracks that match your selected parameters and seed track
          </p>
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
  
      <!-- Recommendations List -->
      <div v-if="recommendations.length > 0 && !isDiscovering" class="recommendations-list">
        <div class="space-y-4">
          <TrackCard
            v-for="(track, index) in recommendations"
            :key="`${track.id}-${index}`"
            :track="track"
          />
        </div>
  
        <!-- Load More Loading State -->
        <div v-if="isLoadingMore" class="load-more-loading mt-6">
          <div class="flex items-center justify-center py-4">
            <div class="animate-spin rounded-full h-6 w-6 border-2 border-k-bg-tertiary border-t-k-accent mr-3"></div>
            <span class="text-k-text-secondary">Loading more tracks...</span>
          </div>
        </div>
  
        <!-- Results Footer with Load More -->
        <div class="results-footer mt-8 pt-6 border-t border-k-border">
          <div class="flex items-center justify-between">
            <div class="text-k-text-secondary text-sm">
              Showing {{ displayedCount }} recommendations
            </div>
            <div class="flex items-center gap-3">
              <!-- Load More Button -->
              <Btn
                v-if="hasMoreToLoad"
                :disabled="isLoadingMore"
                green
                @click="$emit('loadMore')"
              >
                {{ isLoadingMore ? 'Loading...' : 'Load More' }}
              </Btn>
              
              <span class="text-k-text-tertiary text-xs">
                Powered by SoundStats AI
              </span>
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
  import { faMusic, faSearch, faExclamationTriangle, faExclamationCircle, faTimes } from '@fortawesome/free-solid-svg-icons'
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