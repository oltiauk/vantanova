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
  searchQuery?: string
  searchTags?: string
  genre?: string
  bpmFrom?: number
  bpmTo?: number
  durationFrom?: number
  durationTo?: number
  minPlays?: number
  maxPlays?: number
  timePeriod?: string
  limit?: number
  offset?: number
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
      // console.log('🎵 Cache HIT:', cacheKey.substring(0, 50) + '...', `(${cached.data.length} tracks)`)
      return cached.data
    }
    
    // console.log('🎵 Cache MISS:', cacheKey.substring(0, 50) + '...', 'fetching fresh data')
    const data = await fetchFn()
    queryCache.set(cacheKey, { data, timestamp: Date.now() })
    return data
  }

  private makeRequest = async (config: any, retries: number = SoundCloudService.MAX_RETRIES): Promise<any> => {
    const startTime = Date.now()
    
    try {
      // console.log('🌐 Making HTTP request to Koel SoundCloud API', {
      //   method: 'GET',
      //   endpoint: SoundCloudService.API_ENDPOINT,
      //   params: config,
      //   retries_remaining: retries,
      //   timestamp: new Date().toISOString()
      // })

      // console.log('🎵 DEBUG: HTTP request config.params:', config)
      // console.log('🎵 DEBUG: Expected URL would be:', `${SoundCloudService.API_ENDPOINT}?${new URLSearchParams(config).toString()}`)
      // console.log('🎵 DEBUG: http.get call structure:', { 
      //   endpoint: SoundCloudService.API_ENDPOINT, 
      //   options: { params: config },
      //   config_keys: Object.keys(config),
      //   config_values: Object.values(config)
      // })

      // Fix: For GET requests, parameters should be in config.params, not as second argument
      const response = await http.get(SoundCloudService.API_ENDPOINT, { params: config })
      const responseTime = Date.now() - startTime

      // console.log('✅ SoundCloud API Response Success', {
      //   status: 'success',
      //   response_time_ms: responseTime,
      //   track_count: response.collection?.length || response.data?.length || 0,
      //   first_track_title: response.collection?.[0]?.title || response.data?.[0]?.title || 'none',
      //   has_next_page: !!response.next_href,
      //   timestamp: new Date().toISOString()
      // })

      return response
    } catch (error: any) {
      const responseTime = Date.now() - startTime
      
      // console.error('❌ SoundCloud API Request Error', {
      //   error_type: error.name || 'Unknown',
      //   error_message: error.message,
      //   status_code: error.response?.status,
      //   response_time_ms: responseTime,
      //   retries_remaining: retries,
      //   will_retry: retries > 0 && error.response?.status >= 500,
      //   timestamp: new Date().toISOString()
      // })

      if (retries > 0 && error.response?.status >= 500) {
        // console.log(`🔄 Retrying request after ${SoundCloudService.RETRY_DELAY_MS}ms delay...`)
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

    console.log(`🎵 Applying minimal filters to ${tracks.length} tracks`)
    console.log('🎵 DEBUG: Active filters:', {
      durationFrom: filters.durationFrom,
      durationTo: filters.durationTo, 
      minPlays: filters.minPlays,
      maxPlays: filters.maxPlays
    })
    
    let filteredTracks = tracks

    // Only apply duration and popularity filters if explicitly set
    filteredTracks = filteredTracks.filter(track => {
      // Duration filter
      if (filters.durationFrom || filters.durationTo) {
        const durationSec = track.duration / 1000
        console.log(`🎵 Duration check: track ${track.title} has ${durationSec}s, range: ${filters.durationFrom}-${filters.durationTo}`)
        if (filters.durationFrom && durationSec < filters.durationFrom) {
          console.log(`🎵 Filtered out ${track.title} - too short (${durationSec}s < ${filters.durationFrom}s)`)
          return false
        }
        if (filters.durationTo && durationSec > filters.durationTo) {
          console.log(`🎵 Filtered out ${track.title} - too long (${durationSec}s > ${filters.durationTo}s)`)
          return false
        }
      }
      
      // Popularity filter
      if (filters.minPlays && track.playback_count < filters.minPlays) {
        console.log(`🎵 Filtered out ${track.title} - too few plays (${track.playback_count} < ${filters.minPlays})`)
        return false
      }
      if (filters.maxPlays && track.playback_count > filters.maxPlays) {
        console.log(`🎵 Filtered out ${track.title} - too many plays (${track.playback_count} > ${filters.maxPlays})`)
        return false
      }
      
      return true
    })

    // Apply VERY lenient genre filtering only for Country
    if (filters.genre && filters.genre.toLowerCase() === 'country') {
      // console.log(`🎵 Applying VERY lenient country filtering`)
      
      filteredTracks = filteredTracks.filter(track => {
        const searchText = `${track.title} ${track.user.username}`.toLowerCase()
        
        // Only remove tracks with obvious Arabic/Middle Eastern keywords
        const arabicKeywords = ['محكمه', 'مهرجان', 'توزيع', 'كليب']
        const hasArabic = arabicKeywords.some(keyword => searchText.includes(keyword.toLowerCase()))
        
        if (hasArabic) {
          // console.log(`🎵 Removing Arabic track: ${track.title}`)
          return false
        }
        
        // console.log(`🎵 Keeping track: ${track.title}`)
        return true
      })
    }

    // Sort by popularity to get the most relevant tracks first  
    filteredTracks.sort((a, b) => b.playback_count - a.playback_count)
    
    // Return all filtered tracks (not limited) for Load More functionality
    console.log(`🎵 Minimal filtering result: ${tracks.length} -> ${filteredTracks.length} tracks`)
    
    return filteredTracks
  }

  /**
   * Progressive search - returns first batch immediately, continues loading in background
   */
  async searchWithProgressiveLoading(filters: SoundCloudFilters, onBatchReceived?: (tracks: SoundCloudTrack[]) => void): Promise<{tracks: SoundCloudTrack[], hasMore: boolean, nextHref?: string, apiCalls?: number}> {
    console.log('🎵 Starting progressive search')

    try {
      // Always use single API call with higher limit for better filtering pool
      const params: any = {
        limit: 100,
        _cache_bust: Date.now()
      }
      
      // Add offset for pagination
      if (filters.offset) {
        params.offset = filters.offset
      }
      
      // Add genre if specified
      if (filters.genre && filters.genre !== 'All Genres') {
        params.genre = filters.genre
      }
      
      // Add search query parameter - prioritize searchQuery over searchTags
      if (filters.searchQuery) {
        params.q = filters.searchQuery
      } else if (filters.searchTags) {
        params.q = filters.searchTags
      }
      
      // Add time period filtering
      if (filters.timePeriod) {
        const cutoffDate = this.calculateCutoffDate(filters.timePeriod)
        const formattedDate = cutoffDate.toISOString().slice(0, 19).replace('T', ' ')
        params['created_at[from]'] = formattedDate
      }
      
      // Add BPM filtering if not default range (95-172)
      if (filters.bpmFrom && filters.bpmFrom !== 95) {
        params['bpm[from]'] = filters.bpmFrom
      }
      if (filters.bpmTo && filters.bpmTo !== 172) {
        params['bpm[to]'] = filters.bpmTo  
      }
      
      console.log('🎵 Getting first batch immediately')
      
      const allTracks: any[] = []
      let currentResponse = await this.makeRequest(params)
      let requestCount = 1
      
      // Get first batch and apply filtering
      const firstBatch = currentResponse?.collection || currentResponse?.data || []
      allTracks.push(...firstBatch)
      const firstFiltered = this.applyMinimalFilters([...allTracks], filters)
      
      console.log(`🎵 First batch ready: ${firstBatch.length} raw -> ${firstFiltered.length} filtered`)
      
      // Send first batch immediately via callback
      if (onBatchReceived) {
        onBatchReceived(firstFiltered)
      }
      
      // Continue fetching more tracks in background
      const backgroundFetch = async () => {
        while (currentResponse?.next_href && requestCount < 4 && allTracks.length < 100) {
          console.log(`🎵 Background fetch: request ${requestCount + 1}`)
          
          try {
            const nextUrl = new URL(currentResponse.next_href, 'https://api.soundcloud.com')
            const nextOffset = nextUrl.searchParams.get('offset')
            
            if (nextOffset) {
              const nextParams = { ...params, offset: parseInt(nextOffset) }  
              currentResponse = await this.makeRequest(nextParams)
              
              const nextBatch = currentResponse?.collection || currentResponse?.data || []
              allTracks.push(...nextBatch)
              requestCount++
              
              // Apply filtering to all tracks so far
              const allFiltered = this.applyMinimalFilters([...allTracks], filters)
              
              console.log(`🎵 Background batch: +${nextBatch.length} raw (total: ${allTracks.length} raw, ${allFiltered.length} filtered)`)
              
              // Send updated batch via callback
              if (onBatchReceived) {
                onBatchReceived(allFiltered)
              }
            } else {
              break
            }
          } catch (error) {
            console.error('🎵 Background fetch error:', error)
            break
          }
        }
        
        console.log(`🎵 Progressive loading complete: ${allTracks.length} tracks from ${requestCount} requests`)
      }
      
      // Start background fetching (don't await - let it run in background)
      backgroundFetch()
      
      // Return initial response immediately
      return {
        tracks: firstFiltered,
        hasMore: !!(currentResponse?.next_href),
        nextHref: currentResponse?.next_href,
        apiCalls: requestCount
      }
      
    } catch (error) {
      console.error('🎵 Progressive search failed:', error)
      return {
        tracks: [],
        hasMore: false,
        apiCalls: 0
      }
    }
  }

  /**
   * Search with pagination support
   */
  async searchWithPagination(filters: SoundCloudFilters): Promise<{tracks: SoundCloudTrack[], hasMore: boolean, nextHref?: string, apiCalls?: number}> {
    const response = await this.searchWithRawResponse(filters)
    
    // Check if the response has a next_href to determine if there are more results
    const hasMore = !!(response?.next_href)
    const rawTracks = response?.collection || response?.data || []
    
    // Apply frontend filtering for duration and plays (SoundCloud API doesn't support these)
    const tracks = this.applyMinimalFilters(rawTracks, filters)
    
    // console.log('🎵 Pagination check:', {
    //   has_next_href: hasMore,
    //   next_href: response?.next_href,
    //   tracks_count: tracks.length
    // })
    
    // DEBUG: Check followers count issue  
    if (tracks && tracks.length > 0) {
      // console.log('🎵 DEBUG: First filtered track user object:', tracks[0]?.user)
      // console.log('🎵 DEBUG: User object fields:', tracks[0]?.user ? Object.keys(tracks[0].user) : 'No user object')
      // console.log('🎵 DEBUG: User followers_count:', tracks[0]?.user?.followers_count)
      
      // Check if followers_count exists in any form
      if (tracks[0]?.user) {
        const userKeys = Object.keys(tracks[0].user)
        const followerKeys = userKeys.filter(key => key.toLowerCase().includes('follow'))
        // console.log('🎵 DEBUG: Follower-related keys in user:', followerKeys)
        
        // Check all user properties and their values
        // console.log('🎵 DEBUG: All user properties and values:')
        Object.entries(tracks[0].user).forEach(([key, value]) => {
          // console.log(`  ${key}:`, value)
        })
      }
    }
    
    return {
      tracks: Array.isArray(tracks) ? tracks : [],
      hasMore,
      nextHref: response?.next_href,
      apiCalls: response?.apiCalls || 1
    }
  }

  private async searchWithRawResponse(filters: SoundCloudFilters): Promise<any> {
    console.log('🎵 SoundCloud Single API Call Search - Filters:', filters)

    try {
      // Always use single API call with higher limit for better filtering pool
      const params: any = {
        limit: 100, // Increased from 20 to get more tracks for frontend filtering
        _cache_bust: Date.now() // Ensure no caching issues
      }
      
      // Add offset for pagination
      if (filters.offset) {
        params.offset = filters.offset
      }
      
      // Add genre if specified
      if (filters.genre && filters.genre !== 'All Genres') {
        params.genre = filters.genre
      }
      
      // Add search query parameter - prioritize searchQuery over searchTags
      if (filters.searchQuery) {
        params.q = filters.searchQuery
      } else if (filters.searchTags) {
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
          cutoff_date: formattedDate
        })
      }
      
      // Add BPM filtering if not default range (95-172)
      if (filters.bpmFrom && filters.bpmFrom !== 95) {
        params['bpm[from]'] = filters.bpmFrom
      }
      if (filters.bpmTo && filters.bpmTo !== 172) {
        params['bpm[to]'] = filters.bpmTo  
      }
      
      // Note: Duration and plays filtering applied frontend-side since SoundCloud API doesn't support them
      
      console.log('🎵 Single API call params:', params)
      console.log('🎵 Making single API request')
      
      const response = await this.makeRequest(params)
      console.log('🎵 Single API call response:', {
        track_count: response?.collection?.length || response?.data?.length || 0,
        has_next_href: !!response?.next_href
      })
      
      // Log first track structure to see what data is available
      const tracks = response?.collection || response?.data || []
      if (tracks && tracks.length > 0) {
        console.log('🎵 First track complete structure:', tracks[0])
        console.log('🎵 First track user object:', tracks[0]?.user)
        
        if (tracks[0]?.user) {
          console.log('🎵 User object keys:', Object.keys(tracks[0].user))
          console.log('🎵 User followers_count:', tracks[0].user.followers_count)
          console.log('🎵 User followings_count:', tracks[0].user.followings_count)
          console.log('🎵 User track_count:', tracks[0].user.track_count)
          console.log('🎵 User public_favorites_count:', tracks[0].user.public_favorites_count)
          
          // Look for any follower-related fields
          const userKeys = Object.keys(tracks[0].user)
          const followerKeys = userKeys.filter(key => 
            key.toLowerCase().includes('follow') || 
            key.toLowerCase().includes('fan') ||
            key.toLowerCase().includes('subscriber')
          )
          console.log('🎵 Follower-related keys found:', followerKeys)
          
          // Log all user fields and values
          console.log('🎵 Complete user data:')
          Object.entries(tracks[0].user).forEach(([key, value]) => {
            console.log(`  ${key}:`, value)
          })
        }
      }
      
      return {
        ...response,
        apiCalls: 1
      }
      
    } catch (error) {
      console.error('🎵 Single API call search failed:', error)
      return null
    }
  }

  /**
   * FIXED: Exact Python script replication strategy
   */
  async search(filters: SoundCloudFilters): Promise<SoundCloudTrack[]> {
    console.log('🎵 Simplified search using single API call')
    
    const response = await this.searchWithRawResponse(filters)
    
    if (!response) {
      console.log('🎵 No response from API')
      return []
    }
    
    const rawTracks = response.collection || response.data || []
    
    if (!rawTracks || !Array.isArray(rawTracks)) {
      console.log('🎵 Invalid tracks data returned')
      return []
    }
    
    console.log(`🎵 Got ${rawTracks.length} tracks from API, applying frontend filtering`)
    
    // Apply frontend filtering for duration, plays, etc.
    const filteredTracks = this.applyMinimalFilters(rawTracks, filters)
    
    // Return ALL filtered tracks (not limited to 20) so Load More can work
    console.log(`🎵 Returning ${filteredTracks.length} tracks (filtered from ${rawTracks.length})`)
    
    return filteredTracks
  }

  async getUserDetails(userId: number): Promise<any> {
    // console.log('🎵 Fetching user details for:', userId)

    const response = await http.get<any>('soundcloud/user', { 
      params: { user_id: userId } 
    })

    // console.log('✅ User details fetched successfully:', response)
    return response
  }

  async getEmbedUrl(trackId: number, options: {
    auto_play?: boolean
    hide_related?: boolean
    show_comments?: boolean
    show_user?: boolean
    visual?: boolean
  } = {}): Promise<string> {
    // console.log('🎵 Requesting SoundCloud embed URL', {
    //   track_id: trackId,
    //   options,
    //   endpoint: SoundCloudService.EMBED_API_ENDPOINT,
    //   timestamp: new Date().toISOString()
    // })

    const response = await http.post<{ embed_url: string }>(SoundCloudService.EMBED_API_ENDPOINT, {
      track_id: trackId,
      auto_play: true,
      hide_related: true,
      show_comments: false,
      show_user: true,
      visual: true,
      ...options
    })

    // console.log('✅ SoundCloud embed URL generated successfully', {
    //   track_id: trackId,
    //   embed_url: response.embed_url,
    //   url_length: response.embed_url.length
    // })

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

  private formatNumber(num: number | undefined | null): string {
    if (!num || num === 0) {
      return '0'
    }
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
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
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