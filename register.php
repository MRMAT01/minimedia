<?php
require 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = "member"; // default unless you set manually

    // Handle profile image upload
    $profileImage = "profile/guest.png";
    if (!empty($_FILES['profile_image']['name'])) {
        $targetDir = "profile/";
        $targetFile = $targetDir . time() . "_" . basename($_FILES["profile_image"]["name"]);
        move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFile);
        $profileImage = $targetFile;
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, profile_image) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$username, $email, $password, $role, $profileImage])) {
        $_SESSION['message'] = "Registration successful! Please login.";
        header("Location: login.php");
        exit;
    } else {
        $error = "Registration failed. Try again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2>Register</h2>
    <?php if (!empty($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
        <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
        <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
        <div class="mb-3"><input type="file" name="profile_image" class="form-control"></div>
        <button class="btn btn-primary">Register</button>
    </form>
    <p class="mt-3">Already registered? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
