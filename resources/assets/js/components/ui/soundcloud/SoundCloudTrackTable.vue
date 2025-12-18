<template>
  <div class="bg-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b border-white/10">
            <th class="text-left p-3 font-medium"></th>
            <th class="text-left p-3 font-medium">Artist(s)</th>
            <th class="text-left p-3 font-medium">Title</th>
            <th class="text-center p-3 font-medium align-middle">Genre</th>
            <th class="text-center p-3 font-medium align-middle">BPM</th>
            <th class="text-center p-3 font-medium align-middle">Streams</th>
            <th class="text-center p-3 font-medium align-middle">Release Date</th>
            <th class="text-left p-3 font-medium"></th>
            <th class="text-left p-3 font-medium"></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(track, index) in tracks" :key="track.id">
            <tr
              class="hover:bg-white/5 transition h-16"
              :class="[
                isCurrentTrack(track) ? 'bg-white/5' : 'border-b border-white/5',
                expandedTrackId !== track.id && allowAnimations && index >= animationStartIndex ? 'track-row' : '',
              ]"
              :style="expandedTrackId !== track.id
                && allowAnimations
                && index >= animationStartIndex
                ? { animationDelay: `${Math.max(index - animationStartIndex, 0) * 50}ms` }
                : {}"
            >
              <!-- Index -->
              <td class="p-3 align-middle">
                <span class="text-white/60">
                  {{ (props.startIndex || 0) + index + 1 }}
                </span>
              </td>

              <!-- Artist -->
              <td class="p-3 align-middle">
                <a
                  v-if="getUserProfileUrl(track.user)"
                  :href="getUserProfileUrl(track.user)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-medium text-white/90 hover:text-k-accent transition cursor-pointer"
                >
                  {{ track.user?.username || 'Unknown' }}
                </a>
                <div v-else class="font-medium text-white/90">
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
                  class="text-white/80 hover:text-k-accent transition cursor-pointer"
                >
                  {{ track.title || 'Untitled' }}
                </a>
                <div v-else class="text-white/80">{{ track.title || 'Untitled' }}</div>
              </td>

              <!-- Genre -->
              <td class="p-3 align-middle text-center">
                <span
                  v-if="track.genre"
                  class="px-2 py-1 bg-white/10 text-white rounded text-sm whitespace-nowrap inline-block"
                  :title="track.genre"
                >
                  {{ track.genre.length > 12 ? `${track.genre.substring(0, 12)}...` : track.genre }}
                </span>
                <span v-else class="text-white/40">-</span>
              </td>

              <!-- BPM -->
              <td class="p-3 align-middle text-center">
                <span v-if="track.bpm" class="text-white/80">{{ track.bpm }}</span>
                <span v-else class="text-white/40">-</span>
              </td>

              <!-- Stream Count -->
              <td class="p-3 align-middle text-center">
                <span class="text-white/80">{{ formatCount(track.playback_count || 0) }}</span>
              </td>

              <!-- Release Date -->
              <td class="p-3 whitespace-nowrap align-middle text-center">
                <span class="text-white/80 whitespace-nowrap inline-block">{{ formatDate(track.created_at || '') }}</span>
              </td>

              <!-- Save/Ban Actions -->
              <td class="pl-3 align-middle">
                <div class="flex gap-2 justify-center">
                  <!-- Save Button (24h) -->
                  <button
                    @click="$emit('saveTrack', track)"
                    :disabled="processingTrack === track.id"
                    :class="isTrackSaved(track)
                      ? 'bg-green-600 hover:bg-green-700 text-white'
                      : 'bg-[#484948] hover:bg-gray-500 text-white'"
                    class="h-[34px] w-[34px] rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                    :title="isTrackSaved(track) ? 'Click to unsave track' : 'Save the Track (24h)'"
                  >
                    <Icon :icon="faHeart" class="text-sm" />
                  </button>

                  <!-- Blacklist Button -->
                  <button
                    @click="$emit('blacklistTrack', track)"
                    :disabled="processingTrack === track.id"
                    :class="isBanButtonActive(track)
                      ? 'bg-red-600 hover:bg-red-700 text-white'
                      : 'bg-[#484948] hover:bg-gray-500 text-white'"
                    class="h-[34px] w-[34px] rounded text-sm font-medium transition disabled:opacity-50 flex items-center justify-center"
                    :title="isTrackBlacklisted(track) ? 'Click to unblock track' : 'Ban the Track'"
                  >
                    <Icon :icon="faBan" class="text-sm" />
                  </button>
                </div>
              </td>

              <!-- Actions -->
              <td class="p-3 align-middle">
                <div class="flex gap-2 justify-center relative z-0">
                  <button
                    v-if="props.showRelatedButton"
                    class="px-3 py-2 bg-[#484948] hover:bg-gray-500 rounded text-sm font-medium transition disabled:opacity-50 flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center"
                    title="Find Related Tracks"
                    @click="$emit('relatedTracks', track)"
                  >
                    <Icon :icon="faSearch" class="w-4 h-4 mr-1" />
                    <span>Related</span>
                  </button>

                  <button
                    class="px-3 py-2 rounded text-sm font-medium transition flex items-center gap-1 min-w-[100px] min-h-[34px] justify-center"
                    :class="[
                      expandedTrackId === track.id || isTrackListened(track)
                        ? 'bg-[#868685] hover:bg-[#6d6d6d] text-white'
                        : 'bg-[#484948] hover:bg-gray-500 text-white'
                    ]"
                    @click="toggleInlinePlayer(track)"
                  >
                    <Icon
                      v-if="expandedTrackId === track.id"
                      :icon="faTimes"
                      class="w-4 h-4 mr-1"
                    />
                    <img
                      v-else
                      src="/public/img/soundcloud-icon.svg"
                      alt="SoundCloud"
                      class="w-[21px] h-[21px] object-contain mr-1"
                    >
                    <span>{{ expandedTrackId === track.id ? 'Close' : (isTrackListened(track) ? 'Listened' : 'Listen') }}</span>
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
import { faPlay, faSearch, faTimes, faHeart, faBan } from '@fortawesome/free-solid-svg-icons'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { computed, ref, watch } from 'vue'
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
  animationStartIndex?: number
  savedTracks?: Set<string>
  blacklistedTracks?: Set<string>
  processingTrack?: string | number | null
  listenedTracks?: Set<string>
}

