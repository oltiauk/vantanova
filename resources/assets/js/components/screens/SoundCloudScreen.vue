<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader header-image="/VantaNova-Logo.svg">
        Enhanced SoundCloud Search
      </ScreenHeader>
    </template>

    <div class="p-6 space-y-6">
      <!-- Enhanced Search Controls -->
      <div class="rounded-lg p-4">
        <!-- Main Layout: Search Controls Left, Advanced Filters Right -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Left Side: Search Controls (50% width) -->
          <div class="space-y-4">
            <!-- Top Row: Artist & Keywords -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Artist Input -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Artist(s)</label>
                <input
                  v-model="artistFilter"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                  placeholder="Type artist(s) name(s)"
                  @keyup.enter="search"
                >
              </div>

              <!-- Search Query Row -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Keyword in Title</label>
                <input
                  v-model="searchQuery"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg search-input"
                  placeholder="Type a title or keyword(s)"
                  @keyup.enter="search"
                >
                <div class="mt-2 flex flex-wrap gap-1">
                  <button
                    v-for="preset in keywordPresets"
                    :key="preset"
                    class="px-2 py-1 bg-white/10 hover:bg-k-accent/20 rounded text-xs text-white/70 hover:text-k-accent transition"
                    @click="addKeywordPreset(preset)"
                  >
                    {{ preset }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Top Row: Genres and Tags -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Genre Input -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Genre</label>
                <input
                  v-model="selectedGenre"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                  placeholder="Type a genre"
                >
              </div>

              <!-- Tags Input -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Tags</label>
                <input
                  v-model="searchTags"
                  type="text"
                  class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                  placeholder="Type other genre(s) or keyword(s)"
                >
              </div>
            </div>

            <!-- Second Row: Time Period and Content Type -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Time Period -->
              <div>
                <label class="block text-sm font-medium mb-2 text-white/80">Time Period</label>
                <select
                  v-model="timePeriod"
                  class="time-period-select w-full py-3 pl-4 pr-4 rounded-lg border-0 focus:outline-none text-white/80 text-lg font-medium transition"
                  style="background-color: rgba(47, 47, 47, 255) !important;"
                >
                  <option value="">All Time</option>
                  <option value="1d">Last Day</option>
                  <option value="1w">Last Week</option>
                  <option value="1m">Last Month</option>
                  <option value="3m">Last 3 Months</option>
                  <option value="6m">Last 6 Months</option>
                  <option value="1y">Last Year</option>
                </select>
              </div>

              <!-- Content Type -->
              <div>
                <label class="block text-sm font-medium mb-3 text-white/80">Content Type</label>
                <div class="flex gap-3">
                  <label class="flex-1 cursor-pointer">
                    <input
                      v-model="contentType"
                      type="radio"
                      value="tracks"
                      class="sr-only"
                    >
                    <div
                      class="px-4 py-2.5 rounded-lg border-2 transition-all duration-200 text-center text-sm font-medium"
                      :class="contentType === 'tracks'
                        ? 'border-k-accent bg-k-accent/20 text-white'
                        : 'border-white/20 bg-white/5 text-white/70 hover:border-white/30 hover:bg-white/10'"
                    >
                      Tracks
                    </div>
                  </label>
                  <label class="flex-1 cursor-pointer">
                    <input
                      v-model="contentType"
                      type="radio"
                      value="mixes"
                      class="sr-only"
                    >
                    <div
                      class="px-4 py-2.5 rounded-lg border-2 transition-all duration-200 text-center text-sm font-medium"
                      :class="contentType === 'mixes'
                        ? 'border-k-accent bg-k-accent/20 text-white'
                        : 'border-white/20 bg-white/5 text-white/70 hover:border-white/30 hover:bg-white/10'"
                    >
                      Mixes
                    </div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Side: Advanced Filters (50% width) -->
          <div>
            <div class="p-4 bg-white/5 rounded-lg h-full flex flex-col">
              <div class="flex items-center gap-2 mb-4">
                <Icon :icon="faFilter" class="text-white/80" />
                <h3 class="text-sm font-medium text-white/80">Advanced Filters</h3>
              </div>

              <!-- Min/Max Plays -->
              <div class="grid grid-cols-2 gap-4 mb-auto">
                <!-- Minimum Plays -->
                <div>
                  <label class="block text-sm font-medium mb-2 text-white/80">Minimum Streams</label>
                  <input
                    v-model="minPlaysFormatted"
                    type="text"
                    placeholder="e.g. 10,000"
                    class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                    @input="handleMinPlaysInput"
                  >
                </div>

                <!-- Maximum Plays -->
                <div>
                  <label class="block text-sm font-medium mb-2 text-white/80">Maximum Streams</label>
                  <input
                    v-model="maxPlaysFormatted"
                    type="text"
                    placeholder="e.g. 1,000,000"
                    class="w-full py-3 pl-4 pr-4 bg-white/10 rounded-lg border-0 focus:outline-none text-white text-lg"
                    @input="handleMaxPlaysInput"
                  >
                </div>
              </div>

              <!-- BPM Range - Pushed to bottom -->
              <div class="mt-auto">
                <label class="block text-sm font-medium mb-2 text-white/80">
                  BPM Range: {{ bpmFrom }} - {{ bpmTo }}
                </label>
                <div class="mt-4">
                  <DualRangeSlider
                    :min="60"
                    :max="200"
                    :from="bpmFrom"
                    :to="bpmTo"
                    class="bpm-slider-white"
                    @update:from="bpmFrom = $event"
                    @update:to="bpmTo = $event"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Search Button -->
        <div class="flex justify-center gap-3 mt-6">
          <button
            :disabled="loading || !hasValidFilters"
            class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-medium transition flex items-center gap-2"
            @click="search"
          >
            <Icon :icon="faSearch" />
            {{ searchButtonText }}
          </button>

          <button
            v-if="hasValidFilters"
            class="px-4 py-3 bg-white/10 hover:bg-white/20 rounded-lg font-medium transition"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Loading Animation -->
      <div v-if="loading" class="text-center p-12">
        <div class="inline-flex flex-col items-center">
          <svg class="w-8 h-8 animate-spin text-white mb-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="m4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        </div>
      </div>

      <!-- Enhanced Results Table -->
      <div v-else-if="tracks.length > 0">
        <!-- Sort by Dropdown -->
        <div class="flex justify-end mb-4 relative">
          <button
            class="px-4 py-2 rounded-lg font-medium transition flex items-center gap-2 bg-white/10 text-white/80 hover:bg-white/20"
            style="background-color: rgba(47, 47, 47, 255) !important;"
            @click="toggleLikesRatioDropdown"
            @blur="hideLikesRatioDropdown"
          >
            {{ getSortText() }}
            <Icon :icon="faChevronDown" class="text-xs" />
          </button>

          <!-- Dropdown Menu -->
          <div
            v-if="showLikesRatioDropdown"
            class="absolute right-0 mt-12 w-52  rounded-lg shadow-lg z-10"
            style="background-color: rgb(67,67,67,255);"
          >
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2 rounded-t-lg"
              :style="likesRatioFilter === 'newest' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('newest')"
            >
              Most Recent
            </button>
            <button
              class="w-full px-4 py-2 text-left text-white hover:bg-white/10 transition flex items-center gap-2 rounded-b-lg"
              :style="likesRatioFilter === 'streams' ? 'background-color: rgb(67,67,67,255)' : ''"
              @mousedown.prevent="setLikesRatioFilter('streams')"
            >
              Most Streams
            </button>
          </div>
        </div>

        <SoundCloudTrackTable
          ref="soundcloudTable"
          :tracks="tracks"
          :start-index="0"
          :saved-tracks="savedTracks"
          :blacklisted-tracks="blacklistedTracks"
          :processing-track="processingTrack"
          :listened-tracks="listenedTracks"
          @play="playTrack"
          @pause="pauseTrack"
          @seek="seekTrack"
          @related-tracks="openRelatedTracks"
          @save-track="saveTrack"
          @blacklist-track="blacklistTrack"
          @mark-listened="markTrackAsListened"
        />

        <!-- Load More -->
        <div v-if="hasMoreResults && !loading" class="flex items-center justify-center mt-8">
          <button
            class="px-4 py-2 bg-k-accent text-white rounded hover:bg-k-accent/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="loadingMore"
            @click="loadMoreResults"
          >
            <Icon v-if="loadingMore" :icon="faSpinner" spin class="mr-2" />
            Load More
          </button>
        </div>
      </div>

      <!-- No Results -->
      <div v-else-if="searched && tracks.length === 0 && !loading" class="text-center p-12">
        <Icon :icon="faSearch" class="text-4xl text-white/40 mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">No Results Found</h3>
        <p class="text-white/60 mb-4">
          No tracks found with your criteria. Try broadening your filters.
        </p>
        <div class="text-sm text-white/50 max-w-md mx-auto">
          <strong>Try:</strong>
          <ul class="list-disc list-inside mt-2 space-y-1">
            <li>Broadening your BPM range</li>
            <li>Removing BPM range filter</li>
            <li>Using more general tags</li>
            <li>Removing time period restrictions</li>
            <li>Lowering minimum plays/likes</li>
          </ul>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="error" class="bg-red-500/20 border border-red-500/40 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <Icon :icon="faExclamationTriangle" class="text-red-400 mt-0.5" />
          <div>
            <h4 class="font-medium text-red-200 mb-1">Search Error</h4>
            <p class="text-red-200">{{ error }}</p>
          </div>
        </div>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faBan, faChevronDown, faExclamationTriangle, faFilter, faHeart, faSearch, faSpinner } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from '@/composables/useRouter'
