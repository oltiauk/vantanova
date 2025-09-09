<template>
  <SidebarItem :href="url('soundcloud')" screen="SoundCloud">
    <template #icon>
      <img src="/public/img/soundcloud-icon.svg" alt="SoundCloud" class="w-5 h-5 object-contain" />
    </template>
    <slot />
  </SidebarItem>
</template>

<script lang="ts" setup>
import { useRouter } from '@/composables/useRouter'

import SidebarItem from './SidebarItem.vue'

const { url } = useRouter()
</script>