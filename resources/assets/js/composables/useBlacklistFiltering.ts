import { ref, computed, onMounted, onUnmounted } from 'vue'
import { http } from '@/services/http'
import { eventBus } from '@/utils/eventBus'

interface Track {
  id: string
  name: string
  artist: string
  album: string
  duration_ms?: number
  external_url?: string
  preview_url?: string
  image?: string
  uri?: string
  artists?: Array<{
    id: string
    name: string
  }>
}

interface SoundCloudTrack {
  id: number
  title: string
  user: {
    username: string
  }
  playback_count?: number
  favoritings_count?: number
  created_at?: string
  duration?: number
}

// Global state for blacklisted items
const blacklistedTracks = ref<Set<string>>(new Set())
const blacklistedArtists = ref<Set<string>>(new Set())

export const useBlacklistFiltering = () => {
  // Event bus listeners for cross-component synchronization
  const setupEventListeners = () => {
    eventBus.on('BLACKLIST_UPDATED', async () => {
      // console.log('🔄 Received blacklist update event, reloading...')
      await reloadBlacklistedItems()
    })
    
    eventBus.on('BLACKLIST_ARTIST_DELETED', async (artistName: string) => {
      // console.log('🗑️ Received artist deletion event for:', artistName)
      removeArtistFromBlacklist(artistName)
    })
    
    eventBus.on('BLACKLIST_TRACK_DELETED', async (trackData: { artist: string, name: string }) => {
      // console.log('🗑️ Received track deletion event for:', trackData) 
      // Create a mock track object for removal
      const mockTrack = { artist: trackData.artist, name: trackData.name, id: '', album: '' }
      removeTrackFromBlacklist(mockTrack)
    })
  }
  
  const cleanupEventListeners = () => {
    eventBus.off('BLACKLIST_UPDATED')
    eventBus.off('BLACKLIST_ARTIST_DELETED') 
    eventBus.off('BLACKLIST_TRACK_DELETED')
  }
  // Helper function to get track key for blacklist checking
  const getTrackKey = (track: Track): string => {
    return `${track.artist}-${track.name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
  }

  // Helper function to get SoundCloud track key
  const getSoundCloudTrackKey = (track: SoundCloudTrack): string => {
    const artist = track.user?.username || 'Unknown Artist'
    return `${artist}-${track.title}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
  }

  // Helper function to get artist key for blacklist checking
  const getArtistKey = (artistName: string): string => {
    return artistName.toLowerCase().trim()
  }

  // Check if track is blacklisted
  const isTrackBlacklisted = (track: Track): boolean => {
    return blacklistedTracks.value.has(getTrackKey(track))
  }

  // Check if SoundCloud track is blacklisted
  const isSoundCloudTrackBlacklisted = (track: SoundCloudTrack): boolean => {
    return blacklistedTracks.value.has(getSoundCloudTrackKey(track))
  }

  // Check if artist is blacklisted
  const isArtistBlacklisted = (artistName: string): boolean => {
    return blacklistedArtists.value.has(getArtistKey(artistName))
  }

  // Check if track's artist is blacklisted
  const isTrackArtistBlacklisted = (track: Track): boolean => {
    // Only check primary artist (not collaborating artists)
    // This matches the banning behavior where we ban only the primary/first artist
    return isArtistBlacklisted(track.artist)
  }

  // Check if SoundCloud track's artist is blacklisted
  const isSoundCloudTrackArtistBlacklisted = (track: SoundCloudTrack): boolean => {
    const artistName = track.user?.username || 'Unknown Artist'
    return isArtistBlacklisted(artistName)
  }

  // Combined check for track or its artist being blacklisted
  const isTrackOrArtistBlacklisted = (track: Track): boolean => {
    return isTrackBlacklisted(track) || isTrackArtistBlacklisted(track)
  }

  // Combined check for SoundCloud track or its artist being blacklisted
  const isSoundCloudTrackOrArtistBlacklisted = (track: SoundCloudTrack): boolean => {
    return isSoundCloudTrackBlacklisted(track) || isSoundCloudTrackArtistBlacklisted(track)
  }

  // Filter array of tracks (removes blacklisted tracks and tracks by blacklisted artists)
  const filterTracks = (tracks: Track[], excludeSimilarArtists = false): Track[] => {
    // For Similar Artists section, don't filter
    if (excludeSimilarArtists) {
      return tracks
    }
    
    return tracks.filter(track => !isTrackOrArtistBlacklisted(track))
  }

  // Filter array of SoundCloud tracks
  const filterSoundCloudTracks = (tracks: SoundCloudTrack[]): SoundCloudTrack[] => {
    return tracks.filter(track => !isSoundCloudTrackOrArtistBlacklisted(track))
  }

  // Add track to blacklist
  const addTrackToBlacklist = (track: Track) => {
    const trackKey = getTrackKey(track)
    blacklistedTracks.value.add(trackKey)
  }

  // Add SoundCloud track to blacklist
  const addSoundCloudTrackToBlacklist = (track: SoundCloudTrack) => {
    const trackKey = getSoundCloudTrackKey(track)
    blacklistedTracks.value.add(trackKey)
  }

  // Add artist to blacklist
  const addArtistToBlacklist = (artistName: string) => {
    const artistKey = getArtistKey(artistName)
    blacklistedArtists.value.add(artistKey)
    // console.log(`🚫 Added artist "${artistName}" to local blacklist (normalized: "${artistKey}")`)

    // Emit event to notify other components
    eventBus.emit('ARTIST_BANNED', artistName)
  }

  // Remove track from blacklist
  const removeTrackFromBlacklist = (track: Track) => {
    const trackKey = getTrackKey(track)
    blacklistedTracks.value.delete(trackKey)
  }

  // Remove artist from blacklist
  const removeArtistFromBlacklist = (artistName: string) => {
    const artistKey = getArtistKey(artistName)
    const wasRemoved = blacklistedArtists.value.delete(artistKey)
    // console.log(`🚫 ${wasRemoved ? 'Removed' : 'Attempted to remove'} artist "${artistName}" from local blacklist (normalized: "${artistKey}")`)

    // Clear localStorage cache when removing items to prevent stale data
    localStorage.removeItem('koel-banned-artists')
    // console.log('🗑️ Cleared localStorage banned artists cache')

    // Emit event to notify other components if removal was successful
    if (wasRemoved) {
      eventBus.emit('ARTIST_UNBANNED', artistName)
    }

    return wasRemoved
  }

  // Load blacklisted items from API
  const loadBlacklistedItems = async () => {
    try {
      // console.log('🚫 Loading blacklisted items from API...')

      // Load blacklisted tracks
      const blacklistedTracksResponse = await http.get('music-preferences/blacklisted-tracks')
      if (blacklistedTracksResponse.success && blacklistedTracksResponse.data) {
        blacklistedTracks.value.clear()
        blacklistedTracksResponse.data.forEach((track: any) => {
          const trackKey = `${track.artist_name}-${track.track_name}`.toLowerCase().replace(/[^a-z0-9]/g, '-')
          blacklistedTracks.value.add(trackKey)
        })
        // console.log(`🚫 Loaded ${blacklistedTracks.value.size} blacklisted tracks`)
      }

      // Load blacklisted artists
      const blacklistedArtistsResponse = await http.get('music-preferences/blacklisted-artists')
      if (blacklistedArtistsResponse.success && blacklistedArtistsResponse.data) {
        blacklistedArtists.value.clear()
        
        // Group artists by normalized name to handle duplicates
        const artistsByName = new Map<string, any[]>()
        blacklistedArtistsResponse.data.forEach((artist: any) => {
          const normalizedName = getArtistKey(artist.artist_name)
          if (!artistsByName.has(normalizedName)) {
            artistsByName.set(normalizedName, [])
          }
          artistsByName.get(normalizedName)!.push(artist)
        })
        
        // Add each unique artist name to blacklist
        artistsByName.forEach((artists, normalizedName) => {
          blacklistedArtists.value.add(normalizedName)
          
          // Log if we found duplicates
          if (artists.length > 1) {
              // console.log(`🚫 Found ${artists.length} entries for artist "${artists[0].artist_name}":`, 
              //   artists.map(a => a.spotify_artist_id))
          }
        })
        
        // console.log(`🚫 Loaded ${blacklistedArtists.value.size} unique blacklisted artists (from ${blacklistedArtistsResponse.data.length} entries)`)
      }

      // console.log('🚫 Blacklist loading complete')
    } catch (error) {
      // console.log('🚫 Could not load blacklisted items (user may not be logged in)')
    }
  }

  // Computed properties for reactive access
  const blacklistedTrackCount = computed(() => blacklistedTracks.value.size)
  const blacklistedArtistCount = computed(() => blacklistedArtists.value.size)

  // Clear all blacklist caches
  const clearBlacklistCaches = () => {
    // console.log('🗑️ Clearing all blacklist caches...')
    localStorage.removeItem('koel-banned-artists')
    localStorage.removeItem('koel-banned-tracks') // In case this exists too
    // console.log('🗑️ All blacklist caches cleared')
  }

  // Reload blacklisted items (useful after deletions in preferences)
  const reloadBlacklistedItems = async () => {
    // console.log('🔄 Reloading blacklisted items from API...')
    clearBlacklistCaches() // Clear caches before reloading
    await loadBlacklistedItems()
  }

  // Setup event bus listeners when composable is used
  setupEventListeners()

  return {
    // State
    blacklistedTracks: blacklistedTracks.value,
    blacklistedArtists: blacklistedArtists.value,
    blacklistedTrackCount,
    blacklistedArtistCount,

    // Checking functions
    isTrackBlacklisted,
    isSoundCloudTrackBlacklisted,
    isArtistBlacklisted,
    isTrackArtistBlacklisted,
    isSoundCloudTrackArtistBlacklisted,
    isTrackOrArtistBlacklisted,
    isSoundCloudTrackOrArtistBlacklisted,

    // Filtering functions
    filterTracks,
    filterSoundCloudTracks,

    // Management functions
    addTrackToBlacklist,
    addSoundCloudTrackToBlacklist,
    addArtistToBlacklist,
    removeTrackFromBlacklist,
    removeArtistFromBlacklist,
    loadBlacklistedItems,
    reloadBlacklistedItems,
    clearBlacklistCaches,
    
    // Event management
    setupEventListeners,
    cleanupEventListeners,

    // Helper functions
    getTrackKey,
    getSoundCloudTrackKey,
    getArtistKey
  }
}