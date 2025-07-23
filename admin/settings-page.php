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
    // 現在のタブを保持
    $current_tab = isset($_POST['current_tab']) ? sanitize_text_field($_POST['current_tab']) : 'general';
    
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
    if (function_exists('tcl_run_propose_clusters_all_types')) {
        tcl_run_propose_clusters_all_types();
        $success_message = '✅ すべての投稿タイプでクラスターページの再提案が完了しました。';
    } elseif (function_exists('tcl_run_propose_clusters')) {
        tcl_run_propose_clusters();
        $success_message = '✅ クラスターページの再提案が完了しました。';
    }
    
    // クラスター管理タブに自動移動
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelector(".nav-tab[href=\'#clusters\']").click();
            if (typeof success_message !== "undefined") {
                var notice = document.createElement("div");
                notice.className = "notice notice-success";
                notice.innerHTML = "<p>' . $success_message . '</p>";
                document.querySelector("#clusters").insertBefore(notice, document.querySelector("#clusters").firstChild);
            }
        });
    </script>';
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
            
            <form method="post" id="tcl-propose-form">
    <input type="hidden" name="current_tab" value="clusters">
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
        // タブ切り替え機能 - 改良版
        function switchTab(targetTab) {
            // タブを非アクティブ化
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active');
            
            // 指定されたタブをアクティブ化
            $('.nav-tab[href="' + targetTab + '"]').addClass('nav-tab-active');
            $(targetTab).addClass('active');
            
            // URLにタブ情報を保存（リロード時に維持）
            if (history.replaceState) {
                var newUrl = window.location.href.split('#')[0] + targetTab;
                history.replaceState(null, null, newUrl);
            }
        }
        
        // タブクリック時の処理
        $('.nav-tab').click(function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            switchTab(target);
        });
        
        // ページ読み込み時にURLのハッシュを確認
        function checkInitialTab() {
            var hash = window.location.hash;
            if (hash && $(hash).length > 0) {
                switchTab(hash);
            } else {
                // デフォルトは基本設定
                switchTab('#general');
            }
        }
        
        // 初期タブ設定
        checkInitialTab();
        
        // ブラウザの戻る/進むボタン対応
        $(window).on('hashchange', function() {
            checkInitialTab();
        });
        
        // フォーム送信時にクラスター管理タブを維持
        $('form').on('submit', function() {
            var currentTab = $('.nav-tab-active').attr('href');
            if (currentTab === '#clusters') {
                // クラスター管理タブの場合、隠しフィールドを追加
                if (!$(this).find('input[name="current_tab"]').length) {
                    $(this).append('<input type="hidden" name="current_tab" value="clusters">');
                }
            }
        });
        
        // ページ読み込み後にタブを復元
        <?php if (isset($_POST['current_tab']) && $_POST['current_tab'] === 'clusters'): ?>
        setTimeout(function() {
            switchTab('#clusters');
        }, 100);
        <?php endif; ?>
        
        // 再提案ボタンの特別処理
        $('input[name="tcl_propose_clusters"]').closest('form').on('submit', function() {
            // 再提案後はクラスター管理タブに戻る
            $(this).append('<input type="hidden" name="current_tab" value="clusters">');
        });
    });
</script>
    <?php
}


/**
 * ピラーページ別クラスター提案の表示（完全版・修正済み）
 */
function tcl_display_proposals_by_pillar() {
    // ページング設定
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $show_linked = isset($_GET['show_linked']) && $_GET['show_linked'] === '1';
    $show_all_clusters = isset($_GET['show_all_clusters']) ? sanitize_text_field($_GET['show_all_clusters']) : '';
    
    echo '<div style="background: #e8f5e8; padding: 15px; margin: 10px 0; border-left: 4px solid #4caf50;">';
    echo '<h4>📋 ピラーページ別クラスター管理</h4>';
    
    // すべての投稿タイプを取得
    $all_post_types = get_post_types(array('public' => true), 'names');
    
    // ピラーページを取得（ページング対応）
    $pillar_query = new WP_Query(array(
        'post_type' => $all_post_types,
        'posts_per_page' => $per_page,
        'paged' => $current_page,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'pillar_keywords',
                'value' => '',
                'compare' => '!='
            )
        )
    ));
    
    $total_pillars = $pillar_query->found_posts;
    $total_pages = ceil($total_pillars / $per_page);
    
    // 統計情報
    echo '<div style="background: #fff; padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid #ddd;">';
    echo '<p><strong>📊 統計:</strong> ';
    echo 'ピラーページ総数: ' . $total_pillars . '件 | ';
    echo '現在のページ: ' . $current_page . '/' . $total_pages . '</p>';
    echo '</div>';
    
    // 表示オプション - 修正版
echo '<div style="background: #f0f8ff; padding: 15px; margin: 10px 0; border-radius: 4px; border: 1px solid #b3d9ff;">';
echo '<h5 style="margin: 0 0 10px 0;">🔧 表示オプション</h5>';

// 現在のページURL（クラスター管理タブ）を保持
$current_url = remove_query_arg(array('show_linked', 'show_all_clusters', 'paged'));
$current_url = add_query_arg('page', 'topic-cluster-linker', admin_url('admin.php'));
$current_url .= '#clusters';

echo '<div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">';

if (!$show_linked) {
    $show_linked_url = add_query_arg('show_linked', '1', $current_url);
    echo '<a href="' . esc_url($show_linked_url) . '" class="button button-secondary" style="min-width: 200px; text-align: center;">🔗 リンク済みも含めて表示</a>';
} else {
    $hide_linked_url = remove_query_arg('show_linked', $current_url);
    echo '<a href="' . esc_url($hide_linked_url) . '" class="button button-secondary" style="min-width: 200px; text-align: center;">📋 未リンクのみ表示</a>';
}

echo '<span style="padding: 8px 12px; background: white; border: 1px solid #ddd; border-radius: 4px; color: #666; font-weight: bold;">';
echo '現在: ' . ($show_linked ? 'リンク済み含む' : '未リンクのみ');
echo '</span>';

