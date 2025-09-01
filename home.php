<?php
session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
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
<title>MiniMedia - Movies</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
body {
    background-image: url('images/bg.jpg');
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
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
.toggle-change::after { border-top:0; border-bottom:0.3em solid; }
.card-img-top { height: 350px; object-fit: cover; border-radius: 8px; }
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

  <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
    <?php foreach ($items as $item): ?>
      <div class="col">
        <div class="card h-100 text-center">
          <img src="<?= $item['cover'] ?: 'placeholder.jpg' ?>" class="card-img-top">
          <div class="card-body">
            <h6 class="card-title">
              <?= htmlspecialchars($item['title']) ?>
              <?php if ($item['season']): ?>
                - S<?= str_pad($item['season'],2,'0',STR_PAD_LEFT) ?>E<?= str_pad($item['episode']?:0,2,'0',STR_PAD_LEFT) ?>
              <?php endif; ?>
            </h6>
            <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#playerModal" data-path="<?= $item['path'] ?>">Play</button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Player Modal -->
<div class="modal fade" id="playerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <h5 class="modal-title text-white">Now Playing</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <video id="player" class="w-100" controls autoplay></video>
      </div>
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
var playerModal = document.getElementById('playerModal');
playerModal.addEventListener('show.bs.modal', function(event) {
  var button = event.relatedTarget;
  var path = button.getAttribute('data-path');
  var player = document.getElementById('player');
  player.src = 'stream.php?file=' + encodeURIComponent(path);
  player.load();
  player.play();
});

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
