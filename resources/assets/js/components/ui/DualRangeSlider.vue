<template>
  <div class="dual-range-slider w-full">
    <div class="slider-container">
      <input
        ref="fromSlider"
        type="range"
        :value="fromValue"
        :min="min"
        :max="max"
        @input="handleFromSlider"
        class="range-input from-slider"
      />
      <input
        ref="toSlider"
        type="range"
        :value="toValue"
        :min="min"
        :max="max"
        @input="handleToSlider"
        class="range-input to-slider"
      />
      <div class="slider-track">
        <div class="track-background"></div>
        <div class="track-fill" :style="`right: ${maxThumb}%; left: ${minThumb}%`"></div>
      </div>
    </div>
    <div class="input-group">
      <!-- <div class="input-wrapper">
        <input
          type="text"
          maxlength="5"
          v-model="fromValue"
          @input="handleFromTextInput"
          class="range-text-input"
          :placeholder="min.toString()"
        />
      </div>
      <div class="input-wrapper">
        <input
          type="text"
          maxlength="5"
          v-model="toValue"
          @input="handleToTextInput"
          class="range-text-input"
          :placeholder="max.toString()"
        />
      </div> -->
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, nextTick } from 'vue'

interface Props {
  min: number
  max: number
  from: number
  to: number
}

interface Emits {
  (e: 'update:from', value: number): void
  (e: 'update:to', value: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const fromSlider = ref<HTMLInputElement>()
const toSlider = ref<HTMLInputElement>()
const fromValue = ref(props.from)
const toValue = ref(props.to)
const minThumb = ref(0)
const maxThumb = ref(0)

const updateThumbPositions = () => {
  const rangeDistance = props.max - props.min
  minThumb.value = ((fromValue.value - props.min) / rangeDistance) * 100
  maxThumb.value = 100 - (((toValue.value - props.min) / rangeDistance) * 100)
}

const validateValues = () => {
  // Validate fromValue
  if (!/^\d*$/.test(fromValue.value.toString())) {
    fromValue.value = props.min
  } else {
    if (fromValue.value > props.max) {
      fromValue.value = Math.floor(props.max * 0.95)
    }
    if (fromValue.value < props.min) {
      fromValue.value = props.min
    }
  }
  
  // Validate toValue
  if (!/^\d*$/.test(toValue.value.toString())) {
    toValue.value = props.max
  } else {
    if (toValue.value > props.max) {
      toValue.value = props.max
    }
    if (toValue.value < props.min) {
      toValue.value = Math.max(props.min, Math.floor(props.min + (props.max - props.min) * 0.2))
    }
  }
}

const handleFromSlider = () => {
  if (!fromSlider.value) return
  
  const value = parseInt(fromSlider.value.value)
  const minGap = Math.max(1, Math.floor((props.max - props.min) * 0.05))
  
  // Ensure value doesn't go below minimum
  fromValue.value = Math.max(props.min, Math.min(value, toValue.value - minGap))
  
  validateValues()
  updateThumbPositions()
  emit('update:from', fromValue.value)
}

const handleToSlider = () => {
  if (!toSlider.value) return
  
  const value = parseInt(toSlider.value.value)
  const minGap = Math.max(1, Math.floor((props.max - props.min) * 0.02))
  
  // Ensure value doesn't go above maximum or below minimum + gap
  toValue.value = Math.min(props.max, Math.max(value, fromValue.value + minGap))
  
  validateValues()
  updateThumbPositions()
  emit('update:to', toValue.value)
}

const handleFromTextInput = () => {
  validateValues()
  const minGap = Math.max(1, Math.floor((props.max - props.min) * 0.05))
  fromValue.value = Math.max(props.min, Math.min(fromValue.value, toValue.value - minGap))
  updateThumbPositions()
  emit('update:from', fromValue.value)
}

const handleToTextInput = () => {
  validateValues()
  const minGap = Math.max(1, Math.floor((props.max - props.min) * 0.02))
  toValue.value = Math.min(props.max, Math.max(toValue.value, fromValue.value + minGap))
  updateThumbPositions()
  emit('update:to', toValue.value)
}


onMounted(async () => {
  await nextTick()
  updateThumbPositions()
})

watch(() => [props.from, props.to], () => {
  fromValue.value = props.from
  toValue.value = props.to
  nextTick(() => {
    updateThumbPositions()
  })
})
</script>

<style scoped>
.dual-range-slider {
  @apply flex flex-col gap-6;
}

.slider-container {
  @apply relative mb-2;
  height: 24px;
  min-height: 24px;
}

.range-input {
  @apply absolute w-full appearance-none bg-transparent;
  height: 24px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  z-index: 10;
}

.range-input::-webkit-slider-thumb {
  pointer-events: all;
}

.range-input::-moz-range-thumb {
  pointer-events: all;
}

.range-input::-webkit-slider-thumb {
  @apply appearance-none w-5 h-5 rounded-full cursor-pointer shadow-lg;
  background: var(--color-highlight);
  border: 2px solid rgba(255, 255, 255, 0.9);
  pointer-events: all;
  transition: all 0.2s ease-in-out;
  margin-top: -8px; /* Center the thumb properly */
}

.range-input::-moz-range-thumb {
  @apply w-5 h-5 rounded-full cursor-pointer shadow-lg border-0;
  background: var(--color-highlight);
  border: 2px solid rgba(255, 255, 255, 0.9);
  pointer-events: all;
  margin-top: -8px; /* Center the thumb properly */
}

.range-input::-webkit-slider-thumb:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 8px rgba(255, 125, 46, 0.3);
}

.range-input::-webkit-slider-thumb:active {
  transform: scale(1.05);
}

.slider-track {
  @apply absolute w-full h-2 rounded-full;
  top: 50%;
  left: 0;
  transform: translateY(-50%);
}

.track-background {
  @apply absolute inset-0 rounded-full;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.track-fill {
  @apply absolute top-0 bottom-0 rounded-full;
  background: linear-gradient(90deg, var(--color-highlight), var(--color-primary));
  transition: all 0.2s ease-in-out;
}


.input-group {
  @apply flex items-center justify-between gap-4;
}

.input-wrapper {
  @apply flex-1 max-w-24;
}

.range-text-input {
  @apply w-full text-center text-base;
  @apply bg-k-bg-input text-k-text-input;
  @apply px-3 py-2 rounded border;
  border-color: rgba(255, 255, 255, 0.1);
  transition: all 0.2s ease-in-out;
}

.range-text-input:focus {
  @apply outline-none;
  border-color: var(--color-highlight);
  box-shadow: 0 0 0 2px rgba(255, 125, 46, 0.1);
}

.range-text-input:hover {
  border-color: rgba(255, 255, 255, 0.2);
}

/* Hide the default slider track */
.range-input::-webkit-slider-track {
  @apply appearance-none bg-transparent;
}

.range-input::-moz-range-track {
  @apply bg-transparent;
}

/* Override z-index for proper layering */
.from-slider {
  z-index: 1;
}

.to-slider {
  z-index: 2;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .input-group {
    @apply gap-2;
  }
  
  .input-wrapper {
    @apply max-w-20;
  }
  
  .range-text-input {
    @apply px-2 py-1.5 text-sm;
  }
}
</style>