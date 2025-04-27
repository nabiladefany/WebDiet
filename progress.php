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

// Get user's progress data
$stmt = $conn->prepare("SELECT * FROM user_progress WHERE user_id = ? ORDER BY date ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$progress_data = [];
$weight_data = [];
$bmi_data = [];
$date_labels = [];

while ($row = $result->fetch_assoc()) {
    $progress_data[] = $row;
    $weight_data[] = $row['weight'];
    $bmi_data[] = $row['bmi'];
    $date_labels[] = date('d M', strtotime($row['date']));
}

$stmt->close();

// Process add progress form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_progress'])) {
    $date = sanitizeInput($_POST['date']);
    $weight = floatval($_POST['weight']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Calculate BMI if height is available
    $bmi = null;
    if ($user['height'] > 0) {
        $height_m = $user['height'] / 100; // Convert cm to m
        $bmi = $weight / ($height_m * $height_m);
    }
    
    // Check if there's already an entry for this date
    $check_stmt = $conn->prepare("SELECT id FROM user_progress WHERE user_id = ? AND date = ?");
    $check_stmt->bind_param("is", $user_id, $date);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        // Update existing record
        $check_stmt->bind_result($progress_id);
        $check_stmt->fetch();
        $check_stmt->close();
        
        $update_stmt = $conn->prepare("UPDATE user_progress SET weight = ?, bmi = ?, notes = ? WHERE id = ?");
        $update_stmt->bind_param("ddsi", $weight, $bmi, $notes, $progress_id);
        
        if ($update_stmt->execute()) {
            $message = "Data progres berhasil diperbarui.";
        } else {
            $error = "Terjadi kesalahan saat memperbarui data progres.";
        }
        
        $update_stmt->close();
    } else {
        // Insert new record
        $insert_stmt = $conn->prepare("INSERT INTO user_progress (user_id, date, weight, bmi, notes) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("isdds", $user_id, $date, $weight, $bmi, $notes);
        
        if ($insert_stmt->execute()) {
            $message = "Data progres berhasil ditambahkan.";
        } else {
            $error = "Terjadi kesalahan saat menambahkan data progres.";
        }
        
        $insert_stmt->close();
    }
    
    // Update current weight in user profile
    $update_user_stmt = $conn->prepare("UPDATE users SET current_weight = ? WHERE id = ?");
    $update_user_stmt->bind_param("di", $weight, $user_id);
    $update_user_stmt->execute();
    $update_user_stmt->close();
    
    // Refresh progress data
    $stmt = $conn->prepare("SELECT * FROM user_progress WHERE user_id = ? ORDER BY date ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $progress_data = [];
    $weight_data = [];
    $bmi_data = [];
    $date_labels = [];
    
    while ($row = $result->fetch_assoc()) {
        $progress_data[] = $row;
        $weight_data[] = $row['weight'];
        $bmi_data[] = $row['bmi'];
        $date_labels[] = date('d M', strtotime($row['date']));
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
    <title>Progres - WebDiet</title>
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
                    <li class="nav-item"><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                    <li class="nav-item"><a href="meal-plans.php" class="nav-link">Rencana Makan</a></li>
                    <li class="nav-item"><a href="progress.php" class="nav-link active">Progres</a></li>
                    <li class="nav-item"><a href="profile.php" class="nav-link">Profil</a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link">Keluar</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header fade-in">
                <h1>Progres Berat Badan</h1>
                <p>Pantau perkembangan berat badan dan BMI Anda dari waktu ke waktu.</p>
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
            
            <div class="progress-container">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h2>Grafik Perkembangan</h2>
                            </div>
                            <div class="card-body">
                                <?php if (count($progress_data) > 0): ?>
                                    <div class="chart-container">
                                        <canvas id="progressChart"></canvas>
                                    </div>
                                <?php else: ?>
                                    <div class="no-data-message">
                                        <i class="fas fa-chart-line"></i>
                                        <p>Belum ada data progres. Tambahkan data berat badan Anda untuk melihat grafik perkembangan.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (count($progress_data) > 0): ?>
                            <div class="card fade-in">
                                <div class="card-header">
                                    <h2>Riwayat Progres</h2>
                                </div>
                                <div class="card-body">
                                    <div class="progress-history">
                                        <table class="progress-table">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Berat (kg)</th>
                                                    <th>BMI</th>
                                                    <th>Catatan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_reverse($progress_data) as $progress): ?>
                                                    <tr>
                                                        <td><?php echo date('d M Y', strtotime($progress['date'])); ?></td>
                                                        <td><?php echo number_format($progress['weight'], 1); ?> kg</td>
                                                        <td>
                                                            <?php if ($progress['bmi']): ?>
                                                                <?php echo number_format($progress['bmi'], 1); ?>
                                                                <span class="bmi-category">
                                                                    <?php 
                                                                    $bmi = $progress['bmi'];
                                                                    if ($bmi < 18.5) {
                                                                        echo '(Kurus)';
                                                                    } elseif ($bmi < 25) {
                                                                        echo '(Normal)';
                                                                    } elseif ($bmi < 30) {
                                                                        echo '(Gemuk)';
                                                                    } else {
                                                                        echo '(Obesitas)';
                                                                    }
                                                                    ?>
                                                                </span>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($progress['notes'] ?: '-'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card fade-in">
                            <div class="card-header">
                                <h2>Tambah Data Progres</h2>
                            </div>
                            <div class="card-body">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <div class="form-group">
                                        <label for="date" class="form-label">Tanggal</label>
                                        <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="weight" class="form-label">Berat Badan (kg)</label>
                                        <input type="number" id="weight" name="weight" class="form-control" step="0.1" min="0" value="<?php echo htmlspecialchars($user['current_weight'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="notes" class="form-label">Catatan</label>
                                        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <input type="hidden" name="add_progress" value="1">
                                        <button type="submit" class="btn btn-primary">Simpan Data</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <?php if ($user['height'] > 0 && $user['current_weight'] > 0): ?>
                            <div class="card fade-in">
                                <div class="card-header">
                                    <h2>Status BMI Saat Ini</h2>
                                </div>
                                <div class="card-body">
                                    <?php 
                                    $height_m = $user['height'] / 100;
                                    $current_bmi = $user['current_weight'] / ($height_m * $height_m);
                                    $bmi_category = '';
                                    $bmi_color = '';
                                    
                                    if ($current_bmi < 18.5) {
                                        $bmi_category = 'Kurus';
                                        $bmi_color = 'var(--warning-color)';
                                    } elseif ($current_bmi < 25) {
                                        $bmi_category = 'Normal';
                                        $bmi_color = 'var(--success-color)';
                                    } elseif ($current_bmi < 30) {
                                        $bmi_category = 'Gemuk';
                                        $bmi_color = 'var(--warning-color)';
                                    } else {
                                        $bmi_category = 'Obesitas';
                                        $bmi_color = 'var(--danger-color)';
                                    }
                                    ?>
                                    
                                    <div class="bmi-display">
                                        <div class="bmi-value" style="color: <?php echo $bmi_color; ?>">
                                            <?php echo number_format($current_bmi, 1); ?>
                                        </div>
                                        <div class="bmi-category" style="color: <?php echo $bmi_color; ?>">
                                            <?php echo $bmi_category; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="bmi-scale">
                                        <div class="bmi-range underweight">
                                            <div class="range-label">Kurus</div>
                                            <div class="range-value">&lt;18.5</div>
                                        </div>
                                        <div class="bmi-range normal">
                                            <div class="range-label">Normal</div>
                                            <div class="range-value">18.5-24.9</div>
                                        </div>
                                        <div class="bmi-range overweight">
                                            <div class="range-label">Gemuk</div>
                                            <div class="range-value">25-29.9</div>
                                        </div>
                                        <div class="bmi-range obese">
                                            <div class="range-label">Obesitas</div>
                                            <div class="range-value">&gt;30</div>
                                        </div>
                                    </div>
                                    
                                    <div class="bmi-info">
                                        <p>BMI (Body Mass Index) adalah pengukuran yang digunakan untuk menilai apakah seseorang memiliki berat badan yang sehat berdasarkan tinggi badannya.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (count($progress_data) > 0): ?>
            // Initialize progress chart
            const ctx = document.getElementById('progressChart').getContext('2d');
            const progressChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($date_labels); ?>,
                    datasets: [
                        {
                            label: 'Berat Badan (kg)',
                            data: <?php echo json_encode($weight_data); ?>,
                            backgroundColor: 'rgba(76, 175, 80, 0.2)',
                            borderColor: 'rgba(76, 175, 80, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(76, 175, 80, 1)',
                            pointRadius: 4
                        },
                        <?php if (!empty($bmi_data) && !in_array(null, $bmi_data)): ?>
                        {
                            label: 'BMI',
                            data: <?php echo json_encode($bmi_data); ?>,
                            backgroundColor: 'rgba(33, 150, 243, 0.2)',
                            borderColor: 'rgba(33, 150, 243, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(33, 150, 243, 1)',
                            pointRadius: 4,
                            yAxisID: 'y1'
                        }
                        <?php endif; ?>
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Berat Badan (kg)'
                            }
                        },
                        <?php if (!empty($bmi_data) && !in_array(null, $bmi_data)): ?>
                        y1: {
                            position: 'right',
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'BMI'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                        <?php endif; ?>
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        },
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>