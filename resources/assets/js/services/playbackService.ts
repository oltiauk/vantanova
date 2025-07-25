import isMobile from 'ismobilejs'
import plyr from 'plyr'
import { watch } from 'vue'
import { shuffle, throttle } from 'lodash'
import { commonStore } from '@/stores/commonStore'
import { preferenceStore as preferences } from '@/stores/preferenceStore'
import { queueStore } from '@/stores/queueStore'
import { recentlyPlayedStore } from '@/stores/recentlyPlayedStore'
import { songStore } from '@/stores/songStore'
import { userStore } from '@/stores/userStore'
import { logger } from '@/utils/logger'
import { isEpisode, isSong } from '@/utils/typeGuards'
import { arrayify, getPlayableProp } from '@/utils/helpers'
import { eventBus } from '@/utils/eventBus'
import { isAudioContextSupported } from '@/utils/supports'
import { audioService } from '@/services/audioService'
import { http } from '@/services/http'
import { socketService } from '@/services/socketService'
import { volumeManager } from '@/services/volumeManager'
import { useEpisodeProgressTracking } from '@/composables/useEpisodeProgressTracking'

/**
 * The number of seconds before the current playable ends to start preload the next one.
 */
const PRELOAD_BUFFER = 30

class PlaybackService {
  public player!: Plyr
  private youtubePlayer: any = null
  private repeatModes: RepeatMode[] = ['NO_REPEAT', 'REPEAT_ALL', 'REPEAT_ONE']
  private initialized = false

  public get isTranscoding () {
    return isMobile.any && preferences.transcode_on_mobile
  }

  /**
   * The next item in the queue.
   * If we're in REPEAT_ALL mode and there's no next item, just get the first item.
   */
  public get next () {
    if (queueStore.next) {
      return queueStore.next
    }

    if (preferences.repeat_mode === 'REPEAT_ALL') {
      return queueStore.first
    }
  }

  /**
   * The previous item in the queue.
   * If we're in REPEAT_ALL mode and there's no prev item, get the last item.
   */
  public get previous () {
    if (queueStore.previous) {
      return queueStore.previous
    }

    if (preferences.repeat_mode === 'REPEAT_ALL') {
      return queueStore.last
    }
  }

  public init (plyrWrapper: HTMLElement) {
    if (this.initialized) {
      return
    }

    this.player = plyr.setup(plyrWrapper, { controls: [] })[0]

    this.listenToMediaEvents(this.player.media)
    this.setMediaSessionActionHandlers()

    watch(volumeManager.volume, volume => this.player.setVolume(volume), { immediate: true })

    this.initialized = true
  }

  public initWithYouTube (youtubePlayerComponent: any) {
    if (this.initialized) {
      return
    }

    this.youtubePlayer = youtubePlayerComponent

    this.listenToYouTubeEvents()
    this.setMediaSessionActionHandlers()

    watch(volumeManager.volume, volume => {
      // console.log('🎵 PlaybackService - volume changed:', {
      //   volume,
      //   hasYouTubePlayer: !!this.youtubePlayer
      // })
      if (this.youtubePlayer) {
        this.youtubePlayer.setVolume(volume)
      }
    }, { immediate: true })

    this.initialized = true
  }

  public registerPlay (playable: Playable) {
    recentlyPlayedStore.add(playable)
    songStore.registerPlay(playable)
    playable.play_count_registered = true
  }

  public preload (playable: Playable) {
    const audioElement = document.createElement('audio')
    audioElement.setAttribute('src', songStore.getSourceUrl(playable))
    audioElement.setAttribute('preload', 'auto')
    audioElement.load()
    playable.preloaded = true
  }

