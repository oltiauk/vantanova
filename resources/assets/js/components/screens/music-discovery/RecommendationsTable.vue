<template>
  <div class="recommendations-table">
    <!-- Header -->
    <div v-if="recommendations.length > 0 || isDiscovering" class="mb-6">
      <div class="flex justify-between items-center mb-4">
        <!-- <h3 class="text-lg font-medium text-white">
          {{ isDiscovering ? 'Searching...' : 'Related Tracks' }}
        </h3> -->
      </div>
      
      <!-- Blacklist Button - Centered -->
      <div v-if="!isDiscovering && recommendations.length > 0" class="text-center mb-4">
        <button
          @click="blacklistUnsavedTracks"
          :disabled="isBlacklisting"
          class="px-4 py-2 bg-[#429488] hover:bg-[#368075] rounded text-sm font-medium transition disabled:opacity-50 text-white"
        >
          <div class="flex flex-col items-center">
            <span>Add to Blacklist</span>
            <span class="text-xs opacity-80">unsaved tracks from the list</span>
          </div>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isDiscovering" class="text-center p-12">
      <div class="inline-flex flex-col items-center">
        <svg class="w-8 h-8 animate-spin text-[#429488] mb-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="errorMessage && !isDiscovering" class="bg-red-500/20 border border-red-500/40 rounded-lg p-4 mb-6">
      <div class="flex items-start gap-3">
        <Icon :icon="faExclamationTriangle" class="text-red-400 mt-0.5" />
        <div>
          <h4 class="font-medium text-red-200 mb-1">Discovery Failed</h4>
          <p class="text-red-200">{{ errorMessage }}</p>
        </div>
        <button
          @click="$emit('clearError')"
          class="ml-auto text-red-400 hover:text-red-300"
        >
          <Icon :icon="faTimes" class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Recommendations Table -->
    <div v-if="recommendations.length > 0 && !isDiscovering">
      <div class="bg-white/5 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-white/10">
                <th class="text-left p-3 font-medium">#</th>
                <th class="text-left p-3 font-medium">Artist</th>
                <th class="text-left p-3 font-medium">Title</th>
                <th class="text-left p-3 font-medium">Duration</th>
                <th class="text-left p-3 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(track, index) in recommendations" :key="`related-${track.id}`">
                <tr class="hover:bg-white/5 transition h-16 border-b border-white/5">
                  <!-- Index -->
                  <td class="p-3 align-middle">
                    <span class="text-white/60">{{ index + 1 }}</span>
                  </td>

                  <!-- Artist -->
                  <td class="p-3 align-middle">
                    <div class="font-medium text-white">{{ track.artist }}</div>
                  </td>

                  <!-- Title -->
                  <td class="p-3 align-middle">
                    <div class="flex items-center gap-2">
                      <span class="text-white/80">{{ track.name }}</span>
                      <!-- Source Badge -->
                      <span
                        v-if="track.source === 'shazam'"
                        class="px-2 py-0.5 bg-blue-500/80 text-white text-xs font-medium rounded"
                        title="Track from Shazam"
                      >
                        🎵 SHAZAM
                      </span>
                      <span
                        v-else-if="track.source === 'spotify'"
                        class="px-2 py-0.5 bg-green-500/80 text-white text-xs font-medium rounded"
                        title="Track from Spotify"
                      >
                        🎧 SPOTIFY
                      </span>
                    </div>
                  </td>

                  <!-- Duration -->
                  <td class="p-3 align-middle">
                    <span class="text-white/80">{{ formatDuration(track.duration_ms) }}</span>
                  </td>

                  <!-- Actions -->
                  <td class="p-3 align-middle">
                    <div class="flex gap-2">
                      <!-- Save Button (24h) -->
                      <button
                        @click="saveTrack(track)"
                        :disabled="processingTrack === getTrackKey(track)"
                        :class="isTrackSaved(track) 
                          ? 'bg-green-600 hover:bg-green-700 text-white' 
                          : 'bg-gray-600 hover:bg-gray-500 text-white'"
                        class="px-2 py-1.5 rounded text-sm font-medium transition disabled:opacity-50"
                        :title="isTrackSaved(track) ? 'Saved (24h)' : 'Save track (24h)'"
                      >
                        <Icon :icon="faHeart" class="text-xs" />
                      </button>

                      <!-- Blacklist Button -->
                      <button
                        @click="blacklistTrack(track)"
                        :disabled="processingTrack === getTrackKey(track)"
                        :class="isTrackBlacklisted(track) 
                          ? 'bg-orange-600 hover:bg-orange-700 text-white' 
                          : 'bg-gray-600 hover:bg-gray-500 text-white'"
                        class="px-2 py-1.5 rounded text-sm font-medium transition disabled:opacity-50"
                        :title="isTrackBlacklisted(track) ? 'Unblock track' : 'Block track'"
                      >
                        <Icon :icon="faBan" class="text-xs" />
                      </button>

                      <!-- Related Track Button -->
                      <button
                        @click="getRelatedTracks(track)"
                        :disabled="processingTrack === getTrackKey(track)"
                        class="px-3 py-1.5 bg-[#429488] rounded text-sm font-medium transition disabled:opacity-50"
                        title="Find Related Tracks"
                      >
                        Related
                      </button>
                      
                      <!-- Preview Button -->
                      <button
                        @click="track.source === 'shazam' ? previewShazamTrack(track) : toggleSpotifyPlayer(track)"
                        :disabled="processingTrack === getTrackKey(track)"
                        class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50"
                        :title="track.source === 'shazam' ? 'Preview Shazam track via Spotify' : 'Preview Spotify track'"
                      >
                        <Icon :icon="expandedTrackId === getTrackKey(track) ? faTimes : faPlay" class="mr-1" />
                        {{ expandedTrackId === getTrackKey(track) ? 'Close' : 'Preview' }}
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- Spotify Player Dropdown Row with Animation -->
                <Transition name="spotify-dropdown" mode="out-in">
                  <tr v-if="expandedTrackId === getTrackKey(track)" :key="`spotify-${track.id}`" class="border-b border-white/5">
                    <td colspan="5" class="p-0 overflow-hidden">
                      <div class="spotify-player-container bg-green-50/5 p-4">
                        <div class="max-w-4xl mx-auto">
                          <div v-if="track.id && track.id !== 'NO_TRACK_FOUND'">
                            <iframe
                              :key="track.id"
                              :src="`https://open.spotify.com/embed/track/${track.id}?utm_source=generator&theme=0`"
                              :title="`${track.artist} - ${track.name}`"
                              class="w-full"
                              style="height: 80px; border-radius: 15px;"
                              frameBorder="0"
                              allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                              loading="lazy"
                              @load="() => console.log('✅ Spotify player loaded for:', track.name)"
                              @error="() => console.log('❌ Spotify player failed to load for:', track.name)"
                            ></iframe>
                          </div>
                          <div v-else class="bg-gray-800 flex items-center justify-center" style="height: 80px; border-radius: 15px;">
                            <div class="text-center text-white/60">
                              <div class="text-sm font-medium">No Spotify preview available</div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </Transition>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Load More Loading State -->
        <div v-if="isLoadingMore" class="p-6 border-t border-white/10 text-center">
          <div class="flex items-center justify-center gap-2">
            <Icon :icon="faSpinner" spin />
            <span class="text-white/70">Loading more tracks...</span>
          </div>
        </div>

        <!-- Load More Button -->
        <div v-if="hasMoreToLoad && !isLoadingMore" class="p-6 border-t border-white/10 text-center">
          <button
            @click="$emit('loadMore')"
            class="px-6 py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition"
          >
            Load More Tracks
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { faSpinner, faExclamationTriangle, faTimes, faHeart, faBan, faUserPlus, faUserMinus, faPlay, faRandom, faInfoCircle, faSearch } from '@fortawesome/free-solid-svg-icons'
import { http } from '@/services/http'

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
  artists?: Array<{
    id: string
    name: string
  }>
  source?: string  // 'shazam' or 'spotify'
  shazam_id?: string
  spotify_id?: string
}

