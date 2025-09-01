<?php
session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

// Fetch all TV media grouped by folder
$tv_media = $pdo->query("SELECT * FROM media WHERE type='tv' ORDER BY path")->fetchAll(PDO::FETCH_ASSOC);
$tv_shows = [];
foreach($tv_media as $ep) {
    $folder = basename(dirname(str_replace('\\','/',$ep['path'])));
    if(!isset($tv_shows[$folder])) {
        $tv_shows[$folder] = $ep; // first episode's poster
    }
}

// Determine media type
$media_type = $_GET['type'] ?? 'movie';
$stmt = $pdo->prepare("SELECT * FROM media WHERE type = ? ORDER BY title");
$stmt->execute([$media_type]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$profileImage = "profile/guest.png"; // default

if (!empty($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['profile_image']) && file_exists(__DIR__ . '/' . $user['profile_image'])) {
        $profileImage = $user['profile_image'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - TV Shows</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
body {
    background-image: url('images/bg.jpg');
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    color: #fff;
}
.navbar-custom {
    position: sticky;
    top: 0;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
    z-index: 1000;
}
.profile-pic {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
}
.profile-pic img { width:100%; height:100%; object-fit: cover; }
.toggle-change::after { border-top:0; border-bottom:0.3em solid; }
.tv-grid {
    display: grid;
    grid-template-columns: repeat(9, 1fr);
    gap: 1rem;
    margin: 1rem 0;
}
.tv-card img { height: 250px; object-fit: cover; border-radius:6px; }
.tv-card .card-body { padding:0.5rem; text-align:center; }
a { text-decoration:none; color:#fff; }
a:hover { text-decoration:none; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><img src="images/logo.jpg" height="60" alt="MiniMedia"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
    <li class="nav-item"><a class="btn btn-sm btn-outline-danger active me-2" href="index.php">Home</a></li>
    <li class="nav-item"><a class="btn btn-sm btn-outline-info me-2" href="home.php?type=movie">Movies</a></li>
    <li class="nav-item"><a class="btn btn-sm btn-outline-primary me-2" href="tv.php?type=tv">TV</a></li>
	</ul>
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 profile-menu">
        <li class="nav-item dropdown">
          <a class="nav-link" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown">
            <div class="profile-pic"><img src="<?= $profileImage ?>" alt="Profile"></div>
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
<div class="container py-4 text-white">
  <h1 class="mb-4">My <?= htmlspecialchars(ucfirst($media_type)) ?> Library</h1>
<!-- TV Grid -->
<div class="container">
    <div class="tv-grid">
    <?php foreach($tv_shows as $folder => $ep): ?>
        <div class="card tv-card h-100 bg-dark text-white">
            <?php if($ep['cover'] && file_exists($ep['cover'])): ?>
                <img src="<?= $ep['cover'] ?>" class="card-img-top">
            <?php else: ?>
                <div class="bg-secondary text-white text-center py-5">No Poster</div>
            <?php endif; ?>
            <div class="card-body">
                <h6 class="card-title mb-1"><?= htmlspecialchars($folder) ?></h6>
                <a href="tv_show.php?show=<?= urlencode($folder) ?>" class="btn btn-sm btn-primary w-100">View Episodes</a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
</div>
<!-- Footer -->
<footer class="bg-white py-3 mt-4 text-center text-dark">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="#" target="_blank">MrMat</a></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Profile dropdown toggle visual
document.querySelectorAll('.dropdown-toggle').forEach(item => {
  item.addEventListener('click', event => {
    if(event.target.classList.contains('dropdown-toggle') || event.target.parentElement.classList.contains('dropdown-toggle')){
      event.target.classList.toggle('toggle-change');
    }
  });
});
</script>
</body>
</html>