import { type SoundCloudFilters, soundcloudService, type SoundCloudTrack } from '@/services/soundcloudService'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { eventBus } from '@/utils/eventBus'
import Router from '@/router'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { http } from '@/services/http'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import DualRangeSlider from '@/components/ui/DualRangeSlider.vue'
import SoundCloudTrackTable from '@/components/ui/soundcloud/SoundCloudTrackTable.vue'

interface SearchStats {
  apiCalls: number
  totalTime: number
  cacheHits: number
  resultQuality: 'high' | 'medium' | 'low'
}

// Search form state
const searchQuery = ref('')
const selectedGenre = ref('')
const searchTags = ref('')
const bpmFrom = ref<number>(95)
const bpmTo = ref<number>(172)
// Temporary input states to prevent slider jumping while typing
const bpmFromInput = ref<string>('95')
const bpmToInput = ref<string>('172')
const timePeriod = ref('')
const artistFilter = ref('')
const minPlays = ref<number>()
const maxPlays = ref<number>()
const minPlaysFormatted = ref('')
const maxPlaysFormatted = ref('')
const contentType = ref('tracks') // Default to tracks
const likesRatioFilter = ref<'streams' | 'newest'>('newest') // Sort filter state
const showLikesRatioDropdown = ref(false) // Dropdown visibility
const displayLimit = ref(20)

// Results state
const tracks = ref<SoundCloudTrack[]>([])
const originalTracks = ref<SoundCloudTrack[]>([]) // Store unfiltered tracks
const loading = ref(false)
const searched = ref(false)
const error = ref('')
const searchStats = ref<SearchStats | null>(null)

// Pagination state
const loadingMore = ref(false)
const hasMoreResults = ref(false)
const serverHasMore = ref(false)
const lastSearchFilters = ref<SoundCloudFilters | null>(null)

// Banned artists tracking (shared with Similar Artists and Recommendations)
const bannedArtists = ref(new Set<string>()) // Store artist names

// Initialize global blacklist filtering composable
const {
  loadBlacklistedItems,
  addTrackToBlacklist,
  filterSoundCloudTracks,
} = useBlacklistFiltering()

// Music preferences state
const savedTracks = ref<Set<string>>(new Set())
const blacklistedTracks = ref<Set<string>>(new Set())
const clientUnsavedTracks = ref<Set<string>>(new Set()) // Tracks unsaved by client
const processingTrack = ref<string | number | null>(null)
const listenedTracks = ref<Set<string>>(new Set()) // Tracks that have been listened to

// Player state (now using global store)
// Note: Player will show in footer when this component is mounted (SoundCloud page)

// Computed properties
const hasValidFilters = computed(() => {
  const hasQuery = searchQuery.value?.trim()
  const hasGenre = selectedGenre.value && selectedGenre.value !== 'All Genres' && selectedGenre.value !== ''
  const hasTags = searchTags.value?.trim()
  const hasBPM = (bpmFrom.value !== 95 || bpmTo.value !== 172)
  const hasArtist = artistFilter.value?.trim()
  const hasAdvanced = minPlays.value || maxPlays.value || (contentType.value !== 'tracks')
  const hasTimePeriod = timePeriod.value && timePeriod.value !== ''

  return hasQuery || hasGenre || hasTags || hasBPM || hasAdvanced || hasTimePeriod || hasArtist
})

