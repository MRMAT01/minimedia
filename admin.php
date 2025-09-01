<?php
require 'config.php';
session_start();

/* ---------- auth ---------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("DB error");
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userRole = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$userRole || $userRole['role'] !== 'admin') {
    echo "<h1 style='color:#fff'>Access denied</h1>";
    exit;
}

/* ---------- profile image ---------- */
$profileImage = "profile/guest.png";
if (!empty($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE username=?");
    $stmt->execute([$_SESSION['username']]);
    $usr = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usr && !empty($usr['profile_image']) && file_exists(__DIR__ . '/' . $usr['profile_image'])) {
        $profileImage = $usr['profile_image'];
    }
}

/* ---------- active tab ---------- */
$page  = $_GET['page'] ?? 'movies';
$type  = $page === 'movies' ? 'movie' : ($page === 'tv' ? 'tv' : 'music');

/* ---------- tv folders for dropdown ---------- */
$tvFolders = is_dir($tv_path) ? array_filter(glob($tv_path.'/*'), 'is_dir') : [];

/* ---------- actions ---------- */
$msg = "";

/* Fetch poster (TMDB) */
if (isset($_GET['fetch']) && ctype_digit($_GET['fetch'])) {
    $id = (int) $_GET['fetch'];
    $stmt = $pdo->prepare("SELECT id,title,type,path FROM media WHERE id=?");
    $stmt->execute([$id]);
    if ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $endpoint = $item['type'] === 'tv' ? 'search/tv' : 'search/movie';
        $q = @file_get_contents("https://api.themoviedb.org/3/$endpoint?api_key={$tmdb_api}&query=" . urlencode($item['title']));
        $result = json_decode($q, true);
        if (!empty($result['results'][0]['poster_path'])) {
            $poster = $result['results'][0]['poster_path'];
            if (!is_dir($cover_path)) @mkdir($cover_path, 0777, true);
            $coverName = md5($item['path']) . '.jpg';
            $coverAbs  = rtrim($cover_path, '/').'/'.$coverName;
            $coverRel  = 'covers/'.$coverName;
            @file_put_contents($coverAbs, @file_get_contents('https://image.tmdb.org/t/p/w500' . $poster));
            $pdo->prepare("UPDATE media SET cover=? WHERE id=?")->execute([$coverRel, $id]);
            $msg = "Poster updated for: " . htmlspecialchars($item['title']);
        } else {
            $msg = "No poster found for: " . htmlspecialchars($item['title']);
        }
    }
    header("Location: admin.php?page={$page}&msg=" . urlencode($msg));
    exit;
}

/* Delete media (file + cover) */
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $pdo->prepare("SELECT path, cover FROM media WHERE id=?");
    $stmt->execute([$id]);
    if ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($item['path']) && file_exists($item['path'])) @unlink($item['path']);
        if (!empty($item['cover']) && file_exists(__DIR__ . '/' . $item['cover'])) @unlink(__DIR__ . '/' . $item['cover']);
        $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
        $msg = "Deleted media ID {$id}";
    }
    header("Location: admin.php?page={$page}&msg=" . urlencode($msg));
    exit;
}

