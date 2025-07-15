<?php
session_start();
error_reporting(0);

define('DB_FILE', 'zakat_app.sqlite');
define('NISAB_GOLD_GRAMS_DEFAULT', 87.48);
define('NISAB_SILVER_GRAMS_DEFAULT', 612.36);
define('ZAKAT_RATE', 0.025);

function init_db() {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user', -- 'user', 'admin'
        email TEXT UNIQUE,
        full_name TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS zakat_calculations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        year INTEGER NOT NULL, -- Hijri Year ideally, or Gregorian for simplicity
        gold_grams REAL DEFAULT 0,
        silver_grams REAL DEFAULT 0,
        cash REAL DEFAULT 0,
        business_assets REAL DEFAULT 0,
        debts REAL DEFAULT 0,
        gold_price_per_gram REAL NOT NULL,
        silver_price_per_gram REAL NOT NULL,
        nisab_threshold_used REAL NOT NULL,
        total_assets REAL NOT NULL,
        zakat_due REAL NOT NULL,
        calculated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS zakat_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        calculation_id INTEGER NULL, -- Link to a specific calculation if needed
        year INTEGER NOT NULL,
        amount REAL NOT NULL,
        payment_date DATE NOT NULL,
        recipient TEXT,
        status TEXT DEFAULT 'Paid', -- e.g., Paid, Pending
        notes TEXT,
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (calculation_id) REFERENCES zakat_calculations(id) ON DELETE SET NULL
    )");

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password_hash, role, full_name, email) VALUES ('admin', '$admin_pass', 'admin', 'Admin User', 'admin@example.com')");
    }

    $defaults = [
        'nisab_gold_grams' => NISAB_GOLD_GRAMS_DEFAULT,
        'nisab_silver_grams' => NISAB_SILVER_GRAMS_DEFAULT,
        'current_gold_price_per_gram' => 60.0,
        'current_silver_price_per_gram' => 0.80,
        'default_language' => 'en'
    ];
    foreach ($defaults as $key => $value) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)");
        $stmt->execute([':key' => $key, ':value' => $value]);
    }
    return $db;
}

function get_setting($db, $key) {
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = :key");
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['value'] : null;
}

function update_setting($db, $key, $value) {
    $stmt = $db->prepare("UPDATE settings SET value = :value WHERE key = :key");
    return $stmt->execute([':key' => $key, ':value' => $value]);
}

$db = init_db();

$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : get_setting($db, 'default_language');
if (isset($_GET['lang']) && ($_GET['lang'] == 'en' || $_GET['lang'] == 'ur')) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
}

$translations = [
    'en' => [
        'title' => 'Personal Zakat Calculation & Payment Log',
        'toggle_lang' => 'اردو',
        'login' => 'Login',
        'register' => 'Register',
        'logout' => 'Logout',
        'username' => 'Username',
        'password' => 'Password',
        'email' => 'Email',
        'full_name' => 'Full Name',
        'confirm_password' => 'Confirm Password',
        'registration_success' => 'Registration successful. Please login.',
        'registration_failed' => 'Registration failed.',
        'passwords_do_not_match' => 'Passwords do not match.',
        'username_exists' => 'Username already exists.',
        'email_exists' => 'Email already exists.',
        'login_failed' => 'Invalid username or password.',
        'welcome' => 'Welcome',
        'dashboard' => 'Dashboard',
        'calculate_zakat' => 'Calculate Zakat',
        'payment_log' => 'Payment Log',
        'calculation_history' => 'Calculation History',
        'admin_panel' => 'Admin Panel',
        'user_management' => 'User Management',
        'all_logs' => 'View All Logs',
        'settings' => 'Settings',
        'public_info' => 'Public Information',
        'zakat_info_title' => 'About Zakat',
        'zakat_info_text' => 'Zakat is one of the Five Pillars of Islam. It is an obligatory charity for eligible Muslims who own wealth above a certain threshold (Nisab). Zakat is calculated annually at a rate of 2.5% on specific types of wealth that have been owned for a full lunar year.',
        'year' => 'Year (Hijri/Gregorian)',
        'gold_grams' => 'Gold (grams)',
        'silver_grams' => 'Silver (grams)',
        'cash' => 'Cash (on hand and in banks)',
        'business_assets' => 'Value of Business Goods/Inventory',
        'debts' => 'Liabilities/Debts to be Deducted',
        'current_gold_price' => 'Current Gold Price per Gram',
        'current_silver_price' => 'Current Silver Price per Gram',
        'calculate' => 'Calculate',
        'calculation_result' => 'Zakat Calculation Result',
        'total_zakatable_assets' => 'Total Zakatable Assets (Net)',
        'nisab_threshold' => 'Nisab Threshold Used',
        'zakat_due' => 'Zakat Due (2.5%)',
        'zakat_not_due' => 'Zakat is not due as assets are below the Nisab threshold.',
        'log_payment' => 'Log Zakat Payment',
        'amount' => 'Amount Paid',
        'payment_date' => 'Payment Date',
        'recipient' => 'Recipient/Organization',
        'status' => 'Status',
        'notes' => 'Notes (Optional)',
        'submit_payment' => 'Log Payment',
        'payment_logged_success' => 'Payment logged successfully.',
        'payment_logged_fail' => 'Failed to log payment.',
        'filter_by_year' => 'Filter by Year',
        'filter_by_status' => 'Filter by Status',
        'filter' => 'Filter',
        'clear_filter' => 'Clear Filter',
        'date' => 'Date',
        'calculated_on' => 'Calculated On',
        'action' => 'Action',
        'delete' => 'Delete',
        'view' => 'View',
        'edit' => 'Edit',
        'no_history' => 'No history found.',
        'no_payments' => 'No payments logged yet.',
        'role' => 'Role',
        'created_at' => 'Registered On',
        'manage_users' => 'Manage Users',
        'add_user' => 'Add User',
        'edit_user' => 'Edit User',
        'delete_user' => 'Delete User',
        'user_added_success' => 'User added successfully.',
        'user_updated_success' => 'User updated successfully.',
        'user_deleted_success' => 'User deleted successfully.',
        'user_action_failed' => 'User action failed.',
        'confirm_delete_user' => 'Are you sure you want to delete this user?',
        'view_calculations' => 'View Calculations',
        'view_payments' => 'View Payments',
        'export_csv' => 'Export CSV',
        'nisab_settings' => 'Nisab & Price Settings',
        'nisab_gold_grams' => 'Nisab: Gold (grams)',
        'nisab_silver_grams' => 'Nisab: Silver (grams)',
        'update_settings' => 'Update Settings',
        'settings_updated' => 'Settings updated successfully.',
        'error' => 'Error',
        'zakat_due_reminder' => 'Zakat Due Reminder',
        'reminder_text' => 'It has been approximately one lunar year since your last Zakat calculation or payment. Please consider calculating your Zakat for the current year.',
        'last_calculation_date' => 'Last Calculation Date',
        'last_payment_date' => 'Last Payment Date',
        'no_data_for_reminder' => 'Not enough data for Zakat reminder.',
         'paid' => 'Paid',
        'pending' => 'Pending',
        'user_id' => 'User ID',
        'select_user' => 'Select User',
        'all_users' => 'All Users',
         'home' => 'Home',
        'logged_in_as' => 'Logged in as',
         'save_changes' => 'Save Changes',
         'cancel' => 'Cancel'
    ],
    'ur' => [
        'title' => 'ذاتی زکوٰۃ کا حساب اور ادائیگی کا لاگ',
        'toggle_lang' => 'English',
        'login' => 'لاگ ان',
        'register' => 'رجسٹر کریں',
        'logout' => 'لاگ آؤٹ',
        'username' => 'صارف نام',
        'password' => 'پاس ورڈ',
        'email' => 'ای میل',
        'full_name' => 'پورا نام',
        'confirm_password' => 'پاس ورڈ کی تصدیق کریں',
        'registration_success' => 'رجسٹریشن کامیاب ہو گئی۔ براہ کرم لاگ ان کریں۔',
        'registration_failed' => 'رجسٹریشن ناکام ہو گئی۔',
        'passwords_do_not_match' => 'پاس ورڈ مماثل نہیں ہیں۔',
        'username_exists' => 'صارف نام پہلے سے موجود ہے۔',
        'email_exists' => 'ای میل پہلے سے موجود ہے۔',
        'login_failed' => 'غلط صارف نام یا پاس ورڈ۔',
        'welcome' => 'خوش آمدید',
        'dashboard' => 'ڈیش بورڈ',
        'calculate_zakat' => 'زکوٰۃ کا حساب لگائیں',
        'payment_log' => 'ادائیگی لاگ',
        'calculation_history' => 'حساب کتاب کی تاریخ',
        'admin_panel' => 'ایڈمن پینل',
        'user_management' => 'صارفین کا انتظام',
        'all_logs' => 'تمام لاگز دیکھیں',
        'settings' => 'ترتیبات',
        'public_info' => 'عوامی معلومات',
        'zakat_info_title' => 'زکوٰۃ کے بارے میں',
        'zakat_info_text' => 'زکوٰۃ اسلام کے پانچ ستونوں میں سے ایک ہے۔ یہ ان اہل مسلمانوں پر فرض صدقہ ہے جو ایک مخصوص حد (نصاب) سے زیادہ مال کے مالک ہوں۔ زکوٰۃ کا حساب سالانہ 2.5% کی شرح سے مخصوص قسم کی دولت پر کیا جاتا ہے جو پورے قمری سال تک ملکیت میں رہی ہو۔',
        'year' => 'سال (ھجری/عیسوی)',
        'gold_grams' => 'سونا (گرام)',
        'silver_grams' => 'چاندی (گرام)',
        'cash' => 'نقدی (ہاتھ میں اور بینک میں)',
        'business_assets' => 'تجارتی سامان/انوینٹری کی قیمت',
        'debts' => 'واجبات/قرض جو منہا کیے جائیں گے',
        'current_gold_price' => 'فی گرام سونے کی موجودہ قیمت',
        'current_silver_price' => 'فی گرام چاندی کی موجودہ قیمت',
        'calculate' => 'حساب لگائیں',
        'calculation_result' => 'زکوٰۃ کے حساب کا نتیجہ',
        'total_zakatable_assets' => 'کل قابلِ زکوٰۃ اثاثے (خالص)',
        'nisab_threshold' => 'استعمال شدہ نصاب کی حد',
        'zakat_due' => 'واجب الادا زکوٰۃ (2.5%)',
        'zakat_not_due' => 'زکوٰۃ واجب نہیں ہے کیونکہ اثاثے نصاب کی حد سے کم ہیں۔',
        'log_payment' => 'زکوٰۃ کی ادائیگی لاگ کریں',
        'amount' => 'ادا شدہ رقم',
        'payment_date' => 'ادائیگی کی تاریخ',
        'recipient' => 'وصول کنندہ/ادارہ',
        'status' => 'حیثیت',
        'notes' => 'نوٹس (اختیاری)',
        'submit_payment' => 'ادائیگی لاگ کریں',
        'payment_logged_success' => 'ادائیگی کامیابی سے لاگ ہو گئی۔',
        'payment_logged_fail' => 'ادائیگی لاگ کرنے میں ناکامی ہوئی۔',
        'filter_by_year' => 'سال کے لحاظ سے فلٹر کریں',
        'filter_by_status' => 'حیثیت کے لحاظ سے فلٹر کریں',
        'filter' => 'فلٹر',
        'clear_filter' => 'فلٹر صاف کریں',
        'date' => 'تاریخ',
        'calculated_on' => 'حساب لگایا گیا',
        'action' => 'عمل',
        'delete' => 'حذف کریں',
        'view' => 'دیکھیں',
        'edit' => 'ترمیم',
        'no_history' => 'کوئی تاریخ نہیں ملی۔',
        'no_payments' => 'ابھی تک کوئی ادائیگی لاگ نہیں ہوئی۔',
        'role' => 'کردار',
        'created_at' => 'رجسٹریشن کی تاریخ',
        'manage_users' => 'صارفین کا انتظام',
        'add_user' => 'صارف شامل کریں',
        'edit_user' => 'صارف میں ترمیم کریں',
        'delete_user' => 'صارف حذف کریں',
        'user_added_success' => 'صارف کامیابی سے شامل ہو گیا۔',
        'user_updated_success' => 'صارف کامیابی سے اپ ڈیٹ ہو گیا۔',
        'user_deleted_success' => 'صارف کامیابی سے حذف ہو گیا۔',
        'user_action_failed' => 'صارف کا عمل ناکام ہو گیا۔',
        'confirm_delete_user' => 'کیا آپ واقعی اس صارف کو حذف کرنا چاہتے ہیں؟',
        'view_calculations' => 'حساب کتاب دیکھیں',
        'view_payments' => 'ادائیگیاں دیکھیں',
        'export_csv' => 'CSV برآمد کریں',
        'nisab_settings' => 'نصاب اور قیمت کی ترتیبات',
        'nisab_gold_grams' => 'نصاب: سونا (گرام)',
        'nisab_silver_grams' => 'نصاب: چاندی (گرام)',
        'update_settings' => 'ترتیبات اپ ڈیٹ کریں',
        'settings_updated' => 'ترتیبات کامیابی سے اپ ڈیٹ ہو گئیں۔',
        'error' => 'خرابی',
        'zakat_due_reminder' => 'زکوٰۃ کی یاد دہانی',
        'reminder_text' => 'آپ کے پچھلے زکوٰۃ کے حساب یا ادائیگی کو تقریباً ایک قمری سال ہو گیا ہے۔ براہ کرم موجودہ سال کے لیے اپنی زکوٰۃ کا حساب لگانے پر غور کریں۔',
        'last_calculation_date' => 'آخری حساب کتاب کی تاریخ',
        'last_payment_date' => 'آخری ادائیگی کی تاریخ',
        'no_data_for_reminder' => 'زکوٰۃ کی یاد دہانی کے لیے کافی ڈیٹا نہیں ہے۔',
        'paid' => 'ادا شدہ',
        'pending' => 'زیر التواء',
        'user_id' => 'صارف آئی ڈی',
        'select_user' => 'صارف منتخب کریں',
        'all_users' => 'تمام صارفین',
        'home' => 'گھر',
        'logged_in_as' => 'لاگ ان ہیں بطور',
        'save_changes' => 'تبدیلیاں محفوظ کریں',
        'cancel' => 'منسوخ کریں'
    ]
];

