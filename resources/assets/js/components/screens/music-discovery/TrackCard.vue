<template>
    <div class="track-card result-item bg-k-bg-secondary border border-k-border rounded-lg p-4 hover:border-k-accent transition-all duration-200">
      <div class="flex items-center space-x-4">
        <!-- Album Art -->
        <div class="shrink-0">
          <img
            v-if="track.image"
            :src="track.image"
            :alt="`${track.name} by ${track.artist}`"
            class="w-16 h-16 rounded-lg object-cover"
          >
          <div
            v-else
            class="w-16 h-16 rounded-lg bg-k-bg-tertiary flex items-center justify-center"
          >
            <Icon :icon="faMusic" class="w-6 h-6 text-k-text-secondary" />
          </div>
        </div>
  
        <!-- Track Info -->
        <div class="flex-1 min-w-0">
          <h3 class="text-k-text-primary font-medium text-lg truncate">
            {{ track.name }}
          </h3>
          <p class="text-k-text-secondary text-base truncate">
            {{ track.artist }}
          </p>
          <p class="text-k-text-tertiary text-sm truncate">
            {{ track.album }}
          </p>
          <div v-if="track.duration_ms" class="text-k-text-tertiary text-xs mt-1">
            {{ formatDuration(track.duration_ms) }}
          </div>
        </div>
  
        <!-- Actions -->
        <div class="shrink-0 flex items-center space-x-2">
          <!-- Preference Buttons (RESTORED) -->
          <template v-if="!hidePref">
            <!-- Save Track Button -->
            <Btn
              size="sm"
              class="!p-2"
              title="Save Track (24 hours)"
              green
              @click="saveTrack"
            >
              <Icon :icon="faHeart" class="w-4 h-4" />
            </Btn>

            <!-- Save Artist Button -->
            <Btn
              size="sm"
              class="!p-2"
              title="Save Artist"
              blue
              @click="saveArtist"
            >
              <Icon :icon="faUserPlus" class="w-4 h-4" />
            </Btn>

            <!-- Ban Artist Button -->
            <Btn
              size="sm"
              class="!p-2"
              title="Ban Artist"
              red
              @click="banArtist"
            >
              <Icon :icon="faUserMinus" class="w-4 h-4" />
            </Btn>
          </template>
          
          <!-- Play Button -->
          <Btn
            size="sm"
            class="!p-2"
            :title="isPlaying ? 'Pause' : 'Play on YouTube'"
            @click="togglePlay"
          >
            <Icon :icon="isPlaying ? faPause : faPlay" class="w-4 h-4" />
          </Btn>
  
          <!-- External Link -->
          <Btn
            v-if="track.external_url"
            size="sm"
            class="!p-2"
            title="Open in Spotify"
            @click="openExternal"
          >
            <Icon :icon="faExternalLinkAlt" class="w-4 h-4" />
          </Btn>
  
          <!-- Fallback if no preview but has external -->
          <Btn
            v-else-if="!track.preview_url && track.external_url"
            size="sm"
            gray
            @click="openExternal"
          >
            Listen
          </Btn>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { ref, onUnmounted, computed } from 'vue'
  import { faMusic, faPlay, faPause, faExternalLinkAlt, faHeart, faUserPlus, faUserMinus } from '@fortawesome/free-solid-svg-icons'
  import { http } from '@/services/http'
  import { youTubeService } from '@/services/youTubeService'
  import { playbackService } from '@/services/playbackService'
  import { queueStore } from '@/stores/queueStore'
  import { requireInjection } from '@/utils/helpers'
  import { CurrentPlayableKey } from '@/symbols'
  
  import Btn from '@/components/ui/form/Btn.vue'
  
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
  const props = defineProps<{
    track: Track
    hidePref?: boolean
  }>()
  
  // State
  const currentPlayable = requireInjection(CurrentPlayableKey, ref())
  
  // Computed properties
  const isCurrentTrack = computed(() => {
    return currentPlayable.value?.id === `discovery-${props.track.id}`
  })
  
  const isPlaying = computed(() => {
    return isCurrentTrack.value && currentPlayable.value?.playback_state === 'Playing'
  })
  
  // Methods
  const formatDuration = (ms: number): string => {
    const minutes = Math.floor(ms / 60000)
    const seconds = Math.floor((ms % 60000) / 1000)
    return `${minutes}:${seconds.toString().padStart(2, '0')}`
  }
  
  const playTrack = async () => {
    console.log('🎵 TrackCard - playTrack called for:', props.track.name)
    
    // Create a playable object from the discovered track
    const playable: Song = {
      type: 'songs',
      id: `discovery-${props.track.id}`, // Use discovery prefix to avoid conflicts
      title: props.track.name,
      artist_name: props.track.artist,
      artist_id: 'unknown',
      album_name: props.track.album || 'Unknown Album',
      album_id: 'unknown',
      album_cover: props.track.image || null,
      length: props.track.duration_ms ? Math.floor(props.track.duration_ms / 1000) : 0,
      track: null,
      disc: null,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      is_public: true,
      liked: false,
      play_count: 0,
      play_count_registered: false,
      preloaded: false,
      playback_state: 'Stopped',
      deleted: false,
      genre: null,
      year: null,
      lyrics: null,
      owner_id: null,
      owner_name: null,
      collaborative: false,
      is_episode: false,
      is_podcast: false,
      podcast_id: null,
      episode_description: null,
      episode_link: null,
      episode_image: null,
      episode_metadata: null,
      created_by: null,
      visibility: 'public',
      storage: {
        type: 'youtube',
        metadata: {
          track_name: props.track.name,
          artist_name: props.track.artist,
          spotify_id: props.track.id
        }
      }
    }
    
    console.log('🎵 TrackCard - created playable object:', playable)
    
    // Add to queue and play using the playback service
    try {
      await playbackService.queueAndPlay([playable])
      console.log('🎵 TrackCard - successfully queued and playing track')
    } catch (error) {
      console.error('🎵 TrackCard - failed to queue and play track:', error)
      // Fallback to direct YouTube play
      youTubeService.playTrack({
        name: props.track.name,
        artist: props.track.artist
      })
    }
  }

  const togglePlay = async () => {
    console.log('🎵 TrackCard - togglePlay called:', {
      isCurrentTrack: isCurrentTrack.value,
      isPlaying: isPlaying.value
    })
    
    if (isCurrentTrack.value) {
      // This track is currently playing, toggle play/pause
      await playbackService.toggle()
    } else {
      // This track is not current, play it
      await playTrack()
    }
  }
  
  const openExternal = () => {
    if (props.track.external_url) {
      window.open(props.track.external_url, '_blank')
    }
  }
  
  // Save track to favorites (24-hour expiration)
  const saveTrack = async () => {
    const isrc = props.track.external_ids?.isrc
    if (!isrc) {
      // console.warn('⚠️ No ISRC available for track:', props.track.name)
      // Could show toast notification to user
      return
    }

    try {
      const response = await http.post('music-preferences/save-track', {
        isrc,
        track_name: props.track.name,
        artist_name: props.track.artist,
        spotify_id: props.track.id
      })

      if (response.success) {
        // console.log('✅ Track saved successfully:', props.track.name)
        // Could emit event to parent to show success message
      }
    } catch (error: any) {
      if (error.response?.status === 503 && error.response?.data?.code === 'TABLES_MISSING') {
        // console.warn('⚠️ Music preferences feature not set up yet')
        // Could show setup message to user
      } else {
        // console.error('❌ Failed to save track:', error.response?.data?.error || error.message)
      }
    }
  }

  // Save artist to favorites
  const saveArtist = async () => {
    const primaryArtist = props.track.artists?.[0]
    if (!primaryArtist?.id) {
      // console.warn('⚠️ No primary artist ID available for:', props.track.artist)
      return
    }

    try {
      const response = await http.post('music-preferences/save-artist', {
        spotify_artist_id: primaryArtist.id,
        artist_name: primaryArtist.name
      })

      if (response.success) {
        // console.log('✅ Artist saved successfully:', primaryArtist.name)
      }
    } catch (error: any) {
      if (error.response?.status === 503 && error.response?.data?.code === 'TABLES_MISSING') {
        // console.warn('⚠️ Music preferences feature not set up yet')
      } else {
        // console.error('❌ Failed to save artist:', error.response?.data?.error || error.message)
      }
    }
  }

  // Ban artist from recommendations
  const banArtist = async () => {
    const primaryArtist = props.track.artists?.[0]
    if (!primaryArtist?.id) {
      // console.warn('⚠️ No primary artist ID available for:', props.track.artist)
      return
    }

    try {
      const response = await http.post('music-preferences/blacklist-artist', {
        spotify_artist_id: primaryArtist.id,
        artist_name: primaryArtist.name
      })

      if (response.success) {
        // console.log('✅ Artist banned successfully:', primaryArtist.name)
      }
    } catch (error: any) {
      if (error.response?.status === 503 && error.response?.data?.code === 'TABLES_MISSING') {
        // console.warn('⚠️ Music preferences feature not set up yet')
      } else {
        // console.error('❌ Failed to ban artist:', error.response?.data?.error || error.message)
      }
    }
  }

  // Cleanup on component unmount
  onUnmounted(() => {
    // No cleanup needed for YouTube player
  })
  </script>
  
  <style scoped>
  .track-card:hover {
    transform: translateY(-1px);
    transition: all 0.2s ease;
  }
  </style>