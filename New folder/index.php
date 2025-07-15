<?php
session_start();
ini_set('display_errors', 0); // Production: Hide errors
error_reporting(0); // Production: Report no errors
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Karachi');
// --- Security Headers ---
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()"); // Example permissions policy
// --- Configuration ---
define('DB_FILE', './zakat_app_final_data.sqlite'); // Use a distinct name
define('GOLD_NISAB_TOLAS', 7.5);
define('SILVER_NISAB_TOLAS', 52.5);
define('TOLA_TO_GRAMS', 11.664);
define('GOLD_NISAB_GRAMS', round(GOLD_NISAB_TOLAS * TOLA_TO_GRAMS, 3)); // 87.48
define('SILVER_NISAB_GRAMS', round(SILVER_NISAB_TOLAS * TOLA_TO_GRAMS, 3)); // 612.36
define('ZAKAT_RATE', 0.025); // 2.5%
define('ADMIN_USERNAME', 'admin');
// IMPORTANT: Generate a strong hash for your production password using password_hash()
define('ADMIN_PASSWORD_HASH', '$2y$10$YKlhnF2ZknHk6DRlAQNFz.1F8XoI3a./9xkvDF0MBI8wH14J94XnK'); // Default: admin123

// --- Database Setup ---
try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA journal_mode = WAL;"); // Improve performance
    $pdo->exec("PRAGMA foreign_keys = ON;"); // Enforce foreign key constraints

    // Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL COLLATE NOCASE, -- Case-insensitive unique usernames
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('user', 'admin')),
        zakat_due_date DATE NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Prices Table (Singleton)
    $pdo->exec("CREATE TABLE IF NOT EXISTS prices (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        gold_price_per_gram_pkr REAL NOT NULL CHECK(gold_price_per_gram_pkr > 0),
        silver_price_per_gram_pkr REAL NOT NULL CHECK(silver_price_per_gram_pkr > 0),
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Initialize prices if table is empty or prices are invalid
    $stmt_price_check = $pdo->query("SELECT gold_price_per_gram_pkr, silver_price_per_gram_pkr FROM prices WHERE id = 1");
    $current_db_prices = $stmt_price_check->fetch();
    if (!$current_db_prices || $current_db_prices['gold_price_per_gram_pkr'] <= 0 || $current_db_prices['silver_price_per_gram_pkr'] <= 0) {
        $pdo->exec("INSERT OR REPLACE INTO prices (id, gold_price_per_gram_pkr, silver_price_per_gram_pkr, last_updated) VALUES (1, 20000, 250, CURRENT_TIMESTAMP)");
    }

    // Zakat Logs Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS zakat_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL, -- Allow NULL for anonymous calculations
        calculation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        gold_grams REAL NOT NULL CHECK(gold_grams >= 0),
        silver_grams REAL NOT NULL CHECK(silver_grams >= 0),
        cash_pkr REAL NOT NULL CHECK(cash_pkr >= 0),
        business_assets_pkr REAL NOT NULL CHECK(business_assets_pkr >= 0),
        gold_price_at_calc REAL NOT NULL,
        silver_price_at_calc REAL NOT NULL,
        total_assets_value_pkr REAL NOT NULL,
        zakat_base_pkr REAL NOT NULL,
        nisab_type TEXT NOT NULL, -- 'gold_only', 'silver_only', 'silver_mix'
        nisab_value_pkr REAL NOT NULL, -- PKR value of the nisab threshold used
        nisab_met INTEGER NOT NULL CHECK(nisab_met IN (0, 1)), -- 0 = No, 1 = Yes
        zakat_due_pkr REAL NOT NULL,
        payment_date DATE NULL,
        paid_amount_pkr REAL NULL CHECK(paid_amount_pkr IS NULL OR paid_amount_pkr >= 0),
        recipient TEXT NULL,
        notes TEXT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL -- Keep log if user is deleted
    )");

    // Ensure Admin User Exists
    $stmt_admin_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt_admin_check->execute([ADMIN_USERNAME]);
    if ($stmt_admin_check->fetchColumn() == 0) {
        $stmt_admin_add = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
        $stmt_admin_add->execute([ADMIN_USERNAME, ADMIN_PASSWORD_HASH]);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage()); // Log detailed error
    die("ڈیٹا بیس میں خرابی واقع ہوئی ہے۔ براہ کرم بعد میں کوشش کریں۔"); // User-friendly message
}

// --- Helper Functions ---
function h($string) {
    // Ensure the input is treated as a string, handle potential nulls or other types
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_current_prices($pdo) {
    try {
        // Fetch again to ensure latest values are used within the request lifecycle
        $stmt = $pdo->query("SELECT gold_price_per_gram_pkr, silver_price_per_gram_pkr FROM prices WHERE id = 1");
        $prices = $stmt->fetch();
        if ($prices && $prices['gold_price_per_gram_pkr'] > 0 && $prices['silver_price_per_gram_pkr'] > 0) {
            return $prices;
        }
    } catch (PDOException $e) {
        error_log("Price Fetch Error: " . $e->getMessage());
    }
    // Fallback default prices if DB fetch fails or returns invalid data
    return ['gold_price_per_gram_pkr' => 20000, 'silver_price_per_gram_pkr' => 250];
}

function update_prices($pdo, $gold_price, $silver_price) {
    if ($gold_price <= 0 || $silver_price <= 0) {
        return false; // Invalid prices
    }
    $stmt = $pdo->prepare("UPDATE prices SET gold_price_per_gram_pkr = ?, silver_price_per_gram_pkr = ?, last_updated = CURRENT_TIMESTAMP WHERE id = 1");
    return $stmt->execute([round($gold_price, 2), round($silver_price, 2)]);
}

function get_user_by_username($pdo, $username) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? COLLATE NOCASE"); // Use NOCASE for login
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function register_user($pdo, $username, $password) {
    $username = trim($username);
    if (empty($username) || empty($password)) return "براہ کرم صارف نام اور پاس ورڈ درج کریں۔";
    if (mb_strlen($username) < 3) return "صارف نام کم از کم 3 حروف کا ہونا چاہیے۔";
    if (mb_strlen($password) < 6) return "پاس ورڈ کم از کم 6 حروف کا ہونا چاہیے۔";
    if (get_user_by_username($pdo, $username)) return "یہ صارف نام پہلے سے استعمال میں ہے۔ براہ کرم دوسرا منتخب کریں۔";

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'user')");
        if ($stmt->execute([$username, $hash])) return true;
    } catch (PDOException $e) {
        error_log("Registration Error: " . $e->getMessage());
        if ($e->getCode() == 23000 || $e->getCode() == 19) { // Integrity constraint violation (likely UNIQUE)
            return "یہ صارف نام پہلے سے استعمال میں ہے۔ براہ کرم دوسرا منتخب کریں۔";
        }
    }
    return "رجسٹریشن ناکام ہوگئی۔ ڈیٹا بیس میں خرابی۔";
}

function login_user($pdo, $username, $password) {
    $user = get_user_by_username($pdo, trim($username));
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true); // Mitigate session fixation
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['zakat_due_date'] = $user['zakat_due_date'];
        // Check if hash needs rehashing (if password algo changes in future)
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
        }
        return true;
    }
    return false;
}

