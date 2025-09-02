<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader>SoundCloud Search</ScreenHeader>
    </template>

    <div class="p-6 space-y-6">
      <!-- Compact Search Controls -->
      <div class="bg-white/5 rounded-lg p-4">
        <!-- Top Row: Genres and Tags -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <!-- Genres Dropdown -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Genre</label>
            <select
              v-model="selectedGenre"
              class="w-full p-2 bg-white/10 rounded focus:border-k-accent text-white scrollbar-thin scrollbar-thumb-white/20 scrollbar-track-transparent"
              style="scrollbar-color: rgba(255,255,255,0.3) transparent;"
            >
              <option value="" class="bg-gray-800">All Genres</option>
              <option v-for="genre in genres" :key="genre" :value="genre" class="bg-gray-800">{{ genre }}</option>
            </select>
          </div>

          <!-- Tags Input -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Tags</label>
            <input
              v-model="searchTags"
              type="text"
              class="w-full p-2 bg-white/10 rounded  focus:border-k-accent text-white"
              placeholder="electronic, remix, house..."
            />
            <div class="mt-1 flex flex-wrap gap-1">
              <button
                v-for="tag in popularTags"
                :key="tag"
                @click="addTag(tag)"
                class="px-2 py-0.5 bg-white/10 hover:bg-k-accent/20 rounded text-xs text-white/70 hover:text-k-accent transition"
              >
                {{ tag }}
              </button>
            </div>
          </div>
        </div>

        <!-- Second Row: Release Date and BPM Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
          <!-- Time Period -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">Time Period</label>
            <select
              v-model="timePeriod"
              class="w-full p-2 bg-white/10 rounded  focus:border-k-accent text-white"
            >
              <option value="" class="bg-gray-800">All Time</option>
              <option value="1d" class="bg-gray-800">Last Day</option>
              <option value="1w" class="bg-gray-800">Last Week</option>
              <option value="1m" class="bg-gray-800">Last Month</option>
              <option value="3m" class="bg-gray-800">Last 3 Months</option>
              <option value="6m" class="bg-gray-800">Last 6 Months</option>
              <option value="1y" class="bg-gray-800">Last Year</option>
            </select>
          </div>

          <!-- BPM Range -->
          <div>
            <label class="block text-sm font-medium mb-2 text-white/80">
              BPM Range: {{ bpmFrom }} - {{ bpmTo }}
            </label>
            <DualRangeSlider
              :min="60"
              :max="200"
              :from="bpmFrom"
              :to="bpmTo"
              @update:from="bpmFrom = $event"
              @update:to="bpmTo = $event"
            />
          </div>
        </div>

        <!-- Search Button -->
        <div class="flex justify-center">
          <button
            @click="search"
            :disabled="loading"
            class="px-8 py-3 bg-k-accent hover:bg-k-accent/80 disabled:opacity-50 rounded-lg font-medium transition flex items-center gap-2"
          >
            <Icon v-if="loading" :icon="faSpinner" spin />
            {{ loading ? 'Searching...' : 'Search SoundCloud' }}
          </button>
        </div>
      </div>

      <!-- Results Table -->
      <div v-if="tracks.length > 0" class="bg-white/5 rounded-lg overflow-hidden">
        <div class="p-4 border-b border-white/10">
          <h3 class="font-medium text-white">Search Results ({{ tracks.length }})</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-white/10 text-left">
                <th class="p-3 text-sm font-medium text-white/80">Title</th>
                <th class="p-3 text-sm font-medium text-white/80">Artist</th>
                <th class="p-3 text-sm font-medium text-white/80">Genre</th>
                <th class="p-3 text-sm font-medium text-white/80">Date Added</th>
                <th class="p-3 text-sm font-medium text-white/80">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="track in tracks"
                :key="track.id"
                class="border-b border-white/5 hover:bg-white/5 transition"
              >
                <td class="p-3">
                  <div class="font-medium text-white">{{ track.title }}</div>
                  <div v-if="track.bpm" class="text-xs text-white/60">{{ track.bpm }} BPM</div>
                </td>
                <td class="p-3 text-white/80">{{ track.user.username }}</td>
                <td class="p-3">
                  <span
                    v-if="track.genre"
                    class="px-2 py-1 bg-k-accent/20 text-k-accent rounded text-xs"
                  >
                    {{ track.genre }}
                  </span>
                  <span v-else class="text-white/40">-</span>
                </td>
                <td class="p-3 text-white/80">{{ formatDate(track.created_at) }}</td>
                <td class="p-3">
                  <button
                    @click="playTrack(track)"
                    class="px-3 py-1.5 bg-k-accent bg-gray-300 hover:bg-gray-400 hover:bg-k-accent/80 rounded text-sm font-medium transition flex items-center gap-1"
                  >
                    <Icon :icon="faPlay" />
                    Preview
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!searched && !loading" class="text-center p-12">
        <Icon :icon="faSoundcloud" class="text-6xl text-white/40 mb-4" />
        <h3 class="text-xl font-semibold text-white mb-2">Search SoundCloud</h3>
        <p class="text-white/60 max-w-md mx-auto">
          Search tracks with advanced filters like BPM range and release date - features not available on the official SoundCloud website!
        </p>
      </div>

      <!-- No Results -->
      <div v-else-if="searched && tracks.length === 0 && !loading" class="text-center p-12">
        <Icon :icon="faSearch" class="text-4xl text-white/40 mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">No Results Found</h3>
        <p class="text-white/60">Try adjusting your search criteria or filters.</p>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="text-center p-12">
        <Icon :icon="faSpinner" spin class="text-4xl text-k-accent mb-4" />
        <h3 class="text-lg font-semibold text-white mb-2">Searching SoundCloud...</h3>
      </div>

      <!-- Error State -->
      <div v-if="error" class="bg-red-500/20 border border-red-500/40 rounded-lg p-4">
        <p class="text-red-200">{{ error }}</p>
      </div>
    </div>

    <!-- SoundCloud Player Modal -->
    <teleport to="body">
      <div
        v-if="showPlayer"
        class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
        @click="closePlayer"
      >
        <div
          class="bg-k-bg-secondary rounded-lg p-6 max-w-4xl w-full max-h-[80vh] overflow-auto"
          @click.stop
        >
          <div class="flex justify-between items-center mb-4">
            <div>
              <h3 class="text-xl font-semibold text-white">{{ currentTrack?.title }}</h3>
              <p class="text-white/60">by {{ currentTrack?.user.username }}</p>
            </div>
            <button
              @click="closePlayer"
              class="p-2 hover:bg-white/10 rounded-lg transition"
            >
              <Icon :icon="faTimes" class="text-white" />
            </button>
          </div>
          <iframe
            v-if="embedUrl"
            :src="embedUrl"
            width="100%"
            height="166"
            frameborder="no"
            scrolling="no"
            allow="autoplay"
            class="rounded"
          />
        </div>
      </div>
    </teleport>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faPlay, faSearch, faSpinner, faTimes } from '@fortawesome/free-solid-svg-icons'
