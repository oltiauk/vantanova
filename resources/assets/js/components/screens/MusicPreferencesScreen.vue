<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>
        Music Preferences
        <template #meta>
          <span class="text-k-text-secondary text-lg">Manage your blacklisted and saved tracks & artists</span>
        </template>
      </ScreenHeader>
    </template>

    <div class="music-preferences-screen space-y-8">
      
      <!-- Section Navigation -->
      <div class="section-tabs">
        <div class="flex border-b border-k-border">
          <button
            v-for="section in sections"
            :key="section.key"
            :class="[
              'px-6 py-3 font-medium transition-colors',
              activeSection === section.key
                ? 'text-k-accent border-b-2 border-k-accent'
                : 'text-k-text-secondary hover:text-k-text-primary'
            ]"
            @click="activeSection = section.key"
          >
            {{ section.name }}
            <span v-if="section.count !== null" class="ml-2 px-2 py-1 text-xs bg-k-bg-tertiary rounded-full">
              {{ section.count }}
            </span>
          </button>
        </div>
      </div>

      <!-- Blacklisted Tracks Section -->
      <div v-if="activeSection === 'blacklisted-tracks'" class="section">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-medium text-k-text-primary">Blacklisted Tracks</h3>
          <Btn
            v-if="blacklistedTracks.length > 0"
            size="sm"
            red
            :disabled="isLoading"
            @click="clearAllBlacklistedTracks"
          >
            Clear All
          </Btn>
        </div>

        <div v-if="isLoading" class="loading-state">
          <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-2 border-k-accent border-t-transparent"></div>
            <span class="ml-3 text-k-text-secondary">Loading...</span>
          </div>
        </div>

        <div v-else-if="blacklistedTracks.length === 0" class="empty-state">
          <div class="text-center py-12">
            <Icon :icon="faMusic" class="w-16 h-16 text-k-text-tertiary mx-auto mb-4" />
            <h4 class="text-lg font-medium text-k-text-primary mb-2">No Blacklisted Tracks</h4>
            <p class="text-k-text-secondary">Tracks you blacklist will appear here</p>
          </div>
        </div>

        <div v-else class="tracks-list space-y-3">
          <div
            v-for="track in blacklistedTracks"
            :key="track.id"
            class="track-item bg-k-bg-secondary border border-k-border rounded-lg p-4"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h4 class="font-medium text-k-text-primary">{{ track.track_name }}</h4>
                <p class="text-k-text-secondary">{{ track.artist_name }}</p>
                <p class="text-xs text-k-text-tertiary">ISRC: {{ track.isrc }}</p>
              </div>
              <Btn
                size="sm"
                red
                @click="removeFromBlacklist('track', track)"
                title="Remove from blacklist"
              >
                <Icon :icon="faTrash" class="w-4 h-4" />
              </Btn>
            </div>
          </div>
        </div>
      </div>

      <!-- Saved Tracks Section -->
      <div v-if="activeSection === 'saved-tracks'" class="section">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-medium text-k-text-primary">Saved Tracks</h3>
          <div class="text-sm text-k-text-secondary">
            Expires after 24 hours
          </div>
        </div>

        <div v-if="isLoading" class="loading-state">
          <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-2 border-k-accent border-t-transparent"></div>
            <span class="ml-3 text-k-text-secondary">Loading...</span>
          </div>
        </div>

        <div v-else-if="filteredSavedTracks.length === 0" class="empty-state">
          <div class="text-center py-12">
            <Icon :icon="faHeart" class="w-16 h-16 text-k-text-tertiary mx-auto mb-4" />
            <h4 class="text-lg font-medium text-k-text-primary mb-2">No Saved Tracks</h4>
            <p class="text-k-text-secondary">Tracks you save will appear here (expires in 24 hours)</p>
          </div>
        </div>

        <div v-else class="tracks-list space-y-3">
          <div
            v-for="track in filteredSavedTracks"
            :key="track.id"
            class="track-item bg-k-bg-secondary border border-k-border rounded-lg p-4"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h4 class="font-medium text-k-text-primary">{{ track.track_name }}</h4>
                <p class="text-k-text-secondary">{{ track.artist_name }}</p>
                <p class="text-xs text-k-text-tertiary">
                  Expires: {{ formatExpiration(track.expires_at) }}
                </p>
              </div>
              <div class="flex items-center space-x-2">
                <div class="text-xs text-green-400">
                  {{ getTimeRemaining(track.expires_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Blacklisted Artists Section -->
      <div v-if="activeSection === 'blacklisted-artists'" class="section">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-medium text-k-text-primary">Blacklisted Artists</h3>
          <Btn
            v-if="blacklistedArtists.length > 0"
            size="sm"
            red
            :disabled="isLoading"
            @click="clearAllBlacklistedArtists"
          >
            Clear All
          </Btn>
        </div>

        <div v-if="isLoading" class="loading-state">
          <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-2 border-k-accent border-t-transparent"></div>
            <span class="ml-3 text-k-text-secondary">Loading...</span>
          </div>
        </div>

        <div v-else-if="blacklistedArtists.length === 0" class="empty-state">
          <div class="text-center py-12">
            <Icon :icon="faUserMinus" class="w-16 h-16 text-k-text-tertiary mx-auto mb-4" />
            <h4 class="text-lg font-medium text-k-text-primary mb-2">No Blacklisted Artists</h4>
            <p class="text-k-text-secondary">Artists you blacklist will appear here</p>
          </div>
        </div>

        <div v-else class="artists-list space-y-3">
          <div
            v-for="artist in blacklistedArtists"
            :key="artist.id"
            class="artist-item bg-k-bg-secondary border border-k-border rounded-lg p-4"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h4 class="font-medium text-k-text-primary">{{ artist.artist_name }}</h4>
                <p class="text-xs text-k-text-tertiary">Spotify ID: {{ artist.spotify_artist_id }}</p>
              </div>
              <Btn
                size="sm"
                red
                @click="removeFromBlacklist('artist', artist)"
                title="Remove from blacklist"
              >
                <Icon :icon="faTrash" class="w-4 h-4" />
              </Btn>
            </div>
          </div>
        </div>
      </div>

      <!-- Saved Artists Section -->
      <div v-if="activeSection === 'saved-artists'" class="section">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-xl font-medium text-k-text-primary">Saved Artists</h3>
          <div class="text-sm text-k-text-secondary">
            Saved permanently
          </div>
        </div>

        <div v-if="isLoading" class="loading-state">
          <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-2 border-k-accent border-t-transparent"></div>
            <span class="ml-3 text-k-text-secondary">Loading...</span>
          </div>
        </div>

        <div v-else-if="savedArtists.length === 0" class="empty-state">
          <div class="text-center py-12">
            <Icon :icon="faUserPlus" class="w-16 h-16 text-k-text-tertiary mx-auto mb-4" />
            <h4 class="text-lg font-medium text-k-text-primary mb-2">No Saved Artists</h4>
            <p class="text-k-text-secondary">Artists you save will appear here</p>
          </div>
        </div>

        <div v-else class="artists-list space-y-3">
          <div
            v-for="artist in savedArtists"
            :key="artist.id"
            class="artist-item bg-k-bg-secondary border border-k-border rounded-lg p-4"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h4 class="font-medium text-k-text-primary">{{ artist.artist_name }}</h4>
                <p class="text-xs text-k-text-tertiary">Spotify ID: {{ artist.spotify_artist_id }}</p>
              </div>
              <div class="text-xs text-blue-400">
                Saved {{ formatDate(artist.created_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </ScreenBase>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { faMusic, faHeart, faUserPlus, faUserMinus, faTrash } from '@fortawesome/free-solid-svg-icons'
import { http } from '@/services/http'
import { useRouter } from '@/composables/useRouter'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import Btn from '@/components/ui/form/Btn.vue'

// Types
interface BlacklistedTrack {
  id: number
  isrc: string
  track_name: string
  artist_name: string
  spotify_id: string
  created_at: string
}

interface SavedTrack {
  id: number
  isrc: string
  track_name: string
  artist_name: string
  spotify_id: string
  expires_at: string
  created_at: string
}

interface BlacklistedArtist {
  id: number
  spotify_artist_id: string
  artist_name: string
  created_at: string
}

interface SavedArtist {
  id: number
  spotify_artist_id: string
  artist_name: string
  created_at: string
}

// State
const activeSection = ref('blacklisted-tracks')
const isLoading = ref(false)

const blacklistedTracks = ref<BlacklistedTrack[]>([])
const savedTracks = ref<SavedTrack[]>([])
const blacklistedArtists = ref<BlacklistedArtist[]>([])
const savedArtists = ref<SavedArtist[]>([])
const clientUnsavedTracks = ref<Set<string>>(new Set())

// Helper function to generate track key (same as RecommendationsTable)
const getTrackKey = (track: SavedTrack): string => {
  return `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Load client-side unsaved tracks from localStorage
const loadClientUnsavedTracks = () => {
  try {
    const stored = localStorage.getItem('koel-client-unsaved-tracks')
    if (stored) {
      const unsavedList = JSON.parse(stored)
      clientUnsavedTracks.value = new Set(unsavedList)
      // console.log('MusicPreferences: Loaded client unsaved tracks:', unsavedList)
    }
  } catch (error) {
    console.warn('Failed to load client unsaved tracks from localStorage:', error)
  }
}

// Computed property to filter out client-unsaved tracks
const filteredSavedTracks = computed(() => {
  return savedTracks.value.filter(track => {
    const trackKey = getTrackKey(track)
    return !clientUnsavedTracks.value.has(trackKey)
  })
})

// Sections configuration
const sections = computed(() => [
  {
    key: 'blacklisted-tracks',
    name: 'Blacklisted Tracks',
    count: blacklistedTracks.value.length
  },
  {
    key: 'saved-tracks', 
    name: 'Saved Tracks',
    count: filteredSavedTracks.value.length
  },
  {
    key: 'blacklisted-artists',
    name: 'Blacklisted Artists', 
    count: blacklistedArtists.value.length
  },
  {
    key: 'saved-artists',
    name: 'Saved Artists',
    count: savedArtists.value.length
  }
])

// Methods
const loadData = async () => {
  isLoading.value = true
  
  try {
    const [blackTracksRes, savedTracksRes, blackArtistsRes, savedArtistsRes] = await Promise.all([
      http.get('music-preferences/blacklisted-tracks'),
      http.get('music-preferences/saved-tracks'),
      http.get('music-preferences/blacklisted-artists'),
      http.get('music-preferences/saved-artists')
    ])

    blacklistedTracks.value = blackTracksRes.data || []
    savedTracks.value = savedTracksRes.data || []
    blacklistedArtists.value = blackArtistsRes.data || []
    savedArtists.value = savedArtistsRes.data || []
  } catch (error) {
    console.error('Failed to load music preferences:', error)
  } finally {
    isLoading.value = false
  }
}

const removeFromBlacklist = async (type: 'track' | 'artist', item: any) => {
  try {
    const endpoint = type === 'track' ? 'blacklist-track' : 'blacklist-artist'
    
    if (type === 'track') {
      // console.log('Track item:', item)
      const deleteData = {
        isrc: item.isrc || item.id, // fallback to id if isrc is null
        track_name: item.track_name,
        artist_name: item.artist_name
      }
      // console.log('DELETE data:', deleteData)
      
      // Try using query parameters instead
      const params = new URLSearchParams(deleteData)
      await http.delete(`music-preferences/${endpoint}?${params}`)
    } else {
      const deleteData = {
        spotify_artist_id: item.spotify_artist_id,
        artist_name: item.artist_name
      }
      const params = new URLSearchParams(deleteData)
      await http.delete(`music-preferences/${endpoint}?${params}`)
    }
    
    // Remove from local state
    if (type === 'track') {
      blacklistedTracks.value = blacklistedTracks.value.filter(track => track.id !== item.id)
    } else {
      blacklistedArtists.value = blacklistedArtists.value.filter(artist => artist.id !== item.id)
    }
  } catch (error: any) {
    console.error(`Failed to remove ${type} from blacklist:`, error)
    console.error('Error response:', error.response?.data)
  }
}

const clearAllBlacklistedTracks = async () => {
  if (!confirm('Are you sure you want to remove all blacklisted tracks?')) return
  
  try {
    // Try bulk delete first
    const response = await http.delete('music-preferences/clear-all-blacklisted-tracks')
    if (response.success) {
      blacklistedTracks.value = []
      // console.log('✅ All blacklisted tracks cleared successfully')
      return
    }
  } catch (error) {
    // console.log('Bulk delete not available, falling back to individual deletions')
  }
  
  // Fallback to individual deletions
  try {
    const promises = blacklistedTracks.value.map(track => removeFromBlacklist('track', track))
    await Promise.all(promises)
    // console.log('✅ All blacklisted tracks cleared individually')
  } catch (error) {
    console.error('Failed to clear all blacklisted tracks:', error)
  }
}

const clearAllBlacklistedArtists = async () => {
  if (!confirm('Are you sure you want to remove all blacklisted artists?')) return
  
  try {
    // Try bulk delete first
    const response = await http.delete('music-preferences/clear-all-blacklisted-artists')
    if (response.success) {
      blacklistedArtists.value = []
      // console.log('✅ All blacklisted artists cleared successfully')
      return
    }
  } catch (error) {
    // console.log('Bulk delete not available, falling back to individual deletions')
  }
  
  // Fallback to individual deletions
  try {
    const promises = blacklistedArtists.value.map(artist => removeFromBlacklist('artist', artist))
    await Promise.all(promises)
    // console.log('✅ All blacklisted artists cleared individually')
  } catch (error) {
    console.error('Failed to clear all blacklisted artists:', error)
  }
}

const formatExpiration = (dateString: string): string => {
  return new Date(dateString).toLocaleString()
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleDateString()
}

const getTimeRemaining = (expiresAt: string): string => {
  const now = new Date()
  const expiry = new Date(expiresAt)
  const diffMs = expiry.getTime() - now.getTime()
  
  if (diffMs <= 0) return 'Expired'
  
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
  const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60))
  
  if (diffHours > 0) {
    return `${diffHours}h ${diffMinutes}m left`
  } else {
    return `${diffMinutes}m left`
  }
}

// Lifecycle
onMounted(() => {
  loadClientUnsavedTracks()
  loadData()
  
  // Only refresh data when the screen is actually visible to the user
  // This prevents unnecessary network requests when on other screens
  let interval: number | null = null
  
  const { isCurrentScreen } = useRouter()
  
  watch(() => isCurrentScreen('MusicPreferences'), (isVisible) => {
    if (isVisible) {
      // Start polling when screen becomes visible
      loadData()
      loadClientUnsavedTracks() // Reload client unsaved tracks when screen becomes visible
      interval = setInterval(() => {
        loadData()
        loadClientUnsavedTracks() // Also check for changes in client unsaved tracks
      }, 10000) // Increased to 10 seconds to reduce frequency
    } else {
      // Stop polling when screen is not visible
      if (interval) {
        clearInterval(interval)
        interval = null
      }
    }
  }, { immediate: true })
  
  // Clean up interval on unmount
  onUnmounted(() => {
    if (interval) {
      clearInterval(interval)
    }
  })
})
</script>

<style scoped>
.section-tabs button {
  border-bottom: 2px solid transparent;
}
</style>