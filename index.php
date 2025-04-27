<?php
session_start();
require_once 'config/database.php';

// Redirect to dashboard if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebDiet - Program Diet Berbasis AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="landing-page">
            <div class="landing-content">
                <div class="logo-container">
                    <h1>Web<span>Diet</span></h1>
                </div>
                <h2>Program Diet Cerdas Berbasis AI</h2>
                <p>Dapatkan rekomendasi diet personal yang disesuaikan dengan kebutuhan dan tujuan Anda. Pantau kemajuan dan capai target berat badan ideal Anda dengan bantuan teknologi AI.</p>
                <div class="cta-buttons">
                    <a href="login.php" class="btn btn-primary">Masuk</a>
                    <a href="register.php" class="btn btn-secondary">Daftar</a>
                </div>
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-brain"></i>
                        <h3>Rekomendasi AI</h3>
                        <p>Diet plan yang dipersonalisasi berdasarkan data Anda</p>
                    </div>
                    <div class="feature">
                        <i class="fas fa-chart-line"></i>
                        <h3>Pantau Progres</h3>
                        <p>Lacak kemajuan diet Anda secara real-time</p>
                    </div>
                    <div class="feature">
                        <i class="fas fa-utensils"></i>
                        <h3>Rencana Makan</h3>
                        <p>Dapatkan menu harian yang sesuai dengan target Anda</p>
                    </div>
                </div>
            </div>
            <div class="landing-image">
                <img src="assets/images/diet-illustration.svg" alt="WebDiet Illustration">
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> WebDiet - Program Diet Berbasis AI</p>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>