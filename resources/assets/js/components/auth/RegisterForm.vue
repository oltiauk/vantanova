<template>
  <div class="flex items-center justify-center min-h-screen my-0 mx-auto flex-col gap-5">
    <form
      :class="{ error: failed }"
      class="w-full sm:w-[288px] sm:border duration-500 p-7 rounded-xl border-transparent sm:bg-white/10 space-y-3"
      data-testid="register-form"
      @submit.prevent="register"
    >
      <div class="text-center mb-8">
        <img alt="Vantanova's logo" class="inline-block" :src="logoUrl" width="300">
      </div>

      <FormRow>
        <TextInput v-model="name" autofocus placeholder="Full Name" required type="text" />
      </FormRow>

      <FormRow>
        <TextInput v-model="email" placeholder="Email Address" required type="email" />
      </FormRow>

      <FormRow>
        <PasswordField v-model="password" placeholder="Password" required />
        <template #help>Min. 10 characters. Should be a mix of characters, numbers, and symbols.</template>
      </FormRow>

      <FormRow>
        <PasswordField v-model="passwordConfirmation" placeholder="Confirm Password" required />
      </FormRow>

      <FormRow>
        <Btn :disabled="loading" data-testid="submit" type="submit">Sign Up</Btn>
      </FormRow>

      <FormRow>
        <a class="text-center text-[.95rem] text-k-text-secondary block" role="button" @click.prevent="goToLogin">
          Already have an account? Log in
        </a>
      </FormRow>
    </form>
  </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue'
import { authService } from '@/services/authService'
import { logger } from '@/utils/logger'
import { useMessageToaster } from '@/composables/useMessageToaster'
import { useRouter } from '@/composables/useRouter'

import Btn from '@/components/ui/form/Btn.vue'
import PasswordField from '@/components/ui/form/PasswordField.vue'
import TextInput from '@/components/ui/form/TextInput.vue'
import FormRow from '@/components/ui/form/FormRow.vue'

const emit = defineEmits<{ (e: 'registered'): void }>()
const router = useRouter()
const { toastError, toastSuccess } = useMessageToaster()

const logoUrl = '/VantaNova-Logo.svg'

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const failed = ref(false)
const loading = ref(false)

const goToLogin = () => {
  router.go('/')
}

const register = async () => {
  if (password.value !== passwordConfirmation.value) {
    toastError('Passwords do not match.')
    failed.value = true
    window.setTimeout(() => (failed.value = false), 2000)
    return
  }

  if (password.value.length < 10) {
    toastError('Password must be at least 10 characters long.')
    failed.value = true
    window.setTimeout(() => (failed.value = false), 2000)
    return
  }

  try {
    loading.value = true
    await authService.register(name.value, email.value, password.value, passwordConfirmation.value)
    failed.value = false
    toastSuccess('Account created successfully! Please log in.')
    
    // Reset the form
    name.value = ''
    email.value = ''
    password.value = ''
    passwordConfirmation.value = ''
    
    // Go to login page
    goToLogin()
  } catch (error: unknown) {
    failed.value = true
    logger.error(error)
    window.setTimeout(() => (failed.value = false), 2000)
  } finally {
    loading.value = false
  }
}
</script>

<style lang="postcss" scoped>
/**
 * I like to move it move it
 * I like to move it move it
 * I like to move it move it
 * You like to - move it!
 */
@keyframes shake {
  8%,
  41% {
    transform: translateX(-10px);
  }
  25%,
  58% {
    transform: translateX(10px);
  }
  75% {
    transform: translateX(-5px);
  }
  92% {
    transform: translateX(5px);
  }
  0%,
  100% {
    transform: translateX(0);
  }
}

form {
  &.error {
    @apply border-red-500;
    animation: shake 0.5s;
  }
}
</style>