import { faSoundcloud } from '@fortawesome/free-brands-svg-icons'
import { ref } from 'vue'
import { http } from '@/services/http'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import DualRangeSlider from '@/components/ui/DualRangeSlider.vue'

interface SoundCloudTrack {
  id: number
  title: string
  duration: number
  created_at: string
  genre: string
  tag_list: string
  bpm?: number
  playback_count: number
  favoritings_count: number
  user: {
    username: string
    followers_count: number
  }
}

interface SoundCloudResponse {
  collection: SoundCloudTrack[]
}

// Search form state
const selectedGenre = ref('')
const searchTags = ref('')
const bpmFrom = ref<number>(60)
const bpmTo = ref<number>(200)
const timePeriod = ref('')

// Results state
const tracks = ref<SoundCloudTrack[]>([])
const loading = ref(false)
const searched = ref(false)
const error = ref('')

// Player state
const showPlayer = ref(false)
const currentTrack = ref<SoundCloudTrack | null>(null)
const embedUrl = ref('')

// Available genres
const genres = [
  'Alternative Rock', 'Ambient', 'Classical', 'Country', 'Dance & EDM',
  'Dancehall', 'Deep House', 'Disco', 'Drum & Bass', 'Dubstep',
  'Electronic', 'Folk & Singer-Songwriter', 'Hip-hop & Rap', 'House',
  'Indie', 'Jazz & Blues', 'Latin', 'Metal', 'Piano', 'Pop',
  'R&B & Soul', 'Reggae', 'Reggaeton', 'Rock', 'Soundtrack',
  'Speech', 'Techno', 'Trance', 'Trap', 'Triphop', 'World'
]