function t($key) {
    global $lang, $translations;
    return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function calculate_zakat_logic($db, $gold_grams, $silver_grams, $cash, $business_assets, $debts) {
    $gold_grams = floatval($gold_grams);
    $silver_grams = floatval($silver_grams);
    $cash = floatval($cash);
    $business_assets = floatval($business_assets);
    $debts = floatval($debts);

    $nisab_gold_grams = floatval(get_setting($db, 'nisab_gold_grams'));
    $nisab_silver_grams = floatval(get_setting($db, 'nisab_silver_grams'));
    $gold_price_per_gram = floatval(get_setting($db, 'current_gold_price_per_gram'));
    $silver_price_per_gram = floatval(get_setting($db, 'current_silver_price_per_gram'));

    $total_gold_value = $gold_grams * $gold_price_per_gram;
    $total_silver_value = $silver_grams * $silver_price_per_gram;

    $total_assets_before_debt = $total_gold_value + $total_silver_value + $cash + $business_assets;
    $net_zakatable_assets = max(0, $total_assets_before_debt - $debts);

    $nisab_threshold_used = 0;
    $zakat_due = 0;
    $is_zakat_due = false;

    $nisab_silver_value = $nisab_silver_grams * $silver_price_per_gram;
    $nisab_gold_value = $nisab_gold_grams * $gold_price_per_gram;

    if ($silver_grams == 0 && $cash == 0 && $business_assets == 0 && $gold_grams >= $nisab_gold_grams) {
        // Only gold scenario
         $nisab_threshold_used = $nisab_gold_value; // Use gold value for comparison only if above gold gram threshold
         if ($net_zakatable_assets >= $nisab_gold_value) { // compare value against gold nisab value
            $is_zakat_due = true;
         }

    } else {
        // Mixed assets or only silver/cash/business scenario
        $nisab_threshold_used = $nisab_silver_value;
        if ($net_zakatable_assets >= $nisab_threshold_used) {
            $is_zakat_due = true;
        }
    }

    if ($is_zakat_due) {
        $zakat_due = $net_zakatable_assets * ZAKAT_RATE;
    }

    return [
        'total_assets' => $net_zakatable_assets,
        'nisab_threshold_used' => $nisab_threshold_used,
        'zakat_due' => $zakat_due,
        'gold_price_per_gram' => $gold_price_per_gram,
        'silver_price_per_gram' => $silver_price_per_gram
    ];
}

function handle_post_requests($db) {
    if (!isset($_POST['action']) || !verify_csrf_token($_POST['csrf_token'])) {
         if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['csrf_token'])) {
             return ['error' => 'CSRF token missing or invalid.'];
         }
         if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && !verify_csrf_token($_POST['csrf_token'])) {
            return ['error' => 'CSRF token mismatch. Please try again.'];
         }
        return null;
    }

    $action = $_POST['action'];
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    $message = [];

    try {
        switch ($action) {
            case 'register':
                $username = trim($_POST['username']);
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                $full_name = trim($_POST['full_name']);

                if ($password !== $confirm_password) {
                    return ['error' => t('passwords_do_not_match')];
                }
                if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
                     return ['error' => 'All fields are required.'];
                }

                $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
                $stmt->execute([':username' => $username]);
                if ($stmt->fetch()) return ['error' => t('username_exists')];

                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                if ($stmt->fetch()) return ['error' => t('email_exists')];

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, full_name, role) VALUES (:username, :password_hash, :email, :full_name, 'user')");
                if ($stmt->execute([':username' => $username, ':password_hash' => $password_hash, ':email' => $email, ':full_name' => $full_name])) {
                    return ['success' => t('registration_success')];
                } else {
                    return ['error' => t('registration_failed')];
                }
                break;

            case 'login':
                $username = trim($_POST['username']);
                $password = $_POST['password'];

                $stmt = $db->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE username = :username");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    session_regenerate_id(true);
                    header('Location: ?page=dashboard');
                    exit;
                } else {
                    return ['error' => t('login_failed')];
                }
                break;

            case 'calculate_zakat':
                 if (!$user_id) return ['error' => 'Login required.'];
                 $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT) ?: date('Y'); // Default to current Gregorian year if not provided
                 $gold_grams = filter_input(INPUT_POST, 'gold_grams', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0;
                 $silver_grams = filter_input(INPUT_POST, 'silver_grams', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0;
                 $cash = filter_input(INPUT_POST, 'cash', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0;
                 $business_assets = filter_input(INPUT_POST, 'business_assets', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0;
                 $debts = filter_input(INPUT_POST, 'debts', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) ?? 0;

                $result = calculate_zakat_logic($db, $gold_grams, $silver_grams, $cash, $business_assets, $debts);

                $stmt = $db->prepare("INSERT INTO zakat_calculations
                    (user_id, year, gold_grams, silver_grams, cash, business_assets, debts, gold_price_per_gram, silver_price_per_gram, nisab_threshold_used, total_assets, zakat_due)
                    VALUES (:user_id, :year, :gold_grams, :silver_grams, :cash, :business_assets, :debts, :gold_price, :silver_price, :nisab, :total_assets, :zakat_due)");

                $stmt->execute([
                    ':user_id' => $user_id,
                    ':year' => $year,
                    ':gold_grams' => $gold_grams,
                    ':silver_grams' => $silver_grams,
                    ':cash' => $cash,
                    ':business_assets' => $business_assets,
                    ':debts' => $debts,
                    ':gold_price' => $result['gold_price_per_gram'],
                    ':silver_price' => $result['silver_price_per_gram'],
                    ':nisab' => $result['nisab_threshold_used'],
                    ':total_assets' => $result['total_assets'],
                    ':zakat_due' => $result['zakat_due']
                ]);
                $_SESSION['last_calculation_result'] = $result;
                 header('Location: ?page=calculate&success=1'); // Redirect to avoid form resubmission
                 exit;
                break;

            case 'log_payment':
                 if (!$user_id) return ['error' => 'Login required.'];
                 $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT) ?: date('Y');
                 $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
                 $payment_date = trim($_POST['payment_date']); // Validate date format 'YYYY-MM-DD'
                 $recipient = htmlspecialchars(trim($_POST['recipient']));
                 $status = htmlspecialchars(trim($_POST['status']));
                 $notes = htmlspecialchars(trim($_POST['notes']));

                 if (empty($amount) || empty($payment_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
                     return ['error' => 'Invalid input for payment logging. Amount and valid Date (YYYY-MM-DD) are required.'];
                 }

                 $stmt = $db->prepare("INSERT INTO zakat_payments (user_id, year, amount, payment_date, recipient, status, notes)
                                     VALUES (:user_id, :year, :amount, :payment_date, :recipient, :status, :notes)");
                 if ($stmt->execute([
                     ':user_id' => $user_id,
                     ':year' => $year,
                     ':amount' => $amount,
                     ':payment_date' => $payment_date,
                     ':recipient' => $recipient,
                     ':status' => $status,
                     ':notes' => $notes
                 ])) {
                     return ['success' => t('payment_logged_success')];
                 } else {
                     return ['error' => t('payment_logged_fail')];
                 }
                break;

             case 'delete_calculation':
                 $calc_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                 if (!$calc_id) return ['error' => 'Invalid ID.'];
                 $stmt = $db->prepare("SELECT user_id FROM zakat_calculations WHERE id = :id");
                 $stmt->execute([':id' => $calc_id]);
                 $calc_user_id = $stmt->fetchColumn();

                 if (($user_role === 'admin') || ($user_id && $calc_user_id == $user_id)) {
                     $stmt = $db->prepare("DELETE FROM zakat_calculations WHERE id = :id");
                     if ($stmt->execute([':id' => $calc_id])) {
                          return ['success' => 'Calculation deleted.'];
                     } else {
                          return ['error' => 'Failed to delete calculation.'];
                     }
                 } else {
                    return ['error' => 'Permission denied.'];
                 }
                 break;

            case 'delete_payment':
                $payment_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if (!$payment_id) return ['error' => 'Invalid ID.'];
                $stmt = $db->prepare("SELECT user_id FROM zakat_payments WHERE id = :id");
                $stmt->execute([':id' => $payment_id]);
                $payment_user_id = $stmt->fetchColumn();

                if (($user_role === 'admin') || ($user_id && $payment_user_id == $user_id)) {
                    $stmt = $db->prepare("DELETE FROM zakat_payments WHERE id = :id");
                     if ($stmt->execute([':id' => $payment_id])) {
                          return ['success' => 'Payment deleted.'];
                     } else {
                          return ['error' => 'Failed to delete payment.'];
                     }
                } else {
                     return ['error' => 'Permission denied.'];
                }
                break;

            case 'admin_update_settings':
                if ($user_role !== 'admin') return ['error' => 'Admin access required.'];
                 $nisab_gold = filter_input(INPUT_POST, 'nisab_gold_grams', FILTER_VALIDATE_FLOAT);
                 $nisab_silver = filter_input(INPUT_POST, 'nisab_silver_grams', FILTER_VALIDATE_FLOAT);
                 $price_gold = filter_input(INPUT_POST, 'current_gold_price_per_gram', FILTER_VALIDATE_FLOAT);
                 $price_silver = filter_input(INPUT_POST, 'current_silver_price_per_gram', FILTER_VALIDATE_FLOAT);

                 if ($nisab_gold !== false) update_setting($db, 'nisab_gold_grams', $nisab_gold);
                 if ($nisab_silver !== false) update_setting($db, 'nisab_silver_grams', $nisab_silver);
                 if ($price_gold !== false) update_setting($db, 'current_gold_price_per_gram', $price_gold);
                 if ($price_silver !== false) update_setting($db, 'current_silver_price_per_gram', $price_silver);

                 return ['success' => t('settings_updated')];
                break;

            case 'admin_add_user':
                 if ($user_role !== 'admin') return ['error' => 'Admin access required.'];
                 $username = trim($_POST['username']);
                 $password = $_POST['password'];
                 $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                 $full_name = trim($_POST['full_name']);
                 $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';

                 if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
                      return ['error' => 'All fields required.'];
                 }
                 $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
                 $stmt->execute([':username' => $username, ':email' => $email]);
                 if ($stmt->fetch()) return ['error' => t('username_exists').' or '.t('email_exists')];

                 $password_hash = password_hash($password, PASSWORD_DEFAULT);
                 $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, full_name, role) VALUES (:username, :password_hash, :email, :full_name, :role)");
                 if ($stmt->execute([':username' => $username, ':password_hash' => $password_hash, ':email' => $email, ':full_name' => $full_name, ':role' => $role])) {
                    return ['success' => t('user_added_success')];
                 } else {
                    return ['error' => t('user_action_failed')];
                 }
                 break;

            case 'admin_edit_user':
                 if ($user_role !== 'admin') return ['error' => 'Admin access required.'];
                 $edit_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                 $username = trim($_POST['username']);
                 $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                 $full_name = trim($_POST['full_name']);
                 $role = ($_POST['role'] === 'admin') ? 'admin' : 'user';
                 $password = $_POST['password']; // Optional: only update if provided

                 if (!$edit_user_id || empty($username) || empty($email) || empty($full_name)) {
                      return ['error' => 'Invalid data.'];
                 }

                 // Check for conflicts excluding the current user being edited
                 $stmt = $db->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
                 $stmt->execute([':username' => $username, ':email' => $email, ':id' => $edit_user_id]);
                 if ($stmt->fetch()) return ['error' => t('username_exists').' or '.t('email_exists')];


                 if (!empty($password)) {
                     $password_hash = password_hash($password, PASSWORD_DEFAULT);
                     $stmt = $db->prepare("UPDATE users SET username = :username, email = :email, full_name = :full_name, role = :role, password_hash = :password_hash WHERE id = :id");
                     $params = [':username' => $username, ':email' => $email, ':full_name' => $full_name, ':role' => $role, ':password_hash' => $password_hash, ':id' => $edit_user_id];
                 } else {
                     $stmt = $db->prepare("UPDATE users SET username = :username, email = :email, full_name = :full_name, role = :role WHERE id = :id");
                     $params = [':username' => $username, ':email' => $email, ':full_name' => $full_name, ':role' => $role, ':id' => $edit_user_id];
                 }

                 if ($stmt->execute($params)) {
                     // Prevent admin from demoting the last admin account
                     if($role == 'user' && $edit_user_id == $user_id) {
                         $stmt_admin_count = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                         if($stmt_admin_count->fetchColumn() == 0) {
                              // Revert role change
                             $stmt_revert = $db->prepare("UPDATE users SET role = 'admin' WHERE id = :id");
                             $stmt_revert->execute([':id' => $edit_user_id]);
                             return ['error' => 'Cannot remove the last admin account.'];
                         }
                     }
                     // Update session if admin edits their own profile
                     if ($edit_user_id == $user_id) {
                         $_SESSION['username'] = $username;
                         $_SESSION['user_role'] = $role;
                         $_SESSION['full_name'] = $full_name;
                     }
                     return ['success' => t('user_updated_success')];
                 } else {
                     return ['error' => t('user_action_failed')];
                 }
                 break;

            case 'admin_delete_user':
                if ($user_role !== 'admin') return ['error' => 'Admin access required.'];
                $delete_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

                if (!$delete_user_id) return ['error' => 'Invalid User ID.'];
                if ($delete_user_id == $user_id) return ['error' => 'Cannot delete your own account.'];

                // Prevent deleting the last admin
                 $stmt = $db->prepare("SELECT role FROM users WHERE id = :id");
                 $stmt->execute([':id' => $delete_user_id]);
                 $user_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);

                 if($user_to_delete && $user_to_delete['role'] === 'admin') {
                     $stmt_admin_count = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                     if($stmt_admin_count->fetchColumn() <= 1) {
                          return ['error' => 'Cannot delete the last admin account.'];
                     }
                 }


                $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                if ($stmt->execute([':id' => $delete_user_id])) {
                     // Optionally delete associated data or rely on cascade if set up properly
                     // $db->prepare("DELETE FROM zakat_calculations WHERE user_id = :id")->execute([':id' => $delete_user_id]);
                     // $db->prepare("DELETE FROM zakat_payments WHERE user_id = :id")->execute([':id' => $delete_user_id]);
                    return ['success' => t('user_deleted_success')];
                } else {
                    return ['error' => t('user_action_failed')];
                }
                break;

             case 'export_csv':
                 if ($user_role !== 'admin') return ['error' => 'Admin access required.'];
                 $log_type = $_POST['log_type']; // 'calculations' or 'payments'
                 $filter_user_id = filter_input(INPUT_POST, 'filter_user_id', FILTER_VALIDATE_INT);
                 $filter_year = filter_input(INPUT_POST, 'filter_year', FILTER_VALIDATE_INT);

                 if ($log_type === 'calculations') {
                     $sql = "SELECT c.*, u.username FROM zakat_calculations c JOIN users u ON c.user_id = u.id WHERE 1=1";
                     $params = [];
                     if ($filter_user_id) { $sql .= " AND c.user_id = :user_id"; $params[':user_id'] = $filter_user_id; }
                     if ($filter_year) { $sql .= " AND c.year = :year"; $params[':year'] = $filter_year; }
                     $sql .= " ORDER BY c.calculated_at DESC";
                     $stmt = $db->prepare($sql);
                     $stmt->execute($params);
                     $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                     header('Content-Type: text/csv');
                     header('Content-Disposition: attachment; filename="zakat_calculations_export_'.date('Ymd').'.csv"');
                     $output = fopen('php://output', 'w');
                     fputcsv($output, array_keys($data[0] ?? []));
                     foreach ($data as $row) { fputcsv($output, $row); }
                     fclose($output);
                     exit;
                 } elseif ($log_type === 'payments') {
                     $sql = "SELECT p.*, u.username FROM zakat_payments p JOIN users u ON p.user_id = u.id WHERE 1=1";
                      $params = [];
                     if ($filter_user_id) { $sql .= " AND p.user_id = :user_id"; $params[':user_id'] = $filter_user_id; }
                     if ($filter_year) { $sql .= " AND p.year = :year"; $params[':year'] = $filter_year; }
                     $sql .= " ORDER BY p.payment_date DESC";
                     $stmt = $db->prepare($sql);
                     $stmt->execute($params);
                     $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                     header('Content-Type: text/csv');
                     header('Content-Disposition: attachment; filename="zakat_payments_export_'.date('Ymd').'.csv"');
                     $output = fopen('php://output', 'w');
                     fputcsv($output, array_keys($data[0] ?? []));
                     foreach ($data as $row) { fputcsv($output, $row); }
                     fclose($output);
                     exit;
                 }
                 break;
        }
    } catch (PDOException $e) {
        $message = ['error' => 'Database error: ' . $e->getMessage()];
    } catch (Exception $e) {
        $message = ['error' => 'An error occurred: ' . $e->getMessage()];
    }
    return $message;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = handle_post_requests($db);
} elseif (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header('Location: ?page=login');
    exit;
}


