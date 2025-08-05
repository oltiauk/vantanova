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
          <tr
            v-for="(track, index) in tracks"
            :key="track.id"
            class="hover:bg-white/5 transition"
            :class="isCurrentTrack(track)
              ? 'bg-white/5 border-2 border-white/20'
              : 'border-b border-white/5'"
          >
            <!-- Index -->
            <td class="p-3 text-white/60">{{ index + 1 }}</td>

            <!-- Artist -->
            <td class="p-3">
              <a
                v-if="track.user?.username"
                :href="`https://soundcloud.com/${track.user.username}`"
                target="_blank"
                rel="noopener noreferrer"
                class="font-medium text-white hover:text-k-accent transition cursor-pointer underline"
              >
                {{ track.user.username }}
              </a>
              <div v-else class="font-medium">Unknown</div>
            </td>

            <!-- Title -->
            <td class="p-3">
              <a
                v-if="track.permalink_url"
                :href="track.permalink_url"
                target="_blank"
                rel="noopener noreferrer"
                class="font-medium text-white hover:text-k-accent transition cursor-pointer underline"
              >
                {{ track.title || 'Untitled' }}
              </a>
              <div v-else class="font-medium text-white">{{ track.title || 'Untitled' }}</div>
            </td>

            <!-- Genre -->
            <td class="p-3">
              <span
                v-if="track.genre"
                class="px-2 py-1 bg-white/10 text-white rounded text-sm"
              >
                {{ track.genre }}
              </span>
              <span v-else class="text-white/40">-</span>
            </td>

            <!-- BPM -->
            <td class="p-3">
              <span v-if="track.bpm" class="text-white/80">{{ track.bpm }}</span>
              <span v-else class="text-white/40">-</span>
            </td>

            <!-- Playback Count -->
            <td class="p-3">
              <span class="text-white/80">{{ formatCount(track.playback_count || 0) }}</span>
            </td>

            <!-- Likes (Favoritings) -->
            <td class="p-3">
              <span class="text-white/80">{{ formatCount(track.favoritings_count || 0) }}</span>
            </td>

            <!-- Likes Ratio -->
            <td class="p-3">
              <span class="text-white/80">{{ formatLikesRatio(track.favoritings_count || 0, track.playback_count || 0) }}</span>
            </td>

            <!-- Release Date -->
            <td class="p-3 whitespace-nowrap">
              <span class="text-white/80 whitespace-nowrap inline-block">{{ formatDate(track.created_at || '') }}</span>
            </td>

            <!-- Duration -->
            <td class="p-3">
              <span class="text-white/80">{{ formatDuration(track.duration || 0) }}</span>
            </td>

            <!-- Actions -->
            <td class="p-3">
              <div class="flex gap-2">
                <button
                  class="px-3 py-1.5 bg-k-accent hover:bg-k-accent/80 rounded text-sm font-medium transition"
                  @click="$emit('play', track)"
                >
                  <Icon :icon="isCurrentTrack(track) ? faPause : faPlay" class="mr-1" />
                  {{ isCurrentTrack(track) ? 'Stop' : 'Play' }}
                </button>
                
                <button
                  v-if="props.showRelatedButton"
                  class="px-3 py-1.5 bg-green-600 hover:bg-green-700 rounded text-sm font-medium transition"
                  @click="$emit('relatedTracks', track)"
                  title="Find Related Tracks"
                >
                  <Icon :icon="faMusic" class="mr-1" />
                  Related
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { faPause, faPlay, faMusic } from '@fortawesome/free-solid-svg-icons'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { computed } from 'vue'

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
  }
}

interface Props {
  tracks: SoundCloudTrack[]
  showRelatedButton?: boolean
}

interface Emits {
  (e: 'play', track: SoundCloudTrack): void
  (e: 'relatedTracks', track: SoundCloudTrack): void
}

const props = withDefaults(defineProps<Props>(), {
  showRelatedButton: true
})
defineEmits<Emits>()

// Helper function to check if a track is currently playing
const isCurrentTrack = (track: SoundCloudTrack) => {
  const currentTrack = soundcloudPlayerStore.track
  return currentTrack && currentTrack.id === track.id
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
