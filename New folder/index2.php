
<?php
session_start();

class Zakat {
    private $db;

    public function __construct() {
        try {
            $this->db = new PDO('sqlite:zakat.db');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeDatabase();
        } catch (PDOException $e) {
            die('خطا: ' . $e->getMessage());
        }
    }

    private function initializeDatabase() {
        $this->db->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        $this->db->exec('CREATE TABLE IF NOT EXISTS zakat_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            gold_tola REAL,
            silver_tola REAL,
            cash_amount REAL,
            business_goods_value REAL,
            silver_price_per_tola REAL,
            total_wealth REAL,
            zakat_amount REAL,
            payment_date DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )');

        $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        if ($stmt->fetchColumn() == 0) {
            $this->createDefaultAdmin();
        }
    }

    private function createDefaultAdmin() {
        $stmt = $this->db->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    }

    public function registerUser($username, $password, $role = 'user') {
        try {
            $stmt = $this->db->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function loginUser($username, $password) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                return $user;
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function calculateZakat($goldTola, $silverTola, $cashAmount, $businessGoodsValue, $silverPricePerTola) {
        $totalWealth = 0;
        $zakatAmount = 0;
        $isZakatDue = false;

        $goldNisab = 7.5;
        $silverNisab = 52.5;
        $combinedNisab = $silverNisab * $silverPricePerTola;

        if ($goldTola >= $goldNisab && $silverTola == 0 && $cashAmount == 0 && $businessGoodsValue == 0) {
            $isZakatDue = true;
            $totalWealth = $goldTola * $silverPricePerTola;
        } elseif ($silverTola >= $silverNisab && $goldTola == 0 && $cashAmount == 0 && $businessGoodsValue == 0) {
            $isZakatDue = true;
            $totalWealth = $silverTola * $silverPricePerTola;
        } else {
            $totalWealth = ($goldTola * $silverPricePerTola) + ($silverTola * $silverPricePerTola) + $cashAmount + $businessGoodsValue;
            if ($totalWealth >= $combinedNisab) {
                $isZakatDue = true;
            }
        }

        if ($isZakatDue) {
            $zakatAmount = $totalWealth * 0.025;
        }

        return [
            'totalWealth' => $totalWealth,
            'zakatAmount' => $zakatAmount,
            'isZakatDue' => $isZakatDue,
            'silverNisabValue' => $combinedNisab
        ];
    }

    public function recordPayment($userId, $goldTola, $silverTola, $cashAmount, $businessGoodsValue, $silverPricePerTola, $totalWealth, $zakatAmount) {
        try {
            $stmt = $this->db->prepare('INSERT INTO zakat_payments (user_id, gold_tola, silver_tola, cash_amount, business_goods_value, silver_price_per_tola, total_wealth, zakat_amount, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $goldTola, $silverTola, $cashAmount, $businessGoodsValue, $silverPricePerTola, $totalWealth, $zakatAmount, date('Y-m-d H:i:s')]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getPaymentHistory($userId = null) {
        try {
            if ($userId) {
                $stmt = $this->db->prepare('SELECT * FROM zakat_payments WHERE user_id = ? ORDER BY payment_date DESC');
                $stmt->execute([$userId]);
            } else {
                $stmt = $this->db->query('SELECT z.*, u.username FROM zakat_payments z LEFT JOIN users u ON z.user_id = u.id ORDER BY z.payment_date DESC');
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function getAllUsers() {
        try {
            $stmt = $this->db->query('SELECT id, username, role, created_at FROM users ORDER BY created_at DESC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function deleteUser($userId) {
        try {
            $stmt = $this->db->prepare('DELETE FROM zakat_payments WHERE user_id = ?');
            $stmt->execute([$userId]);
            
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function deletePayment($paymentId) {
        try {
            $stmt = $this->db->prepare('DELETE FROM zakat_payments WHERE id = ?');
            $stmt->execute([$paymentId]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function updateSilverPrice($price) {
        try {
            $file = fopen('silver_price.txt', 'w');
            fwrite($file, $price);
            fclose($file);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function getCurrentSilverPrice() {
        if (file_exists('silver_price.txt')) {
            return floatval(file_get_contents('silver_price.txt'));
        }
        return 0;
    }
}

$zakat = new Zakat();
$message = '';
$result = null;
$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$isAdmin = $currentUser && $currentUser['role'] === 'admin';
$isUser = $currentUser && $currentUser['role'] === 'user';
$currentSilverPrice = $zakat->getCurrentSilverPrice();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $user = $zakat->loginUser($_POST['username'], $_POST['password']);
        if ($user) {
            $_SESSION['user'] = $user;
            $currentUser = $user;
            $isAdmin = $currentUser['role'] === 'admin';
            $isUser = $currentUser['role'] === 'user';
            $message = 'لاگ ان کامیاب رہا۔';
        } else {
            $message = 'غلط صارف نام یا پاس ورڈ۔';
        }
    } elseif (isset($_POST['register'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        if (strlen($username) < 3) {
            $message = 'صارف نام کم از کم 3 حروف پر مشتمل ہونا چاہیے۔';
        } elseif (strlen($password) < 6) {
            $message = 'پاس ورڈ کم از کم 6 حروف پر مشتمل ہونا چاہیے۔';
        } else {
            $role = isset($_POST['role']) && $isAdmin ? $_POST['role'] : 'user';
            if ($zakat->registerUser($username, $password, $role)) {
                $message = 'اکاؤنٹ کامیابی کے ساتھ بنا دیا گیا ہے۔';
            } else {
                $message = 'رجسٹریشن میں خطا۔ ممکن ہے یہ صارف نام پہلے سے موجود ہو۔';
            }
        }
    } elseif (isset($_POST['logout'])) {
        unset($_SESSION['user']);
        unset($_SESSION['calculation_result']);
        unset($_SESSION['form_data']);
        $currentUser = null;
        $isAdmin = false;
        $isUser = false;
        $message = 'آپ کامیابی سے لاگ آؤٹ ہو گئے ہیں۔';
    } elseif (isset($_POST['calculate'])) {
        $goldTola = floatval($_POST['gold_tola'] ?? 0);
        $silverTola = floatval($_POST['silver_tola'] ?? 0);
        $cashAmount = floatval($_POST['cash_amount'] ?? 0);
        $businessGoodsValue = floatval($_POST['business_goods_value'] ?? 0);
        $silverPricePerTola = floatval($_POST['silver_price_per_tola'] ?? 0);

        $result = $zakat->calculateZakat($goldTola, $silverTola, $cashAmount, $businessGoodsValue, $silverPricePerTola);
        $_SESSION['calculation_result'] = $result;
        $_SESSION['form_data'] = [
            'gold_tola' => $goldTola,
            'silver_tola' => $silverTola,
            'cash_amount' => $cashAmount,
            'business_goods_value' => $businessGoodsValue,
            'silver_price_per_tola' => $silverPricePerTola,
        ];
    } elseif (isset($_POST['record_payment']) && isset($_SESSION['calculation_result']) && isset($_SESSION['form_data']) && $currentUser) {
        $result = $_SESSION['calculation_result'];
        $formData = $_SESSION['form_data'];
        
        if ($zakat->recordPayment(
            $currentUser['id'],
            $formData['gold_tola'],
            $formData['silver_tola'],
            $formData['cash_amount'],
            $formData['business_goods_value'],
            $formData['silver_price_per_tola'],
            $result['totalWealth'],
            $result['zakatAmount']
        )) {
            $message = 'زکوٰۃ کی ادائیگی کامیابی سے ریکارڈ کی گئی۔';
            unset($_SESSION['calculation_result']);
            unset($_SESSION['form_data']);
            $result = null;
        } else {
            $message = 'زکوٰۃ کی ادائیگی ریکارڈ کرنے میں خطا۔';
        }
    } elseif (isset($_POST['delete_user']) && $isAdmin) {
        $userId = intval($_POST['user_id']);
        if ($zakat->deleteUser($userId)) {
            $message = 'صارف کو کامیابی سے حذف کر دیا گیا۔';
        } else {
            $message = 'صارف کو حذف کرنے میں خطا۔';
        }
    } elseif (isset($_POST['delete_payment']) && ($isAdmin || $isUser)) {
        $paymentId = intval($_POST['payment_id']);
        if ($zakat->deletePayment($paymentId)) {
            $message = 'ادائیگی کو کامیابی سے حذف کر دیا گیا۔';
        } else {
            $message = 'ادائیگی حذف کرنے میں خطا۔';
        }
    } elseif (isset($_POST['update_silver_price']) && $isAdmin) {
        $price = floatval($_POST['silver_price']);
        if ($zakat->updateSilverPrice($price)) {
            $currentSilverPrice = $price;
            $message = 'چاندی کی قیمت کامیابی سے اپڈیٹ ہو گئی۔';
        } else {
            $message = 'چاندی کی قیمت اپڈیٹ کرنے میں خطا۔';
        }
    }
}

$paymentHistory = [];
if ($isAdmin) {
    $paymentHistory = $zakat->getPaymentHistory();
    $allUsers = $zakat->getAllUsers();
} elseif ($isUser) {
    $paymentHistory = $zakat->getPaymentHistory($currentUser['id']);
}
?>
<!DOCTYPE html>

<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>زکوٰۃ کیلکولیٹر</title>
    <style>
        body {
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
            color: #333;
        }
        h1, h2, h3 {
            color: #007bff;
            text-align: center;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"],
        input[type="number"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            text-align: right;
        }
        button, .btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px 0;
        }
        button:hover, .btn:hover {
            background-color: #0069d9;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9f7ef;
            border-radius: 4px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            flex: 1;
            text-align: center;
        }
        .tab.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .user-panel {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .auth-forms {
            display: flex;
            gap: 20px;
        }
        .auth-form {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>زکوٰۃ کیلکولیٹر</h1>
        
        <div class="user-panel">
            <?php if ($currentUser): ?>
                <div>
                    <strong>خوش آمدید، <?php echo htmlspecialchars($currentUser['username']); ?></strong>
                    <span>(<?php echo $currentUser['role'] === 'admin' ? 'منتظم' : 'صارف'; ?>)</span>
                </div>
                <form method="post" style="margin: 0;">
                    <button type="submit" name="logout" class="btn btn-danger">لاگ آؤٹ</button>
                </form>
            <?php else: ?>
                <div>آپ لاگ ان نہیں ہیں</div>
                <button onclick="openTab('auth')" class="btn">لاگ ان / رجسٹر</button>
            <?php endif; ?>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="openTab('calculator')">زکوٰۃ کیلکولیٹر</div>
            <?php if ($currentUser): ?>
                <div class="tab" onclick="openTab('history')">ادائیگیوں کی تاریخ</div>
                <?php if ($isAdmin): ?>
                    <div class="tab" onclick="openTab('users')">صارفین</div>
                    <div class="tab" onclick="openTab('settings')">ترتیبات</div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!$currentUser): ?>
                <div class="tab" onclick="openTab('auth')">لاگ ان / رجسٹر</div>
            <?php endif; ?>
        </div>
        
        <div id="calculator" class="tab-content active">
            <h2>زکوٰۃ کیلکولیٹر</h2>
            
            <form method="post">
                <div class="form-group">
                    <label for="silver_price_per_tola">چاندی کی فی تولہ قیمت (روپے میں)</label>
                    <input type="number" id="silver_price_per_tola" name="silver_price_per_tola" step="0.01" min="0" required 
                           value="<?php echo isset($_SESSION['form_data']['silver_price_per_tola']) ? $_SESSION['form_data']['silver_price_per_tola'] : $currentSilverPrice; ?>">
                    <?php if ($currentSilverPrice > 0): ?>
                        <small>حالیہ قیمت: <?php echo number_format($currentSilverPrice, 2); ?> روپے</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="gold_tola">سونے کی مقدار (تولے میں)</label>
                    <input type="number" id="gold_tola" name="gold_tola" step="0.01" min="0" required value="<?php echo isset($_SESSION['form_data']['gold_tola']) ? $_SESSION['form_data']['gold_tola'] : '0'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="silver_tola">چاندی کی مقدار (تولے میں)</label>
                    <input type="number" id="silver_tola" name="silver_tola" step="0.01" min="0" required value="<?php echo isset($_SESSION['form_data']['silver_tola']) ? $_SESSION['form_data']['silver_tola'] : '0'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="cash_amount">نقد رقم (روپے میں)</label>
                    <input type="number" id="cash_amount" name="cash_amount" step="0.01" min="0" required value="<?php echo isset($_SESSION['form_data']['cash_amount']) ? $_SESSION['form_data']['cash_amount'] : '0'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="business_goods_value">تجارتی سامان کی قیمت (روپے میں)</label>
                    <input type="number" id="business_goods_value" name="business_goods_value" step="0.01" min="0" required value="<?php echo isset($_SESSION['form_data']['business_goods_value']) ? $_SESSION['form_data']['business_goods_value'] : '0'; ?>">
                </div>
                
                <button type="submit" name="calculate">زکوٰۃ کا حساب کریں</button>
            </form>
            
            <?php if ($result): ?>
                <div class="result">
                    <h3>زکوٰۃ کا حساب</h3>
                    <p>نصاب (52.5 تولا چاندی کی قیمت): <?php echo number_format($result['silverNisabValue'], 2); ?> روپے</p>
                    <p>کل دولت: <?php echo number_format($result['totalWealth'], 2); ?> روپے</p>
                    
                    <?php if ($result['isZakatDue']): ?>
                        <p>واجب زکوٰۃ (2.5%): <?php echo number_format($result['zakatAmount'], 2); ?> روپے</p>
                        <?php if ($currentUser): ?>
                            <form method="post">
                                <button type="submit" name="record_payment">ادائیگی ریکارڈ کریں</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p>ادائیگی ریکارڈ کرنے کے لیے لاگ ان کریں۔</p>
                                <button onclick="openTab('auth')" class="btn">لاگ ان / رجسٹر</button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <p>آپ پر زکوٰۃ واجب نہیں ہے کیونکہ آپ کی کل دولت نصاب سے کم ہے۔</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($currentUser): ?>
            <div id="history" class="tab-content">
                <h2>زکوٰۃ کی ادائیگیوں کی تاریخ</h2>
                
                <?php if (empty($paymentHistory)): ?>
                    <p>ابھی تک کوئی ادائیگی ریکارڈ نہیں کی گئی ہے۔</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <?php if ($isAdmin): ?>
                                    <th>صارف</th>
                                <?php endif; ?>
                                <th>تاریخ</th>
                                <th>سونا (تولے)</th>
                                <th>چاندی (تولے)</th>
                                <th>نقد رقم</th>
                                <th>تجارتی سامان</th>
                                <th>چاندی کی قیمت</th>
                                <th>کل دولت</th>
                                <th>زکوٰۃ کی رقم</th>
                                <th>اقدامات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paymentHistory as $payment): ?>
                                <tr>
                                    <?php if ($isAdmin): ?>
                                        <td><?php echo isset($payment['username']) ? htmlspecialchars($payment['username']) : 'نامعلوم'; ?></td>
                                    <?php endif; ?>
                                    <td><?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo number_format($payment['gold_tola'], 2); ?></td>
                                    <td><?php echo number_format($payment['silver_tola'], 2); ?></td>
                                    <td><?php echo number_format($payment['cash_amount'], 2); ?></td>
                                    <td><?php echo number_format($payment['business_goods_value'], 2); ?></td>
                                    <td><?php echo number_format($payment['silver_price_per_tola'], 2); ?></td>
                                    <td><?php echo number_format($payment['total_wealth'], 2); ?></td>
                                    <td><?php echo number_format($payment['zakat_amount'], 2); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('کیا آپ واقعی اس ادائیگی کو حذف کرنا چاہتے ہیں؟');">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <button type="submit" name="delete_payment" class="btn btn-danger">حذف کریں</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($isAdmin): ?>
            <div id="users" class="tab-content">
                <h2>صارفین کا انتظام</h2>
                
                <h3>نیا صارف بنائیں</h3>
                <form method="post">
                    <div class="form-group">
                        <label for="admin-username">صارف نام</label>
                        <input type="text" id="admin-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="admin-password">پاس ورڈ</label>
                        <input type="password" id="admin-password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="admin-role">کردار</label>
                        <select id="admin-role" name="role">
                            <option value="user">صارف</option>
                            <option value="admin">منتظم</option>
                        </select>
                    </div>
                    <button type="submit" name="register" class="btn btn-success">صارف بنائیں</button>
                </form>
                
                <h3>صارفین کی فہرست</h3>
                <?php if (empty($allUsers)): ?>
                    <p>کوئی صارف موجود نہیں ہے۔</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>آئی ڈی</th>
                                <th>صارف نام</th>
                                <th>کردار</th>
                                <th>بنانے کی تاریخ</th>
                                <th>اقدامات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo $user['role'] === 'admin' ? 'منتظم' : 'صارف'; ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['id'] != $currentUser['id']): ?>
                                            <form method="post" onsubmit="return confirm('کیا آپ واقعی اس صارف کو حذف کرنا چاہتے ہیں؟');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger">حذف کریں</button>
                                            </form>
                                        <?php else: ?>
                                            <span>موجودہ صارف</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div id="settings" class="tab-content">
                <h2>ترتیبات</h2>
                
                <h3>چاندی کی قیمت اپڈیٹ کریں</h3>
                <form method="post">
                    <div class="form-group">
                        <label for="silver-price">چاندی کی فی تولہ قیمت (روپے میں)</label>
                        <input type="number" id="silver-price" name="silver_price" step="0.01" min="0" required value="<?php echo $currentSilverPrice; ?>">
                    </div>
                    <button type="submit" name="update_silver_price" class="btn btn-success">اپڈیٹ کریں</button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if (!$currentUser): ?>
            <div id="auth" class="tab-content">
                <h2>لاگ ان / رجسٹر</h2>
                
                <div class="auth-forms">
                    <div class="auth-form">
                        <h3>لاگ ان</h3>
                        <form method="post">
                            <div class="form-group">
                                <label for="login-username">صارف نام</label>
                                <input type="text" id="login-username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="login-password">پاس ورڈ</label>
                                <input type="password" id="login-password" name="password" required>
                            </div>
                            <button type="submit" name="login" class="btn">لاگ ان کریں</button>
                        </form>
                    </div>
                    
                    <div class="auth-form">
                        <h3>رجسٹر</h3>
                        <form method="post">
                            <div class="form-group">
                                <label for="register-username">صارف نام</label>
                                <input type="text" id="register-username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="register-password">پاس ورڈ</label>
                                <input type="password" id="register-password" name="password" required>
                            </div>
                            <button type="submit" name="register" class="btn">رجسٹر کریں</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function openTab(tabName) {
        const tabs = document.getElementsByClassName("tab");
        const tabContents = document.getElementsByClassName("tab-content");
        
        for (let i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove("active");
        }
        
        for (let i = 0; i < tabContents.length; i++) {
            tabContents[i].classList.remove("active");
        }
        
        document.getElementById(tabName).classList.add("active");
        
        for (let i = 0; i < tabs.length; i++) {
            if (tabs[i].textContent.includes({
                'calculator': 'زکوٰۃ کیلکولیٹر',
                'history': 'ادائیگیوں کی تاریخ',
                'users': 'صارفین',
                'settings': 'ترتیبات',
                'auth': 'لاگ ان / رجسٹر'
            }[tabName])) {
                tabs[i].classList.add("active");
                break;
            }
        }
    }
	
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