echo '</div>';
echo '</div>';
    
    if ($pillar_query->have_posts()) {
        $proposals = get_option('tcl_cluster_proposals', array());
        
        while ($pillar_query->have_posts()) {
            $pillar_query->the_post();
            $pillar_id = get_the_ID();
            $pillar_title = get_the_title();
            $keywords = get_field('pillar_keywords', $pillar_id);
            
            // 投稿タイプ情報
            $post_type_obj = get_post_type_object(get_post_type());
            $type_text = $post_type_obj ? $post_type_obj->label : get_post_type();
            $type_colors = array(
                'post' => '#2196f3',
                'page' => '#4caf50', 
                'product' => '#ff9800',
                'service' => '#9c27b0'
            );
            $type_color = isset($type_colors[get_post_type()]) ? $type_colors[get_post_type()] : '#757575';
            
            echo '<div class="tcl-pillar-container" style="background: white; margin: 20px 0; border-radius: 8px; border: 2px solid ' . $type_color . '; overflow: hidden;">';
            
            // ピラーページヘッダー
            echo '<div style="background: ' . $type_color . '; color: white; padding: 15px;">';
            echo '<h4 style="margin: 0; color: white;">📍 ' . esc_html($pillar_title) . '</h4>';
            echo '<p style="margin: 5px 0 0 0; opacity: 0.9;">投稿タイプ: ' . $type_text . ' | ID: ' . $pillar_id . '</p>';
            echo '</div>';
            
            echo '<div style="padding: 15px;">';
  echo '<p><strong>🎯 キーワード:</strong> ' . esc_html($keywords) . '</p>';

// 関連キーワード表示
echo '<div style="margin: 10px 0;">';
echo '<details style="background: #f8f9fa; padding: 10px; border-radius: 4px; border: 1px solid #dee2e6;">';
echo '<summary style="cursor: pointer; font-weight: bold; color: #0073aa; padding: 5px;">🔍 関連キーワード提案を表示</summary>';

$related_keywords = tcl_get_related_keywords_batch($keywords);

if (!empty($related_keywords)) {
    echo '<div style="margin-top: 10px;">';
    foreach ($related_keywords as $base_keyword => $suggestions) {
        if (!empty($suggestions)) {
            echo '<div style="margin-bottom: 15px; padding: 10px; background: white; border-left: 3px solid #0073aa; border-radius: 4px;">';
            echo '<h6 style="margin: 0 0 8px 0; color: #0073aa;">「' . esc_html($base_keyword) . '」の関連語</h6>';
            echo '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
            
            foreach ($suggestions as $suggestion) {
                echo '<span style="background: #e3f2fd; color: #1976d2; padding: 3px 8px; border-radius: 12px; font-size: 12px; border: 1px solid #bbdefb;">';
                echo esc_html($suggestion);
                echo '</span>';
            }
            
            echo '</div></div>';
        }
    }
    echo '</div>';
} else {
    echo '<p style="color: #666; font-style: italic; margin: 10px 0;">関連キーワードの取得に失敗しました。</p>';
}

// 関連キーワード活用方法
echo '<div style="margin: 10px 0; padding: 10px; background: #fff3cd; border-radius: 4px; border-left: 4px solid #ffc107;">';
echo '<h6 style="margin: 0 0 8px 0; color: #856404;">💡 関連キーワード活用方法</h6>';
echo '<ul style="margin: 5px 0; padding-left: 20px; font-size: 13px; color: #856404;">';
echo '<li><strong>新しいクラスター記事のアイデア</strong>として活用</li>';
echo '<li><strong>既存記事のタイトル・見出し改善</strong>に活用</li>';
echo '<li><strong>メタディスクリプション</strong>に関連語を含める</li>';
echo '<li><strong>内部リンクのアンカーテキスト</strong>として活用</li>';
echo '</ul>';
echo '</div>';

echo '</details></div>';
            
            // クラスター提案の確認
            $clusters = isset($proposals[$pillar_id]) ? $proposals[$pillar_id] : array();
            $linked_clusters = tcl_get_linked_clusters($pillar_id);
            $unlinked_clusters = tcl_filter_unlinked_clusters($clusters, $linked_clusters);
            
            // 統計表示
            echo '<div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0;">';
            echo '<p><strong>📊 クラスター統計:</strong></p>';
            echo '<ul style="margin: 5px 0; padding-left: 20px;">';
            echo '<li>🔗 <strong>リンク済み:</strong> ' . count($linked_clusters) . '件</li>';
            echo '<li>📝 <strong>未リンク:</strong> ' . count($unlinked_clusters) . '件</li>';
            echo '<li>📈 <strong>提案総数:</strong> ' . count($clusters) . '件</li>';
            echo '</ul>';
            echo '</div>';
          

            // アクションボタン
            echo '<div style="margin: 15px 0;">';
            echo '<a href="' . get_edit_post_link($pillar_id) . '" target="_blank" class="button button-primary">編集する</a> ';
            echo '<a href="' . get_permalink($pillar_id) . '" target="_blank" class="button button-secondary">表示する</a>';
            echo '</div>';
            
            // クラスター一覧（入れ子表示）- 修正版
if ($show_linked) {
    // リンク済み含むモードの場合、すべてのクラスターを表示
    $display_clusters = $clusters;
} else {
    // 未リンクのみモードの場合、未リンクのみ表示
    $display_clusters = $unlinked_clusters;
}

// デバッグ用追加情報
echo '<div style="background: #e8f4fd; padding: 8px; margin: 5px 0; font-size: 12px;">';
echo '<strong>🔍 表示判定:</strong> ';
echo 'モード=' . ($show_linked ? 'リンク済み含む' : '未リンクのみ') . ' | ';
echo '表示対象=' . count($display_clusters) . '件 | ';
echo 'クラスターID=[' . implode(', ', array_column($display_clusters, 'cluster_id')) . ']';
echo '</div>';

if (!empty($display_clusters)) {
    $show_all_key = 'pillar_' . $pillar_id;
    $is_expanded = ($show_all_clusters === $show_all_key);
    $display_limit = $is_expanded ? count($display_clusters) : 3;
    $visible_clusters = array_slice($display_clusters, 0, $display_limit);
    
    echo '<div class="tcl-clusters-section" style="border-top: 1px solid #eee; padding-top: 15px;">';
    echo '<h5>🎯 関連クラスターページ (' . count($display_clusters) . '件)</h5>';
    
    foreach ($visible_clusters as $index => $cluster) {
        $cluster_post = get_post($cluster['cluster_id']);
        if (!$cluster_post) continue;
        
        $is_linked = in_array($cluster['cluster_id'], $linked_clusters);
        $status_color = $is_linked ? '#28a745' : '#6c757d';
        $status_text = $is_linked ? 'リンク済み' : '未リンク';
        $status_icon = $is_linked ? '🔗' : '📄';
        
        echo '<div style="background: #f8f9fa; margin: 8px 0; padding: 12px; border-radius: 4px; border-left: 4px solid ' . $status_color . ';">';
        echo '<div style="display: flex; justify-content: space-between; align-items: flex-start;">';
        
        echo '<div style="flex: 1;">';
        echo '<h6 style="margin: 0 0 5px 0;">' . $status_icon . ' ';
        echo '<a href="' . get_edit_post_link($cluster['cluster_id']) . '" target="_blank" style="text-decoration: none; font-weight: bold;">';
        echo esc_html($cluster_post->post_title) . '</a></h6>';
        
        if (!empty($cluster['matched_keywords'])) {
            echo '<small style="color: #0073aa;">🎯 マッチ: ' . implode(', ', $cluster['matched_keywords']) . '</small><br>';
        }
        
        echo '<small style="color: ' . $status_color . '; font-weight: bold;">' . $status_text . '</small>';
        echo '</div>';
        
        // アクションボタン追加
        echo '<div style="margin-left: 15px; display: flex; flex-direction: column; gap: 5px;">';
        echo '<a href="' . get_edit_post_link($cluster['cluster_id']) . '" target="_blank" class="button button-small" style="font-size: 11px; padding: 3px 8px;">✏️ 編集</a>';
        
        if (!$is_linked) {
            echo '<button type="button" class="button button-small tcl-add-link-btn" style="font-size: 11px; padding: 3px 8px; background: #0073aa; color: white;" ';
            echo 'data-pillar-id="' . $pillar_id . '" data-cluster-id="' . $cluster['cluster_id'] . '" data-cluster-title="' . esc_attr($cluster_post->post_title) . '">';
            echo '🔗 リンク追加</button>';
        }
        
        echo '<small style="color: #666; margin-top: 5px;">' . ($index + 1) . '/' . count($display_clusters) . '</small>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
   // 展開/折りたたみボタン - 修正版
if (count($display_clusters) > 3) {
    echo '<div style="text-align: center; margin: 15px 0;">';
    
    // 現在のページURLを維持（クラスター管理タブを含む）
    $base_url = admin_url('admin.php?page=topic-cluster-linker');
    
    if (!$is_expanded) {
        $expand_url = add_query_arg(array(
            'show_all_clusters' => $show_all_key,
            'show_linked' => $show_linked ? '1' : null,
            'paged' => $current_page > 1 ? $current_page : null
        ), $base_url) . '#clusters';
        
        echo '<a href="' . esc_url($expand_url) . '" class="button button-secondary">';
        echo '📋 全て表示 (' . count($display_clusters) . '件)</a>';
    } else {
        $collapse_url = add_query_arg(array(
            'show_linked' => $show_linked ? '1' : null,
            'paged' => $current_page > 1 ? $current_page : null
        ), $base_url) . '#clusters';
        
        echo '<a href="' . esc_url($collapse_url) . '" class="button button-secondary">';
        echo '📁 3件表示に戻る</a>';
    }
    echo '</div>';
}
    
    echo '</div>';
}
            
            echo '</div>';
            echo '</div>';
        }
        
        wp_reset_postdata();
        
        // ページネーション
        if ($total_pages > 1) {
            echo '<div class="tcl-pagination" style="text-align: center; margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 4px;">';
            echo '<h5>📄 ページナビゲーション</h5>';
            
            for ($i = 1; $i <= $total_pages; $i++) {
                $page_url = add_query_arg('paged', $i);
                $is_current = ($i === $current_page);
                $button_class = $is_current ? 'button button-primary' : 'button button-secondary';
                
                echo '<a href="' . esc_url($page_url) . '" class="' . $button_class . '" style="margin: 0 3px;">' . $i . '</a>';
            }
            
            echo '<p style="margin: 10px 0 0 0; color: #666;">ページ ' . $current_page . ' / ' . $total_pages . ' (全 ' . $total_pillars . '件)</p>';
            echo '</div>';
        }
        
    } else {
        echo '<p style="color: #f44336;">❌ ピラーキーワードが設定されたコンテンツが見つかりません。</p>';
    }
    
    echo '</div>';
    
    // 提案実行セクション
    echo '<div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;">';
    echo '<h4>🔄 クラスター再提案</h4>';
    echo '<p>クラスター提案を実行するには「🔄 クラスターページ再提案」ボタンをクリックしてください。</p>';
    echo '<p><small>※ リンク済みのクラスターは新しい提案から除外されます。</small></p>';
    echo '</div>';
}
// リンク追加機能のJavaScript
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // リンク追加ボタンのクリックイベント
    document.querySelectorAll(".tcl-add-link-btn").forEach(function(button) {
        button.addEventListener("click", function() {
            var pillarId = this.getAttribute("data-pillar-id");
            var clusterId = this.getAttribute("data-cluster-id");
            var clusterTitle = this.getAttribute("data-cluster-title");
            
            if (confirm("「" + clusterTitle + "」への内部リンクをピラーページに追加しますか？")) {
                // ピラーページの編集画面を新しいタブで開く
                var editUrl = "' . admin_url('post.php') . '?post=" + pillarId + "&action=edit";
                window.open(editUrl, "_blank");
                
                // 案内メッセージを表示
                alert("ピラーページの編集画面が開きました。\\n\\n手順：\\n1. 適切な場所にテキストを追加\\n2. テキストを選択\\n3. リンクボタンをクリック\\n4. 「" + clusterTitle + "」を検索してリンク");
            }
        });
    });
});
</script>';
/**
 * ピラーページに既にリンクされているクラスターIDを取得（カスタム投稿対応版）
 */