const searchButtonText = computed(() => 'Search')

// Keyword presets that users commonly search for
const keywordPresets = [
  'Remix',
  'Mashup',
  'Free Download',
  'Free DL',
]

const addKeywordPreset = (preset: string) => {
  if (searchQuery.value) {
    // Add space if there are existing keywords
    if (!searchQuery.value.endsWith(' ')) {
      searchQuery.value += ' '
    }
    searchQuery.value += preset
  } else {
    searchQuery.value = preset
  }
}

const formatCount = (count: number): string => {
  if (count >= 1000000) {
    return `${(count / 1000000).toFixed(1)}M`
  } else if (count >= 1000) {
    return `${(count / 1000).toFixed(1)}K`
  }
  return count.toString()
}

const formatDate = (dateString: string): string => {
  if (!dateString) {
    return 'Unknown'
  }
  try {
    const date = new Date(dateString)
    const now = new Date()
    const diffTime = Math.abs(now.getTime() - date.getTime())
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    if (diffDays < 30) {
      return `${diffDays} days ago`
    } else if (diffDays < 365) {
      const months = Math.floor(diffDays / 30)
      return `${months} month${months > 1 ? 's' : ''} ago`
    } else {
      const years = Math.floor(diffDays / 365)
      return `${years} year${years > 1 ? 's' : ''} ago`
    }
  } catch {
    return 'Unknown'
  }
}

const toggleLikesRatioDropdown = () => {
  showLikesRatioDropdown.value = !showLikesRatioDropdown.value
}

const hideLikesRatioDropdown = () => {
  setTimeout(() => {
    showLikesRatioDropdown.value = false
  }, 150) // Small delay to allow click events to register
}

const setLikesRatioFilter = (type: 'streams' | 'newest') => {
  likesRatioFilter.value = type
  showLikesRatioDropdown.value = false
  applyFiltering()
}

const getSortText = () => {
  switch (likesRatioFilter.value) {
    case 'streams': return 'Most Streams'
    default: return 'Most Recent'
  }
}

const getFilteredTracks = () => {
  let filteredTracks = [...originalTracks.value]
  const originalCount = filteredTracks.length

  console.log('🔍 applyFiltering called:', {
    originalCount,
    minPlays: minPlays.value,
    maxPlays: maxPlays.value,
    hasPlayFilters: !!(minPlays.value || maxPlays.value),
    searchQuery: searchQuery.value,
    searchTags: searchTags.value,
  })

  // Apply keyword filtering in title first (if search query exists)
  if (searchQuery.value?.trim()) {
    const keywords = searchQuery.value.trim().toLowerCase().split(/\s+/).filter(Boolean)
    const beforeCount = filteredTracks.length
    filteredTracks = filteredTracks.filter(track => {
      const title = (track.title || '').toLowerCase()
      // All keywords must be present in the title
      const matches = keywords.every(keyword => title.includes(keyword))
      if (!matches) {
        console.log('❌ Title filter REJECTED:', {
          title: track.title,
          searchedKeywords: keywords,
          reason: 'Not all keywords found in title',
        })
      }
      return matches
    })

    console.log('🔍 [KEYWORD IN TITLE FILTER]', {
      searchQuery: searchQuery.value,
      keywords,
      beforeCount,
      afterCount: filteredTracks.length,
      filteredOut: beforeCount - filteredTracks.length,
      sampleMatches: filteredTracks.slice(0, 3).map(t => ({
        title: t?.title,
        matchedKeywords: keywords.filter(k => t.title?.toLowerCase().includes(k)),
      })),
    })
  }

  // Helper function to create tag variations (handles hyphen/space variations)
  const createTagVariations = (tag: string): string[] => {
    const variations = new Set<string>()
    variations.add(tag) // Original tag
    variations.add(tag.replace(/-/g, ' ')) // Replace hyphens with spaces: "hip-hop" -> "hip hop"
    variations.add(tag.replace(/\s+/g, '-')) // Replace spaces with hyphens: "hip hop" -> "hip-hop"
    variations.add(tag.replace(/[-\s]+/g, '')) // Remove both: "hip-hop" -> "hiphop"
    return Array.from(variations)
  }

  // Apply tag filtering (if search tags exist)
  if (searchTags.value?.trim()) {
    // Get the original search string and split into individual tags
    const originalSearchString = searchTags.value.trim().toLowerCase()
    const rawTags = originalSearchString.split(/\s+/).filter(Boolean)
    const beforeCount = filteredTracks.length

    filteredTracks = filteredTracks.filter(track => {
      // Check if tags appear in tag_list, caption, description, or title fields
      const tagList = ((track as any).tag_list || '').toLowerCase()
      const caption = ((track as any).caption || '').toLowerCase()
      const description = ((track as any).description || '').toLowerCase()
      const title = (track.title || '').toLowerCase()

      // First, check if the full original search string (as a phrase) exists
      const fullPhraseVariations = createTagVariations(originalSearchString)
      const fullPhraseFound = fullPhraseVariations.some(v =>
        tagList.includes(v) || caption.includes(v) || description.includes(v) || title.includes(v),
      )

      // If full phrase found, accept the track
      if (fullPhraseFound) {
        return true
      }

      // Otherwise, check if all individual tags are found (each can be in different fields)
      const tagMatches = rawTags.map(tag => {
        const variations = createTagVariations(tag)
        const foundInTagList = variations.some(v => tagList.includes(v))
        const foundInCaption = variations.some(v => caption.includes(v))
        const foundInDescription = variations.some(v => description.includes(v))
        const foundInTitle = variations.some(v => title.includes(v))
        const foundAnywhere = foundInTagList || foundInCaption || foundInDescription || foundInTitle

        return {
          tag,
          variations,
          foundInTagList,
          foundInCaption,
          foundInDescription,
          foundInTitle,
          foundAnywhere,
        }
      })

      const allTagsFound = tagMatches.every(m => m.foundAnywhere)

      const trackMatches = fullPhraseFound || allTagsFound

      if (!trackMatches) {
        const missingTags = tagMatches.filter(m => !m.foundAnywhere).map(m => ({
          tag: m.tag,
          variations: m.variations,
        }))
        console.log('❌ Tag filter REJECTED:', {
          title: track.title,
          searchedPhrase: originalSearchString,
          searchedTags: rawTags,
          fullPhraseFound,
          missingTags,
          tag_list: tagList || '(empty)',
          caption: caption || '(empty)',
          description: description || '(empty)',
          title_field: title || '(empty)',
          reason: fullPhraseFound
            ? 'N/A (should not happen)'
            : `Missing tags (checked phrase "${originalSearchString}" and individual tags in tag_list/caption/description/title): ${missingTags.map(m => `${m.tag} [${m.variations.join(', ')}]`).join(', ')}`,
        })
      }

      return trackMatches
    })

    console.log('🏷️ [TAG FILTER]', {
      searchTags: searchTags.value,
      originalPhrase: originalSearchString,
      tags: rawTags,
      beforeCount,
      afterCount: filteredTracks.length,
      filteredOut: beforeCount - filteredTracks.length,
      sampleMatches: filteredTracks.slice(0, 3).map(t => {
        const tagList = ((t as any).tag_list || '').toLowerCase()
        const caption = ((t as any).caption || '').toLowerCase()
        const description = ((t as any).description || '').toLowerCase()
        const title = (t.title || '').toLowerCase()

        // Check if full phrase matched
        const fullPhraseVariations = createTagVariations(originalSearchString)
        const matchedViaPhrase = fullPhraseVariations.some(v =>
          tagList.includes(v) || caption.includes(v) || description.includes(v) || title.includes(v),
        )

        // Check which individual tags matched
        const matchedTags = rawTags.filter(tag => {
          const variations = createTagVariations(tag)
          return variations.some(v =>
            tagList.includes(v) || caption.includes(v) || description.includes(v) || title.includes(v),
          )
        })

        return {
          title: t?.title,
          tag_list: (t as any).tag_list || '(empty)',
          caption: (t as any).caption || '(empty)',
          description: (t as any).description || '(empty)',
          matchedViaPhrase,
          matchedTags,
          matchMethod: matchedViaPhrase ? 'full phrase' : 'individual tags',
        }
      }),
    })
  }

  // Apply plays filtering
  if (minPlays.value || maxPlays.value) {
    filteredTracks = filteredTracks.filter(track => {
      const playCount = track.playback_count || 0

      if (minPlays.value && playCount < minPlays.value) {
        return false
      }

      if (maxPlays.value && playCount > maxPlays.value) {
        return false
      }

      return true
    })

    console.log('🔍 After plays filtering:', {
      originalCount,
      filteredCount: filteredTracks.length,
      sampleResults: filteredTracks.slice(0, 3).map(t => ({
        title: t?.title,
        plays: t?.playback_count,
      })),
    })
  }

  // Note: Artist filter is now included in the API search query, so no need to filter client-side

  // Then apply sorting
  if (likesRatioFilter.value === 'newest') {
    // Sort by creation date newest to oldest
    filteredTracks.sort((a, b) => {
      const dateA = new Date(a.created_at || 0).getTime()
      const dateB = new Date(b.created_at || 0).getTime()
      return dateB - dateA
    })
  } else {
    // Sort by streams (highest to lowest)
    filteredTracks.sort((a, b) => (b.playback_count || 0) - (a.playback_count || 0))
  }

  return filteredTracks
}