  /**
   * Play a song. Because
   *
   * So many adventures couldn't happen today,
   * So many songs we forgot to play
   * So many dreams swinging out of the blue
   * We'll let them come true
   */
  public async play (playable: Playable, position = 0) {
    if (isEpisode(playable)) {
      useEpisodeProgressTracking().trackEpisode(playable)
    }

    queueStore.queueIfNotQueued(playable, 'after-current')

    // If for any reason (most likely a bug), the requested song has been deleted, attempt the next item in the queue.
    if (isSong(playable) && playable.deleted) {
      logger.warn('Attempted to play a deleted song', playable)

      if (this.next && this.next.id !== playable.id) {
        await this.playNext()
      }

      return
    }

    if (queueStore.current) {
      queueStore.current.playback_state = 'Stopped'
    }

    playable.playback_state = 'Playing'

    await this.setNowPlayingMeta(playable)

    if (this.youtubePlayer) {
      // YouTube player handles the video automatically via watch() in the component
      this.recordStartTime(playable)
      this.broadcast(playable)
      this.showNotification(playable)
    } else {
      // Manually set the `src` attribute of the audio to prevent plyr from resetting
      // the audio media object and cause our equalizer to malfunction.
      this.player.media.src = songStore.getSourceUrl(playable)

      if (position === 0) {
        // We'll just "restart" playing the item, which will handle notification, scrobbling etc.
        // Fixes #898
        await this.restart()
      } else {
        this.player.seek(position)
        await this.resume()
      }
    }

    this.setMediaSessionActionHandlers()
  }

  public showNotification (playable: Playable) {
    if (!isSong(playable) && !isEpisode(playable)) {
      throw new Error('Invalid playable type.')
    }

    if (preferences.show_now_playing_notification) {
      try {
        const notification = new window.Notification(`♫ ${playable.title}`, {
          icon: getPlayableProp(playable, 'album_cover', 'episode_image'),
          body: isSong(playable)
            ? `${playable.album_name} – ${playable.artist_name}`
            : playable.title,
        })

        notification.onclick = () => window.focus()

        window.setTimeout(() => notification.close(), 5000)
      } catch (error: unknown) {
        // Notification fails.
        // @link https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerRegistration/showNotification
        logger.error(error)
      }
    }

    if (!navigator.mediaSession) {
      return
    }

    navigator.mediaSession.metadata = new MediaMetadata({
      title: playable.title,
      artist: getPlayableProp(playable, 'artist_name', 'podcast_author'),
      album: getPlayableProp(playable, 'album_name', 'podcast_title'),
      artwork: [48, 64, 96, 128, 192, 256, 384, 512].map(d => ({
        src: getPlayableProp(playable, 'album_cover', 'episode_image'),
        sizes: `${d}x${d}`,
        type: 'image/png',
      })),
    })
  }

  public async restart () {
    const playable = queueStore.current!

    this.recordStartTime(playable)
    this.broadcast(playable)

    try {
      http.silently.put('queue/playback-status', {
        song: playable.id,
        position: 0,
      })
    } catch (error: unknown) {
      logger.error(error)
    }

    if (this.youtubePlayer) {
      this.youtubePlayer.seekTo(0)
      this.youtubePlayer.play()
    } else {
      this.player.restart()

      try {
        await this.player.media.play()
      } catch (error: unknown) {
        // convert this into a warning, as an error will cause Cypress to fail the tests entirely
        logger.warn(error)
      }
    }

    navigator.mediaSession && (navigator.mediaSession.playbackState = 'playing')
    this.showNotification(playable)
  }

  public rotateRepeatMode () {
    let index = this.repeatModes.indexOf(preferences.repeat_mode) + 1

    if (index >= this.repeatModes.length) {
      index = 0
    }

    preferences.repeat_mode = this.repeatModes[index]
  }

  /**
   * Play the prev item the queue, if one is found.
   * If there's no prev item and the current mode is NO_REPEAT, we stop completely.
   */
  public async playPrev () {
    // If the item's duration is greater than 5 seconds, and we've passed 5 seconds into it,
    // restart playing instead.
    let currentTime = 0
    if (this.youtubePlayer) {
      currentTime = this.youtubePlayer.getCurrentTime()
    } else {
      currentTime = this.player.media.currentTime
    }

    if (currentTime > 5 && queueStore.current!.length > 5) {
      if (this.youtubePlayer) {
        this.youtubePlayer.seekTo(0)
        this.youtubePlayer.play()
      } else {
        this.player.restart()
      }

      return
    }

    if (!this.previous && preferences.repeat_mode === 'NO_REPEAT') {
      await this.stop()
    } else {
      this.previous && await this.play(this.previous)
    }
  }