function tcl_get_linked_clusters($pillar_id) {
    $pillar_post = get_post($pillar_id);
    if (!$pillar_post) return array();
    
    $linked_ids = array();
    
    // 投稿内容から内部リンクを抽出
    preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $pillar_post->post_content, $matches);
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $url) {
            $post_id = null;
            
            // URL形式を正規化
            $clean_url = trim($url);
            
            // 相対URLの場合、絶対URLに変換
            if (strpos($clean_url, '/') === 0 && strpos($clean_url, '//') !== 0) {
                $clean_url = home_url($clean_url);
            }
            
            // 同じドメインの場合のみ処理
            if (strpos($clean_url, home_url()) === 0) {
                // まず標準の url_to_postid を試す
                $post_id = url_to_postid($clean_url);
                
                // 標準関数で見つからない場合、カスタム投稿も含めて検索
                if (!$post_id) {
                    $post_id = tcl_url_to_postid_custom($clean_url);
                }
                
                // IDが見つかり、かつ自分自身でない場合
                if ($post_id && $post_id !== $pillar_id) {
                    $linked_ids[] = $post_id;
                }
            }
        }
    }
    
    return array_unique($linked_ids);
}

/**
 * カスタム投稿タイプにも対応したURL→投稿ID変換
 */
function tcl_url_to_postid_custom($url) {
    global $wpdb;
    
    // URLからパスを抽出
    $url_path = parse_url($url, PHP_URL_PATH);
    if (!$url_path) return 0;
    
    // パスの最後のスラッシュを除去
    $url_path = rtrim($url_path, '/');
    
    // スラッグを抽出（最後の/以降）
    $slug = basename($url_path);
    if (!$slug) return 0;
    
    // すべての公開投稿タイプを取得
    $post_types = get_post_types(array('public' => true), 'names');
    $post_types_str = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
    
    // スラッグで投稿を検索
    $post_id = $wpdb->get_var($wpdb->prepare("
        SELECT ID 
        FROM {$wpdb->posts} 
        WHERE post_name = %s 
        AND post_type IN ($post_types_str) 
        AND post_status = 'publish'
        LIMIT 1
    ", $slug));
    
    // 見つからない場合、パーマリンク構造を考慮した検索
    if (!$post_id) {
        // カスタム投稿タイプのパーマリンク構造を考慮
        $path_parts = explode('/', trim($url_path, '/'));
        
        if (count($path_parts) >= 2) {
            // 最後の部分をスラッグとして使用
            $slug = end($path_parts);
            
            $post_id = $wpdb->get_var($wpdb->prepare("
                SELECT ID 
                FROM {$wpdb->posts} 
                WHERE post_name = %s 
                AND post_type IN ($post_types_str) 
                AND post_status = 'publish'
                LIMIT 1
            ", $slug));
        }
    }
    
    return $post_id ? intval($post_id) : 0;
}

/**
 * デバッグ用：リンク検出の詳細情報を表示
 */
function tcl_debug_linked_clusters($pillar_id) {
    $pillar_post = get_post($pillar_id);
    if (!$pillar_post) return;
    
    echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 12px;">';
    echo '<h6>🔍 リンク検出デバッグ (ID: ' . $pillar_id . ')</h6>';
    
    // リンクを抽出
    preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $pillar_post->post_content, $matches, PREG_SET_ORDER);
    
    echo '<p><strong>検出されたリンク数:</strong> ' . count($matches) . '</p>';
    
    foreach ($matches as $match) {
        $url = $match[1];
        $text = strip_tags($match[2]);
        $post_id = url_to_postid($url);
        $custom_post_id = tcl_url_to_postid_custom($url);
        
        echo '<div style="margin: 5px 0; padding: 5px; background: white; border-left: 3px solid #0073aa;">';
        echo '<strong>URL:</strong> ' . esc_html($url) . '<br>';
        echo '<strong>テキスト:</strong> ' . esc_html(substr($text, 0, 50)) . '<br>';
        echo '<strong>標準検出ID:</strong> ' . ($post_id ?: '検出されず') . '<br>';
        echo '<strong>カスタム検出ID:</strong> ' . ($custom_post_id ?: '検出されず') . '<br>';
        
        if ($custom_post_id) {
            $linked_post = get_post($custom_post_id);
            echo '<strong>投稿タイトル:</strong> ' . esc_html($linked_post->post_title) . '<br>';
            echo '<strong>投稿タイプ:</strong> ' . $linked_post->post_type;
        }
        echo '</div>';
    }
    
    echo '</div>';
}


/**
 * 未リンククラスターのみをフィルタリング
 */
function tcl_filter_unlinked_clusters($clusters, $linked_ids) {
    return array_filter($clusters, function($cluster) use ($linked_ids) {
        return !in_array($cluster['cluster_id'], $linked_ids);
    });
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
                if (class_exists('Google\Ads\GoogleAds\Lib\V16\GoogleAdsClientBuilder')) {
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

/**
* Composer ライブラリの自動インストール
*/
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


/**
 * すべての投稿タイプに対応したクラスター提案実行
 */
function tcl_run_propose_clusters_all_types() {
    // すべての投稿タイプを取得
    $all_post_types = get_post_types(array('public' => true), 'names');
    
    // ピラーキーワードが設定されたすべてのコンテンツを取得
    $pillar_posts = get_posts(array(
        'post_type' => $all_post_types,
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'pillar_keywords',
                'value' => '',
                'compare' => '!='
            )
        )
    ));
    
    if (empty($pillar_posts)) {
        return false;
    }
    
    $cluster_proposals = array();
    
    foreach ($pillar_posts as $pillar_post) {
        $pillar_keywords = get_field('pillar_keywords', $pillar_post->ID);
        
        if (empty($pillar_keywords)) {
            continue;
        }
        
        // キーワードを配列に変換
        $keywords_array = array_map('trim', explode('、', $pillar_keywords));
        
        // 関連する投稿を検索
        $related_posts = array();
        
        foreach ($keywords_array as $keyword) {
            // すべての投稿タイプから関連記事を検索
            $posts = get_posts(array(
                'posts_per_page' => 20,
                'post_status' => 'publish',
                'post_type' => $all_post_types,
                's' => $keyword,
                'exclude' => array($pillar_post->ID)
            ));
            
            foreach ($posts as $post) {
                // タイトルまたはコンテンツにキーワードが含まれるかチェック
                $title_match = stripos($post->post_title, $keyword) !== false;
                $content_match = stripos($post->post_content, $keyword) !== false;
                
                if ($title_match || $content_match) {
                    $related_posts[] = array(
                        'cluster_id' => $post->ID,
                        'matched_keywords' => array($keyword),
                        'match_type' => $title_match ? 'title' : 'content'
                    );
                }
            }
        }
        
        // 重複を除去
        $unique_posts = array();
        $seen_ids = array();
        
        foreach ($related_posts as $item) {
            if (!in_array($item['cluster_id'], $seen_ids)) {
                $unique_posts[] = $item;
                $seen_ids[] = $item['cluster_id'];
            }
        }
        
        if (!empty($unique_posts)) {
            $cluster_proposals[$pillar_post->ID] = $unique_posts;
        }
    }
    
    // 提案データを保存
    update_option('tcl_cluster_proposals', $cluster_proposals);
    
    return true;
}

/**
 * Google Autocomplete APIから関連キーワードを取得（改良版）
 */
function tcl_get_autocomplete_keywords($keyword, $country = 'jp', $language = 'ja') {
    $keywords = array();
    
    try {
        // Google Autocomplete API (HTTPS版)
        $url = 'https://suggestqueries.google.com/complete/search?' . http_build_query(array(
            'client' => 'firefox',
            'q' => $keyword,
            'hl' => $language,
            'gl' => $country,
            'output' => 'toolbar'
        ));
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'sslverify' => false,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'ja,en-US;q=0.9,en;q=0.8',
                'Cache-Control' => 'no-cache',
                'Referer' => 'https://www.google.com/'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('TCL Autocomplete Error: ' . $response->get_error_message());
            return tcl_get_fallback_keywords($keyword);
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            error_log('TCL Autocomplete HTTP Error: ' . $status_code);
            return tcl_get_fallback_keywords($keyword);
        }
        
        // JSON形式でレスポンスを解析
        if (preg_match('/^\[/', $body)) {
            $data = json_decode($body, true);
            if (isset($data[1]) && is_array($data[1])) {
                $keywords = array_slice($data[1], 0, 8); // 最大8件
                
                // 空の提案を除去
                $keywords = array_filter($keywords, function($k) {
                    return !empty(trim($k));
                });
            }
        }
        
        // 結果が少ない場合はフォールバック
        if (count($keywords) < 3) {
            $fallback = tcl_get_fallback_keywords($keyword);
            $keywords = array_merge($keywords, $fallback);
            $keywords = array_unique($keywords);
        }
        
    } catch (Exception $e) {
        error_log('TCL Autocomplete Exception: ' . $e->getMessage());
        return tcl_get_fallback_keywords($keyword);
    }
    
    return array_slice($keywords, 0, 10);
}

/**
 * フォールバック用の関連キーワード生成
 */
function tcl_get_fallback_keywords($keyword) {
    $suffixes = array(
        'とは', 'メリット', 'デメリット', '方法', '効果',
        '使い方', '選び方', '比較', 'おすすめ', '料金',
        '口コミ', 'レビュー', '評判', '特徴', '種類'
    );
    
    $prefixes = array(
        '初心者', '簡単', '無料', '有料', '最新',
        '人気', 'おすすめ', '安い', '高品質', 'プロ'
    );
    
    $keywords = array();
    
    // サフィックス付きキーワード
    foreach (array_slice($suffixes, 0, 5) as $suffix) {
        $keywords[] = $keyword . ' ' . $suffix;
    }
    
    // プレフィックス付きキーワード
    foreach (array_slice($prefixes, 0, 3) as $prefix) {
        $keywords[] = $prefix . ' ' . $keyword;
    }
    
    return $keywords;
}

/**
 * 複数のキーワードから関連語を取得（改良版・デバッグ付き）
 */
function tcl_get_related_keywords_batch($pillar_keywords) {
    $all_keywords = array();
    $keywords_array = array_map('trim', explode('、', $pillar_keywords));
    
    // デバッグ情報
    error_log('TCL Debug: Processing keywords: ' . print_r($keywords_array, true));
    
    foreach ($keywords_array as $index => $keyword) {
        if (empty($keyword)) continue;
        
        // 進行状況をログに記録
        error_log('TCL Debug: Processing keyword ' . ($index + 1) . '/' . count($keywords_array) . ': ' . $keyword);
        
        $related = tcl_get_autocomplete_keywords($keyword);
        
        if (!empty($related)) {
            $all_keywords[$keyword] = $related;
            error_log('TCL Debug: Found ' . count($related) . ' related keywords for: ' . $keyword);
        } else {
            error_log('TCL Debug: No related keywords found for: ' . $keyword);
            // 空の場合でもフォールバック用のキーワードを設定
            $all_keywords[$keyword] = tcl_get_fallback_keywords($keyword);
        }
        
        // APIリクエスト間隔を空ける（レート制限対策）
        if ($index < count($keywords_array) - 1) {
            usleep(500000); // 0.5秒待機
        }
    }
    
    error_log('TCL Debug: Final result: ' . print_r($all_keywords, true));
    
    return $all_keywords;
}

/**
 * デバッグ用：関連キーワード取得のテスト機能
 */
function tcl_test_keyword_suggestion($test_keyword = 'WordPress') {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    echo '<div style="background: #e8f4fd; padding: 15px; margin: 10px 0; border-radius: 4px;">';
    echo '<h5>🧪 キーワード取得テスト</h5>';
    echo '<p><strong>テストキーワード:</strong> ' . esc_html($test_keyword) . '</p>';
    
    $start_time = microtime(true);
    $results = tcl_get_autocomplete_keywords($test_keyword);
    $end_time = microtime(true);
    $execution_time = round(($end_time - $start_time) * 1000, 2);
    
    echo '<p><strong>実行時間:</strong> ' . $execution_time . 'ms</p>';
    echo '<p><strong>取得件数:</strong> ' . count($results) . '件</p>';
    
    if (!empty($results)) {
        echo '<p><strong>取得結果:</strong></p>';
        echo '<ul>';
        foreach ($results as $result) {
            echo '<li>' . esc_html($result) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="color: red;"><strong>取得に失敗しました</strong></p>';
    }
    
     // デバッグ・テストセクション
    if (current_user_can('manage_options') && isset($_GET['debug'])) {
        echo '<div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107; border-radius: 4px;">';
        echo '<h4>🔧 デバッグ・テストツール</h4>';
        
        // テスト実行
        if (isset($_POST['test_keyword_api'])) {
            $test_keyword = sanitize_text_field($_POST['test_keyword']);
            tcl_test_keyword_suggestion($test_keyword);
        }
        
        echo '<form method="post" style="margin: 15px 0;">';
        echo '<label for="test_keyword">テストキーワード:</label> ';
        echo '<input type="text" name="test_keyword" value="WordPress" style="margin: 0 10px;" />';
        echo '<input type="submit" name="test_keyword_api" class="button button-secondary" value="🧪 API テスト実行" />';
        echo '</form>';
        
        echo '<p><small>※ エラーログは wp-content/debug.log で確認できます</small></p>';
        echo '</div>';
    }

    // デバッグモード切り替えリンク
    $current_url = admin_url('admin.php?page=topic-cluster-linker#clusters');
    if (!isset($_GET['debug'])) {
        echo '<p><a href="' . add_query_arg('debug', '1', $current_url) . '" class="button button-secondary">🔧 デバッグモードを有効にする</a></p>';
    } else {
        echo '<p><a href="' . remove_query_arg('debug', $current_url) . '" class="button button-secondary">🔧 デバッグモードを無効にする</a></p>';
    }
    
    echo '</div>';
}

/**
 * 投稿編集画面にメタボックスを追加
 */
function tcl_add_related_keywords_metabox() {
    // すべての公開投稿タイプに追加
    $post_types = get_post_types(array('public' => true), 'names');
    
    foreach ($post_types as $post_type) {
        add_meta_box(
            'tcl_related_keywords',
            '🎯 関連キーワード提案',
            'tcl_related_keywords_metabox_callback',
            $post_type,
            'side',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'tcl_add_related_keywords_metabox');

/**
 * 関連キーワードメタボックスの内容
 */
function tcl_related_keywords_metabox_callback($post) {
    // 現在の投稿のピラーキーワードを取得
    $pillar_keywords = get_field('pillar_keywords', $post->ID);
    
    echo '<div id="tcl-related-keywords-container">';
    
    if (empty($pillar_keywords)) {
        echo '<div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
        echo '<h4 style="margin: 0 0 8px 0;">💡 新しいクラスターページを作成</h4>';
        
        // クラスターキーワード生成方法
        echo '<div style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 10px 0;">';
        echo '<h5 style="margin: 0 0 8px 0; color: #1976d2;">🎯 クラスターキーワード生成方法</h5>';
        echo '<p style="margin: 5px 0; font-size: 13px;">2つの方法から選択してください</p>';
        
        echo '<div style="display: flex; gap: 8px; margin: 10px 0;">';
        echo '<button type="button" class="button button-secondary tcl-generate-btn" data-method="ai" style="flex: 1; padding: 8px; font-size: 12px;">🤖 AI生成<br><small>(基本)</small></button>';
        echo '<button type="button" class="button button-primary tcl-generate-btn" data-method="search" style="flex: 1; padding: 8px; font-size: 12px;">🔍 検索データ連携<br><small>(推奨)</small></button>';
        echo '</div>';
        echo '</div>';
        
        // 具体的な記事アイデア提案
        echo '<div style="background: #f0f8ff; padding: 10px; border-radius: 4px; margin: 10px 0;">';
        echo '<h5 style="margin: 0 0 8px 0; color: #0066cc;">💡 具体的な記事アイデア提案</h5>';
        echo '<p style="margin: 5px 0; font-size: 13px;">選択したキーワードから具体的な記事を提案します</p>';
        echo '<button type="button" class="button button-secondary tcl-idea-btn" style="width: 100%; margin-top: 5px;">📝 記事アイデア生成</button>';
        echo '</div>';
        
        // 使い方のヒント
        echo '<div style="background: #f8f9fa; padding: 8px; border-radius: 4px; margin-top: 10px;">';
        echo '<h6 style="margin: 0 0 5px 0;">使い方のヒント:</h6>';
        echo '<ul style="margin: 0; padding-left: 15px; font-size: 11px; line-height: 1.4;">';
        echo '<li>「🔍 検索」でタイトルやリンクを追加</li>';
        echo '<li>「📝 再生成」でAI が新しいリンクテキストを作成</li>';
        echo '<li>リンクは文脈に合わせて自動調整されます</li>';
        echo '<li>管理画面で全体の構成を確認</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div>';
        
        // 結果表示エリア
        echo '<div id="tcl-generation-result" style="display: none;"></div>';
        
    } else {
        // ピラーキーワードが設定されている場合の表示
        echo '<div style="background: #e8f5e8; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
        echo '<h4 style="margin: 0 0 8px 0;">🎯 設定済みピラーキーワード</h4>';
        echo '<p style="margin: 5px 0; font-weight: bold;">' . esc_html($pillar_keywords) . '</p>';
        echo '</div>';
        
        // 関連キーワード表示エリア
        echo '<div id="tcl-related-keywords-display">';
        echo '<button type="button" class="button button-primary" onclick="tclLoadRelatedKeywords()" style="width: 100%;">🔍 関連キーワードを表示</button>';
        echo '</div>';
        
        // 結果表示エリア
        echo '<div id="tcl-keywords-result" style="margin-top: 10px;"></div>';
    }
    
    echo '</div>';
    
    // JavaScript を追加
    tcl_add_metabox_scripts($post);
}

/**
 * メタボックス用のJavaScript
 */
function tcl_add_metabox_scripts($post) {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // キーワード生成ボタンのクリックイベント
        $('.tcl-generate-btn').on('click', function() {
            var method = $(this).data('method');
            var $button = $(this);
            var originalText = $button.html();
            
            $button.html('🔄 生成中...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tcl_generate_cluster_keywords',
                    method: method,
                    post_id: <?php echo $post->ID; ?>,
                    post_title: '<?php echo esc_js($post->post_title); ?>',
                    post_content: <?php echo json_encode(wp_strip_all_tags($post->post_content)); ?>,
                    nonce: '<?php echo wp_create_nonce('tcl_generate_keywords'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#tcl-generation-result').html(response.data.html).show();
                    } else {
                        alert('エラー: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました');
                },
                complete: function() {
                    $button.html(originalText).prop('disabled', false);
                }
            });
        });
        
        // 記事アイデア生成ボタン
        $('.tcl-idea-btn').on('click', function() {
            var $button = $(this);
            var originalText = $button.html();
            
            $button.html('🔄 生成中...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tcl_generate_article_ideas',
                    post_id: <?php echo $post->ID; ?>,
                    post_title: '<?php echo esc_js($post->post_title); ?>',
                    post_content: <?php echo json_encode(wp_strip_all_tags($post->post_content)); ?>,
                    nonce: '<?php echo wp_create_nonce('tcl_generate_ideas'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#tcl-generation-result').html(response.data.html).show();
                    } else {
                        alert('エラー: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました');
                },
                complete: function() {
                    $button.html(originalText).prop('disabled', false);
                }
            });
        });
    });
    
    // 関連キーワード読み込み関数
    function tclLoadRelatedKeywords() {
        var $button = $('#tcl-related-keywords-display button');
        var originalText = $button.html();
        
        $button.html('🔄 読み込み中...').prop('disabled', true);
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tcl_load_related_keywords',
                post_id: <?php echo $post->ID; ?>,
                nonce: '<?php echo wp_create_nonce('tcl_load_keywords'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    jQuery('#tcl-keywords-result').html(response.data.html);
                    $button.hide();
                } else {
                    alert('エラー: ' + response.data.message);
                }
            },
            error: function() {
                alert('通信エラーが発生しました');
            },
            complete: function() {
                $button.html(originalText).prop('disabled', false);
            }
        });
    }
    </script>
    <?php
}

