<template>
  <slot />
</template>

<script lang="ts" setup>
import { onMounted } from 'vue'
import { useErrorHandler } from '@/composables/useErrorHandler'
import { useOverlay } from '@/composables/useOverlay'
import { commonStore } from '@/stores/commonStore'
import { preferenceStore as preferences } from '@/stores/preferenceStore'
import { socketListener } from '@/services/socketListener'
import { socketService } from '@/services/socketService'
import { uploadService } from '@/services/uploadService'

const emits = defineEmits<{
  (e: 'success'): void
  (e: 'error', err: unknown): void
}>()

const { showOverlay, hideOverlay } = useOverlay()

onMounted(async () => {
  showOverlay({ message: 'Just a little patience…' })

  try {
    await commonStore.init()

    window.addEventListener('beforeunload', (e: BeforeUnloadEvent) => {
      if (uploadService.shouldWarnUponWindowUnload() || preferences.confirm_before_closing) {
        e.preventDefault()
        e.returnValue = ''
      }
    })

    await socketService.init() && socketListener.listen()

    emits('success')
  } catch (error: unknown) {
    useErrorHandler().handleHttpError(error)
    emits('error', error)
  } finally {
    hideOverlay()
  }
})
</script>

<style lang="postcss" scoped>

</style>
