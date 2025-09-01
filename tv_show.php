<?php
session_start();
require 'config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8", $dbuser, $dbpass);

$show = $_GET['show'] ?? '';
if (!$show) exit('Show not specified');

// Fetch all TV episodes for this show
$stmt = $pdo->prepare("SELECT * FROM media WHERE type='tv' ORDER BY season, episode");
$stmt->execute();
$all_episodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$episodes = [];
foreach ($all_episodes as $ep) {
    $folder = basename(dirname(str_replace('\\','/',$ep['path'])));
    if ($folder === $show) $episodes[] = $ep;
}

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
<title>MiniMedia - <?= htmlspecialchars($show) ?> - Episodes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
body {
    background-image: url('images/bg.jpg');
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    color: #fff;
	.ep-table img { height:80px; object-fit:cover; border-radius:4px; }
        .table th, .table td { vertical-align: middle; }
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
.tv-card img { height: 150px; object-fit: cover; border-radius:6px; }
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

<div class="container py-4">
    <h1 class="mb-4"><?= htmlspecialchars($show) ?> - Episodes</h1>
    <a href="tv.php" class="btn btn-outline-primary mb-3">Back to TV Shows</a>

    <?php if(!$episodes): ?>
        <p class="text-danger">No episodes found for this show.</p>
    <?php else: ?>
    <table class="table table-dark table-striped ep-table">
        <thead>
            <tr>
                <th>Cover</th>
                <th>Title</th>
                <th>Season</th>
                <th>Episode</th>
                <th>Play</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($episodes as $ep): ?>
            <tr>
                <td><img src="<?= $ep['cover'] ?: 'placeholder.jpg' ?>"></td>
                <td><?= htmlspecialchars($ep['title']) ?></td>
                <td><?= $ep['season'] ?: '-' ?></td>
                <td><?= $ep['episode'] ?: '-' ?></td>
                <td>
                    <button class="btn btn-success btn-sm play-btn" data-path="<?= $ep['path'] ?>">Play</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Video Modal -->
<div class="modal fade" id="playerModal" tabindex="-1">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.play-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        var player = document.getElementById('player');
        player.src = 'stream.php?file=' + encodeURIComponent(btn.dataset.path);
        player.load();
        player.play();
        new bootstrap.Modal(document.getElementById('playerModal')).show();
    });
});
</script>
</body>
</html>
