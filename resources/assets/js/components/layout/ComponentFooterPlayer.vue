<template>
  <footer
    class="fixed bottom-0 left-0 right-0 flex flex-col bg-k-bg-secondary z-50"
    :class="showSoundCloudPlayer ? 'h-[130px]' : 'h-k-footer-height'"
  >
    <YouTubeProgressBar v-if="playable && youtubePlayer" :youtube-player="youtubePlayer" />
    
    <!-- YouTube Player positioned above footer -->
    <YouTubePlayer ref="youtubePlayer" class="youtube-player-floating" />
    
    <div class="fullscreen-backdrop hidden" />

    <div class="wrapper relative" :class="showSoundCloudPlayer ? 'flex flex-col' : 'flex flex-1'">
      <!-- SoundCloud Player (when on SoundCloud page) -->
      <SoundCloudFooterPlayer v-if="showSoundCloudPlayer" />
      
      <!-- Default Koel Player (always show player controls) -->
      <template v-else>
        <div class="flex flex-1 items-center">
          <SongInfo />
        </div>
        <PlaybackControls />
        <ExtraControls />
      </template>
    </div>
  </footer>
</template>

<script lang="ts" setup>
import { computed, nextTick, ref, watch } from 'vue'
import { isSong } from '@/utils/typeGuards'
import { requireInjection } from '@/utils/helpers'
import { CurrentPlayableKey } from '@/symbols'
import { artistStore } from '@/stores/artistStore'
import { preferenceStore } from '@/stores/preferenceStore'
import { playbackService } from '@/services/playbackService'
import { useRouter } from '@/composables/useRouter'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'

import YouTubePlayer from '@/components/layout/app-footer/YouTubePlayer.vue'
import YouTubeProgressBar from '@/components/layout/app-footer/YouTubeProgressBar.vue'
import SongInfo from '@/components/layout/app-footer/FooterSongInfo.vue'
import ExtraControls from '@/components/layout/app-footer/FooterExtraControls.vue'
import PlaybackControls from '@/components/layout/app-footer/FooterPlaybackControls.vue'
import SoundCloudFooterPlayer from '@/components/layout/app-footer/SoundCloudFooterPlayer.vue'

const playable = requireInjection(CurrentPlayableKey, ref())

const artist = ref<Artist>()
const youtubePlayer = ref<InstanceType<typeof YouTubePlayer>>()
const { isCurrentScreen } = useRouter()

// Check if we're on a SoundCloud-related page and should show SoundCloud player
const showSoundCloudPlayer = computed(() => {
  return (isCurrentScreen('SoundCloud') || isCurrentScreen('SoundCloudRelatedTracks')) && soundcloudPlayerStore.isVisible
})

watch(playable, async () => {
  if (!playable.value) {
    return
  }

  if (isSong(playable.value)) {
    try {
      artist.value = await artistStore.resolve(playable.value.artist_id)
    } catch (error) {
      // Ignore artist resolution errors for external tracks (like from music discovery)
      console.warn('Could not resolve artist for track:', playable.value.title, error)
      artist.value = undefined
    }
  }
})

const initPlaybackRelatedServices = async () => {
  if (!youtubePlayer.value) {
    await nextTick()
    await initPlaybackRelatedServices()
    return
  }

  playbackService.initWithYouTube(youtubePlayer.value)
}

watch(preferenceStore.initialized, async initialized => {
  if (!initialized) {
    return
  }
  await initPlaybackRelatedServices()
}, { immediate: true })
</script>

<style lang="postcss" scoped>
footer {
  box-shadow: 0 0 30px 20px rgba(0, 0, 0, 0.2);
}

.youtube-player-floating {
  position: fixed;
  bottom: calc(var(--footer-height) + 45px);
  left: 0;
  z-index: 30;
  width: var(--sidebar-width);
}
</style>