<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="collapsed">
        SoundCloud Related Tracks
        <template #meta>
          <span v-if="seedTrack" class="text-text-secondary">
            Related to: {{ seedTrack.title }} by {{ seedTrack.artist }}
          </span>
          <span v-else-if="searchQuery" class="text-text-secondary">
            Search results for: "{{ searchQuery }}"
          </span>
        </template>
      </ScreenHeader>
    </template>

    <div class="p-6">
      <!-- Welcome Message - Only show when no results and no search -->
      <div v-if="!showingSeedResults && !tracks.length && !seedSearchQuery.trim() && !error" class="max-w-2xl mx-auto text-center mb-8">
        <Icon :icon="faMusic" class="text-6xl mb-4 text-k-text-secondary" />
        <h2 class="text-2xl font-bold mb-2">SoundCloud Related Tracks</h2>
        <p class="text-k-text-secondary">
          Search for a seed track to find related tracks, or click "Related Tracks" while playing a SoundCloud song.
        </p>
      </div>

      <!-- Search Box for Seed Track - Always visible -->
      <div class="max-w-2xl mx-auto mb-8">
        <div class="bg-k-bg-secondary rounded-lg p-6">
          <label class="block text-sm font-medium mb-3">Search for seed track</label>
          <form @submit.prevent="performSeedSearch" class="space-y-4">
            <div class="flex gap-3">
              <input
                v-model="seedSearchQuery"
                type="text"
                placeholder="Enter track name or artist to find seed track..."
                class="flex-1 px-4 py-3 bg-k-bg-primary border border-k-border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-k-accent"
                @input="onSeedSearchInput"
              />
              <button
                type="submit"
                :disabled="!seedSearchQuery.trim() || loading"
                class="px-6 py-3 bg-k-accent hover:bg-k-accent/80 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium transition-colors"
              >
                <Icon v-if="loading && showingSeedResults" :icon="faSpinner" spin class="mr-2" />
                Search
              </button>
            </div>
            <p class="text-xs text-k-text-secondary">
              {{ showingSeedResults ? 'Search for different seed track or select one below:' : 'First, search for a track to use as your seed. Then we\'ll find related tracks.' }}
            </p>
          </form>
        </div>
      </div>

      <!-- Error state -->
      <div v-if="error" class="text-center py-6 mb-6">
        <div class="text-red-400 mb-4">{{ error }}</div>
        <button 
          @click="retry" 
          class="px-4 py-2 bg-k-accent hover:bg-k-accent/80 rounded text-white transition-colors"
        >
          Try Again
        </button>
      </div>

      <!-- Seed Search Results (when searching for seed track) -->
      <div v-if="showingSeedResults">
        <div class="flex items-center justify-between mb-6">
          <div class="text-k-text-secondary">
            <Icon v-if="loading" :icon="faSpinner" spin class="mr-2" />
            {{ loading ? 'Searching...' : `Found ${seedSearchResults.length} seed tracks - click one to find related tracks` }}
          </div>
          <button 
            @click="clearResults" 
            class="px-4 py-2 bg-k-bg-secondary hover:bg-k-bg-secondary/80 rounded text-sm transition-colors"
          >
            New Search
          </button>
        </div>
        
        <SoundCloudTrackTable 
          v-if="!loading && seedSearchResults.length > 0"
          :tracks="seedSearchResults"
          @play="playTrack"
          @relatedTracks="selectSeedTrack"
        />
        
        <div v-else-if="loading" class="flex items-center justify-center py-12">
          <Icon :icon="faSpinner" spin class="text-2xl mr-3" />
          <span class="text-lg">Searching for seed tracks...</span>
        </div>
      </div>

      <!-- Related Tracks Results -->
      <div v-if="!showingSeedResults && tracks.length > 0">
        <div class="flex items-center justify-between mb-6">
          <div class="text-k-text-secondary">
            Found {{ tracks.length }} related tracks
          </div>
          <button 
            @click="clearResults" 
            class="px-4 py-2 bg-k-bg-secondary hover:bg-k-bg-secondary/80 rounded text-sm transition-colors"
          >
            New Search
          </button>
        </div>
        
        <SoundCloudTrackTable 
          :tracks="tracks"
          @play="playTrackAndFindRelated"
          @relatedTracks="findRelatedForTrack"
        />
      </div>

      <!-- Loading state for related tracks -->
      <div v-if="!showingSeedResults && loading && tracks.length === 0" class="flex items-center justify-center py-12">
        <Icon :icon="faSpinner" spin class="text-2xl mr-3" />
        <span class="text-lg">Loading related tracks...</span>
      </div>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { faMusic, faSpinner } from '@fortawesome/free-solid-svg-icons'
