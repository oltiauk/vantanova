<template>
  <SidebarSection>
    <template #header>
      <SidebarSectionHeader>Manage</SidebarSectionHeader>
    </template>

    <ul class="menu">
      <SidebarItem :href="url('saved-tracks')" screen="SavedTracks">
        <template #icon>
          <Icon :icon="faHeart" fixed-width class="text-[1.2em]" />
        </template>
        Liked Tracks
        <span v-if="savedTrackCount !== null" class="ml-1 text-sm text-white/70">({{ savedTrackCount }})</span>
      </SidebarItem>
      <SidebarItem :href="url('banned-tracks')" screen="BannedTracks">
        <template #icon>
          <Icon :icon="faBan" fixed-width class="text-[1.2em]" />
        </template>
        Banned Tracks
      </SidebarItem>
      <!-- <SidebarItem :href="url('banned-artists')" screen="BannedArtists">
        <template #icon>
          <Icon :icon="faUserSlash" fixed-width />
        </template>
        Banned Artists
      </SidebarItem> -->

      <!-- Admin-only items -->
      <template v-if="isAdmin">
        <SidebarItem :href="url('users.index')" screen="Users">
          <template #icon>
            <Icon :icon="faUsers" fixed-width class="text-[1.2em]" />
          </template>
          Users
        </SidebarItem>
        <SidebarItem :href="url('upload')" screen="Upload">
          <template #icon>
            <Icon :icon="faUpload" fixed-width class="text-[1.2em]" />
          </template>
          Upload
        </SidebarItem>
        <SidebarItem :href="url('settings')" screen="Settings">
          <template #icon>
            <Icon :icon="faTools" fixed-width class="text-[1.2em]" />
          </template>
          Settings
        </SidebarItem>
      </template>
    </ul>
  </SidebarSection>
</template>

<script lang="ts" setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { faBan, faHeart, faTools, faUpload, faUsers, faUserSlash } from '@fortawesome/free-solid-svg-icons'
import { useRouter } from '@/composables/useRouter'
import { useAuthorization } from '@/composables/useAuthorization'
import { http } from '@/services/http'

import SidebarSection from '@/components/layout/main-wrapper/sidebar/SidebarSection.vue'
import SidebarSectionHeader from '@/components/layout/main-wrapper/sidebar/SidebarSectionHeader.vue'
import SidebarItem from '@/components/layout/main-wrapper/sidebar/SidebarItem.vue'

const { url } = useRouter()
const { isAdmin } = useAuthorization()

const savedTrackCount = ref<number | null>(null)
const clientUnsavedTracks = ref<Set<string>>(new Set())

// Helper function to get track key (same as in SavedTracksScreen.vue)
const getTrackKey = (track: { artist_name: string, track_name: string }): string => {
  return `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Load client-side unsaved tracks from localStorage
const loadClientUnsavedTracks = () => {
  try {
    const stored = localStorage.getItem('koel-client-unsaved-tracks')
    if (stored) {
      const unsavedList = JSON.parse(stored)
      clientUnsavedTracks.value = new Set(unsavedList)
    }
  } catch (error) {
    console.warn('Failed to load client unsaved tracks from localStorage:', error)
  }
}

const loadSavedTrackCount = async () => {
  try {
    // Load client-side unsaved tracks first
    loadClientUnsavedTracks()

    const response = await http.get('music-preferences/saved-tracks')
    if (response.success && Array.isArray(response.data)) {
      // Filter out client-side unsaved tracks
      const filteredTracks = response.data.filter((track: { artist_name: string, track_name: string }) => {
        const trackKey = getTrackKey(track)
        return !clientUnsavedTracks.value.has(trackKey)
      })
      savedTrackCount.value = filteredTracks.length
    }
  } catch (error) {
    savedTrackCount.value = null
  }
}

const handleTrackSaved = () => {
  // Reload the count to ensure it's accurate
  loadSavedTrackCount()
}

const handleTrackUnsaved = (event?: CustomEvent) => {
  // Update count immediately for instant feedback
  if (savedTrackCount.value !== null && savedTrackCount.value > 0) {
    savedTrackCount.value = Math.max(0, savedTrackCount.value - 1)
  }
  
  // Also reload from server to ensure accuracy (accounts for client-side unsaved tracks)
  // This happens in the background
  loadSavedTrackCount()
}

const handleStorageChange = (e: StorageEvent) => {
  if (e.key === 'koel-client-unsaved-tracks' || e.key === 'track-saved-timestamp' || e.key === 'track-unsaved-timestamp') {
    loadSavedTrackCount()
  }
}

onMounted(() => {
  loadSavedTrackCount()
  window.addEventListener('track-saved', handleTrackSaved as EventListener)
  window.addEventListener('track-unsaved', handleTrackUnsaved as EventListener)
  window.addEventListener('storage', handleStorageChange)
})

onUnmounted(() => {
  window.removeEventListener('track-saved', handleTrackSaved)
  window.removeEventListener('track-unsaved', handleTrackUnsaved)
  window.removeEventListener('storage', handleStorageChange)
})
</script>
