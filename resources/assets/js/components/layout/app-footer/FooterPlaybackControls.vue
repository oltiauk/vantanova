<template>
  <div class="playback-controls flex flex-1 flex-col justify-center">
    <div class="flex items-center justify-between md:justify-center gap-5 md:gap-12 px-4 md:px-0">
      <LikeButton v-if="playable" :playable="playable" class="text-base" />
      <button v-else type="button" /> <!-- a placeholder to maintain the asymmetric layout -->
      
      <!-- Related Tracks button (only shows when SoundCloud track is playing) -->
      <FooterBtn 
        v-if="showRelatedTracksButton" 
        class="text-lg bg-k-accent hover:bg-k-accent/80 text-white rounded-lg px-3 py-2" 
        title="Find Related Tracks" 
        @click.prevent="openRelatedTracks"
      >
        <Icon :icon="faMusic" class="mr-1" />
        Related
      </FooterBtn>

      <FooterBtn class="text-2xl" title="Seek backward 10 seconds" @click.prevent="seekBackward">
        <img :src="seekBackward10" alt="Seek backward 10" class="w-7 h-7" />
      </FooterBtn>

      <PlayButton />

      <FooterBtn class="text-2xl" title="Seek forward 10 seconds" @click.prevent="seekForward">
        <img :src="seekForward10" alt="Seek forward 10" class="w-7 h-7" />
      </FooterBtn>

      <RepeatModeSwitch class="text-base" />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref, toRefs } from 'vue'
import { faMusic } from '@fortawesome/free-solid-svg-icons'
import { playbackService } from '@/services/playbackService'
import { requireInjection } from '@/utils/helpers'
import { CurrentPlayableKey } from '@/symbols'
import { eventBus } from '@/utils/eventBus'
import { isSong } from '@/utils/typeGuards'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import Router from '@/router'

// Import the custom seek icons
import seekBackward10 from '@/../img/seek-backward-10.svg'
import seekForward10 from '@/../img/seek-forward-10.svg'

import RepeatModeSwitch from '@/components/ui/RepeatModeSwitch.vue'
import LikeButton from '@/components/song/SongLikeButton.vue'
import PlayButton from '@/components/ui/FooterPlayButton.vue'
import FooterBtn from '@/components/layout/app-footer/FooterButton.vue'

const playable = requireInjection(CurrentPlayableKey, ref())

// Show Related Tracks button only when playing a SoundCloud track
const { showPlayer, currentTrack } = toRefs(soundcloudPlayerStore.state)

const showRelatedTracksButton = computed(() => {
  const isVisible = showPlayer.value
  const hasTrack = !!currentTrack.value
  
  console.log('🎵 Related Tracks Button Debug:', {
    isVisible,
    hasTrack,
    currentTrack: currentTrack.value?.title,
    shouldShow: isVisible && hasTrack,
    storeState: soundcloudPlayerStore.state
  })
  
  return isVisible && hasTrack
})

const seekBackward = () => {
  console.log('🎵 FooterPlaybackControls - seek backward 10 seconds')
  playbackService.seekBy(-10)
}

const seekForward = () => {
  console.log('🎵 FooterPlaybackControls - seek forward 10 seconds')
  playbackService.seekBy(10)
}


const openRelatedTracks = () => {
  const track = currentTrack.value
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
</script>

<style lang="postcss" scoped>
:fullscreen .playback-controls {
  @apply scale-125;
}
</style>
