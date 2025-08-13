<template>
  <tr class="inline-player-row">
    <td colspan="9" class="p-4 text-center">
      <div class="flex items-center justify-center gap-4 animate-slide-down w-full">
        <!-- Previous Track Button -->
        <button
          @click="$emit('previous')"
          :disabled="!hasPreviousTrack"
          class="w-10 h-10 rounded-full bg-gray-700 hover:bg-gray-600 disabled:bg-gray-800 disabled:opacity-50 flex items-center justify-center text-white transition-colors flex-shrink-0"
          title="Previous Track"
        >
          <Icon :icon="faChevronLeft" class="text-lg" />
        </button>
        
        <!-- SoundCloud Embedded Player -->
        <div class="w-full max-w-4xl">
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
          <div v-else class="h-32 bg-gray-800 rounded-lg flex items-center justify-center">
            <Icon :icon="faSoundcloud" class="text-gray-400 text-3xl" />
            <span class="text-gray-400 ml-2">Loading...</span>
          </div>
        </div>
        
        <!-- Next Track Button -->
        <button
          @click="$emit('next')"
          :disabled="!hasNextTrack"
          class="w-10 h-10 rounded-full bg-gray-700 hover:bg-gray-600 disabled:bg-gray-800 disabled:opacity-50 flex items-center justify-center text-white transition-colors flex-shrink-0"
          title="Next Track"
        >
          <Icon :icon="faChevronRight" class="text-lg" />
        </button>

        <!-- Close Button -->
        <button 
          @click="handleClose"
          class="w-10 h-10 rounded-full bg-gray-700 hover:bg-gray-600 flex items-center justify-center text-white transition-colors flex-shrink-0"
          title="Close Player"
        >
          <Icon :icon="faTimes" class="text-lg" />
        </button>
      </div>
    </td>
  </tr>
</template>

<script lang="ts" setup>
/**
 * SoundCloud Inline Player - Embedded Player Implementation
 * 
 * This component uses SoundCloud's embedded iframe player directly,
 * similar to how it's implemented in the footer player.
 */
import { computed } from 'vue'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { 
  faChevronLeft, 
  faChevronRight, 
  faTimes
} from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'

interface Props {
  track: {
    id: string
    title?: string
  }
  hasPreviousTrack?: boolean
  hasNextTrack?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  hasPreviousTrack: false,
  hasNextTrack: false
})

const emit = defineEmits<{
  close: []
  previous: []
  next: []
}>()

// Get embed URL from store
const embedUrl = computed(() => soundcloudPlayerStore.url)

// Close handler
const handleClose = () => {
  console.log('🎵 [INLINE] Player closed')
  emit('close')
}

// Clean embed URL function (same as footer player)
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
</script>

<style scoped>
.inline-player-row {
  background: transparent;
}

.inline-soundcloud-player {
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.animate-slide-down {
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
    max-height: 0;
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
    max-height: 300px;
  }
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
.inline-soundcloud-player * {
  pointer-events: auto !important;
}
</style>