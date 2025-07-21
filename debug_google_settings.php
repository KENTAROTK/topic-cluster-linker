<?php
// WordPressを読み込み（パス修正）
require_once('../../../wp-config.php');

echo "=== Google Ads API 設定値確認 ===<br>";
echo "Developer Token: " . (get_option('tcl_google_developer_token') ?: '未設定') . "<br>";
echo "Client ID: " . (get_option('tcl_google_client_id') ?: '未設定') . "<br>";  
echo "Client Secret: " . (get_option('tcl_google_client_secret') ? '設定済み（非表示）' : '未設定') . "<br>";
echo "Refresh Token: " . (get_option('tcl_google_refresh_token') ? '設定済み（非表示）' : '未設定') . "<br>";
echo "Customer ID: " . (get_option('tcl_google_customer_id') ?: '未設定') . "<br>";
echo "Language ID: " . (get_option('tcl_google_language_id') ?: '未設定') . "<br>";
echo "Location ID: " . (get_option('tcl_google_location_id') ?: '未設定') . "<br>";

echo "<br>=== オプション名確認 ===<br>";
// 全ての tcl_ で始まるオプションを確認
global $wpdb;
$results = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'tcl_%'");
foreach($results as $result) {
    echo $result->option_name . "<br>";
}
?>