/**
 * AJAX: クラスターキーワード生成
 */
function tcl_ajax_generate_cluster_keywords() {
    check_ajax_referer('tcl_generate_keywords', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_die(json_encode(array('success' => false, 'message' => '権限が不足しています')));
    }
    
    $method = sanitize_text_field($_POST['method']);
    $post_id = intval($_POST['post_id']);
    $post_title = sanitize_text_field($_POST['post_title']);
    $post_content = sanitize_textarea_field($_POST['post_content']);
    
    try {
        if ($method === 'ai') {
            $keywords = tcl_generate_ai_keywords($post_title, $post_content);
        } else {
            $keywords = tcl_generate_search_keywords($post_title, $post_content);
        }
        
        if (empty($keywords)) {
            throw new Exception('キーワードの生成に失敗しました');
        }
        
        $html = tcl_render_generated_keywords($keywords, $method);
        
        wp_die(json_encode(array(
            'success' => true,
            'data' => array('html' => $html)
        )));
        
    } catch (Exception $e) {
        wp_die(json_encode(array(
            'success' => false,
            'message' => $e->getMessage()
        )));
    }
}
add_action('wp_ajax_tcl_generate_cluster_keywords', 'tcl_ajax_generate_cluster_keywords');

/**
 * AJAX: 記事アイデア生成
 */
