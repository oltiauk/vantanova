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
            <th class="text-left p-3 font-medium">Track Count</th>
            <th class="text-left p-3 font-medium">Release Date</th>
            <th class="text-left p-3 font-medium">Duration</th>
            <th class="text-left p-3 font-medium">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(track, index) in tracks"
            :key="track.id"
            class="border-b border-white/5 hover:bg-white/5 transition"
          >
            <!-- Index -->
            <td class="p-3 text-white/60">{{ index + 1 }}</td>
            
            <!-- Artist -->
            <td class="p-3">
              <div class="flex items-center gap-3">
                <div class="font-medium">{{ track.user?.username || 'Unknown' }}</div>
                <button
                  @click="$emit('view-artist', track.user)"
                  class="px-3 py-1.5 bg-k-accent/20 hover:bg-k-accent/40 text-k-accent rounded-md text-xs font-medium transition-all hover:scale-105 active:scale-95 shadow-sm hover:shadow-md flex items-center gap-1"
                  title="View artist details & real follower count"
                >
                  <Icon :icon="faUser" class="text-xs" />
                  Details
                </button>
              </div>
            </td>
            
            <!-- Title -->
            <td class="p-3">
              <div class="font-medium text-white">{{ track.title || 'Untitled' }}</div>
            </td>
            
            <!-- Genre -->
            <td class="p-3">
              <span
                v-if="track.genre"
                class="px-2 py-1 bg-k-accent/20 text-k-accent rounded text-sm"
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
            
            <!-- Track Count -->
            <td class="p-3">
              <span class="text-white/80">{{ formatCount(track.user?.track_count || 0) }}</span>
            </td>
            
            <!-- Release Date -->
            <td class="p-3">
              <span class="text-white/80">{{ formatDate(track.created_at || '') }}</span>
            </td>
            
            <!-- Duration -->
            <td class="p-3">
              <span class="text-white/80">{{ formatDuration(track.duration || 0) }}</span>
            </td>
            
            <!-- Actions -->
            <td class="p-3">
              <button
                @click="$emit('play', track)"
                class="px-3 py-1.5 bg-k-accent hover:bg-k-accent/80 rounded text-sm font-medium transition"
              >
                <Icon :icon="faPlay" class="mr-1" />
                Play
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { faPlay, faUser } from '@fortawesome/free-solid-svg-icons'

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
  user: {
    username: string
    followers_count: number
  }
}

interface Props {
  tracks: SoundCloudTrack[]
}

interface Emits {
  (e: 'play', track: SoundCloudTrack): void
  (e: 'view-artist', user: any): void
}

defineProps<Props>()
defineEmits<Emits>()

const formatCount = (count: number | undefined | null): string => {
  if (!count || count === 0) {
    return '0'
  }
  if (count >= 1000000) {
    return (count / 1000000).toFixed(1) + 'M'
  } else if (count >= 1000) {
    return (count / 1000).toFixed(1) + 'K'
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
</script>