import { ref, onMounted, onUnmounted } from 'vue'
import { debounce } from 'lodash'
import { eventBus } from '@/utils/eventBus'
import { soundcloudService, type SoundCloudTrack } from '@/services/soundcloudService'
import { soundcloudPlayerStore } from '@/stores/soundcloudPlayerStore'
import { useRouter } from '@/composables/useRouter'

import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'
import SoundCloudTrackTable from '@/components/ui/soundcloud/SoundCloudTrackTable.vue'

const { isCurrentScreen } = useRouter()

const loading = ref(false)
const error = ref('')
const tracks = ref<SoundCloudTrack[]>([])
const seedTrack = ref<{ title: string; artist: string; trackUrn: string } | null>(null)
const searchQuery = ref('')
const seedSearchQuery = ref('')
const currentData = ref<any>(null)
const seedSearchResults = ref<SoundCloudTrack[]>([])
const showingSeedResults = ref(false)

const loadRelatedTracks = async (trackUrn: string) => {
  loading.value = true
  error.value = ''
  
  try {
    console.log('🎵 Loading related tracks for URN:', trackUrn)
    const relatedTracks = await soundcloudService.getRelatedTracks(trackUrn)
    tracks.value = relatedTracks
    console.log('🎵 Loaded', relatedTracks.length, 'related tracks')
  } catch (err: any) {
    error.value = `Failed to load related tracks: ${err.message || 'Unknown error'}`
    console.error('🎵 Related tracks error:', err)
  } finally {
    loading.value = false
  }
}

const searchTracks = async (query: string) => {
  loading.value = true
  error.value = ''
  
  try {
    console.log('🎵 Searching for seed tracks:', query)
    const searchResults = await soundcloudService.search({
      searchQuery: query,
      limit: 50
    })
    tracks.value = searchResults
    console.log('🎵 Found', searchResults.length, 'search results')
  } catch (err: any) {
    error.value = `Failed to search tracks: ${err.message || 'Unknown error'}`
    console.error('🎵 Search error:', err)
  } finally {
    loading.value = false
  }
}

const searchSeedTracks = async (query: string) => {
  loading.value = true
  error.value = ''
  showingSeedResults.value = true
  
  try {
    console.log('🎵 Searching for seed tracks:', query)
    const searchResults = await soundcloudService.search({
      searchQuery: query,
      limit: 50
    })
    seedSearchResults.value = searchResults
    console.log('🎵 Found', searchResults.length, 'seed track candidates')
  } catch (err: any) {
    error.value = `Failed to search seed tracks: ${err.message || 'Unknown error'}`
    console.error('🎵 Seed search error:', err)
  } finally {
    loading.value = false
  }
}

const selectSeedTrack = async (track: SoundCloudTrack) => {
  console.log('🎵 Selected seed track:', track.title, 'by', track.user.username)
  
  // Set the seed track info
  seedTrack.value = {
    title: track.title,
    artist: track.user.username,
    trackUrn: `soundcloud:tracks:${track.id}`
  }
  
  // Clear seed search results and show related tracks loading state
  showingSeedResults.value = false
  seedSearchResults.value = []
  seedSearchQuery.value = ''
  
  // Load related tracks for this seed
  await loadRelatedTracks(`soundcloud:tracks:${track.id}`)
}

const playTrack = async (track: SoundCloudTrack) => {
  try {
    console.log('🎵 Related Tracks Screen - Playing track:', track.title)
    
    // Show player immediately with loading state
    soundcloudPlayerStore.show(track, '')
    
    // Update navigation state based on track position
    updateNavigationState(track)
    
    console.log('🎵 Loading SoundCloud player for track:', track.title)
    
    const embedUrl = await soundcloudService.getEmbedUrl(track.id, {
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true
    })

    console.log('🎵 Got embed URL:', embedUrl)
    soundcloudPlayerStore.setEmbedUrl(embedUrl)
    console.log('🎵 SoundCloud player loaded successfully')
  } catch (err: any) {
    console.error('🎵 Failed to load SoundCloud player:', err)
    error.value = `Failed to load SoundCloud player: ${err.message || 'Unknown error'}`
    soundcloudPlayerStore.hide() // Close player on error
  }
}

