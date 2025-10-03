<template>
  <header
    :class="[layout, disabled ? 'disabled' : '']"
    class="screen-header min-h-0 md:min-h-full flex items-center flex-shrink-0 relative content-stretch leading-normal pl-10 pr-6 py-6
    border-b border-b-k-bg-secondary"
  >
    <aside v-if="$slots.thumbnail" class="thumbnail-wrapper hidden md:block overflow-hidden w-0 rounded-md">
      <slot name="thumbnail" />
    </aside>

    <main class="flex flex-1 gap-5 items-center">
      <button
        v-if="showBackButton"
        class="back-btn flex-shrink-0 w-10 h-10 rounded-full bg-k-bg-secondary hover:bg-k-bg-tertiary flex items-center justify-center transition-colors cursor-pointer"
        @click="goBack"
      >
        <Icon :icon="faArrowLeft" fixed-width />
      </button>

      <div class="flex-1 min-w-0">
        <!-- Header Image Display -->
        <div v-if="headerImage" class="flex justify-center items-center py-4">
          <img
            :src="headerImage"
            :alt="`${$slots.default?.[0]?.children || 'Screen'} Header`"
            :class="getImageSizeClass()"
            class="w-auto object-contain"
          />
        </div>

        <!-- Fallback for non-image headers -->
        <div v-else class="overflow-hidden">
          <h1 class="name truncate">
            <slot />
          </h1>
          <span v-if="$slots.meta" class="meta text-k-text-secondary hidden text-[0.9rem] leading-loose space-x-2">
            <slot name="meta" />
          </span>
        </div>
      </div>

      <div class="flex items-center gap-3 flex-shrink-0 ml-auto">
        <slot name="controls" />
        <ProfileDropdown />
      </div>
    </main>
  </header>
</template>

<script lang="ts" setup>
import { faArrowLeft } from '@fortawesome/free-solid-svg-icons'
import { computed } from 'vue'
import ProfileDropdown from '@/components/ui/ProfileDropdown.vue'
import { useRouter } from '@/composables/useRouter'

const props = withDefaults(defineProps<{
  layout?: ScreenHeaderLayout
  disabled?: boolean
  showMusicDiscovery?: boolean
  headerImage?: string
}>(), {
  layout: 'expanded',
  disabled: false,
  showMusicDiscovery: false,
  headerImage: undefined,
})

const { go, isCurrentScreen } = useRouter()

const showBackButton = computed(() => {
  return isCurrentScreen('Profile', 'Users', 'Settings')
})

const goBack = () => {
  go(-1)
}

const getImageSizeClass = () => {
  if (!props.headerImage) return 'max-h-12'

  // Smaller size for LastFM images
  if (props.headerImage.includes('LastFM')) {
    return 'max-h-8' // 2rem = 32px (smaller)
  }

  // Slightly larger size for SoundCloud images
  if (props.headerImage.includes('SoundCloud') || props.headerImage.includes('Soundcloud')) {
    return 'max-h-14' // 3.5rem = 56px (slightly larger than the previous 48px)
  }

  // Default size
  return 'max-h-12'
}
</script>

<style lang="postcss" scoped>
header.screen-header {
  --transition-duration: 300ms;

  @media (prefers-reduced-motion) {
    --transition-duration: 0;
  }

  &.disabled {
    @apply opacity-50 cursor-not-allowed;

    *,
    *::before,
    *::after {
      @apply pointer-events-none;
    }
  }

  &.expanded {
    .thumbnail-wrapper {
      @apply mr-6 w-[192px];

      > * {
        @apply scale-100;
      }
    }

    .meta {
      @apply block;
    }
  }

  .thumbnail-wrapper {
    transition: width var(--transition-duration);

    > * {
      @apply scale-0 origin-bottom-left;
      transition:
        transform var(--transition-duration),
        width var(--transition-duration);
    }

    &:empty {
      @apply hidden;
    }
  }

  h1.name {
    font-size: clamp(1.2rem, 2vw, 2.5rem);
  }

  .meta {
    a {
      @apply text-k-text-primary hover:text-k-highlight;
    }

    > :slotted(*) + :slotted(*) {
      @apply ml-1 inline-block before:content-['•'] before:mr-1 before:text-k-text-secondary;
    }
  }

  .back-btn {
    @apply text-k-text-primary;

    &:hover {
      @apply text-k-highlight;
    }
  }
}
</style>
