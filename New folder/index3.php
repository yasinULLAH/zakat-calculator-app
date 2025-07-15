<?php
// Initialize session and database 
session_start();
$db = new SQLite3('zakat.db');

// Create tables if they don't exist
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'user',
    email TEXT UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS prices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    gold_price REAL NOT NULL,
    silver_price REAL NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    gold_tola REAL DEFAULT 0,
    silver_tola REAL DEFAULT 0,
    cash REAL DEFAULT 0,
    business_goods REAL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS calculations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    gold_tola REAL DEFAULT 0,
    silver_tola REAL DEFAULT 0,
    cash REAL DEFAULT 0,
    business_goods REAL DEFAULT 0,
    gold_price REAL NOT NULL,
    silver_price REAL NOT NULL,
    zakat_amount REAL NOT NULL,
    calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    threshold REAL,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    message TEXT NOT NULL,
    is_read INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Insert initial data if needed
$result = $db->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row['count'] == 0) {
    $db->exec("INSERT INTO users (username, password, role, email) VALUES ('admin', '".password_hash('admin', PASSWORD_DEFAULT)."', 'admin', 'admin@example.com')");
}

$result = $db->query("SELECT COUNT(*) as count FROM prices");
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row['count'] == 0) {
    $db->exec("INSERT INTO prices (gold_price, silver_price) VALUES (150000, 2000)");
}

// Helper functions
function getCurrentPrices() {
    global $db;
    $result = $db->query("SELECT * FROM prices ORDER BY id DESC LIMIT 1");
    return $result->fetchArray(SQLITE3_ASSOC);
}

function calculateZakat($gold_tola, $silver_tola, $cash, $business_goods, $prices) {
    $gold_nisab = 7.5;
    $silver_nisab = 52.5;
    
    $gold_value = $gold_tola * $prices['gold_price'];
    $silver_value = $silver_tola * $prices['silver_price'];
    $total_wealth = $gold_value + $silver_value + $cash + $business_goods;
    $silver_nisab_value = $silver_nisab * $prices['silver_price'];
    
    // Check if zakat is due
    $is_zakat_due = false;
    
    // Condition 1: Gold meets or exceeds its nisab
    if ($gold_tola >= $gold_nisab) {
        $is_zakat_due = true;
    }
    
    // Condition 2: Silver meets or exceeds its nisab
    if ($silver_tola >= $silver_nisab) {
        $is_zakat_due = true;
    }
    
    // Condition 3: Total wealth meets or exceeds silver nisab value
    if ($total_wealth >= $silver_nisab_value) {
        $is_zakat_due = true;
    }
    
    // Calculate zakat amount (2.5% of zakatable wealth)
    $zakat_amount = $is_zakat_due ? $total_wealth * 0.025 : 0;
    
    return [
        'is_due' => $is_zakat_due,
        'amount' => $zakat_amount,
        'nisab_silver' => $silver_nisab_value,
        'total_wealth' => $total_wealth
    ];
}

function saveCalculation($user_id, $gold_tola, $silver_tola, $cash, $business_goods, $zakat_amount) {
    global $db;
    $prices = getCurrentPrices();
    $stmt = $db->prepare("INSERT INTO calculations (user_id, gold_tola, silver_tola, cash, business_goods, gold_price, silver_price, zakat_amount) 
                           VALUES (:user_id, :gold_tola, :silver_tola, :cash, :business_goods, :gold_price, :silver_price, :zakat_amount)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':gold_tola', $gold_tola, SQLITE3_FLOAT);
    $stmt->bindValue(':silver_tola', $silver_tola, SQLITE3_FLOAT);
    $stmt->bindValue(':cash', $cash, SQLITE3_FLOAT);
    $stmt->bindValue(':business_goods', $business_goods, SQLITE3_FLOAT);
    $stmt->bindValue(':gold_price', $prices['gold_price'], SQLITE3_FLOAT);
    $stmt->bindValue(':silver_price', $prices['silver_price'], SQLITE3_FLOAT);
    $stmt->bindValue(':zakat_amount', $zakat_amount, SQLITE3_FLOAT);
    $stmt->execute();
}

function createNotification($user_id, $message) {
    global $db;
    $stmt = $db->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->execute();
}