/* Edit media (title/type [+season/episode], optional cover upload) */
if (!empty($_POST['edit_id']) && ctype_digit($_POST['edit_id'])) {
    $id         = (int) $_POST['edit_id'];
    $newTitle   = $_POST['title'] ?? '';
    $newType    = $_POST['type']  ?? 'movie';
    $season     = isset($_POST['season']) ? (int) $_POST['season'] : null;
    $episode    = isset($_POST['episode']) ? (int) $_POST['episode'] : null;

    $coverSet = null;
    if (!empty($_FILES['edit_cover']['name']) && is_uploaded_file($_FILES['edit_cover']['tmp_name'])) {
        // determine destination
        $destCoverFolder = $newType === 'music' ? rtrim($music_path,'/').'/covers' : rtrim($cover_path,'/');
        if (!is_dir($destCoverFolder)) @mkdir($destCoverFolder, 0777, true);
        $coverName = md5($id . time()) . '.jpg';
        $coverAbs  = $destCoverFolder.'/'.$coverName;
        if (@move_uploaded_file($_FILES['edit_cover']['tmp_name'], $coverAbs)) {
            $coverSet = ($newType === 'music' ? 'music/covers/' : 'covers/') . $coverName;
        }
    }

    if ($newType === 'tv') {
        if ($coverSet) {
            $stmt = $pdo->prepare("UPDATE media SET title=?, type=?, season=?, episode=?, cover=? WHERE id=?");
            $stmt->execute([$newTitle, $newType, $season, $episode, $coverSet, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE media SET title=?, type=?, season=?, episode=? WHERE id=?");
            $stmt->execute([$newTitle, $newType, $season, $episode, $id]);
        }
    } else {
        if ($coverSet) {
            $stmt = $pdo->prepare("UPDATE media SET title=?, type=?, season=NULL, episode=NULL, cover=? WHERE id=?");
            $stmt->execute([$newTitle, $newType, $coverSet, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE media SET title=?, type=?, season=NULL, episode=NULL WHERE id=?");
            $stmt->execute([$newTitle, $newType, $id]);
        }
    }

    $msg = "Updated media ID {$id}";
    header("Location: admin.php?page={$page}&msg=" . urlencode($msg));
    exit;
}
/* ------ Start music section -----*/
/* ---------- Upload media (movie/tv/music) + optional manual cover ---------- */
if (isset($_POST['upload']) && !empty($_FILES['media_file']['name']) && is_uploaded_file($_FILES['media_file']['tmp_name'])) {
    $filename    = basename($_FILES['media_file']['name']);
    $type_upload = $_POST['type'] ?? 'movie';
    
    // Destination folder
    if ($type_upload === 'tv') {
        // existing TV logic (unchanged)
        if (!empty($_POST['tv_folder']) && $_POST['tv_folder'] !== '__new__') {
            $folderName = basename($_POST['tv_folder']);
        } else {
            $raw = trim($_POST['new_tv_folder'] ?? '');
            $folderName = $raw !== '' ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $raw) : 'New_Show';
        }
        $dest_folder = rtrim($tv_path, '/').'/'.$folderName;
        if (!is_dir($dest_folder)) mkdir($dest_folder, 0777, true);
    } elseif ($type_upload === 'music') {
        $dest_folder = rtrim($music_path, '/');
        if (!is_dir($dest_folder)) mkdir($dest_folder, 0777, true);
        
        // Ensure music/covers exists
        $cover_folder = $dest_folder . '/covers';
        if (!is_dir($cover_folder)) mkdir($cover_folder, 0777, true);
    } else {
        $dest_folder = rtrim($movie_path, '/');
        if (!is_dir($dest_folder)) mkdir($dest_folder, 0777, true);
    }

    $dest_path = $dest_folder . '/' . $filename;

    // Move main file
    if (!move_uploaded_file($_FILES['media_file']['tmp_name'], $dest_path)) {
        $msg = "Failed to move uploaded file: $filename";
    } else {
        $cover_rel = null;

        // Optional manual cover
        if (!empty($_FILES['cover_file']['name']) && is_uploaded_file($_FILES['cover_file']['tmp_name'])) {
            if ($type_upload === 'music') {
                $coverAbs = $cover_folder . '/' . md5($dest_path) . '.jpg';
                $cover_rel = 'music/covers/' . basename($coverAbs);
            } else {
                if (!is_dir($cover_path)) mkdir($cover_path, 0777, true);
                $coverAbs = rtrim($cover_path, '/') . '/' . md5($dest_path) . '.jpg';
                $cover_rel = 'covers/' . basename($coverAbs);
            }

            if (!move_uploaded_file($_FILES['cover_file']['tmp_name'], $coverAbs)) {
                $msg = "Failed to move uploaded cover file.";
            }
        }

        // Insert into DB
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $stmt = $pdo->prepare("INSERT INTO media (title,path,type,cover) VALUES (?,?,?,?)");
        $stmt->execute([$title, $dest_path, $type_upload, $cover_rel]);

        if (empty($msg)) $msg = "Uploaded: $filename";
    }

    header("Location: admin.php?page={$page}&msg=" . urlencode($msg));
    exit;
}
/* ------ End music section -----*/

/* ---------- load data ---------- */
if (!empty($_GET['msg'])) $msg = $_GET['msg'];

$stmt = $pdo->prepare("SELECT * FROM media WHERE type=? ORDER BY title");
$stmt->execute([$type]);
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<style>
/* ---- keep your original look ---- */
body {
    background-image: url('images/bg.jpg');
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    color: #fff;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
}
.navbar-custom {
    position: sticky;
    top: 0;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
    z-index: 1000;
}
.profile-pic { width: 50px; height: 50px; border-radius: 50%; overflow: hidden; }
.profile-pic img { width: 100%; height: 100%; object-fit: cover; }
a { text-decoration: none; }
.table thead th { color: #fff; }
.card, .table { background: rgba(0,0,0,0.6); }
.sidebar .btn { width: 100%; text-align: left; }
.sidebar .section-title { font-size: .85rem; opacity: .8; margin: .5rem 0; }
.badge-type { font-size: .75rem; }

/* --- WIDER LAYOUT --- */
.admin-full {
  max-width: 1600px;    /* bigger than default container */
  margin: 0 auto;
  padding-left: 12px;
  padding-right: 12px;
}

/* slightly narrower sidebar on large screens so main area gets wider */
@media (min-width: 992px) {
  .sidebar { padding-right: 8px; }
  .sidebar .btn { padding-left: 0.75rem; padding-right: 0.75rem; }
}

/* give table cells more breathing room on wide screens */
.table td, .table th { white-space: nowrap; }
</style>
</head>
<body class="bg-dark">
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><img src="images/logo.jpg" height="60" alt="MiniMedia"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="btn btn-sm btn-outline-danger me-2" href="index.php">Home</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-outline-info me-2 <?= $page==='movies'?'active':'' ?>" href="home.php?page=movies">Movies</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-outline-primary me-2 <?= $page==='tv'?'active':'' ?>" href="tv.php?page=tv">TV Shows</a></li>
        <li class="nav-item"><a class="btn btn-sm btn-outline-warning me-2 <?= $page==='music'?'active':'' ?>" href="music.php?page=music">Music</a></li>
      </ul>

      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 profile-menu">
        <li class="nav-item dropdown">
          <a class="nav-link" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
            <div class="profile-pic"><img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile"></div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user fa-fw"></i> Profile</a></li>
            <li><a class="dropdown-item" href="#"><i class="fas fa-cog fa-fw"></i> Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Log Out</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- WIDE container -->
<div class="container-fluid admin-full py-4">
  <div class="row g-4">
    <!-- Sidebar: keep small on lg so main gets wider -->
    <div class="col-md-3 col-lg-2">
      <div class="sidebar">
        <div class="section-title">Admin</div>
        <a href="admin.php" class="btn btn-outline-success mb-2">Admin Home</a>
        <a href="admin.php?page=movies" class="btn btn-outline-info mb-2 <?= $page==='movies'?'active':'' ?>">Edit Movies</a>
        <a href="admin.php?page=tv" class="btn btn-outline-info mb-2 <?= $page==='tv'?'active':'' ?>">Edit TV Shows</a>
        <a href="admin.php?page=music" class="btn btn-outline-info mb-2 <?= $page==='music'?'active':'' ?>">Edit Music</a>

        <div class="section-title mt-2">View</div>
        <a href="home.php?type=movie" class="btn btn-outline-primary mb-2">View Movies</a>
        <a href="tv.php" class="btn btn-outline-primary mb-2">View TV Shows</a>
        <a href="music.php" class="btn btn-outline-primary mb-2">View Music</a>

        <div class="section-title mt-2">System</div>
        <a href="scan.php" class="btn btn-outline-light mb-2">Rescan Library</a>
        <a href="users.php" class="btn btn-outline-danger mb-2">Users</a>
      </div>
    </div>

    <!-- Main: widened by using col-lg-10 -->
    <div class="col-md-9 col-lg-10">
      <h1 class="mb-3">Admin Dashboard</h1>
      <?php if (!empty($msg)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <!-- Upload -->
      <div class="card p-3 mb-4 bg-white">
        <form id="upload-form" method="post" enctype="multipart/form-data">
          <input type="hidden" name="upload" value="1">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Media file</label>
              <input type="file" name="media_file" class="form-control" required>
              <div class="form-text">Movies/TV: video; Music: mp3/ogg</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Manual cover (optional)</label>
              <input type="file" name="cover_file" class="form-control" accept="image/*">
            </div>
            <div class="col-md-2">
              <label class="form-label">Type</label>
              <select name="type" class="form-select" id="media-type">
                <option value="movie" <?= $page==='movies'?'selected':'' ?>>Movie</option>
                <option value="tv"    <?= $page==='tv'?'selected':'' ?>>TV</option>
                <option value="music" <?= $page==='music'?'selected':'' ?>>Music</option>
              </select>
            </div>

            <div class="col-md-3" id="tv-folder-wrap" style="<?= $page==='tv'?'':'display:none;' ?>">
              <label class="form-label">TV Folder</label>
              <select name="tv_folder" class="form-select" id="tv-folder-select">
                <?php foreach ($tvFolders as $f): $bn = basename($f); ?>
                  <option value="<?= htmlspecialchars($bn) ?>"><?= htmlspecialchars($bn) ?></option>
                <?php endforeach; ?>
                <option value="__new__">New Folder…</option>
              </select>
              <input type="text" name="new_tv_folder" id="new-tv-folder" class="form-control mt-1" placeholder="New folder name" style="display:none;">
            </div>

            <div class="col-12 mt-2">
              <button class="btn btn-success" name="uploadBtn">Upload</button>
            </div>
          </div>
        </form>
        <progress id="upload-progress" value="0" max="100" style="width:100%; display:none;"></progress>
      </div>

      <!-- Library Table -->
      <div class="table-responsive">
        <table class="table table-dark table-striped align-middle bg-dark">
          <thead>
            <tr>
              <th>Title</th>
              <th style="width:140px;">Type</th>
              <th style="width:160px;">Season/Episode</th>
              <th style="width:160px;">Cover</th>
              <th style="width:320px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($media as $m): ?>
              <tr>
                <form method="post" enctype="multipart/form-data">
                  <td>
                    <input type="hidden" name="edit_id" value="<?= (int) $m['id'] ?>">
                    <input type="text" name="title" value="<?= htmlspecialchars($m['title']) ?>" class="form-control form-control-sm">
                  </td>

                  <td>
                    <select name="type" class="form-select form-select-sm type-select">
                      <option value="movie" <?= $m['type']==='movie'?'selected':'' ?>>Movie</option>
                      <option value="tv"    <?= $m['type']==='tv'?'selected':'' ?>>TV</option>
                      <option value="music" <?= $m['type']==='music'?'selected':'' ?>>Music</option>
                    </select>
                  </td>

                  <td>
                    <div class="d-flex gap-1 tv-fields" style="<?= $m['type']==='tv'?'':'display:none;' ?>">
                      <input type="number" name="season"  value="<?= htmlspecialchars($m['season'] ?? '') ?>"  class="form-control form-control-sm" placeholder="S">
                      <input type="number" name="episode" value="<?= htmlspecialchars($m['episode'] ?? '') ?>" class="form-control form-control-sm" placeholder="E">
                    </div>
                    <?php if ($m['type']!=='tv'): ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>

                  <td>
                    <?php if (!empty($m['cover']) && file_exists(__DIR__ . '/' . $m['cover'])): ?>
                      <img src="<?= htmlspecialchars($m['cover']) ?>" height="80" class="rounded">
                    <?php else: ?>
                      <span class="text-muted">None</span>
                    <?php endif; ?>
                    <input type="file" name="edit_cover" class="form-control form-control-sm mt-1" accept="image/*">
                  </td>

                  <td class="d-flex flex-wrap gap-1">
                    <button class="btn btn-sm btn-primary">Save</button>
                    <a href="admin.php?page=<?= urlencode($page) ?>&delete=<?= (int) $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this media and its files?')">Delete</a>
                    <?php if ($m['type']!=='music'): ?>
                      <a href="admin.php?page=<?= urlencode($page) ?>&fetch=<?= (int) $m['id'] ?>" class="btn btn-sm btn-secondary">Fetch Poster</a>
                    <?php endif; ?>
                    <span class="badge rounded-pill bg-info text-dark badge-type"><?= htmlspecialchars(strtoupper($m['type'])) ?></span>
                  </td>
                </form>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<footer class="bg-white py-3 mt-4 text-center text-dark">
  <p class="mb-0">Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p class="mb-0">Designed by <a href="#" target="_blank">MrMat</a></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// toggle TV upload controls on type change
const typeSel = document.getElementById('media-type');
const tvWrap  = document.getElementById('tv-folder-wrap');
const tvSel   = document.getElementById('tv-folder-select');
const tvNew   = document.getElementById('new-tv-folder');

function toggleTvUpload() {
  tvWrap.style.display = (typeSel.value === 'tv') ? 'block' : 'none';
}
function toggleNewFolder() {
  if (!tvSel) return;
  tvNew.style.display = (tvSel.value === '__new__') ? 'block' : 'none';
}
if (typeSel) {
  typeSel.addEventListener('change', toggleTvUpload);
  toggleTvUpload();
}
if (tvSel) {
  tvSel.addEventListener('change', toggleNewFolder);
  toggleNewFolder();
}

// show/hide per-row season/episode when changing type
document.querySelectorAll('.type-select').forEach(function(sel){
  sel.addEventListener('change', function(){
    const td = sel.closest('tr').querySelector('.tv-fields');
    if (td) td.style.display = (sel.value === 'tv') ? 'flex' : 'none';
  });
});

// optional AJAX upload with progress (keeps your styling)
const upForm = document.getElementById('upload-form');
const prog   = document.getElementById('upload-progress');
if (upForm && prog) {
  upForm.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(upForm);
    // ensure the upload marker is present
    if (!fd.has('upload')) fd.append('upload', '1');
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin.php?page=<?= htmlspecialchars($page) ?>', true);
    xhr.upload.onprogress = function(ev){
      if (ev.lengthComputable) {
        prog.style.display = 'block';
        prog.value = (ev.loaded / ev.total) * 100;
      }
    };
    xhr.onload = function(){ window.location.reload(); };
    xhr.send(fd);
  });
}
</script>
</body>
</html>
