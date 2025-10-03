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
      <SidebarItem :href="url('banned-artists')" screen="BannedArtists">
        <template #icon>
          <Icon :icon="faUserSlash" fixed-width />
        </template>
        Banned Artists
      </SidebarItem>
    </ul>
  </SidebarSection>
</template>

<script lang="ts" setup>
import { faBan, faUserSlash, faHeart } from '@fortawesome/free-solid-svg-icons'
import { useRouter } from '@/composables/useRouter'

import SidebarSection from '@/components/layout/main-wrapper/sidebar/SidebarSection.vue'
import SidebarSectionHeader from '@/components/layout/main-wrapper/sidebar/SidebarSectionHeader.vue'
import SidebarItem from '@/components/layout/main-wrapper/sidebar/SidebarItem.vue'

const { url } = useRouter()
</script>