function getUserAssets($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM assets WHERE user_id = :user_id ORDER BY id DESC LIMIT 1");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$row) {
        // Create default entry if none exists
        $db->exec("INSERT INTO assets (user_id) VALUES ('$user_id')");
        return [
            'gold_tola' => 0, 
            'silver_tola' => 0, 
            'cash' => 0, 
            'business_goods' => 0
        ];
    }
    
    return $row;
}

function checkUserAlerts() {
    global $db;
    if (!isset($_SESSION['user_id'])) return;
    
    $user_id = $_SESSION['user_id'];
    $prices = getCurrentPrices();
    $assets = getUserAssets($user_id);
    
    // Check nisab alerts
    $stmt = $db->prepare("SELECT * FROM alerts WHERE user_id = :user_id AND active = 1 AND type = 'nisab'");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($alert = $result->fetchArray(SQLITE3_ASSOC)) {
        $zakat_info = calculateZakat(
            $assets['gold_tola'], 
            $assets['silver_tola'], 
            $assets['cash'], 
            $assets['business_goods'], 
            $prices
        );
        
        if ($zakat_info['is_due']) {
            createNotification($user_id, "آپ کی دولت نصاب کی حد تک پہنچ گئی ہے۔ آپ کے اثاثوں پر زکاۃ واجب ہو سکتی ہے۔");
            
            // Deactivate this alert
            $db->exec("UPDATE alerts SET active = 0 WHERE id = " . $alert['id']);
        }
    }
    
    // Check price alerts
    $stmt = $db->prepare("SELECT * FROM alerts WHERE user_id = :user_id AND active = 1 AND (type = 'gold_price' OR type = 'silver_price')");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($alert = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($alert['type'] == 'gold_price' && $prices['gold_price'] >= $alert['threshold']) {
            createNotification($user_id, "سونے کی قیمت الرٹ: موجودہ قیمت آپ کی مقررہ حد " . $alert['threshold'] . " تک پہنچ گئی ہے");
            $db->exec("UPDATE alerts SET active = 0 WHERE id = " . $alert['id']);
        } else if ($alert['type'] == 'silver_price' && $prices['silver_price'] >= $alert['threshold']) {
            createNotification($user_id, "چاندی کی قیمت الرٹ: موجودہ قیمت آپ کی مقررہ حد " . $alert['threshold'] . " تک پہنچ گئی ہے");
            $db->exec("UPDATE alerts SET active = 0 WHERE id = " . $alert['id']);
        }
    }
}

