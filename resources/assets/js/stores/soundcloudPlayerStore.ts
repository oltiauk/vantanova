import { reactive } from 'vue'
import type { SoundCloudTrack } from '@/services/soundcloudService'

interface SoundCloudPlayerState {
  showPlayer: boolean
  currentTrack: SoundCloudTrack | null
  embedUrl: string
  isPlaying: boolean
  canSkipPrevious: boolean
  canSkipNext: boolean
}

const initialState: SoundCloudPlayerState = {
  showPlayer: false,
  currentTrack: null,
  embedUrl: '',
  isPlaying: false,
  canSkipPrevious: false,
  canSkipNext: false
}

export const soundcloudPlayerStore = {
  state: reactive<SoundCloudPlayerState>(initialState),

  show(track: SoundCloudTrack, embedUrl: string = '') {
    this.state.currentTrack = track
    this.state.embedUrl = embedUrl
    this.state.showPlayer = true
    this.state.isPlaying = false
  },

  hide() {
    this.state.showPlayer = false
    this.state.currentTrack = null
    this.state.embedUrl = ''
    this.state.isPlaying = false
    this.state.canSkipPrevious = false
    this.state.canSkipNext = false
  },

  setEmbedUrl(url: string) {
    this.state.embedUrl = url
  },

  setPlaying(playing: boolean) {
    this.state.isPlaying = playing
  },

  setNavigationState(canSkipPrevious: boolean, canSkipNext: boolean) {
    this.state.canSkipPrevious = canSkipPrevious
    this.state.canSkipNext = canSkipNext
  },

  get isVisible() {
    return this.state.showPlayer
  },

  get track() {
    return this.state.currentTrack
  },

  get url() {
    return this.state.embedUrl
  },

  get canSkipPrevious() {
    return this.state.canSkipPrevious
  },

  get canSkipNext() {
    return this.state.canSkipNext
  }
}