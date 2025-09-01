<?php
require 'config.php';
session_start();

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = $error = "";

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newUsername = trim($_POST['username']);
    $newPassword = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_BCRYPT) : $user['password'];
    $profileImage = $user['profile_image'];

    // Handle image upload
if (!empty($_FILES['profile_image']['name']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
    $targetDir = "profile/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $targetFile = $targetDir . time() . "_" . basename($_FILES["profile_image"]["name"]);

    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFile)) {
        // Delete old image if it exists and is not the default guest
        if (!empty($profileImage) && file_exists($profileImage) && basename($profileImage) !== 'guest.png') {
            @unlink($profileImage);
        }

        $profileImage = $targetFile;
    }
}

    try {
    $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, profile_image=? WHERE id=?");
    $stmt->execute([$newUsername, $newPassword, $profileImage, $user_id]);

    // Update session immediately
    $_SESSION['username'] = $newUsername;
    $_SESSION['profile_image'] = $profileImage;

    // Also update local $user array for the form display
    $user['username'] = $newUsername;
    $user['profile_image'] = $profileImage;

    $success = "Profile updated successfully!";
} catch (Exception $e) {
    $error = "Update failed. Username might already exist.";
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>MiniMedia - Profile</title>
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
<div class="container py-5 text-white">
    <h2>My Profile</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Profile Picture</label><br>
            <img src="<?= htmlspecialchars($user['profile_image'] ?: 'profile/guest.png') ?>" width="100" class="rounded-circle mb-2"><br>
            <input type="file" name="profile_image" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">New Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>

<!-- Footer -->
<footer class="bg-white py-3 mt-4 text-center text-dark">
  <p>Copyright © 2025 - <?= date('Y') ?> Minimedia. All rights reserved.</p>
  <p>Designed by <a href="#" target="_blank">MrMat</a></p>
</footer>

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