function logout_user() {
    $_SESSION = array(); // Unset all session variables
    if (ini_get("session.use_cookies")) { // Delete the session cookie
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy(); // Destroy the session
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function is_logged_in() { return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0; }
function is_admin() { return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }

function get_user_zakat_logs($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM zakat_logs WHERE user_id = ? ORDER BY calculation_date DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_all_zakat_logs($pdo) {
    // LEFT JOIN to include logs even if user is deleted (user_id is NULL)
    $stmt = $pdo->query("SELECT zl.*, u.username FROM zakat_logs zl LEFT JOIN users u ON zl.user_id = u.id ORDER BY zl.calculation_date DESC");
    return $stmt->fetchAll();
}

function log_zakat_calculation($pdo, $user_id, $calc_data) {
    // user_id can be null for anonymous calculations
    $current_user_id = ($user_id !== null && $user_id > 0) ? $user_id : null;

    $sql = "INSERT INTO zakat_logs (user_id, gold_grams, silver_grams, cash_pkr, business_assets_pkr, gold_price_at_calc, silver_price_at_calc, total_assets_value_pkr, zakat_base_pkr, nisab_type, nisab_value_pkr, nisab_met, zakat_due_pkr)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    try {
        return $stmt->execute([
            $current_user_id,
            $calc_data['gold_grams'], $calc_data['silver_grams'], $calc_data['cash_pkr'], $calc_data['business_assets_pkr'],
            $calc_data['gold_price'], $calc_data['silver_price'], $calc_data['total_assets_value'],
            $calc_data['zakat_base'], $calc_data['nisab_type'], $calc_data['nisab_value'],
            $calc_data['nisab_met'] ? 1 : 0, // Store boolean as 1 or 0
            $calc_data['zakat_due']
        ]);
    } catch (PDOException $e) {
        error_log("Logging Error: " . $e->getMessage());
        return false;
    }
}

function log_zakat_payment($pdo, $log_id, $user_id, $payment_date, $paid_amount, $recipient, $notes) {
    // Verify the log exists, belongs to the user, and is unpaid
    $stmt_check = $pdo->prepare("SELECT zakat_due_pkr FROM zakat_logs WHERE id = ? AND user_id = ? AND payment_date IS NULL");
    $stmt_check->execute([$log_id, $user_id]);
    $log = $stmt_check->fetch();

    if (!$log) return "لاگ آئی ڈی غلط ہے، آپ سے متعلق نہیں، یا اس کی ادائیگی پہلے ہی ہو چکی ہے۔";
    if ($paid_amount < 0.01) return "ادا شدہ رقم کم از کم 0.01 ہونی چاہیے۔";

    // Sanitize text inputs
    $recipient = trim(strip_tags($recipient)); // Basic sanitization
    $notes = trim(strip_tags($notes));

    $sql = "UPDATE zakat_logs SET payment_date = ?, paid_amount_pkr = ?, recipient = ?, notes = ? WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$payment_date, $paid_amount, $recipient, $notes, $log_id, $user_id])) {
        try {
           // Add 1 Gregorian year for simplicity
           $next_due_date_obj = new DateTime($payment_date);
           $next_due_date_obj->modify('+1 year');
           $next_due_date = $next_due_date_obj->format('Y-m-d');

           $stmt_user = $pdo->prepare("UPDATE users SET zakat_due_date = ? WHERE id = ?");
           $stmt_user->execute([$next_due_date, $user_id]);
           $_SESSION['zakat_due_date'] = $next_due_date; // Update session
           return true;
        } catch (Exception $e) {
             error_log("Due Date Update Error: " . $e->getMessage());
             return "ادائیگی لاگ ہو گئی، لیکن اگلی مقررہ تاریخ اپ ڈیٹ کرنے میں خرابی۔";
        }
    }
    error_log("Payment Log DB Error for log ID: " . $log_id);
    return "ادائیگی لاگ کرنے میں ڈیٹا بیس میں خرابی۔";
}

function get_users($pdo) {
    try {
        return $pdo->query("SELECT id, username, role, zakat_due_date FROM users ORDER BY username COLLATE NOCASE")->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Users Error: " . $e->getMessage());
        return []; // Return empty array on error
    }
}

function check_zakat_due_notification() {
    if (is_logged_in() && isset($_SESSION['zakat_due_date']) && $_SESSION['zakat_due_date']) {
        try {
            $today = new DateTime();
            $due_date = new DateTime($_SESSION['zakat_due_date']);
            // Notify if due date is today or passed
            if ($today->format('Y-m-d') >= $due_date->format('Y-m-d')) {
                return "نوٹ: آپ کی سالانہ زکوٰۃ کی ادائیگی کا وقت آ گیا ہے یا گزر چکا ہے۔ آخری مقررہ تاریخ: " . h($_SESSION['zakat_due_date']);
            }
            // Optional: Notify if due date is approaching (e.g., within 7 days)
            // $interval = $today->diff($due_date);
            // if ($interval->days <= 7 && !$interval->invert) { // invert=0 means due date is in the future
            //     return "یاد دہانی: آپ کی سالانہ زکوٰۃ کی ادائیگی کا وقت قریب ہے۔ مقررہ تاریخ: " . h($_SESSION['zakat_due_date']);
            // }
        } catch (Exception $e) { /* Ignore date parsing errors */ }
    }
    return null;
}

function export_logs_to_csv($logs) {
    try {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=zakat_logs_'.date('Y-m-d_His').'.csv'); // Add timestamp
        header('Pragma: no-cache'); // Prevent caching
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new Exception("Failed to open output stream.");
        }

        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel UTF-8 compatibility

        fputcsv($output, [
            'Log ID', 'Username', 'Calc Date', 'Gold (g)', 'Silver (g)', 'Cash (PKR)', 'Business Assets (PKR)',
            'Gold Price', 'Silver Price', 'Total Asset Value (PKR)', 'Zakat Base (PKR)', 'Nisab Rule', 'Nisab Threshold (PKR)', 'Nisab Met', 'Zakat Due (PKR)',
            'Payment Date', 'Paid Amount (PKR)', 'Recipient', 'Notes'
        ]);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'], $log['username'] ?? 'Anonymous/Deleted', // Handle potential NULL username
                $log['calculation_date'], round($log['gold_grams'], 3), round($log['silver_grams'], 3),
                round($log['cash_pkr'], 2), round($log['business_assets_pkr'], 2), round($log['gold_price_at_calc'], 2), round($log['silver_price_at_calc'], 2),
                round($log['total_assets_value_pkr'], 2), round($log['zakat_base_pkr'], 2), $log['nisab_type'], round($log['nisab_value_pkr'], 2), $log['nisab_met'] ? 'Yes' : 'No', round($log['zakat_due_pkr'], 2),
                $log['payment_date'], $log['paid_amount_pkr'] !== null ? round($log['paid_amount_pkr'], 2) : '', // Handle null paid amount
                $log['recipient'], $log['notes']
            ]);
        }
        fclose($output);
    } catch (Exception $e) {
        error_log("CSV Export Error: " . $e->getMessage());
        // Don't die, maybe show an error message if possible, but headers might already be sent.
        echo "CSV ایکسپورٹ کرنے میں خرابی واقع ہوئی ہے۔";
    }
    exit; // Ensure script stops after file generation
}

