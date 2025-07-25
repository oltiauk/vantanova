<template>
  <FooterButton
    :title="playing ? 'Pause' : 'Play or resume'"
    class="!w-[3rem] rounded-full border-2 border-solid aspect-square !transition-transform hover:scale-125 !text-2xl
    has-[.icon-play]:indent-[0.23rem]"
    @click.prevent="toggle"
  >
    <Icon v-if="playing" :icon="faPause" />
    <Icon v-else :icon="faPlay" class="icon-play" />
  </FooterButton>
</template>

<script lang="ts" setup>
import { faPause, faPlay } from '@fortawesome/free-solid-svg-icons'
import { computed, ref } from 'vue'
import { playbackService } from '@/services/playbackService'
import { commonStore } from '@/stores/commonStore'
import { favoriteStore } from '@/stores/favoriteStore'
import { queueStore } from '@/stores/queueStore'
import { recentlyPlayedStore } from '@/stores/recentlyPlayedStore'
import { songStore } from '@/stores/songStore'
import { useRouter } from '@/composables/useRouter'
import { requireInjection } from '@/utils/helpers'
import { CurrentPlayableKey } from '@/symbols'

import FooterButton from '@/components/layout/app-footer/FooterButton.vue'

const { getCurrentScreen, getRouteParam, go, url } = useRouter()
const song = requireInjection(CurrentPlayableKey, ref())

const libraryEmpty = computed(() => commonStore.state.song_count === 0)
const playing = computed(() => {
  const isPlaying = song.value?.playback_state === 'Playing'
  // console.log('🎵 FooterPlayButton - playing computed:', {
  //   isPlaying,
  //   song: song.value,
  //   playback_state: song.value?.playback_state
  // })
  return isPlaying
})

const initiatePlayback = async () => {
  // console.log('🎵 FooterPlayButton - initiatePlayback called')
  if (libraryEmpty.value) {
    // console.log('🎵 FooterPlayButton - library is empty, returning')
    return
  }

  let playables: Playable[]

  switch (getCurrentScreen()) {
    case 'Album':
      playables = await songStore.fetchForAlbum(getRouteParam('id')!)
      break
    case 'Artist':
      playables = await songStore.fetchForArtist(getRouteParam('id')!)
      break
    case 'Playlist':
      playables = await songStore.fetchForPlaylist(getRouteParam('id')!)
      break
    case 'Favorites':
      playables = await favoriteStore.fetch()
      break
    case 'RecentlyPlayed':
      playables = await recentlyPlayedStore.fetch()
      break
    default:
      playables = await queueStore.fetchRandom()
      break
  }

  await playbackService.queueAndPlay(playables)
  go(url('queue'))
}

const toggle = async () => {
  // console.log('🎵 FooterPlayButton - toggle clicked:', {
  //   hasSong: !!song.value,
  //   songId: song.value?.id,
  //   playbackState: song.value?.playback_state
  // })
  
  try {
    if (song.value) {
      // console.log('🎵 FooterPlayButton - calling playbackService.toggle()')
      await playbackService.toggle()
    } else {
      // console.log('🎵 FooterPlayButton - no song, calling initiatePlayback()')
      await initiatePlayback()
    }
  } catch (error) {
    // console.error('🎵 FooterPlayButton - error in toggle():', error)
  }
}
</script>
