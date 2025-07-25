/**
 * SoundCloud Integration Service - FIXED VERSION
 * 
 * Key fixes applied:
 * 1. Matches Python script's single API call strategy
 * 2. Minimal filtering to preserve legitimate country tracks
 * 3. Fixed parameter handling to match working Python implementation
 */

import { http } from '@/services/http'

export interface SoundCloudTrack {
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
  stream_url?: string
  permalink_url?: string
  artwork_url?: string
}

export interface SoundCloudFilters {
  searchTags?: string
  genre?: string
  bpmFrom?: number
  bpmTo?: number
  durationFrom?: number
  durationTo?: number
  minPlays?: number
  minLikes?: number
  timePeriod?: string
  limit?: number
}

interface APICall {
  type: 'text' | 'genre' | 'bpm' | 'duration'
  promise: Promise<{ collection?: SoundCloudTrack[], data?: SoundCloudTrack[] }>
}

interface CacheEntry {
  data: SoundCloudTrack[]
  timestamp: number
}

// Query cache for frequent searches
const queryCache = new Map<string, CacheEntry>()
const CACHE_TTL = 5 * 60 * 1000 // 5 minutes

class SoundCloudService {
  private static readonly API_ENDPOINT = 'soundcloud/search'
  private static readonly EMBED_API_ENDPOINT = 'soundcloud/embed'
  private static readonly REQUEST_TIMEOUT_MS = 30000
  private static readonly MAX_RETRIES = 2
  private static readonly RETRY_DELAY_MS = 1000

  private getCachedOrFetch = async (cacheKey: string, fetchFn: () => Promise<SoundCloudTrack[]>): Promise<SoundCloudTrack[]> => {
    const cached = queryCache.get(cacheKey)
    if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
      console.log('🎵 Cache HIT:', cacheKey.substring(0, 50) + '...', `(${cached.data.length} tracks)`)
      return cached.data
    }
    
