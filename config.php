<?php
$dbhost = 'localhost';
$dbname = 'db_mmedia';
$dbuser = 'username';
$dbpass = 'password';
$movie_path = __DIR__ . '\movies';
$tv_path = __DIR__ . '\tv';
$cover_path = __DIR__ . '\covers';
$music_path = __DIR__ . '\music';      // actual folder on disk
$cover_path = __DIR__ . '\covers';     // still for movies/t

$ffmpeg = 'ffmpeg/ffmpeg.exe';
$ffprobe = 'ffmpeg/ffprobe.exe';

$tmdb_api = 'API key here'; // Add your TMDb API key here

// Streaming mode: 'direct', 'transcode', or 'smart'
$stream_mode = 'smart';

// PDO
try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // For installer convenience, don't reveal credentials in production
    die('Database error: ' . $e->getMessage());
}
?>