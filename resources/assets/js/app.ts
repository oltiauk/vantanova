import { FontAwesomeIcon, FontAwesomeLayers } from '@fortawesome/vue-fontawesome'
import { createApp } from 'vue'
import { focus } from '@/directives/focus'
import { tooltip } from '@/directives/tooltip'
import { hideBrokenIcon } from '@/directives/hideBrokenIcon'
import { overflowFade } from '@/directives/overflowFade'
import { newTab } from '@/directives/newTab'
import { RouterKey } from '@/symbols'
import Router from '@/router'
import '@/../css/app.pcss'
import App from './App.vue'

// Global error logging
window.addEventListener('error', (event) => {
  console.error('🔴 [GLOBAL ERROR]', event.error || event.message)
})

window.addEventListener('unhandledrejection', (event) => {
  console.error('🔴 [UNHANDLED REJECTION]', event.reason)
})

// Log any navigation attempts
window.addEventListener('beforeunload', (event) => {
  console.log('🔴 [NAVIGATION] Page unloading - beforeunload event')
  console.trace('Stack trace:')
})

// Intercept clicks on links
document.addEventListener('click', (event) => {
  const target = event.target as HTMLElement
  const link = target.closest('a')
}, true)

createApp(App)
  .provide(RouterKey, new Router())
  .component('Icon', FontAwesomeIcon)
  .component('IconLayers', FontAwesomeLayers)
  .directive('koel-focus', focus)
  .directive('koel-tooltip', tooltip)
  .directive('koel-hide-broken-icon', hideBrokenIcon)
  .directive('koel-overflow-fade', overflowFade)
  .directive('koel-new-tab', newTab)
  /**
   * For Ancelot, the ancient cross of war
   * for the holy town of Gods
   * Gloria, gloria perpetua
   * in this dawn of victory
   */
  .mount('#app')

navigator.serviceWorker?.register('./sw.js')