  /**
   * Play the next item in the queue if one is found.
   * If there's no next item and the current mode is NO_REPEAT, we stop completely.
   */
  public async playNext () {
    if (!this.next && preferences.repeat_mode === 'NO_REPEAT') {
      await this.stop() //  Nothing lasts forever, even cold November rain.
    } else {
      this.next && await this.play(this.next)
    }
  }

  public async stop () {
    document.title = 'Koel'
    
    if (this.youtubePlayer) {
      this.youtubePlayer.pause()
      this.youtubePlayer.seekTo(0)
    } else {
      this.player.pause()
      this.player.seek(0)
    }

    queueStore.current && (queueStore.current.playback_state = 'Stopped')
    navigator.mediaSession && (navigator.mediaSession.playbackState = 'none')

    socketService.broadcast('SOCKET_PLAYBACK_STOPPED')
  }

  public pause () {
    // console.log('🎵 PlaybackService - pause called:', {
    //   hasYouTubePlayer: !!this.youtubePlayer,
    //   currentSong: queueStore.current?.id
    // })
    
    try {
      if (this.youtubePlayer) {
        // console.log('🎵 PlaybackService - calling youtubePlayer.pause()')
        this.youtubePlayer.pause()
      } else {
        // console.log('🎵 PlaybackService - calling player.pause()')
        this.player.pause()
      }

      queueStore.current!.playback_state = 'Paused'
      // console.log('🎵 PlaybackService - set playback_state to Paused')
      navigator.mediaSession && (navigator.mediaSession.playbackState = 'paused')

      socketService.broadcast('SOCKET_SONG', queueStore.current)
    } catch (error) {
      // console.error('🎵 PlaybackService - error in pause():', error)
    }
  }

  public async resume () {
    const playable = queueStore.current!
    // console.log('🎵 PlaybackService - resume called:', {
    //   hasYouTubePlayer: !!this.youtubePlayer,
    //   playableId: playable.id,
    //   currentPlaybackState: playable.playback_state
    // })

    try {
      if (this.youtubePlayer) {
        // console.log('🎵 PlaybackService - calling youtubePlayer.play()')
        this.youtubePlayer.play()
      } else {
        // console.log('🎵 PlaybackService - using regular player')
        if (!this.player.media.src) {
          // console.log('🎵 PlaybackService - no media src, setting up player')
          // on first load when the queue is loaded from saved state, the player's src is empty
          // we need to properly set it as well as any kind of playback metadata
          this.player.media.src = songStore.getSourceUrl(playable)
          this.player.seek(commonStore.state.queue_state.playback_position)

          await this.setNowPlayingMeta(queueStore.current!)
          this.recordStartTime(playable)
        }

        try {
          await this.player.media.play()
        } catch (error: unknown) {
          logger.error(error)
        }
      }

      queueStore.current!.playback_state = 'Playing'
      // console.log('🎵 PlaybackService - set playback_state to Playing')
      navigator.mediaSession && (navigator.mediaSession.playbackState = 'playing')

      this.broadcast(playable)
    } catch (error) {
      // console.error('🎵 PlaybackService - error in resume():', error)
    }
  }

  public async toggle () {
    // console.log('🎵 PlaybackService - toggle called:', {
    //   hasCurrent: !!queueStore.current,
    //   currentPlaybackState: queueStore.current?.playback_state,
    //   hasYouTubePlayer: !!this.youtubePlayer
    // })
    
    if (!queueStore.current) {
      // console.log('🎵 PlaybackService - no current song, calling playFirstInQueue')
      await this.playFirstInQueue()
      return
    }

    if (queueStore.current.playback_state !== 'Playing') {
      // console.log('🎵 PlaybackService - not playing, calling resume')
      await this.resume()
      return
    }

    // console.log('🎵 PlaybackService - currently playing, calling pause')
    this.pause()
  }

  public seekBy (seconds: number) {
    // console.log('🎵 PlaybackService - seekBy called:', {
    //   seconds,
    //   hasYouTubePlayer: !!this.youtubePlayer
    // })
    
    if (this.youtubePlayer) {
      const currentTime = this.youtubePlayer.getCurrentTime()
      const newTime = currentTime + seconds
      // console.log('🎵 PlaybackService - YouTube seek:', {
      //   currentTime,
      //   newTime,
      //   seconds
      // })
      this.youtubePlayer.seekTo(newTime)
    } else if (this.player.media.duration) {
      // console.log('🎵 PlaybackService - regular player seek:', {
      //   currentTime: this.player.media.currentTime,
      //   seconds
      // })
      this.player.media.currentTime += seconds
    } else {
      // console.log('🎵 PlaybackService - no player available for seek')
    }
  }

