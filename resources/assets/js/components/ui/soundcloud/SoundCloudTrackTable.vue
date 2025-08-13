<template>
  <div class="bg-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b border-white/10">
            <th class="text-left p-3 font-medium">#</th>
            <th class="text-left p-3 font-medium">Artist</th>
            <th class="text-left p-3 font-medium">Title</th>
            <th class="text-left p-3 font-medium">Genre</th>
            <th class="text-left p-3 font-medium">BPM</th>
            <th class="text-left p-3 font-medium">Plays</th>
            <th class="text-left p-3 font-medium">Likes</th>
            <th class="text-left p-3 font-medium">Likes Ratio</th>
            <th class="text-left p-3 font-medium">Release Date</th>
            <th class="text-left p-3 font-medium">Duration</th>
            <th class="text-left p-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(track, index) in tracks" :key="track.id">
            <tr
              class="hover:bg-white/5 transition h-16"
              :class="isCurrentTrack(track)
                ? 'bg-white/5'
                : 'border-b border-white/5'"
            >
            <!-- Index -->
            <td class="p-3 align-middle">
              <div class="flex items-center">
                <Icon 
                  v-if="isCurrentTrack(track)" 
                  :icon="faPlay" 
                  class="text-k-accent mr-2 text-sm animate-pulse" 
                />
                <span :class="isCurrentTrack(track) ? 'text-k-accent font-medium' : 'text-white/60'">
                  {{ index + 1 }}
                </span>
              </div>
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
                class="px-2 py-1 bg-white/10 text-white rounded text-sm"
              >
                {{ track.genre }}
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
              <div class="flex gap-2">
                <button
                  v-if="props.showRelatedButton"
                  class="px-3 py-1.5 bg-[#429488] rounded text-sm font-medium transition"
                  @click="$emit('relatedTracks', track)"
                  title="Find Related Tracks"
                >
                  <Icon :icon="faMusic" class="mr-1" />
                  Related
                </button>
                
                <button
                  class="px-3 py-1.5 bg-gray-600 hover:bg-gray-500 rounded text-sm font-medium transition"
                  @click="toggleInlinePlayer(track)"
                >
                  <Icon :icon="expandedTrackId === track.id ? faTimes : faPlay" class="mr-1" />
                  {{ expandedTrackId === track.id ? 'Close' : 'Preview' }}
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
import { faPause, faPlay, faMusic, faTimes } from '@fortawesome/free-solid-svg-icons'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { computed, ref } from 'vue'
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
}

interface Emits {
  (e: 'play', track: SoundCloudTrack): void
  (e: 'pause', track: SoundCloudTrack): void
  (e: 'seek', position: number): void
  (e: 'relatedTracks', track: SoundCloudTrack): void
}

const props = withDefaults(defineProps<Props>(), {
  showRelatedButton: true
})
const emit = defineEmits<Emits>()

const expandedTrackId = ref<string | null>(null)

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
  htmlEl.style.height = height + 'px'
  htmlEl.style.opacity = '1'
  htmlEl.style.overflow = 'hidden'
  htmlEl.style.transform = 'scaleY(1)'
  htmlEl.style.transformOrigin = 'top'
  
  // Force reflow
  htmlEl.offsetHeight
  
  requestAnimationFrame(() => {
    htmlEl.style.height = '0'
    htmlEl.style.opacity = '0'
    htmlEl.style.transform = 'scaleY(0)'
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
  if (!user) return null
  
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
</script>

<style scoped>
/* Enhanced transition styles for smooth player dropdown */
.player-expand-enter-active {
  transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1), 
              opacity 0.25s ease-in-out 0.05s,
              transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.player-expand-leave-active {
  transition: height 0.3s cubic-bezier(0.4, 0, 0.6, 1), 
              opacity 0.2s ease-in-out,
              transform 0.3s cubic-bezier(0.4, 0, 0.6, 1);
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
  transform: scaleY(0);
  transform-origin: top;
}

/* Smooth table row transitions */
tr {
  transition: background-color 0.15s ease;
}

/* Add subtle shadow to playing rows */
tr.bg-white\/5 {
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05);
}
</style>