interface Emits {
  (e: 'play', track: SoundCloudTrack): void
  (e: 'pause', track: SoundCloudTrack): void
  (e: 'seek', position: number): void
  (e: 'relatedTracks', track: SoundCloudTrack): void
  (e: 'saveTrack', track: SoundCloudTrack): void
  (e: 'blacklistTrack', track: SoundCloudTrack): void
  (e: 'markListened', track: SoundCloudTrack): void
}

const props = withDefaults(defineProps<Props>(), {
  showRelatedButton: true,
  allowAnimations: false,
  animationStartIndex: 0,
  savedTracks: () => new Set<string>(),
  blacklistedTracks: () => new Set<string>(),
  processingTrack: null,
  listenedTracks: () => new Set<string>(),
})
const emit = defineEmits<Emits>()

// Helper function to get track key (same format as parent components)
const getTrackKey = (track: SoundCloudTrack): string => {
  const artist = track.user?.username || 'Unknown'
  const title = track.title || 'Untitled'
  return `${artist}-${title}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Check if track is saved
const isTrackSaved = (track: SoundCloudTrack): boolean => {
  return props.savedTracks?.has(getTrackKey(track)) || false
}

// Check if track is blacklisted
const isTrackBlacklisted = (track: SoundCloudTrack): boolean => {
  return props.blacklistedTracks?.has(getTrackKey(track)) || false
}

// Check if ban button should be active (red)
const isBanButtonActive = (track: SoundCloudTrack): boolean => {
  return isTrackBlacklisted(track) && !isTrackSaved(track)
}

// Check if track has been listened to
const isTrackListened = (track: SoundCloudTrack): boolean => {
  return props.listenedTracks?.has(getTrackKey(track)) || false
}

const expandedTrackId = ref<string | null>(null)
const allowAnimations = computed(() => props.allowAnimations)
const animationStartIndex = computed(() => props.animationStartIndex ?? 0)

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
    // Mark track as listened when opening player
    emit('markListened', track)
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
  if (!dateString) {
    return 'Unknown'
  }

  try {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = Math.abs(now.getTime() - date.getTime())
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

    if (diffDays < 7) {
      if (diffDays === 0) {
        return 'Today'
      }
      return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
    }

    if (diffDays < 30) {
      const weeks = Math.floor(diffDays / 7)
      return `${weeks} week${weeks === 1 ? '' : 's'} ago`
    }

    if (diffDays < 365) {
      const months = Math.floor(diffDays / 30)
      return `${months} month${months === 1 ? '' : 's'} ago`
    }

    const years = Math.floor(diffDays / 365)
    return `${years} year${years === 1 ? '' : 's'} ago`
  } catch {
    return 'Unknown'
  }
}
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

/* Match label search table text tone */
tbody td {
  color: rgba(209, 213, 219, 0.9); /* similar to text-gray-300 */
}

/* Lighten text on row hover for better focus */
tbody tr:hover td {
  color: rgba(255, 255, 255, 0.86);
}

tbody tr:hover td a {
  color: rgba(255, 255, 255, 0.9);
}

tbody tr:hover td .text-white\/60 {
  color: rgba(255, 255, 255, 0.8);
}

tbody tr:hover td .text-white\/40 {
  color: rgba(255, 255, 255, 0.65);
}
</style>
