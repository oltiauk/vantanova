<template>
  <SidebarItem :href="url('soundcloud')" screen="SoundCloud">
    <template #icon>
      <Icon :icon="faSoundcloud" fixed-width />
    </template>
    <slot />
  </SidebarItem>
</template>

<script lang="ts" setup>
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { useRouter } from '@/composables/useRouter'

import SidebarItem from './SidebarItem.vue'

const { url } = useRouter()
</script>