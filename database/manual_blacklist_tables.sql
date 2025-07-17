-- Manual SQL to create blacklist/saved tables
-- Run these if migrations can't be executed

-- Create blacklisted_tracks table
CREATE TABLE IF NOT EXISTS blacklisted_tracks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    isrc VARCHAR(255) NOT NULL,
    track_name VARCHAR(255) NOT NULL,
    artist_name VARCHAR(255) NOT NULL,
    spotify_id VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY blacklisted_tracks_user_id_isrc_unique (user_id, isrc),
    KEY blacklisted_tracks_isrc_index (isrc),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create saved_tracks table
CREATE TABLE IF NOT EXISTS saved_tracks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    isrc VARCHAR(255) NOT NULL,
    track_name VARCHAR(255) NOT NULL,
    artist_name VARCHAR(255) NOT NULL,
    spotify_id VARCHAR(255) NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY saved_tracks_user_id_isrc_unique (user_id, isrc),
    KEY saved_tracks_isrc_index (isrc),
    KEY saved_tracks_expires_at_index (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create blacklisted_artists table
CREATE TABLE IF NOT EXISTS blacklisted_artists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    spotify_artist_id VARCHAR(255) NOT NULL,
    artist_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY blacklisted_artists_user_id_spotify_artist_id_unique (user_id, spotify_artist_id),
    KEY blacklisted_artists_spotify_artist_id_index (spotify_artist_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create saved_artists table
CREATE TABLE IF NOT EXISTS saved_artists (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    spotify_artist_id VARCHAR(255) NOT NULL,
    artist_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY saved_artists_user_id_spotify_artist_id_unique (user_id, spotify_artist_id),
    KEY saved_artists_spotify_artist_id_index (spotify_artist_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);