function tcl_ajax_generate_article_ideas() {
    check_ajax_referer('tcl_generate_ideas', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_die(json_encode(array('success' => false, 'message' => '権限が不足しています')));
    }
    
    $post_id = intval($_POST['post_id']);
    $post_title = sanitize_text_field($_POST['post_title']);
    $post_content = sanitize_textarea_field($_POST['post_content']);
    
    try {
        $ideas = tcl_generate_article_ideas($post_title, $post_content);
        
        if (empty($ideas)) {
            throw new Exception('記事アイデアの生成に失敗しました');
        }
        
        $html = tcl_render_article_ideas($ideas);
        
        wp_die(json_encode(array(
            'success' => true,
            'data' => array('html' => $html)
        )));
        
    } catch (Exception $e) {
        wp_die(json_encode(array(
            'success' => false,
            'message' => $e->getMessage()
        )));
    }
}
add_action('wp_ajax_tcl_generate_article_ideas', 'tcl_ajax_generate_article_ideas');

/**
 * AJAX: 関連キーワード読み込み
 */
function tcl_ajax_load_related_keywords() {
    check_ajax_referer('tcl_load_keywords', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_die(json_encode(array('success' => false, 'message' => '権限が不足しています')));
    }
    
    $post_id = intval($_POST['post_id']);
    $pillar_keywords = get_field('pillar_keywords', $post_id);
    
    if (empty($pillar_keywords)) {
        wp_die(json_encode(array('success' => false, 'message' => 'ピラーキーワードが設定されていません')));
    }
    
    try {
        $related_keywords = tcl_get_related_keywords_batch($pillar_keywords);
        $html = tcl_render_related_keywords_in_metabox($related_keywords);
        
        wp_die(json_encode(array(
            'success' => true,
            'data' => array('html' => $html)
        )));
        
    } catch (Exception $e) {
        wp_die(json_encode(array(
            'success' => false,
            'message' => $e->getMessage()
        )));
    }
}
add_action('wp_ajax_tcl_load_related_keywords', 'tcl_ajax_load_related_keywords');

