<?php
session_start();
require_once 'config/database.php';

// Redirect to dashboard if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Process registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = connectDB();
    
    // Get form data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    $gender = isset($_POST['gender']) ? sanitizeInput($_POST['gender']) : '';
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Semua field wajib diisi.";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Username sudah digunakan. Silakan pilih username lain.";
        } else {
            $stmt->close();
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = "Email sudah terdaftar. Silakan gunakan email lain.";
            } else {
                $stmt->close();
                
                // Hash password
                $hashed_password = hashPassword($password);
                
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, gender) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $email, $hashed_password, $full_name, $gender);
                
                if ($stmt->execute()) {
                    $success = "Pendaftaran berhasil! Silakan login dengan akun Anda.";
                    // Redirect to login page after 3 seconds
                    header("refresh:3;url=login.php");
                } else {
                    $error = "Terjadi kesalahan saat mendaftar. Silakan coba lagi.";
                }
            }
        }
        
        $stmt->close();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - WebDiet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="auth-container fade-in">
            <div class="auth-header">
                <a href="index.php" class="navbar-brand">Web<span>Diet</span></a>
                <h2>Buat Akun Baru</h2>
                <p>Daftar untuk mendapatkan rekomendasi diet personal berbasis AI</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Masukkan email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="full_name" class="form-label">Nama Lengkap</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-id-card"></i></span>
                        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Masukkan nama lengkap">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Jenis Kelamin</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="gender" value="male"> Laki-laki
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="gender" value="female"> Perempuan
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="gender" value="other"> Lainnya
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password (min. 6 karakter)" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Konfirmasi password" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">Daftar</button>
                </div>
            </form>
            
            <div class="auth-footer">
                <p>Sudah punya akun? <a href="login.php">Masuk sekarang</a></p>
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