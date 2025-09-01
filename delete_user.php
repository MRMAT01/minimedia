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

// Fetch user to get profile image
$stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Delete profile image if exists and not default
    if (!empty($user['profile_image']) && $user['profile_image'] !== 'guest.png') {
        $file = '' . $user['profile_image'];
        if (file_exists($file)) unlink($file);
    }

    // Delete user from database
    $delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $delete->execute([$id]);
}

header("Location: users.php");
exit();