$page = isset($_GET['page']) ? $_GET['page'] : (isset($_SESSION['user_id']) ? 'dashboard' : 'public');
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
$csrf_token = generate_csrf_token();

// Reminder Logic
$show_reminder = false;
if ($user_id && ($page == 'dashboard' || $page == 'calculate_zakat')) {
    $stmt_last_calc = $db->prepare("SELECT MAX(calculated_at) FROM zakat_calculations WHERE user_id = :user_id");
    $stmt_last_calc->execute([':user_id' => $user_id]);
    $last_calc_date = $stmt_last_calc->fetchColumn();

    $stmt_last_pay = $db->prepare("SELECT MAX(payment_date) FROM zakat_payments WHERE user_id = :user_id");
    $stmt_last_pay->execute([':user_id' => $user_id]);
    $last_pay_date = $stmt_last_pay->fetchColumn();

    $last_activity_date_str = $last_calc_date ?: $last_pay_date; // Prioritize calculation date if available

    if ($last_activity_date_str) {
        try {
            $last_activity_timestamp = strtotime($last_activity_date_str);
            $lunar_year_seconds = 354.37 * 24 * 60 * 60; // Approximate lunar year in seconds
            if (time() - $last_activity_timestamp > $lunar_year_seconds) {
                $show_reminder = true;
                $reminder_last_calc_date = $last_calc_date ? date('Y-m-d', strtotime($last_calc_date)) : 'N/A';
                $reminder_last_pay_date = $last_pay_date ? date('Y-m-d', strtotime($last_pay_date)) : 'N/A';
            }
        } catch (Exception $e) {
           // Ignore date parsing errors
        }
    }
}