// Popular tags that users commonly search for
const popularTags = [
  'remix', 'vocal', 'instrumental', 'chill', 'upbeat', 'melodic', 'experimental', 'vintage', 'modern', 'dark'
]

const addTag = (tag: string) => {
  if (searchTags.value) {
    // Add comma if there are existing tags
    if (!searchTags.value.endsWith(', ')) {
      searchTags.value += ', '
    }
    searchTags.value += tag
  } else {
    searchTags.value = tag
  }
}

const ensureBpmOrder = () => {
  if (bpmFrom.value > bpmTo.value) {
    const temp = bpmFrom.value
    bpmFrom.value = bpmTo.value
    bpmTo.value = temp
  }
}

const formatDate = (dateString: string): string => {
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  } catch {
    return 'Unknown'
  }
}

const search = async () => {
  if (!selectedGenre.value && !searchTags.value) {
    error.value = 'Please select a genre or add tags to search.'
    return
  }

  loading.value = true
  error.value = ''
  tracks.value = []

  try {
    const params: Record<string, any> = {
      limit: 20,
      _timestamp: Date.now() // Force fresh request
    }

    console.log('🎵 SoundCloud Search - Building params:', {
      selectedGenre: selectedGenre.value,
      searchTags: searchTags.value,
      bpmFrom: bpmFrom.value,
      bpmTo: bpmTo.value,
      timePeriod: timePeriod.value
    })

    if (selectedGenre.value) {
      params.genres = selectedGenre.value
    }

    if (searchTags.value.trim()) {
      params.tags = searchTags.value.trim()
    }

    if (bpmFrom.value !== 60) {
      params.bpm_from = bpmFrom.value
    }

    if (bpmTo.value !== 200) {
      params.bpm_to = bpmTo.value
    }

    if (timePeriod.value) {
      const now = new Date()
      let fromDate: Date

      switch (timePeriod.value) {
        case '1d':
          fromDate = new Date(now.getTime() - 24 * 60 * 60 * 1000)
          break
        case '1w':
          fromDate = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
          break
        case '1m':
          fromDate = new Date(now.setMonth(now.getMonth() - 1))
          break
        case '3m':
          fromDate = new Date(now.setMonth(now.getMonth() - 3))
          break
        case '6m':
          fromDate = new Date(now.setMonth(now.getMonth() - 6))
          break
        case '1y':
          fromDate = new Date(now.setFullYear(now.getFullYear() - 1))
          break
        default:
          fromDate = new Date(now.getTime() - 24 * 60 * 60 * 1000)
      }

      params.created_from = fromDate.toISOString().split('T')[0]
    }

    console.log('🎵 SoundCloud Search - Final params:', params)

    const response = await http.get<SoundCloudResponse>('soundcloud/search', params)
    
    console.log('🎵 SoundCloud Search - Response:', {
      hasCollection: !!response.collection,
      trackCount: response.collection?.length || 0,
      firstTrack: response.collection?.[0]?.title || 'none'
    })

    tracks.value = response.collection || []
    searched.value = true

    if (tracks.value.length === 0) {
      error.value = 'No tracks found. Try different search criteria.'
    }
  } catch (err: any) {
    console.error('🎵 SoundCloud Search - Error:', err)
    error.value = err.response?.data?.error || 'Failed to search SoundCloud. Please try again.'
    tracks.value = []
  } finally {
    loading.value = false
  }
}

const playTrack = async (track: SoundCloudTrack) => {
  try {
    const response = await http.post<{ embed_url: string }>('soundcloud/embed', {
      track_id: track.id,
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true,
      visual: true
    })

    currentTrack.value = track
    embedUrl.value = response.embed_url
    showPlayer.value = true
  } catch (err) {
    error.value = 'Failed to load SoundCloud player.'
  }
}

const closePlayer = () => {
  showPlayer.value = false
  currentTrack.value = null
  embedUrl.value = ''
}
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

/* Range slider styling */
.slider {
  -webkit-appearance: none;
  appearance: none;
  background: rgba(255, 255, 255, 0.1);
  outline: none;
  border-radius: 4px;
}

.slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  background: #ff6600;
  border-radius: 50%;
  border: 2px solid white;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
}

.slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  background: #ff6600;
  border-radius: 50%;
  border: 2px solid white;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
  border: none;
}

.slider::-moz-range-track {
  background: rgba(255, 255, 255, 0.1);
  height: 8px;
  border-radius: 4px;
}
</style>