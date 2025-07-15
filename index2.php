<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 0); // Set to 1 for debugging, 0 for production

// --- Configuration ---
define('DB_FILE', 'zakat_app.db');
define('APP_TITLE', 'Zakat Calculator & Logger');
define('DEFAULT_LANG', 'en');
define('NISAB_GOLD_GRAMS_DEFAULT', 87.48);
define('NISAB_SILVER_GRAMS_DEFAULT', 612.36);
define('ZAKAT_RATE', 0.025); // 2.5%

// --- Language Strings ---
$lang_data = [
    'en' => [
        'toggle_lang' => 'اردو',
        'login' => 'Login',
        'register' => 'Register',
        'logout' => 'Logout',
        'username' => 'Username',
        'password' => 'Password',
        'email' => 'Email',
        'role' => 'Role',
        'user' => 'User',
        'admin' => 'Admin',
        'public' => 'Public',
        'dashboard' => 'Dashboard',
        'calculate_zakat' => 'Calculate Zakat',
        'payment_log' => 'Payment Log',
        'payment_history' => 'Payment History',
        'admin_panel' => 'Admin Panel',
        'manage_users' => 'Manage Users',
        'settings' => 'Settings',
        'all_payment_logs' => 'All Payment Logs',
        'export_logs' => 'Export Logs',
        'welcome' => 'Welcome',
        'info_text' => 'This application helps you calculate Zakat according to the Hanafi fiqh and log your payments. Please register or login to continue.',
        'register_account' => 'Register Account',
        'login_account' => 'Login to Account',
        'already_registered' => 'Already registered? Login here.',
        'not_registered' => 'Not registered yet? Register here.',
        'calculation_year' => 'Zakat Year (e.g., 2025 or 1446)',
        'gold_grams' => 'Gold (grams)',
        'silver_grams' => 'Silver (grams)',
        'cash_on_hand_bank' => 'Cash (on hand & bank)',
        'business_inventory_value' => 'Business Inventory Value',
        'short_term_liabilities' => 'Short-term Liabilities (debts due within a year)',
        'calculate' => 'Calculate',
        'zakat_calculation_result' => 'Zakat Calculation Result',
        'total_assets' => 'Total Zakatable Assets',
        'zakatable_wealth' => 'Net Zakatable Wealth',
        'nisab_threshold_used' => 'Nisab Threshold Used',
        'nisab_based_on_gold' => 'Gold Nisab ('.NISAB_GOLD_GRAMS_DEFAULT.'g)',
        'nisab_based_on_silver' => 'Silver Nisab Value ('.NISAB_SILVER_GRAMS_DEFAULT.'g)',
        'zakat_due' => 'Zakat Due',
        'zakat_not_due' => 'Zakat Not Due (below Nisab)',
        'log_payment' => 'Log New Payment',
        'amount' => 'Amount',
        'payment_date' => 'Payment Date',
        'recipient' => 'Recipient/Organization',
        'status' => 'Status',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'notes' => 'Notes (optional)',
        'save_payment' => 'Save Payment',
        'filter_by_year' => 'Filter by Year',
        'all_years' => 'All Years',
        'date' => 'Date',
        'action' => 'Action',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'gold_price_per_gram' => 'Gold Price per Gram',
        'silver_price_per_gram' => 'Silver Price per Gram',
        'nisab_gold_grams' => 'Nisab Gold (grams)',
        'nisab_silver_grams' => 'Nisab Silver (grams)',
        'update_settings' => 'Update Settings',
        'user_management' => 'User Management',
        'add_user' => 'Add User',
        'update_user' => 'Update User',
        'view_logs' => 'View Logs',
        'id' => 'ID',
        'calculation_date' => 'Calculation Date',
        'logged_at' => 'Logged At',
        'error' => 'Error',
        'success' => 'Success',
        'invalid_input' => 'Invalid input provided.',
        'login_failed' => 'Login failed. Check username/password.',
        'registration_failed' => 'Registration failed. Username or email might be taken.',
        'user_not_found' => 'User not found.',
        'unauthorized' => 'Unauthorized access.',
        'db_error' => 'Database error.',
        'settings_updated' => 'Settings updated successfully.',
        'user_added' => 'User added successfully.',
        'user_updated' => 'User updated successfully.',
        'user_deleted' => 'User deleted successfully.',
        'calculation_saved' => 'Zakat calculation saved.',
        'payment_saved' => 'Payment logged successfully.',
        'payment_deleted' => 'Payment deleted successfully.',
        'zakat_reminder_due' => 'Your annual Zakat calculation may be due.',
        'no_records_found' => 'No records found.',
        'confirm_delete' => 'Are you sure you want to delete this item?',
        'export_all_user_logs' => 'Export All User Payment Logs (CSV)',
        'export_my_logs' => 'Export My Payment Logs (CSV)',
        'last_calculation_date' => 'Last Calculation Date',
    ],
    'ur' => [
        'toggle_lang' => 'English',
        'login' => 'لاگ ان کریں',
        'register' => 'رجسٹر کریں',
        'logout' => 'لاگ آؤٹ',
        'username' => 'صارف نام',
        'password' => 'پاس ورڈ',
        'email' => 'ای میل',
        'role' => 'کردار',
        'user' => 'صارف',
        'admin' => 'ایڈمن',
        'public' => 'عوامی',
        'dashboard' => 'ڈیش بورڈ',
        'calculate_zakat' => 'زکوٰۃ کا حساب لگائیں',
        'payment_log' => 'ادائیگی لاگ',
        'payment_history' => 'ادائیگی کی تاریخ',
        'admin_panel' => 'ایڈمن پینل',
        'manage_users' => 'صارفین کا انتظام',
        'settings' => 'ترتیبات',
        'all_payment_logs' => 'تمام ادائیگی لاگز',
        'export_logs' => 'لاگز برآمد کریں',
        'welcome' => 'خوش آمدید',
        'info_text' => 'یہ ایپلیکیشن حنفی فقہ کے مطابق زکوٰۃ کا حساب لگانے اور آپ کی ادائیگیوں کو لاگ کرنے میں مدد کرتی ہے۔ براہ کرم جاری رکھنے کے لیے رجسٹر یا لاگ ان کریں۔',
        'register_account' => 'اکاؤنٹ رجسٹر کریں',
        'login_account' => 'اکاؤنٹ میں لاگ ان کریں',
        'already_registered' => 'پہلے سے رجسٹرڈ ہیں؟ یہاں لاگ ان کریں۔',
        'not_registered' => 'ابھی تک رجسٹرڈ نہیں ہیں؟ یہاں رجسٹر کریں۔',
        'calculation_year' => 'زکوٰۃ کا سال (مثلاً 2025 یا 1446)',
        'gold_grams' => 'سونا (گرام)',
        'silver_grams' => 'چاندی (گرام)',
        'cash_on_hand_bank' => 'نقدی (ہاتھ میں اور بینک میں)',
        'business_inventory_value' => 'کاروباری سامان کی قیمت',
        'short_term_liabilities' => 'قلیل مدتی واجبات (ایک سال کے اندر واجب الادا قرضے)',
        'calculate' => 'حساب لگائیں',
        'zakat_calculation_result' => 'زکوٰۃ کے حساب کا نتیجہ',
        'total_assets' => 'کل زکوٰۃ کے قابل اثاثے',
        'zakatable_wealth' => 'قابلِ زکوٰۃ کل مالیت',
        'nisab_threshold_used' => 'استعمال شدہ نصاب کی حد',
        'nisab_based_on_gold' => 'سونے کا نصاب ('.NISAB_GOLD_GRAMS_DEFAULT.' گرام)',
        'nisab_based_on_silver' => 'چاندی کا نصاب ('.NISAB_SILVER_GRAMS_DEFAULT.' گرام کی قیمت)',
        'zakat_due' => 'واجب الادا زکوٰۃ',
        'zakat_not_due' => 'زکوٰۃ واجب نہیں (نصاب سے کم)',
        'log_payment' => 'نئی ادائیگی لاگ کریں',
        'amount' => 'رقم',
        'payment_date' => 'ادائیگی کی تاریخ',
        'recipient' => 'وصول کنندہ/ادارہ',
        'status' => 'حیثیت',
        'paid' => 'ادا شدہ',
        'pending' => 'زیر التواء',
        'notes' => 'نوٹس (اختیاری)',
        'save_payment' => 'ادائیگی محفوظ کریں',
        'filter_by_year' => 'سال کے لحاظ سے فلٹر کریں',
        'all_years' => 'تمام سال',
        'date' => 'تاریخ',
        'action' => 'عمل',
        'edit' => 'ترمیم',
        'delete' => 'حذف کریں',
        'gold_price_per_gram' => 'سونے کی قیمت فی گرام',
        'silver_price_per_gram' => 'چاندی کی قیمت فی گرام',
        'nisab_gold_grams' => 'نصاب سونا (گرام)',
        'nisab_silver_grams' => 'نصاب چاندی (گرام)',
        'update_settings' => 'ترتیبات اپ ڈیٹ کریں',
        'user_management' => 'صارف کا انتظام',
        'add_user' => 'صارف شامل کریں',
        'update_user' => 'صارف اپ ڈیٹ کریں',
        'view_logs' => 'لاگز دیکھیں',
        'id' => 'آئی ڈی',
        'calculation_date' => 'حساب کی تاریخ',
        'logged_at' => 'لاگ کرنے کا وقت',
        'error' => 'خرابی',
        'success' => 'کامیابی',
        'invalid_input' => 'غلط ان پٹ فراہم کیا گیا ہے۔',
        'login_failed' => 'لاگ ان ناکام۔ صارف نام/پاس ورڈ چیک کریں۔',
        'registration_failed' => 'رجسٹریشن ناکام۔ صارف نام یا ای میل پہلے سے استعمال میں ہو سکتا ہے۔',
        'user_not_found' => 'صارف نہیں ملا۔',
        'unauthorized' => 'غیر مجاز رسائی۔',
        'db_error' => 'ڈیٹا بیس میں خرابی۔',
        'settings_updated' => 'ترتیبات کامیابی سے اپ ڈیٹ ہو گئیں۔',
        'user_added' => 'صارف کامیابی سے شامل ہو گیا۔',
        'user_updated' => 'صارف کامیابی سے اپ ڈیٹ ہو گیا۔',
        'user_deleted' => 'صارف کامیابی سے حذف ہو گیا۔',
        'calculation_saved' => 'زکوٰۃ کا حساب محفوظ کر لیا گیا۔',
        'payment_saved' => 'ادائیگی کامیابی سے لاگ ہو گئی۔',
        'payment_deleted' => 'ادائیگی کامیابی سے حذف ہو گئی۔',
        'zakat_reminder_due' => 'آپ کی سالانہ زکوٰۃ کا حساب واجب الادا ہو سکتا ہے۔',
        'no_records_found' => 'کوئی ریکارڈ نہیں ملا۔',
        'confirm_delete' => 'کیا آپ واقعی اس آئٹم کو حذف کرنا چاہتے ہیں؟',
        'export_all_user_logs' => 'تمام صارفین کے ادائیگی لاگز برآمد کریں (CSV)',
        'export_my_logs' => 'میرے ادائیگی لاگز برآمد کریں (CSV)',
        'last_calculation_date' => 'آخری حساب کی تاریخ',
    ]
];

