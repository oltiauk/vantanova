<template>
  <SidebarSection>
    <template #header>
      <SidebarSectionHeader>Manage</SidebarSectionHeader>
    </template>

    <ul class="menu">
      <SidebarItem :href="url('saved-tracks')" screen="SavedTracks">
        <template #icon>
          <Icon :icon="faHeart" fixed-width />
        </template>
        Saved Tracks
      </SidebarItem>
      <SidebarItem :href="url('banned-tracks')" screen="BannedTracks">
        <template #icon>
          <Icon :icon="faBan" fixed-width />
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
            <Icon :icon="faUsers" fixed-width />
          </template>
          Users
        </SidebarItem>
        <SidebarItem :href="url('upload')" screen="Upload">
          <template #icon>
            <Icon :icon="faUpload" fixed-width />
          </template>
          Upload
        </SidebarItem>
        <SidebarItem :href="url('settings')" screen="Settings">
          <template #icon>
            <Icon :icon="faTools" fixed-width />
          </template>
          Settings
        </SidebarItem>
      </template>
    </ul>
  </SidebarSection>
</template>

<script lang="ts" setup>
import { faBan, faUserSlash, faHeart, faUsers, faUpload, faTools } from '@fortawesome/free-solid-svg-icons'
import { useRouter } from '@/composables/useRouter'
import { useAuthorization } from '@/composables/useAuthorization'

import SidebarSection from '@/components/layout/main-wrapper/sidebar/SidebarSection.vue'
import SidebarSectionHeader from '@/components/layout/main-wrapper/sidebar/SidebarSectionHeader.vue'
import SidebarItem from '@/components/layout/main-wrapper/sidebar/SidebarItem.vue'

const { url } = useRouter()
const { isAdmin } = useAuthorization()
</script>
