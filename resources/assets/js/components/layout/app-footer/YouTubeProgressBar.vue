<template>
  <div class="progress-bar absolute top-0 left-0 w-full h-1 bg-k-bg-primary/20 cursor-pointer" @click="seek">
    <div
      class="progress-fill h-full bg-k-highlight transition-all duration-300 ease-out"
      :style="{ width: `${progress}%` }"
    />
  </div>
</template>

<script lang="ts" setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { requireInjection } from '@/utils/helpers'
import { CurrentPlayableKey } from '@/symbols'

const playable = requireInjection(CurrentPlayableKey, ref())
const progress = ref(0)

const props = defineProps<{
  youtubePlayer: any
}>()

let progressInterval: number

const updateProgress = () => {
  if (!props.youtubePlayer || !playable.value) return

  const currentTime = props.youtubePlayer.getCurrentTime()
  const duration = props.youtubePlayer.getDuration()

  if (duration > 0) {
    progress.value = (currentTime / duration) * 100
  }
}

const seek = (event: MouseEvent) => {
  if (!props.youtubePlayer || !playable.value) return

  const rect = (event.target as HTMLElement).getBoundingClientRect()
  const clickX = event.clientX - rect.left
  const percentage = clickX / rect.width
  const duration = props.youtubePlayer.getDuration()

  if (duration > 0) {
    const seekTime = percentage * duration
    props.youtubePlayer.seekTo(seekTime)
  }
}

onMounted(() => {
  progressInterval = window.setInterval(updateProgress, 1000)
})

onUnmounted(() => {
  if (progressInterval) {
    clearInterval(progressInterval)
  }
})
</script>

<style lang="postcss" scoped>
.progress-bar {
  :fullscreen & {
    @apply h-2 bg-white/20;
  }
}

.progress-fill {
  :fullscreen & {
    @apply bg-white;
  }
}
</style>