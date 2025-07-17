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
                @click="removeFromBlacklist('track', track.id)"
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

        <div v-else-if="savedTracks.length === 0" class="empty-state">
          <div class="text-center py-12">
            <Icon :icon="faHeart" class="w-16 h-16 text-k-text-tertiary mx-auto mb-4" />
            <h4 class="text-lg font-medium text-k-text-primary mb-2">No Saved Tracks</h4>
            <p class="text-k-text-secondary">Tracks you save will appear here (expires in 24 hours)</p>
          </div>
        </div>

        <div v-else class="tracks-list space-y-3">
          <div
            v-for="track in savedTracks"
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
                @click="removeFromBlacklist('artist', artist.id)"
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
import { ref, onMounted, computed } from 'vue'
import { faMusic, faHeart, faUserPlus, faUserMinus, faTrash } from '@fortawesome/free-solid-svg-icons'
import { http } from '@/services/http'

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
    count: savedTracks.value.length
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

const removeFromBlacklist = async (type: 'track' | 'artist', id: number) => {
  try {
    const endpoint = type === 'track' ? 'blacklist-track' : 'blacklist-artist'
    await http.delete(`music-preferences/${endpoint}`, { id })
    
    // Remove from local state
    if (type === 'track') {
      blacklistedTracks.value = blacklistedTracks.value.filter(track => track.id !== id)
    } else {
      blacklistedArtists.value = blacklistedArtists.value.filter(artist => artist.id !== id)
    }
  } catch (error) {
    console.error(`Failed to remove ${type} from blacklist:`, error)
  }
}

const clearAllBlacklistedTracks = async () => {
  if (!confirm('Are you sure you want to remove all blacklisted tracks?')) return
  
  try {
    for (const track of blacklistedTracks.value) {
      await removeFromBlacklist('track', track.id)
    }
  } catch (error) {
    console.error('Failed to clear all blacklisted tracks:', error)
  }
}

const clearAllBlacklistedArtists = async () => {
  if (!confirm('Are you sure you want to remove all blacklisted artists?')) return
  
  try {
    for (const artist of blacklistedArtists.value) {
      await removeFromBlacklist('artist', artist.id)
    }
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
  loadData()
})
</script>

<style scoped>
.section-tabs button {
  border-bottom: 2px solid transparent;
}
</style>