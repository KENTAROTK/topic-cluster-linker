<?php
/**
 * 管理画面設定ページ
 */

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * メイン設定ページの追加
 */
function tcl_add_admin_menu() {
    add_menu_page(
        'Topic Cluster Linker',
        'Topic Cluster Linker', 
        'manage_options',
        'topic-cluster-linker',
        'tcl_admin_page',
        'dashicons-admin-links',
        30
    );
}
add_action('admin_menu', 'tcl_add_admin_menu');

/**
 * メイン管理画面の表示
 */
function tcl_admin_page() {
    // 設定保存処理
    if (isset($_POST['submit'])) {
        tcl_save_settings();
    }
    
    // Composer操作処理
    if (isset($_POST['tcl_composer_nonce']) && wp_verify_nonce($_POST['tcl_composer_nonce'], 'tcl_composer_install')) {
        if (isset($_POST['tcl_install_composer_libs'])) {
            tcl_auto_install_composer_libs();
        } elseif (isset($_POST['tcl_update_composer_libs'])) {
            tcl_auto_update_composer_libs();
        } elseif (isset($_POST['tcl_remove_composer_libs'])) {
            tcl_remove_composer_libs();
        }
    }
    
    // クラスター再提案処理（既存機能を保持）
    if (isset($_POST['tcl_propose_clusters'])) {
        if (function_exists('tcl_run_propose_clusters')) {
            tcl_run_propose_clusters();
            echo '<div class="notice notice-success"><p>✅ クラスターページの再提案が完了しました。</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1>Topic Cluster Linker 設定</h1>
        
        <!-- タブメニュー -->
        <nav class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active">基本設定</a>
            <a href="#clusters" class="nav-tab">クラスター管理</a>
            <a href="#google-api" class="nav-tab">Google API</a>
            <a href="#composer" class="nav-tab">Composer</a>
            <a href="#status" class="nav-tab">ステータス</a>
        </nav>
        
        <!-- 基本設定タブ -->
        <div id="general" class="tab-content active">
            <form method="post" action="">
                <?php wp_nonce_field('tcl_settings', 'tcl_settings_nonce'); ?>
                
                <h2>📋 基本設定</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">ChatGPT APIキー</th>
                        <td>
                            <input type="password" name="tcl_api_key" 
                                   value="<?php echo esc_attr(get_option('tcl_api_key')); ?>" 
                                   class="regular-text" placeholder="sk-proj-ChatGPTのAPIキー..." />
                            <p class="description">OpenAI APIキーを入力してください。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">SerpAPI キー（オプション）</th>
                        <td>
                            <input type="text" name="tcl_serpapi_key" 
                                   value="<?php echo esc_attr(get_option('tcl_serpapi_key')); ?>" 
                                   class="regular-text" placeholder="オプション: より高品質な検索データ取得" />
                            <p class="description">SerpAPIキーを設定すると、より精度の高い関連キーワードが取得できます。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">1記事あたりの最大リンク数</th>
                        <td>
                            <input type="number" name="tcl_max_links_per_post" 
                                   value="<?php echo esc_attr(get_option('tcl_max_links_per_post', 2)); ?>" 
                                   min="1" max="10" class="small-text" />
                            <p class="description">1つの記事に挿入する内部リンクの最大数</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">自動提案</th>
                        <td>
                            <label>
                                <input type="checkbox" name="tcl_auto_suggest" value="1" 
                                       <?php checked(get_option('tcl_auto_suggest', true)); ?> />
                                記事編集時に自動でリンク提案を表示
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('設定を保存'); ?>
            </form>
        </div>
        
        <!-- クラスター管理タブ（既存機能を保持） -->
        <div id="clusters" class="tab-content">
            <h2>🎯 クラスター管理</h2>
            
            <div class="notice notice-info">
                <p><strong>設定方法：</strong></p>
                <ol>
                    <li>ACFで「pillar_keywords」フィールドを作成し、ピラーページに設定してください</li>
                    <li>ピラーページの「pillar_keywords」フィールドに関連キーワードを「、」区切りで入力</li>
                    <li>下記「クラスターページ再提案」ボタンでクラスターページを自動提案</li>
                    <li>投稿編集画面でリンクを挿入（1投稿あたり2個まで）</li>
                </ol>
            </div>
            
            <form method="post">
                <input type="submit" name="tcl_propose_clusters" class="button button-primary" value="🔄 クラスターページ再提案">
            </form>
            
            <hr>
            <h3>ピラーページ別クラスター提案</h3>
            <?php tcl_display_proposals_by_pillar(); ?>
        </div>
        
        <!-- Google API設定タブ -->
        <div id="google-api" class="tab-content">
            <?php tcl_render_google_ads_api_settings(); ?>
        </div>
        
        <!-- Composerタブ -->
        <div id="composer" class="tab-content">
            <?php tcl_render_composer_section(); ?>
        </div>
        
        <!-- ステータスタブ -->
        <div id="status" class="tab-content">
            <?php tcl_render_status_section(); ?>
        </div>
    </div>
    
    <style>
        .nav-tab-wrapper { margin-bottom: 20px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tcl-status-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .tcl-status-good { border-left: 4px solid #46b450; }
        .tcl-status-warning { border-left: 4px solid #ffb900; }
        .tcl-status-error { border-left: 4px solid #dc3232; }
        .tcl-code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        .tcl-install-buttons {
            margin: 20px 0;
        }
        .tcl-install-buttons .button {
            margin-right: 10px;
        }
    </style>
    
    <script>
        jQuery(document).ready(function($) {
            // タブ切り替え機能
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                
                // タブを非アクティブ化
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                
                // クリックされたタブをアクティブ化
                $(this).addClass('nav-tab-active');
                var target = $(this).attr('href');
                $(target).addClass('active');
            });
        });
    </script>
    <?php
}

/**
 * Google Ads API設定セクション
 */
function tcl_render_google_ads_api_settings() {
    ?>
    <div class="tcl-section">
        <h3>🔧 Google Ads API 設定</h3>
        <p>より正確なキーワードデータを取得するためのGoogle Ads API設定</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('tcl_settings', 'tcl_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Developer Token</th>
                    <td>
                        <input type="text" name="tcl_google_developer_token" 
                               value="<?php echo esc_attr(get_option('tcl_google_developer_token')); ?>" 
                               class="regular-text" />
                        <p class="description">Google Ads APIの開発者トークン</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Client ID</th>
                    <td>
                        <input type="text" name="tcl_google_client_id" 
                               value="<?php echo esc_attr(get_option('tcl_google_client_id')); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Client Secret</th>
                    <td>
                        <input type="password" name="tcl_google_client_secret" 
                               value="<?php echo esc_attr(get_option('tcl_google_client_secret')); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Refresh Token</th>
                    <td>
                        <input type="text" name="tcl_google_refresh_token" 
                               value="<?php echo esc_attr(get_option('tcl_google_refresh_token')); ?>" 
                               class="regular-text" />
                        <p class="description">OAuth2認証で取得したリフレッシュトークン</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Customer ID</th>
                    <td>
                        <input type="text" name="tcl_google_customer_id" 
                               value="<?php echo esc_attr(get_option('tcl_google_customer_id')); ?>" 
                               class="regular-text" placeholder="123-456-7890" />
                        <p class="description">Google Ads アカウントのカスタマーID（ハイフン付き）</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">言語ID</th>
                    <td>
                        <input type="number" name="tcl_google_language_id" 
                               value="<?php echo esc_attr(get_option('tcl_google_language_id', 1005)); ?>" 
                               class="small-text" />
                        <p class="description">言語コード（日本語: 1005、英語: 1000）</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">地域ターゲットID</th>
                    <td>
                        <input type="number" name="tcl_google_geo_target_id" 
                               value="<?php echo esc_attr(get_option('tcl_google_geo_target_id', 2392)); ?>" 
                               class="small-text" />
                        <p class="description">地域コード（日本: 2392、アメリカ: 2840）</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Google API設定を保存'); ?>
        </form>
        
        <h4>📋 設定手順</h4>
        <ol>
            <li><a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>でプロジェクトを作成</li>
            <li>Google Ads APIを有効化</li>
            <li>OAuth 2.0 認証情報を作成してClient IDとClient Secretを取得</li>
            <li><a href="https://ads.google.com/" target="_blank">Google Ads</a>でDeveloper Tokenを申請・取得</li>
            <li>OAuth認証フローでRefresh Tokenを取得</li>
            <li>Google AdsアカウントのCustomer IDを確認</li>
        </ol>
        
        <form method="post" action="">
            <?php wp_nonce_field('tcl_settings', 'tcl_test_nonce'); ?>
            <p>
                <input type="submit" name="test_google_ads_api" class="button button-secondary" value="🧪 接続テスト" />
            </p>
        </form>
        
        <?php
        if (isset($_POST['test_google_ads_api']) && wp_verify_nonce($_POST['tcl_test_nonce'], 'tcl_settings')) {
            if (function_exists('tcl_test_google_ads_api_connection')) {
                $test_result = tcl_test_google_ads_api_connection();
                
                if ($test_result['success']) {
                    echo '<div class="notice notice-success"><p>✅ ' . esc_html($test_result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>❌ ' . esc_html($test_result['error']) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-warning"><p>⚠️ テスト機能はkeyword-suggester.phpで定義されています</p></div>';
            }
        }
        ?>
    </div>
    <?php
}

/**
 * Composerセクションの表示
 */
function tcl_render_composer_section() {
    ?>
    <h2>📦 Composer & Google Ads PHPライブラリ</h2>
    <p>Google Ads APIを使用するには、PHPライブラリのインストールが必要です。</p>
    
    <?php
    $vendor_path = TCL_PLUGIN_DIR . 'vendor/autoload.php';
    $is_installed = file_exists($vendor_path);
    ?>
    
    <div class="tcl-status-box <?php echo $is_installed ? 'tcl-status-good' : 'tcl-status-warning'; ?>">
        <h3>📊 インストール状況</h3>
        <?php if ($is_installed): ?>
            <p>✅ <strong>Google Ads PHPライブラリがインストールされています</strong></p>
            <p><code><?php echo esc_html($vendor_path); ?></code></p>
            
            <?php
            // バージョン情報を取得
            try {
                require_once $vendor_path;
                if (class_exists('Google\Ads\GoogleAds\Lib\V17\GoogleAdsClientBuilder')) {
                    echo '<p>🎯 <strong>Google Ads API v17 が利用可能です</strong></p>';
                } else {
                    echo '<p>⚠️ ライブラリはインストールされていますが、正しくロードできません</p>';
                }
            } catch (Exception $e) {
                echo '<p>⚠️ ライブラリロードエラー: ' . esc_html($e->getMessage()) . '</p>';
            }
            ?>
        <?php else: ?>
            <p>⚠️ <strong>Google Ads PHPライブラリがインストールされていません</strong></p>
            <p>自動インストールまたは手動インストールが必要です。</p>
        <?php endif; ?>
    </div>
    
    <!-- 自動インストール -->
    <div class="tcl-status-box">
        <h3>🚀 自動インストール</h3>
        <p>ボタンクリック一つでライブラリを自動インストールします。</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('tcl_composer_install', 'tcl_composer_nonce'); ?>
            
            <div class="tcl-install-buttons">
                <?php if (!$is_installed): ?>
                    <input type="submit" name="tcl_install_composer_libs" 
                           class="button button-primary" 
                           value="🚀 Google Ads PHPライブラリをインストール" 
                           onclick="return confirm('ライブラリをダウンロード・インストールしますか？\n\nこの操作には数分かかる場合があります。');" />
                <?php else: ?>
                    <input type="submit" name="tcl_update_composer_libs" 
                           class="button button-secondary" 
                           value="🔄 ライブラリを更新" 
                           onclick="return confirm('ライブラリを最新版に更新しますか？');" />
                    <input type="submit" name="tcl_remove_composer_libs" 
                           class="button button-secondary" 
                           value="🗑️ ライブラリを削除" 
                           onclick="return confirm('ライブラリを削除しますか？\n\nGoogle Ads API機能が使用できなくなります。');" />
                <?php endif; ?>
            </div>
        </form>
        
        <p class="description">
            <strong>注意事項:</strong><br>
            • インターネット接続が必要です<br>
            • サーバーでのファイル書き込み権限が必要です<br>
            • 失敗する場合は下記の手動インストールをお試しください
        </p>
    </div>
    
    <!-- 手動インストール手順 -->
    <div class="tcl-status-box">
        <h3>📋 手動インストール手順</h3>
        <p>自動インストールが失敗する場合は、以下の手動手順をお試しください。</p>
        
        <h4>方法1: サーバーでComposer実行</h4>
        <div class="tcl-code-block">
<pre>
# サーバーにSSHでログイン
cd <?php echo esc_html(TCL_PLUGIN_DIR); ?>

# composer.jsonファイルを作成
cat > composer.json << 'EOF'
{
    "require": {
        "googleads/google-ads-php": "^v23.0.0"
    },
    "config": {
        "optimize-autoloader": true
    }
}
EOF

# ライブラリをインストール  
composer install --no-dev --optimize-autoloader
</pre>
        </div>
        
        <h4>方法2: ローカル環境でインストール後アップロード</h4>
        <div class="tcl-code-block">
<pre>
# ローカル環境で実行
mkdir tcl-composer-build
cd tcl-composer-build

# composer.jsonを作成（上記と同じ内容）
composer install --no-dev --optimize-autoloader

# vendorフォルダをFTP/SFTPでプラグインディレクトリにアップロード
</pre>
        </div>
    </div>
    <?php
}

/**
 * ステータスセクションの表示
 */
function tcl_render_status_section() {
    ?>
    <h2>📊 システムステータス</h2>
    
    <div class="tcl-status-box">
        <h3>🔧 基本設定</h3>
        <table class="form-table">
            <tr>
                <th>ChatGPT APIキー</th>
                <td>
                    <?php if (get_option('tcl_api_key')): ?>
                        <span style="color: green;">✅ 設定済み</span>
                    <?php else: ?>
                        <span style="color: red;">❌ 未設定</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Google Ads API設定</th>
                <td>
                    <?php 
                    if (function_exists('tcl_check_google_ads_api_setup')) {
                        $google_setup = tcl_check_google_ads_api_setup();
                        if ($google_setup['ready']): ?>
                            <span style="color: green;">✅ 設定完了</span>
                        <?php else: ?>
                            <span style="color: orange;">⚠️ 未設定 (<?php echo count($google_setup['missing']); ?>項目)</span>
                        <?php endif;
                    } else { ?>
                        <span style="color: gray;">ℹ️ 関数未定義</span>
                    <?php } ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="tcl-status-box">
        <h3>💻 サーバー環境</h3>
        <table class="form-table">
            <tr>
                <th>PHP バージョン</th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <th>WordPress バージョン</th>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <th>cURL拡張</th>
                <td>
                    <?php if (function_exists('curl_init')): ?>
                        <span style="color: green;">✅ 利用可能</span>
                    <?php else: ?>
                        <span style="color: red;">❌ 利用不可</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>JSON拡張</th>
                <td>
                    <?php if (function_exists('json_encode')): ?>
                        <span style="color: green;">✅ 利用可能</span>
                    <?php else: ?>
                        <span style="color: red;">❌ 利用不可</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>shell_exec関数</th>
                <td>
                    <?php if (function_exists('shell_exec')): ?>
                        <span style="color: green;">✅ 利用可能</span>
                    <?php else: ?>
                        <span style="color: red;">❌ 利用不可</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <?php if (function_exists('tcl_get_keyword_suggestion_stats')): ?>
    <div class="tcl-status-box">
        <h3>📈 使用統計</h3>
        <?php
        $stats = tcl_get_keyword_suggestion_stats();
        ?>
        <table class="form-table">
            <tr>
                <th>クラスター提案数</th>
                <td><?php echo $stats['total_proposals']; ?>件</td>
            </tr>
            <tr>
                <th>総キーワード数</th>
                <td><?php echo $stats['total_keywords']; ?>件</td>
            </tr>
            <tr>
                <th>平均キーワード/提案</th>
                <td><?php echo $stats['avg_keywords_per_proposal']; ?>件</td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
    <?php
}

// 既存の関数を保持
function tcl_display_proposals_by_pillar() {
    $proposals = get_option('tcl_cluster_proposals', []);
    
    if (empty($proposals)) {
        echo '<p>提案はまだありません。上記「クラスターページ再提案」ボタンをクリックしてください。</p>';
        return;
    }
    
    foreach ($proposals as $pillar_id => $clusters) {
        $pillar_post = get_post($pillar_id);
        if (!$pillar_post) continue;
        
        echo '<div style="margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
        echo '<h3>📍 ピラーページ: ' . esc_html($pillar_post->post_title) . '</h3>';
        
        $pillar_keywords = function_exists('get_field') ? get_field('pillar_keywords', $pillar_id) : '';
        if ($pillar_keywords) {
            echo '<p><strong>キーワード:</strong> ' . esc_html($pillar_keywords) . '</p>';
        } else {
            echo '<p><strong>キーワード:</strong> <span style="color: #d63384;">未設定</span></p>';
        }
        
        echo '<p><strong>関連クラスター数:</strong> ' . count($clusters) . '件</p>';
        echo '<p><strong>投稿編集:</strong> <a href="' . get_edit_post_link($pillar_id) . '" target="_blank">編集画面を開く</a></p>';
        
        if (!empty($clusters)) {
            echo '<h4>関連クラスターページ:</h4>';
            echo '<ul>';
            foreach ($clusters as $item) {
                $cluster_post = get_post($item['cluster_id']);
                if ($cluster_post) {
                    echo '<li>';
                    echo '<a href="' . get_edit_post_link($item['cluster_id']) . '" target="_blank">';
                    echo esc_html($cluster_post->post_title);
                    echo '</a>';
                    echo ' <small style="color: #666;">(' . $cluster_post->post_type . ')</small>';
                    
                    // マッチしたキーワードを表示
                    if (!empty($item['matched_keywords'])) {
                        echo '<br><small style="color: #0073aa;">マッチキーワード: ' . implode(', ', $item['matched_keywords']) . '</small>';
                    }
                    echo '</li>';
                }
            }
            echo '</ul>';
        } else {
            echo '<p style="color: #666;">関連クラスターページはありません。</p>';
        }
        echo '</div>';
    }
}

/**
 * 設定保存処理
 */
function tcl_save_settings() {
    if (!isset($_POST['tcl_settings_nonce']) && !isset($_POST['tcl_test_nonce'])) {
        return;
    }
    
    if (isset($_POST['tcl_settings_nonce'])) {
        $nonce = $_POST['tcl_settings_nonce'];
        $action = 'tcl_settings';
    } else {
        $nonce = $_POST['tcl_test_nonce'];
        $action = 'tcl_settings';
    }
    
    if (!wp_verify_nonce($nonce, $action)) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // 保存する設定項目
    $settings = [
        'tcl_api_key',
        'tcl_serpapi_key',
        'tcl_max_links_per_post',
        'tcl_auto_suggest',
        'tcl_google_developer_token',
        'tcl_google_client_id',
        'tcl_google_client_secret',
        'tcl_google_refresh_token',
        'tcl_google_customer_id',
        'tcl_google_language_id',
        'tcl_google_geo_target_id'
    ];
    
    foreach ($settings as $setting) {
        if (isset($_POST[$setting])) {
            $value = $_POST[$setting];
            
            // チェックボックスの処理
            if ($setting === 'tcl_auto_suggest') {
                $value = ($value === '1') ? true : false;
            }
            
            update_option($setting, sanitize_text_field($value));
        } elseif ($setting === 'tcl_auto_suggest') {
            // チェックボックスが未チェックの場合
            update_option($setting, false);
        }
    }
    
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>✅ 設定が保存されました。</p></div>';
    });
}

function tcl_auto_install_composer_libs() {
    if (!current_user_can('manage_options')) {
        wp_die('権限が不足しています');
    }
    
    try {
        $plugin_dir = TCL_PLUGIN_DIR;
        $vendor_dir = $plugin_dir . 'vendor/';
        
        // 環境変数を設定
        putenv('HOME=' . $plugin_dir);
        putenv('COMPOSER_HOME=' . $plugin_dir . '.composer');
        
        // .composerディレクトリを作成
        $composer_home = $plugin_dir . '.composer';
        if (!is_dir($composer_home)) {
            wp_mkdir_p($composer_home);
        }
        
        // composer.jsonの作成
        $composer_json = [
            'require' => [
                'googleads/google-ads-php' => '^v23.0.0'
            ],
            'config' => [
                'optimize-autoloader' => true,
                'home' => $composer_home
            ]
        ];
        
        $composer_file = $plugin_dir . 'composer.json';
        file_put_contents($composer_file, json_encode($composer_json, JSON_PRETTY_PRINT));
        
        // Composerインストーラーのダウンロード
        $composer_installer_url = 'https://getcomposer.org/installer';
        $installer_response = wp_remote_get($composer_installer_url, [
            'timeout' => 60,
            'sslverify' => false
        ]);
        
        if (is_wp_error($installer_response)) {
            throw new Exception('Composerインストーラーのダウンロードに失敗: ' . $installer_response->get_error_message());
        }
        
        $installer_code = wp_remote_retrieve_body($installer_response);
        
        // 一時的にディレクトリを変更
        $old_dir = getcwd();
        chdir($plugin_dir);
        
        // 一時ファイルにインストーラーを保存
        $installer_file = $plugin_dir . 'composer-installer.php';
        file_put_contents($installer_file, $installer_code);
        
        // 環境変数を設定してComposerインストーラーを実行
        if (function_exists('shell_exec')) {
            $env_vars = 'HOME=' . escapeshellarg($plugin_dir) . ' COMPOSER_HOME=' . escapeshellarg($composer_home);
            $output = shell_exec($env_vars . ' php composer-installer.php 2>&1');
            unlink($installer_file);
            
            // composer.pharを使ってライブラリをインストール
            if (file_exists($plugin_dir . 'composer.phar')) {
                $install_output = shell_exec($env_vars . ' php composer.phar install --no-dev --optimize-autoloader 2>&1');
            } else {
                throw new Exception('composer.pharの作成に失敗しました。出力: ' . $output);
            }
        } else {
            throw new Exception('shell_exec関数が利用できません。手動インストールを実行してください。');
        }
        
        chdir($old_dir);
        
        // インストール確認
        if (file_exists($vendor_dir . 'autoload.php')) {
            echo '<div class="notice notice-success"><p>✅ Google Ads PHPライブラリのインストールが完了しました！</p></div>';
            if (function_exists('tcl_log_message')) {
                tcl_log_message('Composer ライブラリインストール成功');
            }
        } else {
            throw new Exception('インストールは実行されましたが、ライブラリが見つかりません。出力: ' . $install_output);
        }
        
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>❌ インストールエラー: ' . esc_html($e->getMessage()) . '</p></div>';
        echo '<div class="notice notice-info"><p>💡 手動インストール方法:</p>';
        echo '<ol>';
        echo '<li>SSH接続でサーバーにアクセス</li>';
        echo '<li>プラグインディレクトリで<code>curl -sS https://getcomposer.org/installer | php</code>実行</li>';
        echo '<li><code>php composer.phar install --no-dev --optimize-autoloader</code>実行</li>';
        echo '</ol></div>';
        
        if (function_exists('tcl_log_message')) {
            tcl_log_message('Composer インストールエラー: ' . $e->getMessage());
        }
    }
}
/**
* Composer ライブラリの更新
*/
function tcl_auto_update_composer_libs() {
   if (!current_user_can('manage_options')) {
       wp_die('権限が不足しています');
   }
   
   try {
       $plugin_dir = TCL_PLUGIN_DIR;
       $composer_phar = $plugin_dir . 'composer.phar';
       
       if (!file_exists($composer_phar)) {
           throw new Exception('composer.pharが見つかりません。まず新規インストールを実行してください。');
       }
       
       if (!function_exists('shell_exec')) {
           throw new Exception('shell_exec関数が利用できません。');
       }
       
       $old_dir = getcwd();
       chdir($plugin_dir);
       
       $output = shell_exec('php composer.phar update --no-dev --optimize-autoloader 2>&1');
       
       chdir($old_dir);
       
       echo '<div class="notice notice-success"><p>✅ ライブラリの更新が完了しました</p></div>';
       if (function_exists('tcl_log_message')) {
           tcl_log_message('Composer ライブラリ更新成功');
       }
       
   } catch (Exception $e) {
       echo '<div class="notice notice-error"><p>❌ 更新エラー: ' . esc_html($e->getMessage()) . '</p></div>';
       if (function_exists('tcl_log_message')) {
           tcl_log_message('Composer 更新エラー: ' . $e->getMessage());
       }
   }
}

/**
* Composer ライブラリの削除
*/
function tcl_remove_composer_libs() {
   if (!current_user_can('manage_options')) {
       wp_die('権限が不足しています');
   }
   
   try {
       $plugin_dir = TCL_PLUGIN_DIR;
       $vendor_dir = $plugin_dir . 'vendor/';
       $composer_files = [
           $plugin_dir . 'composer.json',
           $plugin_dir . 'composer.lock',
           $plugin_dir . 'composer.phar'
       ];
       
       // vendorディレクトリを削除
       if (is_dir($vendor_dir)) {
           tcl_delete_directory($vendor_dir);
       }
       
       // Composer関連ファイルを削除
       foreach ($composer_files as $file) {
           if (file_exists($file)) {
               unlink($file);
           }
       }
       
       echo '<div class="notice notice-success"><p>✅ Composerライブラリを削除しました</p></div>';
       if (function_exists('tcl_log_message')) {
           tcl_log_message('Composer ライブラリ削除完了');
       }
       
   } catch (Exception $e) {
       echo '<div class="notice notice-error"><p>❌ 削除エラー: ' . esc_html($e->getMessage()) . '</p></div>';
       if (function_exists('tcl_log_message')) {
           tcl_log_message('Composer 削除エラー: ' . $e->getMessage());
       }
   }
}

/**
* ディレクトリを再帰的に削除
*/
function tcl_delete_directory($dir) {
   if (!is_dir($dir)) {
       return;
   }
   
   $files = array_diff(scandir($dir), ['.', '..']);
   
   foreach ($files as $file) {
       $path = $dir . DIRECTORY_SEPARATOR . $file;
       
       if (is_dir($path)) {
           tcl_delete_directory($path);
       } else {
           unlink($path);
       }
   }
   
   rmdir($dir);
}

/**
* Google Ads API設定チェック関数
*/
function tcl_check_google_ads_api_setup() {
   $required_options = [
       'tcl_google_developer_token',
       'tcl_google_client_id',
       'tcl_google_client_secret',
       'tcl_google_refresh_token',
       'tcl_google_customer_id'
   ];
   
   $missing = [];
   foreach ($required_options as $option) {
       if (empty(get_option($option))) {
           $missing[] = $option;
       }
   }
   
   return [
       'ready' => empty($missing),
       'missing' => $missing
   ];
}