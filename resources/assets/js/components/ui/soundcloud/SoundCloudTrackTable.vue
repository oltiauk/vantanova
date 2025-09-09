<template>
  <div class="bg-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b border-white/10">
            <th class="text-left p-3 font-medium">#</th>
            <th class="text-left p-3 font-medium w-12">Ban Artist</th>
            <th class="text-left p-3 font-medium">Name(s)</th>
            <th class="text-left p-3 font-medium">Title</th>
            <th class="text-left p-3 font-medium">Genre</th>
            <th class="text-left p-3 font-medium">BPM</th>
            <th class="text-left p-3 font-medium">Plays</th>
            <th class="text-left p-3 font-medium">Likes</th>
            <th class="text-left p-3 font-medium">Likes Ratio</th>
            <th class="text-left p-3 font-medium">Release Date</th>
            <th class="text-left p-3 font-medium">Duration</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(track, index) in tracks" :key="track.id">
            <tr
              class="hover:bg-white/5 transition h-16"
              :class="[
                isCurrentTrack(track) ? 'bg-white/5' : 'border-b border-white/5',
                expandedTrackId !== track.id && allowAnimations ? 'track-row' : '',
                isArtistBanned(track) ? 'opacity-60 bg-red-500/5' : '',
              ]"
              :style="expandedTrackId !== track.id && allowAnimations ? { animationDelay: `${index * 50}ms` } : {}"
            >
              <!-- Index -->
              <td class="p-3 align-middle">
                <span class="text-white/60">
                  {{ (props.startIndex || 0) + index + 1 }}
                </span>
              </td>

              <!-- Ban Button -->
              <td class="p-3 align-middle">
                <button
                  class="p-2 rounded-full transition-colors" :class="[
                    isArtistBanned(track)
                      ? 'text-red-400 hover:text-red-300 hover:bg-red-500/20'
                      : 'text-[#bcbcbc] hover:text-white hover:bg-white/10',
                  ]"
                  :title="isArtistBanned(track) ? 'Click to unban this artist' : 'Ban this artist'"
                  @click="banArtist(track)"
                >
                  <Icon :icon="faBan" class="w-4 h-4" />
                </button>
              </td>

              <!-- Artist -->
              <td class="p-3 align-middle">
                <a
                  v-if="getUserProfileUrl(track.user)"
                  :href="getUserProfileUrl(track.user)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-white hover:text-k-accent transition cursor-pointer"
                >
                  {{ track.user?.username || 'Unknown' }}
                </a>
                <div v-else class="font-medium text-white">
                  {{ track.user?.username || 'Unknown' }}
                </div>
              </td>

              <!-- Title -->
              <td class="p-3 align-middle">
                <a
                  v-if="track.permalink_url"
                  :href="track.permalink_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-white hover:text-k-accent transition cursor-pointer"
                >
                  {{ track.title || 'Untitled' }}
                </a>
                <div v-else class="font-medium text-white">{{ track.title || 'Untitled' }}</div>
              </td>

              <!-- Genre -->
              <td class="p-3 align-middle">
                <span
                  v-if="track.genre"
                  class="px-2 py-1 bg-white/10 text-white rounded text-sm whitespace-nowrap"
                  :title="track.genre"
                >
                  {{ track.genre.length > 12 ? `${track.genre.substring(0, 12)}...` : track.genre }}
                </span>
                <span v-else class="text-white/40">-</span>
              </td>

              <!-- BPM -->
              <td class="p-3 align-middle">
                <span v-if="track.bpm" class="text-white/80">{{ track.bpm }}</span>
                <span v-else class="text-white/40">-</span>
              </td>

              <!-- Playback Count -->
              <td class="p-3 align-middle">
                <span class="text-white/80">{{ formatCount(track.playback_count || 0) }}</span>
              </td>

              <!-- Likes (Favoritings) -->
              <td class="p-3 align-middle">
                <span class="text-white/80">{{ formatCount(track.favoritings_count || 0) }}</span>
              </td>

              <!-- Likes Ratio -->
              <td class="p-3 align-middle">
                <span class="text-white/80">{{ formatLikesRatio(track.favoritings_count || 0, track.playback_count || 0) }}</span>
              </td>

              <!-- Release Date -->
              <td class="p-3 whitespace-nowrap align-middle">
                <span class="text-white/80 whitespace-nowrap inline-block">{{ formatDate(track.created_at || '') }}</span>
              </td>

              <!-- Duration -->
              <td class="p-3 align-middle">
                <span class="text-white/80">{{ formatDuration(track.duration || 0) }}</span>
              </td>

              <!-- Actions -->
              <td class="p-3 align-middle">
                <div class="flex gap-2 relative z-0">
                  <button
                    v-if="props.showRelatedButton"
                    class="px-3 py-1.5 bg-[#9d0cc6] hover:bg-[#c036e8] rounded text-sm font-medium transition relative z-0 flex items-center gap-1"
                    title="Find Related Tracks"
                    @click="$emit('relatedTracks', track)"
                  >
                    <Icon :icon="faSearch" class="w-3 h-3" />
                    <span>Related</span>
                  </button>

                  <button
                    class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 rounded text-sm font-medium transition flex items-center gap-1 w-20 justify-center"
                    @click="toggleInlinePlayer(track)"
                  >
                    <Icon :icon="expandedTrackId === track.id ? faTimes : faPlay" class="w-3 h-3" />
                    <span>{{ expandedTrackId === track.id ? 'Close' : 'Preview' }}</span>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Inline Player Row -->
            <transition
              name="player-expand"
              @enter="onEnter"
              @after-enter="onAfterEnter"
              @leave="onLeave"
              @after-leave="onAfterLeave"
            >
              <InlineSoundCloudPlayer
                v-if="expandedTrackId === track.id"
                :track="track"
              />
            </transition>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { faBan, faMusic, faPause, faPlay, faSearch, faTimes } from '@fortawesome/free-solid-svg-icons'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import InlineSoundCloudPlayer from '@/components/ui/soundcloud/InlineSoundCloudPlayer.vue'