// --- Language Selection ---
$current_lang = DEFAULT_LANG;
if (isset($_GET['lang']) && isset($lang_data[$_GET['lang']])) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: ".$_SERVER['PHP_SELF']); // Redirect to remove lang from URL
    exit;
} elseif (isset($_SESSION['lang']) && isset($lang_data[$_SESSION['lang']])) {
    $current_lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['lang']) && isset($lang_data[$_COOKIE['lang']])) {
    $current_lang = $_COOKIE['lang'];
}
setcookie('lang', $current_lang, time() + (365 * 24 * 60 * 60), "/");

function t($key) {
    global $lang_data, $current_lang;
    return $lang_data[$current_lang][$key] ?? $key;
}

// --- Database Setup ---
function get_db() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO('sqlite:'.DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Create tables if they don't exist
            $db->exec("CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                email TEXT UNIQUE,
                role TEXT NOT NULL DEFAULT 'user',
                lang TEXT DEFAULT '".DEFAULT_LANG."',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS zakat_calculations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                year INTEGER NOT NULL,
                gold_grams REAL DEFAULT 0,
                silver_grams REAL DEFAULT 0,
                cash REAL DEFAULT 0,
                inventory_value REAL DEFAULT 0,
                short_term_liabilities REAL DEFAULT 0,
                total_assets REAL DEFAULT 0,
                zakatable_wealth REAL DEFAULT 0,
                nisab_threshold_used TEXT,
                nisab_value_at_calc REAL DEFAULT 0,
                zakat_due REAL DEFAULT 0,
                calculation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            $db->exec("CREATE TABLE IF NOT EXISTS zakat_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                calculation_id INTEGER,
                year INTEGER NOT NULL,
                amount REAL NOT NULL,
                payment_date DATE NOT NULL,
                recipient TEXT,
                status TEXT DEFAULT 'paid',
                notes TEXT,
                logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (calculation_id) REFERENCES zakat_calculations(id) ON DELETE SET NULL
            )");

            // Initialize default settings if not present
            $defaults = [
                'gold_price_per_gram' => '65.00',
                'silver_price_per_gram' => '0.80',
                'nisab_gold_grams' => (string)NISAB_GOLD_GRAMS_DEFAULT,
                'nisab_silver_grams' => (string)NISAB_SILVER_GRAMS_DEFAULT
            ];
            $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)");
            foreach ($defaults as $key => $value) {
                $stmt->execute([':key' => $key, ':value' => $value]);
            }
             // Add default admin if no users exist
            $user_count = $db->query("SELECT COUNT(*) as count FROM users")->fetchColumn();
            if ($user_count == 0) {
                $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
                $db->exec("INSERT INTO users (username, password_hash, email, role) VALUES ('admin', '$admin_pass', 'admin@example.com', 'admin')");
            }

        } catch (PDOException $e) {
            die("DB Connection Error: " . $e->getMessage());
        }
    }
    return $db;
}
$db = get_db();

// --- Helper Functions ---
function get_setting($key) {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :key");
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : null;
}

function update_setting($key, $value) {
    global $db;
    $stmt = $db->prepare("UPDATE settings SET value = :value WHERE key = :key");
    return $stmt->execute([':key' => $key, ':value' => $value]);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_role() {
     return $_SESSION['user_role'] ?? 'public';
}

function redirect($action = '') {
    header("Location: ".$_SERVER['PHP_SELF'].($action ? '?action='.$action : ''));
    exit;
}

function set_message($type, $message_key) {
    $_SESSION['message'] = ['type' => $type, 'text' => t($message_key)];
}

function display_message() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message']['type'];
        $text = $_SESSION['message']['text'];
        echo "<div class='message {$type}'>".htmlspecialchars($text)."</div>";
        unset($_SESSION['message']);
    }
}

