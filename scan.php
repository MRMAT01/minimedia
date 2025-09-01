<?php
require 'config.php';
$pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

// Allow long-running conversions
set_time_limit(0);

// Bootstrap 5 + Cancel button + auto-scroll + thumbnails
echo '<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Scan Progress</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.log-entry { display:flex; align-items:center; margin:4px 0; font-family:monospace; }
.log-entry img { width:50px; height:auto; margin-right:8px; border-radius:4px; }
</style>
</head><body class="p-3">

<button id="cancel-btn" class="btn btn-danger mb-3">Cancel Scan</button>

<div class="progress mb-3" style="height:30px;">
    <div id="progress-bar" class="progress-bar" role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
</div>

<div id="log-container" style="max-height:600px; overflow-y:auto;"></div>

<script>
let cancelScan = false;
document.getElementById("cancel-btn").addEventListener("click", function() {
    cancelScan = true;
});
function appendLog(msg, color, thumb) {
    let logContainer = document.getElementById("log-container");
    let div = document.createElement("div");
    div.className = "log-entry";
    div.style.color = color;
    if (thumb) {
        let img = document.createElement("img");
        img.src = thumb;
        div.appendChild(img);
    }
    let text = document.createElement("div");
    text.innerHTML = msg;
    div.appendChild(text);
    logContainer.appendChild(div);
    logContainer.scrollTop = logContainer.scrollHeight;
}
function updateProgress(progress) {
    let bar = document.getElementById("progress-bar");
    bar.style.width = progress + "%";
    bar.setAttribute("aria-valuenow", progress);
    bar.innerText = progress + "%";
}
</script>
';

// Helper functions
function clean_title($filename) {
    $t = preg_replace('/[._]/',' ', $filename);
    $t = preg_replace('/\b(19|20)\d{2}\b/', '', $t);
    $t = preg_replace('/\b(S\d{1,2}E\d{1,2}|\d+x\d+|720p|1080p|2160p|x264|h264|xvid|bluray|webrip|hdrip|dvdrip)\b/i','', $t);
    return trim($t);
}

function log_msg($msg, $progress = null, $type = 'info', $thumb = null) {
    $colors = [
        'success' => '#28a745',
        'warning' => '#ffc107',
        'error'   => '#dc3545',
        'info'    => '#6c757d'
    ];
    $color = $colors[$type] ?? $colors['info'];
    $progress_js = $progress !== null ? "<script>updateProgress($progress);</script>" : "";
    $thumb_js = $thumb ? json_encode($thumb) : "null";
    echo "<script>appendLog(" . json_encode($msg) . ", '$color', $thumb_js);</script>$progress_js";
    @ob_flush(); flush();
}

function scan_dir($path, $type, $pdo, $tmdb_api, $cover_path, $ffmpeg) {
    global $cancelScan;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

    $files = [];
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4','mkv','avi','mov'])) $files[] = $file;
    }

    $total = count($files);
    if ($total === 0) return;
    $count = 0;

    foreach ($files as $file) {
        if ($cancelScan) {
            log_msg("🚫 Scan cancelled by user.", null, 'warning');
            return;
        }

        $count++;
        $progress = round(($count / $total) * 100);
        log_msg("<strong>Progress: $progress% ($count of $total)</strong>", $progress, 'info');

        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $origPath = $file->getPathname();
        $title = clean_title(pathinfo($file->getFilename(), PATHINFO_FILENAME));
        $rel = $origPath;
        $poster_thumb = null;

        // Convert non-MP4
        if ($ext !== 'mp4') {
            $newPath = preg_replace('/\.[^.]+$/', '.mp4', $origPath);
            log_msg("Converting: {$file->getFilename()} → " . basename($newPath), null, 'info');

            $cmd = "\"$ffmpeg\" -hide_banner -loglevel error -i " . escapeshellarg($origPath) . " " .
                   "-c:v libx264 -preset veryfast -crf 23 -c:a aac -ac 2 -b:a 128k -movflags +faststart " . escapeshellarg($newPath);

            exec($cmd, $out, $ret);

            if ($ret === 0 && file_exists($newPath)) {
                unlink($origPath);
                $rel = $newPath;
                log_msg("✅ Converted and deleted original: " . basename($origPath), null, 'success');
            } else {
                log_msg("❌ Failed to convert: " . basename($origPath), null, 'error');
                log_msg("FFmpeg output: " . implode("<br>", $out), null, 'error');
                $rel = $origPath;
            }
        } else {
            log_msg("Skipping (already mp4): {$file->getFilename()}", null, 'warning');
        }

        // Check DB
        $stmt = $pdo->prepare("SELECT id FROM media WHERE path=? OR path=?");
        $stmt->execute([$origPath, $rel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($ext !== 'mp4' && isset($newPath) && file_exists($newPath)) {
                $stmt = $pdo->prepare("UPDATE media SET path=? WHERE id=?");
                $stmt->execute([$newPath, $row['id']]);
                log_msg("🔄 Updated DB record for {$title} to new MP4", null, 'success');
            } else {
                log_msg("ℹ️ Already in database: " . basename($rel), null, 'warning');
            }
            continue;
        }

        // TMDb poster
        $endpoint = $type === 'tv' ? 'search/tv' : 'search/movie';
        $json = @file_get_contents("https://api.themoviedb.org/3/$endpoint?api_key=$tmdb_api&query=" . urlencode($title));
        $result = json_decode($json, true);
        $cover_rel = '';
        if (!empty($result['results'][0]['poster_path'])) {
            $poster = $result['results'][0]['poster_path'];
            $cover_name = md5($rel) . '.jpg';
            $cover_rel = 'covers/' . $cover_name;
            $cover_abs = $cover_path . '/' . $cover_name;
            @file_put_contents($cover_abs, @file_get_contents('https://image.tmdb.org/t/p/w200' . $poster));
            $poster_thumb = $cover_rel;
            log_msg("🖼 Poster saved: $cover_rel", null, 'success', $poster_thumb);
        } else {
            log_msg("⚠️ No poster found for: $title", null, 'warning');
        }

        // TV season/episode
        $season = $episode = null;
        if ($type === 'tv') {
            if (preg_match('/S(\d{1,2})E(\d{1,2})/i', $file->getFilename(), $m)) {
                $season = intval($m[1]); $episode = intval($m[2]);
            } elseif (preg_match('/(\d{1,2})x(\d{1,2})/i', $file->getFilename(), $m)) {
                $season = intval($m[1]); $episode = intval($m[2]);
            }
        }

        $stmt = $pdo->prepare("INSERT INTO media (title,path,type,cover,season,episode) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$title,$rel,$type,$cover_rel,$season,$episode]);
        log_msg("✅ Added to DB: $title", null, 'success', $poster_thumb);
    }
}

scan_dir($movie_path, 'movie', $pdo, $tmdb_api, $cover_path, $ffmpeg);
scan_dir($tv_path, 'tv', $pdo, $tmdb_api, $cover_path, $ffmpeg);

log_msg("<strong>🎉 Scan complete.</strong> <a class='btn btn-sm btn-outline-primary' href='admin.php'>Admin</a>", 100, 'success');
echo '</body></html>';
?>
