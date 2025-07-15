<?php
session_start();
ini_set('display_errors', 0); // Disable error display for production
error_reporting(0);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Karachi');

// --- Configuration ---
define('DB_FILE', './zakat_app_data.sqlite');
define('GOLD_NISAB_GRAMS', 87.48);
define('SILVER_NISAB_GRAMS', 612.36);
define('ZAKAT_RATE', 0.025); // 2.5%
define('ADMIN_USERNAME', 'admin');
// Generate a hash for the default admin password 'admin123'
// Use password_hash('admin123', PASSWORD_DEFAULT); to generate your own secure hash
define('ADMIN_PASSWORD_HASH', '$2y$10$YKlhnF2ZknHk6DRlAQNFz.1F8XoI3a./9xkvDF0MBI8wH14J94XnK'); // Default: admin123

// --- Database Setup ---
try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user', -- 'user' or 'admin'
        zakat_due_date DATE NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS prices (
        id INTEGER PRIMARY KEY CHECK (id = 1), -- Enforce single row
        gold_price_per_gram_pkr REAL NOT NULL DEFAULT 0,
        silver_price_per_gram_pkr REAL NOT NULL DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

     // Initialize prices if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM prices");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO prices (id, gold_price_per_gram_pkr, silver_price_per_gram_pkr) VALUES (1, 20000, 250)");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS zakat_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        calculation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        gold_grams REAL NOT NULL,
        silver_grams REAL NOT NULL,
        cash_pkr REAL NOT NULL,
        business_assets_pkr REAL NOT NULL,
        gold_price_at_calc REAL NOT NULL,
        silver_price_at_calc REAL NOT NULL,
        total_assets_value_pkr REAL NOT NULL,
        nisab_type TEXT NOT NULL, -- 'gold', 'silver', 'mixed'
        nisab_value_pkr REAL NOT NULL,
        zakat_due_pkr REAL NOT NULL,
        payment_date DATE NULL,
        paid_amount_pkr REAL NULL,
        recipient TEXT NULL,
        notes TEXT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Ensure admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([ADMIN_USERNAME]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
        $stmt->execute([ADMIN_USERNAME, ADMIN_PASSWORD_HASH]);
    }

} catch (PDOException $e) {
    die("ڈیٹا بیس کنکشن یا سیٹ اپ میں خرابی: " . $e->getMessage());
}

// --- Helper Functions ---
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_current_prices($pdo) {
    $stmt = $pdo->query("SELECT gold_price_per_gram_pkr, silver_price_per_gram_pkr FROM prices WHERE id = 1");
    return $stmt->fetch() ?: ['gold_price_per_gram_pkr' => 0, 'silver_price_per_gram_pkr' => 0];
}

function update_prices($pdo, $gold_price, $silver_price) {
    $stmt = $pdo->prepare("UPDATE prices SET gold_price_per_gram_pkr = ?, silver_price_per_gram_pkr = ?, last_updated = CURRENT_TIMESTAMP WHERE id = 1");
    return $stmt->execute([$gold_price, $silver_price]);
}