// Process forms
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle login
    if (isset($_POST['login'])) {
        $username = SQLite3::escapeString($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $message = "لاگ ان کامیاب!";
        } else {
            $error = "غلط یوزرنیم یا پاس ورڈ";
        }
    }
    
    // Handle registration
    if (isset($_POST['register'])) {
        $username = SQLite3::escapeString($_POST['username']);
        $password = $_POST['password'];
        $email = SQLite3::escapeString($_POST['email']);
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row['count'] > 0) {
            $error = "یوزرنیم یا ای میل پہلے سے موجود ہے";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, email) VALUES (:username, :password, :email)");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->execute();
            
            $message = "رجسٹریشن کامیاب! آپ اب لاگ ان کر سکتے ہیں۔";
        }
    }
    
    // Handle guest calculation
    if (isset($_POST['guest_calculate'])) {
        $gold_tola = floatval($_POST['gold_tola'] ?? 0);
        $silver_tola = floatval($_POST['silver_tola'] ?? 0);
        $cash = floatval($_POST['cash'] ?? 0);
        $business_goods = floatval($_POST['business_goods'] ?? 0);
        
        $prices = getCurrentPrices();
        $zakat_info = calculateZakat($gold_tola, $silver_tola, $cash, $business_goods, $prices);
        
        $_SESSION['guest_calculation'] = [
            'gold_tola' => $gold_tola,
            'silver_tola' => $silver_tola,
            'cash' => $cash,
            'business_goods' => $business_goods,
            'zakat_info' => $zakat_info
        ];
    }
    
    // Handle user assets update
    if (isset($_POST['update_assets']) && isset($_SESSION['user_id'])) {
        $gold_tola = floatval($_POST['gold_tola'] ?? 0);
        $silver_tola = floatval($_POST['silver_tola'] ?? 0);
        $cash = floatval($_POST['cash'] ?? 0);
        $business_goods = floatval($_POST['business_goods'] ?? 0);
        $user_id = $_SESSION['user_id'];
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO assets (user_id, gold_tola, silver_tola, cash, business_goods, updated_at) 
                               VALUES (:user_id, :gold_tola, :silver_tola, :cash, :business_goods, CURRENT_TIMESTAMP)");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':gold_tola', $gold_tola, SQLITE3_FLOAT);
        $stmt->bindValue(':silver_tola', $silver_tola, SQLITE3_FLOAT);
        $stmt->bindValue(':cash', $cash, SQLITE3_FLOAT);
        $stmt->bindValue(':business_goods', $business_goods, SQLITE3_FLOAT);
        $stmt->execute();
        
        $message = "اثاثے کامیابی سے اپڈیٹ ہو گئے!";
        
        // Calculate and save zakat
        $prices = getCurrentPrices();
        $zakat_info = calculateZakat($gold_tola, $silver_tola, $cash, $business_goods, $prices);
        saveCalculation($user_id, $gold_tola, $silver_tola, $cash, $business_goods, $zakat_info['amount']);
    }
    
    // Handle price alert setting
    if (isset($_POST['set_alert']) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $type = SQLite3::escapeString($_POST['alert_type']);
        $threshold = floatval($_POST['threshold'] ?? 0);
        
        $stmt = $db->prepare("INSERT INTO alerts (user_id, type, threshold) VALUES (:user_id, :type, :threshold)");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':threshold', $threshold, SQLITE3_FLOAT);
        $stmt->execute();
        
        $message = "الرٹ کامیابی سے سیٹ ہو گیا!";
    }
    
    // Handle admin price update
    if (isset($_POST['update_prices']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $gold_price = floatval($_POST['gold_price'] ?? 0);
        $silver_price = floatval($_POST['silver_price'] ?? 0);
        
        $stmt = $db->prepare("INSERT INTO prices (gold_price, silver_price) VALUES (:gold_price, :silver_price)");
        $stmt->bindValue(':gold_price', $gold_price, SQLITE3_FLOAT);
        $stmt->bindValue(':silver_price', $silver_price, SQLITE3_FLOAT);
        $stmt->execute();
        
        $message = "قیمتیں کامیابی سے اپڈیٹ ہو گئیں!";
    }
    
    // Handle admin sending notifications
    if (isset($_POST['send_notification']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $notification_message = SQLite3::escapeString($_POST['notification_message']);
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
        
        if ($user_id) {
            createNotification($user_id, $notification_message);
            $message = "نوٹیفیکیشن صارف کو بھیج دیا گیا!";
        } else {
            // Send to all users
            $result = $db->query("SELECT id FROM users WHERE role = 'user'");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                createNotification($row['id'], $notification_message);
            }
            $message = "نوٹیفیکیشن تمام صارفین کو بھیج دیا گیا!";
        }
    }
    
    // Handle logout
    if (isset($_POST['logout'])) {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Check alerts for logged in users
checkUserAlerts();

// Get current prices
$prices = getCurrentPrices();

// Get user assets if logged in
$assets = isset($_SESSION['user_id']) ? getUserAssets($_SESSION['user_id']) : null;

// Get notifications for logged in user
$notifications = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :user_id OR user_id IS NULL ORDER BY created_at DESC LIMIT 10");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notifications[] = $row;
    }
    
    // Mark as read
    $db->exec("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id OR user_id IS NULL");
}

// Get calculation history for logged in user
$calculations = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM calculations WHERE user_id = :user_id ORDER BY calculated_at DESC LIMIT 10");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $calculations[] = $row;
    }
}

