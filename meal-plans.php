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

// Get user's active diet plan
$stmt = $conn->prepare("SELECT * FROM diet_plans WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$active_plan = $result->fetch_assoc();
$stmt->close();

// Get meal plans for the active diet plan
$meal_plans = [];
if ($active_plan) {
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE diet_plan_id = ? ORDER BY day_number ASC");
    $stmt->bind_param("i", $active_plan['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $meal_plans[] = $row;
    }
    $stmt->close();
}

// Get meals for each meal plan
foreach ($meal_plans as &$plan) {
    $stmt = $conn->prepare("SELECT * FROM meals WHERE meal_plan_id = ? ORDER BY FIELD(meal_type, 'breakfast', 'lunch', 'dinner', 'snack')");
    $stmt->bind_param("i", $plan['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $plan['meals'] = [];
    while ($row = $result->fetch_assoc()) {
        $plan['meals'][] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rencana Makan - WebDiet</title>
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
                    <li class="nav-item"><a href="meal-plans.php" class="nav-link active">Rencana Makan</a></li>
                    <li class="nav-item"><a href="progress.php" class="nav-link">Progres</a></li>
                    <li class="nav-item"><a href="profile.php" class="nav-link">Profil</a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link">Keluar</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header fade-in">
                <h1>Rencana Makan</h1>
                <p>Rencana makan harian yang disesuaikan dengan kebutuhan nutrisi Anda.</p>
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
            
            <?php if ($active_plan): ?>
                <div class="diet-plan-summary card fade-in">
                    <div class="card-header">
                        <h2><?php echo htmlspecialchars($active_plan['plan_name']); ?></h2>
                    </div>
                    <div class="card-body">
                        <p><?php echo htmlspecialchars($active_plan['description']); ?></p>
                        
                        <div class="macros-summary">
                            <div class="macro-item">
                                <div class="macro-icon"><i class="fas fa-fire"></i></div>
                                <div class="macro-value"><?php echo htmlspecialchars($active_plan['calorie_target']); ?></div>
                                <div class="macro-label">Kalori</div>
                            </div>
                            <div class="macro-item">
                                <div class="macro-icon"><i class="fas fa-drumstick-bite"></i></div>
                                <div class="macro-value"><?php echo htmlspecialchars($active_plan['protein_target']); ?>g</div>
                                <div class="macro-label">Protein</div>
                            </div>
                            <div class="macro-item">
                                <div class="macro-icon"><i class="fas fa-bread-slice"></i></div>
                                <div class="macro-value"><?php echo htmlspecialchars($active_plan['carbs_target']); ?>g</div>
                                <div class="macro-label">Karbohidrat</div>
                            </div>
                            <div class="macro-item">
                                <div class="macro-icon"><i class="fas fa-cheese"></i></div>
                                <div class="macro-value"><?php echo htmlspecialchars($active_plan['fat_target']); ?>g</div>
                                <div class="macro-label">Lemak</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="meal-plans-container">
                    <?php if (empty($meal_plans)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Belum ada rencana makan yang tersedia.
                        </div>
                    <?php else: ?>
                        <div class="meal-days-tabs">
                            <?php foreach ($meal_plans as $index => $plan): ?>
                                <div class="meal-day-tab <?php echo $index === 0 ? 'active' : ''; ?>" data-day="day-<?php echo $plan['day_number']; ?>">
                                    <div class="day-number">Hari <?php echo $plan['day_number']; ?></div>
                                    <div class="day-date"><?php echo date('d M', strtotime($plan['date'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="meal-days-content">
                            <?php foreach ($meal_plans as $index => $plan): ?>
                                <div class="meal-day-content <?php echo $index === 0 ? 'active' : ''; ?>" id="day-<?php echo $plan['day_number']; ?>">
                                    <div class="day-header">
                                        <h3>Rencana Makan - Hari <?php echo $plan['day_number']; ?></h3>
                                        <p>Total Kalori: <?php echo $plan['total_calories']; ?> kcal</p>
                                    </div>
                                    
                                    <?php if (empty($plan['meals'])): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Belum ada makanan yang direncanakan untuk hari ini.
                                        </div>
                                    <?php else: ?>
                                        <div class="meals-container">
                                            <?php foreach ($plan['meals'] as $meal): ?>
                                                <div class="meal-card card">
                                                    <div class="meal-header">
                                                        <div class="meal-type">
                                                            <?php if ($meal['meal_type'] === 'breakfast'): ?>
                                                                <i class="fas fa-coffee"></i> Sarapan
                                                            <?php elseif ($meal['meal_type'] === 'lunch'): ?>
                                                                <i class="fas fa-utensils"></i> Makan Siang
                                                            <?php elseif ($meal['meal_type'] === 'dinner'): ?>
                                                                <i class="fas fa-moon"></i> Makan Malam
                                                            <?php else: ?>
                                                                <i class="fas fa-apple-alt"></i> Camilan
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="meal-calories"><?php echo $meal['calories']; ?> kcal</div>
                                                    </div>
                                                    <div class="meal-body">
                                                        <h4><?php echo htmlspecialchars($meal['name']); ?></h4>
                                                        <p><?php echo htmlspecialchars($meal['description']); ?></p>
                                                        
                                                        <div class="meal-macros">
                                                            <div class="meal-macro">
                                                                <span class="macro-label">Protein:</span>
                                                                <span class="macro-value"><?php echo $meal['protein']; ?>g</span>
                                                            </div>
                                                            <div class="meal-macro">
                                                                <span class="macro-label">Karbo:</span>
                                                                <span class="macro-value"><?php echo $meal['carbs']; ?>g</span>
                                                            </div>
                                                            <div class="meal-macro">
                                                                <span class="macro-label">Lemak:</span>
                                                                <span class="macro-value"><?php echo $meal['fat']; ?>g</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if (!empty($meal['recipe'])): ?>
                                                            <div class="meal-recipe">
                                                                <h5>Resep:</h5>
                                                                <p><?php echo nl2br(htmlspecialchars($meal['recipe'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($meal['image_url'])): ?>
                                                            <div class="meal-image">
                                                                <img src="<?php echo htmlspecialchars($meal['image_url']); ?>" alt="<?php echo htmlspecialchars($meal['name']); ?>">
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-plan-container card fade-in">
                    <div class="card-body text-center">
                        <div class="no-plan-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h3>Belum Ada Rencana Makan Aktif</h3>
                        <p>Anda belum memiliki rencana makan aktif. Lengkapi profil Anda untuk mendapatkan rencana makan yang disesuaikan dengan kebutuhan Anda.</p>
                        <a href="profile.php" class="btn btn-primary">Lengkapi Profil</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> WebDiet - Program Diet Berbasis AI</p>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize meal day tabs
            const mealDayTabs = document.querySelectorAll('.meal-day-tab');
            const mealDayContents = document.querySelectorAll('.meal-day-content');
            
            if (mealDayTabs.length > 0) {
                mealDayTabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        // Remove active class from all tabs and contents
                        mealDayTabs.forEach(t => t.classList.remove('active'));
                        mealDayContents.forEach(c => c.classList.remove('active'));
                        
                        // Add active class to clicked tab
                        tab.classList.add('active');
                        
                        // Show the selected day content
                        const dayId = tab.getAttribute('data-day');
                        const activeContent = document.getElementById(dayId);
                        if (activeContent) {
                            activeContent.classList.add('active');
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>