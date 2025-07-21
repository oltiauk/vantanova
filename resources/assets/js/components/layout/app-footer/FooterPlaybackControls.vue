<template>
  <div class="playback-controls flex flex-1 flex-col justify-center">
    <div class="flex items-center justify-between md:justify-center gap-5 md:gap-12 px-4 md:px-0">
      <LikeButton v-if="playable" :playable="playable" class="text-base" />
      <button v-else type="button" /> <!-- a placeholder to maintain the asymmetric layout -->

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
import { ref } from 'vue'
import { playbackService } from '@/services/playbackService'
import { requireInjection } from '@/utils/helpers'
import { CurrentPlayableKey } from '@/symbols'

// Import the custom seek icons
import seekBackward10 from '@/../img/seek-backward-10.svg'
import seekForward10 from '@/../img/seek-forward-10.svg'

import RepeatModeSwitch from '@/components/ui/RepeatModeSwitch.vue'
import LikeButton from '@/components/song/SongLikeButton.vue'
import PlayButton from '@/components/ui/FooterPlayButton.vue'
import FooterBtn from '@/components/layout/app-footer/FooterButton.vue'

const playable = requireInjection(CurrentPlayableKey, ref())

const seekBackward = () => {
  console.log('🎵 FooterPlaybackControls - seek backward 10 seconds')
  playbackService.seekBy(-10)
}

const seekForward = () => {
  console.log('🎵 FooterPlaybackControls - seek forward 10 seconds')
  playbackService.seekBy(10)
}
</script>

<style lang="postcss" scoped>
:fullscreen .playback-controls {
  @apply scale-125;
}
</style>