function get_user_by_username($pdo, $username) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function register_user($pdo, $username, $password) {
    if (empty($username) || empty($password)) {
        return "براہ کرم صارف نام اور پاس ورڈ درج کریں۔";
    }
    if (get_user_by_username($pdo, $username)) {
        return "یہ صارف نام پہلے سے موجود ہے۔";
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
    if ($stmt->execute([$username, $hash])) {
        return true; // Success
    }
    return "رجسٹریشن ناکام ہوگئی۔";
}

function login_user($pdo, $username, $password) {
    $user = get_user_by_username($pdo, $username);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function logout_user() {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function get_user_zakat_logs($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM zakat_logs WHERE user_id = ? ORDER BY calculation_date DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_all_zakat_logs($pdo) {
    $stmt = $pdo->query("SELECT zl.*, u.username FROM zakat_logs zl JOIN users u ON zl.user_id = u.id ORDER BY zl.calculation_date DESC");
    return $stmt->fetchAll();
}

function log_zakat_calculation($pdo, $user_id, $calc_data) {
    $sql = "INSERT INTO zakat_logs (user_id, gold_grams, silver_grams, cash_pkr, business_assets_pkr, gold_price_at_calc, silver_price_at_calc, total_assets_value_pkr, nisab_type, nisab_value_pkr, zakat_due_pkr)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $user_id,
        $calc_data['gold_grams'],
        $calc_data['silver_grams'],
        $calc_data['cash_pkr'],
        $calc_data['business_assets_pkr'],
        $calc_data['gold_price'],
        $calc_data['silver_price'],
        $calc_data['total_assets_value'],
        $calc_data['nisab_type'],
        $calc_data['nisab_value'],
        $calc_data['zakat_due']
    ]);
}

function log_zakat_payment($pdo, $log_id, $user_id, $payment_date, $paid_amount, $recipient, $notes) {
     // First, verify the log belongs to the user
    $stmt_check = $pdo->prepare("SELECT id FROM zakat_logs WHERE id = ? AND user_id = ? AND payment_date IS NULL");
    $stmt_check->execute([$log_id, $user_id]);
    if (!$stmt_check->fetch()) {
        return "لاگ آئی ڈی غلط ہے یا ادائیگی پہلے ہی ہو چکی ہے۔"; // Invalid log ID or already paid
    }

    $sql = "UPDATE zakat_logs SET payment_date = ?, paid_amount_pkr = ?, recipient = ?, notes = ? WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$payment_date, $paid_amount, $recipient, $notes, $log_id, $user_id])) {
        // Update user's next zakat due date (one Gregorian year later)
        $next_due_date = date('Y-m-d', strtotime($payment_date . ' +1 year'));
        $stmt_user = $pdo->prepare("UPDATE users SET zakat_due_date = ? WHERE id = ?");
        $stmt_user->execute([$next_due_date, $user_id]);
        return true;
    }
    return "ادائیگی لاگ کرنے میں خرابی۔";
}

function get_users($pdo) {
    return $pdo->query("SELECT id, username, role, zakat_due_date FROM users ORDER BY username")->fetchAll();
}

function check_zakat_due_notification($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT zakat_due_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user && $user['zakat_due_date']) {
        $today = date('Y-m-d');
        if ($today >= $user['zakat_due_date']) {
            return "نوٹ: آپ کی سالانہ زکوٰۃ کی ادائیگی کا وقت قریب ہے یا گزر چکا ہے۔ آخری مقررہ تاریخ: " . h($user['zakat_due_date']);
        }
    }
    return null; // No notification needed
}

function export_logs_to_csv($logs) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=zakat_logs_'.date('Y-m-d').'.csv');
    $output = fopen('php://output', 'w');
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    // Header Row (translate headers to Urdu)
    fputcsv($output, [
        'لاگ ID', 'صارف نام', 'حساب کی تاریخ', 'سونا (گرام)', 'چاندی (گرام)', 'نقدی (PKR)', 'کاروباری اثاثے (PKR)',
        'سونے کی قیمت', 'چاندی کی قیمت', 'کل اثاثوں کی قیمت (PKR)', 'نصاب قسم', 'نصاب کی قیمت (PKR)', 'واجب الادا زکوٰۃ (PKR)',
        'ادائیگی کی تاریخ', 'ادا شدہ رقم (PKR)', 'وصول کنندہ', 'نوٹس'
    ]);
    // Data Rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'], $log['username'] ?? $log['user_id'], // Add username if available (from admin view)
            $log['calculation_date'], $log['gold_grams'], $log['silver_grams'], $log['cash_pkr'], $log['business_assets_pkr'],
            $log['gold_price_at_calc'], $log['silver_price_at_calc'], $log['total_assets_value_pkr'], $log['nisab_type'],
            $log['nisab_value_pkr'], $log['zakat_due_pkr'], $log['payment_date'], $log['paid_amount_pkr'],
            $log['recipient'], $log['notes']
        ]);
    }
    fclose($output);
    exit;
}


// --- Request Handling ---
$action = $_GET['action'] ?? 'home';
$message = '';
$error = '';
$calculation_result = null;
$user_logs = [];
$all_logs = [];
$users_list = [];
$notification = null;