interface SoundCloudTrack {
  id: number
  title: string
  duration: number
  created_at: string
  genre: string
  tag_list: string
  bpm?: number
  playback_count: number
  favoritings_count: number
  permalink_url?: string
  user: {
    username: string
    followers_count: number
    permalink_url?: string
    id?: number
  }
}

interface Props {
  tracks: SoundCloudTrack[]
  showRelatedButton?: boolean
  startIndex?: number
  allowAnimations?: boolean
}

interface Emits {
  (e: 'play', track: SoundCloudTrack): void
  (e: 'pause', track: SoundCloudTrack): void
  (e: 'seek', position: number): void
  (e: 'relatedTracks', track: SoundCloudTrack): void
  (e: 'banArtist', track: SoundCloudTrack): void
}

const props = withDefaults(defineProps<Props>(), {
  showRelatedButton: true,
  allowAnimations: false,
}); const emit = defineEmits<Emits>() // Banned artists tracking (shared with SoundCloud screen)
const bannedArtists = ref(new Set<string>()) // Store artist names

// Helper function to check if an artist is banned
const isArtistBanned = (track: SoundCloudTrack): boolean => {
  return bannedArtists.value.has(track.user?.username || '')
}

const expandedTrackId = ref<string | null>(null)
const initialLoadComplete = ref(false)
const allowAnimations = computed(() => props.allowAnimations)

// Method to close any expanded inline player
const closeInlinePlayer = () => {
  if (expandedTrackId.value) {
    expandedTrackId.value = null
    soundcloudPlayerStore.hide()
  }
}

// Expose the close method to parent components
defineExpose({
  closeInlinePlayer,
})

// Helper function to check if a track is currently playing
const isCurrentTrack = (track: SoundCloudTrack) => {
  const currentTrack = soundcloudPlayerStore.track
  return currentTrack && currentTrack.id === track.id
}

const toggleInlinePlayer = (track: SoundCloudTrack) => {
  if (expandedTrackId.value === track.id) {
    expandedTrackId.value = null
    // Clear the soundcloud player store when closing the dropdown
    soundcloudPlayerStore.hide()
  } else {
    expandedTrackId.value = track.id
    // Auto-play when opening inline player
    emit('play', track)
  }
}

const banArtist = (track: SoundCloudTrack) => {
  emit('banArtist', track)

  // Immediately toggle the banned state in this component for instant visual feedback
  const artistName = track.user?.username || ''
  if (isArtistBanned(track)) {
    bannedArtists.value.delete(artistName)
  } else {
    bannedArtists.value.add(artistName)
  }
}

// Load banned artists from localStorage
const loadBannedArtists = () => {
  try {
    const stored = localStorage.getItem('koel-banned-artists')
    if (stored) {
      const bannedList = JSON.parse(stored)
      bannedArtists.value = new Set(bannedList)
    }
  } catch (error) {
    console.warn('SoundCloudTrackTable: Failed to load banned artists from localStorage:', error)
  }
}

// Watch for changes to localStorage to stay in sync with parent component
const handleStorageChange = (event: StorageEvent) => {
  if (event.key === 'koel-banned-artists' && event.newValue) {
    try {
      const bannedList = JSON.parse(event.newValue)
      bannedArtists.value = new Set(bannedList)
    } catch (error) {
      console.warn('SoundCloudTrackTable: Failed to parse banned artists from storage event:', error)
    }
  }
}

// Close inline player when page changes (detected by startIndex change)
watch(() => props.startIndex, () => {
  if (expandedTrackId.value) {
    expandedTrackId.value = null
    soundcloudPlayerStore.hide()
  }
})

