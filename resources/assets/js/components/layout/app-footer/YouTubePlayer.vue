<template>
  <div class="youtube-player-container relative" :class="{ 'video-loading': isLoading, 'hidden': !isVisible }">
    <!-- Close Button -->
    <button
      @click="closePlayer"
      class="absolute top-2 right-2 z-20 w-8 h-8 bg-black/70 hover:bg-black/90 rounded-full flex items-center justify-center text-white text-sm transition-all"
      title="Close YouTube Player"
    >
      ✕
    </button>
    
    <div
      ref="playerContainer"
      class="youtube-player w-full h-full rounded-lg overflow-hidden shadow-lg bg-black/20"
    />
    <div
      v-if="isLoading"
      class="loading-overlay absolute inset-0 bg-black/50 flex items-center justify-center rounded-lg"
    >
      <div class="loading-spinner w-6 h-6 border-2 border-white/20 border-t-white rounded-full animate-spin" />
    </div>
  </div>
</template>

<script lang="ts" setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { requireInjection } from '@/utils/helpers'
import { CurrentPlayableKey } from '@/symbols'
import { isSong } from '@/utils/typeGuards'
import { youTubeService } from '@/services/youTubeService'
import { eventBus } from '@/utils/eventBus'
import { queueStore } from '@/stores/queueStore'
import { useRouter } from '@/composables/useRouter'

const playable = requireInjection(CurrentPlayableKey, ref())
const playerContainer = ref<HTMLElement>()
const isLoading = ref(false)
const isVisible = ref(false)
const { isCurrentScreen } = useRouter()

let youtubePlayer: any = null
let isPlayerReady = false
let currentVideoId: string | null = null

// Load YouTube IFrame API
const loadYouTubeAPI = (): Promise<void> => {
  return new Promise((resolve) => {
    if (window.YT && window.YT.Player) {
      resolve()
      return
    }

    const tag = document.createElement('script')
    tag.src = 'https://www.youtube.com/iframe_api'
    const firstScriptTag = document.getElementsByTagName('script')[0]
    firstScriptTag.parentNode?.insertBefore(tag, firstScriptTag)

    window.onYouTubeIframeAPIReady = () => {
      resolve()
    }
  })
}

const createPlayer = async () => {
  if (!playerContainer.value) return

  try {
    await loadYouTubeAPI()
    
    youtubePlayer = new window.YT.Player(playerContainer.value, {
      width: '100%',
      height: '100%',
      playerVars: {
        autoplay: 0,
        controls: 1,
        disablekb: 0,
        enablejsapi: 1,
        fs: 1,
        iv_load_policy: 3,
        modestbranding: 1,
        origin: window.location.origin,
        playsinline: 1,
        rel: 0,
        showinfo: 0,
        wmode: 'opaque'
      },
      events: {
        onReady: onPlayerReady,
        onStateChange: onPlayerStateChange,
        onError: onPlayerError
      }
    })
  } catch (error) {
    // console.error('Failed to create YouTube player:', error)
  }
}

const onPlayerReady = () => {
  isPlayerReady = true
  // console.log('YouTube player ready')
}

