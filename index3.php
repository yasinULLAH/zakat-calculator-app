<?php
// phpcs:disable Generic.Files.LineLength.TooLong
// phpcs:disable Squiz.PHP.EmbeddedPhp.ContentBeforeOpen, Squiz.PHP.EmbeddedPhp.ContentAfterEnd

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Karachi');
define('DB_FILE', __DIR__ . '/zakat_app.sqlite');
define('APP_NAME', 'Personal Zakat Calculator');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'password123');
define('SESSION_NAME', 'zakat_session');
define('DEFAULT_LANG', 'en');
define('NISAB_GOLD_TOLAS', 7.5);
define('NISAB_SILVER_TOLAS', 52.5);
define('GRAMS_PER_TOLA', 11.664);

session_name(SESSION_NAME);
session_start([
    'cookie_lifetime' => 86400 * 30,
    'gc_maxlifetime' => 86400 * 30,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

$supported_languages = ['en', 'ur'];
$lang_code = isset($_SESSION['lang']) && in_array($_SESSION['lang'], $supported_languages)
    ? $_SESSION['lang']
    : (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $supported_languages)
        ? $_COOKIE['lang']
        : DEFAULT_LANG);

if (isset($_GET['lang']) && in_array($_GET['lang'], $supported_languages)) {
    $lang_code = $_GET['lang'];
    $_SESSION['lang'] = $lang_code;
    setcookie('lang', $lang_code, time() + (86400 * 365), "/");
    $query_string = $_GET;
    unset($query_string['lang']);
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($query_string);
    header('Location: ' . $redirect_url);
    exit;
}

$translations = [
    'en' => [
        'toggle_lang' => 'اردو',
        'app_title' => 'Personal Zakat Calculator & Logger',
        'home' => 'Home',
        'calculate_zakat' => 'Calculate Zakat',
        'payment_log' => 'Payment Log',
        'calculation_history' => 'Calculation History',
        'profile' => 'Profile',
        'admin_panel' => 'Admin Panel',
        'login' => 'Login',
        'register' => 'Register',
        'logout' => 'Logout',
        'welcome' => 'Welcome',
        'dashboard' => 'Dashboard',
        'username' => 'Username',
        'password' => 'Password',
        'email' => 'Email',
        'confirm_password' => 'Confirm Password',
        'register_account' => 'Register Account',
        'login_account' => 'Login to Account',
        'already_have_account' => 'Already have an account?',
        'dont_have_account' => 'Don\'t have an account?',
        'invalid_login' => 'Invalid username or password.',
        'registration_successful' => 'Registration successful. Please login.',
        'passwords_do_not_match' => 'Passwords do not match.',
        'username_taken' => 'Username already taken.',
        'email_taken' => 'Email already taken.',
        'error_occurred' => 'An error occurred. Please try again.',
        'zakat_calculation_form' => 'Zakat Calculation Form',
        'current_prices' => 'Current Market Prices',
        'gold_price' => 'Gold Price',
        'silver_price' => 'Silver Price',
        'per_tola' => 'Per Tola',
        'per_gram' => 'Per Gram',
        'use_default_prices' => 'Use Default Prices',
        'assets' => 'Assets (Zakatable)',
        'gold' => 'Gold',
        'silver' => 'Silver',
        'unit' => 'Unit',
        'tola' => 'Tola',
        'gram' => 'Gram',
        'amount' => 'Amount',
        'cash_on_hand_bank' => 'Cash (on hand, in bank accounts)',
        'business_goods_inventory' => 'Business Goods / Inventory Value',
        'liabilities_deductions' => 'Liabilities / Deductions',
        'short_term_debts' => 'Short-Term Debts (Due within 1 year)',
        'calculate' => 'Calculate',
        'zakat_calculation_results' => 'Zakat Calculation Results',
        'total_assets' => 'Total Assets Value',
        'total_liabilities' => 'Total Liabilities',
        'zakatable_wealth' => 'Net Zakatable Wealth',
        'nisab_threshold' => 'Nisab Threshold Used',
        'nisab_value_today' => 'Nisab Value (Today)',
        'zakat_due' => 'Total Zakat Due',
        'zakat_not_due' => 'Zakat is not due (below Nisab).',
        'calculation_date' => 'Calculation Date',
        'save_calculation' => 'Save Calculation',
        'calculation_saved' => 'Calculation saved successfully.',
        'error_saving_calculation' => 'Error saving calculation.',
        'log_zakat_payment' => 'Log Zakat Payment',
        'payment_date' => 'Payment Date',
        'recipient' => 'Recipient (Person/Org)',
        'status' => 'Status',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'notes' => 'Notes (Optional)',
        'log_payment' => 'Log Payment',
        'payment_logged' => 'Payment logged successfully.',
        'error_logging_payment' => 'Error logging payment.',
        'zakat_payment_history' => 'Zakat Payment History',
        'filter_by_year' => 'Filter by Year',
        'all_years' => 'All Years',
        'filter' => 'Filter',
        'date' => 'Date',
        'action' => 'Action',
        'actions' => 'Actions',
        'view' => 'View',
        'no_payments_logged' => 'No payments logged yet.',
        'zakat_calculation_history' => 'Zakat Calculation History',
        'year' => 'Year',
        'no_calculations_found' => 'No calculations found.',
        'user_profile' => 'User Profile',
        'update_profile' => 'Update Profile',
        'new_password' => 'New Password (leave blank to keep current)',
        'zakat_due_date' => 'Your Zakat Due Date (Hijri or Gregorian)',
        'profile_updated' => 'Profile updated successfully.',
        'error_updating_profile' => 'Error updating profile.',
        'incorrect_current_password' => 'Incorrect current password.',
        'zakat_reminder' => 'Zakat Reminder',
        'your_zakat_due_date_is' => 'Your Zakat is due around:',
        'please_calculate_zakat' => 'Please calculate your Zakat if you haven\'t already.',
        'set_due_date_in_profile' => 'Set your Zakat due date in your profile for reminders.',
        'admin_dashboard' => 'Admin Dashboard',
        'user_management' => 'User Management',
        'all_zakat_logs' => 'All Zakat Logs (Calculations & Payments)',
        'settings' => 'Settings',
        'manage_users' => 'Manage Users',
        'add_user' => 'Add User',
        'id' => 'ID',
        'role' => 'Role',
        'last_login' => 'Last Login',
        'created_at' => 'Created At',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'user' => 'User',
        'admin' => 'Admin',
        'edit_user' => 'Edit User',
        'update_user' => 'Update User',
        'password_leave_blank' => 'Password (leave blank to not change)',
        'user_added' => 'User added successfully.',
        'error_adding_user' => 'Error adding user.',
        'user_updated' => 'User updated successfully.',
        'error_updating_user' => 'Error updating user.',
        'user_deleted' => 'User deleted successfully.',
        'error_deleting_user' => 'Error deleting user.',
        'cannot_delete_self' => 'Cannot delete your own admin account.',
        'confirm_delete' => 'Are you sure you want to delete this user?',
        'view_all_logs' => 'View All Logs',
        'filter_by_user' => 'Filter by User',
        'all_users' => 'All Users',
        'export_csv' => 'Export CSV',
        'type' => 'Type',
        'calculation' => 'Calculation',
        'payment' => 'Payment',
        'details' => 'Details',
        'calculation_details' => 'Calculation Details',
        'payment_details' => 'Payment Details',
        'no_logs_found' => 'No logs found.',
        'application_settings' => 'Application Settings',
        'nisab_settings' => 'Nisab Settings',
        'nisab_gold_tola' => 'Nisab Gold (Tolas)',
        'nisab_silver_tola' => 'Nisab Silver (Tolas)',
        'grams_per_tola_setting' => 'Grams per Tola',
        'default_market_prices' => 'Default Market Prices (Per Gram)',
        'default_gold_price_per_gram' => 'Default Gold Price (per Gram)',
        'default_silver_price_per_gram' => 'Default Silver Price (per Gram)',
        'save_settings' => 'Save Settings',
        'settings_saved' => 'Settings saved successfully.',
        'error_saving_settings' => 'Error saving settings.',
        'info_page_title' => 'Zakat Information (Hanafi Fiqh)',
        'info_page_content' => <<<HTML
        <h2>Zakat Calculation Rules (Hanafi Fiqh - Simplified)</h2>
        <p>Zakat is one of the five pillars of Islam, an obligatory charity for eligible Muslims.</p>
        <h4>When does Zakat become obligatory (Fardh)?</h4>
        <ol>
            <li><strong>If you only possess gold:</strong> Zakat is due if you own <strong>7.5 tolas</strong> (approximately 87.48 grams) or more of gold, and have no other zakatable assets (like silver, cash, business goods). The rate is 2.5% of the gold's value.</li>
            <li><strong>If you only possess silver:</strong> Zakat is due if you own <strong>52.5 tolas</strong> (approximately 612.36 grams) or more of silver. The rate is 2.5% of the silver's value.</li>
            <li><strong>If you possess a mix of assets (gold, silver, cash, business goods):</strong>
                <ul>
                    <li>Calculate the total current market value of all your gold, silver, cash (in hand, bank accounts), and business inventory/stock.</li>
                    <li>Exclude personal use items (your house, car for personal use, clothes, essential furniture).</li>
                    <li>Deduct your immediate liabilities and short-term debts (due within one lunar year).</li>
                    <li>Compare the remaining net value (Zakatable Wealth) to the value of <strong>52.5 tolas of silver</strong> (this is the Nisab threshold in this case).</li>
                    <li>If your Zakatable Wealth equals or exceeds the value of 52.5 tolas of silver on your Zakat due date, then Zakat is obligatory.</li>
                </ul>
            </li>
            <li><strong>If you possess only cash and/or business goods:</strong> The Nisab threshold is the value of <strong>52.5 tolas of silver</strong>.</li>
        </ol>
        <h4>Key Points:</h4>
        <ul>
            <li><strong>Nisab:</strong> The minimum threshold for Zakat to be obligatory. It's either 7.5 tolas of gold OR 52.5 tolas of silver. <strong>Crucially, according to Hanafi fiqh, if you own *any* asset besides gold (e.g., even a small amount of cash or silver), the Nisab threshold used is the value of 52.5 tolas of silver.</strong> The 7.5 tola gold Nisab applies *only* if you possess *nothing* but gold.</li>
            <li><strong>Hawl (Lunar Year):</strong> Zakat is payable once a year on the wealth that has remained in possession for one full Islamic (lunar) year. You should establish a Zakat date (e.g., 1st Ramadan) and calculate annually on that date.</li>
            <li><strong>Rate:</strong> The standard Zakat rate is <strong>2.5%</strong> of your total Zakatable Wealth.</li>
            <li><strong>Calculation Day:</strong> Use the market prices of gold and silver on the specific day your Zakat becomes due for calculations.</li>
        </ul>
        <p><em>Disclaimer: This is a simplified guide based on Hanafi Fiqh. Consult a qualified Islamic scholar for specific rulings related to your personal situation.</em></p>
HTML
        ,
        'value' => 'Value',
        'gold_info' => 'Weight of gold you own (jewelry, bars, etc.)',
        'silver_info' => 'Weight of silver you own (jewelry, bars, etc.)',
        'cash_info' => 'Cash in hand, bank balances, liquid investments',
        'business_info' => 'Current resale value of stock/inventory',
        'debts_info' => 'Debts you need to repay within the next lunar year',
        'zakat_year' => 'Zakat Year (Approx)',
        'related_calculation' => 'Related Calculation',
        'public_calculator' => 'Public Zakat Calculator',
        'login_register_to_save' => 'Login or Register to Save Calculations and Log Payments',
        'currency_symbol' => 'PKR',
        'required_field' => 'This field is required.',
        'invalid_number' => 'Please enter a valid number.',
        'invalid_email' => 'Please enter a valid email address.',
        'nisab_explanation' => 'Nisab is the minimum threshold for Zakat. If you only own gold, the threshold is 7.5 Tolas of gold. If you own any mix of assets (gold + cash/silver/etc.), the threshold is the value of 52.5 Tolas of silver.',
        'gold_nisab_used' => 'Gold Nisab (7.5 Tolas)',
        'silver_nisab_used' => 'Silver Nisab (52.5 Tolas Value)',
        'update_password' => 'Update Password',
        'cancel' => 'Cancel',
        'enter_custom_prices_info' => 'Check to enter custom prices, otherwise defaults will be used:',
        'custom_prices_entry_label' => 'Custom Prices Entry',
        'enter_price_tola_gram_info' => 'Enter price per Gram OR per Tola',
        'log_payment_after_saving_note' => '(Save calculation first to link payment)',
        'back_to_history' => 'Back to History',
        'back_to_users' => 'Back to Users',
        'close_details' => 'Close Details',
        'disclaimer' => 'Disclaimer: This tool provides calculations based on the Hanafi school of thought for informational purposes only. Verify with a qualified scholar for your specific situation.',

    ],
    'ur' => [
        'toggle_lang' => 'English',
        'app_title' => ' ذاتی زکوٰۃ کیلکولیٹر اور لاگر',
        'home' => 'صفحہ اول',
        'calculate_zakat' => 'زکوٰۃ کا حساب لگائیں',
        'payment_log' => 'ادائیگی لاگ',
        'calculation_history' => 'حساب کتاب کی تاریخ',
        'profile' => 'پروفائل',
        'admin_panel' => 'ایڈمن پینل',
        'login' => 'لاگ ان کریں',
        'register' => 'رجسٹر کریں',
        'logout' => 'لاگ آؤٹ',
        'welcome' => 'خوش آمدید',
        'dashboard' => 'ڈیش بورڈ',
        'username' => 'صارف نام',
        'password' => 'پاس ورڈ',
        'email' => 'ای میل',
        'confirm_password' => 'پاس ورڈ کی تصدیق کریں',
        'register_account' => 'اکاؤنٹ رجسٹر کریں',
        'login_account' => 'اکاؤنٹ میں لاگ ان کریں',
        'already_have_account' => 'پہلے سے اکاؤنٹ ہے؟',
        'dont_have_account' => 'اکاؤنٹ نہیں ہے؟',
        'invalid_login' => 'غلط صارف نام یا پاس ورڈ۔',
        'registration_successful' => 'رجسٹریشن کامیاب ہو گئی۔ براہ کرم لاگ ان کریں۔',
        'passwords_do_not_match' => 'پاس ورڈ مماثل نہیں ہیں۔',
        'username_taken' => 'صارف نام پہلے سے موجود ہے۔',
        'email_taken' => 'ای میل پہلے سے موجود ہے۔',
        'error_occurred' => 'ایک خرابی پیش آگئی۔ براہ کرم دوبارہ کوشش کریں۔',
        'zakat_calculation_form' => 'زکوٰۃ حساب کتاب فارم',
        'current_prices' => 'موجودہ مارکیٹ قیمتیں',
        'gold_price' => 'سونے کی قیمت',
        'silver_price' => 'چاندی کی قیمت',
        'per_tola' => 'فی تولہ',
        'per_gram' => 'فی گرام',
        'use_default_prices' => 'ڈیفالٹ قیمتیں استعمال کریں',
        'assets' => 'اثاثے (قابلِ زکوٰۃ)',
        'gold' => 'سونا',
        'silver' => 'چاندی',
        'unit' => 'اکائی',
        'tola' => 'تولہ',
        'gram' => 'گرام',
        'amount' => 'مقدار/رقم',
        'cash_on_hand_bank' => 'نقد رقم (ہاتھ میں، بینک اکاؤنٹس میں)',
        'business_goods_inventory' => 'تجارتی سامان / انوینٹری کی قیمت',
        'liabilities_deductions' => 'واجبات / کٹوتیاں',
        'short_term_debts' => 'قلیل مدتی قرضے (1 سال کے اندر واجب الادا)',
        'calculate' => 'حساب لگائیں',
        'zakat_calculation_results' => 'زکوٰۃ حساب کتاب کے نتائج',
        'total_assets' => 'کل اثاثوں کی قیمت',
        'total_liabilities' => 'کل واجبات',
        'zakatable_wealth' => 'قابلِ زکوٰۃ دولت',
        'nisab_threshold' => 'نصاب کی حد استعمال ہوئی',
        'nisab_value_today' => 'نصاب کی قیمت (آج)',
        'zakat_due' => 'کل واجب الادا زکوٰۃ',
        'zakat_not_due' => 'زکوٰۃ واجب نہیں ہے (نصاب سے کم)۔',
        'calculation_date' => 'حساب کتاب کی تاریخ',
        'save_calculation' => 'حساب محفوظ کریں',
        'calculation_saved' => 'حساب کامیابی سے محفوظ ہو گیا۔',
        'error_saving_calculation' => 'حساب محفوظ کرنے میں خرابی۔',
        'log_zakat_payment' => 'زکوٰۃ ادائیگی لاگ کریں',
        'payment_date' => 'ادائیگی کی تاریخ',
        'recipient' => 'وصول کنندہ (شخص/ادارہ)',
        'status' => 'حیثیت',
        'paid' => 'ادا کر دیا',
        'pending' => 'زیر التواء',
        'notes' => 'نوٹس (اختیاری)',
        'log_payment' => 'ادائیگی لاگ کریں',
        'payment_logged' => 'ادائیگی کامیابی سے لاگ ہو گئی۔',
        'error_logging_payment' => 'ادائیگی لاگ کرنے میں خرابی۔',
        'zakat_payment_history' => 'زکوٰۃ ادائیگی کی تاریخ',
        'filter_by_year' => 'سال کے لحاظ سے فلٹر کریں',
        'all_years' => 'تمام سال',
        'filter' => 'فلٹر',
        'date' => 'تاریخ',
        'action' => 'کارروائی',
        'actions' => 'کاروائیاں',
        'view' => 'دیکھیں',
        'no_payments_logged' => 'ابھی تک کوئی ادائیگی لاگ نہیں ہوئی۔',
        'zakat_calculation_history' => 'زکوٰۃ حساب کتاب کی تاریخ',
        'year' => 'سال',
        'no_calculations_found' => 'کوئی حساب کتاب نہیں ملا۔',
        'user_profile' => 'صارف پروفائل',
        'update_profile' => 'پروفائل اپ ڈیٹ کریں',
        'new_password' => 'نیا پاس ورڈ (موجودہ رکھنے کے لیے خالی چھوڑ دیں)',
        'zakat_due_date' => 'آپ کی زکوٰۃ کی مقررہ تاریخ (ہجری یا گریگورین)',
        'profile_updated' => 'پروفائل کامیابی سے اپ ڈیٹ ہو گیا۔',
        'error_updating_profile' => 'پروفائل اپ ڈیٹ کرنے میں خرابی۔',
        'incorrect_current_password' => 'موجودہ پاس ورڈ غلط ہے۔',
        'zakat_reminder' => 'زکوٰۃ یاد دہانی',
        'your_zakat_due_date_is' => 'آپ کی زکوٰۃ کی تاریخ قریب ہے:',
        'please_calculate_zakat' => 'اگر آپ نے پہلے ہی نہیں کیا ہے تو براہ کرم اپنی زکوٰۃ کا حساب لگائیں۔',
        'set_due_date_in_profile' => 'یاد دہانیوں کے لیے اپنی پروفائل میں زکوٰۃ کی مقررہ تاریخ درج کریں۔',
        'admin_dashboard' => 'ایڈمن ڈیش بورڈ',
        'user_management' => 'صارف کا انتظام',
        'all_zakat_logs' => 'تمام زکوٰۃ لاگز (حساب کتاب اور ادائیگیاں)',
        'settings' => 'ترتیبات',
        'manage_users' => 'صارفین کا نظم کریں',
        'add_user' => 'صارف شامل کریں',
        'id' => 'آئی ڈی',
        'role' => 'کردار',
        'last_login' => 'آخری لاگ ان',
        'created_at' => 'بنانے کی تاریخ',
        'edit' => 'ترمیم',
        'delete' => 'حذف کریں',
        'user' => 'صارف',
        'admin' => 'ایڈمن',
        'edit_user' => 'صارف میں ترمیم کریں',
        'update_user' => 'صارف اپ ڈیٹ کریں',
        'password_leave_blank' => 'پاس ورڈ (تبدیل نہ کرنے کے لیے خالی چھوڑ دیں)',
        'user_added' => 'صارف کامیابی سے شامل ہو گیا۔',
        'error_adding_user' => 'صارف شامل کرنے میں خرابی۔',
        'user_updated' => 'صارف کامیابی سے اپ ڈیٹ ہو گیا۔',
        'error_updating_user' => 'صارف اپ ڈیٹ کرنے میں خرابی۔',
        'user_deleted' => 'صارف کامیابی سے حذف ہو گیا۔',
        'error_deleting_user' => 'صارف حذف کرنے میں خرابی۔',
        'cannot_delete_self' => 'آپ اپنا ایڈمن اکاؤنٹ حذف نہیں کر سکتے۔',
        'confirm_delete' => 'کیا آپ واقعی اس صارف کو حذف کرنا چاہتے ہیں؟',
        'view_all_logs' => 'تمام لاگز دیکھیں',
        'filter_by_user' => 'صارف کے لحاظ سے فلٹر کریں',
        'all_users' => 'تمام صارفین',
        'export_csv' => 'CSV برآمد کریں',
        'type' => 'قسم',
        'calculation' => 'حساب کتاب',
        'payment' => 'ادائیگی',
        'details' => 'تفصیلات',
        'calculation_details' => 'حساب کی تفصیلات',
        'payment_details' => 'ادائیگی کی تفصیلات',
        'no_logs_found' => 'کوئی لاگ نہیں ملا۔',
        'application_settings' => 'ایپلیکیشن کی ترتیبات',
        'nisab_settings' => 'نصاب کی ترتیبات',
        'nisab_gold_tola' => 'نصاب سونا (تولہ)',
        'nisab_silver_tola' => 'نصاب چاندی (تولہ)',
        'grams_per_tola_setting' => 'گرام فی تولہ',
        'default_market_prices' => 'ڈیفالٹ مارکیٹ قیمتیں (فی گرام)',
        'default_gold_price_per_gram' => 'ڈیفالٹ سونے کی قیمت (فی گرام)',
        'default_silver_price_per_gram' => 'ڈیفالٹ چاندی کی قیمت (فی گرام)',
        'save_settings' => 'ترتیبات محفوظ کریں',
        'settings_saved' => 'ترتیبات کامیابی سے محفوظ ہو گئیں۔',
        'error_saving_settings' => 'ترتیبات محفوظ کرنے میں خرابی۔',
        'info_page_title' => 'زکوٰۃ کی معلومات (حنفی فقہ)',
        'info_page_content' => <<<HTML
        <h2>زکوٰۃ کے حساب کتاب کے قواعد (حنفی فقہ - آسان وضاحت)</h2>
        <p>زکوٰۃ اسلام کے پانچ ستونوں میں سے ایک ہے، جو اہل مسلمانوں پر فرض صدقہ ہے۔</p>
        <h4>زکوٰۃ کب فرض ہوتی ہے؟</h4>
        <ol>
            <li><strong>اگر آپ کے پاس صرف سونا ہے:</strong> زکوٰۃ تب واجب ہوتی ہے جب آپ کے پاس <strong>7.5 تولے</strong> (تقریباً 87.48 گرام) یا اس سے زیادہ سونا ہو، اور کوئی دوسرے قابلِ زکوٰۃ اثاثے (جیسے چاندی، نقد رقم، تجارتی سامان) نہ ہوں۔ شرح سونے کی قیمت کا 2.5% ہے۔</li>
            <li><strong>اگر آپ کے پاس صرف چاندی ہے:</strong> زکوٰۃ تب واجب ہوتی ہے جب آپ کے پاس <strong>52.5 تولے</strong> (تقریباً 612.36 گرام) یا اس سے زیادہ چاندی ہو۔ شرح چاندی کی قیمت کا 2.5% ہے۔</li>
            <li><strong>اگر آپ کے پاس ملے جلے اثاثے ہیں (سونا، چاندی، نقد رقم، تجارتی سامان):</strong>
                <ul>
                    <li>اپنے تمام سونے، چاندی، نقد رقم (ہاتھ میں، بینک اکاؤنٹس میں)، اور کاروباری انوینٹری/اسٹاک کی موجودہ مارکیٹ ویلیو کا حساب لگائیں۔</li>
                    <li>ذاتی استعمال کی اشیاء (آپ کا گھر، ذاتی استعمال کی گاڑی، کپڑے، ضروری فرنیچر) کو خارج کر دیں۔</li>
                    <li>اپنی فوری واجبات اور قلیل مدتی قرضوں (جو ایک قمری سال کے اندر واجب الادا ہوں) کو منہا کریں۔</li>
                    <li>باقی خالص مالیت (قابلِ زکوٰۃ دولت) کا موازنہ <strong>52.5 تولے چاندی</strong> کی قیمت سے کریں (اس صورت میں یہ نصاب کی حد ہے)۔</li>
                    <li>اگر آپ کی زکوٰۃ کی مقررہ تاریخ پر آپ کی قابلِ زکوٰۃ دولت 52.5 تولے چاندی کی قیمت کے برابر یا اس سے زیادہ ہے، تو زکوٰۃ فرض ہے۔</li>
                </ul>
            </li>
            <li><strong>اگر آپ کے پاس صرف نقد رقم اور/یا تجارتی سامان ہے:</strong> نصاب کی حد <strong>52.5 تولے چاندی</strong> کی قیمت ہے۔</li>
        </ol>
        <h4>اہم نکات:</h4>
        <ul>
            <li><strong>نصاب:</strong> زکوٰۃ کے فرض ہونے کی کم از کم حد۔ یہ یا تو 7.5 تولے سونا ہے یا 52.5 تولے چاندی۔ <strong>اہم بات یہ ہے کہ حنفی فقہ کے مطابق، اگر آپ سونے کے علاوہ *کوئی* بھی اثاثہ رکھتے ہیں (مثلاً، تھوڑی سی نقد رقم یا چاندی بھی)، تو استعمال ہونے والی نصاب کی حد 52.5 تولے چاندی کی قیمت ہے۔</strong> 7.5 تولے سونے کا نصاب *صرف* اس صورت میں لاگو ہوتا ہے جب آپ کے پاس سونے کے علاوہ *کچھ بھی* نہ ہو۔</li>
            <li><strong>حول (قمری سال):</strong> زکوٰۃ سال میں ایک بار اس دولت پر قابل ادائیگی ہے جو پورے ایک اسلامی (قمری) سال تک ملکیت میں رہی ہو۔ آپ کو ایک زکوٰۃ کی تاریخ مقرر کرنی چاہیے (مثلاً، یکم رمضان) اور ہر سال اسی تاریخ پر حساب لگانا چاہیے۔</li>
            <li><strong>شرح:</strong> زکوٰۃ کی معیاری شرح آپ کی کل قابلِ زکوٰۃ دولت کا <strong>2.5%</strong> ہے۔</li>
            <li><strong>حساب کتاب کا دن:</strong> حساب کتاب کے لیے اپنی زکوٰۃ کی مقررہ تاریخ پر سونے اور چاندی کی مارکیٹ قیمتیں استعمال کریں۔</li>
        </ul>
        <p><em>ڈس کلیمر: یہ حنفی فقہ پر مبنی ایک آسان گائیڈ ہے۔ اپنی ذاتی صورتحال سے متعلق مخصوص احکام کے لیے کسی مستند اسلامی عالم سے رجوع کریں۔</em></p>
HTML
        ,
        'value' => 'قیمت',
        'gold_info' => 'آپ کی ملکیت میں سونے کا وزن (زیورات، بارز وغیرہ)',
        'silver_info' => 'آپ کی ملکیت میں چاندی کا وزن (زیورات، بارز وغیرہ)',
        'cash_info' => 'ہاتھ میں نقد رقم، بینک بیلنس، مائع سرمایہ کاری',
        'business_info' => 'اسٹاک/انوینٹری کی موجودہ دوبارہ فروخت کی قیمت',
        'debts_info' => 'وہ قرضے جو آپ کو اگلے قمری سال کے اندر ادا کرنے ہیں',
        'zakat_year' => 'زکوٰۃ سال (تقریباً)',
        'related_calculation' => 'متعلقہ حساب کتاب',
        'public_calculator' => 'عوامی زکوٰۃ کیلکولیٹر',
        'login_register_to_save' => 'حسابات محفوظ کرنے اور ادائیگیوں کو لاگ کرنے کے لیے لاگ ان یا رجسٹر کریں۔',
        'currency_symbol' => 'روپے',
        'required_field' => 'یہ خانہ ضروری ہے۔',
        'invalid_number' => 'براہ کرم ایک درست نمبر درج کریں۔',
        'invalid_email' => 'براہ کرم ایک درست ای میل ایڈریس درج کریں۔',
        'nisab_explanation' => 'نصاب زکوٰۃ کے لیے کم از کم حد ہے۔ اگر آپ کے پاس صرف سونا ہے تو حد 7.5 تولہ سونا ہے۔ اگر آپ کے پاس اثاثوں کا کوئی مرکب ہے (سونا + نقد/چاندی/وغیرہ)، تو حد 52.5 تولہ چاندی کی قیمت ہے۔',
        'gold_nisab_used' => 'سونے کا نصاب (7.5 تولہ)',
        'silver_nisab_used' => 'چاندی کا نصاب (52.5 تولہ قیمت)',
        'update_password' => 'پاس ورڈ اپ ڈیٹ کریں',
        'cancel' => 'منسوخ کریں',
        'enter_custom_prices_info' => 'اپنی قیمتیں درج کرنے کے لیے چیک کریں، ورنہ ڈیفالٹ استعمال ہوں گی:',
        'custom_prices_entry_label' => 'اپنی قیمتیں درج کریں',
        'enter_price_tola_gram_info' => 'فی گرام یا فی تولہ قیمت درج کریں',
        'log_payment_after_saving_note' => '(ادائیگی کو لنک کرنے کے لیے پہلے حساب محفوظ کریں)',
        'back_to_history' => 'تاریخ پر واپس',
        'back_to_users' => 'صارفین پر واپس',
        'close_details' => 'تفصیلات بند کریں',
        'disclaimer' => 'ڈس کلیمر: یہ ٹول صرف معلوماتی مقاصد کے لیے حنفی مکتبہ فکر کی بنیاد پر حساب فراہم کرتا ہے۔ اپنی مخصوص صورتحال کے لیے کسی مستند عالم سے تصدیق کریں۔',
    ]
];

function t(string $key): string {
    global $translations, $lang_code;
    return $translations[$lang_code][$key] ?? $key;
}

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        role TEXT NOT NULL DEFAULT 'user',
        zakat_due_date TEXT,
        last_login DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS zakat_calculations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        year INTEGER NOT NULL,
        gold_grams REAL NOT NULL,
        silver_grams REAL NOT NULL,
        cash REAL NOT NULL,
        business_goods REAL NOT NULL,
        debts REAL NOT NULL,
        gold_price_per_gram REAL NOT NULL,
        silver_price_per_gram REAL NOT NULL,
        total_assets REAL NOT NULL,
        zakatable_wealth REAL NOT NULL,
        nisab_threshold_used TEXT NOT NULL,
        nisab_value REAL NOT NULL,
        zakat_due REAL NOT NULL,
        calculation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

     $db->exec("CREATE TABLE IF NOT EXISTS zakat_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        calculation_id INTEGER,
        amount REAL NOT NULL,
        payment_date DATE NOT NULL,
        recipient TEXT,
        status TEXT NOT NULL,
        notes TEXT,
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (calculation_id) REFERENCES zakat_calculations(id) ON DELETE SET NULL
    )");

    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([ADMIN_USERNAME]);
    if ($stmt->fetchColumn() == 0) {
        $db->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)")
           ->execute([ADMIN_USERNAME, password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 'admin@example.com', 'admin']);
    }

     $default_settings = [
        'nisab_gold_tolas' => (string)NISAB_GOLD_TOLAS,
        'nisab_silver_tolas' => (string)NISAB_SILVER_TOLAS,
        'grams_per_tola' => (string)GRAMS_PER_TOLA,
        'default_gold_price_per_gram' => '20000',
        'default_silver_price_per_gram' => '250'
    ];
     foreach ($default_settings as $key => $value) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Database connection failed. Error details have been logged.");
}

