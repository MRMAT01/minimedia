<?php
session_start();
require 'config.php';

$profileImage = "profile/guest.png"; // default

if (!empty($_SESSION['username'])) {
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['profile_image']) && file_exists(__DIR__ . '/' . $user['profile_image'])) {
        $profileImage = $user['profile_image'];
    }
}

// Determine media type
$media_type = $_GET['type'] ?? 'movie';
$stmt = $pdo->prepare("SELECT * FROM media WHERE type = ? ORDER BY title");
$stmt->execute([$media_type]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>MiniMedia - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-iBBXm8fW90+nuLcSKlbmrPcLa0OT92xO1BIsZ+ywDWZCvqsWgccV3gFoRBv0z+8dLJgyAHIhR35VZc2oM/gI1w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js'></script>

    <style>
		body{
			background-image:url('images/bg.jpg');
			background-attachment:fixed;
			background-repeat: no-repeat;
			background-size: cover;
		}
		/* Sticky translucent navbar */
        .navbar-custom {
            position: sticky;
            top: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }
        .category-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-top:2rem; }
        .category-card { text-align:center; cursor:pointer; transition:transform .2s; }
        .category-card:hover { transform:scale(1.05); }
        .category-card img { width:100%; height:150px; object-fit:cover; border-radius:8px; }
        .profile-img { width:40px; height:40px; border-radius:50%; object-fit:cover; }
        .search-input { width:250px; max-width:100%; }
		
		 /* Profile Picture */
    .profile-pic{
       display: inline-block;
       vertical-align: middle;
        width: 50px;
        height: 50px;
        overflow: hidden;
       border-radius: 50%;
    }
     
    .profile-pic img{
       width: 100%;
       height: auto;
       object-fit: cover;
    }
    .profile-menu .dropdown-menu {
      right: 0;
      left: unset;
    }
    .profile-menu .fa-fw {
      margin-right: 10px;
    }
     
    .toggle-change::after {
      border-top: 0;
      border-bottom: 0.3em solid;
    }
    </style>
</head>
<body class="bg-dark">
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom px-4">
      <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><img src="images/logo.jpg" height="60" alt="MiniMedia"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link active" aria-current="page" href="index.php">Home</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#">Link</a>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                Link
              </a>
              <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                <li><a class="dropdown-item" href="#">Action</a></li>
                <li><a class="dropdown-item" href="#">Another action</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Something else here</a></li>
              </ul>
            </li>
          </ul>
          <ul class="navbar-nav ms-auto mb-2 mb-lg-0 profile-menu"> 
            <li class="nav-item dropdown">
              <a class="nav-link" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="profile-pic">
                    <img src="<?php echo $profileImage; ?>" alt="Profile">
                 </div>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user fa-fw"></i> Profile</a></li>
                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog fa-fw"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item" href="login.php"><i class="fas fa-sign-in-alt fa-fw"></i> Login</a></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Log Out</a></li>
              </ul>
            </li>
         </ul>
        </div>
      </div>
    </nav>

<div class="container py-4">
    <h1 class="mb-4 text-white">My Media</h1>
    <div class="category-grid">
        <a href="home.php?type=movie" class="category-card text-decoration-none text-dark">
            <img src="images/movies.png" alt="Movies">
            <div class="mt-2 text-white">Movies</div>
        </a>
        <a href="tv.php?type=tv" class="category-card text-decoration-none text-dark">
            <img src="images/tv.png" alt="TV Shows">
            <div class="mt-2 text-white">TV</div>
        </a>
        <a href="music.php?type=music" class="category-card text-decoration-none text-dark">
            <img src="images/music.png" alt="Music">
            <div class="mt-2 text-white">Music</div>
        </a>
        <a href="other.php?type=other" class="category-card text-decoration-none text-dark">
            <img src="images/other.jpg" alt="Other">
            <div class="mt-2 text-white">Other</div>
        </a>
    </div>
</div>
<br><br>
<!-- footer -->
<div class="d-sm-flex justify-content-center bg-white">
    <div class="footer-bottom">
			<div class="container">
				<div class="row">
					<p>Copyright © 2025 - <?php echo date('Y');?> Minimedia. All rights reserved.</p>
					<p>Designed by <span><a target="_blank" href="#">MrMat</a></span></p>
				</div>
			</div>
		</div>
</div>
</body>
<script>
    document.querySelectorAll('.dropdown-toggle').forEach(item => {
      item.addEventListener('click', event => {
     
        if(event.target.classList.contains('dropdown-toggle') ){
          event.target.classList.toggle('toggle-change');
        }
        else if(event.target.parentElement.classList.contains('dropdown-toggle')){
          event.target.parentElement.classList.toggle('toggle-change');
        }
      })
    });
</script>
</html>