/**
 * AI によるキーワード生成
 */
function tcl_generate_ai_keywords($title, $content) {
    $api_key = get_option('tcl_api_key');
    if (empty($api_key)) {
        throw new Exception('ChatGPT APIキーが設定されていません');
    }
    
    $prompt = "以下の記事のタイトルと内容から、関連するクラスターキーワードを5個提案してください。\n\n";
    $prompt .= "タイトル: {$title}\n";
    $prompt .= "内容: " . substr($content, 0, 500) . "\n\n";
    $prompt .= "条件:\n";
    $prompt .= "- SEO効果の高いキーワードを選択\n";
    $prompt .= "- 検索ボリュームのあるキーワード\n";
    $prompt .= "- カンマ区切りで出力\n";
    $prompt .= "- キーワードのみ出力（説明不要）";
    
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_tokens' => 200,
            'temperature' => 0.7
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        throw new Exception('API通信エラー: ' . $response->get_error_message());
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('APIレスポンスが無効です');
    }
    
    $keywords_text = trim($data['choices'][0]['message']['content']);
    $keywords = array_map('trim', explode(',', $keywords_text));
    
    return array_filter($keywords);
}

/**
 * 検索データ連携によるキーワード生成
 */
function tcl_generate_search_keywords($title, $content) {
    // タイトルから主要キーワードを抽出
    $main_keywords = tcl_extract_main_keywords($title);
    
    $all_keywords = array();
    
    foreach ($main_keywords as $keyword) {
        $related = tcl_get_autocomplete_keywords($keyword);
        $all_keywords = array_merge($all_keywords, $related);
    }
    
    // 重複を除去し、最大8個まで
    $unique_keywords = array_unique($all_keywords);
    return array_slice($unique_keywords, 0, 8);
}

