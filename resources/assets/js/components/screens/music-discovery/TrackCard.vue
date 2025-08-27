<template>
    <div class="track-card group bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-sm border border-slate-700/50 hover:border-purple-500/50 rounded-2xl p-6 transition-all duration-300 hover:shadow-2xl hover:shadow-purple-500/10">
      <!-- Album Art -->
      <div class="relative mb-4">
        <img
          v-if="track.image"
          :src="track.image"
          :alt="`${track.name} by ${track.artist}`"
          class="w-full aspect-square rounded-xl object-cover group-hover:scale-105 transition-transform duration-300"
        >
        <div
          v-else
          class="w-full aspect-square rounded-xl bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center"
        >
          <Icon :icon="faMusic" class="w-12 h-12 text-slate-400" />
        </div>
        
        <!-- Play overlay on hover -->
        <div class="absolute inset-0 bg-black/40 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
          <button
            @click="togglePlay"
            class="p-4 bg-white/20 hover:bg-white/30 rounded-full backdrop-blur-sm transition-all duration-200 transform hover:scale-110"
          >
            <Icon :icon="isPlaying ? faPause : faPlay" class="w-6 h-6 text-white" />
          </button>
        </div>
      </div>

      <!-- Track Info -->
      <div class="mb-4">
        <h3 class="text-white font-bold text-lg leading-tight mb-2 group-hover:text-purple-300 transition-colors duration-200">
          {{ track.name }}
        </h3>
        <p class="text-slate-300 font-medium text-base mb-1">
          {{ track.artist }}
        </p>
        <p class="text-slate-500 text-sm mb-2">
          {{ track.album }}
        </p>
        <div v-if="track.duration_ms" class="text-slate-600 text-xs">
          {{ formatDuration(track.duration_ms) }}
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-between">
        <!-- Left actions -->
        <div class="flex items-center gap-2">
          <template v-if="!hidePref">
            <!-- Save Track Button -->
            <button
              @click="saveTrack"
              title="Save Track (24 hours)"
              class="p-2 bg-green-500/20 hover:bg-green-500/30 text-green-400 rounded-lg transition-all duration-200 hover:scale-110"
            >
              <Icon :icon="faHeart" class="w-4 h-4" />
            </button>

            <!-- Save Artist Button -->
            <button
              @click="saveArtist"
              title="Save Artist"
              class="p-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded-lg transition-all duration-200 hover:scale-110"
            >
              <Icon :icon="faUserPlus" class="w-4 h-4" />
            </button>

            <!-- Ban Artist Button -->
            <button
              @click="banArtist"
              title="Ban Artist"
              class="p-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg transition-all duration-200 hover:scale-110"
            >
              <Icon :icon="faUserMinus" class="w-4 h-4" />
            </button>
          </template>
        </div>
        
        <!-- Right actions -->
        <div class="flex items-center gap-2">
          <!-- Preview Button -->
          <button
            v-if="track.source === 'shazam' || track.source === 'shazam_fallback'"
            @click="testPreview"
            class="p-2 bg-orange-500/20 hover:bg-orange-500/30 text-orange-400 rounded-lg transition-all duration-200 hover:scale-110 animate-pulse"
          >
            <Icon :icon="faEye" class="w-4 h-4" />
          </button>
          
          <!-- External Link -->
          <button
            v-if="track.external_url"
            @click="openExternal"
            class="p-2 bg-slate-600/50 hover:bg-slate-600/70 text-slate-300 rounded-lg transition-all duration-200 hover:scale-110"
          >
            <Icon :icon="faExternalLinkAlt" class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  import { ref, onUnmounted, computed } from 'vue'
  import { faMusic, faPlay, faPause, faExternalLinkAlt, faHeart, faUserPlus, faUserMinus, faEye } from '@fortawesome/free-solid-svg-icons'
  import { http } from '@/services/http'
  import { youTubeService } from '@/services/youTubeService'
  import { playbackService } from '@/services/playbackService'
  import { queueStore } from '@/stores/queueStore'
  import { requireInjection } from '@/utils/helpers'
  import { CurrentPlayableKey } from '@/symbols'
  
  
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

  // Test preview for tracks
  const testPreview = async () => {
    console.log('🎵 Testing preview for track:', props.track.name)
    
    try {
      const response = await http.get('music-discovery/track-preview', {
        params: {
          artist_name: props.track.artist,
          track_title: props.track.name,
          source: 'shazam'
        }
      })

      if (response.success && response.data) {
        console.log('✅ Preview data received:', response.data)
        
        // Create a popup or modal to show Spotify embed
        const popup = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes')
        if (popup) {
          popup.document.write(`
            <html>
              <head>
                <title>Spotify Preview: ${props.track.name}</title>
                <style>
                  body { 
                    margin: 0; 
                    padding: 20px; 
                    font-family: Arial, sans-serif; 
                    background: #121212; 
                    color: white; 
                  }
                  .header { margin-bottom: 20px; text-align: center; }
                  .success { color: #1db954; font-weight: bold; }
                  .info { color: #ccc; margin-bottom: 10px; }
                </style>
              </head>
              <body>
                <div class="header">
                  <h2>🎵 Track Preview Test</h2>
                  <div class="success">✅ Successfully found on Spotify!</div>
                </div>
                <div class="info"><strong>Track:</strong> ${props.track.name}</div>
                <div class="info"><strong>Artist:</strong> ${props.track.artist}</div>
                <div class="info"><strong>Source:</strong> Music Discovery</div>
                <div class="info"><strong>Spotify ID:</strong> ${response.data.spotify_track_id}</div>
                <hr style="border-color: #333; margin: 20px 0;">
                ${response.data.oembed.html}
              </body>
            </html>
          `)
          popup.document.close()
        }
      } else {
        console.warn('❌ Preview failed:', response.error || 'Unknown error')
        alert(`Preview failed: ${response.error || 'Could not find track on Spotify'}`)
      }
    } catch (error: any) {
      console.error('❌ Preview request failed:', error)
      alert(`Preview failed: ${error.response?.data?.error || error.message || 'Network error'}`)
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