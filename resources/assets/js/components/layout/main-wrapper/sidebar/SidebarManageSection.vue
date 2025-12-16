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

const loadSavedTrackCount = async () => {
  try {
    const response = await http.get('music-preferences/saved-tracks')
    if (response.success && Array.isArray(response.data)) {
      savedTrackCount.value = response.data.length
    }
  } catch (error) {
    savedTrackCount.value = null
  }
}

const handleTrackSaved = () => {
  if (savedTrackCount.value === null) {
    savedTrackCount.value = 1
  } else {
    savedTrackCount.value += 1
  }
}

const handleTrackUnsaved = () => {
  if (savedTrackCount.value === null) {
    savedTrackCount.value = 0
  } else {
    savedTrackCount.value = Math.max(0, savedTrackCount.value - 1)
  }
}

onMounted(() => {
  loadSavedTrackCount()
  window.addEventListener('track-saved', handleTrackSaved)
  window.addEventListener('track-unsaved', handleTrackUnsaved)
})

onUnmounted(() => {
  window.removeEventListener('track-saved', handleTrackSaved)
  window.removeEventListener('track-unsaved', handleTrackUnsaved)
})
</script>
