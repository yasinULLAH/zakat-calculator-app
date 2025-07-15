<?php
session_start();
error_reporting(0);

define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('PRICES_FILE', DATA_DIR . '/prices.json');
define('HISTORY_DIR', DATA_DIR . '/history');
define('ADMIN_USERNAME', 'admin');

define('GOLD_NISAB_TOLA', 7.5);
define('GOLD_NISAB_GRAM', 87.48);
define('SILVER_NISAB_TOLA', 52.5);
define('SILVER_NISAB_GRAM', 612.36);
define('ZAKAT_RATE', 0.025);

// Ensure data directories and files exist
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755);
if (!is_dir(HISTORY_DIR)) mkdir(HISTORY_DIR, 0755);
if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, json_encode([]));
if (!file_exists(PRICES_FILE)) file_put_contents(PRICES_FILE, json_encode(['gold_price_gram' => 0, 'gold_price_tola' => 0, 'silver_price_gram' => 0, 'silver_price_tola' => 0]));

// Helper Functions
function getUsers() {
    $content = file_get_contents(USERS_FILE);
    return json_decode($content, true) ?: [];
}

function saveUsers($users) {
    $fp = fopen(USERS_FILE, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($users, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function getPrices() {
    $content = file_get_contents(PRICES_FILE);
    return json_decode($content, true) ?: ['gold_price_gram' => 0, 'gold_price_tola' => 0, 'silver_price_gram' => 0, 'silver_price_tola' => 0];
}

function savePrices($prices) {
    $fp = fopen(PRICES_FILE, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($prices, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function getUserHistory($username) {
    $historyFile = HISTORY_DIR . '/' . basename($username) . '.json';
    if (!file_exists($historyFile)) return [];
    $content = file_get_contents($historyFile);
    return json_decode($content, true) ?: [];
}

function saveUserHistory($username, $history) {
    $historyFile = HISTORY_DIR . '/' . basename($username) . '.json';
    $fp = fopen($historyFile, 'w');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, json_encode($history, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function getUserData($username) {
    $users = getUsers();
    return $users[$username] ?? null;
}

function isAdmin() {
    return isset($_SESSION['username']) && $_SESSION['username'] === ADMIN_USERNAME;
}

function isLoggedIn() {
    return isset($_SESSION['username']);
}

function calculateZakat($assets, $prices) {
    $gold_grams = floatval($assets['gold_grams'] ?? 0);
    $gold_tolas = floatval($assets['gold_tolas'] ?? 0);
    $silver_grams = floatval($assets['silver_grams'] ?? 0);
    $silver_tolas = floatval($assets['silver_tolas'] ?? 0);
    $cash = floatval($assets['cash'] ?? 0);
    $business_assets = floatval($assets['business_assets'] ?? 0);
    $liabilities = floatval($assets['liabilities'] ?? 0);

    $gold_price_gram = floatval($prices['gold_price_gram'] ?? 0);
    $gold_price_tola = floatval($prices['gold_price_tola'] ?? 0);
    $silver_price_gram = floatval($prices['silver_price_gram'] ?? 0);
    $silver_price_tola = floatval($prices['silver_price_tola'] ?? 0);

    $total_gold_grams = $gold_grams + ($gold_tolas * 11.664); // 1 Tola approx 11.664g
    $total_silver_grams = $silver_grams + ($silver_tolas * 11.664);

    $gold_value = 0;
    if ($gold_price_gram > 0) {
        $gold_value = $total_gold_grams * $gold_price_gram;
    } elseif ($gold_price_tola > 0) {
        $gold_value = ($total_gold_grams / 11.664) * $gold_price_tola;
    }

    $silver_value = 0;
    if ($silver_price_gram > 0) {
        $silver_value = $total_silver_grams * $silver_price_gram;
    } elseif ($silver_price_tola > 0) {
        $silver_value = ($total_silver_grams / 11.664) * $silver_price_tola;
    }

    $has_gold = $total_gold_grams > 0;
    $has_silver = $total_silver_grams > 0;
    $has_other_assets = $cash > 0 || $business_assets > 0;

    $nisab_value = 0;
    $nisab_type = '';
    $total_zakatable_wealth = 0;
    $zakat_due = 0;
    $message = '';

    // Determine Nisab and Total Wealth
    if ($has_gold && !$has_silver && !$has_other_assets) {
        // Only Gold Scenario
        $nisab_type = 'Gold ('.GOLD_NISAB_GRAM.'g / '.GOLD_NISAB_TOLA.' Tola)';
        if ($total_gold_grams >= GOLD_NISAB_GRAM) {
            $total_zakatable_wealth = $gold_value;
            $nisab_value = -1; // Indicate gold nisab met by weight
            $message = 'Nisab based on Gold only ('.GOLD_NISAB_GRAM.'g).';
        } else {
             $message = 'Gold amount ('.$total_gold_grams.'g) is below the Nisab threshold ('.GOLD_NISAB_GRAM.'g).';
        }
    } elseif ($has_silver && !$has_gold && !$has_other_assets) {
        // Only Silver Scenario
        $nisab_type = 'Silver ('.SILVER_NISAB_GRAM.'g / '.SILVER_NISAB_TOLA.' Tola)';
         if ($total_silver_grams >= SILVER_NISAB_GRAM) {
             $total_zakatable_wealth = $silver_value;
             $nisab_value = -2; // Indicate silver nisab met by weight
              $message = 'Nisab based on Silver only ('.SILVER_NISAB_GRAM.'g).';
         } else {
              $message = 'Silver amount ('.$total_silver_grams.'g) is below the Nisab threshold ('.SILVER_NISAB_GRAM.'g).';
         }
    } else {
        // Mix Scenario (Gold + Anything else OR Silver + Anything else OR Only Cash/Business)
        $nisab_type = 'Silver Value ('.SILVER_NISAB_TOLA.' Tola)';
        if ($silver_price_gram > 0) {
            $nisab_value = SILVER_NISAB_GRAM * $silver_price_gram;
        } elseif ($silver_price_tola > 0) {
            $nisab_value = SILVER_NISAB_TOLA * $silver_price_tola;
        } else {
            $nisab_value = 0; // Cannot determine Nisab if silver price is zero
             $message = 'Silver price is not set. Cannot determine Nisab value.';
        }

        if ($nisab_value > 0) {
            $total_wealth = $gold_value + $silver_value + $cash + $business_assets;
            $net_wealth = $total_wealth - $liabilities;

            if ($net_wealth >= $nisab_value) {
                $total_zakatable_wealth = $net_wealth;
                 $message = 'Nisab based on Silver value (Value of '.SILVER_NISAB_TOLA.' Tola).';
            } else {
                 $message = 'Total net wealth ('.number_format($net_wealth, 2).') is below the Nisab threshold ('.number_format($nisab_value, 2).').';
            }
        }
    }

    // Calculate Zakat if Nisab met
    if ($total_zakatable_wealth > 0) {
        $zakat_due = $total_zakatable_wealth * ZAKAT_RATE;
    }

    return [
        'total_gold_grams' => $total_gold_grams,
        'total_silver_grams' => $total_silver_grams,
        'gold_value' => $gold_value,
        'silver_value' => $silver_value,
        'cash' => $cash,
        'business_assets' => $business_assets,
        'total_assets_value' => $gold_value + $silver_value + $cash + $business_assets,
        'liabilities' => $liabilities,
        'net_wealth' => $total_zakatable_wealth > 0 ? $total_zakatable_wealth : ($gold_value + $silver_value + $cash + $business_assets - $liabilities),
        'nisab_type' => $nisab_type,
        'nisab_value_required' => $nisab_value,
        'nisab_met' => $total_zakatable_wealth > 0,
        'zakat_due' => $zakat_due,
        'message' => $message,
        'calculation_date' => date('Y-m-d H:i:s'),
        'prices_used' => $prices
    ];
}


// State Variables
$action = $_REQUEST['action'] ?? 'public_calculator';
$message = '';
$error = '';
$calculation_result = null;
$default_prices = getPrices();
$user_prices = [];
$current_prices = $default_prices; // Default to admin prices

// Handle User Specific Price Loading
if (isLoggedIn()) {
    $userData = getUserData($_SESSION['username']);
    if (!empty($userData['prices'])) {
        $user_prices = $userData['prices'];
        // Override default prices with user prices if they exist and are non-zero
        foreach ($user_prices as $key => $value) {
             if (!empty($value)) {
                 $current_prices[$key] = $value;
             }
        }
    }
}


// Action Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Authentication ---
    if ($action === 'register') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $users = getUsers();
        if (empty($username) || empty($password) || empty($email)) {
            $error = 'All fields are required for registration.';
        } elseif (isset($users[$username])) {
            $error = 'Username already exists.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             $error = 'Invalid email format.';
        } else {
            $users[$username] = [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email,
                'prices' => [],
                'reminder_days' => 355 // Approx 1 year reminder default
            ];
            saveUsers($users);
            $message = 'Registration successful. Please login.';
            $action = 'login_form';
        }
    } elseif ($action === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $users = getUsers();
        if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
            $_SESSION['username'] = $username;
            header('Location: ' . $_SERVER['PHP_SELF'] . '?action=user_calculator'); // Redirect to avoid form resubmission
            exit;
        } else {
            $error = 'Invalid username or password.';
            $action = 'login_form';
        }
    } elseif ($action === 'logout') {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    // --- Price Settings ---
    elseif ($action === 'save_admin_prices' && isAdmin()) {
        $new_prices = [
            'gold_price_gram' => floatval($_POST['gold_price_gram']),
            'gold_price_tola' => floatval($_POST['gold_price_tola']),
            'silver_price_gram' => floatval($_POST['silver_price_gram']),
            'silver_price_tola' => floatval($_POST['silver_price_tola'])
        ];
        savePrices($new_prices);
        $message = 'Default prices updated successfully.';
        $default_prices = $new_prices; // Update current view
        $current_prices = $new_prices; // Update prices used for calculation if user has no overrides
        // Reload user prices potentially? No, user prices should persist unless they change them.
         if (isLoggedIn() && $_SESSION['username'] !== ADMIN_USERNAME) {
             $userData = getUserData($_SESSION['username']);
             if (!empty($userData['prices'])) {
                  $user_prices = $userData['prices'];
                  foreach ($user_prices as $key => $value) {
                        if (!empty($value)) {
                            $current_prices[$key] = $value; // Re-apply user overrides
                        }
                  }
             }
         } else {
             $current_prices = $default_prices; // Ensure admin sees updated defaults
         }

        $action = 'admin_settings'; // Stay on settings page
    } elseif ($action === 'save_user_prices' && isLoggedIn()) {
        $users = getUsers();
        $username = $_SESSION['username'];
        if (isset($users[$username])) {
            $users[$username]['prices'] = [
                'gold_price_gram' => floatval($_POST['user_gold_price_gram']),
                'gold_price_tola' => floatval($_POST['user_gold_price_tola']),
                'silver_price_gram' => floatval($_POST['user_silver_price_gram']),
                'silver_price_tola' => floatval($_POST['user_silver_price_tola'])
            ];
            saveUsers($users);
            $message = 'Your custom prices updated successfully.';
            $user_prices = $users[$username]['prices']; // Update current view
             // Recalculate current_prices based on new user prices
            $current_prices = getPrices(); // Start with defaults
            foreach ($user_prices as $key => $value) {
                 if (!empty($value)) {
                     $current_prices[$key] = $value;
                 }
            }
        } else {
            $error = 'User not found.';
        }
        $action = 'user_settings'; // Stay on settings page
    }
    // --- Calculation ---
    elseif ($action === 'calculate_public') {
        $assets = $_POST;
        $manual_prices = [
            'gold_price_gram' => floatval($_POST['manual_gold_price_gram']),
            'gold_price_tola' => floatval($_POST['manual_gold_price_tola']),
            'silver_price_gram' => floatval($_POST['manual_silver_price_gram']),
            'silver_price_tola' => floatval($_POST['manual_silver_price_tola'])
        ];

        if (($manual_prices['gold_price_gram'] <= 0 && $manual_prices['gold_price_tola'] <= 0 && (floatval($assets['gold_grams'] ?? 0) > 0 || floatval($assets['gold_tolas'] ?? 0) > 0)) ||
            ($manual_prices['silver_price_gram'] <= 0 && $manual_prices['silver_price_tola'] <= 0)) {
             $error = 'Please enter valid prices for Gold (if owned) and Silver to calculate Zakat.';
             $action = 'public_calculator';
        } else {
             $calculation_result = calculateZakat($assets, $manual_prices);
             $action = 'public_calculator'; // Show result on the same page
        }

    } elseif ($action === 'calculate_user' && isLoggedIn()) {
        $assets = $_POST;
        // Use current_prices which already factor in user overrides or defaults
         if (($current_prices['gold_price_gram'] <= 0 && $current_prices['gold_price_tola'] <= 0 && (floatval($assets['gold_grams'] ?? 0) > 0 || floatval($assets['gold_tolas'] ?? 0) > 0)) ||
            ($current_prices['silver_price_gram'] <= 0 && $current_prices['silver_price_tola'] <= 0)) {
             $error = 'Gold/Silver prices are not set. Please ask the admin to set default prices or set your own custom prices in settings.';
             $action = 'user_calculator';
         } else {
            $calculation_result = calculateZakat($assets, $current_prices);
            // Save to history automatically
            $history = getUserHistory($_SESSION['username']);
            array_unshift($history, $calculation_result); // Add to beginning
            if (count($history) > 20) { // Limit history size
                $history = array_slice($history, 0, 20);
            }
            saveUserHistory($_SESSION['username'], $history);
            $message = 'Calculation complete and saved to history.';
            $action = 'user_calculator'; // Show result on the same page
         }
    }
     // --- History Deletion ---
    elseif ($action === 'delete_history_item' && isLoggedIn()) {
        $index_to_delete = $_POST['index'] ?? null;
        if ($index_to_delete !== null) {
             $username = $_SESSION['username'];
             $history = getUserHistory($username);
             if (isset($history[$index_to_delete])) {
                 array_splice($history, $index_to_delete, 1);
                 saveUserHistory($username, $history);
                 $message = 'History item deleted.';
             } else {
                 $error = 'Invalid history item index.';
             }
        } else {
             $error = 'No history item index specified for deletion.';
        }
        $action = 'history'; // Refresh history view
    }
     // --- Reminder Settings ---
     elseif ($action === 'save_reminder_settings' && isLoggedIn()) {
         $users = getUsers();
         $username = $_SESSION['username'];
         if (isset($users[$username])) {
             $days = intval($_POST['reminder_days']);
             if ($days > 0 && $days <= 366) {
                 $users[$username]['reminder_days'] = $days;
                 saveUsers($users);
                 $message = 'Reminder settings updated.';
             } else {
                  $error = 'Invalid number of days. Please enter a value between 1 and 366.';
             }
         } else {
             $error = 'User not found.';
         }
         $action = 'user_settings';
     }


} else {
    // GET Request or initial load
    // Clear calculation result if navigating away
    $calculation_result = null;
}

// Check for Reminder Alert
$reminder_alert = '';
if (isLoggedIn() && ($action === 'user_calculator' || $action === 'history')) {
    $userData = getUserData($_SESSION['username']);
    $history = getUserHistory($_SESSION['username']);
    if ($userData && !empty($history)) {
        $lastCalculationDate = strtotime($history[0]['calculation_date']);
        $reminderDays = $userData['reminder_days'] ?? 355; // Default to ~1 year
        $reminderThreshold = strtotime("-$reminderDays days");
        if ($lastCalculationDate <= $reminderThreshold) {
            $nextDueDate = date('Y-m-d', strtotime("+$reminderDays days", $lastCalculationDate));
            $reminder_alert = "Reminder: Your last Zakat calculation was on " . date('Y-m-d', $lastCalculationDate) . ". Consider calculating your Zakat again (around " . $nextDueDate . ").";
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zakat Calculator</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #0056b3; }
        nav { background: #0056b3; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        nav a { color: white; padding: 10px 15px; text-decoration: none; display: inline-block; }
        nav a:hover { background: #004494; border-radius: 3px;}
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="password"], input[type="email"] { width: calc(100% - 22px); padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="number"] { -moz-appearance: textfield; } /* Firefox */
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; } /* Chrome, Safari, Edge, Opera */
        button { background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #218838; }
        button.delete { background-color: #dc3545; }
        button.delete:hover { background-color: #c82333; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.reminder { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .result, .history-item { background: #e9ecef; padding: 15px; margin-top: 20px; border: 1px solid #ced4da; border-radius: 5px; }
        .result h3, .history-item h3 { margin-top: 0; }
        .result p, .history-item p { margin: 5px 0; }
        .result strong, .history-item strong { color: #0056b3; }
        .price-note { font-size: 0.9em; color: #666; margin-top: 10px; }
        .hidden { display: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px;}
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left;}
        th { background-color: #f2f2f2;}
        .inline-label { display: inline-block; width: 150px;}
    </style>
</head>
<body>
<div class="container">
    <h1>Zakat Calculator</h1>

    <nav>
        <a href="?action=public_calculator">Public Calculator</a>
        <?php if (isLoggedIn()): ?>
            <a href="?action=user_calculator">My Calculator</a>
            <a href="?action=history">History</a>
            <a href="?action=user_settings">My Settings</a>
            <?php if (isAdmin()): ?>
                <a href="?action=admin_settings">Admin Settings</a>
            <?php endif; ?>
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" style="display:inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" style="padding: 10px 15px; background-color: #dc3545; margin-left: 10px;">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</button>
            </form>
        <?php else: ?>
            <a href="?action=login_form">Login</a>
            <a href="?action=register_form">Register</a>
        <?php endif; ?>
    </nav>

    <?php if ($message): ?>
        <div class="message success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
     <?php if ($reminder_alert): ?>
        <div class="message reminder"><?= htmlspecialchars($reminder_alert) ?></div>
    <?php endif; ?>


    <?php // --- Page Content based on Action --- ?>

    <?php if ($action === 'login_form'): ?>
        <h2>Login</h2>
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a href="?action=register_form">Register here</a></p>
    <?php endif; ?>

    <?php if ($action === 'register_form'): ?>
        <h2>Register</h2>
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
             <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Register</button>
        </form>
         <p>Already have an account? <a href="?action=login_form">Login here</a></p>
    <?php endif; ?>

    <?php if ($action === 'public_calculator'): ?>
        <h2>Public Zakat Calculator</h2>
        <p>This calculator does not save your data. Please enter current market prices.</p>
        <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
            <input type="hidden" name="action" value="calculate_public">

            <h3>Asset Prices (Required for Calculation)</h3>
             <div class="form-group">
                <label for="manual_gold_price_gram">Gold Price per Gram:</label>
                <input type="number" step="0.01" min="0" id="manual_gold_price_gram" name="manual_gold_price_gram" value="<?= htmlspecialchars($_POST['manual_gold_price_gram'] ?? '') ?>" required>
            </div>
             <div class="form-group">
                 <label for="manual_gold_price_tola">OR Gold Price per Tola:</label>
                 <input type="number" step="0.01" min="0" id="manual_gold_price_tola" name="manual_gold_price_tola" value="<?= htmlspecialchars($_POST['manual_gold_price_tola'] ?? '') ?>">
                 <small>(If both gram and tola prices are entered, gram price will be used for gold.)</small>
             </div>
            <div class="form-group">
                <label for="manual_silver_price_gram">Silver Price per Gram:</label>
                <input type="number" step="0.01" min="0" id="manual_silver_price_gram" name="manual_silver_price_gram" value="<?= htmlspecialchars($_POST['manual_silver_price_gram'] ?? '') ?>" required>
            </div>
             <div class="form-group">
                 <label for="manual_silver_price_tola">OR Silver Price per Tola:</label>
                 <input type="number" step="0.01" min="0" id="manual_silver_price_tola" name="manual_silver_price_tola" value="<?= htmlspecialchars($_POST['manual_silver_price_tola'] ?? '') ?>">
                 <small>(If both gram and tola prices are entered, gram price will be used for silver.)</small>
            </div>

            <h3>Your Assets</h3>
             <div class="form-group">
                 <label for="gold_grams">Gold (grams):</label>
                 <input type="number" step="0.01" min="0" id="gold_grams" name="gold_grams" value="<?= htmlspecialchars($_POST['gold_grams'] ?? '0') ?>">
             </div>
             <div class="form-group">
                 <label for="gold_tolas">Gold (tolas):</label>
                 <input type="number" step="0.01" min="0" id="gold_tolas" name="gold_tolas" value="<?= htmlspecialchars($_POST['gold_tolas'] ?? '0') ?>">
             </div>
             <div class="form-group">
                 <label for="silver_grams">Silver (grams):</label>
                 <input type="number" step="0.01" min="0" id="silver_grams" name="silver_grams" value="<?= htmlspecialchars($_POST['silver_grams'] ?? '0') ?>">
             </div>
             <div class="form-group">
                 <label for="silver_tolas">Silver (tolas):</label>
                 <input type="number" step="0.01" min="0" id="silver_tolas" name="silver_tolas" value="<?= htmlspecialchars($_POST['silver_tolas'] ?? '0') ?>">
             </div>
             <div class="form-group">
                 <label for="cash">Cash (on hand and in bank accounts):</label>
                 <input type="number" step="0.01" min="0" id="cash" name="cash" value="<?= htmlspecialchars($_POST['cash'] ?? '0') ?>">
             </div>
             <div class="form-group">
                 <label for="business_assets">Value of Business Goods/Stock for Sale:</label>
                 <input type="number" step="0.01" min="0" id="business_assets" name="business_assets" value="<?= htmlspecialchars($_POST['business_assets'] ?? '0') ?>">
             </div>
             <div class="form-group">
                 <label for="liabilities">Short-Term Liabilities (debts due within one year):</label>
                 <input type="number" step="0.01" min="0" id="liabilities" name="liabilities" value="<?= htmlspecialchars($_POST['liabilities'] ?? '0') ?>">
             </div>

            <button type="submit">Calculate Zakat</button>
        </form>

        <?php if ($calculation_result && $_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'calculate_public'): ?>
        <div class="result">
            <h3>Calculation Result</h3>
            <p><?= htmlspecialchars($calculation_result['message']) ?></p>
            <table>
                 <tr><th>Asset Type</th><th>Amount/Value</th></tr>
                 <tr><td>Total Gold</td><td><?= number_format($calculation_result['total_gold_grams'], 2) ?> grams</td></tr>
                 <tr><td>Total Silver</td><td><?= number_format($calculation_result['total_silver_grams'], 2) ?> grams</td></tr>
                 <tr><td>Value of Gold</td><td><?= number_format($calculation_result['gold_value'], 2) ?></td></tr>
                 <tr><td>Value of Silver</td><td><?= number_format($calculation_result['silver_value'], 2) ?></td></tr>
                 <tr><td>Cash</td><td><?= number_format($calculation_result['cash'], 2) ?></td></tr>
                 <tr><td>Business Assets</td><td><?= number_format($calculation_result['business_assets'], 2) ?></td></tr>
                 <tr><td><strong>Total Assets Value</strong></td><td><strong><?= number_format($calculation_result['total_assets_value'], 2) ?></strong></td></tr>
                 <tr><td>Liabilities</td><td><?= number_format($calculation_result['liabilities'], 2) ?></td></tr>
                 <tr><td><strong>Net Wealth Considered</strong></td><td><strong><?= number_format($calculation_result['net_wealth'], 2) ?></strong></td></tr>
                 <tr><td>Nisab Type Used</td><td><?= htmlspecialchars($calculation_result['nisab_type']) ?></td></tr>
                 <?php if ($calculation_result['nisab_value_required'] > 0): ?>
                 <tr><td>Nisab Threshold Value</td><td><?= number_format($calculation_result['nisab_value_required'], 2) ?></td></tr>
                 <?php endif; ?>
                 <tr><td>Nisab Met?</td><td><?= $calculation_result['nisab_met'] ? 'Yes' : 'No' ?></td></tr>
                 <tr><td><strong>Zakat Due (2.5%)</strong></td><td><strong><?= number_format($calculation_result['zakat_due'], 2) ?></strong></td></tr>
             </table>
             <p><small>Calculated on: <?= $calculation_result['calculation_date'] ?></small></p>
             <p><small>Prices Used: Gold/g: <?= $calculation_result['prices_used']['gold_price_gram'] ?: 'N/A' ?>, Gold/tola: <?= $calculation_result['prices_used']['gold_price_tola'] ?: 'N/A' ?>, Silver/g: <?= $calculation_result['prices_used']['silver_price_gram'] ?: 'N/A' ?>, Silver/tola: <?= $calculation_result['prices_used']['silver_price_tola'] ?: 'N/A' ?></small></p>
        </div>
        <?php endif; ?>

    <?php endif; // end public_calculator ?>


    <?php if (isLoggedIn()): ?>

        <?php if ($action === 'user_calculator'): ?>
            <h2>My Zakat Calculator</h2>
            <p>Assalam-o-alaikum, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
            <p>This calculator uses the latest price settings. You can override the default prices in 'My Settings'.</p>
             <div class="price-note">
                <strong>Currently Used Prices:</strong><br>
                Gold: <?= number_format($current_prices['gold_price_gram'], 2) ?>/gram OR <?= number_format($current_prices['gold_price_tola'], 2) ?>/tola<br>
                Silver: <?= number_format($current_prices['silver_price_gram'], 2) ?>/gram OR <?= number_format($current_prices['silver_price_tola'], 2) ?>/tola<br>
                 <small>(Gram price takes precedence if both are set. Nisab is based on Silver value unless only Gold/Silver is owned.)</small>
            </div>

            <?php if (($current_prices['gold_price_gram'] <= 0 && $current_prices['gold_price_tola'] <= 0) || ($current_prices['silver_price_gram'] <= 0 && $current_prices['silver_price_tola'] <= 0)) : ?>
                 <div class="message error">Warning: Gold or Silver prices are not set. Calculation might be inaccurate or impossible. Please set prices in 'My Settings' or ask the Admin to set defaults.</div>
            <?php endif; ?>


            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                <input type="hidden" name="action" value="calculate_user">

                <h3>Your Assets</h3>
                <div class="form-group">
                    <label for="gold_grams">Gold (grams):</label>
                    <input type="number" step="0.01" min="0" id="gold_grams" name="gold_grams" value="<?= htmlspecialchars($_POST['gold_grams'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label for="gold_tolas">Gold (tolas):</label>
                    <input type="number" step="0.01" min="0" id="gold_tolas" name="gold_tolas" value="<?= htmlspecialchars($_POST['gold_tolas'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label for="silver_grams">Silver (grams):</label>
                    <input type="number" step="0.01" min="0" id="silver_grams" name="silver_grams" value="<?= htmlspecialchars($_POST['silver_grams'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label for="silver_tolas">Silver (tolas):</label>
                    <input type="number" step="0.01" min="0" id="silver_tolas" name="silver_tolas" value="<?= htmlspecialchars($_POST['silver_tolas'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label for="cash">Cash (on hand and in bank accounts):</label>
                    <input type="number" step="0.01" min="0" id="cash" name="cash" value="<?= htmlspecialchars($_POST['cash'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label for="business_assets">Value of Business Goods/Stock for Sale:</label>
                    <input type="number" step="0.01" min="0" id="business_assets" name="business_assets" value="<?= htmlspecialchars($_POST['business_assets'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label for="liabilities">Short-Term Liabilities (debts due within one year):</label>
                    <input type="number" step="0.01" min="0" id="liabilities" name="liabilities" value="<?= htmlspecialchars($_POST['liabilities'] ?? '0') ?>">
                </div>

                <button type="submit">Calculate & Save Zakat</button>
            </form>

             <?php if ($calculation_result && $_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'calculate_user'): ?>
             <div class="result">
                 <h3>Calculation Result</h3>
                  <p><?= htmlspecialchars($calculation_result['message']) ?></p>
                 <table>
                     <tr><th>Asset Type</th><th>Amount/Value</th></tr>
                     <tr><td>Total Gold</td><td><?= number_format($calculation_result['total_gold_grams'], 2) ?> grams</td></tr>
                     <tr><td>Total Silver</td><td><?= number_format($calculation_result['total_silver_grams'], 2) ?> grams</td></tr>
                     <tr><td>Value of Gold</td><td><?= number_format($calculation_result['gold_value'], 2) ?></td></tr>
                     <tr><td>Value of Silver</td><td><?= number_format($calculation_result['silver_value'], 2) ?></td></tr>
                     <tr><td>Cash</td><td><?= number_format($calculation_result['cash'], 2) ?></td></tr>
                     <tr><td>Business Assets</td><td><?= number_format($calculation_result['business_assets'], 2) ?></td></tr>
                     <tr><td><strong>Total Assets Value</strong></td><td><strong><?= number_format($calculation_result['total_assets_value'], 2) ?></strong></td></tr>
                     <tr><td>Liabilities</td><td><?= number_format($calculation_result['liabilities'], 2) ?></td></tr>
                     <tr><td><strong>Net Wealth Considered</strong></td><td><strong><?= number_format($calculation_result['net_wealth'], 2) ?></strong></td></tr>
                      <tr><td>Nisab Type Used</td><td><?= htmlspecialchars($calculation_result['nisab_type']) ?></td></tr>
                      <?php if ($calculation_result['nisab_value_required'] > 0): ?>
                      <tr><td>Nisab Threshold Value</td><td><?= number_format($calculation_result['nisab_value_required'], 2) ?></td></tr>
                      <?php endif; ?>
                     <tr><td>Nisab Met?</td><td><?= $calculation_result['nisab_met'] ? 'Yes' : 'No' ?></td></tr>
                     <tr><td><strong>Zakat Due (2.5%)</strong></td><td><strong><?= number_format($calculation_result['zakat_due'], 2) ?></strong></td></tr>
                 </table>
                  <p><small>Calculated on: <?= $calculation_result['calculation_date'] ?></small></p>
                  <p><small>Prices Used: Gold/g: <?= $calculation_result['prices_used']['gold_price_gram'] ?: 'N/A' ?>, Gold/tola: <?= $calculation_result['prices_used']['gold_price_tola'] ?: 'N/A' ?>, Silver/g: <?= $calculation_result['prices_used']['silver_price_gram'] ?: 'N/A' ?>, Silver/tola: <?= $calculation_result['prices_used']['silver_price_tola'] ?: 'N/A' ?></small></p>
             </div>
             <?php endif; ?>

        <?php endif; // end user_calculator ?>

        <?php if ($action === 'history'): ?>
            <h2>My Calculation History</h2>
            <?php
            $history = getUserHistory($_SESSION['username']);
            if (empty($history)): ?>
                <p>You have no saved calculations yet.</p>
            <?php else: ?>
                <?php foreach ($history as $index => $calc): ?>
                    <div class="history-item">
                        <h3>Calculation from: <?= htmlspecialchars($calc['calculation_date']) ?></h3>
                         <p><?= htmlspecialchars($calc['message']) ?></p>
                        <p><strong>Net Wealth Considered:</strong> <?= number_format($calc['net_wealth'], 2) ?></p>
                         <?php if ($calc['nisab_value_required'] > 0): ?>
                         <p><strong>Nisab Threshold Value:</strong> <?= number_format($calc['nisab_value_required'], 2) ?> (<?= htmlspecialchars($calc['nisab_type']) ?>)</p>
                         <?php else: ?>
                          <p><strong>Nisab Type:</strong> <?= htmlspecialchars($calc['nisab_type']) ?></p>
                          <?php endif; ?>
                         <p><strong>Nisab Met:</strong> <?= $calc['nisab_met'] ? 'Yes' : 'No' ?></p>
                        <p><strong>Zakat Due:</strong> <?= number_format($calc['zakat_due'], 2) ?></p>
                         <p><small>Details: Gold: <?=number_format($calc['total_gold_grams'], 2)?>g, Silver: <?=number_format($calc['total_silver_grams'], 2)?>g, Cash: <?=number_format($calc['cash'], 2)?>, Business: <?=number_format($calc['business_assets'], 2)?>, Liab: <?=number_format($calc['liabilities'], 2)?></small></p>
                         <p><small>Prices Used: G/g: <?= $calc['prices_used']['gold_price_gram'] ?: 'N/A' ?>, G/t: <?= $calc['prices_used']['gold_price_tola'] ?: 'N/A' ?>, S/g: <?= $calc['prices_used']['silver_price_gram'] ?: 'N/A' ?>, S/t: <?= $calc['prices_used']['silver_price_tola'] ?: 'N/A' ?></small></p>
                         <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post" style="margin-top:10px;">
                             <input type="hidden" name="action" value="delete_history_item">
                             <input type="hidden" name="index" value="<?= $index ?>">
                             <button type="submit" class="delete">Delete This Entry</button>
                         </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; // end history ?>

        <?php if ($action === 'user_settings'):
            $userData = getUserData($_SESSION['username']);
            $user_prices = $userData['prices'] ?? [];
            $reminder_days = $userData['reminder_days'] ?? 355;
         ?>
            <h2>My Settings</h2>
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                <input type="hidden" name="action" value="save_user_prices">
                <h3>My Custom Prices (Optional)</h3>
                <p>Enter your preferred prices here to override the defaults. Leave blank to use default prices set by the admin.</p>
                 <div class="form-group">
                    <label for="user_gold_price_gram">Gold Price per Gram:</label>
                    <input type="number" step="0.01" min="0" id="user_gold_price_gram" name="user_gold_price_gram" value="<?= htmlspecialchars($user_prices['gold_price_gram'] ?? '') ?>">
                </div>
                 <div class="form-group">
                     <label for="user_gold_price_tola">OR Gold Price per Tola:</label>
                     <input type="number" step="0.01" min="0" id="user_gold_price_tola" name="user_gold_price_tola" value="<?= htmlspecialchars($user_prices['gold_price_tola'] ?? '') ?>">
                     <small>(If both set, gram price is used.)</small>
                 </div>
                <div class="form-group">
                    <label for="user_silver_price_gram">Silver Price per Gram:</label>
                    <input type="number" step="0.01" min="0" id="user_silver_price_gram" name="user_silver_price_gram" value="<?= htmlspecialchars($user_prices['silver_price_gram'] ?? '') ?>">
                </div>
                 <div class="form-group">
                     <label for="user_silver_price_tola">OR Silver Price per Tola:</label>
                     <input type="number" step="0.01" min="0" id="user_silver_price_tola" name="user_silver_price_tola" value="<?= htmlspecialchars($user_prices['silver_price_tola'] ?? '') ?>">
                      <small>(If both set, gram price is used.)</small>
                 </div>
                <button type="submit">Save My Prices</button>
            </form>

             <hr style="margin: 30px 0;">

             <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                 <input type="hidden" name="action" value="save_reminder_settings">
                 <h3>Annual Reminder</h3>
                 <p>Set how many days after your last calculation you want a reminder to appear.</p>
                 <div class="form-group">
                     <label for="reminder_days">Remind me after (days):</label>
                     <input type="number" min="1" max="366" id="reminder_days" name="reminder_days" value="<?= htmlspecialchars($reminder_days) ?>" required>
                 </div>
                  <button type="submit">Save Reminder Setting</button>
             </form>

        <?php endif; // end user_settings ?>


        <?php if (isAdmin()): ?>
            <?php if ($action === 'admin_settings'): ?>
            <h2>Admin Settings - Default Prices</h2>
             <p>Set the default market prices. Users can override these in their own settings.</p>
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                <input type="hidden" name="action" value="save_admin_prices">
                <div class="form-group">
                    <label for="gold_price_gram">Default Gold Price per Gram:</label>
                    <input type="number" step="0.01" min="0" id="gold_price_gram" name="gold_price_gram" value="<?= htmlspecialchars($default_prices['gold_price_gram']) ?>" required>
                </div>
                 <div class="form-group">
                     <label for="gold_price_tola">Default Gold Price per Tola:</label>
                     <input type="number" step="0.01" min="0" id="gold_price_tola" name="gold_price_tola" value="<?= htmlspecialchars($default_prices['gold_price_tola']) ?>" required>
                 </div>
                <div class="form-group">
                    <label for="silver_price_gram">Default Silver Price per Gram:</label>
                    <input type="number" step="0.01" min="0" id="silver_price_gram" name="silver_price_gram" value="<?= htmlspecialchars($default_prices['silver_price_gram']) ?>" required>
                </div>
                 <div class="form-group">
                     <label for="silver_price_tola">Default Silver Price per Tola:</label>
                     <input type="number" step="0.01" min="0" id="silver_price_tola" name="silver_price_tola" value="<?= htmlspecialchars($default_prices['silver_price_tola']) ?>" required>
                 </div>
                <button type="submit">Save Default Prices</button>
            </form>
            <?php endif; // end admin_settings ?>
        <?php endif; // end isAdmin ?>

    <?php endif; // end isLoggedIn ?>


</div> <?php /* End Container */ ?>
<script>
    // Basic client-side validation or interactions can be added here if needed
    // Example: Prevent entering both gram and tola price for the same metal?
    // Or dynamically show which price will be used.
    // For now, keeping it server-side focused as requested.
</script>
</body>
</html>
