<template>
  <div class="recommendations-table">
    <!-- Header -->
    <div v-if="recommendations.length > 0 || isDiscovering" class="mb-6">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-medium text-white">
          {{ isDiscovering ? 'Searching...' : 'Related Tracks' }}
        </h3>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isDiscovering" class="text-center p-12">
      <Icon :icon="faSpinner" spin class="text-4xl text-purple-400 mb-4" />
      <h3 class="text-lg font-semibold text-white mb-2">Searching...</h3>
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
                    <div class="text-white/80">{{ track.name }}</div>
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
                        @click="toggleYouTubePlayer(track)"
                        :disabled="processingTrack === getTrackKey(track)"
                        class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50"
                      >
                        <Icon :icon="expandedTrackId === getTrackKey(track) ? faTimes : faPlay" class="mr-1" />
                        {{ expandedTrackId === getTrackKey(track) ? 'Close' : 'Preview' }}
                      </button>
                    </div>
                  </td>
                </tr>

                <!-- YouTube Player Dropdown Row with Animation -->
                <Transition name="youtube-dropdown" mode="out-in">
                  <tr v-if="expandedTrackId === getTrackKey(track)" :key="`youtube-${track.id}`" class="border-b border-white/5">
                    <td colspan="5" class="p-0 overflow-hidden">
                      <div class="youtube-player-container bg-orange-50/5 p-4">
                        <div class="max-w-lg mx-auto">
                          <div class="bg-black rounded-lg overflow-hidden shadow-2xl" style="height: 166px;">
                            <div v-if="youtubeVideoCache[getTrackKey(track)] && youtubeVideoCache[getTrackKey(track)] !== 'NO_VIDEO_FOUND'">
                              <iframe
                                :key="youtubeVideoCache[getTrackKey(track)]"
                                :src="`https://www.youtube.com/embed/${youtubeVideoCache[getTrackKey(track)]}?autoplay=1&rel=0&modestbranding=1&fs=1&showinfo=0`"
                                :title="`${track.artist} - ${track.name}`"
                                class="w-full rounded-lg"
                                style="height: 166px;"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                                @load="() => console.log('✅ Video loaded for:', track.name)"
                              ></iframe>
                            </div>
                            <div v-else-if="youtubeVideoCache[getTrackKey(track)] === 'NO_VIDEO_FOUND'" class="flex items-center justify-center" style="height: 166px;">
                              <div class="text-center text-white/60">
                                <div class="text-red-400 mb-2 text-2xl">❌</div>
                                <div class="text-sm font-medium">No YouTube video found</div>
                                <div class="text-xs text-white/40 mt-1">for "{{ track.artist }} - {{ track.name }}"</div>
                              </div>
                            </div>
                            <div v-else class="flex items-center justify-center" style="height: 166px;">
                              <div class="text-white/60 text-center">
                                <div class="animate-spin w-8 h-8 border-2 border-orange-500/30 border-t-orange-500 rounded-full mx-auto mb-3"></div>
                                <div class="font-medium">Loading preview...</div>
                                <div class="text-xs text-white/40 mt-1">Finding "{{ track.artist }} - {{ track.name }}"</div>
                              </div>
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
import { faSpinner, faExclamationTriangle, faTimes, faHeart, faBan, faUserPlus, faUserMinus, faPlay, faRandom } from '@fortawesome/free-solid-svg-icons'
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
}>()

// State
const expandedTrackId = ref<string | null>(null)
const youtubeVideoCache = ref<Record<string, string>>({})
const processingTrack = ref<string | null>(null)

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
      const response = await http.delete('music-preferences/blacklist-track', {
        isrc: track.external_ids?.isrc || track.id,
        track_name: track.name,
        artist_name: track.artist
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
        isrc: track.external_ids?.isrc || track.id,
        track_name: track.name,
        artist_name: track.artist
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
      const response = await http.delete('music-preferences/blacklist-artist', {
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
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
        spotify_artist_id: track.artists?.[0]?.id || track.id,
        artist_name: track.artist
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

// YouTube functionality
const toggleYouTubePlayer = async (track: Track) => {
  const trackKey = getTrackKey(track)
  
  if (expandedTrackId.value === trackKey) {
    expandedTrackId.value = null
    return
  }

  expandedTrackId.value = trackKey

  if (!youtubeVideoCache.value[trackKey]) {
    const query = `${track.artist} ${track.name}`
    
    try {
      console.log('🔍 Searching YouTube for:', query)
      const response = await http.get('youtube/search', {
        params: {
          q: query
        }
      })
      
      console.log('YouTube API response for recommendations:', response)
      
      if (response && response.data && response.items?.length > 0) {
        // Handle YouTube API response format
        const video = response.items[0]
        if (video?.id?.videoId) {
          youtubeVideoCache.value[trackKey] = video.id.videoId
          console.log('✅ Found YouTube video (API) for:', query, '| ID:', video.id.videoId)
          return
        }
      } else if (response && response.data && Array.isArray(response.data)) {
        // Handle scraping response format  
        const video = response.data[0]
        if (video?.id) {
          youtubeVideoCache.value[trackKey] = video.id
          console.log('✅ Found YouTube video (scraping) for:', query, '| ID:', video.id)
          return
        }
      } else if (response && response.items?.length > 0) {
        // Handle direct response format
        const video = response.items[0]
        if (video?.id?.videoId) {
          youtubeVideoCache.value[trackKey] = video.id.videoId
          console.log('✅ Found YouTube video (direct) for:', query, '| ID:', video.id.videoId)
          return
        }
      }
      
    } catch (error) {
      console.error('YouTube search failed for:', query, error)
    }
    
    youtubeVideoCache.value[trackKey] = 'NO_VIDEO_FOUND'
    console.log('❌ No YouTube video found for:', query)
  }
}

const getRelatedTracks = (track: Track) => {
  emit('related-tracks', track)
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
/* YouTube Dropdown Animations */
.youtube-dropdown-enter-active {
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.youtube-dropdown-leave-active {
  transition: all 0.25s cubic-bezier(0.55, 0.06, 0.68, 0.19);
}

.youtube-dropdown-enter-from {
  opacity: 0;
  transform: translateY(-10px) scaleY(0.8);
}

.youtube-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-5px) scaleY(0.9);
}

.youtube-dropdown-enter-to,
.youtube-dropdown-leave-from {
  opacity: 1;
  transform: translateY(0) scaleY(1);
}

.youtube-player-container {
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
</style>