?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $lang == 'ur' ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('title') ?></title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
        .container { max-width: 1100px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        header { background-color: #006400; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0; }
        header h1 { margin: 0; font-size: 1.5em; }
        header nav ul { list-style: none; padding: 0; margin: 0; display: flex; }
        header nav ul li { margin-left: 15px; }
        header nav ul li a { color: #fff; text-decoration: none; padding: 5px 10px; border-radius: 4px; transition: background-color 0.3s; }
        header nav ul li a:hover, header nav ul li a.active { background-color: #004d00; }
        .lang-toggle a { background-color: #eee; color: #333; font-weight: bold; }
        .lang-toggle a:hover { background-color: #ddd; }
        main { padding: 20px 0; }
        h2, h3 { color: #006400; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="email"], input[type="number"], input[type="date"], textarea, select {
            width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        input[type="number"] { appearance: textfield; -moz-appearance: textfield; } /* Remove number spinners */
        textarea { resize: vertical; min-height: 80px; }
        button, input[type="submit"] {
            background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; transition: background-color 0.3s;
        }
        button:hover, input[type="submit"]:hover { background-color: #0056b3; }
        button.secondary { background-color: #6c757d; }
        button.secondary:hover { background-color: #5a6268; }
         button.danger { background-color: #dc3545; }
        button.danger:hover { background-color: #c82333; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .message.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .message.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .message.info { background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .form-group { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .calculation-result { background-color: #e9f7ef; padding: 15px; border-radius: 5px; border: 1px solid #c8e6c9; margin-top: 20px; }
        .calculation-result p { margin: 5px 0; }
        .reminder { background-color: #fff3cd; border-color: #ffeeba; color: #856404; padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .filter-form { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; align-items: flex-end; }
        .filter-form label, .filter-form select, .filter-form input, .filter-form button { margin-bottom: 0; }
        .filter-form input[type="number"], .filter-form select { max-width: 150px; }
        footer { text-align: center; margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; color: #666; font-size: 0.9em; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 5px; }
        .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-button:hover, .close-button:focus { color: black; text-decoration: none; }

         @media (max-width: 768px) {
            header { flex-direction: column; align-items: flex-start; }
            header nav ul { flex-direction: column; width: 100%; margin-top: 10px; }
            header nav ul li { margin-left: 0; margin-bottom: 5px; }
             header nav ul li a { display: block; text-align: center; }
            .container { padding: 10px; margin: 10px; }
            .filter-form { flex-direction: column; align-items: stretch; }
             .filter-form input[type="number"], .filter-form select { max-width: none; }
            .modal-content { width: 95%; margin: 10% auto; }
         }
        <?php if ($lang == 'ur'): ?>
        body { font-family: 'Noto Nastaliq Urdu', sans-serif; direction: rtl; }
        header nav ul li { margin-left: 0; margin-right: 15px; }
        th, td { text-align: right; }
        label { display: block; text-align: right; }
        input, textarea, select { text-align: right; }
        .close-button { float: left; }
         @media (max-width: 768px) {
            header nav ul li { margin-right: 0; }
         }
        <?php endif; ?>
    </style>
    <?php if ($lang == 'ur'): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400..700&display=swap" rel="stylesheet">
    <?php endif; ?>
</head>
<body>

<div class="container">
    <header>
        <h1><?= t('title') ?></h1>
        <nav>
            <ul>
                <?php if ($user_id): ?>
                    <li><a href="?page=dashboard" class="<?= $page == 'dashboard' ? 'active' : '' ?>"><?= t('dashboard') ?></a></li>
                    <li><a href="?page=calculate" class="<?= $page == 'calculate' ? 'active' : '' ?>"><?= t('calculate_zakat') ?></a></li>
                    <li><a href="?page=payment_log" class="<?= $page == 'payment_log' ? 'active' : '' ?>"><?= t('payment_log') ?></a></li>
                    <li><a href="?page=history" class="<?= $page == 'history' ? 'active' : '' ?>"><?= t('calculation_history') ?></a></li>
                    <li><a href="?page=payment_history" class="<?= $page == 'payment_history' ? 'active' : '' ?>"><?= t('payment_log') ?></a></li>
                    <?php if ($user_role === 'admin'): ?>
                        <li><a href="?page=admin" class="<?= $page == 'admin' ? 'active' : '' ?>"><?= t('admin_panel') ?></a></li>
                    <?php endif; ?>
                     <li><a href="?action=logout"><?= t('logout') ?> (<?= htmlspecialchars($username) ?>)</a></li>
                <?php else: ?>
                    <li><a href="?page=public" class="<?= $page == 'public' ? 'active' : '' ?>"><?= t('public_info') ?></a></li>
                    <li><a href="?page=login" class="<?= $page == 'login' ? 'active' : '' ?>"><?= t('login') ?></a></li>
                    <li><a href="?page=register" class="<?= $page == 'register' ? 'active' : '' ?>"><?= t('register') ?></a></li>
                <?php endif; ?>
                <li class="lang-toggle"><a href="?lang=<?= $lang == 'en' ? 'ur' : 'en' ?>&page=<?= $page ?>"><?= t('toggle_lang') ?></a></li>
            </ul>
        </nav>
    </header>

    <main>
        <?php if (isset($message['success'])): ?>
            <div class="message success"><?= htmlspecialchars($message['success']) ?></div>
        <?php endif; ?>
        <?php if (isset($message['error'])): ?>
            <div class="message error"><?= htmlspecialchars($message['error']) ?></div>
        <?php endif; ?>

        <?php if ($show_reminder): ?>
        <div class="reminder">
            <h4><?= t('zakat_due_reminder') ?></h4>
            <p><?= t('reminder_text') ?></p>
            <p><?= t('last_calculation_date') ?>: <?= $reminder_last_calc_date ?></p>
            <p><?= t('last_payment_date') ?>: <?= $reminder_last_pay_date ?></p>
        </div>
        <?php endif; ?>

        <?php
        switch ($page) {
            case 'login':
                ?>
                <h2><?= t('login') ?></h2>
                <form action="?page=login" method="post">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="username"><?= t('username') ?></label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><?= t('password') ?></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit"><?= t('login') ?></button>
                </form>
                <p><?= t('register') ?>? <a href="?page=register"><?= t('register') ?></a></p>
                <?php
                break;

            case 'register':
                ?>
                <h2><?= t('register') ?></h2>
                <form action="?page=register" method="post">
                     <input type="hidden" name="action" value="register">
                     <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                     <div class="form-group">
                        <label for="username"><?= t('username') ?></label>
                        <input type="text" id="username" name="username" required>
                    </div>
                     <div class="form-group">
                        <label for="full_name"><?= t('full_name') ?></label>
                        <input type="text" id="full_name" name="full_name" required>
                    </div>
                     <div class="form-group">
                        <label for="email"><?= t('email') ?></label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><?= t('password') ?></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><?= t('confirm_password') ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit"><?= t('register') ?></button>
                </form>
                 <p><?= t('login') ?>? <a href="?page=login"><?= t('login') ?></a></p>
                <?php
                break;

             case 'dashboard':
                if (!$user_id) { header('Location: ?page=login'); exit; }
                ?>
                <h2><?= t('dashboard') ?></h2>
                <p><?= t('welcome') ?>, <?= htmlspecialchars($full_name) ?>!</p>
                <ul>
                    <li><a href="?page=calculate"><?= t('calculate_zakat') ?></a></li>
                    <li><a href="?page=payment_log"><?= t('log_payment') ?></a></li>
                    <li><a href="?page=history"><?= t('calculation_history') ?></a></li>
                    <li><a href="?page=payment_history"><?= t('payment_log') ?></a></li>
                </ul>

                 <?php
                 // Display last calculation result if redirected here
                 if (isset($_SESSION['last_calculation_result'])) {
                     $result = $_SESSION['last_calculation_result'];
                     unset($_SESSION['last_calculation_result']); // Clear after displaying
                      echo '<h3>' . t('calculation_result') . '</h3>';
                      echo '<div class="calculation-result">';
                      echo '<p><strong>' . t('total_zakatable_assets') . ':</strong> ' . number_format($result['total_assets'], 2) . '</p>';
                      echo '<p><strong>' . t('nisab_threshold') . ':</strong> ' . number_format($result['nisab_threshold_used'], 2) . '</p>';
                     if ($result['zakat_due'] > 0) {
                         echo '<p><strong>' . t('zakat_due') . ':</strong> ' . number_format($result['zakat_due'], 2) . '</p>';
                     } else {
                         echo '<p>' . t('zakat_not_due') . '</p>';
                     }
                     echo '</div>';
                 }
                 ?>
                <?php
                break;

            case 'calculate':
                 if (!$user_id) { header('Location: ?page=login'); exit; }
                 $current_gold_price = get_setting($db, 'current_gold_price_per_gram');
                 $current_silver_price = get_setting($db, 'current_silver_price_per_gram');
                ?>
                <h2><?= t('calculate_zakat') ?></h2>
                 <?php if (isset($_GET['success'])): ?>
                      <div class="message success">Calculation saved! See result below or on the dashboard.</div>
                       <?php
                        // Re-fetch last calculation for display
                        $stmt = $db->prepare("SELECT * FROM zakat_calculations WHERE user_id = :user_id ORDER BY calculated_at DESC LIMIT 1");
                        $stmt->execute([':user_id' => $user_id]);
                        $last_calc = $stmt->fetch(PDO::FETCH_ASSOC);
                        if($last_calc) {
                             echo '<h3>' . t('calculation_result') . '</h3>';
                             echo '<div class="calculation-result">';
                             echo '<p><strong>' . t('year') . ':</strong> ' . htmlspecialchars($last_calc['year']) . '</p>';
                             echo '<p><strong>' . t('total_zakatable_assets') . ':</strong> ' . number_format($last_calc['total_assets'], 2) . '</p>';
                             echo '<p><strong>' . t('nisab_threshold') . ':</strong> ' . number_format($last_calc['nisab_threshold_used'], 2) . '</p>';
                             if ($last_calc['zakat_due'] > 0) {
                                echo '<p><strong>' . t('zakat_due') . ':</strong> ' . number_format($last_calc['zakat_due'], 2) . '</p>';
                             } else {
                                echo '<p>' . t('zakat_not_due') . '</p>';
                             }
                             echo '</div>';
                        }
                       ?>
                 <?php endif; ?>
                <form action="?page=calculate" method="post">
                    <input type="hidden" name="action" value="calculate_zakat">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="year"><?= t('year') ?></label>
                        <input type="number" id="year" name="year" value="<?= date('Y') ?>" required>
                    </div>
                     <div class="form-group">
                        <label for="gold_grams"><?= t('gold_grams') ?></label>
                        <input type="number" step="0.01" id="gold_grams" name="gold_grams" value="0" required>
                    </div>
                     <div class="form-group">
                        <label for="silver_grams"><?= t('silver_grams') ?></label>
                        <input type="number" step="0.01" id="silver_grams" name="silver_grams" value="0" required>
                    </div>
                     <div class="form-group">
                        <label for="cash"><?= t('cash') ?></label>
                        <input type="number" step="0.01" id="cash" name="cash" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="business_assets"><?= t('business_assets') ?></label>
                        <input type="number" step="0.01" id="business_assets" name="business_assets" value="0" required>
                    </div>
                     <div class="form-group">
                        <label for="debts"><?= t('debts') ?></label>
                        <input type="number" step="0.01" id="debts" name="debts" value="0" required>
                    </div>
                     <p><?= t('current_gold_price') ?>: <?= htmlspecialchars($current_gold_price) ?> | <?= t('current_silver_price') ?>: <?= htmlspecialchars($current_silver_price) ?>
                       <?php if ($user_role === 'admin') echo ' (<a href="?page=admin&section=settings">'.t('edit').'</a>)'; ?>
                    </p>

                    <button type="submit"><?= t('calculate') ?></button>
                </form>
                 <?php
                 break;

            case 'payment_log':
                 if (!$user_id) { header('Location: ?page=login'); exit; }
                 ?>
                <h2><?= t('log_payment') ?></h2>
                 <form action="?page=payment_log" method="post">
                    <input type="hidden" name="action" value="log_payment">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div class="form-group">
                        <label for="year"><?= t('year') ?></label>
                        <input type="number" id="year" name="year" value="<?= date('Y') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="amount"><?= t('amount') ?></label>
                        <input type="number" step="0.01" id="amount" name="amount" required>
                    </div>
                    <div class="form-group">
                        <label for="payment_date"><?= t('payment_date') ?></label>
                        <input type="date" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="recipient"><?= t('recipient') ?></label>
                        <input type="text" id="recipient" name="recipient">
                    </div>
                    <div class="form-group">
                        <label for="status"><?= t('status') ?></label>
                        <select id="status" name="status">
                            <option value="Paid" selected><?= t('paid') ?></option>
                            <option value="Pending"><?= t('pending') ?></option>
                        </select>
                    </div>
                     <div class="form-group">
                        <label for="notes"><?= t('notes') ?></label>
                        <textarea id="notes" name="notes"></textarea>
                    </div>
                    <button type="submit"><?= t('submit_payment') ?></button>
                </form>

                 <h2><?= t('payment_log') ?></h2>
                 <?php // Also show payment history here
                 $filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);
                 $filter_status = isset($_GET['filter_status']) ? htmlspecialchars($_GET['filter_status']) : '';

                 $sql = "SELECT * FROM zakat_payments WHERE user_id = :user_id ";
                 $params = [':user_id' => $user_id];
                 if ($filter_year) { $sql .= " AND year = :year "; $params[':year'] = $filter_year; }
                 if ($filter_status) { $sql .= " AND status = :status "; $params[':status'] = $filter_status; }
                 $sql .= " ORDER BY payment_date DESC";

                 $stmt = $db->prepare($sql);
                 $stmt->execute($params);
                 $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                 ?>
                 <form action="" method="get" class="filter-form">
                      <input type="hidden" name="page" value="payment_log">
                      <div>
                        <label for="filter_year"><?= t('filter_by_year') ?></label>
                        <input type="number" id="filter_year" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                      </div>
                       <div>
                         <label for="filter_status"><?= t('filter_by_status') ?></label>
                         <select id="filter_status" name="filter_status">
                            <option value=""><?= t('all_users') ?></option>
                            <option value="Paid" <?= $filter_status == 'Paid' ? 'selected' : '' ?>><?= t('paid') ?></option>
                            <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>><?= t('pending') ?></option>
                        </select>
                       </div>
                       <button type="submit"><?= t('filter') ?></button>
                       <a href="?page=payment_log" class="button secondary"><?= t('clear_filter') ?></a>
                 </form>

                 <?php if (count($payments) > 0): ?>
                     <table>
                         <thead>
                             <tr>
                                 <th><?= t('year') ?></th>
                                 <th><?= t('amount') ?></th>
                                 <th><?= t('payment_date') ?></th>
                                 <th><?= t('recipient') ?></th>
                                 <th><?= t('status') ?></th>
                                 <th><?= t('notes') ?></th>
                                 <th><?= t('action') ?></th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($payments as $payment): ?>
                                 <tr>
                                     <td><?= htmlspecialchars($payment['year']) ?></td>
                                     <td><?= number_format($payment['amount'], 2) ?></td>
                                     <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                                     <td><?= htmlspecialchars($payment['recipient']) ?></td>
                                     <td><?= t(strtolower(htmlspecialchars($payment['status']))) ?></td>
                                     <td><?= nl2br(htmlspecialchars($payment['notes'])) ?></td>
                                     <td>
                                         <form action="?page=payment_log" method="post" style="display:inline;">
                                             <input type="hidden" name="action" value="delete_payment">
                                             <input type="hidden" name="id" value="<?= $payment['id'] ?>">
                                             <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                             <button type="submit" class="danger" onclick="return confirm('<?= t('confirm_delete_user') ?>');"><?= t('delete') ?></button>
                                         </form>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 <?php else: ?>
                     <p><?= t('no_payments') ?></p>
                 <?php endif; ?>

                <?php
                break;

            case 'history': // Calculation History
                 if (!$user_id) { header('Location: ?page=login'); exit; }
                 ?>
                <h2><?= t('calculation_history') ?></h2>
                 <?php
                 $filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);

                 $sql = "SELECT * FROM zakat_calculations WHERE user_id = :user_id ";
                 $params = [':user_id' => $user_id];
                 if ($filter_year) { $sql .= " AND year = :year "; $params[':year'] = $filter_year; }
                 $sql .= " ORDER BY calculated_at DESC";

                 $stmt = $db->prepare($sql);
                 $stmt->execute($params);
                 $calculations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                 ?>
                  <form action="" method="get" class="filter-form">
                      <input type="hidden" name="page" value="history">
                      <div>
                        <label for="filter_year"><?= t('filter_by_year') ?></label>
                        <input type="number" id="filter_year" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                      </div>
                       <button type="submit"><?= t('filter') ?></button>
                       <a href="?page=history" class="button secondary"><?= t('clear_filter') ?></a>
                 </form>

                 <?php if (count($calculations) > 0): ?>
                     <table>
                         <thead>
                             <tr>
                                 <th><?= t('year') ?></th>
                                 <th><?= t('gold_grams') ?></th>
                                 <th><?= t('silver_grams') ?></th>
                                 <th><?= t('cash') ?></th>
                                 <th><?= t('business_assets') ?></th>
                                  <th><?= t('debts') ?></th>
                                 <th><?= t('total_zakatable_assets') ?></th>
                                 <th><?= t('nisab_threshold') ?></th>
                                 <th><?= t('zakat_due') ?></th>
                                 <th><?= t('calculated_on') ?></th>
                                  <th><?= t('action') ?></th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($calculations as $calc): ?>
                                 <tr>
                                     <td><?= htmlspecialchars($calc['year']) ?></td>
                                     <td><?= number_format($calc['gold_grams'], 2) ?></td>
                                     <td><?= number_format($calc['silver_grams'], 2) ?></td>
                                     <td><?= number_format($calc['cash'], 2) ?></td>
                                     <td><?= number_format($calc['business_assets'], 2) ?></td>
                                     <td><?= number_format($calc['debts'], 2) ?></td>
                                     <td><?= number_format($calc['total_assets'], 2) ?></td>
                                     <td><?= number_format($calc['nisab_threshold_used'], 2) ?></td>
                                     <td><?= number_format($calc['zakat_due'], 2) ?></td>
                                     <td><?= date('Y-m-d H:i', strtotime($calc['calculated_at'])) ?></td>
                                      <td>
                                         <form action="?page=history" method="post" style="display:inline;">
                                             <input type="hidden" name="action" value="delete_calculation">
                                             <input type="hidden" name="id" value="<?= $calc['id'] ?>">
                                             <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                             <button type="submit" class="danger" onclick="return confirm('<?= t('confirm_delete_user') // Reusing translation ?>');"><?= t('delete') ?></button>
                                         </form>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 <?php else: ?>
                     <p><?= t('no_history') ?></p>
                 <?php endif; ?>
                <?php
                break;

            case 'payment_history': // Duplicate of payment log display, just different page name
                 if (!$user_id) { header('Location: ?page=login'); exit; }
                 ?>
                <h2><?= t('payment_log') ?></h2>
                 <?php
                 $filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);
                 $filter_status = isset($_GET['filter_status']) ? htmlspecialchars($_GET['filter_status']) : '';

                 $sql = "SELECT * FROM zakat_payments WHERE user_id = :user_id ";
                 $params = [':user_id' => $user_id];
                 if ($filter_year) { $sql .= " AND year = :year "; $params[':year'] = $filter_year; }
                 if ($filter_status) { $sql .= " AND status = :status "; $params[':status'] = $filter_status; }
                 $sql .= " ORDER BY payment_date DESC";

                 $stmt = $db->prepare($sql);
                 $stmt->execute($params);
                 $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                 ?>
                 <form action="" method="get" class="filter-form">
                      <input type="hidden" name="page" value="payment_history">
                      <div>
                        <label for="filter_year"><?= t('filter_by_year') ?></label>
                        <input type="number" id="filter_year" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                      </div>
                       <div>
                         <label for="filter_status"><?= t('filter_by_status') ?></label>
                         <select id="filter_status" name="filter_status">
                            <option value=""><?= t('all_users') ?></option>
                            <option value="Paid" <?= $filter_status == 'Paid' ? 'selected' : '' ?>><?= t('paid') ?></option>
                            <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>><?= t('pending') ?></option>
                        </select>
                       </div>
                       <button type="submit"><?= t('filter') ?></button>
                       <a href="?page=payment_history" class="button secondary"><?= t('clear_filter') ?></a>
                 </form>

                 <?php if (count($payments) > 0): ?>
                     <table>
                         <thead>
                             <tr>
                                 <th><?= t('year') ?></th>
                                 <th><?= t('amount') ?></th>
                                 <th><?= t('payment_date') ?></th>
                                 <th><?= t('recipient') ?></th>
                                 <th><?= t('status') ?></th>
                                 <th><?= t('notes') ?></th>
                                 <th><?= t('action') ?></th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($payments as $payment): ?>
                                 <tr>
                                     <td><?= htmlspecialchars($payment['year']) ?></td>
                                     <td><?= number_format($payment['amount'], 2) ?></td>
                                     <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                                     <td><?= htmlspecialchars($payment['recipient']) ?></td>
                                     <td><?= t(strtolower(htmlspecialchars($payment['status']))) ?></td>
                                     <td><?= nl2br(htmlspecialchars($payment['notes'])) ?></td>
                                     <td>
                                         <form action="?page=payment_history" method="post" style="display:inline;">
                                             <input type="hidden" name="action" value="delete_payment">
                                             <input type="hidden" name="id" value="<?= $payment['id'] ?>">
                                             <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                             <button type="submit" class="danger" onclick="return confirm('<?= t('confirm_delete_user') ?>');"><?= t('delete') ?></button>
                                         </form>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 <?php else: ?>
                     <p><?= t('no_payments') ?></p>
                 <?php endif; ?>

                <?php
                break;

             case 'admin':
                 if ($user_role !== 'admin') { header('Location: ?page=dashboard'); exit; }
                 $admin_section = isset($_GET['section']) ? $_GET['section'] : 'users';
                 ?>
                <h2><?= t('admin_panel') ?></h2>
                <nav>
                     <ul style="list-style: none; padding: 0; display: flex; gap: 10px; margin-bottom: 20px; background-color: #eee; padding: 10px; border-radius: 5px;">
                        <li><a href="?page=admin&section=users" class="<?= $admin_section == 'users' ? 'active' : '' ?>" style="color:#333;text-decoration:none;font-weight:bold;"><?= t('user_management') ?></a></li>
                        <li><a href="?page=admin&section=calculations" class="<?= $admin_section == 'calculations' ? 'active' : '' ?>" style="color:#333;text-decoration:none;font-weight:bold;"><?= t('view_calculations') ?></a></li>
                        <li><a href="?page=admin&section=payments" class="<?= $admin_section == 'payments' ? 'active' : '' ?>" style="color:#333;text-decoration:none;font-weight:bold;"><?= t('view_payments') ?></a></li>
                        <li><a href="?page=admin&section=settings" class="<?= $admin_section == 'settings' ? 'active' : '' ?>" style="color:#333;text-decoration:none;font-weight:bold;"><?= t('settings') ?></a></li>
                    </ul>
                </nav>

                 <?php if ($admin_section == 'users'): ?>
                     <h3><?= t('manage_users') ?></h3>
                     <button onclick="openModal('addUserModal')"><?= t('add_user') ?></button>

                     <?php
                     $users = $db->query("SELECT id, username, email, full_name, role, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                     ?>
                     <table>
                         <thead>
                             <tr>
                                 <th><?= t('user_id') ?></th>
                                 <th><?= t('username') ?></th>
                                 <th><?= t('full_name') ?></th>
                                 <th><?= t('email') ?></th>
                                 <th><?= t('role') ?></th>
                                 <th><?= t('created_at') ?></th>
                                 <th><?= t('action') ?></th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($users as $user): ?>
                                 <tr>
                                     <td><?= $user['id'] ?></td>
                                     <td><?= htmlspecialchars($user['username']) ?></td>
                                     <td><?= htmlspecialchars($user['full_name']) ?></td>
                                     <td><?= htmlspecialchars($user['email']) ?></td>
                                     <td><?= htmlspecialchars($user['role']) ?></td>
                                     <td><?= date('Y-m-d H:i', strtotime($user['created_at'])) ?></td>
                                     <td>
                                         <button class="secondary" onclick="openEditUserModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>', '<?= htmlspecialchars(addslashes($user['full_name'])) ?>', '<?= htmlspecialchars(addslashes($user['email'])) ?>', '<?= $user['role'] ?>')"><?= t('edit') ?></button>
                                         <?php if ($user['id'] != $user_id): // Prevent self-delete button ?>
                                             <form action="?page=admin&section=users" method="post" style="display:inline;">
                                                 <input type="hidden" name="action" value="admin_delete_user">
                                                 <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                 <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                 <button type="submit" class="danger" onclick="return confirm('<?= t('confirm_delete_user') ?>');"><?= t('delete') ?></button>
                                             </form>
                                          <?php endif; ?>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>

                    <div id="addUserModal" class="modal">
                      <div class="modal-content">
                        <span class="close-button" onclick="closeModal('addUserModal')">&times;</span>
                        <h3><?= t('add_user') ?></h3>
                        <form action="?page=admin&section=users" method="post">
                          <input type="hidden" name="action" value="admin_add_user">
                          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                          <div class="form-group"><label><?= t('username') ?></label><input type="text" name="username" required></div>
                          <div class="form-group"><label><?= t('full_name') ?></label><input type="text" name="full_name" required></div>
                          <div class="form-group"><label><?= t('email') ?></label><input type="email" name="email" required></div>
                          <div class="form-group"><label><?= t('password') ?></label><input type="password" name="password" required></div>
                          <div class="form-group"><label><?= t('role') ?></label><select name="role"><option value="user">User</option><option value="admin">Admin</option></select></div>
                          <button type="submit"><?= t('add_user') ?></button>
                           <button type="button" class="secondary" onclick="closeModal('addUserModal')"><?= t('cancel') ?></button>
                        </form>
                      </div>
                    </div>

                     <div id="editUserModal" class="modal">
                      <div class="modal-content">
                        <span class="close-button" onclick="closeModal('editUserModal')">&times;</span>
                        <h3><?= t('edit_user') ?></h3>
                        <form action="?page=admin&section=users" method="post" id="editUserForm">
                          <input type="hidden" name="action" value="admin_edit_user">
                          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                          <input type="hidden" name="user_id" id="edit_user_id">
                          <div class="form-group"><label><?= t('username') ?></label><input type="text" name="username" id="edit_username" required></div>
                          <div class="form-group"><label><?= t('full_name') ?></label><input type="text" name="full_name" id="edit_full_name" required></div>
                          <div class="form-group"><label><?= t('email') ?></label><input type="email" name="email" id="edit_email" required></div>
                          <div class="form-group"><label><?= t('password') ?> (<?= t('leave_blank_to_keep') ?>)</label><input type="password" name="password" id="edit_password"></div>
                          <div class="form-group"><label><?= t('role') ?></label><select name="role" id="edit_role"><option value="user">User</option><option value="admin">Admin</option></select></div>
                          <button type="submit"><?= t('save_changes') ?></button>
                           <button type="button" class="secondary" onclick="closeModal('editUserModal')"><?= t('cancel') ?></button>
                        </form>
                      </div>
                    </div>


                 <?php elseif ($admin_section == 'calculations'): ?>
                      <h3><?= t('view_calculations') ?></h3>
                      <?php
                        $filter_user_id = filter_input(INPUT_GET, 'filter_user_id', FILTER_VALIDATE_INT);
                        $filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);

                         $sql = "SELECT c.*, u.username FROM zakat_calculations c JOIN users u ON c.user_id = u.id WHERE 1=1";
                         $params = [];
                         if ($filter_user_id) { $sql .= " AND c.user_id = :user_id"; $params[':user_id'] = $filter_user_id; }
                         if ($filter_year) { $sql .= " AND c.year = :year"; $params[':year'] = $filter_year; }
                         $sql .= " ORDER BY c.calculated_at DESC";

                         $stmt = $db->prepare($sql);
                         $stmt->execute($params);
                         $calculations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                         $all_users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
                      ?>
                       <form action="" method="get" class="filter-form">
                          <input type="hidden" name="page" value="admin">
                          <input type="hidden" name="section" value="calculations">
                           <div>
                             <label for="filter_user_id"><?= t('select_user') ?></label>
                             <select id="filter_user_id" name="filter_user_id">
                                <option value=""><?= t('all_users') ?></option>
                                <?php foreach ($all_users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($filter_user_id == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                           </div>
                          <div>
                            <label for="filter_year"><?= t('filter_by_year') ?></label>
                            <input type="number" id="filter_year" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                          </div>
                           <button type="submit"><?= t('filter') ?></button>
                           <a href="?page=admin&section=calculations" class="button secondary"><?= t('clear_filter') ?></a>
                       </form>

                       <form action="" method="post" style="margin-bottom: 15px;">
                            <input type="hidden" name="action" value="export_csv">
                            <input type="hidden" name="log_type" value="calculations">
                            <input type="hidden" name="filter_user_id" value="<?= htmlspecialchars($filter_user_id) ?>">
                            <input type="hidden" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <button type="submit" class="button secondary"><?= t('export_csv') ?></button>
                       </form>


                       <?php if (count($calculations) > 0): ?>
                         <table>
                             <thead>
                                 <tr>
                                      <th><?= t('user_id') ?></th>
                                      <th><?= t('username') ?></th>
                                     <th><?= t('year') ?></th>
                                     <th><?= t('gold_grams') ?></th>
                                     <th><?= t('silver_grams') ?></th>
                                     <th><?= t('cash') ?></th>
                                     <th><?= t('business_assets') ?></th>
                                      <th><?= t('debts') ?></th>
                                     <th><?= t('total_zakatable_assets') ?></th>
                                     <th><?= t('nisab_threshold') ?></th>
                                     <th><?= t('zakat_due') ?></th>
                                     <th><?= t('calculated_on') ?></th>
                                     <th><?= t('action') ?></th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($calculations as $calc): ?>
                                     <tr>
                                         <td><?= htmlspecialchars($calc['user_id']) ?></td>
                                         <td><?= htmlspecialchars($calc['username']) ?></td>
                                         <td><?= htmlspecialchars($calc['year']) ?></td>
                                         <td><?= number_format($calc['gold_grams'], 2) ?></td>
                                         <td><?= number_format($calc['silver_grams'], 2) ?></td>
                                         <td><?= number_format($calc['cash'], 2) ?></td>
                                         <td><?= number_format($calc['business_assets'], 2) ?></td>
                                         <td><?= number_format($calc['debts'], 2) ?></td>
                                         <td><?= number_format($calc['total_assets'], 2) ?></td>
                                         <td><?= number_format($calc['nisab_threshold_used'], 2) ?></td>
                                         <td><?= number_format($calc['zakat_due'], 2) ?></td>
                                         <td><?= date('Y-m-d H:i', strtotime($calc['calculated_at'])) ?></td>
                                          <td>
                                             <form action="?page=admin&section=calculations" method="post" style="display:inline;">
                                                 <input type="hidden" name="action" value="delete_calculation">
                                                 <input type="hidden" name="id" value="<?= $calc['id'] ?>">
                                                 <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                 <button type="submit" class="danger" onclick="return confirm('<?= t('confirm_delete_user') ?>');"><?= t('delete') ?></button>
                                             </form>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>
                     <?php else: ?>
                         <p><?= t('no_history') ?></p>
                     <?php endif; ?>


                 <?php elseif ($admin_section == 'payments'): ?>
                      <h3><?= t('view_payments') ?></h3>
                      <?php
                         $filter_user_id = filter_input(INPUT_GET, 'filter_user_id', FILTER_VALIDATE_INT);
                         $filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);
                         $filter_status = isset($_GET['filter_status']) ? htmlspecialchars($_GET['filter_status']) : '';

                          $sql = "SELECT p.*, u.username FROM zakat_payments p JOIN users u ON p.user_id = u.id WHERE 1=1";
                          $params = [];
                         if ($filter_user_id) { $sql .= " AND p.user_id = :user_id"; $params[':user_id'] = $filter_user_id; }
                         if ($filter_year) { $sql .= " AND p.year = :year"; $params[':year'] = $filter_year; }
                          if ($filter_status) { $sql .= " AND p.status = :status"; $params[':status'] = $filter_status; }
                         $sql .= " ORDER BY p.payment_date DESC";

                         $stmt = $db->prepare($sql);
                         $stmt->execute($params);
                         $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                         $all_users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
                      ?>
                      <form action="" method="get" class="filter-form">
                          <input type="hidden" name="page" value="admin">
                          <input type="hidden" name="section" value="payments">
                           <div>
                             <label for="filter_user_id"><?= t('select_user') ?></label>
                             <select id="filter_user_id" name="filter_user_id">
                                <option value=""><?= t('all_users') ?></option>
                                <?php foreach ($all_users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($filter_user_id == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                           </div>
                          <div>
                            <label for="filter_year"><?= t('filter_by_year') ?></label>
                            <input type="number" id="filter_year" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                          </div>
                           <div>
                             <label for="filter_status"><?= t('filter_by_status') ?></label>
                             <select id="filter_status" name="filter_status">
                                <option value=""><?= t('all_users') // Reusing translation ?></option>
                                <option value="Paid" <?= $filter_status == 'Paid' ? 'selected' : '' ?>><?= t('paid') ?></option>
                                <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>><?= t('pending') ?></option>
                            </select>
                           </div>
                           <button type="submit"><?= t('filter') ?></button>
                           <a href="?page=admin&section=payments" class="button secondary"><?= t('clear_filter') ?></a>
                       </form>

                       <form action="" method="post" style="margin-bottom: 15px;">
                            <input type="hidden" name="action" value="export_csv">
                            <input type="hidden" name="log_type" value="payments">
                             <input type="hidden" name="filter_user_id" value="<?= htmlspecialchars($filter_user_id) ?>">
                             <input type="hidden" name="filter_year" value="<?= htmlspecialchars($filter_year) ?>">
                             <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filter_status) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <button type="submit" class="button secondary"><?= t('export_csv') ?></button>
                       </form>

                      <?php if (count($payments) > 0): ?>
                         <table>
                             <thead>
                                 <tr>
                                     <th><?= t('user_id') ?></th>
                                     <th><?= t('username') ?></th>
                                     <th><?= t('year') ?></th>
                                     <th><?= t('amount') ?></th>
                                     <th><?= t('payment_date') ?></th>
                                     <th><?= t('recipient') ?></th>
                                     <th><?= t('status') ?></th>
                                     <th><?= t('notes') ?></th>
                                     <th><?= t('action') ?></th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($payments as $payment): ?>
                                     <tr>
                                         <td><?= htmlspecialchars($payment['user_id']) ?></td>
                                         <td><?= htmlspecialchars($payment['username']) ?></td>
                                         <td><?= htmlspecialchars($payment['year']) ?></td>
                                         <td><?= number_format($payment['amount'], 2) ?></td>
                                         <td><?= htmlspecialchars($payment['payment_date']) ?></td>
                                         <td><?= htmlspecialchars($payment['recipient']) ?></td>
                                         <td><?= t(strtolower(htmlspecialchars($payment['status']))) ?></td>
                                         <td><?= nl2br(htmlspecialchars($payment['notes'])) ?></td>
                                         <td>
                                             <form action="?page=admin&section=payments" method="post" style="display:inline;">
                                                 <input type="hidden" name="action" value="delete_payment">
                                                 <input type="hidden" name="id" value="<?= $payment['id'] ?>">
                                                 <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                 <button type="submit" class="danger" onclick="return confirm('<?= t('confirm_delete_user') ?>');"><?= t('delete') ?></button>
                                             </form>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>
                     <?php else: ?>
                         <p><?= t('no_payments') ?></p>
                     <?php endif; ?>


                 <?php elseif ($admin_section == 'settings'): ?>
                    <h3><?= t('nisab_settings') ?></h3>
                     <form action="?page=admin&section=settings" method="post">
                        <input type="hidden" name="action" value="admin_update_settings">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                         <div class="form-group">
                            <label for="nisab_gold_grams"><?= t('nisab_gold_grams') ?></label>
                            <input type="number" step="0.01" id="nisab_gold_grams" name="nisab_gold_grams" value="<?= htmlspecialchars(get_setting($db, 'nisab_gold_grams')) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="nisab_silver_grams"><?= t('nisab_silver_grams') ?></label>
                            <input type="number" step="0.01" id="nisab_silver_grams" name="nisab_silver_grams" value="<?= htmlspecialchars(get_setting($db, 'nisab_silver_grams')) ?>" required>
                        </div>
                         <div class="form-group">
                            <label for="current_gold_price_per_gram"><?= t('current_gold_price') ?></label>
                            <input type="number" step="0.01" id="current_gold_price_per_gram" name="current_gold_price_per_gram" value="<?= htmlspecialchars(get_setting($db, 'current_gold_price_per_gram')) ?>" required>
                        </div>
                         <div class="form-group">
                            <label for="current_silver_price_per_gram"><?= t('current_silver_price') ?></label>
                            <input type="number" step="0.01" id="current_silver_price_per_gram" name="current_silver_price_per_gram" value="<?= htmlspecialchars(get_setting($db, 'current_silver_price_per_gram')) ?>" required>
                        </div>
                        <button type="submit"><?= t('update_settings') ?></button>
                    </form>
                 <?php endif; ?>

                 <script>
                    function openModal(modalId) {
                      document.getElementById(modalId).style.display = 'block';
                    }
                    function closeModal(modalId) {
                      document.getElementById(modalId).style.display = 'none';
                    }
                    function openEditUserModal(id, username, fullName, email, role) {
                        document.getElementById('edit_user_id').value = id;
                        document.getElementById('edit_username').value = username;
                        document.getElementById('edit_full_name').value = fullName;
                        document.getElementById('edit_email').value = email;
                        document.getElementById('edit_role').value = role;
                        document.getElementById('edit_password').value = ''; // Clear password field
                        openModal('editUserModal');
                    }
                    window.onclick = function(event) {
                      const modals = document.getElementsByClassName('modal');
                      for (let i = 0; i < modals.length; i++) {
                        if (event.target == modals[i]) {
                          modals[i].style.display = "none";
                        }
                      }
                    }
                 </script>

                 <?php
                 break;

             case 'public':
            default:
                ?>
                <h2><?= t('zakat_info_title') ?></h2>
                <p><?= t('zakat_info_text') ?></p>
                <h3><?= t('login') ?> / <?= t('register') ?></h3>
                <p>Please <a href="?page=login"><?= t('login') ?></a> or <a href="?page=register"><?= t('register') ?></a> to calculate your Zakat and log payments.</p>
                <?php
                break;
        }
        ?>

    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> <?= t('title') ?>. </p>
    </footer>

</div>

</body>
</html>