const playTrackAndFindRelated = async (track: SoundCloudTrack) => {
  console.log('🎵 Playing track and finding related tracks for:', track.title)
  
  // First, play the track
  await playTrack(track)
  
  // Then, find related tracks for this song
  await findRelatedForTrack(track)
}

const findRelatedForTrack = async (track: SoundCloudTrack) => {
  console.log('🎵 Finding related tracks for:', track.title)
  
  // Set the seed track info
  seedTrack.value = {
    title: track.title,
    artist: track.user.username,
    trackUrn: `soundcloud:tracks:${track.id}`
  }
  
  // Clear seed search results and show related tracks loading state
  showingSeedResults.value = false
  seedSearchResults.value = []
  seedSearchQuery.value = ''
  
  // Load related tracks for this seed
  await loadRelatedTracks(`soundcloud:tracks:${track.id}`)
}

const updateNavigationState = (track: SoundCloudTrack) => {
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  const currentIndex = currentTracksList.findIndex(t => t.id === track.id)
  const canSkipPrevious = currentIndex > 0
  const canSkipNext = currentIndex >= 0 && currentIndex < currentTracksList.length - 1
  
  soundcloudPlayerStore.setNavigationState(canSkipPrevious, canSkipNext)
  console.log('🎵 Navigation state updated:', { canSkipPrevious, canSkipNext, currentIndex, totalTracks: currentTracksList.length })
}

const getCurrentTrackIndex = () => {
  const currentTrack = soundcloudPlayerStore.state.currentTrack
  if (!currentTrack) return -1
  
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  return currentTracksList.findIndex(t => t.id === currentTrack.id)
}

const skipToPrevious = () => {
  const currentIndex = getCurrentTrackIndex()
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  
  if (currentIndex > 0) {
    const previousTrack = currentTracksList[currentIndex - 1]
    console.log('🎵 Skipping to previous track:', previousTrack.title)
    if (showingSeedResults.value) {
      selectSeedTrack(previousTrack)
    } else {
      playTrack(previousTrack)
    }
  }
}

const skipToNext = () => {
  const currentIndex = getCurrentTrackIndex()
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  
  if (currentIndex >= 0 && currentIndex < currentTracksList.length - 1) {
    const nextTrack = currentTracksList[currentIndex + 1]
    console.log('🎵 Skipping to next track:', nextTrack.title)
    if (showingSeedResults.value) {
      selectSeedTrack(nextTrack)
    } else {
      playTrack(nextTrack)
    }
  }
}

const retry = () => {
  if (currentData.value) {
    handleScreenLoad(currentData.value)
  }
}

const handleScreenLoad = (data: any) => {
  currentData.value = data
  
  if (data.type === 'related') {
    // Load related tracks for a specific track
    seedTrack.value = {
      title: data.trackTitle,
      artist: data.artist,
      trackUrn: data.trackUrn
    }
    searchQuery.value = ''
    loadRelatedTracks(data.trackUrn)
  } else if (data.type === 'search') {
    // Search for seed tracks
    seedTrack.value = null
    searchQuery.value = data.searchQuery
    searchTracks(data.searchQuery)
  }
}

const clearResults = () => {
  tracks.value = []
  seedTrack.value = null
  searchQuery.value = ''
  seedSearchQuery.value = ''
  seedSearchResults.value = []
  showingSeedResults.value = false
  error.value = ''
  currentData.value = null
}

const performSearch = () => {
  if (searchQuery.value.trim()) {
    searchTracks(searchQuery.value.trim())
  }
}

const performSeedSearch = () => {
  if (seedSearchQuery.value.trim()) {
    searchSeedTracks(seedSearchQuery.value.trim())
  }
}

const onSearchInput = debounce(() => {
  // Auto-search as user types (optional - can be removed if not desired)
  // performSearch()
}, 500)

const onSeedSearchInput = () => {
  // No auto-search - user must click Search button
}

onMounted(() => {
  // Listen for related tracks data from other screens
  eventBus.on('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)
  
  // Listen for skip events from the SoundCloud player
  eventBus.on('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.on('SOUNDCLOUD_SKIP_NEXT', skipToNext)
})

onUnmounted(() => {
  eventBus.off('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)
  eventBus.off('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.off('SOUNDCLOUD_SKIP_NEXT', skipToNext)
})
</script>