/**
 * タイトルから主要キーワードを抽出
 */
function tcl_extract_main_keywords($title) {
    // 日本語の助詞・接続詞などを除外
    $stop_words = array('は', 'が', 'を', 'に', 'で', 'と', 'の', 'や', 'も', 'から', 'まで', 'より', 'こと', 'もの', 'これ', 'それ', 'あれ');
    
    // 単語を分割（簡易版）
    $words = preg_split('/[\s\p{P}]+/u', $title);
    $keywords = array();
    
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) > 2 && !in_array($word, $stop_words)) {
            $keywords[] = $word;
        }
    }
    
    return array_slice($keywords, 0, 3);
}

/**
 * 記事アイデア生成
 */
function tcl_generate_article_ideas($title, $content) {
    $api_key = get_option('tcl_api_key');
    if (empty($api_key)) {
        throw new Exception('ChatGPT APIキーが設定されていません');
    }
    
    $prompt = "以下の記事に関連する、新しい記事のアイデアを5つ提案してください。\n\n";
    $prompt .= "タイトル: {$title}\n";
    $prompt .= "内容: " . substr($content, 0, 500) . "\n\n";
    $prompt .= "条件:\n";
    $prompt .= "- SEO価値のある記事タイトル\n";
    $prompt .= "- 読者にとって有益な内容\n";
    $prompt .= "- 各アイデアは1行で簡潔に\n";
    $prompt .= "- 番号付きリストで出力";
    
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_tokens' => 300,
            'temperature' => 0.8
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        throw new Exception('API通信エラー: ' . $response->get_error_message());
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!isset($data['choices'][0]['message']['content'])) {
        throw new Exception('APIレスポンスが無効です');
    }
    
    $ideas_text = trim($data['choices'][0]['message']['content']);
    $ideas = explode("\n", $ideas_text);
    
    return array_filter($ideas);
}