const applyFiltering = () => {
  const filteredTracks = getFilteredTracks()
  updateVisibleTracks(filteredTracks, false)
}

const updateVisibleTracks = (filteredTracks: SoundCloudTrack[], append: boolean) => {
  if (append) {
    const start = tracks.value.length
    const end = Math.min(filteredTracks.length, start + 20)
    if (end <= start) {
      hasMoreResults.value = serverHasMore.value || filteredTracks.length > start
      return
    }
    const nextChunk = filteredTracks.slice(start, end)
    tracks.value.push(...nextChunk)
    displayLimit.value = tracks.value.length
  } else {
    displayLimit.value = Math.max(20, displayLimit.value)
    tracks.value = filteredTracks.slice(0, displayLimit.value)
  }

  hasMoreResults.value = serverHasMore.value || filteredTracks.length > tracks.value.length
}

// Number formatting helpers for plays inputs
const formatNumberWithCommas = (num: number): string => {
  return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}

const parseNumberFromFormatted = (str: string): number | undefined => {
  const cleaned = str.replace(/[,\s]/g, '')
  const num = Number.parseInt(cleaned, 10)
  return isNaN(num) ? undefined : num
}

const handleMinPlaysInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value
  const cursorPosition = target.selectionStart || 0

  // Remove all non-digits first
  const digitsOnly = value.replace(/\D/g, '')

  if (digitsOnly === '') {
    minPlaysFormatted.value = ''
    minPlays.value = undefined
    return
  }

  // Parse and format with commas
  const num = Number.parseInt(digitsOnly, 10)
  const formatted = formatNumberWithCommas(num)

  minPlaysFormatted.value = formatted
  minPlays.value = num

  // Restore cursor position (adjust for added commas)
  setTimeout(() => {
    const commasBeforeCursor = (formatted.substring(0, cursorPosition).match(/,/g) || []).length
    const newPosition = Math.min(cursorPosition + commasBeforeCursor, formatted.length)
    target.setSelectionRange(newPosition, newPosition)
  }, 0)
}

const handleMaxPlaysInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value
  const cursorPosition = target.selectionStart || 0

  // Remove all non-digits first
  const digitsOnly = value.replace(/\D/g, '')

  if (digitsOnly === '') {
    maxPlaysFormatted.value = ''
    maxPlays.value = undefined
    return
  }

  // Parse and format with commas
  const num = Number.parseInt(digitsOnly, 10)
  const formatted = formatNumberWithCommas(num)

  maxPlaysFormatted.value = formatted
  maxPlays.value = num

  // Restore cursor position (adjust for added commas)
  setTimeout(() => {
    const commasBeforeCursor = (formatted.substring(0, cursorPosition).match(/,/g) || []).length
    const newPosition = Math.min(cursorPosition + commasBeforeCursor, formatted.length)
    target.setSelectionRange(newPosition, newPosition)
  }, 0)
}

// BPM input handlers with debouncing to prevent slider jumping
let bpmFromTimeout: NodeJS.Timeout | null = null
let bpmToTimeout: NodeJS.Timeout | null = null

const handleBpmFromInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value

  // Update the input display immediately - DON'T interfere with user typing
  bpmFromInput.value = value

  // Clear existing timeout
  if (bpmFromTimeout) {
    clearTimeout(bpmFromTimeout)
  }

  // Debounce the actual BPM update to prevent slider jumping
  bpmFromTimeout = setTimeout(() => {
    const numValue = Number.parseInt(value, 10)
    if (!isNaN(numValue) && numValue >= 60 && numValue <= 200) {
      // Ensure min doesn't exceed max with some gap
      bpmFrom.value = Math.min(numValue, bpmTo.value - 5)
    }
    // Don't reset to default when empty - let user type freely
  }, 500) // 500ms debounce
}

const handleBpmToInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  const value = target.value

  // Update the input display immediately - DON'T interfere with user typing
  bpmToInput.value = value

  // Clear existing timeout
  if (bpmToTimeout) {
    clearTimeout(bpmToTimeout)
  }

  // Debounce the actual BPM update to prevent slider jumping
  bpmToTimeout = setTimeout(() => {
    const numValue = Number.parseInt(value, 10)
    if (!isNaN(numValue) && numValue >= 60 && numValue <= 200) {
      // Ensure max doesn't go below min with some gap
      bpmTo.value = Math.max(numValue, bpmFrom.value + 5)
    }
    // Don't reset to default when empty - let user type freely
  }, 500) // 500ms debounce
}

// Watch for plays filter changes to re-apply filtering
watch([minPlays, maxPlays], () => {
  // Only re-filter if we have tracks AND a search has been performed
  if (originalTracks.value.length > 0 && searched.value && !loading.value) {
    applyFiltering()
  }
})

// Watch for slider changes to update input fields (only when not actively typing)
watch([bpmFrom, bpmTo], ([newFrom, newTo]) => {
  // Only update input fields if they don't already contain the same value
  // This prevents interference while user is typing
  if (bpmFromInput.value !== newFrom.toString()) {
    bpmFromInput.value = newFrom.toString()
  }
  if (bpmToInput.value !== newTo.toString()) {
    bpmToInput.value = newTo.toString()
  }
})

const search = async () => {
  if (!hasValidFilters.value) {
    error.value = 'Please select at least one filter to search with enhanced features.'
    return
  }

  loading.value = true
  error.value = ''
  tracks.value = []
  originalTracks.value = []
  searchStats.value = null
  hasMoreResults.value = false
  loadingMore.value = false

  const startTime = performance.now()

  try {
    // Convert contentType to duration filters
    let durationFromFilter: number | undefined
    let durationToFilter: number | undefined

    if (contentType.value === 'tracks') {
      // Tracks: max 1000 seconds (16 minutes 40 seconds)
      durationToFilter = 1000
    } else if (contentType.value === 'mixes') {
      // Mixes: min 1000 seconds
      durationFromFilter = 1000
    }

    // Combine artist filter with search query for API search
    const combinedSearchQuery = [
      artistFilter.value?.trim(),
      searchQuery.value?.trim(),
    ].filter(Boolean).join(' ') || undefined

    const filters: SoundCloudFilters = {
      searchQuery: combinedSearchQuery,
      searchTags: searchTags.value?.trim() || undefined,
      genre: selectedGenre.value || undefined,
      bpmFrom: bpmFrom.value !== 95 ? bpmFrom.value : undefined,
      bpmTo: bpmTo.value !== 172 ? bpmTo.value : undefined,
      durationFrom: durationFromFilter,
      durationTo: durationToFilter,
      minPlays: minPlays.value || undefined,
      maxPlays: maxPlays.value || undefined,
      timePeriod: timePeriod.value || undefined,
      limit: 200,
    }

    // Store filters for pagination
    lastSearchFilters.value = filters

    console.log('🎵 DEBUG: Filters being sent to API:', filters)

    const response = await soundcloudService.searchWithPagination(filters)
    const endTime = performance.now()

    // Store original tracks and display them
    // Store original tracks and display them
    console.log('🎵 DEBUG: Response received:', {
      responseTracksCount: response.tracks?.length || 0,
      firstTrack: response.tracks?.[0] || null,
      samplePlayCounts: (response.tracks || []).slice(0, 5).map(t => ({
        title: t?.title,
        plays: t?.playback_count,
      })),
    })

    const pageLimit = filters.limit || 200
    const limitedTracks = response.tracks.slice(0, pageLimit)

    // Apply global blacklist filtering (tracks + artists)
    const globalFiltered = filterSoundCloudTracks(limitedTracks)

    // Apply local banned artists filtering (for Similar Artists compatibility)
    const filteredTracks = filterBannedArtists(globalFiltered)
    console.log(`🚫 SoundCloud: Filtered ${limitedTracks.length - filteredTracks.length} blacklisted items (${limitedTracks.length - globalFiltered.length} global + ${globalFiltered.length - filteredTracks.length} local banned artists)`)

    const uniqueTracks = dedupeTracks(filteredTracks)
    originalTracks.value = uniqueTracks
    tracks.value = uniqueTracks

    console.log('🎵 DEBUG: After storing:', {
      originalTracksCount: originalTracks.value.length,
      tracksCount: tracks.value.length,
      minPlays: minPlays.value,
      maxPlays: maxPlays.value,
      willFilter: !!(minPlays.value || maxPlays.value),
    })

    serverHasMore.value = response.hasMore || response.tracks.length >= pageLimit

    // Reset visible count to first 20
    displayLimit.value = 20

    // Apply current filtering to visible tracks (updates hasMoreResults)
    applyFiltering()

    console.log('🎵 Search complete:', {
      totalTracks: tracks.value.length,
      hasMorePages: hasMoreResults.value,
    })

    // Calculate search statistics
    const resultQuality: SearchStats['resultQuality']
      = tracks.value.length > 15
        ? 'high'
        : tracks.value.length > 5 ? 'medium' : 'low'

    searchStats.value = {
      apiCalls: response.apiCalls || 1,
      totalTime: Math.round(endTime - startTime),
      cacheHits: 0,
      resultQuality,
    }

    searched.value = true
    initialLoadComplete.value = true
  } catch (err: any) {
    error.value = err.message || 'Failed to search SoundCloud. Please try again.'
    tracks.value = []
    originalTracks.value = []
  } finally {
    loading.value = false
  }
}

