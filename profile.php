<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$conn = connectDB();
$message = '';
$error = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Process profile update form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $gender = sanitizeInput($_POST['gender']);
    $birth_date = sanitizeInput($_POST['birth_date']);
    $height = floatval($_POST['height']);
    $current_weight = floatval($_POST['current_weight']);
    $target_weight = floatval($_POST['target_weight']);
    $activity_level = sanitizeInput($_POST['activity_level']);
    
    // Handle profile image upload
    $profile_image = $user['profile_image']; // Keep existing image by default
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file_name = $_FILES['profile_image']['name'];
        $file_size = $_FILES['profile_image']['size'];
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_type = $_FILES['profile_image']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = "Format file tidak didukung. Gunakan format JPG, PNG, atau GIF.";
        } 
        // Validate file size
        elseif ($file_size > $max_size) {
            $error = "Ukuran file terlalu besar. Maksimal 2MB.";
        } 
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'assets/uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old profile image if exists and not the default one
                if (!empty($user['profile_image']) && file_exists($user['profile_image']) && strpos($user['profile_image'], 'default') === false) {
                    unlink($user['profile_image']);
                }
                
                $profile_image = $upload_path;
            } else {
                $error = "Gagal mengunggah file. Silakan coba lagi.";
            }
        }
    }
    
    // Validate email format
    if (empty($error) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else if (empty($error)) {
        // Check if email already exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Email sudah digunakan oleh pengguna lain.";
        } else {
            $stmt->close();
            
            // Update user profile
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, gender = ?, birth_date = ?, height = ?, current_weight = ?, target_weight = ?, activity_level = ?, profile_image = ? WHERE id = ?");
            $stmt->bind_param("ssssdddssi", $full_name, $email, $gender, $birth_date, $height, $current_weight, $target_weight, $activity_level, $profile_image, $user_id);
            
            if ($stmt->execute()) {
                $message = "Profil berhasil diperbarui.";
                
                // Record weight in progress table if current_weight is provided
                if ($current_weight > 0) {
                    $today = date('Y-m-d');
                    
                    // Calculate BMI if height is provided
                    $bmi = null;
                    if ($height > 0) {
                        $height_m = $height / 100; // Convert cm to m
                        $bmi = $current_weight / ($height_m * $height_m);
                    }
                    
                    // Check if there's already an entry for today
                    $check_stmt = $conn->prepare("SELECT id FROM user_progress WHERE user_id = ? AND date = ?");
                    $check_stmt->bind_param("is", $user_id, $today);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    
                    if ($check_stmt->num_rows > 0) {
                        // Update existing record
                        $check_stmt->bind_result($progress_id);
                        $check_stmt->fetch();
                        $check_stmt->close();
                        
                        $update_stmt = $conn->prepare("UPDATE user_progress SET weight = ?, bmi = ? WHERE id = ?");
                        $update_stmt->bind_param("ddi", $current_weight, $bmi, $progress_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } else {
                        // Insert new record
                        $insert_stmt = $conn->prepare("INSERT INTO user_progress (user_id, date, weight, bmi) VALUES (?, ?, ?, ?)");
                        $insert_stmt->bind_param("isdd", $user_id, $today, $current_weight, $bmi);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                    
                    // Generate AI diet plan if all required fields are filled
                    if ($height > 0 && $current_weight > 0 && $target_weight > 0 && !empty($gender) && !empty($birth_date) && !empty($activity_level)) {
                        // Calculate age from birth date
                        $birth_date_obj = new DateTime($birth_date);
                        $today_obj = new DateTime();
                        $age = $birth_date_obj->diff($today_obj)->y;
                        
                        // Generate diet plan using AI algorithm (simplified version)
                        generateAIDietPlan($conn, $user_id, $gender, $age, $height, $current_weight, $target_weight, $activity_level);
                    }
                }
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
            } else {
                $error = "Terjadi kesalahan saat memperbarui profil.";
            }
        }
    }
}

// Process password change form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field password harus diisi.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru harus minimal 6 karakter.";
    } else {
        // Verify current password
        if (verifyPassword($current_password, $user['password'])) {
            // Hash new password
            $hashed_password = hashPassword($new_password);
            
            // Update password
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $message = "Password berhasil diubah.";
            } else {
                $error = "Terjadi kesalahan saat mengubah password.";
            }
            
            $stmt->close();
        } else {
            $error = "Password saat ini tidak valid.";
        }
    }
}