// Post Request Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';

    try {
        if ($form_action === 'login') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            if (login_user($pdo, $username, $password)) {
                header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to home after login
                exit;
            } else {
                $error = "غلط صارف نام یا پاس ورڈ۔";
            }
        } elseif ($form_action === 'register') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            if ($password !== $confirm_password) {
                 $error = "پاس ورڈز مماثل نہیں ہیں۔";
            } else {
                $result = register_user($pdo, $username, $password);
                if ($result === true) {
                    $message = "رجسٹریشن کامیاب! اب آپ لاگ ان کر سکتے ہیں۔";
                    // Automatically log in the user after registration? Optional.
                    // login_user($pdo, $username, $password);
                    // header('Location: ' . $_SERVER['PHP_SELF']);
                    // exit;
                } else {
                    $error = $result;
                }
            }
        } elseif ($form_action === 'calculate_zakat' && is_logged_in()) {
            $prices = get_current_prices($pdo);
            $gold_price = $prices['gold_price_per_gram_pkr'];
            $silver_price = $prices['silver_price_per_gram_pkr'];

            if ($gold_price <= 0 || $silver_price <= 0) {
                $error = "براہ کرم پہلے ایڈمن پینل میں سونے اور چاندی کی درست قیمتیں مقرر کریں۔";
            } else {
                $gold_amount = filter_var($_POST['gold_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                $gold_unit = $_POST['gold_unit'] ?? 'grams';
                $silver_amount = filter_var($_POST['silver_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
                $silver_unit = $_POST['silver_unit'] ?? 'grams';
                $cash = filter_var($_POST['cash_pkr'] ?? 0, FILTER_VALIDATE_FLOAT);
                $business_assets = filter_var($_POST['business_assets_pkr'] ?? 0, FILTER_VALIDATE_FLOAT);

                $gold_grams = ($gold_unit === 'tolas') ? $gold_amount * 11.664 : $gold_amount;
                $silver_grams = ($silver_unit === 'tolas') ? $silver_amount * 11.664 : $silver_amount;

                $gold_value = $gold_grams * $gold_price;
                $silver_value = $silver_grams * $silver_price;
                $total_assets_value = $gold_value + $silver_value + $cash + $business_assets;

                $silver_nisab_value = SILVER_NISAB_GRAMS * $silver_price;
                $gold_nisab_value = GOLD_NISAB_GRAMS * $gold_price; // Only for comparison if only gold is owned

                $zakat_due = 0;
                $nisab_value = 0;
                $nisab_type = 'کوئی نہیں';
                $nisab_met = false;

                // Determine applicable Nisab and calculate Zakat
                if ($gold_grams > 0 && $silver_grams == 0 && $cash == 0 && $business_assets == 0) {
                    // Gold only
                    $nisab_value = $gold_nisab_value;
                    $nisab_type = 'سونا (' . number_format(GOLD_NISAB_GRAMS, 2) . ' گرام)';
                     if ($gold_grams >= GOLD_NISAB_GRAMS) {
                         $nisab_met = true;
                         $zakat_due = $gold_value * ZAKAT_RATE;
                         // Use total_assets_value for calculation consistency even if only gold
                         // $zakat_due = $total_assets_value * ZAKAT_RATE;
                    }
                } elseif ($silver_grams > 0 && $gold_grams == 0 && $cash == 0 && $business_assets == 0) {
                    // Silver only
                    $nisab_value = $silver_nisab_value;
                    $nisab_type = 'چاندی (' . number_format(SILVER_NISAB_GRAMS, 2) . ' گرام)';
                    if ($silver_grams >= SILVER_NISAB_GRAMS) {
                        $nisab_met = true;
                        $zakat_due = $total_assets_value * ZAKAT_RATE;
                    }
                } else {
                    // Mixed assets or only Cash/Business Assets
                    $nisab_value = $silver_nisab_value;
                    $nisab_type = 'چاندی (' . number_format(SILVER_NISAB_GRAMS, 2) . ' گرام)';
                     if ($total_assets_value >= $silver_nisab_value) {
                        $nisab_met = true;
                        $zakat_due = $total_assets_value * ZAKAT_RATE;
                    }
                }

                $calculation_result = [
                    'gold_grams' => $gold_grams,
                    'silver_grams' => $silver_grams,
                    'cash_pkr' => $cash,
                    'business_assets_pkr' => $business_assets,
                    'gold_price' => $gold_price,
                    'silver_price' => $silver_price,
                    'total_assets_value' => $total_assets_value,
                    'nisab_type' => $nisab_type,
                    'nisab_value' => $nisab_value,
                    'nisab_met' => $nisab_met,
                    'zakat_due' => $zakat_due
                ];

                // Log the calculation immediately if Zakat is due
                if ($nisab_met && $zakat_due > 0) {
                     if (!log_zakat_calculation($pdo, $_SESSION['user_id'], $calculation_result)) {
                         $error = "زکوٰۃ کے حساب کو لاگ کرنے میں خرابی۔";
                     } else {
                         // Redirect to avoid re-logging on refresh, maybe show log section?
                         header('Location: ' . $_SERVER['PHP_SELF'] . '?action=history&calc=success');
                         exit;
                     }
                } elseif (!$nisab_met) {
                     $message = "آپ کے اثاثے نصاب سے کم ہیں۔ فی الحال زکوٰۃ واجب نہیں ہے۔";
                     // Optionally log this calculation as well for record? For now, only log if due.
                }
            }
        } elseif ($form_action === 'log_payment' && is_logged_in()) {
            $log_id = filter_input(INPUT_POST, 'log_id', FILTER_VALIDATE_INT);
            $payment_date = $_POST['payment_date'] ?? '';
            $paid_amount = filter_input(INPUT_POST, 'paid_amount_pkr', FILTER_VALIDATE_FLOAT);
            $recipient = trim($_POST['recipient'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($log_id && $payment_date && $paid_amount !== false && $paid_amount > 0) {
                 $result = log_zakat_payment($pdo, $log_id, $_SESSION['user_id'], $payment_date, $paid_amount, $recipient, $notes);
                 if ($result === true) {
                     $message = "زکوٰۃ کی ادائیگی کامیابی سے لاگ ہو گئی۔";
                     header('Location: ' . $_SERVER['PHP_SELF'] . '?action=history&payment=success'); // Redirect
                     exit;
                 } else {
                     $error = $result; // Contains error message from function
                 }
            } else {
                $error = "براہ کرم ادائیگی لاگ کرنے کے لیے تمام ضروری فیلڈز (لاگ ID، تاریخ، رقم) درست طریقے سے پر کریں۔";
            }

        } elseif ($form_action === 'update_prices' && is_admin()) {
            $gold_price = filter_input(INPUT_POST, 'gold_price', FILTER_VALIDATE_FLOAT);
            $silver_price = filter_input(INPUT_POST, 'silver_price', FILTER_VALIDATE_FLOAT);
            if ($gold_price !== false && $silver_price !== false && $gold_price > 0 && $silver_price > 0) {
                if (update_prices($pdo, $gold_price, $silver_price)) {
                    $message = "قیمتیں کامیابی سے اپ ڈیٹ ہو گئیں۔";
                } else {
                    $error = "قیمتیں اپ ڈیٹ کرنے میں خرابی۔";
                }
            } else {
                $error = "براہ کرم سونے اور چاندی کے لیے درست مثبت قیمتیں درج کریں۔";
            }
             // Stay on admin page after update
            $action = 'admin'; // Ensure we stay on the admin panel view
        }

    } catch (Exception $e) {
        // Generic error catcher
        $error = "ایک غیر متوقع خرابی پیش آئی: " . $e->getMessage();
    }
}

// Get Request Handling / Page Loading
if (is_logged_in()) {
    $notification = check_zakat_due_notification($pdo, $_SESSION['user_id']);
    if ($action === 'history' || isset($_GET['calc']) || isset($_GET['payment'])) {
        $user_logs = get_user_zakat_logs($pdo, $_SESSION['user_id']);
        if(isset($_GET['calc']) && $_GET['calc'] == 'success') $message = "زکوٰۃ کا حساب کامیابی سے لاگ ہو گیا۔ اب آپ ادائیگی کی تفصیلات درج کر سکتے ہیں۔";
        if(isset($_GET['payment']) && $_GET['payment'] == 'success') $message = "زکوٰۃ کی ادائیگی کامیابی سے لاگ ہو گئی۔";
    } elseif ($action === 'admin' && is_admin()) {
        $current_prices = get_current_prices($pdo);
        $users_list = get_users($pdo);
        $all_logs = get_all_zakat_logs($pdo);
        // Handle CSV export request for admin
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
             export_logs_to_csv($all_logs);
        }
    } elseif ($action === 'logout') {
        logout_user();
    }
    // Default action for logged in user is 'home' (calculator)
    if($action === 'home' && is_logged_in() && !is_admin()){
       // User dashboard / calculator is default
    } elseif ($action === 'home' && is_admin()){
        $action = 'admin'; // Admins default to admin panel
        // Reload admin data if redirected here
        $current_prices = get_current_prices($pdo);
        $users_list = get_users($pdo);
        $all_logs = get_all_zakat_logs($pdo);
    }

} else {
    // If not logged in, allow access only to login/register actions
    if ($action !== 'login' && $action !== 'register') {
        $action = 'login'; // Default to login page if not logged in
    }
}

$current_prices = get_current_prices($pdo); // Get prices for display

?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>زکوٰۃ کیلکولیٹر اور لاگنگ سسٹم</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap');
        body { font-family: 'Noto Nastaliq Urdu', serif; direction: rtl; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; font-size: 16px; line-height: 1.6; }
        .container { max-width: 900px; margin: 20px auto; padding: 20px; background-color: #fff; border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h1, h2, h3 { color: #006400; /* Dark Green */ text-align: center; margin-bottom: 20px; }
        nav { background-color: #006400; padding: 10px 0; text-align: center; margin-bottom: 20px; border-radius: 5px; }
        nav a { color: #fff; text-decoration: none; margin: 0 15px; font-weight: bold; }
        nav a:hover { text-decoration: underline; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; background-color:#f9f9f9; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="text"], input[type="password"], input[type="number"], input[type="date"], select, textarea {
            width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; font-family: 'Noto Nastaliq Urdu', serif; font-size: 1rem;
        }
        textarea { height: 80px; }
        select { width: auto; min-width: 100px; padding: 10px 5px;}
        .input-group { display: flex; align-items: center; margin-bottom: 15px; gap: 10px; flex-wrap: wrap;}
        .input-group label { margin-bottom: 0; }
        .input-group input[type="number"] { flex-grow: 1; width: auto; min-width: 150px; margin-bottom: 0;}
        .input-group select { margin-bottom: 0; }

        button[type="submit"] { background-color: #008000; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 1.1rem; font-family: 'Noto Nastaliq Urdu', serif; transition: background-color 0.3s ease; }
        button[type="submit"]:hover { background-color: #006400; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.notification { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: right; vertical-align: top; }
        th { background-color: #e9e9e9; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .calculation-result { margin-top: 20px; padding: 15px; border: 1px solid #d4edda; background-color: #f0fff0; border-radius: 5px; }
        .calculation-result h3 { color: #155724; text-align: right; margin-bottom: 10px;}
        .calculation-result p { margin: 5px 0; }
        .user-info { text-align: left; margin-bottom: 15px; padding: 5px; font-size: 0.9em; color: #555; }
        .price-info { text-align: center; margin-bottom: 15px; font-size: 0.9em; color: #777; }
         /* Responsive */
        @media (max-width: 600px) {
            .container { margin: 10px; padding: 15px; }
            h1 { font-size: 1.5rem; }
            nav a { margin: 0 8px; font-size: 0.9rem;}
            input[type="text"], input[type="password"], input[type="number"], input[type="date"], select, textarea { width: calc(100% - 20px); font-size: 0.95rem; }
            .input-group input[type="number"] { min-width: 100px;}
            th, td { padding: 8px; font-size:0.9rem; }
            button[type="submit"] { width: 100%; padding: 12px; }
            .input-group { flex-direction: column; align-items: stretch; }
            .input-group input[type="number"], .input-group select { width: calc(100% - 20px); }
        }
        .small-text { font-size: 0.85em; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>زکوٰۃ کیلکولیٹر اور لاگنگ سسٹم</h1>

        <?php if (is_logged_in()): ?>
            <div class="user-info">
                خوش آمدید، <?php echo h($_SESSION['username']); ?>! (<?php echo h($_SESSION['role']); ?>)
            </div>
             <nav>
                <?php if (is_admin()): ?>
                    <a href="?action=admin">ایڈمن پینل</a>
                <?php else: ?>
                    <a href="?action=home">زکوٰۃ کا حساب لگائیں</a>
                    <a href="?action=history">ادائیگی کا ریکارڈ</a>
                <?php endif; ?>
                 <a href="?action=logout">لاگ آؤٹ</a>
            </nav>
            <?php if ($notification): ?>
                 <div class="message notification"><?php echo h($notification); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo h($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php // --- Page Content based on Action --- ?>

        <?php if (!is_logged_in()): ?>
            <?php // --- Login/Register Forms --- ?>
            <?php if ($action === 'register'): ?>
                <h2>نئی رجسٹریشن</h2>
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>?action=register">
                    <input type="hidden" name="form_action" value="register">
                    <label for="username">صارف نام:</label>
                    <input type="text" id="username" name="username" required>
                    <label for="password">پاس ورڈ:</label>
                    <input type="password" id="password" name="password" required>
                    <label for="confirm_password">پاس ورڈ کی تصدیق کریں:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="submit">رجسٹر</button>
                </form>
                <p style="text-align:center;">پہلے سے اکاؤنٹ ہے؟ <a href="?action=login">یہاں لاگ ان کریں</a></p>
            <?php else: // Default to login ?>
                <h2>لاگ ان</h2>
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>?action=login">
                    <input type="hidden" name="form_action" value="login">
                    <label for="username">صارف نام:</label>
                    <input type="text" id="username" name="username" required>
                    <label for="password">پاس ورڈ:</label>
                    <input type="password" id="password" name="password" required>
                    <button type="submit">لاگ ان</button>
                </form>
                 <p style="text-align:center;">اکاؤنٹ نہیں ہے؟ <a href="?action=register">نیا اکاؤنٹ بنائیں</a></p>
            <?php endif; ?>

        <?php elseif (is_admin() && $action === 'admin'): ?>
            <?php // --- Admin Panel --- ?>
            <h2>ایڈمن پینل</h2>

             <?php // --- Price Update --- ?>
            <h3>قیمتیں اپ ڈیٹ کریں</h3>
             <div class="price-info">موجودہ قیمتیں: سونا: <?php echo h(number_format($current_prices['gold_price_per_gram_pkr'], 2)); ?> PKR/گرام, چاندی: <?php echo h(number_format($current_prices['silver_price_per_gram_pkr'], 2)); ?> PKR/گرام</div>
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>?action=admin">
                <input type="hidden" name="form_action" value="update_prices">
                <div class="input-group">
                 <label for="gold_price">سونے کی قیمت فی گرام (PKR):</label>
                <input type="number" step="any" id="gold_price" name="gold_price" value="<?php echo h($current_prices['gold_price_per_gram_pkr']); ?>" required>
                </div>
                <div class="input-group">
                <label for="silver_price">چاندی کی قیمت فی گرام (PKR):</label>
                <input type="number" step="any" id="silver_price" name="silver_price" value="<?php echo h($current_prices['silver_price_per_gram_pkr']); ?>" required>
                 </div>
                <button type="submit">اپ ڈیٹ کریں</button>
            </form>

            <?php // --- User Management --- ?>
            <h3>صارفین کا انتظام</h3>
            <table>
                <thead>
                    <tr><th>ID</th><th>صارف نام</th><th>کردار</th><th>زکوٰۃ کی مقررہ تاریخ</th></tr>
                </thead>
                <tbody>
                <?php if (!empty($users_list)): ?>
                    <?php foreach ($users_list as $user): ?>
                    <tr>
                        <td><?php echo h($user['id']); ?></td>
                        <td><?php echo h($user['username']); ?></td>
                        <td><?php echo h($user['role']); ?></td>
                        <td><?php echo h($user['zakat_due_date'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">کوئی صارف نہیں ملا۔</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php // --- All Zakat Logs --- ?>
            <h3>تمام زکوٰۃ لاگز دیکھیں</h3>
             <p><a href="?action=admin&export=csv" style="text-decoration:none;"><button type="button" style="background-color:#007bff;">CSV ایکسپورٹ کریں</button></a></p>
            <table>
                <thead>
                    <tr>
                        <th>لاگ ID</th><th>صارف نام</th><th>حساب کی تاریخ</th><th>کل اثاثے (PKR)</th>
                        <th>نصاب (PKR)</th><th>واجب الادا زکوٰۃ (PKR)</th><th>ادائیگی کی تاریخ</th><th>ادا شدہ رقم (PKR)</th>
                        <th>تفصیلات</th>
                    </tr>
                </thead>
                <tbody>
                 <?php if (!empty($all_logs)): ?>
                    <?php foreach ($all_logs as $log): ?>
                    <tr>
                        <td><?php echo h($log['id']); ?></td>
                        <td><?php echo h($log['username']); ?></td>
                        <td><?php echo h(date('Y-m-d H:i', strtotime($log['calculation_date']))); ?></td>
                        <td><?php echo h(number_format($log['total_assets_value_pkr'], 2)); ?></td>
                        <td><?php echo h(number_format($log['nisab_value_pkr'], 2)); ?> <span class="small-text">(<?php echo h($log['nisab_type']); ?>)</span></td>
                        <td><?php echo h(number_format($log['zakat_due_pkr'], 2)); ?></td>
                        <td><?php echo h($log['payment_date'] ?: '-'); ?></td>
                        <td><?php echo h($log['paid_amount_pkr'] ? number_format($log['paid_amount_pkr'], 2) : '-'); ?></td>
                        <td>
                             <span class="small-text">
                                سونا: <?php echo h(number_format($log['gold_grams'], 2)); ?> گرام<br>
                                چاندی: <?php echo h(number_format($log['silver_grams'], 2)); ?> گرام<br>
                                نقدی: <?php echo h(number_format($log['cash_pkr'], 2)); ?><br>
                                کاروبار: <?php echo h(number_format($log['business_assets_pkr'], 2)); ?><br>
                                وصول کنندہ: <?php echo h($log['recipient'] ?: '-'); ?><br>
                                نوٹس: <?php echo h($log['notes'] ?: '-'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                 <?php else: ?>
                    <tr><td colspan="9">کوئی لاگز نہیں ملے۔</td></tr>
                 <?php endif; ?>
                </tbody>
            </table>

        <?php elseif (!is_admin() && $action === 'home'): ?>
            <?php // --- User Zakat Calculator --- ?>
            <h2>زکوٰۃ کا حساب لگائیں</h2>
             <div class="price-info">موجودہ قیمتیں: سونا: <?php echo h(number_format($current_prices['gold_price_per_gram_pkr'], 2)); ?> PKR/گرام, چاندی: <?php echo h(number_format($current_prices['silver_price_per_gram_pkr'], 2)); ?> PKR/گرام</div>

            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <input type="hidden" name="form_action" value="calculate_zakat">

                 <div class="input-group">
                    <label for="gold_amount">سونا:</label>
                    <input type="number" step="any" id="gold_amount" name="gold_amount" value="0" required>
                    <select name="gold_unit">
                        <option value="grams">گرام</option>
                        <option value="tolas">تولہ</option>
                    </select>
                 </div>
                <p class="small-text">(نصاب: <?php echo GOLD_NISAB_GRAMS; ?> گرام یا 7.5 تولہ صرف سونے کی صورت میں)</p>

                <div class="input-group">
                     <label for="silver_amount">چاندی:</label>
                    <input type="number" step="any" id="silver_amount" name="silver_amount" value="0" required>
                    <select name="silver_unit">
                        <option value="grams">گرام</option>
                        <option value="tolas">تولہ</option>
                    </select>
                </div>
                <p class="small-text">(نصاب: <?php echo SILVER_NISAB_GRAMS; ?> گرام یا 52.5 تولہ صرف چاندی یا مخلوط اثاثوں کی صورت میں)</p>

                <label for="cash_pkr">نقدی (PKR):</label>
                <input type="number" step="any" id="cash_pkr" name="cash_pkr" value="0" required>

                <label for="business_assets_pkr">کاروباری اثاثے (قابل فروخت مال کا موجودہ قیمت، وصول طلب قرضے وغیرہ) (PKR):</label>
                <input type="number" step="any" id="business_assets_pkr" name="business_assets_pkr" value="0" required>

                <button type="submit">حساب لگائیں</button>
            </form>

            <?php if ($calculation_result): ?>
                 <div class="calculation-result">
                    <h3>حساب کا نتیجہ:</h3>
                    <p>کل قابلِ زکوٰۃ اثاثوں کی مالیت: <strong><?php echo h(number_format($calculation_result['total_assets_value'], 2)); ?> PKR</strong></p>
                    <p>موجودہ نصاب (<?php echo h($calculation_result['nisab_type']); ?>): <strong><?php echo h(number_format($calculation_result['nisab_value'], 2)); ?> PKR</strong></p>
                    <?php if ($calculation_result['nisab_met']): ?>
                        <p style="color: green; font-weight: bold;">آپ کے اثاثے نصاب سے زیادہ ہیں۔</p>
                        <p>واجب الادا زکوٰۃ (2.5%): <strong><?php echo h(number_format($calculation_result['zakat_due'], 2)); ?> PKR</strong></p>
                        <p>یہ حساب آپ کے ریکارڈ میں شامل کر دیا گیا ہے۔ آپ <a href="?action=history">ادائیگی کا ریکارڈ</a> سیکشن میں جا کر ادائیگی لاگ کر سکتے ہیں۔</p>
                    <?php else: ?>
                        <p style="color: red; font-weight: bold;">آپ کے اثاثے نصاب سے کم ہیں۔ فی الحال زکوٰۃ واجب نہیں ہے۔</p>
                    <?php endif; ?>
                     <p class="small-text">
                        تفصیلات: سونا <?php echo h(number_format($calculation_result['gold_grams'], 2)); ?> گرام,
                        چاندی <?php echo h(number_format($calculation_result['silver_grams'], 2)); ?> گرام,
                        نقدی <?php echo h(number_format($calculation_result['cash_pkr'], 2)); ?> PKR,
                        کاروباری اثاثے <?php echo h(number_format($calculation_result['business_assets_pkr'], 2)); ?> PKR.
                        (قیمتیں: سونا <?php echo h($calculation_result['gold_price']); ?>/گرام, چاندی <?php echo h($calculation_result['silver_price']); ?>/گرام)
                    </p>
                 </div>
            <?php endif; ?>


        <?php elseif (!is_admin() && $action === 'history'): ?>
             <?php // --- User Payment History and Logging --- ?>
            <h2>ادائیگی کا ریکارڈ اور لاگنگ</h2>

             <?php // --- Pending Payments List --- ?>
            <h3>زیر التواء ادائیگی (حسابات جن کی ادائیگی لاگ نہیں ہوئی)</h3>
            <?php
            $pending_logs = array_filter($user_logs, function($log) {
                return empty($log['payment_date']);
            });
            ?>
             <?php if (!empty($pending_logs)): ?>
                 <table>
                     <thead>
                         <tr><th>لاگ ID</th><th>حساب کی تاریخ</th><th>واجب الادا زکوٰۃ (PKR)</th><th>عمل</th></tr>
                     </thead>
                     <tbody>
                         <?php foreach ($pending_logs as $log): ?>
                         <tr>
                             <td><?php echo h($log['id']); ?></td>
                             <td><?php echo h(date('Y-m-d H:i', strtotime($log['calculation_date']))); ?></td>
                             <td><?php echo h(number_format($log['zakat_due_pkr'], 2)); ?></td>
                             <td><button onclick="document.getElementById('log_id_<?php echo h($log['id']); ?>').style.display='block'; this.style.display='none';">ادائیگی لاگ کریں</button></td>
                         </tr>
                         <tr id="log_id_<?php echo h($log['id']); ?>" style="display:none;">
                             <td colspan="4">
                                 <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>?action=history">
                                     <input type="hidden" name="form_action" value="log_payment">
                                     <input type="hidden" name="log_id" value="<?php echo h($log['id']); ?>">
                                      <p><strong>لاگ ID: <?php echo h($log['id']); ?></strong> | واجب الادا: <?php echo h(number_format($log['zakat_due_pkr'], 2)); ?> PKR</p>
                                     <label for="payment_date_<?php echo h($log['id']); ?>">ادائیگی کی تاریخ:</label>
                                     <input type="date" id="payment_date_<?php echo h($log['id']); ?>" name="payment_date" required>
                                     <label for="paid_amount_pkr_<?php echo h($log['id']); ?>">ادا شدہ رقم (PKR):</label>
                                     <input type="number" step="any" id="paid_amount_pkr_<?php echo h($log['id']); ?>" name="paid_amount_pkr" value="<?php echo h($log['zakat_due_pkr']); ?>" required>
                                     <label for="recipient_<?php echo h($log['id']); ?>">وصول کنندہ (نام/ادارہ):</label>
                                     <input type="text" id="recipient_<?php echo h($log['id']); ?>" name="recipient">
                                     <label for="notes_<?php echo h($log['id']); ?>">نوٹس:</label>
                                     <textarea id="notes_<?php echo h($log['id']); ?>" name="notes"></textarea>
                                     <button type="submit">ادائیگی جمع کروائیں</button>
                                      <button type="button" onclick="document.getElementById('log_id_<?php echo h($log['id']); ?>').style.display='none'; document.querySelector('button[onclick*=\'log_id_<?php echo h($log['id']); ?>\']').style.display='inline-block';" style="background-color:#aaa;">منسوخ کریں</button>
                                 </form>
                             </td>
                         </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             <?php else: ?>
                 <p>کوئی زیر التواء ادائیگی نہیں ہے۔</p>
             <?php endif; ?>


             <?php // --- Completed Payment History --- ?>
            <h3>مکمل شدہ ادائیگیوں کا ریکارڈ</h3>
             <table>
                <thead>
                    <tr>
                        <th>لاگ ID</th><th>حساب کی تاریخ</th><th>کل اثاثے (PKR)</th><th>واجب الادا زکوٰۃ (PKR)</th>
                        <th>ادائیگی کی تاریخ</th><th>ادا شدہ رقم (PKR)</th><th>وصول کنندہ</th><th>نوٹس</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                 $completed_logs = array_filter($user_logs, function($log) {
                     return !empty($log['payment_date']);
                 });
                 ?>
                <?php if (!empty($completed_logs)): ?>
                    <?php foreach ($completed_logs as $log): ?>
                    <tr>
                        <td><?php echo h($log['id']); ?></td>
                         <td><?php echo h(date('Y-m-d H:i', strtotime($log['calculation_date']))); ?></td>
                        <td><?php echo h(number_format($log['total_assets_value_pkr'], 2)); ?></td>
                        <td><?php echo h(number_format($log['zakat_due_pkr'], 2)); ?></td>
                        <td><?php echo h($log['payment_date']); ?></td>
                        <td><?php echo h(number_format($log['paid_amount_pkr'], 2)); ?></td>
                        <td><?php echo h($log['recipient'] ?: '-'); ?></td>
                        <td><?php echo h($log['notes'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8">کوئی مکمل شدہ ادائیگی کا ریکارڈ نہیں ملا۔</td></tr>
                <?php endif; ?>
                </tbody>
            </table>


        <?php endif; // End is_logged_in checks ?>

    </div> <?php // End container ?>
</body>
</html>