  /**
   * Queue up playables (replace them into the queue) and start playing right away.
   */
  public async queueAndPlay (playables: MaybeArray<Playable>, shuffled = false) {
    playables = arrayify(playables)

    if (shuffled) {
      playables = shuffle(playables)
    }

    // console.log('🎵 PlaybackService - queueAndPlay called:', {
    //   playablesCount: playables.length,
    //   hasCurrentSong: !!queueStore.current,
    //   isYouTubePlayer: !!this.youtubePlayer
    // })

    // For YouTube player, we can switch directly without stopping
    if (this.youtubePlayer && queueStore.current) {
      // console.log('🎵 PlaybackService - direct queue switch for YouTube player')
      queueStore.replaceQueueWith(playables)
      await this.play(queueStore.first)
    } else {
      // console.log('🎵 PlaybackService - traditional stop/queue/play')
      await this.stop()
      queueStore.replaceQueueWith(playables)
      await this.play(queueStore.first)
    }
  }

  public async playFirstInQueue () {
    queueStore.all.length && await this.play(queueStore.first)
  }

  private async setNowPlayingMeta (playable: Playable) {
    document.title = `${playable.title} ♫ Koel`
    
    if (!this.youtubePlayer) {
      this.player.media.setAttribute(
        'title',
        isSong(playable) ? `${playable.artist_name} - ${playable.title}` : playable.title,
      )

      if (isAudioContextSupported) {
        await audioService.context.resume()
      }
    }
  }

  // Record the UNIX timestamp the song starts playing, for scrobbling purpose
  private recordStartTime (song: Playable) {
    if (!isSong(song)) {
      return
    }

    song.play_start_time = Math.floor(Date.now() / 1000)
    song.play_count_registered = false
  }

  private broadcast (playable: Playable) {
    socketService.broadcast('SOCKET_SONG', playable)
  }

  private setMediaSessionActionHandlers () {
    if (!navigator.mediaSession) {
      return
    }

    navigator.mediaSession.setActionHandler('play', () => this.resume())
    navigator.mediaSession.setActionHandler('pause', () => this.pause())
    navigator.mediaSession.setActionHandler('stop', () => this.stop())
    navigator.mediaSession.setActionHandler('previoustrack', () => this.playPrev())
    navigator.mediaSession.setActionHandler('nexttrack', () => this.playNext())

    if (!isMobile.apple) {
      navigator.mediaSession.setActionHandler('seekbackward', details => {
        if (this.youtubePlayer) {
          const currentTime = this.youtubePlayer.getCurrentTime()
          this.youtubePlayer.seekTo(currentTime - (details.seekOffset || 10))
        } else {
          this.player.media.currentTime -= (details.seekOffset || 10)
        }
      })

      navigator.mediaSession.setActionHandler('seekforward', details => {
        if (this.youtubePlayer) {
          const currentTime = this.youtubePlayer.getCurrentTime()
          this.youtubePlayer.seekTo(currentTime + (details.seekOffset || 10))
        } else {
          this.player.media.currentTime += (details.seekOffset || 10)
        }
      })
    }

    navigator.mediaSession.setActionHandler('seekto', details => {
      if (this.youtubePlayer) {
        this.youtubePlayer.seekTo(details.seekTime || 0)
      } else {
        if (details.fastSeek && 'fastSeek' in this.player.media) {
          this.player.media.fastSeek(details.seekTime || 0)
          return
        }

        this.player.media.currentTime = details.seekTime || 0
      }
    })
  }