/**
 * 生成されたキーワードをHTMLで表示
 */
function tcl_render_generated_keywords($keywords, $method) {
    $method_label = ($method === 'ai') ? 'AI生成' : '検索データ連携';
    
    $html = '<div style="background: #e8f5e8; padding: 10px; border-radius: 4px; margin: 10px 0;">';
    $html .= '<h5 style="margin: 0 0 8px 0;">🎯 ' . $method_label . ' 結果</h5>';
    
    foreach ($keywords as $keyword) {
        $html .= '<span style="display: inline-block; background: #fff; border: 1px solid #0073aa; color: #0073aa; padding: 4px 8px; margin: 2px; border-radius: 12px; font-size: 11px;">';
        $html .= esc_html($keyword);
        $html .= '</span>';
    }
    
    $html .= '<div style="margin-top: 10px;">';
    $html .= '<button type="button" class="button button-primary" onclick="tclSaveKeywords(\'' . implode('、', $keywords) . '\')" style="width: 100%;">💾 これらのキーワードを保存</button>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '<script>
    function tclSaveKeywords(keywords) {
        if (confirm("ピラーキーワードとして保存しますか？")) {
            jQuery("input[name=\'acf[field_pillar_keywords]\']").val(keywords);
            alert("キーワードが保存されました。投稿を更新してください。");
        }
    }
    </script>';
    
    return $html;
}

/**
 * 記事アイデアをHTMLで表示
 */
function tcl_render_article_ideas($ideas) {
    $html = '<div style="background: #f0f8ff; padding: 10px; border-radius: 4px; margin: 10px 0;">';
    $html .= '<h5 style="margin: 0 0 8px 0;">💡 記事アイデア提案</h5>';
    
    foreach ($ideas as $idea) {
        $idea = trim($idea);
        if (empty($idea)) continue;
        
        $html .= '<div style="background: #fff; padding: 8px; margin: 5px 0; border-left: 3px solid #0066cc; border-radius: 2px; font-size: 12px;">';
        $html .= esc_html($idea);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * メタボックス内で関連キーワードを表示
 */
function tcl_render_related_keywords_in_metabox($related_keywords) {
    $html = '<div style="background: #f8f9fa; padding: 10px; border-radius: 4px;">';
    $html .= '<h5 style="margin: 0 0 10px 0;">🔍 関連キーワード</h5>';
    
    if (empty($related_keywords)) {
        $html .= '<p style="color: #666; font-style: italic;">関連キーワードが見つかりませんでした。</p>';
    } else {
        foreach ($related_keywords as $base_keyword => $suggestions) {
            if (!empty($suggestions)) {
                $html .= '<div style="margin-bottom: 10px;">';
                $html .= '<h6 style="margin: 0 0 5px 0; color: #0073aa;">「' . esc_html($base_keyword) . '」関連</h6>';
                
                foreach ($suggestions as $suggestion) {
                    $html .= '<span style="display: inline-block; background: #e3f2fd; color: #1976d2; padding: 2px 6px; margin: 1px; border-radius: 8px; font-size: 10px;">';
                    $html .= esc_html($suggestion);
                    $html .= '</span>';
                }
                
                $html .= '</div>';
            }
        }
    }
    
    $html .= '</div>';
    
    return $html;
}