const loadMoreResults = async () => {
  if (!lastSearchFilters.value || loadingMore.value || !hasMoreResults.value) {
    return
  }

  const currentVisibleCount = tracks.value.length
  const filteredTracks = getFilteredTracks()

  // If we already have more locally, just reveal the next chunk
  if (displayLimit.value < filteredTracks.length) {
    updateVisibleTracks(filteredTracks, true)
    if (tracks.value.length > currentVisibleCount) {
      setTimeout(() => {
        triggerRowAnimations(currentVisibleCount)
      }, 50)
    }
    return
  }

  // Otherwise fetch the next batch from the API
  loadingMore.value = true
  error.value = ''

  try {
    const offset = originalTracks.value.length
    const limit = lastSearchFilters.value.limit || 200

    const filters = {
      ...lastSearchFilters.value,
      offset,
      limit,
    }

    console.log(`🎵 DEBUG: Loading more SoundCloud results offset ${offset}, limit ${limit}`)

    const response = await soundcloudService.searchWithPagination(filters)

    if (response.tracks && response.tracks.length > 0) {
      const limitedTracks = response.tracks.slice(0, limit)

      // Apply global blacklist filtering (tracks + artists)
      const globalFiltered = filterSoundCloudTracks(limitedTracks)

      // Apply local banned artists filtering (for Similar Artists compatibility)
      const filteredTracksApi = filterBannedArtists(globalFiltered)
      const uniqueTracks = dedupeTracks(filteredTracksApi)

      originalTracks.value = [...originalTracks.value, ...uniqueTracks]
      serverHasMore.value = response.hasMore || response.tracks.length >= limit

      const refreshedFiltered = getFilteredTracks()
      updateVisibleTracks(refreshedFiltered, true)
    } else {
      serverHasMore.value = false
      hasMoreResults.value = false
    }
  } catch (err: any) {
    console.error('🎵 Load more error:', err)
    error.value = 'Failed to load more tracks. Please try again.'
  } finally {
    loadingMore.value = false
  }
}

const playTrack = async (track: SoundCloudTrack) => {
  try {
    console.log('🎵 [SCREEN] Starting playTrack for:', track.title)
    console.log('🎵 [SCREEN] Track ID:', track.id)

    // Check if this track is already current and just paused
    const currentTrack = soundcloudPlayerStore.state.currentTrack
    if (currentTrack && currentTrack.id === track.id) {
      console.log('🎵 [SCREEN] Resuming current track')
      soundcloudPlayerStore.setPlaying(true)
      return
    }

    // Show player immediately with loading state
    soundcloudPlayerStore.show(track, '')
    console.log('🎵 [SCREEN] Player store shown, isVisible:', soundcloudPlayerStore.isVisible)

    // Update navigation state based on track position
    updateNavigationState(track)

    console.log('🎵 Loading SoundCloud player for track:', track.title)

    const embedUrl = await soundcloudService.getEmbedUrl(track.id, {
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true,
    })

    console.log('🎵 [DEBUG] Got embed URL:', embedUrl)
    soundcloudPlayerStore.setEmbedUrl(embedUrl)
    console.log('🎵 [DEBUG] Embed URL set in store')
    console.log('🎵 SoundCloud player loaded successfully')
  } catch (err: any) {
    console.error('🎵 [ERROR] Failed to load SoundCloud player:', err)
    error.value = `Failed to load SoundCloud player: ${err.message || 'Unknown error'}`
    soundcloudPlayerStore.hide() // Close player on error
  }
}

// Track navigation functions
const getCurrentTrackIndex = () => {
  const currentTrack = soundcloudPlayerStore.track
  if (!currentTrack) {
    return -1
  }
  return tracks.value.findIndex(track => track.id === currentTrack.id)
}

const updateNavigationState = (track: SoundCloudTrack) => {
  const currentIndex = tracks.value.findIndex(t => t.id === track.id)
  const canSkipPrevious = currentIndex > 0
  const canSkipNext = currentIndex >= 0 && currentIndex < tracks.value.length - 1

  soundcloudPlayerStore.setNavigationState(canSkipPrevious, canSkipNext)
  console.log('🎵 Navigation state updated:', { canSkipPrevious, canSkipNext, currentIndex, totalTracks: tracks.value.length })
}

const skipToPrevious = () => {
  const currentIndex = getCurrentTrackIndex()
  if (currentIndex > 0) {
    const previousTrack = tracks.value[currentIndex - 1]
    console.log('🎵 Skipping to previous track:', previousTrack.title)
    playTrack(previousTrack) // This will automatically update navigation state
  }
}

const skipToNext = () => {
  const currentIndex = getCurrentTrackIndex()
  if (currentIndex >= 0 && currentIndex < tracks.value.length - 1) {
    const nextTrack = tracks.value[currentIndex + 1]
    console.log('🎵 Skipping to next track:', nextTrack.title)
    playTrack(nextTrack) // This will automatically update navigation state
  }
}

const pauseTrack = (track?: SoundCloudTrack) => {
  console.log('🎵 [SCREEN] Pausing track:', track?.title || 'current')
  soundcloudPlayerStore.setPlaying(false)
}

const seekTrack = (position: number) => {
  console.log('🎵 Seeking to position:', `${position}%`)
  // Here you could implement actual seek functionality if needed
  // For now, this is just for UI feedback
}

const openRelatedTracks = (track: SoundCloudTrack) => {
  console.log('🎵 Opening Related Tracks for:', track.title)

  // Store the related tracks data for the next screen
  eventBus.emit('SOUNDCLOUD_RELATED_TRACKS_DATA', {
    type: 'related',
    trackUrn: `soundcloud:tracks:${track.id}`,
    trackTitle: track.title,
    artist: track.user?.username || 'Unknown Artist',
  })

  // Navigate to the SoundCloud Related Tracks screen using router
  Router.go('soundcloud-related-tracks')
}