const onPlayerStateChange = (event: any) => {
  const state = event.data
  // console.log('🎵 YouTubePlayer - state changed:', {
  //   state,
  //   stateName: getStateName(state),
  //   currentSong: queueStore.current?.id,
  //   currentPlaybackState: queueStore.current?.playback_state,
  //   playableValue: playable.value?.id
  // })
  
  // Emit events for synchronization with app controls
  switch (state) {
    case window.YT.PlayerState.PLAYING:
      isLoading.value = false
      // Update playback state for footer controls - try both queueStore.current and playable.value
      if (queueStore.current) {
        queueStore.current.playback_state = 'Playing'
        // console.log('🎵 YouTubePlayer - state change: set queueStore.current playback_state to Playing')
      } else if (playable.value) {
        playable.value.playback_state = 'Playing'
        // console.log('🎵 YouTubePlayer - state change: set playable.value playback_state to Playing')
      } else {
        // console.warn('🎵 YouTubePlayer - no current song to update state for')
      }
      eventBus.emit('YOUTUBE_PLAYER_PLAYING')
      break
    case window.YT.PlayerState.PAUSED:
      isLoading.value = false
      // Update playback state for footer controls - try both queueStore.current and playable.value
      if (queueStore.current) {
        queueStore.current.playback_state = 'Paused'
        // console.log('🎵 YouTubePlayer - state change: set queueStore.current playback_state to Paused')
      } else if (playable.value) {
        playable.value.playback_state = 'Paused'
        // console.log('🎵 YouTubePlayer - state change: set playable.value playback_state to Paused')
      } else {
        // console.warn('🎵 YouTubePlayer - no current song to update state for')
      }
      eventBus.emit('YOUTUBE_PLAYER_PAUSED')
      break
    case window.YT.PlayerState.ENDED:
      isLoading.value = false
      // Update playback state for footer controls - try both queueStore.current and playable.value
      if (queueStore.current) {
        queueStore.current.playback_state = 'Stopped'
        // console.log('🎵 YouTubePlayer - state change: set queueStore.current playback_state to Stopped')
      } else if (playable.value) {
        playable.value.playback_state = 'Stopped'
        // console.log('🎵 YouTubePlayer - state change: set playable.value playback_state to Stopped')
      } else {
        // console.warn('🎵 YouTubePlayer - no current song to update state for')
      }
      eventBus.emit('YOUTUBE_PLAYER_ENDED')
      break
    case window.YT.PlayerState.BUFFERING:
      // console.log('🎵 YouTubePlayer - state change: buffering')
      // Keep loading state while buffering
      break
    default:
      // console.log('🎵 YouTubePlayer - unknown state:', state)
  }
}

const getStateName = (state: number): string => {
  if (typeof window.YT === 'undefined') return 'UNKNOWN'
  
  switch (state) {
    case window.YT.PlayerState.UNSTARTED: return 'UNSTARTED'
    case window.YT.PlayerState.ENDED: return 'ENDED'
    case window.YT.PlayerState.PLAYING: return 'PLAYING'
    case window.YT.PlayerState.PAUSED: return 'PAUSED'
    case window.YT.PlayerState.BUFFERING: return 'BUFFERING'
    case window.YT.PlayerState.CUED: return 'CUED'
    default: return 'UNKNOWN'
  }
}

const onPlayerError = (error: any) => {
  // console.error('YouTube player error:', error)
  
  // Handle different YouTube error codes
  const errorCode = error.data
  let errorMessage = 'Unknown error'
  
  switch (errorCode) {
    case 2:
      errorMessage = 'Invalid video ID'
      break
    case 5:
      errorMessage = 'HTML5 player error'
      break
    case 100:
      errorMessage = 'Video not found or private'
      break
    case 101:
    case 150:
      errorMessage = 'Video embedding disabled by owner'
      // Try to find an alternative video
      tryNextVideo()
      return
    default:
      errorMessage = `Error code: ${errorCode}`
  }
  
  // console.warn('YouTube player error:', errorMessage)
  isLoading.value = false
}

let currentSearchResults: any[] = []
let currentVideoIndex = 0

const tryNextVideo = async () => {
  if (currentSearchResults.length > currentVideoIndex + 1) {
    currentVideoIndex++
    const nextVideo = currentSearchResults[currentVideoIndex]
    const videoId = nextVideo.id.videoId
    
    if (videoId && videoId !== currentVideoId) {
      currentVideoId = videoId
      // console.log('Trying next video:', nextVideo.snippet.title)
      youtubePlayer.loadVideoById(videoId)
    } else {
      // console.warn('No more videos to try')
      isLoading.value = false
    }
  } else {
    // console.warn('No more videos to try')
    isLoading.value = false
  }
}

const searchAndPlayVideo = async (song: Song) => {
  if (!isPlayerReady) return
  
  // console.log('🎵 YouTubePlayer - searchAndPlayVideo called for:', song.title, 'ID:', song.id)
  isLoading.value = true
  
  try {
    let result
    
    // Check if this is a discovered track (starts with "discovery-")
    if (song.id.startsWith('discovery-')) {
      // console.log('🎵 YouTubePlayer - detected discovered track, using query search')
      const query = `${song.title} ${song.artist_name}`
      result = await youTubeService.searchVideosByQuery(query)
    } else {
      // console.log('🎵 YouTubePlayer - regular song, using song search')
      result = await youTubeService.searchVideosBySong(song, '')
    }
    
    if (result.items && result.items.length > 0) {
      // Store search results for fallback
      currentSearchResults = result.items
      currentVideoIndex = 0
      
      const firstVideo = result.items[0]
      const videoId = firstVideo.id.videoId
      
      if (videoId && videoId !== currentVideoId) {
        currentVideoId = videoId
        isVisible.value = true
        // console.log('🎵 YouTubePlayer - loading video:', firstVideo.snippet.title, 'for song:', song.title)
        // console.log('🎵 YouTubePlayer - before loadVideoById - queueStore.current:', queueStore.current?.id)
        youtubePlayer.loadVideoById(videoId)
        // console.log('🎵 YouTubePlayer - after loadVideoById - queueStore.current:', queueStore.current?.id)
      } else {
        // console.warn('🎵 YouTubePlayer - no valid video ID found for song:', song.title)
        isLoading.value = false
      }
    } else {
      // console.warn('🎵 YouTubePlayer - no YouTube videos found for song:', song.title)
      isLoading.value = false
    }
  } catch (error) {
    // console.error('🎵 YouTubePlayer - failed to search YouTube videos:', error)
    isLoading.value = false
  }
}

