-- Fix migration tracking by marking blacklist migrations as completed
-- Run this SQL in your database if migrations show as pending but tables exist

INSERT IGNORE INTO migrations (migration, batch) VALUES 
('2025_07_16_001000_create_blacklisted_tracks_table', 1),
('2025_07_16_002000_create_saved_tracks_table', 1), 
('2025_07_16_003000_create_blacklisted_artists_table', 1),
('2025_07_16_004000_create_saved_artists_table', 1);