function calculate_zakat($gold_grams, $silver_grams, $cash, $inventory_value, $liabilities) {
    $gold_price = (float) get_setting('gold_price_per_gram');
    $silver_price = (float) get_setting('silver_price_per_gram');
    $nisab_gold_grams = (float) get_setting('nisab_gold_grams');
    $nisab_silver_grams = (float) get_setting('nisab_silver_grams');

    $gold_value = $gold_grams * $gold_price;
    $silver_value = $silver_grams * $silver_price;

    $total_assets = $gold_value + $silver_value + $cash + $inventory_value;
    $zakatable_wealth = $total_assets - $liabilities;

    $nisab_threshold_used = '';
    $nisab_value = 0;
    $zakat_due = 0;

    // Determine Nisab based on Hanafi rules
    if ($zakatable_wealth <= 0) {
        return ['total_assets' => $total_assets, 'zakatable_wealth' => $zakatable_wealth, 'nisab_threshold_used' => '', 'nisab_value_at_calc' => 0, 'zakat_due' => 0];
    }

    $silver_nisab_value = $nisab_silver_grams * $silver_price;
    $gold_nisab_value = $nisab_gold_grams * $gold_price; // Only for comparison if only gold is owned

    $has_only_gold = $gold_grams > 0 && $silver_grams == 0 && $cash == 0 && $inventory_value == 0;
    $has_only_silver = $silver_grams > 0 && $gold_grams == 0 && $cash == 0 && $inventory_value == 0;

    if ($has_only_gold) {
        if ($gold_grams >= $nisab_gold_grams) {
            $nisab_threshold_used = 'nisab_based_on_gold';
            $nisab_value = $gold_nisab_value; // Or just the gram threshold
            if ($zakatable_wealth >= $nisab_value){ // Compare value vs value or grams vs grams? Sticking to grams vs grams threshold for 'gold only'.
                 if($gold_grams >= $nisab_gold_grams) {
                    $zakat_due = $zakatable_wealth * ZAKAT_RATE;
                 }
            }
        }
    } elseif ($has_only_silver) {
         if ($silver_grams >= $nisab_silver_grams) {
            $nisab_threshold_used = 'nisab_based_on_silver';
            $nisab_value = $silver_nisab_value;
            if ($zakatable_wealth >= $nisab_value) {
                 $zakat_due = $zakatable_wealth * ZAKAT_RATE;
            }
        }
    } else { // Mixed assets or only cash/inventory
        $nisab_threshold_used = 'nisab_based_on_silver';
        $nisab_value = $silver_nisab_value;
        if ($zakatable_wealth >= $nisab_value) {
            $zakat_due = $zakatable_wealth * ZAKAT_RATE;
        }
    }


    return [
        'total_assets' => $total_assets,
        'zakatable_wealth' => $zakatable_wealth,
        'nisab_threshold_used' => $nisab_threshold_used,
        'nisab_value_at_calc' => $nisab_value,
        'zakat_due' => max(0, $zakat_due) // Ensure zakat is not negative
    ];
}

function get_last_calculation_date($user_id) {
     global $db;
     $stmt = $db->prepare("SELECT MAX(calculation_date) as last_date FROM zakat_calculations WHERE user_id = :user_id");
     $stmt->execute([':user_id' => $user_id]);
     $result = $stmt->fetchColumn();
     return $result;
}

function check_zakat_due_reminder($user_id) {
    $last_date_str = get_last_calculation_date($user_id);
    if ($last_date_str) {
        $last_date = new DateTime($last_date_str);
        $now = new DateTime();
        $diff = $now->diff($last_date);
        // Using ~354 days for lunar year approximation
        if ($diff->days > 354) {
            return true;
        }
    }
    // Also remind if no calculation exists yet
    // else if (!$last_date_str && is_logged_in() && !is_admin()){
    //     return true; // Remind new users too
    // }
    return false;
}

function export_csv($data, $filename = 'export.csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename='.$filename);
    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        // Header row
        fputcsv($output, array_keys($data[0]));
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}


// --- Request Handling ---
$action = $_GET['action'] ?? 'dashboard';
$user_role = get_user_role();
$user_id = get_user_id();

