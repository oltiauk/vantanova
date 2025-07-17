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
          
          <!-- Preview Button -->
          <Btn
            v-if="track.preview_url"
            size="sm"
            class="!p-2"
            :title="isPlaying ? 'Stop Preview' : 'Play Preview'"
            @click="togglePreview"
          >
            <Icon :icon="isPlaying ? faStop : faPlay" class="w-4 h-4" />
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
  import { ref, onUnmounted } from 'vue'
  import { faMusic, faPlay, faStop, faExternalLinkAlt, faHeart, faUserPlus, faUserMinus } from '@fortawesome/free-solid-svg-icons'
  import { http } from '@/services/http'
  
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
  }>()
  
  // State
  const isPlaying = ref(false)
  const currentAudio = ref<HTMLAudioElement | null>(null)
  
  // Methods
  const formatDuration = (ms: number): string => {
    const minutes = Math.floor(ms / 60000)
    const seconds = Math.floor((ms % 60000) / 1000)
    return `${minutes}:${seconds.toString().padStart(2, '0')}`
  }
  
  const togglePreview = () => {
    if (!props.track.preview_url) return
  
    if (isPlaying.value && currentAudio.value) {
      // Stop current preview
      currentAudio.value.pause()
      currentAudio.value = null
      isPlaying.value = false
    } else {
      // Start new preview
      const audio = new Audio(props.track.preview_url)
      audio.volume = 0.5
      
      audio.addEventListener('loadstart', () => {
        isPlaying.value = true
        currentAudio.value = audio
      })
  
      audio.addEventListener('ended', () => {
        isPlaying.value = false
        currentAudio.value = null
      })
  
      audio.addEventListener('error', (error) => {
        console.error('Preview playback failed:', error)
        isPlaying.value = false
        currentAudio.value = null
      })
  
      audio.play().catch(error => {
        console.error('Preview playback failed:', error)
        isPlaying.value = false
        currentAudio.value = null
      })
  
      // Auto-stop after 30 seconds (Spotify preview length)
      setTimeout(() => {
        if (currentAudio.value === audio && isPlaying.value) {
          audio.pause()
          isPlaying.value = false
          currentAudio.value = null
        }
      }, 30000)
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
      console.warn('⚠️ No ISRC available for track:', props.track.name)
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
        console.log('✅ Track saved successfully:', props.track.name)
        // Could emit event to parent to show success message
      }
    } catch (error: any) {
      if (error.response?.status === 503 && error.response?.data?.code === 'TABLES_MISSING') {
        console.warn('⚠️ Music preferences feature not set up yet')
        // Could show setup message to user
      } else {
        console.error('❌ Failed to save track:', error.response?.data?.error || error.message)
      }
    }
  }

  // Save artist to favorites
  const saveArtist = async () => {
    const primaryArtist = props.track.artists?.[0]
    if (!primaryArtist?.id) {
      console.warn('⚠️ No primary artist ID available for:', props.track.artist)
      return
    }

    try {
      const response = await http.post('music-preferences/save-artist', {
        spotify_artist_id: primaryArtist.id,
        artist_name: primaryArtist.name
      })

      if (response.success) {
        console.log('✅ Artist saved successfully:', primaryArtist.name)
      }
    } catch (error: any) {
      if (error.response?.status === 503 && error.response?.data?.code === 'TABLES_MISSING') {
        console.warn('⚠️ Music preferences feature not set up yet')
      } else {
        console.error('❌ Failed to save artist:', error.response?.data?.error || error.message)
      }
    }
  }

  // Ban artist from recommendations
  const banArtist = async () => {
    const primaryArtist = props.track.artists?.[0]
    if (!primaryArtist?.id) {
      console.warn('⚠️ No primary artist ID available for:', props.track.artist)
      return
    }

    try {
      const response = await http.post('music-preferences/blacklist-artist', {
        spotify_artist_id: primaryArtist.id,
        artist_name: primaryArtist.name
      })

      if (response.success) {
        console.log('✅ Artist banned successfully:', primaryArtist.name)
      }
    } catch (error: any) {
      if (error.response?.status === 503 && error.response?.data?.code === 'TABLES_MISSING') {
        console.warn('⚠️ Music preferences feature not set up yet')
      } else {
        console.error('❌ Failed to ban artist:', error.response?.data?.error || error.message)
      }
    }
  }

  // Cleanup on component unmount
  onUnmounted(() => {
    if (currentAudio.value) {
      currentAudio.value.pause()
      currentAudio.value = null
    }
  })
  </script>
  
  <style scoped>
  .track-card:hover {
    transform: translateY(-1px);
    transition: all 0.2s ease;
  }
  </style>