// Close player function
const closePlayer = () => {
  if (isPlayerReady && youtubePlayer) {
    youtubePlayer.pauseVideo()
    if (queueStore.current) {
      queueStore.current.playback_state = 'Stopped'
    }
  }
  isVisible.value = false
  currentVideoId = null
}

// Auto-hide when on SoundCloud page
watch(() => isCurrentScreen('SoundCloud'), (onSoundCloudPage) => {
  if (onSoundCloudPage && isVisible.value) {
    closePlayer()
  }
})

// Watch for song changes
watch(playable, async (newPlayable, oldPlayable) => {
  // console.log('🎵 YouTubePlayer - playable changed:', {
  //   newPlayable: newPlayable?.id,
  //   oldPlayable: oldPlayable?.id,
  //   queueCurrent: queueStore.current?.id
  // })
  
  if (newPlayable && isSong(newPlayable)) {
    await searchAndPlayVideo(newPlayable)
  } else if (!newPlayable && oldPlayable) {
    console.warn('🎵 YouTubePlayer - playable became null/undefined, was:', oldPlayable.id)
  }
}, { immediate: true })

// Listen for direct track play events
eventBus.on('PLAY_YOUTUBE_TRACK', async (trackData: { title: string, artist: string }) => {
  // console.log('🎵 YouTubePlayer - PLAY_YOUTUBE_TRACK event received:', {
  //   trackData,
  //   isPlayerReady,
  //   hasYoutubePlayer: !!youtubePlayer
  // })
  
  if (!isPlayerReady) {
    // console.log('🎵 YouTubePlayer - player not ready, ignoring event')
    return
  }
  
  isLoading.value = true
  
  try {
    const query = `${trackData.title} ${trackData.artist}`
    // console.log('🎵 YouTubePlayer - searching YouTube for:', query)
    
    const result = await youTubeService.searchVideosByQuery(query)
    
    if (result.items && result.items.length > 0) {
      // Store search results for fallback
      currentSearchResults = result.items
      currentVideoIndex = 0
      
      const firstVideo = result.items[0]
      const videoId = firstVideo.id.videoId
      
      if (videoId && videoId !== currentVideoId) {
        currentVideoId = videoId
        isVisible.value = true
        youtubePlayer.loadVideoById(videoId)
        // console.log('🎵 YouTubePlayer - loading video:', firstVideo.snippet.title)
      } else {
        // console.warn('🎵 YouTubePlayer - no valid video ID found for track:', trackData.title)
        isLoading.value = false
      }
    } else {
      // console.warn('🎵 YouTubePlayer - no YouTube videos found for track:', trackData.title)
      isLoading.value = false
    }
  } catch (error) {
    // console.error('🎵 YouTubePlayer - failed to search YouTube videos for track:', error)
    isLoading.value = false
  }
})

// Control methods for integration with app controls
const play = () => {
  // console.log('🎵 YouTubePlayer - play called:', {
  //   isPlayerReady,
  //   hasYoutubePlayer: !!youtubePlayer,
  //   currentSong: queueStore.current?.id,
  //   currentState: queueStore.current?.playback_state
  // })
  
  try {
    if (isPlayerReady && youtubePlayer && typeof youtubePlayer.playVideo === 'function') {
      // console.log('🎵 YouTubePlayer - calling youtubePlayer.playVideo()')
      youtubePlayer.playVideo()
      // Update playback state immediately for responsive UI
      if (queueStore.current) {
        queueStore.current.playback_state = 'Playing'
        // console.log('🎵 YouTubePlayer - set playback_state to Playing')
      }
    } else {
      // console.log('🎵 YouTubePlayer - cannot play: player not ready or not available')
    }
  } catch (error) {
    console.warn('🎵 YouTubePlayer - play error (likely player destroyed):', error.message)
    isPlayerReady = false
    youtubePlayer = null
  }
}