    console.log('🎵 Cache MISS:', cacheKey.substring(0, 50) + '...', 'fetching fresh data')
    const data = await fetchFn()
    queryCache.set(cacheKey, { data, timestamp: Date.now() })
    return data
  }

  private makeRequest = async (config: any, retries: number = SoundCloudService.MAX_RETRIES): Promise<any> => {
    const startTime = Date.now()
    
    try {
      console.log('🌐 Making HTTP request to Koel SoundCloud API', {
        method: 'GET',
        endpoint: SoundCloudService.API_ENDPOINT,
        params: config,
        retries_remaining: retries,
        timestamp: new Date().toISOString()
      })

      console.log('🎵 DEBUG: HTTP request config.params:', config)
      console.log('🎵 DEBUG: Expected URL would be:', `${SoundCloudService.API_ENDPOINT}?${new URLSearchParams(config).toString()}`)
      console.log('🎵 DEBUG: http.get call structure:', { 
        endpoint: SoundCloudService.API_ENDPOINT, 
        options: { params: config },
        config_keys: Object.keys(config),
        config_values: Object.values(config)
      })

      // Fix: For GET requests, parameters should be in config.params, not as second argument
      const response = await http.get(SoundCloudService.API_ENDPOINT, { params: config })
      const responseTime = Date.now() - startTime

      console.log('✅ SoundCloud API Response Success', {
        status: 'success',
        response_time_ms: responseTime,
        track_count: response.collection?.length || response.data?.length || 0,
        first_track_title: response.collection?.[0]?.title || response.data?.[0]?.title || 'none',
        has_next_page: !!response.next_href,
        timestamp: new Date().toISOString()
      })

      return response
    } catch (error: any) {
      const responseTime = Date.now() - startTime
      
      console.error('❌ SoundCloud API Request Error', {
        error_type: error.name || 'Unknown',
        error_message: error.message,
        status_code: error.response?.status,
        response_time_ms: responseTime,
        retries_remaining: retries,
        will_retry: retries > 0 && error.response?.status >= 500,
        timestamp: new Date().toISOString()
      })

      if (retries > 0 && error.response?.status >= 500) {
        console.log(`🔄 Retrying request after ${SoundCloudService.RETRY_DELAY_MS}ms delay...`)
        await new Promise(resolve => setTimeout(resolve, SoundCloudService.RETRY_DELAY_MS))
        return this.makeRequest(config, retries - 1)
      }
      
      throw error
    }
  }

  private calculateCutoffDate = (timePeriod: string): Date => {
    const now = new Date()
    
    switch (timePeriod) {
      case '1d':
        return new Date(now.getTime() - 24 * 60 * 60 * 1000)
      case '1w':
        return new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
      case '1m':
        return new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
      case '3m':
        return new Date(now.getTime() - 90 * 24 * 60 * 60 * 1000)
      case '6m':
        return new Date(now.getTime() - 180 * 24 * 60 * 60 * 1000)
      case '1y':
        return new Date(now.getTime() - 365 * 24 * 60 * 60 * 1000)
      default:
        return new Date(now.getTime() - 24 * 60 * 60 * 1000)
    }
  }

  /**
   * Minimal filtering to match Python script behavior
   */
  private applyMinimalFilters = (tracks: SoundCloudTrack[], filters: SoundCloudFilters): SoundCloudTrack[] => {
    // Ensure tracks is a valid array
    if (!tracks || !Array.isArray(tracks)) {
      console.log('🎵 applyMinimalFilters: Invalid tracks data, returning empty array')
      console.log('🎵 DEBUG: tracks value:', tracks)
      console.log('🎵 DEBUG: tracks type:', typeof tracks)
      return []
    }

    console.log(`🎵 Applying minimal filters (Python-style) to ${tracks.length} tracks`)
    console.log('🎵 DEBUG: First few track titles:', tracks.slice(0, 3).map(t => t?.title || 'NO TITLE'))
    
    let filteredTracks = tracks

    // Only apply duration and popularity filters if explicitly set
    filteredTracks = filteredTracks.filter(track => {
      // Duration filter
      if (filters.durationFrom || filters.durationTo) {
        const durationSec = track.duration / 1000
        if (filters.durationFrom && durationSec < filters.durationFrom) return false
        if (filters.durationTo && durationSec > filters.durationTo) return false
      }
      
      // Popularity filter
      if (filters.minPlays && track.playback_count < filters.minPlays) return false
      if (filters.minLikes && track.favoritings_count < filters.minLikes) return false
      
      return true
    })

    // Apply VERY lenient genre filtering only for Country
    if (filters.genre && filters.genre.toLowerCase() === 'country') {
      console.log(`🎵 Applying VERY lenient country filtering`)
      
      filteredTracks = filteredTracks.filter(track => {
        const searchText = `${track.title} ${track.user.username}`.toLowerCase()
        
        // Only remove tracks with obvious Arabic/Middle Eastern keywords
        const arabicKeywords = ['محكمه', 'مهرجان', 'توزيع', 'كليب']
        const hasArabic = arabicKeywords.some(keyword => searchText.includes(keyword.toLowerCase()))
        
        if (hasArabic) {
          console.log(`🎵 Removing Arabic track: ${track.title}`)
          return false
        }
        
        console.log(`🎵 Keeping track: ${track.title}`)
        return true
      })
    }

    // Sort by popularity to get the most relevant tracks first  
    filteredTracks.sort((a, b) => b.playback_count - a.playback_count)
    
    const result = filteredTracks.slice(0, filters.limit || 20)
    console.log(`🎵 Minimal filtering result: ${result.length} tracks`)
    
    return result
  }

  /**
   * FIXED: Exact Python script replication strategy
   */
  async search(filters: SoundCloudFilters): Promise<SoundCloudTrack[]> {
    console.log('🎵 SoundCloud Search - Filters:', filters)
    console.log('🎵 DEBUG: Genre from filters:', filters.genre)
    console.log('🎵 DEBUG: All filter properties:', Object.keys(filters))

    // CRITICAL FIX: Always use single API call for genre searches to match Python exactly
    if (filters.genre && filters.genre !== 'All Genres') {
      console.log('🎵 MATCHING PYTHON: Single genre API call only')
      
      try {
        // Use EXACT same parameters as your successful Python script
        const params: any = {
          genre: filters.genre,  // Changed from 'genres' to 'genre' to match controller
          limit: filters.limit || 20,
          _cache_bust: Date.now() // Ensure no caching issues
        }
        
        // Only add other filters if they exist and are meaningful
        if (filters.searchTags) {
          params.q = filters.searchTags
        }
        
        // Add time period filtering
        if (filters.timePeriod) {
          const cutoffDate = this.calculateCutoffDate(filters.timePeriod)
          // SoundCloud API expects format: "yyyy-mm-dd hh:mm:ss"
          const formattedDate = cutoffDate.toISOString().slice(0, 19).replace('T', ' ')
          params['created_at[from]'] = formattedDate
          console.log('📅 Time period filter added:', {
            period: filters.timePeriod,
            cutoff_date: formattedDate,
            cutoff_date_iso: cutoffDate.toISOString()
          })
        }
        
        // IMPORTANT: Only add BPM if it's NOT the default range (95-172)
        // Updated to match the current default range from the UI
        if (filters.bpmFrom && filters.bpmFrom !== 95) {
          params['bpm[from]'] = filters.bpmFrom
        }
        if (filters.bpmTo && filters.bpmTo !== 172) {
          params['bpm[to]'] = filters.bpmTo  
        }
        
        console.log('🎵 PYTHON-STYLE API call params:', params)
        console.log('🎵 DEBUG: Final params before HTTP request:', JSON.stringify(params, null, 2))
        console.log('🎵 DEBUG: params.genre value:', params.genre)
        
        // Critical debug: Log the actual HTTP request that will be made
        console.log('🎵 DEBUG: About to call makeRequest with config:', params)
        
        const response = await this.makeRequest(params)
        console.log('🎵 DEBUG: Raw response from backend:', response)
        console.log('🎵 DEBUG: response.collection:', response.collection)
        console.log('🎵 DEBUG: response.data:', response.data)
        
        const tracks = response.collection || response.data || []
        
        console.log(`🎵 PYTHON-STYLE API returned: ${tracks?.length || 0} tracks`)
        console.log(`🎵 First track: ${tracks?.[0]?.title || 'none'} by ${tracks?.[0]?.user?.username || 'none'}`)
        console.log('🎵 DEBUG: tracks array:', tracks)
        
        // DEBUG: Log the complete structure of the first track to see what fields are available
        if (tracks && tracks.length > 0) {
          console.log('🎵 DEBUG: First track complete structure:', tracks[0])
          console.log('🎵 DEBUG: First track BPM field:', tracks[0].bpm)
          console.log('🎵 DEBUG: All available fields:', Object.keys(tracks[0]))
        }
        
        // Backend now applies smart filtering, so we may get fewer or zero results
        if (!tracks || !Array.isArray(tracks)) {
          console.log('🎵 Backend filtering removed all tracks or returned invalid data')
          return []
        }
        
        // DISABLED: Just return raw SoundCloud results like Python script
        // SoundCloud's genre tagging is unreliable, so filtering causes more problems
        console.log('🎵 Returning raw SoundCloud results without filtering')
        return tracks.slice(0, filters.limit || 20)
        
      } catch (error) {
        console.error('🎵 Python-style search failed:', error)
        return []
      }
    }

    // For non-genre searches, use the complex approach
    const apiCalls: APICall[] = []

    if (filters.searchTags) {
      apiCalls.push({
        type: 'text',
        promise: this.makeRequest({
          q: filters.searchTags,
          limit: 50
        })
      })
    }

    if (filters.bpmFrom || filters.bpmTo) {
      apiCalls.push({
        type: 'bpm',
        promise: this.makeRequest({
          'bpm[from]': filters.bpmFrom,
          'bpm[to]': filters.bpmTo,
          limit: 50
        })
      })
    }

    if (apiCalls.length === 0) {
      throw new Error('Please provide at least one search filter')
    }

    console.log(`🎵 SoundCloud Search - Making ${apiCalls.length} API calls:`, 
      apiCalls.map(call => call.type))

    const results = await Promise.allSettled(
      apiCalls.map(call => call.promise)
    )

    // Simple union combination for non-genre searches
    const trackSets = results
      .filter((result): result is PromiseFulfilledResult<any> => result.status === 'fulfilled')
      .map(result => result.value.collection || result.value.data || [])

    if (trackSets.length === 0) return []

    const unionTracks = new Map<number, SoundCloudTrack>()
    trackSets.forEach(tracks => {
      tracks.forEach((track: SoundCloudTrack) => {
        unionTracks.set(track.id, track)
      })
    })

    const unionTracksArray = Array.from(unionTracks.values())
    console.log(`🎵 SoundCloud Search - Union tracks before minimal filtering: ${unionTracksArray.length}`)
    
    // DISABLED: Just return raw results
    const finalResults = unionTracksArray.slice(0, filters.limit || 20)
    console.log(`🎵 SoundCloud Search - Final results: ${finalResults.length} tracks`)
    
    return finalResults
  }

  async getEmbedUrl(trackId: number, options: {
    auto_play?: boolean
    hide_related?: boolean
    show_comments?: boolean
    show_user?: boolean
    visual?: boolean
  } = {}): Promise<string> {
    console.log('🎵 Requesting SoundCloud embed URL', {
      track_id: trackId,
      options,
      endpoint: SoundCloudService.EMBED_API_ENDPOINT,
      timestamp: new Date().toISOString()
    })

    const response = await http.post<{ embed_url: string }>(SoundCloudService.EMBED_API_ENDPOINT, {
      track_id: trackId,
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true,
      visual: true,
      ...options
    })

    console.log('✅ SoundCloud embed URL generated successfully', {
      track_id: trackId,
      embed_url: response.embed_url,
      url_length: response.embed_url.length
    })

    return response.embed_url
  }

  formatTrackForTable(track: SoundCloudTrack): any {
    return {
      id: track.id,
      title: track.title,
      artist: track.user?.username || 'Unknown',
      genre: track.genre || 'Unknown',
      bpm: track.bpm || 'N/A',
      playback_count: this.formatNumber(track.playback_count),
      favoritings_count: this.formatNumber(track.favoritings_count),
      followers_count: this.formatNumber(track.user?.followers_count),
      release_date: this.formatDate(track.created_at),
      duration: this.formatDuration(track.duration),
      artwork_url: track.artwork_url,
      permalink_url: track.permalink_url,
      stream_url: track.stream_url
    }
  }

  private formatNumber(num: number): string {
    if (num >= 1000000) {
      return (num / 1000000).toFixed(1) + 'M'
    } else if (num >= 1000) {
      return (num / 1000).toFixed(1) + 'K'
    }
    return num.toString()
  }

  private formatDate(dateString: string): string {
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

  private formatDuration(milliseconds: number): string {
    const totalSeconds = Math.floor(milliseconds / 1000)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60
    return `${minutes}:${seconds.toString().padStart(2, '0')}`
  }
}

export const soundcloudService = new SoundCloudService()