// Function to generate AI diet plan
function generateAIDietPlan($conn, $user_id, $gender, $age, $height, $current_weight, $target_weight, $activity_level) {
    // Calculate BMR (Basal Metabolic Rate) using Mifflin-St Jeor Equation
    if ($gender == 'male') {
        $bmr = 10 * $current_weight + 6.25 * $height - 5 * $age + 5;
    } else {
        $bmr = 10 * $current_weight + 6.25 * $height - 5 * $age - 161;
    }
    
    // Calculate TDEE (Total Daily Energy Expenditure) based on activity level
    $activity_multipliers = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'active' => 1.725,
        'very_active' => 1.9
    ];
    
    $tdee = $bmr * $activity_multipliers[$activity_level];
    
    // Calculate calorie target based on weight goal
    $weight_diff = $current_weight - $target_weight;
    $calorie_target = 0;
    
    if ($weight_diff > 0) {
        // Weight loss: 500-1000 calorie deficit
        $deficit = min(1000, max(500, $weight_diff * 100));
        $calorie_target = max(1200, $tdee - $deficit); // Minimum 1200 calories
    } elseif ($weight_diff < 0) {
        // Weight gain: 300-500 calorie surplus
        $surplus = min(500, max(300, abs($weight_diff) * 100));
        $calorie_target = $tdee + $surplus;
    } else {
        // Maintain weight
        $calorie_target = $tdee;
    }
    
    // Calculate macronutrient targets
    $protein_target = round($current_weight * 2); // 2g per kg of body weight
    $fat_target = round(($calorie_target * 0.25) / 9); // 25% of calories from fat
    $carbs_target = round(($calorie_target - ($protein_target * 4) - ($fat_target * 9)) / 4); // Remaining calories from carbs
    
    // Create plan name and description
    $plan_type = $weight_diff > 0 ? "Penurunan Berat Badan" : ($weight_diff < 0 ? "Penambahan Berat Badan" : "Pemeliharaan Berat Badan");
    $plan_name = "Program $plan_type Kustom";
    $description = "Program diet personal yang disesuaikan dengan profil Anda. ";
    $description .= "Target kalori harian: $calorie_target kcal dengan distribusi makronutrien: ";
    $description .= "$protein_target g protein, $carbs_target g karbohidrat, dan $fat_target g lemak.";
    
    // Check if there's an existing active plan
    $stmt = $conn->prepare("SELECT id FROM diet_plans WHERE user_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        // Update existing plan to inactive
        $stmt->close();
        $update_stmt = $conn->prepare("UPDATE diet_plans SET status = 'completed' WHERE user_id = ? AND status = 'active'");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        $stmt->close();
    }
    
    // Create new diet plan
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+30 days'));
    
    $stmt = $conn->prepare("INSERT INTO diet_plans (user_id, plan_name, description, calorie_target, protein_target, carbs_target, fat_target, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("issiiiiss", $user_id, $plan_name, $description, $calorie_target, $protein_target, $carbs_target, $fat_target, $start_date, $end_date);
    $stmt->execute();
    $diet_plan_id = $stmt->insert_id;
    $stmt->close();
    
    // Generate meal plans for the first week (simplified)
    for ($day = 0; $day < 7; $day++) {
        $date = date('Y-m-d', strtotime("+$day days"));
        
        // Create meal plan for the day
        $stmt = $conn->prepare("INSERT INTO meal_plans (diet_plan_id, day_number, date, total_calories) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $diet_plan_id, $day + 1, $date, $calorie_target);
        $stmt->execute();
        $meal_plan_id = $stmt->insert_id;
        $stmt->close();
        
        // Create sample meals (simplified)
        $meals = [
            ['breakfast', 'Sarapan Sehat', 'Oatmeal dengan buah dan yogurt', round($calorie_target * 0.25), round($protein_target * 0.2), round($carbs_target * 0.3), round($fat_target * 0.15)],
            ['lunch', 'Makan Siang Bernutrisi', 'Nasi merah dengan ayam panggang dan sayuran', round($calorie_target * 0.35), round($protein_target * 0.4), round($carbs_target * 0.4), round($fat_target * 0.3)],
            ['dinner', 'Makan Malam Seimbang', 'Ikan panggang dengan quinoa dan sayuran hijau', round($calorie_target * 0.3), round($protein_target * 0.35), round($carbs_target * 0.25), round($fat_target * 0.4)],
            ['snack', 'Camilan Sehat', 'Buah-buahan segar dan kacang-kacangan', round($calorie_target * 0.1), round($protein_target * 0.05), round($carbs_target * 0.05), round($fat_target * 0.15)]
        ];
        
        foreach ($meals as $meal) {
            $stmt = $conn->prepare("INSERT INTO meals (meal_plan_id, meal_type, name, description, calories, protein, carbs, fat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $meal_type = $meal[0];
            $meal_name = $meal[1];
            $meal_desc = $meal[2];
            $meal_calories = $meal[3];
            $meal_protein = $meal[4];
            $meal_carbs = $meal[5];
            $meal_fat = $meal[6];
            $stmt->bind_param("isssiiii", $meal_plan_id, $meal_type, $meal_name, $meal_desc, $meal_calories, $meal_protein, $meal_carbs, $meal_fat);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    return true;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - WebDiet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="navbar-brand">Web<span>Diet</span></a>
                <ul class="navbar-nav">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                    <li class="nav-item"><a href="meal-plans.php" class="nav-link">Rencana Makan</a></li>
                    <li class="nav-item"><a href="progress.php" class="nav-link">Progres</a></li>
                    <li class="nav-item"><a href="profile.php" class="nav-link active">Profil</a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link">Keluar</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="profile-container fade-in">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['full_name'] ?: $username); ?></h2>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><i class="fas fa-calendar-alt"></i> Bergabung sejak <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-tabs">
                    <div class="profile-tab active" data-tab="profile-info">Informasi Profil</div>
                    <div class="profile-tab" data-tab="change-password">Ubah Password</div>
                </div>
                
                <div class="tab-content" id="profile-info">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="profile_image" class="form-label">Foto Profil</label>
                            <div class="profile-image-upload">
                                <div class="current-image">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image" class="preview-image">
                                    <?php else: ?>
                                        <div class="avatar-placeholder preview-image">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="upload-controls">
                                    <input type="file" id="profile_image" name="profile_image" class="form-control-file" accept="image/jpeg,image/png,image/gif">
                                    <p class="upload-help">Format yang didukung: JPG, PNG, GIF. Maksimal 2MB.</p>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name" class="form-label">Nama Lengkap</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="gender" class="form-label">Jenis Kelamin</label>
                                <select id="gender" name="gender" class="form-control">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="male" <?php echo ($user['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Laki-laki</option>
                                    <option value="female" <?php echo ($user['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Perempuan</option>
                                    <option value="other" <?php echo ($user['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="birth_date" class="form-label">Tanggal Lahir</label>
                                <input type="date" id="birth_date" name="birth_date" class="form-control" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="height" class="form-label">Tinggi Badan (cm)</label>
                                <input type="number" id="height" name="height" class="form-control" step="0.1" min="0" value="<?php echo htmlspecialchars($user['height'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="current_weight" class="form-label">Berat Badan Saat Ini (kg)</label>
                                <input type="number" id="current_weight" name="current_weight" class="form-control" step="0.1" min="0" value="<?php echo htmlspecialchars($user['current_weight'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="target_weight" class="form-label">Target Berat Badan (kg)</label>
                                <input type="number" id="target_weight" name="target_weight" class="form-control" step="0.1" min="0" value="<?php echo htmlspecialchars($user['target_weight'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="activity_level" class="form-label">Tingkat Aktivitas</label>
                            <select id="activity_level" name="activity_level" class="form-control">
                                <option value="">Pilih Tingkat Aktivitas</option>
                                <option value="sedentary" <?php echo ($user['activity_level'] ?? '') == 'sedentary' ? 'selected' : ''; ?>>Sedentary (Jarang Berolahraga)</option>
                                <option value="light" <?php echo ($user['activity_level'] ?? '') == 'light' ? 'selected' : ''; ?>>Light (Olahraga Ringan 1-3 hari/minggu)</option>
                                <option value="moderate" <?php echo ($user['activity_level'] ?? '') == 'moderate' ? 'selected' : ''; ?>>Moderate (Olahraga Sedang 3-5 hari/minggu)</option>
                                <option value="active" <?php echo ($user['activity_level'] ?? '') == 'active' ? 'selected' : ''; ?>>Active (Olahraga Berat 6-7 hari/minggu)</option>
                                <option value="very_active" <?php echo ($user['activity_level'] ?? '') == 'very_active' ? 'selected' : ''; ?>>Very Active (Atlet/Olahraga 2x sehari)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <input type="hidden" name="update_profile" value="1">
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
                
                <div class="tab-content" id="change-password" style="display: none;">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <input type="hidden" name="change_password" value="1">
                            <button type="submit" class="btn btn-primary">Ubah Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> WebDiet - Program Diet Berbasis AI</p>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>