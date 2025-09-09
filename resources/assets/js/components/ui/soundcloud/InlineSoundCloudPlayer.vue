<template>
  <tr class="inline-player-row player-row">
    <td colspan="12" class="p-0">
      <div class="flex items-center justify-center player-container">
        <!-- SoundCloud Embedded Player -->
        <div class="w-full max-w-3xl mx-auto px-8 py-4">
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
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'

interface Props {
  track: {
    id: string
    title?: string
  }
}

const props = defineProps<Props>()

// Get embed URL from store
const embedUrl = computed(() => soundcloudPlayerStore.url)

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
  overflow: hidden;
}

.player-container {
  background: transparent;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

/* Ensure parent containers don't block interactions */
.player-container * {
  pointer-events: auto !important;
}
</style>