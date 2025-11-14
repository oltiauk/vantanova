<template>
  <div ref="dropdown" class="profile-dropdown-wrapper">
    <button
      ref="triggerButton"
      class="profile-btn w-10 h-10 rounded-full bg-white/10 flex items-center justify-center transition-colors cursor-pointer"
      @click="toggleMenu"
    >
      <Icon :icon="faUser" fixed-width />
    </button>

    <Teleport to="body">
      <Transition name="dropdown">
        <div
          v-if="isOpen"
          ref="dropdownMenu"
          :style="{ top: dropdownTop, right: dropdownRight }"
          class="fixed w-48 rounded-lg shadow-lg py-2 z-[9999]"
          style="background-color: #303030;"
        >
          <button
            class="w-full px-4 py-2.5 text-left hover:bg-white/10 transition flex items-center gap-3 text-k-text-primary"
            @click="goToProfile"
          >
            <Icon :icon="faUser" fixed-width />
            Profile
          </button>
          <button
            v-if="isAdmin"
            class="w-full px-4 py-2.5 text-left hover:bg-white/10 transition flex items-center gap-3 text-k-text-primary"
            @click="goToUsers"
          >
            <Icon :icon="faUsers" fixed-width />
            Users
          </button>
          <button
            v-if="isAdmin"
            class="w-full px-4 py-2.5 text-left hover:bg-white/10 transition flex items-center gap-3 text-k-text-primary"
            @click="goToSettings"
          >
            <Icon :icon="faTools" fixed-width />
            Settings
          </button>
          <div class="border-t border-white/10 my-1" />
          <button
            class="w-full px-4 py-2.5 text-left hover:bg-white/10 transition flex items-center gap-3 text-k-text-primary"
            @click="logout"
          >
            <Icon :icon="faArrowRightFromBracket" fixed-width />
            Logout
          </button>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script lang="ts" setup>
import { faArrowRightFromBracket, faTools, faUser, faUsers } from '@fortawesome/free-solid-svg-icons'
import { nextTick, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { useAuthorization } from '@/composables/useAuthorization'
import { useRouter } from '@/composables/useRouter'
import { eventBus } from '@/utils/eventBus'

const { go, url } = useRouter()
const { isAdmin } = useAuthorization()

const dropdown = ref<HTMLElement>()
const triggerButton = ref<HTMLButtonElement>()
const dropdownMenu = ref<HTMLElement>()
const isOpen = ref(false)
const dropdownTop = ref('0px')
const dropdownRight = ref('0px')

const updateDropdownPosition = () => {
  if (!triggerButton.value) {
    return
  }

  const rect = triggerButton.value.getBoundingClientRect()
  dropdownTop.value = `${rect.bottom + 8}px`
  dropdownRight.value = `${window.innerWidth - rect.right}px`
}

const toggleMenu = async () => {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    await nextTick()
    updateDropdownPosition()
  }
}

const closeMenu = () => {
  isOpen.value = false
}

const goToProfile = () => {
  closeMenu()
  go(url('profile'))
}

const goToUsers = () => {
  closeMenu()
  go(url('users.index'))
}

const goToSettings = () => {
  closeMenu()
  go(url('settings'))
}

const logout = () => {
  closeMenu()
  eventBus.emit('LOG_OUT')
}

onClickOutside(dropdownMenu, closeMenu, { ignore: [triggerButton] })
</script>

<style lang="postcss" scoped>
.profile-btn {
  @apply text-k-text-primary;

  &:hover {
    @apply bg-k-highlight;
  }
}

.dropdown-enter-active,
.dropdown-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