function get_setting(string $key, $default = null) {
    global $db;
    static $settings_cache = null;
    if ($settings_cache === null) {
        $settings_cache = [];
        $stmt = $db->query("SELECT key, value FROM settings");
        while($row = $stmt->fetch()) {
            $settings_cache[$row['key']] = $row['value'];
        }
    }
    return $settings_cache[$key] ?? $default;
}

function update_setting(string $key, string $value): bool {
     global $db;
     try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        $result = $stmt->execute([$key, $value]);
        // Clear cache on update
        $settings_cache = null;
        return $result;
     } catch (PDOException $e) {
        error_log("Error updating setting '$key': " . $e->getMessage());
        return false;
     }
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header('Location: ?page=login');
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied.');
    }
}

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function validate_number($value, bool $allow_float = true, ?float $min = null, ?float $max = null): bool {
    if (!is_numeric($value)) return false;
    $num = $allow_float ? (float)$value : (int)$value;
    if ($min !== null && $num < $min) return false;
    if ($max !== null && $num > $max) return false;
    return true;
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function format_currency(float $amount, ?string $currency_symbol = null): string {
    if ($currency_symbol === null) {
         $currency_symbol = t('currency_symbol');
    }
    return $currency_symbol . ' ' . number_format($amount, 2);
}

function calculate_zakat(array $input_data): array {
    global $db;

    $nisab_gold_tolas = (float)get_setting('nisab_gold_tolas', NISAB_GOLD_TOLAS);
    $nisab_silver_tolas = (float)get_setting('nisab_silver_tolas', NISAB_SILVER_TOLAS);
    $grams_per_tola = (float)get_setting('grams_per_tola', GRAMS_PER_TOLA);
    if ($grams_per_tola <= 0) $grams_per_tola = GRAMS_PER_TOLA; // Fallback

    $nisab_gold_grams = $nisab_gold_tolas * $grams_per_tola;
    $nisab_silver_grams = $nisab_silver_tolas * $grams_per_tola;

    $gold_amount = isset($input_data['gold_amount']) && is_numeric($input_data['gold_amount']) ? (float)$input_data['gold_amount'] : 0.0;
    $gold_unit = isset($input_data['gold_unit']) && $input_data['gold_unit'] === 'tola' ? 'tola' : 'gram';
    $silver_amount = isset($input_data['silver_amount']) && is_numeric($input_data['silver_amount']) ? (float)$input_data['silver_amount'] : 0.0;
    $silver_unit = isset($input_data['silver_unit']) && $input_data['silver_unit'] === 'tola' ? 'tola' : 'gram';
    $cash = isset($input_data['cash']) && is_numeric($input_data['cash']) ? (float)$input_data['cash'] : 0.0;
    $business_goods = isset($input_data['business_goods']) && is_numeric($input_data['business_goods']) ? (float)$input_data['business_goods'] : 0.0;
    $debts = isset($input_data['debts']) && is_numeric($input_data['debts']) ? (float)$input_data['debts'] : 0.0;

    $use_custom_prices = isset($input_data['use_custom_prices']) && $input_data['use_custom_prices'] === 'on';
    $gold_price_per_gram = $use_custom_prices && isset($input_data['gold_price_per_gram']) && is_numeric($input_data['gold_price_per_gram'])
                            ? (float)$input_data['gold_price_per_gram']
                            : (float)get_setting('default_gold_price_per_gram', 0);
    $silver_price_per_gram = $use_custom_prices && isset($input_data['silver_price_per_gram']) && is_numeric($input_data['silver_price_per_gram'])
                             ? (float)$input_data['silver_price_per_gram']
                             : (float)get_setting('default_silver_price_per_gram', 0);

    if ($gold_price_per_gram <= 0 || $silver_price_per_gram <= 0) {
        return ['error' => 'Gold and Silver prices must be greater than zero. Check defaults or custom inputs.'];
    }

    $gold_grams = ($gold_unit === 'tola') ? $gold_amount * $grams_per_tola : $gold_amount;
    $silver_grams = ($silver_unit === 'tola') ? $silver_amount * $grams_per_tola : $silver_amount;

    $gold_value = $gold_grams * $gold_price_per_gram;
    $silver_value = $silver_grams * $silver_price_per_gram;
    $total_assets_value = $gold_value + $silver_value + $cash + $business_goods;
    $zakatable_wealth = max(0.0, $total_assets_value - $debts);

    $zakat_due = 0.0;
    $nisab_threshold_used = '';
    $nisab_value = 0.0;
    $is_due = false;

    $has_only_gold = $gold_grams > 0 && $silver_grams == 0 && $cash == 0 && $business_goods == 0;

    if ($has_only_gold) {
        $nisab_threshold_used = 'gold';
        $nisab_value = $nisab_gold_grams * $gold_price_per_gram;
        if ($gold_grams >= $nisab_gold_grams) {
             $is_due = true;
             $zakat_due = $gold_value * 0.025;
             $zakatable_wealth = $gold_value;
        } else {
             $zakatable_wealth = $gold_value;
        }
    } else {
        $nisab_threshold_used = 'silver';
        $nisab_value = $nisab_silver_grams * $silver_price_per_gram;
        if ($zakatable_wealth >= $nisab_value) {
            $is_due = true;
            $zakat_due = $zakatable_wealth * 0.025;
        }
    }

    // Return original inputs as well for form pre-filling
    return [
        'gold_amount' => $gold_amount,
        'gold_unit' => $gold_unit,
        'silver_amount' => $silver_amount,
        'silver_unit' => $silver_unit,
        'cash' => $cash,
        'business_goods' => $business_goods,
        'debts' => $debts,
        'use_custom_prices' => $use_custom_prices ? 'on' : null, // Preserve checkbox state
        'gold_price_per_gram' => $gold_price_per_gram, // The actual gram price used
        'silver_price_per_gram' => $silver_price_per_gram, // The actual gram price used
        'gold_grams' => $gold_grams,
        'silver_grams' => $silver_grams,
        'total_assets' => $total_assets_value,
        'zakatable_wealth' => $zakatable_wealth,
        'nisab_threshold_used' => $nisab_threshold_used,
        'nisab_value' => $nisab_value,
        'zakat_due' => $zakat_due,
        'is_due' => $is_due,
        'year' => (int)date('Y'),
        'calculation_date' => date('Y-m-d H:i:s'),
    ];
}

$page = isset($_GET['page']) ? basename($_GET['page']) : 'home';
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
$errors = [];
$success = '';
$calculation_result = null; // Holds result from POST calculation

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection would go here

    try {
        if ($action === 'login') {
            $username = sanitize_input($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $errors[] = t('invalid_login');
            } else {
                $stmt = $db->prepare("SELECT id, username, password_hash, role, email, zakat_due_date FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_zakat_due_date'] = $user['zakat_due_date'];

                    $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);

                    $redirect_url = $_SESSION['redirect_to'] ?? '?page=dashboard';
                    unset($_SESSION['redirect_to']);
                    header('Location: ' . $redirect_url);
                    exit;
                } else {
                    $errors[] = t('invalid_login');
                }
            }
        } elseif ($action === 'register') {
            $username = sanitize_input($_POST['username'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
                $errors[] = t('required_field');
            } elseif ($password !== $confirm_password) {
                $errors[] = t('passwords_do_not_match');
            } elseif (!validate_email($email)) {
                $errors[] = t('invalid_email');
            } else {
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                $existing = $stmt->fetch();
                 if ($existing) {
                     $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                     $stmt_check->execute([$username]);
                     if($stmt_check->fetchColumn() > 0) $errors[] = t('username_taken');

                     $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                     $stmt_check->execute([$email]);
                     if($stmt_check->fetchColumn() > 0) $errors[] = t('email_taken');
                 }
            }

            if (empty($errors)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$username, $password_hash, $email, 'user'])) {
                    $_SESSION['success_message'] = t('registration_successful');
                    header('Location: ?page=login');
                    exit;
                } else {
                    $errors[] = t('error_occurred');
                }
            }
            $page = 'register'; // Stay on register page if errors
        } elseif ($action === 'calculate_zakat' || $action === 'calculate_zakat_public') {
             $input = $_POST; // No need to sanitize numbers here, filter during calculation
             $calculation_result = calculate_zakat($input); // Store result directly for potential immediate display
             if (isset($calculation_result['error'])) {
                 $errors[] = $calculation_result['error'];
                 $calculation_result = null;
             } else {
                 // Store in session ONLY if successful, for potential use if user navigates away/refreshes
                 $_SESSION['last_calculation'] = $calculation_result;
             }
             $page = ($action === 'calculate_zakat_public') ? 'public_calculator' : 'calculate'; // Stay on current calculator page
        } elseif ($action === 'save_calculation' && is_logged_in()) {
            if (isset($_SESSION['last_calculation'])) {
                 $calc = $_SESSION['last_calculation'];
                 $stmt = $db->prepare("INSERT INTO zakat_calculations (user_id, year, gold_grams, silver_grams, cash, business_goods, debts, gold_price_per_gram, silver_price_per_gram, total_assets, zakatable_wealth, nisab_threshold_used, nisab_value, zakat_due, calculation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                 if ($stmt->execute([
                     $_SESSION['user_id'], $calc['year'], $calc['gold_grams'], $calc['silver_grams'], $calc['cash'], $calc['business_goods'], $calc['debts'], $calc['gold_price_per_gram'], $calc['silver_price_per_gram'], $calc['total_assets'], $calc['zakatable_wealth'], $calc['nisab_threshold_used'], $calc['nisab_value'], $calc['zakat_due'], $calc['calculation_date']
                 ])) {
                     $success = t('calculation_saved');
                     unset($_SESSION['last_calculation']); // Clear session calculation once saved
                 } else {
                     $errors[] = t('error_saving_calculation');
                 }
            } else {
                 $errors[] = 'No calculation data found to save.';
            }
            $page = 'calculate';
        } elseif ($action === 'log_payment' && is_logged_in()) {
             $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
             $payment_date = sanitize_input($_POST['payment_date'] ?? '');
             $recipient = sanitize_input($_POST['recipient'] ?? '');
             $status = sanitize_input($_POST['status'] ?? 'paid');
             $notes = sanitize_input($_POST['notes'] ?? '');
             $calculation_id = filter_input(INPUT_POST, 'calculation_id', FILTER_VALIDATE_INT);
             if ($calculation_id === false || $calculation_id <= 0) $calculation_id = null;

             if ($amount === false || $amount <= 0 || empty($payment_date)) {
                 $errors[] = t('required_field') . ' (Amount, Date)';
             } elseif (!in_array($status, ['paid', 'pending'])) {
                 $errors[] = 'Invalid status.';
             } else {
                 $stmt = $db->prepare("INSERT INTO zakat_payments (user_id, calculation_id, amount, payment_date, recipient, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                 if ($stmt->execute([$_SESSION['user_id'], $calculation_id, $amount, $payment_date, $recipient, $status, $notes])) {
                      $success = t('payment_logged');
                 } else {
                      $errors[] = t('error_logging_payment');
                 }
             }
             $page = 'payment_log';
        } elseif ($action === 'update_profile' && is_logged_in()) {
            $email = sanitize_input($_POST['email'] ?? '');
            $zakat_due_date = sanitize_input($_POST['zakat_due_date'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_new_password = $_POST['confirm_new_password'] ?? '';

            if (!validate_email($email)) {
                $errors[] = t('invalid_email');
            }

            $update_password = false;
            if (!empty($new_password)) {
                 if ($new_password !== $confirm_new_password) {
                     $errors[] = t('passwords_do_not_match');
                 } elseif (empty($current_password)){
                    $errors[] = t('incorrect_current_password');
                 } else {
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user_pass = $stmt->fetch();
                    if ($user_pass && password_verify($current_password, $user_pass['password_hash'])) {
                        $update_password = true;
                    } else {
                        $errors[] = t('incorrect_current_password');
                    }
                 }
            }

            if (empty($errors)) {
                 if ($update_password) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET email = ?, zakat_due_date = ?, password_hash = ? WHERE id = ?");
                    $stmt->execute([$email, $zakat_due_date, $new_password_hash, $_SESSION['user_id']]);
                 } else {
                    $stmt = $db->prepare("UPDATE users SET email = ?, zakat_due_date = ? WHERE id = ?");
                    $stmt->execute([$email, $zakat_due_date, $_SESSION['user_id']]);
                 }
                 $_SESSION['user_email'] = $email;
                 $_SESSION['user_zakat_due_date'] = $zakat_due_date;
                 $success = t('profile_updated');
            }
             $page = 'profile';
        } elseif ($action === 'admin_save_settings' && is_admin()) {
             $settings_to_save = [
                'nisab_gold_tolas' => filter_input(INPUT_POST, 'nisab_gold_tolas', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]),
                'nisab_silver_tolas' => filter_input(INPUT_POST, 'nisab_silver_tolas', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]),
                'grams_per_tola' => filter_input(INPUT_POST, 'grams_per_tola', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.1]]),
                'default_gold_price_per_gram' => filter_input(INPUT_POST, 'default_gold_price_per_gram', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]),
                'default_silver_price_per_gram' => filter_input(INPUT_POST, 'default_silver_price_per_gram', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]),
             ];
             $saved_count = 0;
             foreach ($settings_to_save as $key => $value) {
                 if ($value !== false && $value !== null) {
                      if(update_setting($key, (string)$value)) {
                          $saved_count++;
                      } else {
                          $errors[] = "Error saving setting: $key";
                      }
                 } else {
                     $errors[] = "Invalid value provided for setting: $key";
                 }
             }
             if (empty($errors)) {
                 $success = t('settings_saved');
             }
             $page = 'admin_settings';
        } elseif ($action === 'admin_add_user' && is_admin()) {
            $username = sanitize_input($_POST['username'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitize_input($_POST['role'] ?? 'user');

            if (empty($username) || empty($email) || empty($password)) {
                 $errors[] = t('required_field');
            } elseif (!validate_email($email)) {
                 $errors[] = t('invalid_email');
            } elseif (!in_array($role, ['user', 'admin'])) {
                 $errors[] = 'Invalid role selected.';
            } else {
                $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt_check->execute([$username]);
                if($stmt_check->fetchColumn() > 0) $errors[] = t('username_taken');

                $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt_check->execute([$email]);
                if($stmt_check->fetchColumn() > 0) $errors[] = t('email_taken');
            }

            if (empty($errors)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)");
                 if ($stmt->execute([$username, $password_hash, $email, $role])) {
                      $_SESSION['success_message_admin'] = t('user_added');
                      header('Location: ?page=admin_users');
                      exit;
                 } else {
                     $errors[] = t('error_adding_user');
                 }
            }
            $page = 'admin_add_user'; // Stay on add page if errors
        } elseif ($action === 'admin_update_user' && is_admin()) {
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $username = sanitize_input($_POST['username'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitize_input($_POST['role'] ?? 'user');

            if ($user_id === false || $user_id <= 0 || empty($username) || empty($email)) {
                $errors[] = t('required_field') . ' (User ID, Username, Email)';
            } elseif (!validate_email($email)) {
                $errors[] = t('invalid_email');
            } elseif (!in_array($role, ['user', 'admin'])) {
                 $errors[] = 'Invalid role selected.';
            } else {
                $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmt_check->execute([$username, $user_id]);
                if ($stmt_check->fetchColumn() > 0) $errors[] = t('username_taken');

                $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt_check->execute([$email, $user_id]);
                if ($stmt_check->fetchColumn() > 0) $errors[] = t('email_taken');
            }

             if (empty($errors)) {
                 if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ?, password_hash = ? WHERE id = ?");
                    $result = $stmt->execute([$username, $email, $role, $password_hash, $user_id]);
                 } else {
                    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                    $result = $stmt->execute([$username, $email, $role, $user_id]);
                 }

                 if ($result) {
                     $success = t('user_updated');
                 } else {
                     $errors[] = t('error_updating_user');
                 }
             }
             $page = 'admin_edit_user'; // Stay on edit page
             $_GET['user_id'] = $user_id; // Ensure GET param is set for re-rendering edit page

        }

    } catch (PDOException $e) {
        error_log("Database Action Error: " . $e->getMessage());
        $errors[] = t('error_occurred') . " (DB)";
    } catch (Exception $e) {
        error_log("General Action Error: " . $e->getMessage());
        $errors[] = t('error_occurred');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'logout') {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: ?page=login');
        exit;
    } elseif ($action === 'admin_delete_user' && is_admin()) {
        $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if ($user_id && $user_id > 0 && $user_id !== $_SESSION['user_id']) {
            try {
                // Check related records before deleting (optional, depends on desired behavior)
                // $stmt_check_calcs = $db->prepare("SELECT COUNT(*) FROM zakat_calculations WHERE user_id = ?");
                // $stmt_check_calcs->execute([$user_id]);
                // $calc_count = $stmt_check_calcs->fetchColumn();
                // $stmt_check_pays = $db->prepare("SELECT COUNT(*) FROM zakat_payments WHERE user_id = ?");
                // $stmt_check_pays->execute([$user_id]);
                // $pay_count = $stmt_check_pays->fetchColumn();
                // if ($calc_count > 0 || $pay_count > 0) {
                     // Option 1: Prevent deletion if records exist
                     // $_SESSION['error_message_admin'] = 'Cannot delete user with existing logs.';
                     // Option 2: Cascade delete (handled by FOREIGN KEY ON DELETE CASCADE)
                // } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$user_id])) {
                        $_SESSION['success_message_admin'] = t('user_deleted');
                    } else {
                        $_SESSION['error_message_admin'] = t('error_deleting_user');
                    }
                // }
            } catch (PDOException $e) {
                error_log("Error deleting user ID $user_id: " . $e->getMessage());
                $_SESSION['error_message_admin'] = t('error_deleting_user') . " (DB)";
            }
        } elseif ($user_id === $_SESSION['user_id']) {
             $_SESSION['error_message_admin'] = t('cannot_delete_self');
        } else {
             $_SESSION['error_message_admin'] = 'Invalid User ID for deletion.';
        }
        header('Location: ?page=admin_users');
        exit;
    } elseif ($action === 'admin_export_csv' && is_admin()) {
        try {
            $filter_user_id = filter_input(INPUT_GET, 'filter_user_id', FILTER_VALIDATE_INT);
            $filter_year = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="zakat_logs_'.date('Y-m-d').'.csv"');
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'Log ID', 'User ID', 'Username', 'Type', 'Date', 'Year', 'Amount/Zakat Due', 'Recipient', 'Status', 'Notes',
                'Gold (g)', 'Silver (g)', 'Cash', 'Business Goods', 'Debts', 'Gold Price/g', 'Silver Price/g',
                'Total Assets', 'Zakatable Wealth', 'Nisab Used', 'Nisab Value'
            ]);

            $sql_calc = "SELECT c.*, u.username FROM zakat_calculations c LEFT JOIN users u ON c.user_id = u.id WHERE 1=1";
            $params_calc = [];
            if ($filter_user_id) { $sql_calc .= " AND c.user_id = ?"; $params_calc[] = $filter_user_id; }
            if ($filter_year) { $sql_calc .= " AND c.year = ?"; $params_calc[] = $filter_year; }
            $stmt_calc = $db->prepare($sql_calc);
            $stmt_calc->execute($params_calc);
            while ($row = $stmt_calc->fetch()) {
                fputcsv($output, [
                    'C'.$row['id'], $row['user_id'], $row['username'] ?? 'Public', t('calculation'), $row['calculation_date'], $row['year'], $row['zakat_due'],
                    '', '', '',
                    $row['gold_grams'], $row['silver_grams'], $row['cash'], $row['business_goods'], $row['debts'],
                    $row['gold_price_per_gram'], $row['silver_price_per_gram'], $row['total_assets'], $row['zakatable_wealth'],
                    $row['nisab_threshold_used'], $row['nisab_value']
                ]);
            }

            $sql_pay = "SELECT p.*, u.username FROM zakat_payments p JOIN users u ON p.user_id = u.id WHERE 1=1";
            $params_pay = [];
            if ($filter_user_id) { $sql_pay .= " AND p.user_id = ?"; $params_pay[] = $filter_user_id; }
            if ($filter_year) { $sql_pay .= " AND strftime('%Y', p.payment_date) = ?"; $params_pay[] = (string)$filter_year; }
            $stmt_pay = $db->prepare($sql_pay);
            $stmt_pay->execute($params_pay);
            while ($row = $stmt_pay->fetch()) {
                 $payment_year = date('Y', strtotime($row['payment_date']));
                 fputcsv($output, [
                    'P'.$row['id'], $row['user_id'], $row['username'], t('payment'), $row['payment_date'], $payment_year, $row['amount'],
                    $row['recipient'], $row['status'], $row['notes'],
                    '', '', '', '', '', '', '', '', '', '', ''
                ]);
            }

            fclose($output);
            exit;

        } catch (Exception $e) {
             error_log("CSV Export Error: " . $e->getMessage());
             die("Error generating CSV export.");
        }
    }
}

