<?php
/**
 * Plugin Name: Topic Cluster Linker
 * Description: ピラーページとクラスターページの内部リンクを自動提案・挿入します。SEO内部リンク強化プラグイン。
 * Version: 1.0.0
 * Author: あなた
 * Text Domain: topic-cluster-linker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数定義
define('TCL_VERSION', '1.0.0');
define('TCL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TCL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('TCL_PLUGIN_FILE', __FILE__);

/**
 * プラグインの初期化
 */
class TopicClusterLinker {
    
    public function __construct() {
        $this->init();
    }
    
    /**
     * プラグイン初期化
     */
    private function init() {
        // 必要なファイルを読み込み
        $this->load_dependencies();
        
        // フックを登録
        $this->setup_hooks();
        
        // 国際化
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }
    
    /**
     * 依存ファイルの読み込み
     */
    private function load_dependencies() {
        // 必須ファイルを正しい順序で読み込み
        $required_files = [
            'includes/logger.php',              // ログ機能（最初に読み込み）
            'includes/propose-cluster.php',     // クラスター提案機能
            'admin/settings-page.php',          // 設定画面
            'includes/keyword-suggester.php',   // キーワード提案機能
            'admin/metabox.php',               // メタボックス（最後に読み込み）
        ];
        
        foreach ($required_files as $file) {
            $file_path = TCL_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                // ログ機能が読み込まれてからログを記録
                if (function_exists('tcl_log_message')) {
                    tcl_log_message("ファイル読み込み成功: {$file}");
                }
            } else {
                add_action('admin_notices', function() use ($file) {
                    echo '<div class="notice notice-error"><p>';
                    echo sprintf('Topic Cluster Linker: 必要なファイル %s が見つかりません。', esc_html($file));
                    echo '</p></div>';
                });
                
                if (function_exists('tcl_log_message')) {
                    tcl_log_message("ファイル読み込み失敗: {$file}");
                }
            }
        }
    }
    
    /**
     * WordPressフックの設定
     */
    private function setup_hooks() {
        // プラグイン有効化・無効化
        register_activation_hook(TCL_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(TCL_PLUGIN_FILE, [$this, 'deactivate']);
        
        // 管理画面でのスクリプト・スタイル読み込み
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX処理（ログインユーザー用）
        add_action('wp_ajax_tcl_regenerate_link', 'tcl_ajax_regenerate_link');
        
        // 管理者通知
        add_action('admin_notices', [$this, 'admin_notices']);
    }
    
    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // 初期オプションを設定
        $this->setup_default_options();
        
        // ACFプラグインの確認
        if (!function_exists('get_field')) {
            add_option('tcl_show_acf_notice', true);
        }
        
        // データベーステーブルの作成（将来の拡張用）
        $this->create_tables();
        
        // ログ記録
        if (function_exists('tcl_log_message')) {
            tcl_log_message('Topic Cluster Linker プラグインが有効化されました - Version: ' . TCL_VERSION);
        }
        
        // フラッシュリライトルール（必要に応じて）
        flush_rewrite_rules();
    }
    
    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        // 一時的なオプションをクリア
        delete_option('tcl_show_acf_notice');
        
        // ログ記録
        if (function_exists('tcl_log_message')) {
            tcl_log_message('Topic Cluster Linker プラグインが無効化されました');
        }
        
        // フラッシュリライトルール
        flush_rewrite_rules();
    }
    
    /**
     * 初期オプションの設定
     */
    private function setup_default_options() {
        $default_options = [
            'tcl_api_key' => '',
            'tcl_cluster_proposals' => [],
            'tcl_max_links_per_post' => 2,
            'tcl_auto_suggest' => true,
            'tcl_log_level' => 'info',
            'tcl_version' => TCL_VERSION,
        ];
        
        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * データベーステーブル作成（将来の拡張用）
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // リンク履歴テーブル（将来使用予定）
        $table_name = $wpdb->prefix . 'tcl_link_history';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            target_post_id bigint(20) NOT NULL,
            link_text text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY target_post_id (target_post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 管理画面アセットの読み込み
     */
    public function enqueue_admin_assets($hook) {
        // 投稿編集画面のみ
        if (!in_array($hook, ['post.php', 'post-new.php', 'toplevel_page_topic-cluster-linker'])) {
            return;
        }
        
        // JavaScript
        wp_enqueue_script(
            'tcl-admin',
            TCL_PLUGIN_URL . 'tcl-admin.js',
            ['jquery'],
            TCL_VERSION,
            true
        );
        
        // JavaScript用データ
        wp_localize_script('tcl-admin', 'tcl_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcl_ajax_nonce'),
            'max_links' => get_option('tcl_max_links_per_post', 2),
            'messages' => [
                'insert_success' => __('✅ SEO内部リンクを挿入しました', 'topic-cluster-linker'),
                'insert_error' => __('❌ リンクの挿入に失敗しました', 'topic-cluster-linker'),
                'generating' => __('🤖 GPT生成中...', 'topic-cluster-linker'),
                'regenerate' => __('🔄 再生成', 'topic-cluster-linker'),
                'communication_error' => __('❌ 通信エラーが発生しました', 'topic-cluster-linker'),
            ]
        ]);
        
        // 管理画面用CSS（もしファイルが存在すれば）
        $css_file = TCL_PLUGIN_URL . 'admin/css/admin.css';
        if (file_exists(TCL_PLUGIN_DIR . 'admin/css/admin.css')) {
            wp_enqueue_style(
                'tcl-admin',
                $css_file,
                [],
                TCL_VERSION
            );
        }
    }
    
    /**
     * 管理者通知
     */
    public function admin_notices() {
        // ACFプラグイン未インストール通知
        if (get_option('tcl_show_acf_notice')) {
            if (!function_exists('get_field')) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Topic Cluster Linker:</strong> ';
                echo 'このプラグインは Advanced Custom Fields (ACF) プラグインを必要とします。';
                echo ' <a href="' . admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term') . '">今すぐインストール</a>';
                echo '</p>';
                echo '</div>';
            } else {
                delete_option('tcl_show_acf_notice');
            }
        }
        
        // APIキー未設定通知
        if (!get_option('tcl_api_key') && $this->is_tcl_admin_page()) {
            echo '<div class="notice notice-info">';
            echo '<p><strong>Topic Cluster Linker:</strong> ';
            echo 'ChatGPT APIキーを設定して、自動リンクテキスト生成機能を有効化してください。';
            echo ' <a href="' . admin_url('admin.php?page=topic-cluster-linker') . '">設定画面</a>';
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * TCL関連の管理画面かチェック
     */
    private function is_tcl_admin_page() {
        $screen = get_current_screen();
        return $screen && (
            strpos($screen->id, 'topic-cluster-linker') !== false ||
            in_array($screen->id, ['post', 'local_trouble'])
        );
    }
    
    /**
     * 国際化ファイルの読み込み ← この部分が抜けていました
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'topic-cluster-linker',
            false,
            dirname(TCL_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * プラグイン情報を取得
     */
    public static function get_plugin_info() {
        return [
            'version' => TCL_VERSION,
            'dir' => TCL_PLUGIN_DIR,
            'url' => TCL_PLUGIN_URL,
            'basename' => TCL_PLUGIN_BASENAME,
        ];
    }
    
    /**
     * デバッグ情報
     */
    public static function debug_info() {
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'acf_active' => function_exists('get_field'),
            'api_key_set' => !empty(get_option('tcl_api_key')),
            'proposals_count' => count(get_option('tcl_cluster_proposals', [])),
        ];
    }
}

