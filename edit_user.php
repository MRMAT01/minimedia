<?php
require 'config.php';
session_start();

// Only admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    // Handle profile image upload
    $profile_image = $user['profile_image'];
    if (!empty($_FILES['profile_image']['name'])) {
        $filename = time() . "_" . basename($_FILES['profile_image']['name']);
        $target = "profile/" . $filename;
        move_uploaded_file($_FILES['profile_image']['tmp_name'], $target);
        $profile_image = $filename;
    }

    $update = $pdo->prepare("UPDATE users SET username=?, email=?, role=?, profile_image=? WHERE id=?");
    $update->execute([$username, $email, $role, $profile_image, $id]);

    header("Location: users.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2>Edit User</h2>
    <form method="post" enctype="multipart/form-data" class="bg-white p-4 rounded shadow">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Profile Image</label><br>
            <img src="<?= $user['profile_image'] ?: 'guest.png' ?>" width="60" class="rounded-circle mb-2"><br>
            <input type="file" name="profile_image" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Save</button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
