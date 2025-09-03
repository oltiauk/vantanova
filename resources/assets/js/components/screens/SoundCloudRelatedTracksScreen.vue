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
        <h2 class="text-2xl font-bold mb-2">SoundCloud Related Tracks</h2>
        <p class="text-k-text-secondary">
          Search for a seed track to find related tracks, or click "Related Tracks" while playing a SoundCloud song.
        </p>
      </div>

      <!-- Search Box for Seed Track - Always visible -->
      <div class="seed-selection mb-8">
        <!-- Search Container -->
        <div class="search-container mb-6">
          <div class="rounded-lg p-4">
            <div class="max-w-4xl mx-auto">
              <div ref="searchContainer" class="relative">
                <!-- Search Icon -->
                <div class="absolute inset-y-0 left-0 flex items-center pointer-events-none z-20 pl-4">
                  <Icon :icon="faSearch" class="w-5 h-5 text-white/40" />
                </div>

                <input
                  v-model="seedSearchQuery"
                  type="text"
                  class="w-full py-3 pl-12 pr-24 bg-white/10 rounded-lg focus:outline-none text-white text-lg"
                  placeholder="Enter track name or artist to find seed track..."
                  @input="onSeedSearchInput"
                  @focus="onSeedSearchFocus"
                  @keypress.enter.prevent="performSeedSearch"
                />

                <!-- Search Button -->
                <button
                  @click="performSeedSearch"
                  :disabled="!seedSearchQuery.trim() || loading"
                  class="absolute inset-y-0 right-0 flex items-center px-4 bg-k-accent hover:bg-k-accent/80 disabled:opacity-50 disabled:cursor-not-allowed rounded-r-lg text-white font-medium transition-colors"
                >
                  <Icon v-if="loading && showingSeedResults" :icon="faSpinner" spin class="w-4 h-4" />
                  <span v-else class="text-sm">Search</span>
                </button>

                <!-- Search Dropdown -->
                <div
                  v-if="showDropdown && seedSearchResults.length > 0 && showingSeedResults"
                  class="absolute z-50 w-full bg-k-bg-secondary border border-k-border rounded-lg mt-1 shadow-xl"
                >
                  <div class="max-h-80 rounded-lg overflow-hidden overflow-y-auto">
                    <div v-for="track in seedSearchResults.slice(0, 10)" :key="`suggestion-${track.id}`">
                      <div
                        class="flex items-center justify-between px-4 py-3 hover:bg-k-bg-tertiary cursor-pointer transition-colors group border-b border-k-border/30 last:border-b-0"
                        @click="selectSeedTrack(track)"
                      >
                        <!-- Track Info -->
                        <div class="flex-1 min-w-0">
                          <div class="font-medium text-k-text-primary group-hover:text-k-accent transition-colors truncate">
                            {{ track.user?.username || 'Unknown Artist' }} - {{ track.title }}
                          </div>
                        </div>

                        <!-- Duration Badge -->
                        <div class="bg-k-bg-primary/30 px-2 py-1 rounded text-k-text-tertiary text-xs font-mono ml-3 flex-shrink-0">
                          {{ formatDuration(track.duration) }}
                        </div>
                      </div>
                    </div>

                    <div v-if="seedSearchResults.length > 10" class="px-4 py-3 text-center text-k-text-tertiary text-sm border-t border-k-border bg-k-bg-tertiary/20">
                      <Icon :icon="faMusic" class="mr-1 opacity-50" />
                      {{ seedSearchResults.length - 10 }} more tracks found
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <p class="text-xs text-k-text-secondary text-center mb-4">
          {{ showingSeedResults ? 'Search for different seed track or select one above' : 'First, search for a track to use as your seed. Then we\'ll find related tracks.' }}
        </p>
      </div>

      <!-- Selected Seed Track Display - Compact -->
      <div v-if="seedTrack" class="selected-seed mb-4 relative z-20">
        <div class="text-sm font-medium mb-2" style="color: #1e6880;">Seed Track:</div>
        <div class="bg-k-bg-secondary/50 border border-k-border rounded-lg px-3 py-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 flex-1 min-w-0">
              <Icon :icon="faCheck" class="w-4 h-4 text-k-accent flex-shrink-0" />
              <span class="text-k-text-primary font-medium truncate">{{ seedTrack.artist }} - {{ seedTrack.title }}</span>
            </div>
            <button
              class="p-1 hover:bg-red-600/20 text-k-text-tertiary hover:text-red-400 rounded transition-colors flex-shrink-0 ml-2"
              title="Clear seed track"
              @click="clearSeedTrack"
            >
              <Icon :icon="faTimes" class="w-4 h-4" />
            </button>
          </div>
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

      <!-- Seed Search Results (when searching for seed track) - Table view below dropdown -->
      <div v-if="showingSeedResults && !loading && seedSearchResults.length > 10">
        <div class="flex items-center justify-between mb-6">
          <div class="text-k-text-secondary">
            Found {{ seedSearchResults.length }} seed tracks - select one from dropdown above or view all below
          </div>
        </div>
        
        <SoundCloudTrackTable 
          :tracks="seedSearchResults"
          @play="playTrack"
          @relatedTracks="selectSeedTrack"
          @banArtist="banArtist"
        />
      </div>

      <!-- Loading state for seed search -->
      <div v-if="showingSeedResults && loading" class="flex items-center justify-center py-12">
        <Icon :icon="faSpinner" spin class="text-2xl mr-3" />
        <span class="text-lg">Searching for seed tracks...</span>
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
          @play="playTrack"
          @pause="pauseTrack"
          @seek="seekTrack"
          @relatedTracks="findRelatedForTrack"
          @banArtist="banArtist"
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
import { faMusic, faSpinner, faSearch, faCheck, faTimes } from '@fortawesome/free-solid-svg-icons'
import { ref, onMounted, onUnmounted } from 'vue'
import { debounce } from 'lodash'
import { eventBus } from '@/utils/eventBus'
import { soundcloudService, type SoundCloudTrack } from '@/services/soundcloudService'
import { useBlacklistFiltering } from '@/composables/useBlacklistFiltering'
import { http } from '@/services/http'
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
const showDropdown = ref(false)
const searchContainer = ref<HTMLElement | null>(null)