// Props
interface Props {
  recommendations: Track[]
  displayedCount: number
  hasMoreToLoad: boolean
  isDiscovering: boolean
  isLoadingMore: boolean
  errorMessage: string
  currentProvider: string
}

const props = defineProps<Props>()

// Emits
const emit = defineEmits<{
  'clearError': []
  'loadMore': []
  'related-tracks': [track: Track]
  'tracks-blacklisted': [trackKeys: string[]]
}>()

// State
const expandedTrackId = ref<string | null>(null)
const processingTrack = ref<string | null>(null)
const isBlacklisting = ref(false)

// Music preferences state
const savedTracks = ref<Set<string>>(new Set())
const blacklistedTracks = ref<Set<string>>(new Set())
const savedArtists = ref<Set<string>>(new Set())
const blacklistedArtists = ref<Set<string>>(new Set())

// Helper functions
const getTrackKey = (track: Track): string => {
  return `${track.artist}-${track.name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

const formatDuration = (ms?: number): string => {
  if (!ms) return '0:00'
  const minutes = Math.floor(ms / 60000)
  const seconds = Math.floor((ms % 60000) / 1000)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

const isTrackSaved = (track: Track): boolean => {
  return savedTracks.value.has(getTrackKey(track))
}

const isTrackBlacklisted = (track: Track): boolean => {
  return blacklistedTracks.value.has(getTrackKey(track))
}

const isArtistSaved = (track: Track): boolean => {
  return savedArtists.value.has(track.artist.toLowerCase())
}

const isArtistBlacklisted = (track: Track): boolean => {
  return blacklistedArtists.value.has(track.artist.toLowerCase())
}

// Action handlers
const saveTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    if (isTrackSaved(track)) {
      // Remove from saved
      const response = await http.delete('music-preferences/saved-track', {
        data: {
          isrc: track.external_ids?.isrc || track.id,
          track_name: track.name,
          artist_name: track.artist
        }
      })

      if (response.success) {
        savedTracks.value.delete(trackKey)
        console.log('Track unsaved successfully')
      } else {
        throw new Error(response.error || 'Failed to unsave track')
      }
    } else {
      // Save track
      const response = await http.post('music-preferences/save-track', {
        isrc: track.external_ids?.isrc || track.id,
        track_name: track.name,
        artist_name: track.artist,
        spotify_id: track.id
      })

      if (response.success) {
        savedTracks.value.add(trackKey)
        console.log('Track saved successfully')
      } else {
        throw new Error(response.error || 'Failed to save track')
      }
    }
  } catch (error: any) {
    console.error('Failed to save track:', error)
  } finally {
    processingTrack.value = null
  }
}

const blacklistTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    if (isTrackBlacklisted(track)) {
      // Unblock track
      const response = await http.delete('music-preferences/blacklist-track', {
        data: {
          isrc: track.external_ids?.isrc || track.id,
          track_name: track.name,
          artist_name: track.artist
        }
      })

      if (response.success) {
        blacklistedTracks.value.delete(trackKey)
        console.log('Track unblocked successfully')
      } else {
        throw new Error(response.error || 'Failed to unblock track')
      }
    } else {
      // Block track
      const response = await http.post('music-preferences/blacklist-track', {
        isrc: track.external_ids?.isrc || track.id,
        track_name: track.name,
        artist_name: track.artist
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)
        console.log('Track blacklisted successfully')
        // Emit to parent component
        emit('tracks-blacklisted', [trackKey])
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    }
  } catch (error: any) {
    console.error('Failed to toggle blacklist:', error)
  } finally {
    processingTrack.value = null
  }
}

const saveArtist = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artistKey = track.artist.toLowerCase()

    if (isArtistSaved(track)) {
      // Remove from saved artists
      const response = await http.delete('music-preferences/saved-artist', {
        data: {
          spotify_artist_id: track.artists?.[0]?.id || track.id,
          artist_name: track.artist
        }
      })

      if (response.success) {
        savedArtists.value.delete(artistKey)
        console.log('Artist unsaved successfully')
      } else {
        throw new Error(response.error || 'Failed to unsave artist')
      }
    } else {
      // Save artist
      const response = await http.post('music-preferences/save-artist', {
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
      })

      if (response.success) {
        savedArtists.value.add(artistKey)
        console.log('Artist saved successfully')
      } else {
        throw new Error(response.error || 'Failed to save artist')
      }
    }
  } catch (error: any) {
    console.error('Failed to save artist:', error)
  } finally {
    processingTrack.value = null
  }
}

const blacklistArtist = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey

  try {
    const artistKey = track.artist.toLowerCase()

    if (isArtistBlacklisted(track)) {
      // Remove from blacklisted artists
      const response = await http.delete('music-preferences/blacklist-artist', {
        data: {
          spotify_artist_id: track.artists?.[0]?.id || track.id,
          artist_name: track.artist
        }
      })

      if (response.success) {
        blacklistedArtists.value.delete(artistKey)
        console.log('Artist unblacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to unblacklist artist')
      }
    } else {
      // Blacklist artist
      const response = await http.post('music-preferences/blacklist-artist', {
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
      })

      if (response.success) {
        blacklistedArtists.value.add(artistKey)
        console.log('Artist blacklisted successfully')
      } else {
        throw new Error(response.error || 'Failed to blacklist artist')
      }
    }
  } catch (error: any) {
    console.error('Failed to blacklist artist:', error)
  } finally {
    processingTrack.value = null
  }
}

// Spotify player functionality
const toggleSpotifyPlayer = (track: Track) => {
  const trackKey = getTrackKey(track)
  
  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

  expandedTrackId.value = trackKey
  console.log('🎵 Opening Spotify player for:', `${track.artist} - ${track.name}`, '| ID:', track.id)
}

const getRelatedTracks = (track: Track) => {
  emit('related-tracks', track)
}

// Enhanced notification functions for better UX
const showTrackNotFoundNotification = (track: Track) => {
  // Create a beautiful notification instead of alert
  const notification = document.createElement('div')
  notification.className = 'fixed top-4 right-4 bg-gradient-to-br from-orange-500 to-red-500 text-white rounded-xl shadow-2xl p-4 max-w-md z-50 animate-slide-in'
  notification.innerHTML = `
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 mt-1">
        <svg class="w-5 h-5 text-orange-100" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
        </svg>
      </div>
      <div class="flex-1">
        <div class="font-semibold text-sm mb-1">Preview Unavailable</div>
        <div class="text-sm text-orange-100 leading-relaxed">
          Could not find "<span class="font-medium">${track.name}</span>" by <span class="font-medium">${track.artist}</span> on Spotify.
        </div>
        <div class="text-xs text-orange-200 mt-2">
          ${track.source === 'shazam' ? 'Track cleaning and ISRC lookup failed.' : 'Track not available on Spotify.'}
        </div>
      </div>
      <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 text-orange-100 hover:text-white transition-colors">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
        </svg>
      </button>
    </div>
  `
  
  document.body.appendChild(notification)
  
  // Auto remove after 6 seconds
  setTimeout(() => {
    notification.style.transform = 'translateX(100%)'
    notification.style.opacity = '0'
    setTimeout(() => notification.remove(), 300)
  }, 6000)
}

const showPreviewErrorNotification = (track: Track, errorMessage: string) => {
  // Create a beautiful error notification
  const notification = document.createElement('div')
  notification.className = 'fixed top-4 right-4 bg-gradient-to-br from-red-600 to-red-700 text-white rounded-xl shadow-2xl p-4 max-w-md z-50 animate-slide-in'
  notification.innerHTML = `
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 mt-1">
        <svg class="w-5 h-5 text-red-100" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
        </svg>
      </div>
      <div class="flex-1">
        <div class="font-semibold text-sm mb-1">Preview Failed</div>
        <div class="text-sm text-red-100 leading-relaxed">
          Failed to preview "<span class="font-medium">${track.name}</span>" by <span class="font-medium">${track.artist}</span>.
        </div>
        <div class="text-xs text-red-200 mt-2">
          ${errorMessage}
        </div>
      </div>
      <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 text-red-100 hover:text-white transition-colors">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
        </svg>
      </button>
    </div>
  `
  
  document.body.appendChild(notification)
  
  // Auto remove after 8 seconds for error (longer than info)
  setTimeout(() => {
    notification.style.transform = 'translateX(100%)'
    notification.style.opacity = '0'
    setTimeout(() => notification.remove(), 300)
  }, 8000)
}

// Track cleaning function for better cross-platform matching
const cleanTrackForQuery = (text: string): string => {
  if (!text) return ''
  
  // Check if this is likely a specific remix/version we want to keep
  const hasSpecificRemix = /\([^)]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^)]*\)/i.test(text) ||
                          /\[[^\]]*(?:[A-Z][a-z]+ (?:remix|mix|edit))[^\]]*\]/i.test(text)
  
  if (hasSpecificRemix) {
    // Keep specific remixes (e.g., "Mosca Remix", "David Guetta Remix")
    // Only clean up spacing and formatting
    return text
      .replace(/\s*&\s*/g, ' & ')
      .replace(/\s+/g, ' ')
      .replace(/\s*-\s*$/, '')
      .replace(/^\s*-\s*/, '')
      .trim()
  }
  
  // For non-specific versions, clean more aggressively
  return text
    // Remove generic version content
    .replace(/\s*\([^)]*(?:original|version|radio|extended|edit)(?!\s+remix)[^)]*\)/gi, '')
    .replace(/\s*\[[^\]]*(?:original|version|radio|extended|edit)(?!\s+remix)[^\]]*\]/gi, '')
    // Remove standalone generic words at the end
    .replace(/\s+(?:original|version|radio|extended|edit)$/gi, '')
    // Clean up spacing
    .replace(/\s*&\s*/g, ' & ')
    .replace(/\s+/g, ' ')
    .replace(/\s*-\s*$/, '')
    .replace(/^\s*-\s*/, '')
    .trim()
}

// Preview Shazam tracks by converting to Spotify
const previewShazamTrack = async (track: Track) => {
  const trackKey = getTrackKey(track)
  processingTrack.value = trackKey
  
  try {
    // Clean track and artist names for better matching
    const cleanedArtist = cleanTrackForQuery(track.artist)
    const cleanedTitle = cleanTrackForQuery(track.name)
    
    console.log('🎵 Converting Shazam track to Spotify for preview:', track.name)
    console.log('🧹 Original:', `"${track.artist}" - "${track.name}"`)
    console.log('🧹 Cleaned:', `"${cleanedArtist}" - "${cleanedTitle}"`)
    
    const response = await http.get('music-discovery/track-preview', {
      params: {
        artist_name: cleanedArtist,
        track_title: cleanedTitle,
        original_artist: track.artist, // Keep originals for fallback
        original_title: track.name,
        source: 'shazam'
      }
    })

    if (response.success && response.data && response.data.spotify_track_id) {
      console.log('✅ Found Spotify equivalent:', response.data.spotify_track_id)
      
      // Update the track object with Spotify ID for the player
      track.id = response.data.spotify_track_id
      
      // Now open the Spotify player with the converted track
      toggleSpotifyPlayer(track)
    } else {
      console.warn('❌ Could not find Spotify equivalent for Shazam track')
      showTrackNotFoundNotification(track)
    }
  } catch (error: any) {
    console.error('❌ Failed to convert Shazam track to Spotify:', error)
    showPreviewErrorNotification(track, error.response?.data?.error || error.message || 'Network error')
  } finally {
    processingTrack.value = null
  }
}

// Blacklist all unsaved tracks that are currently displayed
const blacklistUnsavedTracks = async () => {
  if (isBlacklisting.value) return
  
  isBlacklisting.value = true
  
  try {
    // Get all currently displayed tracks that are not saved
    const unsavedTracks = props.recommendations.filter(track => !isTrackSaved(track))
    
    if (unsavedTracks.length === 0) {
      console.log('No unsaved tracks to blacklist')
      return
    }
    
    console.log(`Starting to blacklist ${unsavedTracks.length} unsaved tracks...`)
    
    // Process tracks in batches to avoid overwhelming the API
    const batchSize = 5
    let processedCount = 0
    
    for (let i = 0; i < unsavedTracks.length; i += batchSize) {
      const batch = unsavedTracks.slice(i, i + batchSize)
      
      await Promise.all(batch.map(async (track) => {
        try {
          const trackKey = getTrackKey(track)
          
          // Skip if already blacklisted
          if (isTrackBlacklisted(track)) {
            console.log(`Skipping already blacklisted: ${track.artist} - ${track.name}`)
            return
          }
          
          const response = await http.post('music-preferences/blacklist-track', {
            isrc: track.external_ids?.isrc || track.id,
            track_name: track.name,
            artist_name: track.artist
          })
          
          if (response.success) {
            blacklistedTracks.value.add(trackKey)
            processedCount++
            console.log(`✅ Blacklisted: ${track.artist} - ${track.name}`)
          } else {
            console.error(`❌ Failed to blacklist: ${track.artist} - ${track.name}`, response.error)
          }
        } catch (error) {
          console.error(`❌ Error blacklisting: ${track.artist} - ${track.name}`, error)
        }
      }))
      
      // Small delay between batches to be nice to the API
      if (i + batchSize < unsavedTracks.length) {
        await new Promise(resolve => setTimeout(resolve, 100))
      }
    }
    
    console.log(`✅ Bulk blacklist complete! Processed ${processedCount} tracks`)
    
    // Emit the blacklisted track keys to the parent component
    const blacklistedKeys = unsavedTracks
      .filter(track => blacklistedTracks.value.has(getTrackKey(track)))
      .map(track => getTrackKey(track))
    
    if (blacklistedKeys.length > 0) {
      emit('tracks-blacklisted', blacklistedKeys)
    }
    
  } catch (error) {
    console.error('❌ Bulk blacklist failed:', error)
  } finally {
    isBlacklisting.value = false
  }
}

// Load user preferences on mount
onMounted(async () => {
  await loadUserPreferences()
})

// Load user's saved tracks and blacklisted items
const loadUserPreferences = async () => {
  try {
    // Load blacklisted tracks
    const blacklistedTracksResponse = await http.get('music-preferences/blacklisted-tracks')
    if (blacklistedTracksResponse.success && blacklistedTracksResponse.data) {
      blacklistedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        blacklistedTracks.value.add(trackKey)
      })
      console.log(`Loaded ${blacklistedTracks.value.size} blacklisted tracks`)
    }

    // Load saved tracks  
    const savedTracksResponse = await http.get('music-preferences/saved-tracks')
    if (savedTracksResponse.success && savedTracksResponse.data) {
      savedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        savedTracks.value.add(trackKey)
      })
      console.log(`Loaded ${savedTracks.value.size} saved tracks`)
    }

    // Load blacklisted artists
    const blacklistedArtistsResponse = await http.get('music-preferences/blacklisted-artists')
    if (blacklistedArtistsResponse.success && blacklistedArtistsResponse.data) {
      blacklistedArtistsResponse.data.forEach((artist: any) => {
        blacklistedArtists.value.add(artist.artist_name.toLowerCase())
      })
      console.log(`Loaded ${blacklistedArtists.value.size} blacklisted artists`)
    }

    // Load saved artists
    const savedArtistsResponse = await http.get('music-preferences/saved-artists')
    if (savedArtistsResponse.success && savedArtistsResponse.data) {
      savedArtistsResponse.data.forEach((artist: any) => {
        savedArtists.value.add(artist.artist_name.toLowerCase())
      })
      console.log(`Loaded ${savedArtists.value.size} saved artists`)
    }

  } catch (error) {
    console.log('Could not load user preferences (user may not be logged in)')
  }
}
</script>

<style scoped>
/* Spotify Dropdown Animations */
.spotify-dropdown-enter-active {
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.spotify-dropdown-leave-active {
  transition: all 0.25s cubic-bezier(0.55, 0.06, 0.68, 0.19);
}

.spotify-dropdown-enter-from {
  opacity: 0;
  transform: translateY(-10px) scaleY(0.8);
}

.spotify-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-5px) scaleY(0.9);
}

.spotify-dropdown-enter-to,
.spotify-dropdown-leave-from {
  opacity: 1;
  transform: translateY(0) scaleY(1);
}

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

/* Animation for notification slide-in */
:global(.animate-slide-in) {
  animation: slideInFromRight 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes slideInFromRight {
  from {
    opacity: 0;
    transform: translateX(100%);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>