<template>
  <SidebarSection>
    <template #header>
      <SidebarSectionHeader>Manage</SidebarSectionHeader>
    </template>

    <ul class="menu">
      <SidebarItem v-if="isAdmin" :href="url('settings')" screen="Settings">
        <template #icon>
          <Icon :icon="faTools" fixed-width />
        </template>
        Settings
      </SidebarItem>
      <SidebarItem v-if="isAdmin" :href="url('users.index')" screen="Users">
        <template #icon>
          <Icon :icon="faUsers" fixed-width />
        </template>
        Users
      </SidebarItem>
      <SidebarItem :href="url('profile')" screen="Profile">
        <template #icon>
          <Icon :icon="faUser" fixed-width />
        </template>
        Profile
      </SidebarItem>
      <SidebarItem :href="url('music-preferences')" screen="MusicPreferences">
        <template #icon>
          <Icon :icon="faSliders" fixed-width />
        </template>
        Preferences
      </SidebarItem>
    </ul>
  </SidebarSection>
</template>

<script lang="ts" setup>
import { faTools, faUsers, faUser, faSliders } from '@fortawesome/free-solid-svg-icons'
import { useAuthorization } from '@/composables/useAuthorization'
import { useRouter } from '@/composables/useRouter'

import SidebarSection from '@/components/layout/main-wrapper/sidebar/SidebarSection.vue'
import SidebarSectionHeader from '@/components/layout/main-wrapper/sidebar/SidebarSectionHeader.vue'
import SidebarItem from '@/components/layout/main-wrapper/sidebar/SidebarItem.vue'

const { url } = useRouter()
const { isAdmin } = useAuthorization()
</script>