// Initialize global blacklist filtering for SoundCloud
const { 
  filterSoundCloudTracks, 
  addSoundCloudTrackToBlacklist,
  addArtistToBlacklist,
  loadBlacklistedItems 
} = useBlacklistFiltering()

// Local banned artists tracking (for Similar Artists compatibility)
const bannedArtists = ref(new Set<string>()) // Store artist names for SoundCloud tracks

const formatDuration = (duration?: number): string => {
  if (!duration) {
    return '0:00'
  }
  // Duration is in milliseconds for SoundCloud
  const minutes = Math.floor(duration / 60000)
  const seconds = Math.floor((duration % 60000) / 1000)
  return `${minutes}:${seconds.toString().padStart(2, '0')}`
}

const loadRelatedTracks = async (trackUrn: string) => {
  loading.value = true
  error.value = ''
  
  try {
    console.log('🎵 Loading related tracks for URN:', trackUrn)
    const relatedTracks = await soundcloudService.getRelatedTracks(trackUrn)
    
    // Apply global blacklist filtering (tracks + artists)
    const globalFiltered = filterSoundCloudTracks(relatedTracks)
    
    // Apply local banned artists filtering (for Similar Artists compatibility)
    const localFiltered = filterBannedArtists(globalFiltered)
    
    tracks.value = localFiltered
    console.log('🎵 Loaded', tracks.value.length, 'related tracks (after filtering blacklisted tracks/artists)')
    console.log(`🚫 Filtered out ${relatedTracks.length - tracks.value.length} blacklisted items`)
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
  showDropdown.value = true
  
  try {
    console.log('🎵 Searching for seed tracks:', query)
    const searchResults = await soundcloudService.search({
      searchQuery: query,
      limit: 50
    })
    
    // For seed track selection: filter blacklisted tracks but allow blacklisted artists
    // (same logic as SeedTrackSelection - allow tracks by blacklisted artists to be seed tracks)
    const filteredResults = searchResults.filter(track => {
      // Apply local banned artists filter (for Similar Artists compatibility)
      const artistName = track.user?.username
      if (artistName && bannedArtists.value.has(artistName)) {
        return false
      }
      return true
    })
    
    seedSearchResults.value = filteredResults
    console.log('🎵 Found', seedSearchResults.value.length, 'seed track candidates (after filtering)')
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
  showDropdown.value = false
  seedSearchResults.value = []
  seedSearchQuery.value = ''
  
  // Load related tracks for this seed
  await loadRelatedTracks(`soundcloud:tracks:${track.id}`)
}

const playTrack = async (track: SoundCloudTrack) => {
  try {
    console.log('🎵 [RELATED] Starting playTrack for:', track.title)
    
    // Check if this track is already current and just paused
    const currentTrack = soundcloudPlayerStore.state.currentTrack
    if (currentTrack && currentTrack.id === track.id) {
      console.log('🎵 [RELATED] Resuming current track')
      soundcloudPlayerStore.setPlaying(true)
      return
    }
    
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

const pauseTrack = (track?: SoundCloudTrack) => {
  console.log('🎵 [RELATED] Pausing track:', track?.title || 'current')
  soundcloudPlayerStore.setPlaying(false)
}

const seekTrack = (position: number) => {
  console.log('🎵 [RELATED] Seeking to position:', position + '%')
  // Here you could implement actual seek functionality if needed
  // For now, this is just for UI feedback
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
  showDropdown.value = false
  seedSearchResults.value = []
  seedSearchQuery.value = ''
  
  // Load related tracks for this seed
  await loadRelatedTracks(`soundcloud:tracks:${track.id}`)
  
  // After loading related tracks, if this track is currently playing, update navigation
  const currentTrack = soundcloudPlayerStore.state.currentTrack
  if (currentTrack && currentTrack.id === track.id) {
    updateNavigationState(track)
  }
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
  if (!currentTrack) {
    console.log('🎵 [DEBUG] No current track in store')
    return -1
  }
  
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  const index = currentTracksList.findIndex(t => t.id === currentTrack.id)
  console.log('🎵 [DEBUG] getCurrentTrackIndex:', {
    currentTrackId: currentTrack.id,
    currentTrackTitle: currentTrack.title,
    foundIndex: index,
    totalTracks: currentTracksList.length,
    showingSeedResults: showingSeedResults.value
  })
  return index
}

const skipToPrevious = () => {
  const currentIndex = getCurrentTrackIndex()
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  
  if (currentIndex > 0) {
    const previousTrack = currentTracksList[currentIndex - 1]
    console.log('🎵 Skipping to previous track:', previousTrack.title)
    // Always just play the track, don't select it as seed
    playTrack(previousTrack)
  } else {
    console.log('🎵 Cannot skip to previous: already at first track')
  }
}

const skipToNext = () => {
  const currentIndex = getCurrentTrackIndex()
  const currentTracksList = showingSeedResults.value ? seedSearchResults.value : tracks.value
  
  if (currentIndex >= 0 && currentIndex < currentTracksList.length - 1) {
    const nextTrack = currentTracksList[currentIndex + 1]
    console.log('🎵 Skipping to next track:', nextTrack.title)
    // Always just play the track, don't select it as seed
    playTrack(nextTrack)
  } else {
    console.log('🎵 Cannot skip to next: already at last track')
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

const clearSeedTrack = () => {
  seedTrack.value = null
  tracks.value = []
  seedSearchQuery.value = ''
  seedSearchResults.value = []
  showingSeedResults.value = false
  showDropdown.value = false
  error.value = ''
  currentData.value = null
}

const clearResults = () => {
  tracks.value = []
  seedTrack.value = null
  searchQuery.value = ''
  seedSearchQuery.value = ''
  seedSearchResults.value = []
  showingSeedResults.value = false
  showDropdown.value = false
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
  // Show dropdown if we have results and user is typing
  if (seedSearchResults.value.length > 0 && seedSearchQuery.value.trim()) {
    showDropdown.value = true
  } else {
    showDropdown.value = false
  }
}

const onSeedSearchFocus = () => {
  // Show dropdown on focus if we have results
  if (seedSearchResults.value.length > 0 && seedSearchQuery.value.trim()) {
    showDropdown.value = true
  }
}

// Load banned artists from localStorage
const loadBannedArtists = () => {
  try {
    const savedBanned = localStorage.getItem('koel-banned-artists')
    if (savedBanned) {
      const bannedArray = JSON.parse(savedBanned)
      // For SoundCloud tracks, we'll use artist names instead of MBIDs
      bannedArtists.value = new Set(bannedArray)
    }
  } catch (error) {
    console.error('Failed to load banned artists:', error)
  }
}

const banArtist = async (track: SoundCloudTrack) => {
  try {
    const artistName = track.user?.username || 'Unknown Artist'
    console.log('🚫 Banning SoundCloud artist globally:', artistName)
    
    // Add to local banned artists set (for SoundCloud compatibility)
    bannedArtists.value.add(artistName)
    
    // Add to global blacklist (this will affect ALL sections)
    addArtistToBlacklist(artistName)
    
    // Save to backend API for persistence across sessions
    try {
      await http.post('music-preferences/blacklist-artist', {
        artist_name: artistName,
        spotify_artist_id: `soundcloud:${artistName}` // Create SoundCloud identifier
      })
      console.log('✅ SoundCloud artist saved to global blacklist')
    } catch (apiError) {
      console.error('Failed to save SoundCloud artist to API:', apiError)
      // Continue with local operations even if API fails
    }
    
    // Save to localStorage for local compatibility
    localStorage.setItem('koel-banned-artists', JSON.stringify(Array.from(bannedArtists.value)))
    
    // Filter from current results
    const filteredTracks = tracks.value.filter(t => t.user?.username !== artistName)
    tracks.value = filteredTracks
    
    const filteredSeedResults = seedSearchResults.value.filter(t => t.user?.username !== artistName)
    seedSearchResults.value = filteredSeedResults
    
    console.log(`🚫 SoundCloud artist "${artistName}" has been banned globally`)
  } catch (error: any) {
    console.error('Failed to ban artist:', error)
    error.value = `Failed to ban artist: ${error.message || 'Unknown error'}`
  }
}

// Filter function to remove banned artists from tracks
const filterBannedArtists = (trackList: SoundCloudTrack[]): SoundCloudTrack[] => {
  return trackList.filter(track => {
    const artistName = track.user?.username
    return artistName && !bannedArtists.value.has(artistName)
  })
}

// Click outside handler to close dropdown
const handleClickOutside = (event: MouseEvent) => {
  if (searchContainer.value && !searchContainer.value.contains(event.target as Node)) {
    // Only close the dropdown, not the entire results display
    if (showDropdown.value) {
      showDropdown.value = false
    }
  }
}

onMounted(() => {
  // Listen for related tracks data from other screens
  eventBus.on('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)
  
  // Listen for skip events from the SoundCloud player
  eventBus.on('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.on('SOUNDCLOUD_SKIP_NEXT', skipToNext)
  
  // Load local banned artists
  loadBannedArtists()
  
  // Load global blacklisted items 
  loadBlacklistedItems()
  
  // Add click outside listener
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  eventBus.off('SOUNDCLOUD_RELATED_TRACKS_DATA', handleScreenLoad)
  eventBus.off('SOUNDCLOUD_SKIP_PREVIOUS', skipToPrevious)
  eventBus.off('SOUNDCLOUD_SKIP_NEXT', skipToNext)
  
  // Remove click outside listener
  document.removeEventListener('click', handleClickOutside)
})
</script>