// Admin specific data
$users = [];
$user_count = 0;
$total_calculations = 0;

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $result = $db->query("SELECT * FROM users ORDER BY created_at DESC");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $user_count = $row['count'];
    
    $result = $db->query("SELECT COUNT(*) as count FROM calculations");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $total_calculations = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>زکاۃ کیلکولیٹر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.rtl.min.css">
    <style>
        body { 
            padding-top: 20px; 
            font-family: 'Noto Nastaliq Urdu', serif;
        }
        .container { max-width: 1000px; }
        .card { margin-bottom: 20px; }
        .navbar { margin-bottom: 20px; }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 3px 6px;
            border-radius: 50%;
            background: red;
            color: white;
            font-size: 10px;
        }
        .calculation-result {
            font-weight: bold;
            font-size: 1.2rem;
            margin: 15px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .alert { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">زکاۃ کیلکولیٹر</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="#">ہوم</a>
                        </li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="#calculator">میرے اثاثے</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#history">تاریخ</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#alerts">الرٹس</a>
                            </li>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="#admin">ایڈمن</a>
                                </li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="#calculator">کیلکولیٹر</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#info">زکاۃ کی معلومات</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="position-relative me-2">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#notificationsOffcanvas">
                                نوٹیفیکیشنز
                                <?php if (count($notifications) > 0): ?>
                                    <span class="notification-badge"><?= count($notifications) ?></span>
                                <?php endif; ?>
                            </button>
                        </div>
                        <form method="post" class="d-flex">
                            <span class="navbar-text me-3">
                                خوش آمدید، <?= htmlspecialchars($_SESSION['username']) ?>
                            </span>
                            <button type="submit" name="logout" class="btn btn-outline-danger">لاگ آؤٹ</button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-outline-primary me-2" type="button" data-bs-toggle="modal" data-bs-target="#loginModal">لاگ ان</button>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#registerModal">رجسٹر</button>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">موجودہ دھات کی قیمتیں</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>سونے کی قیمت: <?= number_format($prices['gold_price']) ?> فی تولہ</h5>
                    </div>
                    <div class="col-md-6">
                        <h5>چاندی کی قیمت: <?= number_format($prices['silver_price']) ?> فی تولہ</h5>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Guest View -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card" id="calculator">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">زکاۃ کیلکولیٹر</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label for="gold_tola" class="form-label">سونا (تولے)</label>
                                    <input type="number" step="0.01" class="form-control" id="gold_tola" name="gold_tola" value="<?= $_SESSION['guest_calculation']['gold_tola'] ?? 0 ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="silver_tola" class="form-label">چاندی (تولے)</label>
                                    <input type="number" step="0.01" class="form-control" id="silver_tola" name="silver_tola" value="<?= $_SESSION['guest_calculation']['silver_tola'] ?? 0 ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="cash" class="form-label">نقد رقم</label>
                                    <input type="number" step="0.01" class="form-control" id="cash" name="cash" value="<?= $_SESSION['guest_calculation']['cash'] ?? 0 ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="business_goods" class="form-label">کاروباری سامان کی مالیت</label>
                                    <input type="number" step="0.01" class="form-control" id="business_goods" name="business_goods" value="<?= $_SESSION['guest_calculation']['business_goods'] ?? 0 ?>">
                                </div>
                                <button type="submit" name="guest_calculate" class="btn btn-primary">زکاۃ کا حساب کریں</button>
                            </form>
                            
                            <?php if (isset($_SESSION['guest_calculation'])): ?>
                                <div class="calculation-result">
                                    <p>کُل دولت: <?= number_format($_SESSION['guest_calculation']['zakat_info']['total_wealth'], 2) ?></p>
                                    <p>نصاب کی حد (چاندی): <?= number_format($_SESSION['guest_calculation']['zakat_info']['nisab_silver'], 2) ?></p>
                                    <p>زکاۃ واجب: <?= $_SESSION['guest_calculation']['zakat_info']['is_due'] ? 'ہاں' : 'نہیں' ?></p>
                                    <p>زکاۃ کی رقم: <?= number_format($_SESSION['guest_calculation']['zakat_info']['amount'], 2) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6" id="info">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">زکاۃ کی معلومات</h5>
                        </div>
                        <div class="card-body">
                            <h5>زکاۃ کیا ہے؟</h5>
                            <p>زکاۃ اسلام کے پانچ ستونوں میں سے ایک ہے۔ یہ ایک لازمی خیرات ہے جس کا ایک مخصوص حساب کا طریقہ ہے۔</p>
                            
                            <h5>نصاب کی حد</h5>
                            <p>زکاۃ اس وقت واجب ہوتی ہے جب آپ کی دولت نصاب سے زیادہ ہو:</p>
                            <ul>
                                <li>سونے کا نصاب: 7.5 تولے سونا</li>
                                <li>چاندی کا نصاب: 52.5 تولے چاندی</li>
                            </ul>
                            
                            <h5>زکاۃ کی شرح</h5>
                            <p>زکاۃ کی معیاری شرح نصاب سے زیادہ دولت کا 2.5٪ ہے۔</p>
                            
                            <h5>اپنے حساب کو محفوظ کرنے کے لئے رجسٹر کریں</h5>
                            <p>اپنے اثاثوں کو محفوظ کرنے، زکاۃ کی تاریخ کو ٹریک کرنے، اور قیمتوں کے الرٹس حاصل کرنے کے لئے اکاؤنٹ بنائیں!</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Registered User View -->
            <div class="row" id="calculator">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">میرے اثاثے اور زکاۃ کا حساب</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="gold_tola" class="form-label">سونا (تولے)</label>
                                            <input type="number" step="0.01" class="form-control" id="gold_tola" name="gold_tola" value="<?= $assets['gold_tola'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="silver_tola" class="form-label">چاندی (تولے)</label>
                                            <input type="number" step="0.01" class="form-control" id="silver_tola" name="silver_tola" value="<?= $assets['silver_tola'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="cash" class="form-label">نقد رقم</label>
                                            <input type="number" step="0.01" class="form-control" id="cash" name="cash" value="<?= $assets['cash'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="business_goods" class="form-label">کاروباری سامان کی مالیت</label>
                                            <input type="number" step="0.01" class="form-control" id="business_goods" name="business_goods" value="<?= $assets['business_goods'] ?>">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="update_assets" class="btn btn-primary">اثاثے اپڈیٹ کریں اور زکاۃ کا حساب کریں</button>
                            </form>
                            
                            <?php 
                            if ($assets) {
                                $zakat_info = calculateZakat(
                                    $assets['gold_tola'], 
                                    $assets['silver_tola'], 
                                    $assets['cash'], 
                                    $assets['business_goods'], 
                                    $prices
                                );
                            ?>
                                <div class="calculation-result mt-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p>کُل دولت: <?= number_format($zakat_info['total_wealth'], 2) ?></p>
                                            <p>نصاب کی حد (چاندی): <?= number_format($zakat_info['nisab_silver'], 2) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p>زکاۃ واجب: <?= $zakat_info['is_due'] ? 'ہاں' : 'نہیں' ?></p>
                                            <p>زکاۃ کی رقم: <?= number_format($zakat_info['amount'], 2) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4" id="history">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">حساب کی تاریخ</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($calculations) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>تاریخ</th>
                                                <th>سونا</th>
                                                <th>چاندی</th>
                                                <th>نقد رقم</th>
                                                <th>کاروبار</th>
                                                <th>سونے کی قیمت</th>
                                                <th>چاندی کی قیمت</th>
                                                <th>زکاۃ کی رقم</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calculations as $calc): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($calc['calculated_at']) ?></td>
                                                    <td><?= htmlspecialchars($calc['gold_tola']) ?></td>
                                                    <td><?= htmlspecialchars($calc['silver_tola']) ?></td>
                                                    <td><?= number_format($calc['cash']) ?></td>
                                                    <td><?= number_format($calc['business_goods']) ?></td>
                                                    <td><?= number_format($calc['gold_price']) ?></td>
                                                    <td><?= number_format($calc['silver_price']) ?></td>
                                                    <td><?= number_format($calc['zakat_amount'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">ابھی تک کوئی حساب کی تاریخ نہیں ہے۔</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4" id="alerts">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0">قیمت الرٹس سیٹ کریں</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <div class="col-md-5">
                                    <select class="form-select" name="alert_type" required>
                                        <option value="">الرٹ کی قسم چنیں</option>
                                        <option value="gold_price">سونے کی قیمت</option>
                                        <option value="silver_price">چاندی کی قیمت</option>
                                        <option value="nisab">نصاب کی حد الرٹ</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <input type="number" class="form-control" name="threshold" placeholder="حد کی قیمت (قیمت الرٹس کے لیے)">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="set_alert" class="btn btn-warning w-100">الرٹ سیٹ کریں</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <!-- Admin Section -->
                <div class="row mt-4" id="admin">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">ایڈمن ڈیش بورڈ</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h5>کل صارفین</h5>
                                                <h2><?= $user_count ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h5>کل حسابات</h5>
                                                <h2><?= $total_calculations ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h5>موجودہ چاندی کا نصاب</h5>
                                                <h2><?= number_format(52.5 * $prices['silver_price']) ?></h2>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0">قیمتیں اپڈیٹ کریں</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="post">
                                                    <div class="mb-3">
                                                        <label for="gold_price" class="form-label">سونے کی قیمت فی تولہ</label>
                                                        <input type="number" step="0.01" class="form-control" id="gold_price" name="gold_price" value="<?= $prices['gold_price'] ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="silver_price" class="form-label">چاندی کی قیمت فی تولہ</label>
                                                        <input type="number" step="0.01" class="form-control" id="silver_price" name="silver_price" value="<?= $prices['silver_price'] ?>">
                                                    </div>
                                                    <button type="submit" name="update_prices" class="btn btn-primary">قیمتیں اپڈیٹ کریں</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header bg-info text-white">
                                                <h5 class="mb-0">نوٹیفیکیشنز بھیجیں</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="post">
                                                    <div class="mb-3">
                                                        <label for="user_id" class="form-label">صارف (تمام صارفین کے لیے خالی چھوڑیں)</label>
                                                        <select class="form-select" id="user_id" name="user_id">
                                                            <option value="">تمام صارفین</option>
                                                            <?php foreach ($users as $user): ?>
                                                                <?php if ($user['role'] === 'user'): ?>
                                                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="notification_message" class="form-label">پیغام</label>
                                                        <textarea class="form-control" id="notification_message" name="notification_message" rows="3" required></textarea>
                                                    </div>
                                                    <button type="submit" name="send_notification" class="btn btn-info">نوٹیفیکیشن بھیجیں</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header bg-secondary text-white">
                                        <h5 class="mb-0">صارفین کا انتظام</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>آئی ڈی</th>
                                                        <th>یوزرنیم</th>
                                                        <th>ای میل</th>
                                                        <th>رول</th>
                                                        <th>تاریخ اندراج</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($users as $user): ?>
                                                        <tr>
                                                            <td><?= $user['id'] ?></td>
                                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                                            <td><?= htmlspecialchars($user['role']) ?></td>
                                                            <td><?= htmlspecialchars($user['created_at']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <footer class="mt-5 mb-4 text-center text-muted">
            <p>&copy; <?= date('Y') ?> زکاۃ کیلکولیٹر ایپ. جملہ حقوق محفوظ ہیں۔</p>
        </footer>
    </div>
    
    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">لاگ ان</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="login_username" class="form-label">یوزرنیم</label>
                            <input type="text" class="form-control" id="login_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="login_password" class="form-label">پاس ورڈ</label>
                            <input type="password" class="form-control" id="login_password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary">لاگ ان</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">رجسٹر</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="register_username" class="form-label">یوزرنیم</label>
                            <input type="text" class="form-control" id="register_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="register_email" class="form-label">ای میل</label>
                            <input type="email" class="form-control" id="register_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="register_password" class="form-label">پاس ورڈ</label>
                            <input type="password" class="form-control" id="register_password" name="password" required>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary">رجسٹر</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications Offcanvas -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="notificationsOffcanvas" aria-labelledby="notificationsOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 id="notificationsOffcanvasLabel">نوٹیفیکیشنز</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php if (count($notifications) > 0): ?>
                <ul class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                        <li class="list-group-item">
                            <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                            <small class="text-muted"><?= htmlspecialchars($notification['created_at']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-center">کوئی نوٹیفیکیشنز نہیں</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
	
	<script>
	
	const fontUrl = "https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&display=swap";

const link = document.createElement("link");
link.rel = "stylesheet";
link.href = fontUrl;
document.head.appendChild(link);

const style = document.createElement("style");
style.innerHTML = `
  * {
    font-family: 'Noto Nastaliq Urdu', serif !important;
  }
`;
document.head.appendChild(style);
	</script>
</body>
</html>