// Helper function to get track key
const getTrackKey = (track: SoundCloudTrack): string => {
  const artist = track.user?.username || 'Unknown'
  const title = track.title || 'Untitled'
  return `${artist}-${title}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
}

// Check if track is saved
const isTrackSaved = (track: SoundCloudTrack): boolean => {
  const trackKey = getTrackKey(track)
  return savedTracks.value.has(trackKey) && !clientUnsavedTracks.value.has(trackKey)
}

// Check if track is blacklisted
const isTrackBlacklisted = (track: SoundCloudTrack): boolean => {
  return blacklistedTracks.value.has(getTrackKey(track))
}

// Check if ban button should be active (red)
const isBanButtonActive = (track: SoundCloudTrack): boolean => {
  return isTrackBlacklisted(track) && !isTrackSaved(track)
}

// Save track function
const saveTrack = async (track: SoundCloudTrack) => {
  const trackKey = getTrackKey(track)

  if (isTrackSaved(track)) {
    // Unsave track: Update UI immediately for better UX
    savedTracks.value.delete(trackKey)

    // Since no DELETE endpoint exists for saved tracks, use client-side tracking
    clientUnsavedTracks.value.add(trackKey)

    // Save to localStorage for persistence across page reloads
    try {
      const unsavedList = Array.from(clientUnsavedTracks.value)
      localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
    } catch (error) {
      // Failed to save unsaved tracks to localStorage
    }

    // ALSO remove from blacklist when unsaving
    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const deleteData = {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (response.success) {
        blacklistedTracks.value.delete(trackKey)
        console.log('✅ Track removed from blacklist on unsave:', track.title)

        window.dispatchEvent(new CustomEvent('track-unblacklisted', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      }
    } catch (error) {
      console.warn('Failed to remove track from blacklist on unsave:', error)
    }

    // Trigger SavedTracksScreen refresh
    try {
      window.dispatchEvent(new CustomEvent('track-unsaved', {
        detail: { track, trackKey },
      }))
      localStorage.setItem('track-unsaved-timestamp', Date.now().toString())
    } catch (error) {
      // Event dispatch failed, not critical
    }
  } else {
    // Reload client unsaved tracks from localStorage first to get the latest list
    // This ensures we don't lose tracks that were trashed in other screens
    try {
      const stored = localStorage.getItem('koel-client-unsaved-tracks')
      if (stored) {
        const unsavedList = JSON.parse(stored)
        clientUnsavedTracks.value = new Set(unsavedList)
      }
    } catch (error) {
      // Failed to load client unsaved tracks
    }

    // Update UI immediately for instant feedback
    savedTracks.value.add(trackKey)
    clientUnsavedTracks.value.delete(trackKey)

    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/save-track', {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
        spotify_id: null, // SoundCloud tracks don't have Spotify IDs
        label: null,
        popularity: null,
        followers: track.user?.followers_count || null,
        streams: track.playback_count || null,
        release_date: track.created_at || null,
        preview_url: null,
        track_count: 1,
        is_single_track: true,
      })

      if (!response.success) {
        savedTracks.value.delete(trackKey)
        throw new Error(response.error || 'Failed to save track')
      }

      // ALSO add to blacklist when saving
      try {
        const blacklistResponse = await http.post('music-preferences/blacklist-track', {
          spotify_id: null,
          isrc: isrcValue,
          track_name: track.title,
          artist_name: track.user?.username || 'Unknown',
        })

        if (blacklistResponse.success) {
          blacklistedTracks.value.add(trackKey)
          addTrackToBlacklist({
            id: String(track.id),
            name: track.title,
            artist: track.user?.username || 'Unknown',
          } as any)
          console.log('✅ Track blacklisted in backend:', track.title)

          window.dispatchEvent(new CustomEvent('track-blacklisted', {
            detail: { track, trackKey },
          }))
          localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
        }
      } catch (error) {
        console.error('❌ Error blacklisting track:', error)
      }

      // Update localStorage
      try {
        const unsavedList = Array.from(clientUnsavedTracks.value)
        localStorage.setItem('koel-client-unsaved-tracks', JSON.stringify(unsavedList))
      } catch (error) {
        // Failed to update unsaved tracks in localStorage
      }

      // Trigger SavedTracksScreen refresh
      try {
        window.dispatchEvent(new CustomEvent('track-saved', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-saved-timestamp', Date.now().toString())
      } catch (error) {
        // Event dispatch failed, not critical
      }
    } catch (error: any) {
      savedTracks.value.delete(trackKey)
    }
  }
}

// Blacklist track function
const blacklistTrack = async (track: SoundCloudTrack) => {
  const trackKey = getTrackKey(track)
  const trackIdentifier = track.id || trackKey

  if (isTrackBlacklisted(track)) {
    // UNBAN TRACK - Update UI immediately
    blacklistedTracks.value.delete(trackKey)

    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const deleteData = {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
      }
      const params = new URLSearchParams(deleteData)
      const response = await http.delete(`music-preferences/blacklist-track?${params}`)

      if (!response.success) {
        blacklistedTracks.value.add(trackKey)
      } else {
        window.dispatchEvent(new CustomEvent('track-unblacklisted', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      }
    } catch (error: any) {
      blacklistedTracks.value.add(trackKey)
    }
  } else {
    // Block track - show processing state
    processingTrack.value = track.id

    try {
      const isrcValue = `soundcloud:${track.id}` || `generated-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`

      const response = await http.post('music-preferences/blacklist-track', {
        isrc: isrcValue,
        track_name: track.title,
        artist_name: track.user?.username || 'Unknown',
      })

      if (response.success) {
        blacklistedTracks.value.add(trackKey)
        addTrackToBlacklist({
          id: String(track.id),
          name: track.title,
          artist: track.user?.username || 'Unknown',
        } as any)

        window.dispatchEvent(new CustomEvent('track-blacklisted', {
          detail: { track, trackKey },
        }))
        localStorage.setItem('track-blacklisted-timestamp', Date.now().toString())
      } else {
        throw new Error(response.error || 'Failed to blacklist track')
      }
    } catch (error: any) {
      // Failed to blacklist track
    } finally {
      processingTrack.value = null
    }
  }
}

// Mark track as listened
const markTrackAsListened = async (track: SoundCloudTrack) => {
  const trackKey = getTrackKey(track)

  // Mark as listened (optimistic)
  listenedTracks.value.add(trackKey)
  // Reassign to trigger Vue reactivity for Set mutations
  listenedTracks.value = new Set(listenedTracks.value)

  // Persist listened state
  try {
    await http.post('music-preferences/listened-track', {
      track_key: trackKey,
      track_name: track.title,
      artist_name: track.user?.username || 'Unknown',
      spotify_id: null,
      isrc: `soundcloud:${track.id}`,
    })
  } catch (e) {
    // If unauthenticated, store locally
    try {
      const keys = Array.from(listenedTracks.value)
      localStorage.setItem('koel-listened-tracks', JSON.stringify(keys))
    } catch {}
  }
}

// Load user preferences
const loadUserPreferences = async () => {
  try {
    // Load blacklisted tracks
    const blacklistedTracksResponse = await http.get('music-preferences/blacklisted-tracks')
    if (blacklistedTracksResponse.success && blacklistedTracksResponse.data) {
      blacklistedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        blacklistedTracks.value.add(trackKey)
      })
    }

    // Load saved tracks
    const savedTracksResponse = await http.get('music-preferences/saved-tracks')
    if (savedTracksResponse.success && savedTracksResponse.data) {
      savedTracksResponse.data.forEach((track: any) => {
        const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
        savedTracks.value.add(trackKey)
      })
    }

    // Load client unsaved tracks from localStorage
    try {
      const stored = localStorage.getItem('koel-client-unsaved-tracks')
      if (stored) {
        const unsavedList = JSON.parse(stored)
        clientUnsavedTracks.value = new Set(unsavedList)
      }
    } catch (error) {
      // Failed to load client unsaved tracks
    }

    // Load listened tracks from server (fall back to localStorage if unauthenticated)
    try {
      const resp: any = await http.get('music-preferences/listened-tracks')
      if (resp?.success && Array.isArray(resp.data)) {
        listenedTracks.value = new Set(resp.data as string[])
      }
    } catch (e) {
      // Fallback to localStorage per device
      try {
        const stored = localStorage.getItem('koel-listened-tracks')
        if (stored) {
          const keys: string[] = JSON.parse(stored)
          listenedTracks.value = new Set(keys)
        }
      } catch {}
    }
  } catch (error) {
    // Could not load user preferences (user may not be logged in)
  }
}

// Load banned artists from localStorage (shared with Similar Artists and Recommendations)
const loadBannedArtists = () => {
  try {
    const stored = localStorage.getItem('koel-banned-artists')
    if (stored) {
      const bannedList = JSON.parse(stored)
      bannedArtists.value = new Set(bannedList)
      // console.log('SoundCloud: Loaded banned artists:', bannedList)
    }
  } catch (error) {
    console.warn('SoundCloud: Failed to load banned artists from localStorage:', error)
  }
}

// Save banned artists to localStorage
// Filter out tracks from banned artists (only applied to NEW search results)
const filterBannedArtists = (trackList: SoundCloudTrack[]) => {
  return trackList.filter(track => !bannedArtists.value.has(track.user?.username || ''))
}

// Remove duplicate tracks by id when appending results
const dedupeTracks = (incoming: SoundCloudTrack[]) => {
  const existingIds = new Set(originalTracks.value.map(track => track.id))
  return incoming.filter(track => {
    if (!track || track.id == null) {
      return false
    }
    if (existingIds.has(track.id)) {
      return false
    }
    existingIds.add(track.id)
    return true
  })
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedGenre.value = ''
  searchTags.value = ''
  bpmFrom.value = 95
  bpmTo.value = 172
  bpmFromInput.value = '95'
  bpmToInput.value = '172'
  timePeriod.value = ''
  artistFilter.value = ''
  minPlays.value = undefined
  maxPlays.value = undefined
  minPlaysFormatted.value = ''
  maxPlaysFormatted.value = ''
  contentType.value = 'tracks'
  likesRatioFilter.value = 'newest'
  showLikesRatioDropdown.value = false
  displayLimit.value = 20
  tracks.value = []
  originalTracks.value = []
  searched.value = false
  error.value = ''
  searchStats.value = null
  hasMoreResults.value = false
  serverHasMore.value = false
  lastSearchFilters.value = null
  loadingMore.value = false
}

const closePlayer = () => {
  soundcloudPlayerStore.hide()
}

const { onRouteChanged } = useRouter()

// Animation state management
const initialLoadComplete = ref(false)

// Template ref for SoundCloud table
const soundcloudTable = ref<{ closeInlinePlayer: () => void } | null>(null)

// Set up event bus listeners when component is mounted
onMounted(() => {
  // Listen for skip events from the footer player
  eventBus.on('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.on('SOUNDCLOUD_SKIP_NEXT', skipToNext)

  // Load banned artists and global blacklist items
  loadBannedArtists()
  loadBlacklistedItems()
  loadUserPreferences()
})

// Close SoundCloud player when navigating away from this screen
onRouteChanged(route => {
  if (route.screen !== 'SoundCloud') {
    // Close any inline players first
    if (soundcloudTable.value?.closeInlinePlayer) {
      soundcloudTable.value.closeInlinePlayer()
    }
    // Then hide the global player
    soundcloudPlayerStore.hide()
  }
})

// Clean up player and event listeners when component is unmounted (user navigates away)
onBeforeUnmount(() => {
  closePlayer()

  // Remove event listeners
  eventBus.off('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.off('SOUNDCLOUD_SKIP_NEXT', skipToNext)
})
</script>

<style scoped>
/* Custom scrollbar for select dropdown */
select::-webkit-scrollbar {
  width: 8px;
}

select::-webkit-scrollbar-track {
  background: transparent;
}

select::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}

select::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.4);
}

/* White BPM slider styling */
.bpm-slider-white :deep(.track-background) {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.bpm-slider-white :deep(.track-fill) {
  background: rgba(255, 255, 255, 0.6);
}

.bpm-slider-white :deep(.range-input::-webkit-slider-thumb) {
  background: white;
  border: 2px solid rgba(255, 255, 255, 0.8);
  margin-top: 0px;
}

.bpm-slider-white :deep(.range-input::-webkit-slider-thumb:hover) {
  background: rgba(255, 255, 255, 0.9);
  border-color: white;
  box-shadow: 0 4px 8px rgba(255, 255, 255, 0.3);
}

.bmp-slider-white :deep(.range-input::-moz-range-thumb) {
  background: white;
  border: 2px solid rgba(255, 255, 255, 0.8);
  margin-top: 0px;
}

/* Ensure SoundCloud iframe is fully interactive */
iframe {
  pointer-events: auto !important;
  user-select: auto !important;
  -webkit-user-select: auto !important;
  -moz-user-select: auto !important;
  -ms-user-select: auto !important;
}

/* Fix any potential overlay issues */
.soundcloud-player-container {
  position: relative;
  z-index: 1;
  pointer-events: auto !important;
}

.soundcloud-player-container iframe {
  position: relative;
  z-index: 2;
}

/* Time Period dropdown styling - match sort dropdown exactly */
.time-period-select {
  background-color: rgba(47, 47, 47, 255) !important;
  color: rgba(255, 255, 255, 0.8) !important; /* text-white/80 */
}

.time-period-select:hover {
  background-color: rgba(255, 255, 255, 0.2) !important; /* hover:bg-white/20 */
}

.time-period-select:focus {
  background-color: rgba(255, 255, 255, 0.2) !important;
  outline: none !important;
}

/* Style dropdown options with dark grey background and white text */
.time-period-select option {
  background-color: rgb(67, 67, 67) !important;
  color: white !important;
}

.time-period-select option:checked {
  background-color: rgb(67, 67, 67) !important;
  color: white !important;
}

.time-period-select option:hover,
.time-period-select option:focus,
.time-period-select option:active {
  background-color: rgba(255, 255, 255, 0.1) !important;
  color: white !important;
}
</style>