// Animations are now controlled by parent component via allowAnimations prop

// Pagination animations are now controlled by parent component via allowAnimations prop

// Enhanced Animation methods for smooth dropdown
const onEnter = (el: Element) => {
  const htmlEl = el as HTMLElement
  htmlEl.style.height = '0'
  htmlEl.style.opacity = '0'
  htmlEl.style.overflow = 'hidden'
  htmlEl.style.transform = 'scaleY(0)'
  htmlEl.style.transformOrigin = 'top'
}

const onAfterEnter = (el: Element) => {
  const htmlEl = el as HTMLElement
  htmlEl.style.height = ''
  htmlEl.style.opacity = ''
  htmlEl.style.overflow = ''
  htmlEl.style.transform = ''
  htmlEl.style.transformOrigin = ''
}

const onLeave = (el: Element) => {
  const htmlEl = el as HTMLElement
  const height = htmlEl.offsetHeight
  htmlEl.style.height = `${height}px`
  htmlEl.style.opacity = '1'
  htmlEl.style.overflow = 'hidden'
  htmlEl.style.transform = 'scaleY(1)'
  htmlEl.style.transformOrigin = 'top'
  htmlEl.style.transition = 'none'

  // Force reflow
  htmlEl.offsetHeight

  // Re-enable transitions
  htmlEl.style.transition = ''

  requestAnimationFrame(() => {
    htmlEl.style.height = '0'
    htmlEl.style.opacity = '0'
    htmlEl.style.transform = 'scaleY(0.98)'
  })
}

const onAfterLeave = (el: Element) => {
  const htmlEl = el as HTMLElement
  htmlEl.style.height = ''
  htmlEl.style.opacity = ''
  htmlEl.style.overflow = ''
  htmlEl.style.transform = ''
  htmlEl.style.transformOrigin = ''
}

// Helper function to get user profile URL
const getUserProfileUrl = (user: any): string | null => {
  if (!user) {
    return null
  }

  // If we have a permalink_url in the user object, use it
  if (user.permalink_url) {
    return user.permalink_url
  }

  // If we have a user ID, construct the URL using the user ID
  if (user.id) {
    return `https://soundcloud.com/user-${user.id}`
  }

  // If we only have username, try to construct URL but only if it doesn't have spaces
  if (user.username && !user.username.includes(' ')) {
    // Convert username to lowercase and replace special characters
    const cleanUsername = user.username.toLowerCase().replace(/[^a-z0-9-_]/g, '-')
    return `https://soundcloud.com/${cleanUsername}`
  }

  // If username has spaces or other issues, don't create a link
  return null
}

const formatCount = (count: number | undefined | null): string => {
  if (!count || count === 0) {
    return '0'
  }
  if (count >= 1000000) {
    return `${(count / 1000000).toFixed(1)}M`
  } else if (count >= 1000) {
    return `${(count / 1000).toFixed(1)}K`
  }
  return count.toString()
}

const formatDate = (dateString: string): string => {
  try {
    const date = new Date(dateString)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  } catch {
    return 'Unknown'
  }
}

const formatDuration = (milliseconds: number): string => {
  const totalSeconds = Math.floor(milliseconds / 1000)
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

const formatLikesRatio = (likes: number, plays: number): string => {
  if (!plays || plays === 0) {
    return '0.00%'
  }
  const ratio = (likes / plays) * 100
  return `${ratio.toFixed(2)}%`
}

// Initialize banned artists state when component mounts
onMounted(() => {
  loadBannedArtists()
  window.addEventListener('storage', handleStorageChange)
})

// Clean up storage listener when component unmounts
onUnmounted(() => {
  window.removeEventListener('storage', handleStorageChange)
})
</script>

<style scoped>
/* Enhanced transition styles for smooth player dropdown */
.player-expand-enter-active {
  transition:
    height 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94),
    opacity 0.2s ease-out 0.05s,
    transform 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  will-change: height, opacity, transform;
}

.player-expand-leave-active {
  transition:
    height 0.18s cubic-bezier(0.25, 0.46, 0.45, 0.94),
    opacity 0.12s ease-out,
    transform 0.18s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  will-change: height, opacity, transform;
}

.player-expand-enter-from {
  height: 0;
  opacity: 0;
  transform: scaleY(0);
  transform-origin: top;
}

.player-expand-leave-to {
  height: 0;
  opacity: 0;
  transform: scaleY(0.98);
  transform-origin: top;
}

/* Smooth table row transitions */
tr {
  transition: background-color 0.15s ease;
}

/* Prevent layout shifts during player animations */
.player-row {
  position: relative;
  contain: layout;
}

/* Track rows progressive display animation */
.track-row {
  animation: fadeInUp 0.6s ease-out both;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Add subtle shadow to playing rows */
tr.bg-white\/5 {
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05);
}
</style>