  private listenToMediaEvents (media: HTMLMediaElement) {
    media.addEventListener('error', () => this.playNext(), true)

    media.addEventListener('ended', () => {
      if (
        isSong(queueStore.current!)
        && commonStore.state.uses_last_fm
        && userStore.current.preferences!.lastfm_session_key
      ) {
        songStore.scrobble(queueStore.current!)
      }

      preferences.repeat_mode === 'REPEAT_ONE' ? this.restart() : this.playNext()
    })

    let timeUpdateHandler = () => {
      const currentPlayable = queueStore.current

      if (!currentPlayable) {
        return
      }

      if (!currentPlayable.play_count_registered && !this.isTranscoding) {
        // if we've passed 25% of the playable, it's safe to say it has been "played".
        // Refer to https://github.com/koel/koel/issues/1087
        if (!media.duration || media.currentTime * 4 >= media.duration) {
          this.registerPlay(currentPlayable)
        }
      }

      if (Math.ceil(media.currentTime) % 5 === 0) {
        // every 5 seconds, we save the current playback position to the server
        try {
          http.silently.put('queue/playback-status', {
            song: currentPlayable.id,
            position: Math.ceil(media.currentTime),
          })
        } catch (error: unknown) {
          logger.error(error)
        }

        // if the current item is an episode, we emit an event to update the progress on the client side as well
        if (isEpisode(currentPlayable)) {
          eventBus.emit('EPISODE_PROGRESS_UPDATED', currentPlayable, Math.ceil(media.currentTime))
        }
      }

      const nextPlayable = queueStore.next

      if (!nextPlayable || nextPlayable.preloaded || this.isTranscoding) {
        return
      }

      if (media.duration && media.currentTime + PRELOAD_BUFFER > media.duration) {
        this.preload(nextPlayable)
      }
    }

    if (process.env.NODE_ENV !== 'test') {
      timeUpdateHandler = throttle(timeUpdateHandler, 1000)
    }

    media.addEventListener('timeupdate', timeUpdateHandler)
  }

  private listenToYouTubeEvents () {
    eventBus.on('YOUTUBE_PLAYER_PLAYING', () => {
      const currentPlayable = queueStore.current
      if (currentPlayable) {
        currentPlayable.playback_state = 'Playing'
        navigator.mediaSession && (navigator.mediaSession.playbackState = 'playing')
        this.broadcast(currentPlayable)
      }
    })

    eventBus.on('YOUTUBE_PLAYER_PAUSED', () => {
      const currentPlayable = queueStore.current
      if (currentPlayable) {
        currentPlayable.playback_state = 'Paused'
        navigator.mediaSession && (navigator.mediaSession.playbackState = 'paused')
        this.broadcast(currentPlayable)
      }
    })

    eventBus.on('YOUTUBE_PLAYER_ENDED', () => {
      const currentPlayable = queueStore.current
      if (currentPlayable && isSong(currentPlayable)) {
        if (
          commonStore.state.uses_last_fm
          && userStore.current.preferences!.lastfm_session_key
        ) {
          songStore.scrobble(currentPlayable)
        }
      }
      
      preferences.repeat_mode === 'REPEAT_ONE' ? this.restart() : this.playNext()
    })

    // Start a timer to periodically check playback progress
    this.startYouTubeProgressTracking()
  }

  private startYouTubeProgressTracking () {
    setInterval(() => {
      if (!this.youtubePlayer) return

      const currentPlayable = queueStore.current
      if (!currentPlayable) return

      const currentTime = this.youtubePlayer.getCurrentTime()
      const duration = this.youtubePlayer.getDuration()

      if (!currentPlayable.play_count_registered && !this.isTranscoding) {
        // if we've passed 25% of the playable, it's safe to say it has been "played".
        if (!duration || currentTime * 4 >= duration) {
          this.registerPlay(currentPlayable)
        }
      }

      if (Math.ceil(currentTime) % 5 === 0) {
        // every 5 seconds, we save the current playback position to the server
        try {
          http.silently.put('queue/playback-status', {
            song: currentPlayable.id,
            position: Math.ceil(currentTime),
          })
        } catch (error: unknown) {
          logger.error(error)
        }

        // if the current item is an episode, we emit an event to update the progress on the client side as well
        if (isEpisode(currentPlayable)) {
          eventBus.emit('EPISODE_PROGRESS_UPDATED', currentPlayable, Math.ceil(currentTime))
        }
      }

      const nextPlayable = queueStore.next

      if (!nextPlayable || nextPlayable.preloaded || this.isTranscoding) {
        return
      }

      if (duration && currentTime + PRELOAD_BUFFER > duration) {
        this.preload(nextPlayable)
      }
    }, 1000)
  }
}

export const playbackService = new PlaybackService()