const pause = () => {
  // console.log('🎵 YouTubePlayer - pause called:', {
  //   isPlayerReady,
  //   hasYoutubePlayer: !!youtubePlayer,
  //   currentSong: queueStore.current?.id,
  //   currentState: queueStore.current?.playback_state
  // })
  
  try {
    if (isPlayerReady && youtubePlayer && typeof youtubePlayer.pauseVideo === 'function') {
      // console.log('🎵 YouTubePlayer - calling youtubePlayer.pauseVideo()')
      youtubePlayer.pauseVideo()
      // Update playback state immediately for responsive UI
      if (queueStore.current) {
        queueStore.current.playback_state = 'Paused'
        // console.log('🎵 YouTubePlayer - set playback_state to Paused')
      }
    } else {
      // console.log('🎵 YouTubePlayer - cannot pause: player not ready or not available')
    }
  } catch (error) {
    console.warn('🎵 YouTubePlayer - pause error (likely player destroyed):', error.message)
    isPlayerReady = false
    youtubePlayer = null
  }
}

const setVolume = (volume: number) => {
  // console.log('🎵 YouTubePlayer - setVolume called:', {
  //   volume,
  //   isPlayerReady,
  //   hasYoutubePlayer: !!youtubePlayer
  // })
  
  try {
    if (isPlayerReady && youtubePlayer) {
      // Volume manager seems to use a 0-10 range, but YouTube expects 0-100
      // Let's normalize it properly
      const volumePercent = Math.min(100, Math.max(0, volume * 10))
      // console.log('🎵 YouTubePlayer - setting volume to:', volumePercent, 'from input:', volume)
      youtubePlayer.setVolume(volumePercent)
    } else {
      // console.log('🎵 YouTubePlayer - cannot set volume: player not ready or not available')
    }
  } catch (error) {
    // console.error('🎵 YouTubePlayer - error in setVolume():', error)
  }
}

const getCurrentTime = (): number => {
  if (isPlayerReady && youtubePlayer) {
    return youtubePlayer.getCurrentTime()
  }
  return 0
}

const getDuration = (): number => {
  if (isPlayerReady && youtubePlayer) {
    return youtubePlayer.getDuration()
  }
  return 0
}

const seekTo = (seconds: number) => {
  // console.log('🎵 YouTubePlayer - seekTo called:', {
  //   seconds,
  //   isPlayerReady,
  //   hasYoutubePlayer: !!youtubePlayer
  // })
  
  try {
    if (isPlayerReady && youtubePlayer && typeof youtubePlayer.seekTo === 'function') {
      // console.log('🎵 YouTubePlayer - seeking to:', seconds)
      youtubePlayer.seekTo(seconds)
    } else {
      // console.log('🎵 YouTubePlayer - cannot seek: player not ready or not available')
    }
  } catch (error) {
    console.warn('🎵 YouTubePlayer - seekTo error (likely player destroyed):', error.message)
    // Reset player state if it's in a bad state
    isPlayerReady = false
    youtubePlayer = null
  }
}

// Expose methods for parent component
defineExpose({
  play,
  pause,
  setVolume,
  getCurrentTime,
  getDuration,
  seekTo
})

onMounted(async () => {
  await nextTick()
  await createPlayer()
})

onUnmounted(() => {
  if (youtubePlayer) {
    youtubePlayer.destroy()
  }
})
</script>

<style lang="postcss" scoped>
.youtube-player-container {
  width: var(--sidebar-width);
  height: calc(var(--sidebar-width) * 9 / 16); /* 16:9 aspect ratio for sidebar width */
  flex-shrink: 0;
  
  :fullscreen & {
    @apply w-80 h-44;
  }
}

.youtube-player {
  :fullscreen & {
    @apply rounded-xl;
  }
}

.loading-overlay {
  :fullscreen & {
    @apply rounded-xl;
  }
}

.loading-spinner {
  :fullscreen & {
    @apply w-8 h-8 border-4;
  }
}

.video-loading {
  @apply opacity-75;
}
</style>