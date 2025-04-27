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

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user's active diet plan
$stmt = $conn->prepare("SELECT * FROM diet_plans WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$active_plan = $result->fetch_assoc();
$stmt->close();

// Get user's progress data for chart
$stmt = $conn->prepare("SELECT date, weight FROM user_progress WHERE user_id = ? ORDER BY date ASC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$progress_result = $stmt->get_result();
$progress_data = [];
$progress_labels = [];

while ($row = $progress_result->fetch_assoc()) {
    $progress_labels[] = date('d M', strtotime($row['date']));
    $progress_data[] = $row['weight'];
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WebDiet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="dashboard.php" class="navbar-brand">Web<span>Diet</span></a>
                <ul class="navbar-nav">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
                    <li class="nav-item"><a href="meal-plans.php" class="nav-link">Rencana Makan</a></li>
                    <li class="nav-item"><a href="progress.php" class="nav-link">Progres</a></li>
                    <li class="nav-item"><a href="profile.php" class="nav-link">Profil</a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link">Keluar</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1>Selamat Datang, <?php echo htmlspecialchars($user['full_name'] ?: $username); ?>!</h1>
                <p>Berikut adalah ringkasan program diet Anda</p>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Berat Saat Ini</h3>
                    <div class="value"><?php echo $user['current_weight'] ? $user['current_weight'] . ' kg' : '-'; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Target Berat</h3>
                    <div class="value"><?php echo $user['target_weight'] ? $user['target_weight'] . ' kg' : '-'; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Selisih</h3>
                    <div class="value">
                        <?php 
                        if ($user['current_weight'] && $user['target_weight']) {
                            $diff = $user['current_weight'] - $user['target_weight'];
                            echo abs($diff) . ' kg ' . ($diff > 0 ? '<i class="fas fa-arrow-down" style="color: var(--primary-color);"></i>' : '<i class="fas fa-arrow-up" style="color: var(--danger-color);"></i>');
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Kalori Harian</h3>
                    <div class="value"><?php echo $active_plan ? $active_plan['calorie_target'] . ' kcal' : '-'; ?></div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="content-card">
                    <h2>Progres Diet Anda</h2>
                    <?php if (count($progress_data) > 0): ?>
                        <div class="progress-chart">
                            <canvas id="weightChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <p>Belum ada data progres. Mulai catat berat badan Anda untuk melihat grafik progres.</p>
                            <a href="progress.php" class="btn btn-primary">Catat Progres</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content-card">
                    <h2>Rekomendasi AI</h2>
                    <?php if ($active_plan): ?>
                        <div class="ai-recommendation">
                            <h3><?php echo htmlspecialchars($active_plan['plan_name']); ?></h3>
                            <p><?php echo htmlspecialchars($active_plan['description']); ?></p>
                            <div class="nutrient-targets">
                                <div class="nutrient">
                                    <span class="label">Protein</span>
                                    <span class="value"><?php echo $active_plan['protein_target']; ?>g</span>
                                </div>
                                <div class="nutrient">
                                    <span class="label">Karbohidrat</span>
                                    <span class="value"><?php echo $active_plan['carbs_target']; ?>g</span>
                                </div>
                                <div class="nutrient">
                                    <span class="label">Lemak</span>
                                    <span class="value"><?php echo $active_plan['fat_target']; ?>g</span>
                                </div>
                            </div>
                            <a href="meal-plans.php" class="btn btn-primary">Lihat Rencana Makan</a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-brain"></i>
                            <p>Anda belum memiliki rencana diet. Lengkapi profil Anda untuk mendapatkan rekomendasi diet personal dari AI.</p>
                            <a href="profile.php" class="btn btn-primary">Lengkapi Profil</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content-card">
                <h2>Tips Diet Hari Ini</h2>
                <div class="diet-tips">
                    <?php 
                    $tips = [
                        "Minum setidaknya 8 gelas air putih setiap hari untuk menjaga hidrasi dan membantu metabolisme.",
                        "Konsumsi makanan tinggi serat untuk membuat Anda merasa kenyang lebih lama.",
                        "Batasi konsumsi gula dan makanan olahan untuk hasil diet yang lebih baik.",
                        "Lakukan aktivitas fisik minimal 30 menit setiap hari untuk membakar kalori.",
                        "Tidur yang cukup (7-8 jam) dapat membantu mengontrol hormon yang terkait dengan rasa lapar."
                    ];
                    
                    // Display random tip
                    $random_tip = $tips[array_rand($tips)];
                    echo "<div class='tip'><i class='fas fa-lightbulb'></i> <p>$random_tip</p></div>";
                    ?>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> WebDiet - Program Diet Berbasis AI</p>
        </div>
    </footer>

    <script>
    <?php if (count($progress_data) > 0): ?>
    // Weight progress chart
    const ctx = document.getElementById('weightChart').getContext('2d');
    const weightChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($progress_labels); ?>,
            datasets: [{
                label: 'Berat Badan (kg)',
                data: <?php echo json_encode($progress_data); ?>,
                backgroundColor: 'rgba(76, 175, 80, 0.2)',
                borderColor: 'rgba(76, 175, 80, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgba(76, 175, 80, 1)',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    <?php endif; ?>
    </script>

    <script src="assets/js/script.js"></script>
</body>
</html>