// プラグインのインスタンスを作成
new TopicClusterLinker();

/**
 * ユーティリティ関数
 */

/**
 * プラグイン情報を取得するヘルパー関数
 */
function tcl_get_plugin_info() {
    return TopicClusterLinker::get_plugin_info();
}

/**
 * デバッグ情報を取得するヘルパー関数
 */
function tcl_debug_info() {
    return TopicClusterLinker::debug_info();
}

/**
 * 現在のプラグインバージョンを取得
 */
function tcl_get_version() {
    return TCL_VERSION;
}

/**
 * プラグインの互換性チェック
 */
function tcl_check_compatibility() {
    $errors = [];
    
    // PHP バージョンチェック
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = 'PHP 7.4以上が必要です。現在: ' . PHP_VERSION;
    }
    
    // WordPress バージョンチェック
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        $errors[] = 'WordPress 5.0以上が必要です。現在: ' . get_bloginfo('version');
    }
    
    // ACF プラグインチェック
    if (!function_exists('get_field')) {
        $errors[] = 'Advanced Custom Fields プラグインが必要です。';
    }
    
    return $errors;
}

/**
 * プラグインアンインストール時の処理
 */
function tcl_uninstall() {
    // オプションの削除
    $options_to_delete = [
        'tcl_api_key',
        'tcl_cluster_proposals',
        'tcl_max_links_per_post',
        'tcl_auto_suggest',
        'tcl_log_level',
        'tcl_version',
        'tcl_show_acf_notice',
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // データベーステーブルの削除
    global $wpdb;
    $table_name = $wpdb->prefix . 'tcl_link_history';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // ログファイルの削除
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit($upload_dir['basedir']) . 'topic-cluster-log.txt';
    if (file_exists($log_file)) {
        unlink($log_file);
    }
}

// アンインストールフック
register_uninstall_hook(__FILE__, 'tcl_uninstall');