if (strpos($page, 'admin') === 0) {
    if (isset($_SESSION['success_message_admin'])) {
        $success = $_SESSION['success_message_admin'];
        unset($_SESSION['success_message_admin']);
    }
    if (isset($_SESSION['error_message_admin'])) {
        $errors[] = $_SESSION['error_message_admin'];
        unset($_SESSION['error_message_admin']);
    }
}
if ($page === 'login' && isset($_SESSION['success_message'])) {
     $success = $_SESSION['success_message'];
     unset($_SESSION['success_message']);
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$default_gold_price = get_setting('default_gold_price_per_gram', 0);
$default_silver_price = get_setting('default_silver_price_per_gram', 0);
$grams_per_tola_setting = (float)get_setting('grams_per_tola', GRAMS_PER_TOLA);
if ($grams_per_tola_setting <= 0) $grams_per_tola_setting = GRAMS_PER_TOLA;

$latest_user_calculation = null;
if (is_logged_in() && ($page === 'payment_log' || $page === 'dashboard')) {
    $stmt = $db->prepare("SELECT id, year, calculation_date, zakat_due FROM zakat_calculations WHERE user_id = ? ORDER BY calculation_date DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $latest_user_calculation = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>" dir="<?php echo $lang_code === 'ur' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('app_title'); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f8f9fa; color: #333; font-size: 16px; }
        .container { max-width: 1100px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.05); }
        header { background-color: #006400; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        header h1 { margin: 0 10px 0 0; font-size: 1.5em; }
        header h1 a { color: #fff; text-decoration: none; }
        header nav ul { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; align-items: center; }
        header nav ul li { margin-left: 15px; margin-bottom: 5px; margin-top: 5px; }
        header nav ul li a { color: #fff; text-decoration: none; padding: 5px 8px; border-radius: 4px; transition: background-color 0.2s ease; }
        header nav ul li a:hover { background-color: rgba(255,255,255,0.2); }
        .lang-toggle a { font-weight: bold; }
        main { padding: 20px 0; }
        h2 { color: #006400; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-top: 0; font-size: 1.8em; }
        h3 { color: #333; margin-top: 1.5em; font-size: 1.4em; }
        footer { text-align: center; margin-top: 30px; padding: 20px 15px; border-top: 1px solid #eee; font-size: 0.9em; color: #777; background-color: #f1f1f1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="password"], input[type="number"], input[type="date"], select, textarea {
            width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em;
        }
        input[type="number"] { -moz-appearance: textfield; } /* Firefox */
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; } /* Chrome, Safari, Edge, Opera */
        input[type="checkbox"] { margin-right: 5px; vertical-align: middle; }
        button, input[type="submit"], .button {
            background-color: #008000; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; text-decoration: none; display: inline-block; margin-top: 10px; transition: background-color 0.2s ease;
        }
        button:hover, input[type="submit"]:hover, .button:hover { background-color: #006400; }
        .button-secondary { background-color: #6c757d; }
        .button-secondary:hover { background-color: #5a6268; }
        .button-danger { background-color: #dc3545; }
        .button-danger:hover { background-color: #c82333; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; }
        .error ul { margin: 0; padding-left: 20px; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; }
        .info { color: #0c5460; background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 10px 15px; margin-bottom: 15px; border-radius: 4px; }
        .form-group { margin-bottom: 15px; }
        .form-group small { color: #6c757d; font-size: 0.85em; display: block; margin-top: -10px; margin-bottom: 10px; }
        .form-group-inline { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-group-inline label { margin-bottom: 0; width: auto; white-space: nowrap; }
        .form-group-inline input, .form-group-inline select { width: auto; flex-grow: 1; margin-bottom: 0; min-width: 100px; }
        .form-group-inline small { margin-top: 0; margin-left: 10px; flex-basis: 100%;}
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .responsive-table { overflow-x: auto; }
        .calculation-result-box { border: 1px solid #006400; padding: 20px; margin-top: 20px; border-radius: 5px; background-color: #e9f5e9; }
        .calculation-result-box p { margin: 8px 0; }
        .calculation-result-box strong { color: #006400; }
        .calculation-result-box hr { border: 0; border-top: 1px dashed #ccc; margin: 15px 0; }
        .reminder { border: 1px solid #ffc107; background-color: #fff3cd; color: #856404; padding: 10px 15px; margin-bottom: 20px; border-radius: 4px; }
        .action-buttons form, .action-buttons a { margin-right: 5px; display: inline-block; }
        #custom_prices { border: 1px dashed #ccc; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
        [dir="rtl"] body { font-family: 'Tahoma', 'Arial', sans-serif; }
        [dir="rtl"] header nav ul li { margin-left: 0; margin-right: 15px; }
        [dir="rtl"] th, [dir="rtl"] td { text-align: right; }
        [dir="rtl"] input[type="checkbox"] { margin-left: 5px; margin-right: 0; }
        [dir="rtl"] .form-group small { margin-right: 0; margin-left: 0; }
        [dir="rtl"] .form-group-inline label { margin-left: 10px; margin-right: 0; }
        [dir="rtl"] .error ul { padding-left: 0; padding-right: 20px; }
        [dir="rtl"] .action-buttons form, [dir="rtl"] .action-buttons a { margin-right: 0; margin-left: 5px; }
        @media (max-width: 992px) {
            header nav ul li { margin-left: 10px; }
            [dir="rtl"] header nav ul li { margin-right: 10px; margin-left: 0; }
        }
        @media (max-width: 768px) {
            header { flex-direction: column; align-items: flex-start; }
            header nav ul { margin-top: 10px; width: 100%; }
            header nav ul li { margin: 5px 0; margin-left: 0; margin-right: 0; }
            .container { margin: 10px; padding: 15px; }
            h2 { font-size: 1.6em; }
            h3 { font-size: 1.3em; }
            .form-group-inline { flex-direction: column; align-items: stretch; gap: 5px; }
            .form-group-inline label { margin-bottom: 5px; }
            .form-group-inline input, .form-group-inline select { width: 100%; }
            .form-group-inline small { margin-left: 0; }
        }
    </style>
</head>
<body>

    <header>
        <h1><a href="?page=home"><?php echo t('app_title'); ?></a></h1>
        <nav>
            <ul>
                 <?php if (is_logged_in()): ?>
                    <li><a href="?page=dashboard"><?php echo t('dashboard'); ?></a></li>
                    <li><a href="?page=calculate"><?php echo t('calculate_zakat'); ?></a></li>
                    <li><a href="?page=payment_log"><?php echo t('payment_log'); ?></a></li>
                    <li><a href="?page=calculation_history"><?php echo t('calculation_history'); ?></a></li>
                    <li><a href="?page=profile"><?php echo t('profile'); ?></a></li>
                    <?php if (is_admin()): ?>
                    <li><a href="?page=admin_dashboard"><?php echo t('admin_panel'); ?></a></li>
                    <?php endif; ?>
                    <li><a href="?action=logout"><?php echo t('logout'); ?> (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                 <?php else: ?>
                     <li><a href="?page=home"><?php echo t('home'); ?></a></li>
                     <li><a href="?page=public_calculator"><?php echo t('public_calculator'); ?></a></li>
                     <li><a href="?page=login"><?php echo t('login'); ?></a></li>
                     <li><a href="?page=register"><?php echo t('register'); ?></a></li>
                 <?php endif; ?>
                <li class="lang-toggle">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => $lang_code === 'en' ? 'ur' : 'en'])); ?>">
                        <?php echo t('toggle_lang'); ?>
                    </a>
                </li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <main>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php
            switch ($page):

                case 'login': ?>
                    <h2><?php echo t('login_account'); ?></h2>
                    <form method="post" action="?page=login">
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
                    <p><?php echo t('dont_have_account'); ?> <a href="?page=register"><?php echo t('register'); ?></a></p>
                    <?php break;

                case 'register': ?>
                    <h2><?php echo t('register_account'); ?></h2>
                    <form method="post" action="?page=register">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label for="username"><?php echo t('username'); ?></label>
                            <input type="text" id="username" name="username" required value="<?php echo sanitize_input($_POST['username'] ?? ''); ?>">
                        </div>
                         <div class="form-group">
                            <label for="email"><?php echo t('email'); ?></label>
                            <input type="email" id="email" name="email" required value="<?php echo sanitize_input($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><?php echo t('password'); ?></label>
                            <input type="password" id="password" name="password" required>
                        </div>
                         <div class="form-group">
                            <label for="confirm_password"><?php echo t('confirm_password'); ?></label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit"><?php echo t('register'); ?></button>
                    </form>
                     <p><?php echo t('already_have_account'); ?> <a href="?page=login"><?php echo t('login'); ?></a></p>
                    <?php break;

                 case 'dashboard':
                    require_login();
                    ?>
                    <h2><?php echo t('dashboard'); ?></h2>
                    <p><?php echo t('welcome'); ?>, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

                    <?php
                    $due_date_str = $_SESSION['user_zakat_due_date'] ?? null;
                    if ($due_date_str) {
                         echo '<div class="reminder">';
                         echo '<strong>' . t('zakat_reminder') . '</strong><br>';
                         echo t('your_zakat_due_date_is') . ' ' . htmlspecialchars($due_date_str) . '.<br>';
                         echo t('please_calculate_zakat');
                         echo '</div>';
                    } else {
                         echo '<div class="info">' . t('set_due_date_in_profile') . '</div>';
                    }
                    ?>

                    <h3><?php echo t('quick_actions'); ?></h3>
                    <p class="action-buttons">
                        <a href="?page=calculate" class="button"><?php echo t('calculate_zakat'); ?></a>
                        <a href="?page=payment_log" class="button"><?php echo t('payment_log'); ?></a>
                        <a href="?page=calculation_history" class="button button-secondary"><?php echo t('calculation_history'); ?></a>
                        <a href="?page=profile" class="button button-secondary"><?php echo t('profile'); ?></a>
                    </p>

                    <?php
                     if ($latest_user_calculation) {
                        echo "<h3>Latest Calculation (".htmlspecialchars(date('M d, Y H:i', strtotime($latest_user_calculation['calculation_date'])))."):</h3>";
                        echo "<p>Zakat Due: ".format_currency((float)$latest_user_calculation['zakat_due'])."</p>";
                        echo '<p><a href="?page=calculation_history&view_id='.$latest_user_calculation['id'].'">View Details</a></p>';
                     }
                    ?>

                    <?php break;

                 case 'calculate':
                 case 'public_calculator':
                     $is_public = ($page === 'public_calculator');
                     if (!$is_public) require_login();

                     $form_action = $is_public ? 'calculate_zakat_public' : 'calculate_zakat';
                     $form_page = $is_public ? 'public_calculator' : 'calculate';

                     $calculation_result_display = null;
                     if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'calculate_zakat' || $action === 'calculate_zakat_public') && isset($calculation_result) && !isset($calculation_result['error'])) {
                         $calculation_result_display = $calculation_result;
                     } elseif (isset($_SESSION['last_calculation']) && ($page === 'calculate' || $page === 'public_calculator')) {
                         $calculation_result_display = $_SESSION['last_calculation'];
                         // Keep it in session to allow refresh/resubmit attempts without losing data? Or unset:
                         // unset($_SESSION['last_calculation']);
                     }

                     $form_data = $calculation_result_display ?? $_POST ?? [];

                     $gold_price_input = $form_data['gold_price_per_gram'] ?? $default_gold_price;
                     $silver_price_input = $form_data['silver_price_per_gram'] ?? $default_silver_price;
                     $use_defaults_checked = !(isset($form_data['use_custom_prices']) && $form_data['use_custom_prices'] === 'on');
                     $custom_prices_display = $use_defaults_checked ? 'none' : 'block';
                     ?>
                     <h2><?php echo $is_public ? t('public_calculator') : t('calculate_zakat'); ?></h2>
                     <?php if ($is_public): ?>
                        <div class="info"><?php echo t('login_register_to_save'); ?></div>
                     <?php endif; ?>

                    <form method="post" action="?page=<?php echo $form_page; ?>">
                         <input type="hidden" name="action" value="<?php echo $form_action; ?>">

                         <h3><?php echo t('current_prices'); ?></h3>
                         <div class="form-group">
                             <input type="checkbox" id="use_custom_prices" name="use_custom_prices" onchange="document.getElementById('custom_prices').style.display = this.checked ? 'block' : 'none';" <?php echo ($use_defaults_checked ? '' : 'checked'); ?>>
                             <label for="use_custom_prices" style="display:inline; font-weight:normal;">
                                <?php echo t('enter_custom_prices_info'); ?> Gold: <?php echo format_currency((float)$default_gold_price); ?>/g, Silver: <?php echo format_currency((float)$default_silver_price); ?>/g
                             </label>
                         </div>
                         <div id="custom_prices" style="display: <?php echo $custom_prices_display; ?>;">
                             <h4><?php echo t('custom_prices_entry_label'); ?></h4>
                            <p><small><?php echo t('enter_price_tola_gram_info'); ?> (<?php echo number_format($grams_per_tola_setting, 3); ?> g/Tola).</small></p>
                            <div class="form-group-inline">
                                <label for="gold_price_tola"><?php echo t('gold_price'); ?> (<?php echo t('per_tola'); ?>):</label>
                                <input type="number" step="any" min="0" id="gold_price_tola" name="gold_price_tola" value="<?php echo sanitize_input(isset($form_data['gold_price_per_gram']) ? (float)$form_data['gold_price_per_gram'] * $grams_per_tola_setting : ''); ?>" oninput="updatePrice('gold', 'tola')">

                                <label for="gold_price_gram"><?php echo t('per_gram'); ?>:</label>
                                <input type="number" step="any" min="0" id="gold_price_gram" name="gold_price_per_gram" value="<?php echo sanitize_input($form_data['gold_price_per_gram'] ?? ''); ?>" oninput="updatePrice('gold', 'gram')">
                            </div>
                            <div class="form-group-inline">
                                <label for="silver_price_tola"><?php echo t('silver_price'); ?> (<?php echo t('per_tola'); ?>):</label>
                                <input type="number" step="any" min="0" id="silver_price_tola" name="silver_price_tola" value="<?php echo sanitize_input(isset($form_data['silver_price_per_gram']) ? (float)$form_data['silver_price_per_gram'] * $grams_per_tola_setting : ''); ?>" oninput="updatePrice('silver', 'tola')">

                                <label for="silver_price_gram"><?php echo t('per_gram'); ?>:</label>
                                <input type="number" step="any" min="0" id="silver_price_gram" name="silver_price_per_gram" value="<?php echo sanitize_input($form_data['silver_price_per_gram'] ?? ''); ?>" oninput="updatePrice('silver', 'gram')">
                            </div>
                         </div>

                         <h3><?php echo t('assets'); ?></h3>
                         <div class="form-group form-group-inline">
                             <label for="gold_amount"><?php echo t('gold'); ?>:</label>
                             <input type="number" step="any" min="0" id="gold_amount" name="gold_amount" value="<?php echo sanitize_input($form_data['gold_amount'] ?? 0); ?>">
                             <select name="gold_unit" id="gold_unit">
                                 <option value="gram" <?php echo (($form_data['gold_unit'] ?? 'gram') === 'gram') ? 'selected' : ''; ?>><?php echo t('gram'); ?></option>
                                 <option value="tola" <?php echo (($form_data['gold_unit'] ?? '') === 'tola') ? 'selected' : ''; ?>><?php echo t('tola'); ?></option>
                             </select>
                             <small><?php echo t('gold_info'); ?></small>
                         </div>
                         <div class="form-group form-group-inline">
                             <label for="silver_amount"><?php echo t('silver'); ?>:</label>
                             <input type="number" step="any" min="0" id="silver_amount" name="silver_amount" value="<?php echo sanitize_input($form_data['silver_amount'] ?? 0); ?>">
                             <select name="silver_unit" id="silver_unit">
                                  <option value="gram" <?php echo (($form_data['silver_unit'] ?? 'gram') === 'gram') ? 'selected' : ''; ?>><?php echo t('gram'); ?></option>
                                 <option value="tola" <?php echo (($form_data['silver_unit'] ?? '') === 'tola') ? 'selected' : ''; ?>><?php echo t('tola'); ?></option>
                             </select>
                             <small><?php echo t('silver_info'); ?></small>
                         </div>
                         <div class="form-group">
                             <label for="cash"><?php echo t('cash_on_hand_bank'); ?>:</label>
                             <input type="number" step="any" min="0" id="cash" name="cash" value="<?php echo sanitize_input($form_data['cash'] ?? 0); ?>">
                             <small><?php echo t('cash_info'); ?></small>
                         </div>
                         <div class="form-group">
                             <label for="business_goods"><?php echo t('business_goods_inventory'); ?>:</label>
                             <input type="number" step="any" min="0" id="business_goods" name="business_goods" value="<?php echo sanitize_input($form_data['business_goods'] ?? 0); ?>">
                            <small><?php echo t('business_info'); ?></small>
                         </div>

                         <h3><?php echo t('liabilities_deductions'); ?></h3>
                         <div class="form-group">
                             <label for="debts"><?php echo t('short_term_debts'); ?>:</label>
                             <input type="number" step="any" min="0" id="debts" name="debts" value="<?php echo sanitize_input($form_data['debts'] ?? 0); ?>">
                              <small><?php echo t('debts_info'); ?></small>
                         </div>

                         <button type="submit"><?php echo t('calculate'); ?></button>
                     </form>

                     <?php if ($calculation_result_display && !isset($calculation_result_display['error'])): ?>
                         <div class="calculation-result-box">
                             <h3><?php echo t('zakat_calculation_results'); ?></h3>
                             <?php
                                $result_to_show = $calculation_result_display;
                             ?>
                             <p><strong><?php echo t('total_assets'); ?>:</strong> <?php echo format_currency((float)$result_to_show['total_assets']); ?></p>
                             <p><strong><?php echo t('total_liabilities'); ?>:</strong> <?php echo format_currency((float)$result_to_show['debts']); ?></p>
                             <p><strong><?php echo t('zakatable_wealth'); ?>:</strong> <?php echo format_currency((float)$result_to_show['zakatable_wealth']); ?></p>
                             <hr>
                             <p><strong><?php echo t('nisab_threshold'); ?>:</strong>
                                <?php
                                 $nisab_gold_tolas_disp = (float)get_setting('nisab_gold_tolas', NISAB_GOLD_TOLAS);
                                 $nisab_silver_tolas_disp = (float)get_setting('nisab_silver_tolas', NISAB_SILVER_TOLAS);
                                 $grams_per_tola_disp = (float)get_setting('grams_per_tola', GRAMS_PER_TOLA);
                                 if ($grams_per_tola_disp <= 0) $grams_per_tola_disp = GRAMS_PER_TOLA;
                                 $nisab_gold_grams_disp = $nisab_gold_tolas_disp * $grams_per_tola_disp;
                                 $nisab_silver_grams_disp = $nisab_silver_tolas_disp * $grams_per_tola_disp;

                                 $nisab_tola_val_disp = ($result_to_show['nisab_threshold_used'] == 'gold') ? $nisab_gold_tolas_disp : $nisab_silver_tolas_disp;
                                 $nisab_gram_val_disp = ($result_to_show['nisab_threshold_used'] == 'gold') ? $nisab_gold_grams_disp : $nisab_silver_grams_disp;
                                 $nisab_desc_disp = ($result_to_show['nisab_threshold_used'] == 'gold') ? t('gold_nisab_used') : t('silver_nisab_used');

                                 echo htmlspecialchars($nisab_desc_disp . " (" . number_format($nisab_tola_val_disp, 2) . " Tolas / " . number_format($nisab_gram_val_disp, 2) . "g " . $result_to_show['nisab_threshold_used'] . ")");
                                 ?>
                             </p>
                             <p><strong><?php echo t('nisab_value_today'); ?>:</strong> <?php echo format_currency((float)$result_to_show['nisab_value']); ?></p>
                             <p><em><small><?php echo t('nisab_explanation'); ?></small></em></p>
                             <hr>
                              <?php if ($result_to_show['is_due']): ?>
                                 <p style="font-size: 1.2em; font-weight: bold;"><strong><?php echo t('zakat_due'); ?> (2.5%):</strong> <?php echo format_currency((float)$result_to_show['zakat_due']); ?></p>
                                 <?php if (!$is_public): ?>
                                     <div class="action-buttons">
                                         <form method="post" action="?page=calculate">
                                             <input type="hidden" name="action" value="save_calculation">
                                             <button type="submit"><?php echo t('save_calculation'); ?></button>
                                         </form>
                                         <a href="?page=payment_log&amount=<?php echo $result_to_show['zakat_due'];?>" class="button button-secondary"><?php echo t('log_payment'); ?></a>
                                     </div>
                                     <small><?php echo t('log_payment_after_saving_note'); ?></small>
                                 <?php endif; ?>
                             <?php else: ?>
                                 <p style="font-weight: bold;"><?php echo t('zakat_not_due'); ?></p>
                                  <?php if (!$is_public): ?>
                                      <form method="post" action="?page=calculate">
                                          <input type="hidden" name="action" value="save_calculation">
                                          <button type="submit"><?php echo t('save_calculation'); ?></button>
                                      </form>
                                  <?php endif; ?>
                             <?php endif; ?>
                             <p><small><?php echo t('calculation_date'); ?>: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($result_to_show['calculation_date']))); ?></small></p>
                         </div>
                     <?php endif; ?>

                    <script>
                        const gramsPerTola = <?php echo $grams_per_tola_setting; ?>;
                        function updatePrice(metal, sourceUnit) {
                            const tolaInput = document.getElementById(metal + '_price_tola');
                            const gramInput = document.getElementById(metal + '_price_gram');
                            if (!tolaInput || !gramInput) return;

                            if (sourceUnit === 'tola') {
                                const tolaPrice = parseFloat(tolaInput.value);
                                if (!isNaN(tolaPrice) && tolaPrice >= 0) {
                                    gramInput.value = (tolaPrice / gramsPerTola).toFixed(4);
                                } else if (tolaInput.value === '') {
                                    gramInput.value = '';
                                }
                            } else if (sourceUnit === 'gram') {
                                const gramPrice = parseFloat(gramInput.value);
                                if (!isNaN(gramPrice) && gramPrice >= 0) {
                                     tolaInput.value = (gramPrice * gramsPerTola).toFixed(2);
                                } else if (gramInput.value === '') {
                                     tolaInput.value = '';
                                }
                            }
                             // Ensure custom prices checkbox is checked if user interacts with these fields
                             document.getElementById('use_custom_prices').checked = true;
                             document.getElementById('custom_prices').style.display = 'block';
                        }
                         document.addEventListener('DOMContentLoaded', () => {
                             const goldGramInput = document.getElementById('gold_price_gram');
                             const silverGramInput = document.getElementById('silver_price_gram');
                             if(goldGramInput && goldGramInput.value && parseFloat(goldGramInput.value) > 0) updatePrice('gold', 'gram');
                             if(silverGramInput && silverGramInput.value && parseFloat(silverGramInput.value) > 0) updatePrice('silver', 'gram');
                         });
                    </script>

                     <?php break;

                case 'payment_log':
                    require_login();
                    $current_year = date('Y');
                    $filter_year_payment = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);

                    $prefill_amount = filter_input(INPUT_GET, 'amount', FILTER_VALIDATE_FLOAT);
                    ?>
                    <h2><?php echo t('log_zakat_payment'); ?></h2>
                    <form method="post" action="?page=payment_log">
                         <input type="hidden" name="action" value="log_payment">

                         <div class="form-group">
                             <label for="amount"><?php echo t('amount'); ?> (<?php echo t('currency_symbol'); ?>)</label>
                             <input type="number" step="any" min="0.01" id="amount" name="amount" required value="<?php echo $prefill_amount ?? ''; ?>">
                         </div>
                         <div class="form-group">
                             <label for="payment_date"><?php echo t('payment_date'); ?></label>
                             <input type="date" id="payment_date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                         </div>
                         <div class="form-group">
                            <label for="calculation_id"><?php echo t('related_calculation'); ?> (Optional)</label>
                            <select id="calculation_id" name="calculation_id">
                                <option value="">-- Select Calculation --</option>
                                <?php
                                $stmt_calcs = $db->prepare("SELECT id, year, calculation_date, zakat_due FROM zakat_calculations WHERE user_id = ? ORDER BY calculation_date DESC LIMIT 10");
                                $stmt_calcs->execute([$_SESSION['user_id']]);
                                while($calc_row = $stmt_calcs->fetch()) {
                                    echo "<option value='{$calc_row['id']}'>";
                                    echo "{$calc_row['year']} / ".date('M d, Y', strtotime($calc_row['calculation_date']))." / ".format_currency((float)$calc_row['zakat_due']);
                                    echo "</option>";
                                }
                                ?>
                            </select>
                             <small>Link this payment to a saved calculation record.</small>
                         </div>
                         <div class="form-group">
                             <label for="recipient"><?php echo t('recipient'); ?></label>
                             <input type="text" id="recipient" name="recipient">
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
                             <textarea id="notes" name="notes" rows="3"></textarea>
                         </div>
                         <button type="submit"><?php echo t('log_payment'); ?></button>
                    </form>

                    <hr style="margin: 30px 0;">

                    <h2><?php echo t('zakat_payment_history'); ?></h2>

                     <form method="get" action="?page=payment_log">
                         <input type="hidden" name="page" value="payment_log">
                         <div class="form-group-inline">
                             <label for="filter_year_payment"><?php echo t('filter_by_year'); ?>:</label>
                             <select name="filter_year" id="filter_year_payment">
                                 <option value=""><?php echo t('all_years'); ?></option>
                                 <?php
                                 $stmt_years = $db->prepare("SELECT DISTINCT strftime('%Y', payment_date) as p_year FROM zakat_payments WHERE user_id = ? AND p_year IS NOT NULL ORDER BY p_year DESC");
                                 $stmt_years->execute([$_SESSION['user_id']]);
                                 while ($yr = $stmt_years->fetchColumn()) {
                                     if ($yr) echo "<option value='$yr' ".($filter_year_payment == $yr ? 'selected' : '').">$yr</option>";
                                 }
                                 ?>
                             </select>
                             <button type="submit"><?php echo t('filter'); ?></button>
                         </div>
                     </form>

                     <div class="responsive-table">
                        <table>
                             <thead>
                                 <tr>
                                     <th><?php echo t('date'); ?></th>
                                     <th><?php echo t('amount'); ?></th>
                                     <th><?php echo t('recipient'); ?></th>
                                     <th><?php echo t('status'); ?></th>
                                     <th><?php echo t('notes'); ?></th>
                                     <th><?php echo t('related_calculation'); ?></th>
                                 </tr>
                             </thead>
                             <tbody>
                                <?php
                                $sql_payments = "SELECT p.*, c.year as calc_year, c.calculation_date as calc_date FROM zakat_payments p LEFT JOIN zakat_calculations c ON p.calculation_id = c.id WHERE p.user_id = ?";
                                $params_payments = [$_SESSION['user_id']];
                                if ($filter_year_payment) {
                                    $sql_payments .= " AND strftime('%Y', p.payment_date) = ?";
                                    $params_payments[] = (string)$filter_year_payment;
                                }
                                $sql_payments .= " ORDER BY p.payment_date DESC";
                                $stmt_payments = $db->prepare($sql_payments);
                                $stmt_payments->execute($params_payments);
                                $payments = $stmt_payments->fetchAll();

                                if (count($payments) > 0):
                                    foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                        <td><?php echo format_currency((float)$payment['amount']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['recipient'] ?? '-'); ?></td>
                                        <td><?php echo t(htmlspecialchars($payment['status'])); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($payment['notes'] ?? '-')); ?></td>
                                        <td>
                                            <?php if ($payment['calculation_id']): ?>
                                                <a href="?page=calculation_history&view_id=<?php echo $payment['calculation_id']; ?>">
                                                     <?php echo t('calculation'); ?> (<?php echo $payment['calc_year'] ?? '?'; ?>)
                                                </a>
                                            <?php else: ?>
                                                -
                                             <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr><td colspan="6"><?php echo t('no_payments_logged'); ?></td></tr>
                                <?php endif; ?>
                             </tbody>
                         </table>
                     </div>
                    <?php break;

                 case 'calculation_history':
                     require_login();
                     $view_id = filter_input(INPUT_GET, 'view_id', FILTER_VALIDATE_INT);

                    if ($view_id) {
                         $stmt = $db->prepare("SELECT * FROM zakat_calculations WHERE id = ? AND user_id = ?");
                         $stmt->execute([$view_id, $_SESSION['user_id']]);
                         $calc = $stmt->fetch();

                         if ($calc) {
                             ?>
                             <h2><?php echo t('calculation_details'); ?> (ID: <?php echo $calc['id']; ?>)</h2>
                             <div class="calculation-result-box">
                                  <p><strong><?php echo t('calculation_date'); ?>:</strong> <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($calc['calculation_date']))); ?></p>
                                  <p><strong><?php echo t('zakat_year'); ?>:</strong> <?php echo htmlspecialchars($calc['year']); ?></p>
                                  <hr>
                                  <p><strong><?php echo t('gold'); ?>:</strong> <?php echo number_format((float)$calc['gold_grams'], 2); ?> g</p>
                                  <p><strong><?php echo t('silver'); ?>:</strong> <?php echo number_format((float)$calc['silver_grams'], 2); ?> g</p>
                                  <p><strong><?php echo t('cash_on_hand_bank'); ?>:</strong> <?php echo format_currency((float)$calc['cash']); ?></p>
                                  <p><strong><?php echo t('business_goods_inventory'); ?>:</strong> <?php echo format_currency((float)$calc['business_goods']); ?></p>
                                  <p><strong><?php echo t('short_term_debts'); ?>:</strong> <?php echo format_currency((float)$calc['debts']); ?></p>
                                  <hr>
                                  <p><strong><?php echo t('gold_price'); ?> (<?php echo t('per_gram'); ?>):</strong> <?php echo format_currency((float)$calc['gold_price_per_gram']); ?></p>
                                  <p><strong><?php echo t('silver_price'); ?> (<?php echo t('per_gram'); ?>):</strong> <?php echo format_currency((float)$calc['silver_price_per_gram']); ?></p>
                                  <hr>
                                  <p><strong><?php echo t('total_assets'); ?>:</strong> <?php echo format_currency((float)$calc['total_assets']); ?></p>
                                  <p><strong><?php echo t('zakatable_wealth'); ?>:</strong> <?php echo format_currency((float)$calc['zakatable_wealth']); ?></p>
                                  <p><strong><?php echo t('nisab_threshold'); ?>:</strong> <?php echo t($calc['nisab_threshold_used'].'_nisab_used'); ?></p>
                                  <p><strong><?php echo t('nisab_value_today'); ?>:</strong> <?php echo format_currency((float)$calc['nisab_value']); ?></p>
                                  <hr>
                                  <p style="font-size: 1.2em; font-weight: bold;"><strong><?php echo t('zakat_due'); ?>:</strong> <?php echo format_currency((float)$calc['zakat_due']); ?></p>
                             </div>
                              <p class="action-buttons">
                                  <a href="?page=calculation_history" class="button button-secondary">&laquo; <?php echo t('back_to_history'); ?></a>
                                <?php if ($calc['zakat_due'] > 0): ?>
                                    <a href="?page=payment_log&amount=<?php echo $calc['zakat_due'];?>&calculation_id=<?php echo $calc['id']; ?>" class="button"><?php echo t('log_payment'); ?></a>
                                <?php endif; ?>
                              </p>
                             <?php
                         } else {
                             echo "<div class='error'>Calculation not found or access denied.</div>";
                             echo '<p><a href="?page=calculation_history" class="button button-secondary">&laquo; '.t('back_to_history').'</a></p>';
                         }

                     } else {
                         $filter_year_calc = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);
                         ?>
                         <h2><?php echo t('zakat_calculation_history'); ?></h2>

                          <form method="get" action="?page=calculation_history">
                             <input type="hidden" name="page" value="calculation_history">
                             <div class="form-group-inline">
                                 <label for="filter_year_calc"><?php echo t('filter_by_year'); ?>:</label>
                                 <select name="filter_year" id="filter_year_calc">
                                     <option value=""><?php echo t('all_years'); ?></option>
                                     <?php
                                     $stmt_years_calc = $db->prepare("SELECT DISTINCT year FROM zakat_calculations WHERE user_id = ? ORDER BY year DESC");
                                     $stmt_years_calc->execute([$_SESSION['user_id']]);
                                     while ($yr_calc = $stmt_years_calc->fetchColumn()) {
                                         if ($yr_calc) echo "<option value='$yr_calc' ".($filter_year_calc == $yr_calc ? 'selected' : '').">$yr_calc</option>";
                                     }
                                     ?>
                                 </select>
                                 <button type="submit"><?php echo t('filter'); ?></button>
                             </div>
                         </form>

                         <div class="responsive-table">
                             <table>
                                 <thead>
                                     <tr>
                                         <th><?php echo t('date'); ?></th>
                                         <th><?php echo t('year'); ?></th>
                                         <th><?php echo t('zakatable_wealth'); ?></th>
                                         <th><?php echo t('zakat_due'); ?></th>
                                         <th><?php echo t('action'); ?></th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     <?php
                                     $sql_calcs = "SELECT id, calculation_date, year, zakatable_wealth, zakat_due FROM zakat_calculations WHERE user_id = ?";
                                     $params_calcs = [$_SESSION['user_id']];
                                     if ($filter_year_calc) {
                                         $sql_calcs .= " AND year = ?";
                                         $params_calcs[] = $filter_year_calc;
                                     }
                                     $sql_calcs .= " ORDER BY calculation_date DESC";
                                     $stmt_calcs = $db->prepare($sql_calcs);
                                     $stmt_calcs->execute($params_calcs);
                                     $calculations = $stmt_calcs->fetchAll();

                                     if (count($calculations) > 0):
                                         foreach ($calculations as $calculation): ?>
                                         <tr>
                                             <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($calculation['calculation_date']))); ?></td>
                                             <td><?php echo htmlspecialchars($calculation['year']); ?></td>
                                             <td><?php echo format_currency((float)$calculation['zakatable_wealth']); ?></td>
                                             <td><?php echo format_currency((float)$calculation['zakat_due']); ?></td>
                                             <td><a href="?page=calculation_history&view_id=<?php echo $calculation['id']; ?>" class="button button-secondary"><?php echo t('view'); ?></a></td>
                                         </tr>
                                         <?php endforeach;
                                     else: ?>
                                         <tr><td colspan="5"><?php echo t('no_calculations_found'); ?></td></tr>
                                     <?php endif; ?>
                                 </tbody>
                             </table>
                         </div>
                         <?php
                     }
                     break;

                 case 'profile':
                    require_login();
                    ?>
                    <h2><?php echo t('user_profile'); ?></h2>
                    <form method="post" action="?page=profile">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label for="username_disabled"><?php echo t('username'); ?></label>
                            <input type="text" id="username_disabled" name="username_disabled" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" disabled>
                            <small>Username cannot be changed.</small>
                        </div>
                         <div class="form-group">
                            <label for="email"><?php echo t('email'); ?></label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>">
                        </div>
                         <div class="form-group">
                            <label for="zakat_due_date"><?php echo t('zakat_due_date'); ?></label>
                            <input type="text" id="zakat_due_date" name="zakat_due_date" value="<?php echo htmlspecialchars($_SESSION['user_zakat_due_date'] ?? ''); ?>">
                            <small>Enter date (e.g., YYYY-MM-DD) or description (e.g., 1st Ramadan)</small>
                        </div>
                         <hr>
                        <h3><?php echo t('update_password'); ?></h3>
                         <div class="form-group">
                            <label for="current_password"><?php echo t('password'); ?> (Current)</label>
                            <input type="password" id="current_password" name="current_password">
                             <small>Required only if changing password.</small>
                        </div>
                         <div class="form-group">
                            <label for="new_password"><?php echo t('new_password'); ?></label>
                            <input type="password" id="new_password" name="new_password">
                        </div>
                         <div class="form-group">
                            <label for="confirm_new_password"><?php echo t('confirm_password'); ?> (New)</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password">
                        </div>

                        <button type="submit"><?php echo t('update_profile'); ?></button>
                    </form>
                    <?php break;

                case 'admin_dashboard':
                    require_admin();
                    ?>
                    <h2><?php echo t('admin_dashboard'); ?></h2>
                     <p class="action-buttons">
                        <a href="?page=admin_users" class="button"><?php echo t('user_management'); ?></a>
                        <a href="?page=admin_logs" class="button"><?php echo t('all_zakat_logs'); ?></a>
                        <a href="?page=admin_settings" class="button button-secondary"><?php echo t('settings'); ?></a>
                    </p>
                    <?php break;

                case 'admin_users':
                    require_admin();
                    ?>
                    <h2><?php echo t('manage_users'); ?></h2>
                    <p><a href="?page=admin_add_user" class="button"><?php echo t('add_user'); ?></a></p>

                    <div class="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo t('id'); ?></th>
                                    <th><?php echo t('username'); ?></th>
                                    <th><?php echo t('email'); ?></th>
                                    <th><?php echo t('role'); ?></th>
                                    <th><?php echo t('last_login'); ?></th>
                                    <th><?php echo t('created_at'); ?></th>
                                    <th><?php echo t('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt_users = $db->query("SELECT id, username, email, role, last_login, created_at FROM users ORDER BY id ASC");
                                while ($user_row = $stmt_users->fetch()): ?>
                                <tr>
                                    <td><?php echo $user_row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user_row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user_row['email']); ?></td>
                                    <td><?php echo t(htmlspecialchars($user_row['role'])); ?></td>
                                    <td><?php echo $user_row['last_login'] ? htmlspecialchars(date('M d, Y H:i', strtotime($user_row['last_login']))) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user_row['created_at']))); ?></td>
                                    <td class="action-buttons">
                                        <a href="?page=admin_edit_user&user_id=<?php echo $user_row['id']; ?>" class="button button-secondary"><?php echo t('edit'); ?></a>
                                        <?php if ($user_row['id'] !== $_SESSION['user_id']): ?>
                                        <a href="?page=admin_users&action=admin_delete_user&user_id=<?php echo $user_row['id']; ?>"
                                           class="button button-danger"
                                           onclick="return confirm('<?php echo t('confirm_delete'); ?>');">
                                            <?php echo t('delete'); ?>
                                        </a>
                                         <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php break;

                case 'admin_add_user':
                     require_admin();
                    ?>
                    <h2><?php echo t('add_user'); ?></h2>
                    <form method="post" action="?page=admin_add_user">
                         <input type="hidden" name="action" value="admin_add_user">
                         <div class="form-group">
                            <label for="username"><?php echo t('username'); ?></label>
                            <input type="text" id="username" name="username" required value="<?php echo sanitize_input($_POST['username'] ?? ''); ?>">
                        </div>
                         <div class="form-group">
                            <label for="email"><?php echo t('email'); ?></label>
                            <input type="email" id="email" name="email" required value="<?php echo sanitize_input($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><?php echo t('password'); ?></label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="role"><?php echo t('role'); ?></label>
                            <select id="role" name="role" required>
                                <option value="user" <?php echo (($_POST['role'] ?? 'user') === 'user' ? 'selected' : ''); ?>><?php echo t('user'); ?></option>
                                <option value="admin" <?php echo (($_POST['role'] ?? '') === 'admin' ? 'selected' : ''); ?>><?php echo t('admin'); ?></option>
                            </select>
                        </div>
                         <button type="submit"><?php echo t('add_user'); ?></button>
                         <a href="?page=admin_users" class="button button-secondary"><?php echo t('cancel'); ?></a>
                    </form>
                    <?php break;

                 case 'admin_edit_user':
                     require_admin();
                     $edit_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
                     $user_to_edit = null;
                     if ($edit_user_id) {
                        $stmt_edit = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
                        $stmt_edit->execute([$edit_user_id]);
                        $user_to_edit = $stmt_edit->fetch();
                     }

                     if (!$user_to_edit) {
                        echo "<div class='error'>User not found.</div>";
                        echo '<p><a href="?page=admin_users" class="button button-secondary">&laquo; '.t('back_to_users').'</a></p>';
                        break;
                     }
                     // If form was submitted with errors, keep the submitted values
                     $form_values = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $user_to_edit;
                    ?>
                    <h2><?php echo t('edit_user'); ?> (ID: <?php echo $user_to_edit['id']; ?>)</h2>
                    <form method="post" action="?page=admin_edit_user&user_id=<?php echo $user_to_edit['id']; ?>">
                         <input type="hidden" name="action" value="admin_update_user">
                         <input type="hidden" name="user_id" value="<?php echo $user_to_edit['id']; ?>">
                         <div class="form-group">
                            <label for="username"><?php echo t('username'); ?></label>
                            <input type="text" id="username" name="username" required value="<?php echo sanitize_input($form_values['username']); ?>">
                        </div>
                         <div class="form-group">
                            <label for="email"><?php echo t('email'); ?></label>
                            <input type="email" id="email" name="email" required value="<?php echo sanitize_input($form_values['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><?php echo t('password_leave_blank'); ?></label>
                            <input type="password" id="password" name="password">
                        </div>
                        <div class="form-group">
                            <label for="role"><?php echo t('role'); ?></label>
                            <select id="role" name="role" required>
                                <option value="user" <?php echo ($form_values['role'] === 'user' ? 'selected' : ''); ?>><?php echo t('user'); ?></option>
                                <option value="admin" <?php echo ($form_values['role'] === 'admin' ? 'selected' : ''); ?>><?php echo t('admin'); ?></option>
                            </select>
                        </div>
                         <button type="submit"><?php echo t('update_user'); ?></button>
                         <a href="?page=admin_users" class="button button-secondary"><?php echo t('cancel'); ?></a>
                    </form>
                    <?php break;

                 case 'admin_logs':
                     require_admin();
                     $filter_user_id_log = filter_input(INPUT_GET, 'filter_user_id', FILTER_VALIDATE_INT);
                     $filter_year_log = filter_input(INPUT_GET, 'filter_year', FILTER_VALIDATE_INT);
                     ?>
                     <h2><?php echo t('all_zakat_logs'); ?></h2>

                     <form method="get" action="?page=admin_logs">
                         <input type="hidden" name="page" value="admin_logs">
                         <div class="form-group-inline">
                             <label for="filter_user_id_log"><?php echo t('filter_by_user'); ?>:</label>
                             <select name="filter_user_id" id="filter_user_id_log">
                                 <option value=""><?php echo t('all_users'); ?></option>
                                 <?php
                                 $stmt_log_users = $db->query("SELECT id, username FROM users ORDER BY username ASC");
                                 while ($log_user = $stmt_log_users->fetch()) {
                                     echo "<option value='{$log_user['id']}' ".($filter_user_id_log == $log_user['id'] ? 'selected' : '').">".htmlspecialchars($log_user['username'])."</option>";
                                 }
                                 ?>
                             </select>

                             <label for="filter_year_log"><?php echo t('filter_by_year'); ?>:</label>
                             <select name="filter_year" id="filter_year_log">
                                 <option value=""><?php echo t('all_years'); ?></option>
                                 <?php
                                 $years_combined = [];
                                 $stmt_years_log_c = $db->query("SELECT DISTINCT year FROM zakat_calculations WHERE year IS NOT NULL");
                                 while ($yr_c = $stmt_years_log_c->fetchColumn()) { if($yr_c) $years_combined[(int)$yr_c] = true; }
                                 $stmt_years_log_p = $db->query("SELECT DISTINCT strftime('%Y', payment_date) as p_year FROM zakat_payments WHERE p_year IS NOT NULL");
                                 while ($yr_p = $stmt_years_log_p->fetchColumn()) { if($yr_p) $years_combined[(int)$yr_p] = true; }
                                 krsort($years_combined);
                                 foreach (array_keys($years_combined) as $yr_log) {
                                     echo "<option value='$yr_log' ".($filter_year_log == $yr_log ? 'selected' : '').">$yr_log</option>";
                                 }
                                 ?>
                             </select>
                             <button type="submit"><?php echo t('filter'); ?></button>
                             <a href="?page=admin_logs&action=admin_export_csv&filter_user_id=<?php echo $filter_user_id_log; ?>&filter_year=<?php echo $filter_year_log; ?>" class="button button-secondary" target="_blank"><?php echo t('export_csv'); ?></a>
                         </div>
                     </form>

                     <div class="responsive-table">
                         <table>
                             <thead>
                                 <tr>
                                     <th><?php echo t('id'); ?></th>
                                     <th><?php echo t('username'); ?></th>
                                     <th><?php echo t('type'); ?></th>
                                     <th><?php echo t('date'); ?></th>
                                     <th><?php echo t('year'); ?></th>
                                     <th><?php echo t('amount'); ?>/<?php echo t('zakat_due'); ?></th>
                                     <th><?php echo t('details'); ?></th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php
                                 $logs = [];
                                 $params_logs = [];
                                 $sql_logs_base = " WHERE 1=1 ";
                                 if ($filter_user_id_log) {
                                     $sql_logs_base .= " AND user_id = ? ";
                                     $params_logs[] = $filter_user_id_log;
                                 }

                                 $sql_calc = "SELECT 'C' || id as log_id, user_id, '".t('calculation')."' as type, calculation_date as date, year, zakat_due as amount, id as detail_id FROM zakat_calculations " . $sql_logs_base;
                                 $params_calc = $params_logs;
                                 if ($filter_year_log) { $sql_calc .= " AND year = ? "; $params_calc[] = $filter_year_log; }
                                 $stmt_calc_logs = $db->prepare($sql_calc);
                                 $stmt_calc_logs->execute($params_calc);
                                 while($row = $stmt_calc_logs->fetch()) $logs[$row['date'] . '_' . $row['log_id']] = $row; // Use date+id as key for sorting


                                 $sql_pay = "SELECT 'P' || id as log_id, user_id, '".t('payment')."' as type, payment_date as date, strftime('%Y', payment_date) as year, amount, id as detail_id FROM zakat_payments " . $sql_logs_base;
                                 $params_pay = $params_logs;
                                 if ($filter_year_log) { $sql_pay .= " AND strftime('%Y', payment_date) = ? "; $params_pay[] = (string)$filter_year_log; }
                                 $stmt_pay_logs = $db->prepare($sql_pay);
                                 $stmt_pay_logs->execute($params_pay);
                                 while($row = $stmt_pay_logs->fetch()) $logs[$row['date'] . '_' . $row['log_id']] = $row; // Use date+id as key for sorting

                                 // Fetch usernames separately to avoid complex UNION query issues
                                 $user_ids = array_unique(array_filter(array_column($logs, 'user_id')));
                                 $usernames = [];
                                 if (!empty($user_ids)) {
                                    $in_q = implode(',', array_fill(0, count($user_ids), '?'));
                                    $stmt_un = $db->prepare("SELECT id, username FROM users WHERE id IN ($in_q)");
                                    $stmt_un->execute($user_ids);
                                    while ($u = $stmt_un->fetch()) {
                                        $usernames[$u['id']] = $u['username'];
                                    }
                                 }

                                 krsort($logs); // Sort combined logs by key (date_logid) descending

                                 if (count($logs) > 0):
                                     foreach ($logs as $log): ?>
                                     <tr>
                                         <td><?php echo htmlspecialchars($log['log_id']); ?></td>
                                         <td><?php echo htmlspecialchars($usernames[$log['user_id']] ?? t('user').' '.$log['user_id'] ?? 'Public'); ?></td>
                                         <td><?php echo htmlspecialchars($log['type']); ?></td>
                                         <td><?php echo htmlspecialchars($log['date']); ?></td>
                                         <td><?php echo htmlspecialchars($log['year'] ?? '-'); ?></td>
                                         <td><?php echo format_currency((float)$log['amount']); ?></td>
                                         <td class="action-buttons">
                                            <?php if (strpos($log['log_id'], 'C') === 0): ?>
                                                <a href="?page=admin_logs&view_calc_id=<?php echo $log['detail_id']; ?>&filter_user_id=<?php echo $filter_user_id_log; ?>&filter_year=<?php echo $filter_year_log; ?>" class="button button-secondary"><?php echo t('view'); ?></a>
                                            <?php elseif (strpos($log['log_id'], 'P') === 0): ?>
                                                 <a href="?page=admin_logs&view_pay_id=<?php echo $log['detail_id']; ?>&filter_user_id=<?php echo $filter_user_id_log; ?>&filter_year=<?php echo $filter_year_log; ?>" class="button button-secondary"><?php echo t('view'); ?></a>
                                             <?php endif; ?>
                                         </td>
                                     </tr>
                                     <?php endforeach;
                                 else: ?>
                                     <tr><td colspan="7"><?php echo t('no_logs_found'); ?></td></tr>
                                 <?php endif; ?>
                             </tbody>
                         </table>
                     </div>

                     <?php
                     $view_calc_id = filter_input(INPUT_GET, 'view_calc_id', FILTER_VALIDATE_INT);
                     $view_pay_id = filter_input(INPUT_GET, 'view_pay_id', FILTER_VALIDATE_INT);

                     if ($view_calc_id):
                         $stmt_c = $db->prepare("SELECT c.*, u.username FROM zakat_calculations c LEFT JOIN users u ON c.user_id=u.id WHERE c.id = ?");
                         $stmt_c->execute([$view_calc_id]);
                         $calc_detail = $stmt_c->fetch();
                         if($calc_detail): ?>
                            <div class="calculation-result-box" style="margin-top: 20px; border-color: #007bff; background-color:#e7f5ff;">
                                <h3><?php echo t('calculation_details'); ?> (ID: C<?php echo $calc_detail['id']; ?>)</h3>
                                <p><strong><?php echo t('user'); ?>:</strong> <?php echo htmlspecialchars($calc_detail['username'] ?? 'Public'); ?> (ID: <?php echo $calc_detail['user_id'] ?? 'N/A'; ?>)</p>
                                <p><strong><?php echo t('calculation_date'); ?>:</strong> <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($calc_detail['calculation_date']))); ?></p>
                                <p><strong><?php echo t('zakat_year'); ?>:</strong> <?php echo htmlspecialchars($calc_detail['year']); ?></p>
                                <hr>
                                <p><strong><?php echo t('gold'); ?>:</strong> <?php echo number_format((float)$calc_detail['gold_grams'], 2); ?> g</p>
                                <p><strong><?php echo t('silver'); ?>:</strong> <?php echo number_format((float)$calc_detail['silver_grams'], 2); ?> g</p>
                                <p><strong><?php echo t('cash_on_hand_bank'); ?>:</strong> <?php echo format_currency((float)$calc_detail['cash']); ?></p>
                                <p><strong><?php echo t('business_goods_inventory'); ?>:</strong> <?php echo format_currency((float)$calc_detail['business_goods']); ?></p>
                                <p><strong><?php echo t('short_term_debts'); ?>:</strong> <?php echo format_currency((float)$calc_detail['debts']); ?></p>
                                <hr>
                                <p><strong><?php echo t('gold_price'); ?> (<?php echo t('per_gram'); ?>):</strong> <?php echo format_currency((float)$calc_detail['gold_price_per_gram']); ?></p>
                                <p><strong><?php echo t('silver_price'); ?> (<?php echo t('per_gram'); ?>):</strong> <?php echo format_currency((float)$calc_detail['silver_price_per_gram']); ?></p>
                                <hr>
                                <p><strong><?php echo t('total_assets'); ?>:</strong> <?php echo format_currency((float)$calc_detail['total_assets']); ?></p>
                                <p><strong><?php echo t('zakatable_wealth'); ?>:</strong> <?php echo format_currency((float)$calc_detail['zakatable_wealth']); ?></p>
                                <p><strong><?php echo t('nisab_threshold'); ?>:</strong> <?php echo t($calc_detail['nisab_threshold_used'].'_nisab_used'); ?></p>
                                <p><strong><?php echo t('nisab_value_today'); ?>:</strong> <?php echo format_currency((float)$calc_detail['nisab_value']); ?></p>
                                <hr>
                                <p style="font-size: 1.2em; font-weight: bold;"><strong><?php echo t('zakat_due'); ?>:</strong> <?php echo format_currency((float)$calc_detail['zakat_due']); ?></p>
                                <a href="?page=admin_logs&filter_user_id=<?php echo $filter_user_id_log; ?>&filter_year=<?php echo $filter_year_log; ?>" class="button button-secondary"><?php echo t('close_details'); ?></a>
                            </div>
                         <?php endif; ?>
                     <?php elseif ($view_pay_id):
                          $stmt_p = $db->prepare("SELECT p.*, u.username, c.year as calc_year, c.calculation_date as calc_date FROM zakat_payments p JOIN users u ON p.user_id=u.id LEFT JOIN zakat_calculations c ON p.calculation_id = c.id WHERE p.id = ?");
                          $stmt_p->execute([$view_pay_id]);
                          $pay_detail = $stmt_p->fetch();
                         if($pay_detail): ?>
                             <div class="calculation-result-box" style="margin-top: 20px; border-color: #28a745; background-color:#dff0d8;">
                                 <h3><?php echo t('payment_details'); ?> (ID: P<?php echo $pay_detail['id']; ?>)</h3>
                                 <p><strong><?php echo t('user'); ?>:</strong> <?php echo htmlspecialchars($pay_detail['username']); ?> (ID: <?php echo $pay_detail['user_id']; ?>)</p>
                                 <p><strong><?php echo t('payment_date'); ?>:</strong> <?php echo htmlspecialchars($pay_detail['payment_date']); ?></p>
                                 <p><strong><?php echo t('amount'); ?>:</strong> <?php echo format_currency((float)$pay_detail['amount']); ?></p>
                                 <p><strong><?php echo t('recipient'); ?>:</strong> <?php echo htmlspecialchars($pay_detail['recipient'] ?? '-'); ?></p>
                                 <p><strong><?php echo t('status'); ?>:</strong> <?php echo t(htmlspecialchars($pay_detail['status'])); ?></p>
                                 <p><strong><?php echo t('notes'); ?>:</strong> <?php echo nl2br(htmlspecialchars($pay_detail['notes'] ?? '-')); ?></p>
                                 <p><strong><?php echo t('related_calculation'); ?>:</strong>
                                     <?php if ($pay_detail['calculation_id']): ?>
                                         <a href="?page=admin_logs&view_calc_id=<?php echo $pay_detail['calculation_id']; ?>&filter_user_id=<?php echo $filter_user_id_log; ?>&filter_year=<?php echo $filter_year_log; ?>">
                                             <?php echo t('calculation'); ?> (<?php echo $pay_detail['calc_year'] ?? '?'; ?>)
                                         </a>
                                     <?php else: ?>
                                         -
                                     <?php endif; ?>
                                 </p>
                                 <p><small><strong>Logged At:</strong> <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($pay_detail['logged_at']))); ?></small></p>
                                 <a href="?page=admin_logs&filter_user_id=<?php echo $filter_user_id_log; ?>&filter_year=<?php echo $filter_year_log; ?>" class="button button-secondary"><?php echo t('close_details'); ?></a>
                             </div>
                         <?php endif; ?>
                    <?php endif; ?>

                     <?php break;

                 case 'admin_settings':
                     require_admin();
                     ?>
                     <h2><?php echo t('application_settings'); ?></h2>
                     <form method="post" action="?page=admin_settings">
                         <input type="hidden" name="action" value="admin_save_settings">

                         <h3><?php echo t('nisab_settings'); ?></h3>
                          <div class="form-group">
                             <label for="nisab_gold_tolas"><?php echo t('nisab_gold_tola'); ?></label>
                             <input type="number" step="any" min="0" id="nisab_gold_tolas" name="nisab_gold_tolas" required value="<?php echo get_setting('nisab_gold_tolas', NISAB_GOLD_TOLAS); ?>">
                         </div>
                          <div class="form-group">
                             <label for="nisab_silver_tolas"><?php echo t('nisab_silver_tola'); ?></label>
                             <input type="number" step="any" min="0" id="nisab_silver_tolas" name="nisab_silver_tolas" required value="<?php echo get_setting('nisab_silver_tolas', NISAB_SILVER_TOLAS); ?>">
                         </div>
                          <div class="form-group">
                             <label for="grams_per_tola"><?php echo t('grams_per_tola_setting'); ?></label>
                             <input type="number" step="any" min="0.1" id="grams_per_tola" name="grams_per_tola" required value="<?php echo get_setting('grams_per_tola', GRAMS_PER_TOLA); ?>">
                         </div>

                         <h3><?php echo t('default_market_prices'); ?></h3>
                         <div class="form-group">
                             <label for="default_gold_price_per_gram"><?php echo t('default_gold_price_per_gram'); ?></label>
                             <input type="number" step="any" min="0" id="default_gold_price_per_gram" name="default_gold_price_per_gram" required value="<?php echo get_setting('default_gold_price_per_gram', 0); ?>">
                         </div>
                         <div class="form-group">
                             <label for="default_silver_price_per_gram"><?php echo t('default_silver_price_per_gram'); ?></label>
                             <input type="number" step="any" min="0" id="default_silver_price_per_gram" name="default_silver_price_per_gram" required value="<?php echo get_setting('default_silver_price_per_gram', 0); ?>">
                         </div>

                         <button type="submit"><?php echo t('save_settings'); ?></button>
                     </form>
                     <?php break;


                case 'home':
                default:
                    ?>
                    <h2><?php echo t('info_page_title'); ?></h2>
                    <div class="info-content">
                       <?php echo t('info_page_content'); ?>
                    </div>
                    <hr>
                    <h3><?php echo t('actions'); ?></h3>
                     <p class="action-buttons">
                        <a href="?page=public_calculator" class="button"><?php echo t('public_calculator'); ?></a>
                        <?php if (is_logged_in()): ?>
                             <a href="?page=dashboard" class="button button-secondary"><?php echo t('dashboard'); ?></a>
                        <?php else: ?>
                             <a href="?page=login" class="button button-secondary"><?php echo t('login'); ?></a>
                             <a href="?page=register" class="button button-secondary"><?php echo t('register'); ?></a>
                        <?php endif; ?>
                     </p>
                    <?php break;

            endswitch; ?>
        </main>
    </div>

    <footer>
        <p><?php echo t('disclaimer'); ?></p>
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>.</p>
    </footer>

</body>
</html>
<?php $db = null; ?>