// --- POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';

    try {
        // --- Authentication Actions ---
        if ($post_action === 'register') {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
            $password = $_POST['password'] ?? '';
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

            if ($username && $password && $email) {
                $stmt_check = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
                $stmt_check->execute([':username' => $username, ':email' => $email]);
                if ($stmt_check->fetch()) {
                    set_message('error', 'registration_failed');
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role, lang) VALUES (:username, :hash, :email, 'user', :lang)");
                    if ($stmt->execute([':username' => $username, ':hash' => $hash, ':email' => $email, ':lang' => $current_lang])) {
                         set_message('success', 'Registration successful! Please login.'); // Specific message, not using t()
                         redirect('login');
                    } else {
                         set_message('error', 'registration_failed');
                    }
                }
            } else {
                set_message('error', 'invalid_input');
            }
            redirect('register');
        }
        elseif ($post_action === 'login') {
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
            $password = $_POST['password'] ?? '';

            if ($username && $password) {
                $stmt = $db->prepare("SELECT id, password_hash, role, lang FROM users WHERE username = :username");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['lang'] = $user['lang'] ?? $current_lang; // Set session lang from user profile
                    setcookie('lang', $_SESSION['lang'], time() + (365 * 24 * 60 * 60), "/"); // Update cookie too
                    redirect('dashboard');
                } else {
                    set_message('error', 'login_failed');
                }
            } else {
                set_message('error', 'invalid_input');
            }
            redirect('login');
        }
        // --- User Actions ---
        elseif ($post_action === 'calculate' && is_logged_in() && $user_role !== 'public') {
            $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
            $gold = filter_input(INPUT_POST, 'gold_grams', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $silver = filter_input(INPUT_POST, 'silver_grams', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $cash = filter_input(INPUT_POST, 'cash', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $inventory = filter_input(INPUT_POST, 'inventory_value', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $liabilities = filter_input(INPUT_POST, 'short_term_liabilities', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

             if ($year === false || $gold === false || $silver === false || $cash === false || $inventory === false || $liabilities === false) {
                 set_message('error', 'invalid_input');
                 redirect('calculate');
             }
             $gold = max(0, $gold);
             $silver = max(0, $silver);
             $cash = max(0, $cash);
             $inventory = max(0, $inventory);
             $liabilities = max(0, $liabilities);


            $result = calculate_zakat($gold, $silver, $cash, $inventory, $liabilities);

            $stmt = $db->prepare("INSERT INTO zakat_calculations
                (user_id, year, gold_grams, silver_grams, cash, inventory_value, short_term_liabilities, total_assets, zakatable_wealth, nisab_threshold_used, nisab_value_at_calc, zakat_due, calculation_date)
                VALUES (:uid, :yr, :gold, :silver, :cash, :inv, :lia, :ta, :zw, :ntu, :nval, :zd, CURRENT_TIMESTAMP)");
            $stmt->execute([
                ':uid' => $user_id,
                ':yr' => $year,
                ':gold' => $gold,
                ':silver' => $silver,
                ':cash' => $cash,
                ':inv' => $inventory,
                ':lia' => $liabilities,
                ':ta' => $result['total_assets'],
                ':zw' => $result['zakatable_wealth'],
                ':ntu' => $result['nisab_threshold_used'],
                ':nval' => $result['nisab_value_at_calc'],
                ':zd' => $result['zakat_due']
            ]);
            $calc_id = $db->lastInsertId();

            // Store result in session to display on the next page load
            $_SESSION['last_calculation_result'] = $result;
             $_SESSION['last_calculation_input'] = compact('year', 'gold', 'silver', 'cash', 'inventory', 'liabilities');
             $_SESSION['last_calculation_id'] = $calc_id;


            set_message('success', 'calculation_saved');
            redirect('calculate');

        }
        elseif ($post_action === 'log_payment' && is_logged_in() && $user_role !== 'public') {
             $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
             $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
             $payment_date = filter_input(INPUT_POST, 'payment_date', FILTER_SANITIZE_STRING); // Validate date format?
             $recipient = filter_input(INPUT_POST, 'recipient', FILTER_SANITIZE_STRING);
             $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
             $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
             $calculation_id = filter_input(INPUT_POST, 'calculation_id', FILTER_VALIDATE_INT);

             // Basic validation
             if ($year && $amount > 0 && $payment_date && $recipient && in_array($status, ['paid', 'pending'])) {
                 // Validate date format (simple YYYY-MM-DD check)
                 if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
                    $stmt = $db->prepare("INSERT INTO zakat_payments (user_id, calculation_id, year, amount, payment_date, recipient, status, notes, logged_at)
                                         VALUES (:uid, :calc_id, :yr, :amt, :pdate, :rec, :stat, :notes, CURRENT_TIMESTAMP)");
                    if($stmt->execute([
                        ':uid' => $user_id,
                        ':calc_id' => $calculation_id ?: null, // Allow null if not directly linked
                        ':yr' => $year,
                        ':amt' => $amount,
                        ':pdate' => $payment_date,
                        ':rec' => $recipient,
                        ':stat' => $status,
                        ':notes' => $notes
                    ])) {
                         set_message('success', 'payment_saved');
                     } else {
                         set_message('error', 'db_error');
                     }
                 } else {
                     set_message('error', 'Invalid date format. Use YYYY-MM-DD.');
                 }
             } else {
                 set_message('error', 'invalid_input');
             }
             redirect('payment_log');
        }
        elseif ($post_action === 'delete_payment' && is_logged_in() && $user_role !== 'public') {
             $payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
             if ($payment_id) {
                 // Ensure user owns this payment OR is admin
                 $check_sql = "SELECT user_id FROM zakat_payments WHERE id = :id";
                 $stmt_check = $db->prepare($check_sql);
                 $stmt_check->execute([':id' => $payment_id]);
                 $payment_owner_id = $stmt_check->fetchColumn();

                 if ($payment_owner_id && ($payment_owner_id == $user_id || is_admin())) {
                    $stmt = $db->prepare("DELETE FROM zakat_payments WHERE id = :id");
                    if($stmt->execute([':id' => $payment_id])) {
                        set_message('success', 'payment_deleted');
                    } else {
                        set_message('error', 'db_error');
                    }
                 } else {
                    set_message('error', 'unauthorized');
                 }

             } else {
                 set_message('error', 'invalid_input');
             }
            redirect('payment_history');
        }
        // --- Admin Actions ---
        elseif ($post_action === 'update_settings' && is_admin()) {
            $settings_to_update = [
                'gold_price_per_gram',
                'silver_price_per_gram',
                'nisab_gold_grams',
                'nisab_silver_grams'
            ];
            $error = false;
            foreach($settings_to_update as $key) {
                $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                 if ($value !== false && $value >= 0) {
                     update_setting($key, (string)$value);
                 } else {
                     $error = true;
                     set_message('error', "Invalid value for $key."); // More specific error
                     break; // Stop on first error
                 }
            }
            if (!$error) set_message('success', 'settings_updated');
            redirect('settings');
        }
         elseif ($post_action === 'add_user' && is_admin()) {
             $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
             $password = $_POST['password'] ?? '';
             $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
             $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

             if ($username && $password && $email && in_array($role, ['user', 'admin'])) {
                $stmt_check = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
                $stmt_check->execute([':username' => $username, ':email' => $email]);
                 if ($stmt_check->fetch()) {
                     set_message('error', 'registration_failed'); // Re-use message
                 } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role, lang) VALUES (:username, :hash, :email, :role, :lang)");
                    if($stmt->execute([':username' => $username, ':hash' => $hash, ':email' => $email, ':role' => $role, ':lang' => $current_lang])){
                        set_message('success', 'user_added');
                    } else {
                        set_message('error', 'db_error');
                    }
                 }
             } else {
                set_message('error', 'invalid_input');
             }
             redirect('manage_users');
         }
        elseif ($post_action === 'update_user' && is_admin()) {
             $user_id_to_update = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
             $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
             $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
             $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
             $password = $_POST['password'] ?? ''; // Optional password update

             if ($user_id_to_update && $username && $email && in_array($role, ['user', 'admin'])) {
                  // Check for uniqueness conflict (excluding the user being updated)
                 $stmt_check = $db->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
                 $stmt_check->execute([':username' => $username, ':email' => $email, ':id' => $user_id_to_update]);
                 if ($stmt_check->fetch()) {
                     set_message('error', 'Username or email already taken by another user.');
                 } else {
                    $params = [':username' => $username, ':email' => $email, ':role' => $role, ':id' => $user_id_to_update];
                    $sql = "UPDATE users SET username = :username, email = :email, role = :role";
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql .= ", password_hash = :hash";
                        $params[':hash'] = $hash;
                    }
                    $sql .= " WHERE id = :id";
                    $stmt = $db->prepare($sql);
                    if ($stmt->execute($params)) {
                        set_message('success', 'user_updated');
                    } else {
                        set_message('error', 'db_error');
                    }
                }
             } else {
                set_message('error', 'invalid_input');
             }
             redirect('manage_users');
        }
        elseif ($post_action === 'delete_user' && is_admin()) {
             $user_id_to_delete = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
             if ($user_id_to_delete && $user_id_to_delete != get_user_id()) { // Prevent admin deleting self
                // Optional: Also delete related calculations/payments or handle via FOREIGN KEY constraints (ON DELETE CASCADE set)
                $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                if($stmt->execute([':id' => $user_id_to_delete])) {
                    set_message('success', 'user_deleted');
                } else {
                    set_message('error', 'db_error');
                }
             } else {
                set_message('error', 'Invalid request or cannot delete yourself.');
             }
             redirect('manage_users');
        }
        // --- Export Actions ---
         elseif ($post_action === 'export_my_logs' && is_logged_in()) {
             $stmt = $db->prepare("SELECT p.year, p.payment_date, p.amount, p.recipient, p.status, p.notes, p.logged_at, c.zakatable_wealth, c.zakat_due
                                 FROM zakat_payments p
                                 LEFT JOIN zakat_calculations c ON p.calculation_id = c.id
                                 WHERE p.user_id = :user_id ORDER BY p.year DESC, p.payment_date DESC");
             $stmt->execute([':user_id' => $user_id]);
             $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
             export_csv($logs, 'my_zakat_payments_'.date('Ymd').'.csv');
         }
         elseif ($post_action === 'export_all_logs' && is_admin()) {
             $stmt = $db->prepare("SELECT u.username, u.email, p.year, p.payment_date, p.amount, p.recipient, p.status, p.notes, p.logged_at, c.zakatable_wealth, c.zakat_due
                                 FROM zakat_payments p
                                 JOIN users u ON p.user_id = u.id
                                 LEFT JOIN zakat_calculations c ON p.calculation_id = c.id
                                 ORDER BY u.username, p.year DESC, p.payment_date DESC");
             $stmt->execute();
             $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
             export_csv($logs, 'all_zakat_payments_'.date('Ymd').'.csv');
         }

    } catch (PDOException $e) {
        set_message('error', 'db_error');
        // Optionally log the detailed error: error_log("Database Error: " . $e->getMessage());
        redirect($action); // Redirect back to avoid resubmission
    } catch (Exception $e) {
        set_message('error', 'An unexpected error occurred.');
         // Optionally log the detailed error: error_log("General Error: " . $e->getMessage());
         redirect($action); // Redirect back
    }
}

// --- GET Actions (Logout, specific views) ---
if ($action === 'logout') {
    session_destroy();
    setcookie('lang', '', time() - 3600, "/"); // Clear lang cookie
    redirect('login');
}

// Redirect logged-in users from login/register pages
if (is_logged_in() && ($action === 'login' || $action === 'register')) {
    redirect('dashboard');
}

// Redirect non-logged-in users trying to access protected areas
if (!is_logged_in() && !in_array($action, ['login', 'register', 'info'])) {
     if ($action !== 'dashboard') set_message('error','Please login to access this page.'); // Don't show msg for default redirect
    redirect('login');
}

// Redirect non-admins trying to access admin areas
if (!is_admin() && in_array($action, ['admin_panel', 'manage_users', 'settings', 'all_payment_logs', 'edit_user', 'view_user_logs'])) {
    set_message('error', 'unauthorized');
    redirect('dashboard');
}

// Default action for logged in users is dashboard
if ($action === '' && is_logged_in()) {
    $action = 'dashboard';
} elseif ($action === '' || $action === 'dashboard' && !is_logged_in()) {
    $action = 'login'; // Default for non-logged in is login
}


?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo ($current_lang === 'ur' ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_TITLE); ?> - <?php echo t($action); ?></title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { max-width: 960px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        header { background-color: #5cb85c; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0; }
        header h1 { margin: 0; font-size: 1.5em; }
        nav ul { list-style: none; padding: 0; margin: 0; display: flex; }
        nav ul li { margin-left: 15px; }
        nav ul li a { color: #fff; text-decoration: none; padding: 5px 10px; border-radius: 4px; transition: background-color 0.3s; }
        nav ul li a:hover, nav ul li a.active { background-color: #4cae4c; }
        .lang-toggle a { background-color: #f0ad4e; color:#fff; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.9em;}
        .lang-toggle a:hover { background-color: #eea236;}
        main { padding: 20px 0; }
        h2 { color: #5cb85c; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0;}
        h3 { color: #4cae4c; margin-top: 25px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="date"], select, textarea {
            width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        input[type="number"] { appearance: textfield; /* Firefox */ }
         input[type=number]::-webkit-inner-spin-button,
         input[type=number]::-webkit-outer-spin-button {
           -webkit-appearance: none;
           margin: 0;
         }
        textarea { resize: vertical; min-height: 80px; }
        button, input[type="submit"] { background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        button:hover, input[type="submit"]:hover { background-color: #4cae4c; }
        button.danger, input[type="submit"].danger { background-color: #d9534f; }
        button.danger:hover, input[type="submit"].danger:hover { background-color: #c9302c; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .message.success { background-color: #dff0d8; border-color: #d6e9c6; color: #3c763d; }
        .message.error { background-color: #f2dede; border-color: #ebccd1; color: #a94442; }
        .message.info { background-color: #d9edf7; border-color: #bce8f1; color: #31708f; }
        .message.warning { background-color: #fcf8e3; border-color: #faebcc; color: #8a6d3b; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; color: #333; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .form-group { margin-bottom: 15px; }
        .calculation-result { background-color: #e9f5e9; border: 1px solid #d0e9d0; padding: 15px; margin-top: 20px; border-radius: 4px;}
        .calculation-result p { margin: 5px 0; }
        .calculation-result strong { color: #3c763d; }
        .zakat-due { font-weight: bold; font-size: 1.2em; color: #d9534f; }
        .zakat-not-due { font-weight: bold; font-size: 1.1em; color: #5cb85c; }
        .responsive-table { overflow-x: auto; }
        .action-links a, .action-links button { margin-right: 5px; padding: 3px 6px; font-size: 0.9em; text-decoration: none; display: inline-block; border: none; cursor: pointer; }
         .action-links button { background: none; color: #d9534f; padding: 0; vertical-align: middle; }
         .action-links button:hover { text-decoration: underline; }
         .action-links a.edit { color: #5bc0de; }
         .action-links a.edit:hover { text-decoration: underline; }
         .login-register-form { max-width: 400px; margin: 30px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;}
         .form-toggle-link { text-align: center; margin-top: 15px;}
         .dashboard-summary p { margin: 5px 0; font-size: 1.1em;}
         .dashboard-summary strong { color: #5cb85c; }
         .reminder { background-color: #fcf8e3; border-color: #faebcc; color: #8a6d3b; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #faebcc;}
        footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; color: #777; font-size: 0.9em; }
        /* Responsive */
        @media (max-width: 768px) {
            header { flex-direction: column; align-items: flex-start; }
            nav ul { flex-direction: column; width: 100%; margin-top: 10px;}
            nav ul li { margin: 5px 0; }
            nav ul li a { display: block; text-align: center; }
            .container { margin: 10px; padding: 15px; }
            input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="date"], select, textarea { padding: 8px; }
            button, input[type="submit"] { width: 100%; padding: 12px; }
            th, td { padding: 6px; font-size: 0.9em;}
        }
         <?php if ($current_lang === 'ur'): ?>
         body { font-family: 'Noto Nastaliq Urdu', sans-serif; direction: rtl; }
         nav ul li { margin-left: 0; margin-right: 15px; }
         th, td { text-align: right; }
         label { text-align: right; }
         input, select, textarea { direction: rtl; }
         input[type="number"], input[type="email"], input[type="date"] { direction: ltr; text-align: right; /* Keep LTR for specific inputs */}
         header h1 { font-size: 1.8em; }
          @media (max-width: 768px) {
             nav ul li { margin-right: 0; }
             nav ul li a { text-align: center; }
         }
        <?php endif; ?>
    </style>
     <?php if ($current_lang === 'ur'): ?>
     <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <header>
        <h1><?php echo htmlspecialchars(APP_TITLE); ?></h1>
        <nav>
            <ul>
                <?php if (is_logged_in()): ?>
                    <li><a href="?action=dashboard" class="<?php echo $action === 'dashboard' ? 'active' : ''; ?>"><?php echo t('dashboard'); ?></a></li>
                    <li><a href="?action=calculate" class="<?php echo $action === 'calculate' ? 'active' : ''; ?>"><?php echo t('calculate_zakat'); ?></a></li>
                    <li><a href="?action=payment_log" class="<?php echo $action === 'payment_log' ? 'active' : ''; ?>"><?php echo t('payment_log'); ?></a></li>
                    <li><a href="?action=payment_history" class="<?php echo $action === 'payment_history' ? 'active' : ''; ?>"><?php echo t('payment_history'); ?></a></li>
                    <?php if (is_admin()): ?>
                        <li><a href="?action=admin_panel" class="<?php echo $action === 'admin_panel' || in_array($action, ['manage_users', 'settings', 'all_payment_logs']) ? 'active' : ''; ?>"><?php echo t('admin_panel'); ?></a></li>
                    <?php endif; ?>
                     <li><a href="?action=logout"><?php echo t('logout'); ?> (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                <?php else: ?>
                     <li><a href="?action=login" class="<?php echo $action === 'login' ? 'active' : ''; ?>"><?php echo t('login'); ?></a></li>
                     <li><a href="?action=register" class="<?php echo $action === 'register' ? 'active' : ''; ?>"><?php echo t('register'); ?></a></li>
                <?php endif; ?>
                <li class="lang-toggle"><a href="?lang=<?php echo ($current_lang === 'en' ? 'ur' : 'en'); ?>"><?php echo t('toggle_lang'); ?></a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <main>
            <?php display_message(); ?>

            <?php // --- Page Content --- ?>

            <?php if (!is_logged_in()): ?>
                <?php if ($action === 'login'): ?>
                    <div class="login-register-form">
                        <h2><?php echo t('login_account'); ?></h2>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="form-group">
                                <label for="username"><?php echo t('username'); ?></label>
                                <input type="text" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password"><?php echo t('password'); ?></label>
                                <input type="password" id="password" name="password" required>
                            </div>
                            <button type="submit"><?php echo t('login'); ?></button>
                        </form>
                         <div class="form-toggle-link">
                             <a href="?action=register"><?php echo t('not_registered'); ?></a>
                         </div>
                    </div>
                <?php elseif ($action === 'register'): ?>
                    <div class="login-register-form">
                        <h2><?php echo t('register_account'); ?></h2>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                            <input type="hidden" name="action" value="register">
                            <div class="form-group">
                                <label for="username"><?php echo t('username'); ?></label>
                                <input type="text" id="username" name="username" required>
                            </div>
                             <div class="form-group">
                                <label for="email"><?php echo t('email'); ?></label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="password"><?php echo t('password'); ?></label>
                                <input type="password" id="password" name="password" required>
                            </div>
                            <button type="submit"><?php echo t('register'); ?></button>
                        </form>
                        <div class="form-toggle-link">
                            <a href="?action=login"><?php echo t('already_registered'); ?></a>
                        </div>
                    </div>
                <?php else: // Info page (could be expanded) ?>
                     <h2><?php echo t('welcome'); ?></h2>
                     <p><?php echo t('info_text'); ?></p>
                <?php endif; ?>

            <?php else: // Logged In Users ?>
                 <?php if ($action === 'dashboard'): ?>
                    <h2><?php echo t('dashboard'); ?></h2>
                    <p><?php echo t('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

                    <?php if (check_zakat_due_reminder($user_id) && !is_admin()): ?>
                         <div class="reminder"><?php echo t('zakat_reminder_due'); ?> <a href="?action=calculate"><?php echo t('calculate_zakat'); ?></a></div>
                    <?php endif; ?>

                    <div class="dashboard-summary">
                        <h3>Summary</h3>
                        <?php
                           $last_calc_date = get_last_calculation_date($user_id);
                           $stmt_payments = $db->prepare("SELECT SUM(amount) as total_paid, COUNT(*) as count FROM zakat_payments WHERE user_id = :uid AND status = 'paid'");
                           $stmt_payments->execute([':uid' => $user_id]);
                           $payment_summary = $stmt_payments->fetch();
                        ?>
                        <p><?php echo t('last_calculation_date'); ?>: <strong><?php echo $last_calc_date ? date('Y-m-d', strtotime($last_calc_date)) : 'N/A'; ?></strong></p>
                        <p>Total Payments Logged: <strong><?php echo $payment_summary['count'] ?? 0; ?></strong></p>
                        <p>Total Amount Paid (Logged): <strong><?php echo number_format($payment_summary['total_paid'] ?? 0, 2); ?></strong></p>
                        <br>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" style="display: inline;">
                             <input type="hidden" name="action" value="export_my_logs">
                             <button type="submit"><?php echo t('export_my_logs'); ?></button>
                         </form>

                    </div>

                <?php elseif ($action === 'calculate'): ?>
                    <h2><?php echo t('calculate_zakat'); ?></h2>

                    <?php if (isset($_SESSION['last_calculation_result'])):
                        $result = $_SESSION['last_calculation_result'];
                        $input = $_SESSION['last_calculation_input'];
                        $calc_id = $_SESSION['last_calculation_id'];
                        unset($_SESSION['last_calculation_result']);
                        unset($_SESSION['last_calculation_input']);
                        unset($_SESSION['last_calculation_id']);
                    ?>
                        <div class="calculation-result">
                            <h3><?php echo t('zakat_calculation_result'); ?> (<?php echo t('calculation_year'); ?>: <?php echo htmlspecialchars($input['year']); ?>)</h3>
                            <p><?php echo t('gold_grams'); ?>: <?php echo htmlspecialchars(number_format($input['gold'], 2)); ?> g</p>
                            <p><?php echo t('silver_grams'); ?>: <?php echo htmlspecialchars(number_format($input['silver'], 2)); ?> g</p>
                            <p><?php echo t('cash_on_hand_bank'); ?>: <?php echo htmlspecialchars(number_format($input['cash'], 2)); ?></p>
                            <p><?php echo t('business_inventory_value'); ?>: <?php echo htmlspecialchars(number_format($input['inventory'], 2)); ?></p>
                            <hr>
                            <p><?php echo t('total_assets'); ?>: <strong><?php echo htmlspecialchars(number_format($result['total_assets'], 2)); ?></strong></p>
                            <p><?php echo t('short_term_liabilities'); ?>: <?php echo htmlspecialchars(number_format($input['liabilities'], 2)); ?></p>
                             <p><?php echo t('zakatable_wealth'); ?>: <strong><?php echo htmlspecialchars(number_format($result['zakatable_wealth'], 2)); ?></strong></p>
                             <p><?php echo t('nisab_threshold_used'); ?>:
                                 <strong>
                                 <?php
                                     if ($result['nisab_threshold_used'] === 'nisab_based_on_gold') echo t('nisab_based_on_gold');
                                     elseif ($result['nisab_threshold_used'] === 'nisab_based_on_silver') echo t('nisab_based_on_silver') . ' (' . htmlspecialchars(number_format($result['nisab_value_at_calc'], 2)) . ')';
                                     else echo 'N/A';
                                 ?>
                                 </strong>
                            </p>
                            <hr>
                            <?php if ($result['zakat_due'] > 0): ?>
                                <p class="zakat-due"><?php echo t('zakat_due'); ?>: <?php echo htmlspecialchars(number_format($result['zakat_due'], 2)); ?></p>
                                <br>
                                <a href="?action=payment_log&year=<?php echo $input['year']; ?>&amount=<?php echo round($result['zakat_due'],2); ?>&calc_id=<?php echo $calc_id; ?>"><button><?php echo t('log_payment'); ?></button></a>
                            <?php else: ?>
                                 <p class="zakat-not-due"><?php echo t('zakat_not_due'); ?></p>
                             <?php endif; ?>
                        </div>
                    <?php endif; ?>

                     <form action="<?php echo $_SERVER['PHP_SELF']; ?>?action=calculate" method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="calculate">
                         <div class="form-group">
                             <label for="year"><?php echo t('calculation_year'); ?></label>
                             <input type="number" id="year" name="year" value="<?php echo date('Y'); ?>" required>
                         </div>
                         <div class="form-group">
                            <label for="gold_grams"><?php echo t('gold_grams'); ?></label>
                            <input type="number" step="0.01" min="0" id="gold_grams" name="gold_grams" value="0" required>
                         </div>
                         <div class="form-group">
                            <label for="silver_grams"><?php echo t('silver_grams'); ?></label>
                            <input type="number" step="0.01" min="0" id="silver_grams" name="silver_grams" value="0" required>
                         </div>
                          <div class="form-group">
                             <label for="cash"><?php echo t('cash_on_hand_bank'); ?></label>
                             <input type="number" step="0.01" min="0" id="cash" name="cash" value="0" required>
                         </div>
                         <div class="form-group">
                             <label for="inventory_value"><?php echo t('business_inventory_value'); ?></label>
                             <input type="number" step="0.01" min="0" id="inventory_value" name="inventory_value" value="0" required>
                         </div>
                          <div class="form-group">
                             <label for="short_term_liabilities"><?php echo t('short_term_liabilities'); ?></label>
                             <input type="number" step="0.01" min="0" id="short_term_liabilities" name="short_term_liabilities" value="0" required>
                         </div>
                        <button type="submit"><?php echo t('calculate'); ?></button>
                    </form>


                <?php elseif ($action === 'payment_log'): ?>
                    <h2><?php echo t('log_payment'); ?></h2>
                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>?action=payment_log" method="POST">
                        <input type="hidden" name="action" value="log_payment">
                        <?php if (isset($_GET['calc_id'])): ?>
                        <input type="hidden" name="calculation_id" value="<?php echo htmlspecialchars($_GET['calc_id']); ?>">
                        <?php endif; ?>
                         <div class="form-group">
                             <label for="year"><?php echo t('calculation_year'); ?></label>
                             <input type="number" id="year" name="year" value="<?php echo htmlspecialchars($_GET['year'] ?? date('Y')); ?>" required>
                         </div>
                         <div class="form-group">
                            <label for="amount"><?php echo t('amount'); ?></label>
                            <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="<?php echo htmlspecialchars($_GET['amount'] ?? ''); ?>" required>
                         </div>
                         <div class="form-group">
                             <label for="payment_date"><?php echo t('payment_date'); ?></label>
                             <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                         </div>
                         <div class="form-group">
                             <label for="recipient"><?php echo t('recipient'); ?></label>
                             <input type="text" id="recipient" name="recipient" required>
                         </div>
                         <div class="form-group">
                             <label for="status"><?php echo t('status'); ?></label>
                             <select id="status" name="status" required>
                                 <option value="paid" selected><?php echo t('paid'); ?></option>
                                 <option value="pending"><?php echo t('pending'); ?></option>
                             </select>
                         </div>
                         <div class="form-group">
                             <label for="notes"><?php echo t('notes'); ?></label>
                             <textarea id="notes" name="notes"></textarea>
                         </div>
                        <button type="submit"><?php echo t('save_payment'); ?></button>
                    </form>

                <?php elseif ($action === 'payment_history'): ?>
                    <h2><?php echo t('payment_history'); ?></h2>
                    <?php
                        $filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);
                        $sql = "SELECT p.id, p.year, p.amount, p.payment_date, p.recipient, p.status, p.notes, p.logged_at, c.zakat_due
                                FROM zakat_payments p
                                LEFT JOIN zakat_calculations c ON p.calculation_id = c.id
                                WHERE p.user_id = :uid ";
                        $params = [':uid' => $user_id];
                        if ($filter_year) {
                            $sql .= " AND p.year = :year ";
                            $params[':year'] = $filter_year;
                        }
                        $sql .= " ORDER BY p.year DESC, p.payment_date DESC";
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                        $payments = $stmt->fetchAll();

                         // Get distinct years for filter dropdown
                         $stmt_years = $db->prepare("SELECT DISTINCT year FROM zakat_payments WHERE user_id = :uid ORDER BY year DESC");
                         $stmt_years->execute([':uid' => $user_id]);
                         $years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <input type="hidden" name="action" value="payment_history">
                        <label for="filter_year"><?php echo t('filter_by_year'); ?></label>
                        <select name="filter_year" id="filter_year" onchange="this.form.submit()">
                            <option value=""><?php echo t('all_years'); ?></option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($filter_year == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filter_year): ?>
                        <a href="?action=payment_history" style="margin-left: 10px;">Clear Filter</a>
                        <?php endif; ?>
                    </form>

                    <div class="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo t('year'); ?></th>
                                    <th><?php echo t('payment_date'); ?></th>
                                    <th><?php echo t('amount'); ?></th>
                                    <th><?php echo t('recipient'); ?></th>
                                    <th><?php echo t('status'); ?></th>
                                     <th><?php echo t('zakat_due'); ?> (Calc)</th>
                                    <th><?php echo t('notes'); ?></th>
                                    <th><?php echo t('action'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments): ?>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['year']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                        <td><?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($payment['recipient']); ?></td>
                                        <td><?php echo t(htmlspecialchars($payment['status'])); ?></td>
                                        <td><?php echo $payment['zakat_due'] !== null ? htmlspecialchars(number_format($payment['zakat_due'], 2)) : 'N/A'; ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></td>
                                        <td class="action-links">
                                             <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" style="display: inline;" onsubmit="return confirm('<?php echo t('confirm_delete'); ?>');">
                                                <input type="hidden" name="action" value="delete_payment">
                                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                <button type="submit" class="danger" title="<?php echo t('delete'); ?>">🗑️</button>
                                             </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8"><?php echo t('no_records_found'); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                     <br>
                     <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" style="display: inline;">
                          <input type="hidden" name="action" value="export_my_logs">
                          <button type="submit"><?php echo t('export_my_logs'); ?></button>
                      </form>

                <?php // --- Admin Area --- ?>
                <?php elseif (is_admin()): ?>
                     <?php if ($action === 'admin_panel'): ?>
                         <h2><?php echo t('admin_panel'); ?></h2>
                         <ul>
                             <li><a href="?action=manage_users"><?php echo t('manage_users'); ?></a></li>
                             <li><a href="?action=settings"><?php echo t('settings'); ?></a></li>
                             <li><a href="?action=all_payment_logs"><?php echo t('all_payment_logs'); ?></a></li>
                         </ul>
                     <?php elseif ($action === 'settings'): ?>
                         <h2><?php echo t('settings'); ?></h2>
                         <form action="<?php echo $_SERVER['PHP_SELF']; ?>?action=settings" method="POST">
                             <input type="hidden" name="action" value="update_settings">
                             <div class="form-group">
                                 <label for="gold_price_per_gram"><?php echo t('gold_price_per_gram'); ?></label>
                                 <input type="number" step="0.01" min="0" id="gold_price_per_gram" name="gold_price_per_gram" value="<?php echo htmlspecialchars(get_setting('gold_price_per_gram')); ?>" required>
                             </div>
                             <div class="form-group">
                                 <label for="silver_price_per_gram"><?php echo t('silver_price_per_gram'); ?></label>
                                 <input type="number" step="0.01" min="0" id="silver_price_per_gram" name="silver_price_per_gram" value="<?php echo htmlspecialchars(get_setting('silver_price_per_gram')); ?>" required>
                             </div>
                             <div class="form-group">
                                 <label for="nisab_gold_grams"><?php echo t('nisab_gold_grams'); ?></label>
                                 <input type="number" step="0.01" min="0" id="nisab_gold_grams" name="nisab_gold_grams" value="<?php echo htmlspecialchars(get_setting('nisab_gold_grams')); ?>" required>
                             </div>
                             <div class="form-group">
                                 <label for="nisab_silver_grams"><?php echo t('nisab_silver_grams'); ?></label>
                                 <input type="number" step="0.01" min="0" id="nisab_silver_grams" name="nisab_silver_grams" value="<?php echo htmlspecialchars(get_setting('nisab_silver_grams')); ?>" required>
                             </div>
                             <button type="submit"><?php echo t('update_settings'); ?></button>
                         </form>

                    <?php elseif ($action === 'manage_users'): ?>
                        <h2><?php echo t('user_management'); ?></h2>

                        <?php // --- Add/Edit User Form ---
                            $edit_user_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
                            $user_to_edit = null;
                            if ($edit_user_id) {
                                $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = :id");
                                $stmt->execute([':id' => $edit_user_id]);
                                $user_to_edit = $stmt->fetch();
                            }
                        ?>
                        <h3><?php echo $user_to_edit ? t('update_user') : t('add_user'); ?></h3>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?action=manage_users" method="POST">
                            <input type="hidden" name="action" value="<?php echo $user_to_edit ? 'update_user' : 'add_user'; ?>">
                             <?php if ($user_to_edit): ?>
                                <input type="hidden" name="user_id" value="<?php echo $user_to_edit['id']; ?>">
                             <?php endif; ?>
                             <div class="form-group">
                                <label for="username"><?php echo t('username'); ?></label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_to_edit['username'] ?? ''); ?>" required>
                             </div>
                             <div class="form-group">
                                <label for="email"><?php echo t('email'); ?></label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email'] ?? ''); ?>" required>
                             </div>
                            <div class="form-group">
                                <label for="password"><?php echo t('password'); ?><?php echo $user_to_edit ? ' (Leave blank to keep current)' : ''; ?></label>
                                <input type="password" id="password" name="password" <?php echo $user_to_edit ? '' : 'required'; ?>>
                             </div>
                             <div class="form-group">
                                <label for="role"><?php echo t('role'); ?></label>
                                <select id="role" name="role" required>
                                     <option value="user" <?php echo (($user_to_edit['role'] ?? '') === 'user') ? 'selected' : ''; ?>><?php echo t('user'); ?></option>
                                    <option value="admin" <?php echo (($user_to_edit['role'] ?? '') === 'admin') ? 'selected' : ''; ?>><?php echo t('admin'); ?></option>
                                </select>
                             </div>
                            <button type="submit"><?php echo $user_to_edit ? t('update_user') : t('add_user'); ?></button>
                             <?php if ($user_to_edit): ?>
                                <a href="?action=manage_users" style="margin-left: 10px;">Cancel Edit</a>
                            <?php endif; ?>
                        </form>

                        <h3 style="margin-top: 30px;">All Users</h3>
                        <?php
                            $stmt_all = $db->query("SELECT id, username, email, role, created_at FROM users ORDER BY username");
                            $users = $stmt_all->fetchAll();
                        ?>
                        <div class="responsive-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?php echo t('id'); ?></th>
                                        <th><?php echo t('username'); ?></th>
                                        <th><?php echo t('email'); ?></th>
                                        <th><?php echo t('role'); ?></th>
                                        <th>Created</th>
                                        <th><?php echo t('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($users): ?>
                                    <?php foreach ($users as $user_row): ?>
                                    <tr>
                                        <td><?php echo $user_row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user_row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user_row['email']); ?></td>
                                        <td><?php echo t(htmlspecialchars($user_row['role'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($user_row['created_at'])); ?></td>
                                        <td class="action-links">
                                            <a href="?action=manage_users&edit_id=<?php echo $user_row['id']; ?>" class="edit"><?php echo t('edit'); ?></a>
                                            <?php if ($user_row['id'] != get_user_id()): // Cant delete self ?>
                                            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" style="display: inline;" onsubmit="return confirm('<?php echo t('confirm_delete'); ?>');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user_row['id']; ?>">
                                                <button type="submit" class="danger" title="<?php echo t('delete'); ?>">🗑️</button>
                                             </form>
                                             <?php endif; ?>
                                             <a href="?action=all_payment_logs&user_id=<?php echo $user_row['id']; ?>"><?php echo t('view_logs'); ?></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                     <tr><td colspan="6"><?php echo t('no_records_found'); ?></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($action === 'all_payment_logs'): ?>
                         <h2><?php echo t('all_payment_logs'); ?></h2>
                         <?php
                            $filter_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
                            $filter_year_admin = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);

                            $sql = "SELECT p.id, p.year, p.amount, p.payment_date, p.recipient, p.status, p.notes, p.logged_at, u.username, u.id as user_id
                                    FROM zakat_payments p
                                    JOIN users u ON p.user_id = u.id ";
                            $params = [];
                            $where_clauses = [];
                            if ($filter_user_id) {
                                $where_clauses[] = " p.user_id = :uid ";
                                $params[':uid'] = $filter_user_id;
                            }
                             if ($filter_year_admin) {
                                $where_clauses[] = " p.year = :year ";
                                $params[':year'] = $filter_year_admin;
                             }
                             if (!empty($where_clauses)) {
                                 $sql .= " WHERE " . implode(' AND ', $where_clauses);
                             }
                             $sql .= " ORDER BY u.username, p.year DESC, p.payment_date DESC";

                             $stmt = $db->prepare($sql);
                             $stmt->execute($params);
                             $all_payments = $stmt->fetchAll();

                             // Get users and years for filters
                             $stmt_users = $db->query("SELECT id, username FROM users ORDER BY username");
                             $users_list = $stmt_users->fetchAll();
                             $stmt_years_admin = $db->query("SELECT DISTINCT year FROM zakat_payments ORDER BY year DESC");
                             $years_list_admin = $stmt_years_admin->fetchAll(PDO::FETCH_COLUMN);

                         ?>
                         <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                             <input type="hidden" name="action" value="all_payment_logs">
                             <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                                 <div>
                                     <label for="user_id"><?php echo t('user'); ?></label>
                                     <select name="user_id" id="user_id" onchange="this.form.submit()">
                                         <option value="">All Users</option>
                                         <?php foreach ($users_list as $user_item): ?>
                                             <option value="<?php echo $user_item['id']; ?>" <?php echo ($filter_user_id == $user_item['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user_item['username']); ?></option>
                                         <?php endforeach; ?>
                                     </select>
                                 </div>
                                 <div>
                                      <label for="filter_year_admin"><?php echo t('filter_by_year'); ?></label>
                                      <select name="filter_year" id="filter_year_admin" onchange="this.form.submit()">
                                          <option value=""><?php echo t('all_years'); ?></option>
                                          <?php foreach ($years_list_admin as $year): ?>
                                              <option value="<?php echo $year; ?>" <?php echo ($filter_year_admin == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                          <?php endforeach; ?>
                                      </select>
                                 </div>
                                  <div>
                                      <label>&nbsp;</label> <!-- Spacer -->
                                     <?php if ($filter_user_id || $filter_year_admin): ?>
                                         <a href="?action=all_payment_logs">Clear Filters</a>
                                     <?php endif; ?>
                                 </div>
                            </div>
                         </form>

                         <div class="responsive-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?php echo t('username'); ?></th>
                                        <th><?php echo t('year'); ?></th>
                                        <th><?php echo t('payment_date'); ?></th>
                                        <th><?php echo t('amount'); ?></th>
                                        <th><?php echo t('recipient'); ?></th>
                                        <th><?php echo t('status'); ?></th>
                                        <th><?php echo t('notes'); ?></th>
                                        <th><?php echo t('action'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($all_payments): ?>
                                        <?php foreach ($all_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['username']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['year']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                            <td><?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></td>
                                            <td><?php echo htmlspecialchars($payment['recipient']); ?></td>
                                            <td><?php echo t(htmlspecialchars($payment['status'])); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></td>
                                             <td class="action-links">
                                                  <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" style="display: inline;" onsubmit="return confirm('<?php echo t('confirm_delete'); ?>');">
                                                     <input type="hidden" name="action" value="delete_payment"> <!-- Reuse user's delete action -->
                                                     <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                     <button type="submit" class="danger" title="<?php echo t('delete'); ?>">🗑️</button>
                                                  </form>
                                             </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="8"><?php echo t('no_records_found'); ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                         <br>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="export_all_logs">
                             <button type="submit"><?php echo t('export_all_user_logs'); ?></button>
                        </form>

                     <?php endif; // End Admin specific actions ?>
                <?php endif; // End Admin Check ?>
            <?php endif; // End Logged In Check ?>
        </main>
         <footer>
            <?php echo APP_TITLE; ?> &copy; <?php echo date('Y'); ?>
        </footer>
    </div>


</body>
</html>