// --- Global Variables ---
$action = $_GET['action'] ?? 'home';
$message = '';
$error = '';
$calculation_result = null;
$user_logs = [];
$all_logs = [];
$users_list = [];
$notification = null;
$current_prices = get_current_prices($pdo); // Fetch prices for display and calculation
$silver_nisab_pkr = round(SILVER_NISAB_GRAMS * $current_prices['silver_price_per_gram_pkr'], 2); // Calculate silver nisab value once
$gold_nisab_pkr = round(GOLD_NISAB_GRAMS * $current_prices['gold_price_per_gram_pkr'], 2); // Calculate gold nisab value once

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Request Handling (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF Check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "فارم کی میعاد ختم ہوگئی یا غلط ہے۔ براہ کرم دوبارہ کوشش کریں۔";
        // Regenerate token after failure
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $csrf_token = $_SESSION['csrf_token'];
        // Determine action based on where the error occurred if possible, else default
        $form_action_on_error = $_POST['form_action'] ?? 'home';
        switch($form_action_on_error){
            case 'login': $action = 'login'; break;
            case 'register': $action = 'register'; break;
            case 'update_prices': $action = is_admin() ? 'admin' : 'home'; break;
            case 'log_payment': $action = is_logged_in() && !is_admin() ? 'history' : 'home'; break;
            default: $action = 'home';
        }
    } else {
        // Process form data
        $form_action = $_POST['form_action'] ?? '';
        try {
            if ($form_action === 'login') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                if (login_user($pdo, $username, $password)) {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?action=' . (is_admin() ? 'admin' : 'home'));
                    exit;
                } else { $error = "غلط صارف نام یا پاس ورڈ۔"; $action = 'login'; }
            } elseif ($form_action === 'register') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                if ($password !== $confirm_password) { $error = "پاس ورڈز مماثل نہیں ہیں۔"; $action = 'register'; }
                else {
                    $result = register_user($pdo, $username, $password);
                    if ($result === true) { $message = "رجسٹریشن کامیاب! اب آپ لاگ ان کر سکتے ہیں۔"; $action = 'login';}
                    else { $error = $result; $action = 'register';} // Display specific error
                }
            } elseif ($form_action === 'calculate_zakat') {
                $gold_price = $current_prices['gold_price_per_gram_pkr'];
                $silver_price = $current_prices['silver_price_per_gram_pkr'];

                if ($gold_price <= 0 || $silver_price <= 0) {
                    $error = "زکوٰۃ کا حساب لگانے کے لیے سونے اور چاندی کی درست قیمتیں ضروری ہیں۔";
                    $action = 'home';
                } else 

{
                    $gold_amount = filter_input(INPUT_POST, 'gold_amount', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]) ?? 0;
                    $gold_unit = ($_POST['gold_unit'] ?? 'grams') === 'tolas' ? 'tolas' : 'grams';
                    $silver_amount = filter_input(INPUT_POST, 'silver_amount', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]) ?? 0;
                    $silver_unit = ($_POST['silver_unit'] ?? 'grams') === 'tolas' ? 'tolas' : 'grams';
                    $cash = filter_input(INPUT_POST, 'cash_pkr', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]) ?? 0;
                    $business_assets = filter_input(INPUT_POST, 'business_assets_pkr', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]) ?? 0;

                    $gold_grams = round(($gold_unit === 'tolas' ? $gold_amount * TOLA_TO_GRAMS : $gold_amount), 3);
                    $silver_grams = round(($silver_unit === 'tolas' ? $silver_amount * TOLA_TO_GRAMS : $silver_amount), 3);

                    $gold_value = round($gold_grams * $gold_price, 2);
                    $silver_value = round($silver_grams * $silver_price, 2);
                    $total_assets_value = round($gold_value + $silver_value + $cash + $business_assets, 2);

                    // Zakat calculation variables
                    $zakat_due = 0;
                    $nisab_value_applied = 0;
                    $nisab_type_applied = 'کوئی نہیں';
                    $nisab_met = false;
                    $zakat_base = 0;

                    // --- Revised Zakat Logic ---
                    $has_gold = $gold_grams > 0.0001; // Small tolerance for float check
                    $has_silver = $silver_grams > 0.0001;
                    $has_cash = $cash > 0.001;
                    $has_business = $business_assets > 0.001;

                    // Count number of asset types
                    $asset_types_count = ($has_gold ? 1 : 0) + ($has_silver ? 1 : 0) + ($has_cash ? 1 : 0) + ($has_business ? 1 : 0);

                    // Rule 1: Only Gold (and nothing else)
                    if ($has_gold && !$has_silver && !$has_cash && !$has_business) {
                        $nisab_type_applied = 'gold_only';
                        $nisab_value_applied = $gold_nisab_pkr; // For display comparison
                        if ($gold_grams >= GOLD_NISAB_GRAMS) { // 7.5 tolas equivalent in grams
                            $nisab_met = true;
                            $zakat_base = $gold_value;
                        }
                    }
                    // Rule 2: Only Silver (and nothing else)
                    elseif ($has_silver && !$has_gold && !$has_cash && !$has_business) {
                        $nisab_type_applied = 'silver_only';
                        $nisab_value_applied = $silver_nisab_pkr; // For check and display
                        if ($silver_grams >= SILVER_NISAB_GRAMS) { // 52.5 tolas equivalent in grams
                            $nisab_met = true;
                            $zakat_base = $silver_value;
                        }
                    }
                    // Rule 3: Any combination of two or more asset types (gold, silver, cash, business goods)
                    elseif ($asset_types_count >= 2 && $total_assets_value > 0.001) {
                        $nisab_type_applied = 'silver_mix';
                        $nisab_value_applied = $silver_nisab_pkr; // Silver nisab (52.5 tolas) value as threshold
                        if ($total_assets_value >= $silver_nisab_pkr) {
                            $nisab_met = true;
                            $zakat_base = $total_assets_value; // Zakat base is the total value
                        }
                    }

                    // Calculate Zakat if Nisab is met
                    if ($nisab_met) {
                        $zakat_due = round($zakat_base * ZAKAT_RATE, 2); // ZAKAT_RATE is 0.025 (2.5%)
                    }

                    // Map nisab type to Urdu description
                    $nisab_type_urdu = 'کوئی نہیں';
                    if ($nisab_type_applied === 'gold_only') {
                        $nisab_type_urdu = 'صرف سونا (' . GOLD_NISAB_TOLAS . ' تولہ)';
                    } elseif ($nisab_type_applied === 'silver_only') {
                        $nisab_type_urdu = 'صرف چاندی (' . SILVER_NISAB_TOLAS . ' تولہ)';
                    } elseif ($nisab_type_applied === 'silver_mix') {
                        $nisab_type_urdu = 'مخلوط/دیگر (چاندی نصاب: ' . SILVER_NISAB_TOLAS . ' تولہ)';
                    }

                    $calculation_result = [
                        'gold_grams' => $gold_grams,
                        'silver_grams' => $silver_grams,
                        'cash_pkr' => $cash,
                        'business_assets_pkr' => $business_assets,
                        'gold_price' => $gold_price,
                        'silver_price' => $silver_price,
                        'total_assets_value' => $total_assets_value,
                        'nisab_type' => $nisab_type_urdu, // Use Urdu description
                        'nisab_value' => $nisab_value_applied, // The threshold value used
                        'nisab_met' => $nisab_met,
                        'zakat_base' => $zakat_base, // The value Zakat is calculated on
                        'zakat_due' => $zakat_due
                    ];

                    // Log if logged in and Zakat is due
                    if (is_logged_in()) {
                        if ($nisab_met && $zakat_due > 0) {
                            // Log successful calculation before redirecting
                            if (!log_zakat_calculation($pdo, $_SESSION['user_id'], $calculation_result)) {
                                $error = "زکوٰۃ کے حساب کو لاگ کرنے میں خرابی۔";
                                $action = 'home'; // Show error on home page
                            } else {
                                header('Location: ' . $_SERVER['PHP_SELF'] . '?action=history&calc=success'); exit;
                            }
                        } else { // Nisab not met or Zakat is zero
                            $message = $nisab_met ? "نصاب پورا ہے لیکن زکوٰۃ صفر ہے۔" : "آپ کے اثاثے نصاب سے کم ہیں۔ فی الحال زکوٰۃ واجب نہیں ہے۔";
                            $action = 'home'; // Show message/result on home page
                        }
                    } else { // Not logged in
                        if (!$nisab_met) { $message = "آپ کے اثاثے نصاب سے کم ہیں۔ فی الحال زکوٰۃ واجب نہیں ہے۔"; }
                        else { $message = "حساب کا نتیجہ نیچے دکھایا گیا ہے۔ ریکارڈ محفوظ کرنے اور ادائیگی لاگ کرنے کے لیے براہ کرم <a href='?action=login'>لاگ ان</a> یا <a href='?action=register'>رجسٹر</a> ہوں۔"; }
                        $action = 'home'; // Show result on home page
                    }
                }

					// End price check else
            } elseif ($form_action === 'log_payment' && is_logged_in()) {
                $log_id = filter_input(INPUT_POST, 'log_id', FILTER_VALIDATE_INT);
                $payment_date = $_POST['payment_date'] ?? '';
                $paid_amount = filter_input(INPUT_POST, 'paid_amount_pkr', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]);
                $recipient = $_POST['recipient'] ?? ''; // Sanitize later in function
                $notes = $_POST['notes'] ?? ''; // Sanitize later in function

                $date_valid = preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $payment_date) && (new DateTime() >= new DateTime($payment_date)); // Check format and not future date

                if ($log_id && $date_valid && $paid_amount !== null) {
                     $result = log_zakat_payment($pdo, $log_id, $_SESSION['user_id'], $payment_date, $paid_amount, $recipient, $notes);
                     if ($result === true) {
                         header('Location: ' . $_SERVER['PHP_SELF'] . '?action=history&payment=success'); exit;
                     } else { $error = $result; $action = 'history';}
                } else {
                    $error = "براہ کرم ادائیگی لاگ کرنے کے لیے درست تفصیلات درج کریں (لاگ ID، ماضی یا آج کی تاریخ YYYY-MM-DD، مثبت رقم)۔";
                    $action = 'history';
                }
            } elseif ($form_action === 'update_prices' && is_admin()) {
                $gold_price = filter_input(INPUT_POST, 'gold_price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]);
                $silver_price = filter_input(INPUT_POST, 'silver_price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]);
                if ($gold_price && $silver_price) {
                    if (update_prices($pdo, $gold_price, $silver_price)) {
                         $message = "قیمتیں کامیابی سے اپ ڈیٹ ہو گئیں۔";
                         $current_prices = get_current_prices($pdo); // Refresh prices display
                         $silver_nisab_pkr = round(SILVER_NISAB_GRAMS * $current_prices['silver_price_per_gram_pkr'], 2);
                         $gold_nisab_pkr = round(GOLD_NISAB_GRAMS * $current_prices['gold_price_per_gram_pkr'], 2);
                    } else { $error = "قیمتیں اپ ڈیٹ کرنے میں خرابی۔"; }
                } else { $error = "براہ کرم سونے اور چاندی کے لیے درست مثبت قیمتیں درج کریں۔"; }
                $action = 'admin';
            }
        } catch (Exception $e) {
             error_log("General POST Error: " . $e->getMessage());
             $error = "ایک غیر متوقع خرابی پیش آئی۔";
             // Determine action based on context if possible
             $failed_action = $_POST['form_action'] ?? 'home';
             $action = ($failed_action === 'admin' && is_admin()) ? 'admin' : (($failed_action === 'history' && is_logged_in()) ? 'history' : 'home');
        }
    } // End CSRF check else
} // End POST handling

