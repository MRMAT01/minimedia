<?php
require 'config.php';

$file = $_GET['file'] ?? '';
if (!file_exists($file)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

// Video extensions
$video_exts = ['mp4', 'm4v'];
// Audio extensions
$audio_exts = ['mp3', 'ogg'];

// Handle video streaming
if (in_array($ext, $video_exts)) {
    header('Content-Type: video/mp4');

    $size = filesize($file);
    $fp = fopen($file, 'rb');
    $start = 0;
    $end = $size - 1;

    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
            $start = intval($matches[1]);
            if ($matches[2] !== '') {
                $end = intval($matches[2]);
            }
        }
        header('HTTP/1.1 206 Partial Content');
    }

    header("Content-Length: " . ($end - $start + 1));
    header("Accept-Ranges: bytes");
    header("Content-Range: bytes $start-$end/$size");

    fseek($fp, $start);
    while (!feof($fp) && ftell($fp) <= $end) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
    exit;
}

// Handle audio streaming
if (in_array($ext, $audio_exts)) {
    $mime = $ext === 'mp3' ? 'audio/mpeg' : 'audio/ogg';
    header("Content-Type: $mime");
    header("Content-Length: " . filesize($file));
    readfile($file);
    exit;
}

// MKV / Other formats → force transcode to mp4
$cmd = "$ffmpeg -hide_banner -loglevel error -i " . escapeshellarg($file) . " " .
       "-c:v libx264 -preset veryfast -crf 23 " .
       "-c:a aac -ac 2 -b:a 128k " .
       "-movflags +frag_keyframe+empty_moov+default_base_moof " .
       "-f mp4 pipe:1";

passthru($cmd);
exit;
?>
