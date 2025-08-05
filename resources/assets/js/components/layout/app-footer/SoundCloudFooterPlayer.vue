<template>
  <div class="soundcloud-footer-player flex items-start justify-center px-6 bg-k-bg-secondary w-full">
    <!-- SoundCloud Player with Skip Controls -->
    <div class="flex items-center gap-4 w-full max-w-4xl">
      <!-- Related Tracks Button -->
      <button
        @click="openRelatedTracks"
        class="px-3 py-2 rounded-lg bg-k-accent hover:bg-k-accent/80 flex items-center gap-2 text-white transition-colors flex-shrink-0 text-sm font-medium"
        title="Find Related Tracks"
      >
        <Icon :icon="faMusic" class="text-sm" />
        <span>Related Tracks</span>
      </button>
      
      <!-- Previous Track Button -->
      <button
        @click="skipToPrevious"
        :disabled="!canSkipPrevious"
        class="w-10 h-10 rounded-full bg-gray-700 hover:bg-gray-600 disabled:bg-gray-800 disabled:opacity-50 flex items-center justify-center text-white transition-colors flex-shrink-0"
        title="Previous Track"
      >
        <Icon :icon="faChevronLeft" class="text-lg" />
      </button>
      
      <!-- SoundCloud Player -->
      <div class="flex-1">
        <iframe
          v-if="embedUrl"
          :src="cleanEmbedUrl(embedUrl)"
          width="100%"
          height="130"
          scrolling="no"
          frameborder="no"
          allow="autoplay"
          class="w-full rounded-lg m-0"
        ></iframe>
        <div v-else class="h-30 bg-gray-800 rounded-lg flex items-center justify-center">
          <Icon :icon="faSoundcloud" class="text-gray-400 text-3xl" />
          <span class="text-gray-400 ml-2">Loading...</span>
        </div>
      </div>
      
      <!-- Next Track Button -->
      <button
        @click="skipToNext"
        :disabled="!canSkipNext"
        class="w-10 h-10 rounded-full bg-gray-700 hover:bg-gray-600 disabled:bg-gray-800 disabled:opacity-50 flex items-center justify-center text-white transition-colors flex-shrink-0"
        title="Next Track"
      >
        <Icon :icon="faChevronRight" class="text-lg" />
      </button>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { faChevronLeft, faChevronRight, faMusic } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { computed } from 'vue'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { eventBus } from '@/utils/eventBus'
import Router from '@/router'

const currentTrack = computed(() => {
  const track = soundcloudPlayerStore.track
  console.log('🎵 [DEBUG FOOTER] Current track changed:', track?.title || 'None')
  return track
})

const embedUrl = computed(() => {
  const url = soundcloudPlayerStore.url
  console.log('🎵 [DEBUG FOOTER] Embed URL changed:', url || 'None')
  return url
})

// Skip navigation computed properties
const canSkipPrevious = computed(() => soundcloudPlayerStore.canSkipPrevious)
const canSkipNext = computed(() => soundcloudPlayerStore.canSkipNext)

// Related tracks function
const openRelatedTracks = () => {
  const track = soundcloudPlayerStore.state.currentTrack
  if (track) {
    console.log('🎵 Opening SoundCloud Related Tracks for:', track.title)
    // Create URN from SoundCloud track ID (format: soundcloud:tracks:{id})
    const trackUrn = `soundcloud:tracks:${track.id}`
    
    // Store the related tracks data for the next screen
    eventBus.emit('SOUNDCLOUD_RELATED_TRACKS_DATA', {
      type: 'related',
      trackUrn,
      trackTitle: track.title,
      artist: track.user?.username || 'Unknown Artist'
    })
    
    // Navigate to the SoundCloud Related Tracks screen using router
    Router.go('soundcloud-related-tracks')
  }
}

// Skip functions
const skipToPrevious = () => {
  console.log('🎵 Skip to previous track')
  eventBus.emit('SOUNDCLOUD_SKIP_PREVIOUS')
}

const skipToNext = () => {
  console.log('🎵 Skip to next track')
  eventBus.emit('SOUNDCLOUD_SKIP_NEXT')
}

const cleanEmbedUrl = (url: string): string => {
  if (!url) return url
  
  // Remove visual=true parameter to get HTML5 player instead
  let cleanUrl = url.replace(/[&?]visual=true/g, '')
  
  // Ensure we don't have visual=false either
  cleanUrl = cleanUrl.replace(/[&?]visual=false/g, '')
  
  // Add parameter to hide the left-side artwork image
  cleanUrl += '&show_artwork=false'
  
  return cleanUrl
}

const formatDate = (dateString: string): string => {
  if (!dateString) return ''
  try {
    const date = new Date(dateString)
    const now = new Date()
    const diffTime = Math.abs(now.getTime() - date.getTime())
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    
    if (diffDays < 30) {
      return `${diffDays}d ago`
    } else if (diffDays < 365) {
      const months = Math.floor(diffDays / 30)
      return `${months}mo ago`
    } else {
      const years = Math.floor(diffDays / 365)
      return `${years}y ago`
    }
  } catch {
    return ''
  }
}
</script>

<style scoped>
.soundcloud-footer-player {
  height: 130px; /* Height to show track info and clickable waveform */
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding: 0 !important;
  margin: 0 !important;
}

/* Make sure iframe is fully interactive and remove all spacing */
iframe {
  pointer-events: auto !important;
  border-radius: 8px;
  margin: 0 !important;
  padding: 0 !important;
  vertical-align: top !important;
  display: block !important;
  position: relative !important;
  z-index: 10 !important;
}

/* Ensure parent containers don't block interactions */
.soundcloud-footer-player * {
  pointer-events: auto !important;
}
</style>