// --- Page Load Data Fetching (GET requests or after POST errors/redirects) ---
if (is_logged_in()) {
    $notification = check_zakat_due_notification();
    if ($action === 'history' || (isset($_GET['action']) && $_GET['action'] === 'history')) {
        $user_logs = get_user_zakat_logs($pdo, $_SESSION['user_id']);
        if(isset($_GET['calc']) && $_GET['calc'] == 'success' && empty($message)) $message = "زکوٰۃ کا حساب کامیابی سے لاگ ہو گیا۔ اب آپ نیچے زیر التواء فہرست میں ادائیگی کی تفصیلات درج کر سکتے ہیں۔";
        if(isset($_GET['payment']) && $_GET['payment'] == 'success' && empty($message)) $message = "زکوٰۃ کی ادائیگی کامیابی سے لاگ ہو گئی۔";
        $action = 'history'; // Ensure action is set correctly
    } elseif ($action === 'admin' && is_admin()) {
        $users_list = get_users($pdo);
        $all_logs = get_all_zakat_logs($pdo);
        if (isset($_GET['export']) && $_GET['export'] === 'csv') { export_logs_to_csv($all_logs); /* Exits */ }
    } elseif ($action === 'logout') { logout_user(); /* Exits */ }
    elseif ($action === 'home' && is_admin()){ $action = 'admin'; } // Redirect admin from user home

    // Reload admin data if action is admin but data isn't loaded
    if ($action === 'admin' && is_admin() && empty($users_list)) {
         $users_list = get_users($pdo);
         $all_logs = get_all_zakat_logs($pdo);
    }
} else { // Not logged in
    // Allow only home, login, register
    if ($action !== 'home' && $action !== 'login' && $action !== 'register') {
        $action = 'home';
    }
}
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>زکوٰۃ کیلکولیٹر اور لاگنگ سسٹم</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Base styles from previous version, slightly refined */
        body { font-family: 'Noto Nastaliq Urdu', serif; direction: rtl; margin: 0; padding: 0; background-color: #f8f9fa; color: #343a40; font-size: 1rem; line-height: 1.7; }
        .container { max-width: 960px; margin: 25px auto; padding: 25px; background-color: #ffffff; border: 1px solid #dee2e6; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); border-radius: 0.3rem; }
        h1, h2, h3 { color: #005a31; /* Darker Green */ text-align: center; margin-top: 0; margin-bottom: 1.5rem; font-weight: 700; }
        h1 { font-size: 2rem;} h2 { font-size: 1.75rem;} h3 { font-size: 1.5rem; margin-top: 2rem;}
        nav { background-color: #005a31; padding: 0.75rem 0; text-align: center; margin-bottom: 1.5rem; border-radius: 0.25rem; }
        nav a { color: #ffffff; text-decoration: none; margin: 0 1rem; font-weight: 700; font-size: 1.1rem; }
        nav a:hover { text-decoration: underline; color: #e9ecef;}
        form { margin-bottom: 1.5rem; padding: 1.5rem; border: 1px solid #e9ecef; border-radius: 0.25rem; background-color:#f8f9fa; }
        label { display: block; margin-bottom: 0.6rem; font-weight: 700; color: #495057; }
        input[type="text"], input[type="password"], input[type="number"], input[type="date"], select, textarea { display: block; width: 100%; padding: 0.5rem 0.75rem; font-size: 1rem; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: 0.25rem; transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; box-sizing: border-box; margin-bottom: 1rem; font-family: inherit; }
        input:focus, select:focus, textarea:focus { border-color: #80bdff; outline: 0; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); }
        input[type="number"] { direction: ltr; text-align: right; } /* Ensure numbers input correctly */
        textarea { min-height: 90px; resize: vertical;}
        select { width: auto; min-width: 120px; padding: 0.5rem 1.5rem 0.5rem 0.75rem; appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: left 0.75rem center; background-size: 16px 12px;}
        .input-group { display: flex; align-items: center; margin-bottom: 1rem; gap: 0.75rem; flex-wrap: wrap;}
        .input-group label { margin-bottom: 0; flex-shrink: 0;}
        .input-group input[type="number"] { flex: 1 1 auto; width: auto; min-width: 150px; margin-bottom: 0;}
        .input-group select { flex-shrink: 0; margin-bottom: 0; }
        button[type="submit"], .button { background-color: #28a745; color: #ffffff; padding: 0.6rem 1.2rem; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 1.1rem; font-weight: 700; font-family: inherit; transition: background-color 0.2s ease; text-decoration: none; display: inline-block; text-align: center; vertical-align: middle;}
        button[type="submit"]:hover, .button:hover { background-color: #218838; color: #ffffff; }
        .button-secondary { background-color: #6c757d; } .button-secondary:hover { background-color: #5a6268; }
        .button-link { background: none; border: none; color: #007bff; text-decoration: underline; padding: 0; font-size: 1rem; cursor: pointer; font-family: inherit;} .button-link:hover { color: #0056b3; }
        .message { padding: 0.8rem 1.25rem; margin-bottom: 1.5rem; border-radius: 0.25rem; border: 1px solid transparent; font-size: 1rem; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .message.notification { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .message a { color: inherit; font-weight: bold; text-decoration: underline;}
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; background-color: #fff; }
        th, td { border: 1px solid #dee2e6; padding: 0.75rem; text-align: right; vertical-align: top; word-wrap: break-word; }
        th { background-color: #e9ecef; font-weight: 700; white-space: nowrap;}
        tbody tr:nth-child(odd) { background-color: #f8f9fa; }
        .calculation-result { margin-top: 1.5rem; padding: 1.5rem; border: 1px solid #c3e6cb; background-color: #f0fff4; border-radius: 0.25rem; }
        .calculation-result h3 { color: #155724; }
        .calculation-result p, .calculation-result ul { margin-bottom: 0.75rem; }
        .calculation-result ul { padding-right: 25px; list-style: none; /* Use custom bullets if needed */ }
        .calculation-result li::before { content: "•"; color: #005a31; display: inline-block; width: 1em; margin-left: -1em; margin-right: 0.5em; }
        .calculation-result strong { color: #005a31;}
        .calculation-result .final-zakat { font-size: 1.25rem; color: #155724; font-weight: 700; background-color: #d4edda; padding: 0.25rem 0.5rem; border-radius: 0.2rem; display: inline-block;}
        .user-info { text-align: left; margin-bottom: 1rem; padding: 0.5rem; font-size: 0.95em; color: #6c757d; }
        .price-info { text-align: center; margin-bottom: 1rem; font-size: 0.95em; color: #6c757d; }
        .small-text { font-size: 0.9em; color: #6c757d; display:block; margin-top: -0.75rem; margin-bottom: 0.75rem;}
        .action-buttons button { font-size: 0.9rem; padding: 0.3rem 0.6rem; margin: 0.1rem;}
        .highlight-due { color: #721c24; font-weight: bold; background-color: #f8d7da !important;}
        .payment-form-row td { background-color: #e7f5ff; padding: 1.25rem; }
        .payment-form-row form label { margin-top: 0.75rem; }
        .payment-form-row form button { margin-right: 0.5rem; margin-top: 0.5rem;}
        .nisab-info { font-size: 0.95em; color: #495057; margin: 1.5rem 0; padding: 1rem 1.25rem; background-color: #e9ecef; border-radius: 0.25rem; border-right: 5px solid #005a31;}
        .nisab-info strong { color: #005a31;}
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 1rem; border: 1px solid #dee2e6; border-radius: .25rem;}
        .table-responsive table { margin-top: 0; border: none;}
        .table-responsive th, .table-responsive td { border-left: none; border-right: none;}
        .table-responsive thead th { border-top: none;}
        .table-responsive tbody tr:last-child td { border-bottom: none;}
         /* Responsive */
        @media (max-width: 768px) {
             h1 { font-size: 1.75rem;} h2 { font-size: 1.5rem;} h3 { font-size: 1.25rem;}
             nav a { margin: 0 0.5rem; font-size: 1rem;}
             th, td { padding: 0.5rem; font-size: 0.9rem;}
             .container { margin: 15px; padding: 15px;}
             form { padding: 1rem;}
             .input-group { flex-direction: column; align-items: stretch; }
             .input-group label { width: 100%; margin-bottom: 0.3rem; }
             .input-group input[type="number"], .input-group select { width: 100%; margin-bottom: 0.75rem; }
             button[type="submit"] { width: 100%; padding: 0.75rem; }
             .nisab-info { padding: 0.75rem 1rem;}
        }
        @media (max-width: 480px) {
             body { font-size: 0.95rem;}
             h1 { font-size: 1.5rem;} h2 { font-size: 1.25rem;} h3 { font-size: 1.1rem;}
             nav a { display: block; margin: 0.5rem 0;}
             th, td { font-size: 0.85rem;}
             .calculation-result ul { padding-right: 20px;}
        }
        /* Accessibility */
        input:invalid, select:invalid, textarea:invalid { border-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>زکوٰۃ کیلکولیٹر اور لاگنگ سسٹم</h1>

        <nav>
            <a href="?action=home">کیلکولیٹر</a>
            <?php if (is_logged_in()): ?>
                <?php if (is_admin()): ?>
                    <a href="?action=admin">ایڈمن پینل</a>
                <?php else: ?>
                    <a href="?action=history">میرا ریکارڈ</a>
                <?php endif; ?>
                 <a href="?action=logout">لاگ آؤٹ</a>
            <?php else: ?>
                 <a href="?action=login">لاگ ان</a>
                 <a href="?action=register">رجسٹر</a>
            <?php endif; ?>
        </nav>

        <?php if (is_logged_in()): ?>
             <div class="user-info">خوش آمدید، <?php echo h($_SESSION['username']); ?>!</div>
            <?php if ($notification): ?>
                 <div class="message notification"><?php echo h($notification); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($message): ?><div class="message <?php echo (strpos($message, 'کامیاب') !== false || strpos($message, 'لاگ ہو گئی') !== false) ? 'success' : ((strpos($message, 'کم ہیں') !== false || strpos($message, 'واجب نہیں') !== false) ? 'info' : 'success'); ?>"><?php echo $message; /* Allow anchor tags for login/register links */ ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?php echo h($error); ?></div><?php endif; ?>

        <?php // --- Main Content Area --- ?>

        <?php if ($action === 'home'): ?>
            <h2>زکوٰۃ کا حساب لگائیں</h2>
            <div class="price-info">موجودہ قیمتیں (<?php echo date('Y-m-d'); ?>): سونا: <?php echo h(number_format($current_prices['gold_price_per_gram_pkr'], 2)); ?> PKR/گرام, چاندی: <?php echo h(number_format($current_prices['silver_price_per_gram_pkr'], 2)); ?> PKR/گرام</div>

             <div class="nisab-info">
                <strong>نصاب (کم از کم حد جس پر زکوٰۃ واجب ہوتی ہے):</strong><br>
                ۱۔ صرف سونا: <strong><?php echo GOLD_NISAB_TOLAS; ?> تولہ</strong> (<?php echo number_format(GOLD_NISAB_GRAMS, 2); ?> گرام) یا اس سے زیادہ۔<br>
                ۲۔ صرف چاندی: <strong><?php echo SILVER_NISAB_TOLAS; ?> تولہ</strong> (<?php echo number_format(SILVER_NISAB_GRAMS, 2); ?> گرام) یا اس سے زیادہ۔<br>
                ۳۔ مخلوط اثاثے (سونا، چاندی، نقدی، کاروباری سامان کا کوئی بھی مجموعہ) یا صرف نقدی/کاروباری سامان: اگر ان تمام اثاثوں کی کل مالیت <strong><?php echo SILVER_NISAB_TOLAS; ?> تولہ چاندی</strong> کی موجودہ قیمت (تقریباً <?php echo number_format($silver_nisab_pkr, 0); ?> PKR) کے برابر یا اس سے زیادہ ہو۔<br>
                <strong>زکوٰۃ کی شرح:</strong> اگر اثاثے نصاب کے برابر یا اس سے زیادہ ہوں تو کل قابلِ زکوٰۃ مالیت پر <strong>2.5%</strong> زکوٰۃ ادا کرنا ہوگی۔
            </div>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=home">
                <input type="hidden" name="form_action" value="calculate_zakat">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

                 <div class="input-group">
                    <label for="gold_amount">سونا:</label>
                    <input type="number" step="any" min="0" id="gold_amount" name="gold_amount" value="<?php echo isset($_POST['gold_amount']) ? h($_POST['gold_amount']) : '0'; ?>" required aria-label="سونے کی مقدار">
                    <select name="gold_unit" aria-label="سونے کا یونٹ">
                        <option value="grams" <?php echo (!isset($_POST['gold_unit']) || $_POST['gold_unit'] == 'grams') ? 'selected' : ''; ?>>گرام</option>
                        <option value="tolas" <?php echo (isset($_POST['gold_unit']) && $_POST['gold_unit'] == 'tolas') ? 'selected' : ''; ?>>تولہ</option>
                    </select>
                 </div>

                <div class="input-group">
                    <label for="silver_amount">چاندی:</label>
                    <input type="number" step="any" min="0" id="silver_amount" name="silver_amount" value="<?php echo isset($_POST['silver_amount']) ? h($_POST['silver_amount']) : '0'; ?>" required aria-label="چاندی کی مقدار">
                    <select name="silver_unit" aria-label="چاندی کا یونٹ">
                         <option value="grams" <?php echo (!isset($_POST['silver_unit']) || $_POST['silver_unit'] == 'grams') ? 'selected' : ''; ?>>گرام</option>
                        <option value="tolas" <?php echo (isset($_POST['silver_unit']) && $_POST['silver_unit'] == 'tolas') ? 'selected' : ''; ?>>تولہ</option>
                    </select>
                </div>

                <label for="cash_pkr">نقدی (PKR) (بینک بیلنس، ہاتھ میں رقم وغیرہ):</label>
                <input type="number" step="any" min="0" id="cash_pkr" name="cash_pkr" value="<?php echo isset($_POST['cash_pkr']) ? h($_POST['cash_pkr']) : '0'; ?>" required aria-label="نقدی روپے میں">

                <label for="business_assets_pkr">کاروباری اثاثے (PKR) (قابل فروخت مال کی موجودہ قیمت، وصول طلب قرضے وغیرہ):</label>
                <input type="number" step="any" min="0" id="business_assets_pkr" name="business_assets_pkr" value="<?php echo isset($_POST['business_assets_pkr']) ? h($_POST['business_assets_pkr']) : '0'; ?>" required aria-label="کاروباری اثاثے روپے میں">
                <span class="small-text">(ذاتی استعمال کی چیزیں جیسے گھر، گاڑی، فرنیچر وغیرہ شامل نہ کریں۔ شک کی صورت میں مستند عالم سے رجوع کریں۔)</span>

                <button type="submit">زکوٰۃ کا حساب لگائیں</button>
            </form>

            <?php if ($calculation_result): ?>
                 <div class="calculation-result">
                    <h3>حساب کا نتیجہ:</h3>
                    <p>آپ کے فراہم کردہ اثاثے:</p>
                     <ul>
                         <li>سونا: <?php echo h(number_format($calculation_result['gold_grams'], 3)); ?> گرام (موجودہ مالیت: <?php echo h(number_format($calculation_result['gold_grams'] * $calculation_result['gold_price'], 2)); ?> PKR)</li>
                         <li>چاندی: <?php echo h(number_format($calculation_result['silver_grams'], 3)); ?> گرام (موجودہ مالیت: <?php echo h(number_format($calculation_result['silver_grams'] * $calculation_result['silver_price'], 2)); ?> PKR)</li>
                         <li>نقدی: <?php echo h(number_format($calculation_result['cash_pkr'], 2)); ?> PKR</li>
                         <li>کاروباری اثاثے: <?php echo h(number_format($calculation_result['business_assets_pkr'], 2)); ?> PKR</li>
                    </ul>
                    <p>تمام درج کردہ اثاثوں کی کل مالیت: <strong><?php echo h(number_format($calculation_result['total_assets_value'], 2)); ?> PKR</strong></p>
                     <hr style="border-top: 1px solid #c3e6cb;">
                     <p>لاگو نصاب کی قسم: <strong><?php echo h($calculation_result['nisab_type']); ?></strong></p>
                     <p>نصاب کی حد (کم از کم مالیت): <strong><?php echo h(number_format($calculation_result['nisab_value'], 2)); ?> PKR</strong></p>

                    <?php if ($calculation_result['nisab_met']): ?>
                        <p style="color: green;">چونکہ آپ کے کل اثاثے (<?php echo h(number_format($calculation_result['total_assets_value'], 2)); ?> PKR) نصاب کی حد سے زیادہ یا برابر ہیں، لہذا زکوٰۃ واجب ہے۔</p>
                        <p>زکوٰۃ کا حساب اس رقم پر کیا گیا ہے (زکوٰۃ بیس): <strong><?php echo h(number_format($calculation_result['zakat_base'], 2)); ?> PKR</strong></p>
                        <p>واجب الادا زکوٰۃ (<?php echo ZAKAT_RATE * 100; ?>%): <span class="final-zakat"><?php echo h(number_format($calculation_result['zakat_due'], 2)); ?> PKR</span></p>
                        <?php if(is_logged_in()): ?>
                            <p class="small-text" style="color: green;">یہ حساب آپ کے ریکارڈ میں شامل کر دیا گیا ہے۔ آپ <a href="?action=history">میرا ریکارڈ</a> سیکشن میں ادائیگی لاگ کر سکتے ہیں۔</p>
                        <?php else: ?>
                            <p class="small-text" style="color: blue;">نوٹ: یہ نتیجہ صرف معلوماتی ہے۔ اپنے حسابات اور ادائیگیوں کو ٹریک کرنے کے لیے، براہ کرم <a href='?action=login'>لاگ ان</a> یا <a href='?action=register'>رجسٹر</a> ہوں۔</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: red; font-weight: bold;">چونکہ آپ کے کل اثاثے (<?php echo h(number_format($calculation_result['total_assets_value'], 2)); ?> PKR) نصاب کی حد (<?php echo h(number_format($calculation_result['nisab_value'], 2)); ?> PKR) سے کم ہیں، لہذا فی الحال زکوٰۃ واجب نہیں ہے۔</p>
                         <?php if(!is_logged_in()): ?>
                             <p class="small-text" style="color: blue;">نوٹ: یہ نتیجہ صرف معلوماتی ہے۔ اپنے حسابات اور ادائیگیوں کو ٹریک کرنے کے لیے، براہ کرم <a href='?action=login'>لاگ ان</a> یا <a href='?action=register'>رجسٹر</a> ہوں۔</p>
                         <?php endif; ?>
                    <?php endif; ?>
                    <p class="small-text">حساب کے وقت قیمتیں: سونا <?php echo h(number_format($calculation_result['gold_price'], 2)); ?>/گرام, چاندی <?php echo h(number_format($calculation_result['silver_price'], 2)); ?>/گرام</p>
                 </div>
            <?php endif; ?>

        <?php elseif ($action === 'login' && !is_logged_in()): ?>
             <h2>لاگ ان</h2>
             <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=login">
                 <input type="hidden" name="form_action" value="login">
                 <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                 <label for="username">صارف نام:</label>
                 <input type="text" id="username" name="username" required autocomplete="username" autofocus>
                 <label for="password">پاس ورڈ:</label>
                 <input type="password" id="password" name="password" required autocomplete="current-password">
                 <button type="submit">لاگ ان</button>
             </form>
             <p style="text-align:center;">اکاؤنٹ نہیں ہے؟ <a href="?action=register">نیا اکاؤنٹ بنائیں</a></p>

        <?php elseif ($action === 'register' && !is_logged_in()): ?>
             <h2>نئی رجسٹریشن</h2>
             <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=register">
                 <input type="hidden" name="form_action" value="register">
                 <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                 <label for="username">صارف نام:</label>
                 <input type="text" id="username" name="username" required autocomplete="username" pattern="^[a-zA-Z0-9_]{3,}$" title="کم از کم 3 حروف، صرف حروف، نمبر اور انڈر سکور">
                 <label for="password">پاس ورڈ (کم از کم 6 حروف):</label>
                 <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
                 <label for="confirm_password">پاس ورڈ کی تصدیق کریں:</label>
                 <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                 <button type="submit">رجسٹر</button>
             </form>
             <p style="text-align:center;">پہلے سے اکاؤنٹ ہے؟ <a href="?action=login">یہاں لاگ ان کریں</a></p>

        <?php elseif ($action === 'history' && is_logged_in() && !is_admin()): ?>
            <h2>میرا ریکارڈ</h2>
            <h3>زیر التواء ادائیگی</h3>
             <?php $pending_logs = array_filter($user_logs, function($log) { return empty($log['payment_date']) && $log['zakat_due_pkr'] > 0; }); ?>
             <?php if (!empty($pending_logs)): ?>
                 <div class="table-responsive">
                 <table><thead><tr><th>ID</th><th>حساب کی تاریخ</th><th>واجب الادا (PKR)</th><th>عمل</th></tr></thead><tbody>
                     <?php foreach ($pending_logs as $log): ?>
                     <tr>
                         <td><?php echo h($log['id']); ?></td>
                         <td><?php echo h(date('Y-m-d H:i', strtotime($log['calculation_date']))); ?></td>
                         <td><?php echo h(number_format($log['zakat_due_pkr'], 2)); ?></td>
                         <td class="action-buttons"><button class="button-link" onclick="var row = document.getElementById('payment_form_<?php echo h($log['id']); ?>'); var btn = this; row.style.display = row.style.display === 'none' ? 'table-row' : 'none'; btn.textContent = (row.style.display === 'none' ? 'ادائیگی لاگ کریں' : 'منسوخ کریں');">ادائیگی لاگ کریں</button></td>
                     </tr>
                     <tr class="payment-form-row" id="payment_form_<?php echo h($log['id']); ?>" style="display:none;"><td colspan="4">
                         <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=history">
                             <input type="hidden" name="form_action" value="log_payment"><input type="hidden" name="log_id" value="<?php echo h($log['id']); ?>">
                             <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                             <p><strong>لاگ ID: <?php echo h($log['id']); ?></strong> | واجب الادا: <?php echo h(number_format($log['zakat_due_pkr'], 2)); ?> PKR</p>
                             <label for="payment_date_<?php echo h($log['id']); ?>">ادائیگی کی تاریخ:</label><input type="date" id="payment_date_<?php echo h($log['id']); ?>" name="payment_date" required max="<?php echo date('Y-m-d'); ?>" aria-label="ادائیگی کی تاریخ">
                             <label for="paid_amount_pkr_<?php echo h($log['id']); ?>">ادا شدہ رقم (PKR):</label><input type="number" step="any" min="0.01" id="paid_amount_pkr_<?php echo h($log['id']); ?>" name="paid_amount_pkr" value="<?php echo h(number_format($log['zakat_due_pkr'], 2, '.', '')); ?>" required aria-label="ادا شدہ رقم">
                             <label for="recipient_<?php echo h($log['id']); ?>">وصول کنندہ:</label><input type="text" id="recipient_<?php echo h($log['id']); ?>" name="recipient" placeholder="نام/ادارہ (اختیاری)" aria-label="وصول کنندہ">
                             <label for="notes_<?php echo h($log['id']); ?>">نوٹس:</label><textarea id="notes_<?php echo h($log['id']); ?>" name="notes" placeholder="اضافی تفصیلات (اختیاری)" aria-label="نوٹس"></textarea>
                             <button type="submit" class="button">ادائیگی جمع کروائیں</button>
                         </form>
                     </td></tr>
                     <?php endforeach; ?>
                 </tbody></table></div>
             <?php else: ?><p>کوئی زیر التواء ادائیگی نہیں ہے۔ جب آپ <a href="?action=home">کیلکولیٹر</a> استعمال کریں گے اور زکوٰۃ واجب ہوگی تو حساب یہاں ظاہر ہوگا۔</p><?php endif; ?>

            <h3>مکمل شدہ ریکارڈ</h3>
            <?php $completed_logs = array_filter($user_logs, function($log) { return !empty($log['payment_date']); }); ?>
             <?php if (!empty($completed_logs)): ?>
                <div class="table-responsive">
                <table><thead><tr><th>ID</th><th>حساب</th><th>واجب</th><th>ادائیگی</th><th>ادا شدہ</th><th>وصول کنندہ</th><th>نوٹس</th></tr></thead><tbody>
                    <?php foreach ($completed_logs as $log): ?>
                    <tr>
                        <td><?php echo h($log['id']); ?></td>
                        <td><?php echo h(date('Y-m-d', strtotime($log['calculation_date']))); ?></td>
                        <td><?php echo h(number_format($log['zakat_due_pkr'], 2)); ?></td>
                        <td><?php echo h($log['payment_date']); ?></td>
                        <td><?php echo h(number_format($log['paid_amount_pkr'] ?? 0, 2)); ?></td>
                        <td><?php echo h($log['recipient'] ?: '-'); ?></td>
                        <td><?php echo h($log['notes'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
             <?php else: ?><p>کوئی مکمل شدہ ادائیگی کا ریکارڈ نہیں ملا۔</p><?php endif; ?>

        <?php elseif ($action === 'admin' && is_admin()): ?>
            <h2>ایڈمن پینل</h2>
            <h3>قیمتیں اپ ڈیٹ کریں</h3>
            <div class="price-info">موجودہ قیمتیں: سونا: <?php echo h(number_format($current_prices['gold_price_per_gram_pkr'], 2)); ?> PKR/گرام, چاندی: <?php echo h(number_format($current_prices['silver_price_per_gram_pkr'], 2)); ?> PKR/گرام</div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=admin">
                <input type="hidden" name="form_action" value="update_prices">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <div class="input-group"><label for="gold_price">سونا (PKR/گرام):</label><input type="number" step="any" min="0.01" id="gold_price" name="gold_price" value="<?php echo h($current_prices['gold_price_per_gram_pkr']); ?>" required aria-label="سونے کی نئی قیمت"></div>
                <div class="input-group"><label for="silver_price">چاندی (PKR/گرام):</label><input type="number" step="any" min="0.01" id="silver_price" name="silver_price" value="<?php echo h($current_prices['silver_price_per_gram_pkr']); ?>" required aria-label="چاندی کی نئی قیمت"></div>
                <button type="submit">اپ ڈیٹ کریں</button>
            </form>

            <h3>صارفین</h3>
            <div class="table-responsive">
            <table><thead><tr><th>ID</th><th>صارف نام</th><th>کردار</th><th>اگلی زکوٰۃ تاریخ</th></tr></thead><tbody>
                <?php if (!empty($users_list)): foreach ($users_list as $user): ?><tr>
                    <td><?php echo h($user['id']); ?></td><td><?php echo h($user['username']); ?></td><td><?php echo h($user['role']); ?></td>
                    <td <?php echo ($user['zakat_due_date'] && date('Y-m-d') >= $user['zakat_due_date']) ? 'class="highlight-due"' : ''; ?>><?php echo h($user['zakat_due_date'] ?: '-'); ?></td>
                </tr><?php endforeach; else: ?><tr><td colspan="4">کوئی صارف نہیں ملا۔</td></tr><?php endif; ?>
            </tbody></table></div>

            <h3>تمام زکوٰۃ لاگز <a href="?action=admin&export=csv" class="button button-secondary" style="font-size:0.8em; padding: 0.2rem 0.5rem; margin-right:10px; vertical-align: middle;">CSV ایکسپورٹ</a></h3>
            <div class="table-responsive">
            <table><thead><tr><th>ID</th><th>صارف</th><th>حساب</th><th>واجب</th><th>ادائیگی</th><th>ادا شدہ</th><th>تفصیلات</th></tr></thead><tbody>
                 <?php if (!empty($all_logs)): foreach ($all_logs as $log): ?><tr>
                    <td><?php echo h($log['id']); ?></td><td><?php echo h($log['username'] ?? 'حذف شدہ'); ?></td><td><?php echo h(date('Y-m-d H:i', strtotime($log['calculation_date']))); ?></td>
                    <td><?php echo h(number_format($log['zakat_due_pkr'], 2)); ?></td>
                    <td <?php echo ($log['nisab_met'] && !$log['payment_date']) ? 'class="highlight-due"' : ''; ?>><?php echo h($log['payment_date'] ?: ($log['nisab_met'] ? 'باقیہ' : '-')); ?></td>
                    <td><?php echo h($log['paid_amount_pkr'] !== null ? number_format($log['paid_amount_pkr'], 2) : '-'); ?></td>
                    <td><button class="button-link" onclick="var det = this.nextElementSibling; det.style.display = det.style.display === 'none' ? 'block' : 'none';">دکھائیں/چھپائیں</button><span class="small-text" style="display:none; margin-top: 5px; background:#eee; padding:5px; border-radius:3px; display: block; text-align:right;">
                        نصاب پورا ہوا: <?php echo $log['nisab_met'] ? 'ہاں' : 'نہیں'; ?><br>
                        کل مالیت: <?php echo number_format($log['total_assets_value_pkr'],0);?><br>
                        زکوٰۃ بیس: <?php echo number_format($log['zakat_base_pkr'],0);?><br>
                        نصاب حد: <?php echo number_format($log['nisab_value_pkr'],0);?> (<?php echo h($log['nisab_type']);?>)<br>
                        سونا:<?php echo number_format($log['gold_grams'],1);?>g, چاندی:<?php echo number_format($log['silver_grams'],1);?>g<br>
                        نقدی:<?php echo number_format($log['cash_pkr'],0);?>, کاروبار:<?php echo number_format($log['business_assets_pkr'],0);?><br>
                        وصول کنندہ: <?php echo h($log['recipient'] ?: '-'); ?><br>
                        نوٹس: <?php echo h($log['notes'] ?: '-'); ?>
                        </span></td>
                 </tr><?php endforeach; else: ?><tr><td colspan="7">کوئی لاگز نہیں ملے۔</td></tr><?php endif; ?>
            </tbody></table></div>

        <?php else: ?>
             <?php // Fallback for unknown state or invalid action ?>
             <p>صفحہ نہیں ملا یا آپ کے پاس اس تک رسائی نہیں ہے۔ براہ کرم <a href="?action=home">ہوم پیج</a> پر جائیں۔</p>
        <?php endif; ?>

    </div> <?php // End container ?>
    <?php // Simple script to toggle payment form visibility more reliably
    // Can be enhanced with better event delegation if needed ?>
    <script>
      document.addEventListener('click', function(event) {
        if (event.target.matches('.action-buttons .button-link')) {
          let button = event.target;
          let logId = button.closest('tr').querySelector('td:first-child').textContent; // Get log ID from first cell
          let paymentFormRow = document.getElementById('payment_form_' + logId);
          if (paymentFormRow) {
            let isHidden = paymentFormRow.style.display === 'none';
            paymentFormRow.style.display = isHidden ? 'table-row' : 'none';
            button.textContent = isHidden ? 'منسوخ کریں' : 'ادائیگی لاگ کریں';
          }
        }
        if (event.target.matches('td > .button-link')) { // For admin details toggle
            let detailSpan = event.target.nextElementSibling;
             if (detailSpan && detailSpan.classList.contains('small-text')) {
                 detailSpan.style.display = detailSpan.style.display === 'none' ? 'block' : 'none';
             }
        }
      });
    </script>
</body>
</html>
