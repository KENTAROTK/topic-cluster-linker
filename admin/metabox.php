<?php
/**
 * Topic Cluster Linker - メタボックス機能
 * 投稿編集画面でのSEO内部リンク管理
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

/**
 * メタボックス管理クラス
 */
class TCL_Metabox {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post', [$this, 'save_metabox_data'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_metabox_assets']);
    }
    
    /**
     * メタボックスを追加
     */
    public function add_metabox() {
        $post_types = apply_filters('tcl_metabox_post_types', ['post', 'local_trouble']);
        
        add_meta_box(
            'tcl-cluster-links',
            '🔗 SEO内部リンク管理 - Topic Cluster',
            [$this, 'metabox_callback'],
            $post_types,
            'side',
            'high'
        );
    }
    
    /**
     * メタボックスの内容を表示
     */
    public function metabox_callback($post) {
        $proposals = get_option('tcl_cluster_proposals', []);
        $current_post_id = $post->ID;
        
        // ナンス追加
        wp_nonce_field('tcl_metabox_nonce', 'tcl_metabox_nonce_field');
        
        // スタイル出力
        $this->output_metabox_styles();
        
        // メタボックスのメイン処理
        echo '<div class="tcl-metabox-container">';
        $this->display_metabox_content($post, $proposals);
        echo '</div>';
    }
    
    /**
     * メタボックス用スタイル
     */
    private function output_metabox_styles() {
        ?>
        <style>
            .tcl-metabox-container {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .tcl-status-box {
                padding: 12px;
                border-radius: 6px;
                margin-bottom: 15px;
                border-left: 4px solid;
                font-size: 13px;
                line-height: 1.4;
            }
            .tcl-success {
                background: #d1edff;
                border-left-color: #0073aa;
                color: #0073aa;
            }
            .tcl-warning {
                background: #fff3cd;
                border-left-color: #ffc107;
                color: #856404;
            }
            .tcl-error {
                background: #f8d7da;
                border-left-color: #dc3545;
                color: #721c24;
            }
            .tcl-link-box {
                border: 1px solid #ddd;
                padding: 12px;
                margin-bottom: 12px;
                background: #f9f9f9;
                border-radius: 6px;
                transition: box-shadow 0.2s;
            }
            .tcl-link-box:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .tcl-link-title {
                font-weight: 600;
                color: #23282d;
                margin-bottom: 6px;
                font-size: 14px;
            }
            .tcl-keywords {
                font-size: 11px;
                color: #666;
                margin-bottom: 8px;
                font-style: italic;
            }
            .tcl-preview {
                font-size: 12px;
                line-height: 1.5;
                margin: 8px 0;
                padding: 10px;
                background: white;
                border: 1px solid #e1e1e1;
                border-radius: 4px;
                border-left: 3px solid #0073aa;
                min-height: 40px;
            }
            .tcl-button-group {
                margin-top: 10px;
                display: flex;
                gap: 8px;
            }
            .tcl-button {
                border: none;
                padding: 8px 14px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 500;
                transition: all 0.2s;
            }
            .tcl-button-primary {
                background: #0073aa;
                color: white;
            }
            .tcl-button-primary:hover {
                background: #005a87;
                color: white;
            }
            .tcl-button-secondary {
                background: #6c757d;
                color: white;
            }
            .tcl-button-secondary:hover {
                background: #545b62;
                color: white;
            }
            .tcl-section-title {
                font-size: 14px;
                font-weight: 600;
                color: #23282d;
                margin: 20px 0 12px 0;
                padding-bottom: 8px;
                border-bottom: 2px solid #0073aa;
            }
            .tcl-stats-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 15px;
            }
            .tcl-stat-item {
                text-align: center;
                padding: 8px;
                background: #f1f1f1;
                border-radius: 4px;
                font-size: 12px;
            }
            .tcl-stat-number {
                display: block;
                font-size: 18px;
                font-weight: bold;
                color: #0073aa;
            }
            .tcl-no-clusters {
                text-align: center;
                padding: 20px;
                color: #666;
                font-style: italic;
            }
            .tcl-help-text {
                font-size: 11px;
                color: #666;
                margin-top: 8px;
                line-height: 1.4;
            }
            .tcl-pillar-link {
                color: #0073aa;
                text-decoration: none;
                font-weight: 500;
            }
            .tcl-cluster-creation-box {
                border: 1px solid #0073aa;
                padding: 15px;
                background: #f0f8ff;
                border-radius: 6px;
                margin-top: 15px;
            }
        </style>
        <?php
    }
    
    /**
     * メタボックスのメインコンテンツ表示
     */
    private function display_metabox_content($post, $proposals) {
        $current_post_id = $post->ID;
        
        // トピッククラスター関係の分析
        $cluster_analysis = $this->analyze_post_cluster_relationship($current_post_id, $proposals);
        
        if (!$cluster_analysis['is_in_cluster']) {
            $this->display_no_cluster_message();
            return;
        }
        
        // ステータス表示
        $this->display_cluster_status($cluster_analysis);
        
        // リンク統計
        $this->display_link_statistics($cluster_analysis);
        
        // リンク候補表示
        if ($cluster_analysis['remaining_links'] > 0) {
            $this->display_link_candidates($post, $cluster_analysis);
        } else {
            $this->display_max_links_reached();
        }
        
        // ヘルプテキスト
        $this->display_help_text();
    }
    
    /**
     * 投稿のクラスター関係を分析
     */
    private function analyze_post_cluster_relationship($post_id, $proposals) {
        $analysis = [
            'is_in_cluster' => false,
            'is_pillar' => false,
            'pillar_id' => null,
            'pillar_title' => '',
            'available_clusters' => [],
            'existing_links' => 0,
            'remaining_links' => 0,
            'max_links' => get_option('tcl_max_links_per_post', 2),
        ];
        
        // ピラーページかチェック（修正版）
        $pillar_keywords = get_field('pillar_keywords', $post_id);
        if ($pillar_keywords) {
            $analysis['is_in_cluster'] = true;
            $analysis['is_pillar'] = true;
            $analysis['pillar_id'] = $post_id;
            $analysis['pillar_title'] = get_the_title($post_id);
            // クラスターがない場合でも空配列を設定
            $analysis['available_clusters'] = isset($proposals[$post_id]) ? $proposals[$post_id] : [];
            
            // デバッグログ追加
            if (function_exists('tcl_log_message')) {
                tcl_log_message("ピラーページ検出: ID {$post_id}, クラスター数: " . count($analysis['available_clusters']));
            }
        } else {
            // クラスターページかチェック
            foreach ($proposals as $pillar_id => $clusters) {
                foreach ($clusters as $cluster) {
                    if ($cluster['cluster_id'] == $post_id) {
                        $analysis['is_in_cluster'] = true;
                        $analysis['is_pillar'] = false;
                        $analysis['pillar_id'] = $pillar_id;
                        $analysis['pillar_title'] = get_the_title($pillar_id);
                        break 2;
                    }
                }
            }
        }
        
        // 既存リンク数を計算
        if ($analysis['is_in_cluster']) {
            $analysis['existing_links'] = $this->count_existing_links($post_id, $proposals);
            $analysis['remaining_links'] = max(0, $analysis['max_links'] - $analysis['existing_links']);
        }
        
        return $analysis;
    }
    
    /**
     * クラスターに含まれていない場合のメッセージ
     */
    private function display_no_cluster_message() {
        echo '<div class="tcl-status-box tcl-warning">';
        echo '<strong>📝 トピッククラスターステータス</strong><br>';
        echo 'この投稿はトピッククラスターに含まれていません。<br>';
        echo '<div class="tcl-help-text">';
        echo '• ピラーページを作成するには、ACFで「pillar_keywords」フィールドを設定<br>';
        echo '• 既存のピラーページにキーワードを追加して関連付け<br>';
        echo '• <a href="' . admin_url('admin.php?page=topic-cluster-linker') . '" class="tcl-pillar-link">管理画面</a>で「クラスターページ再提案」を実行';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * クラスターステータス表示
     */
    private function display_cluster_status($analysis) {
        echo '<div class="tcl-status-box tcl-success">';
        echo '<strong>📍 トピッククラスター情報</strong><br>';
        
        if ($analysis['is_pillar']) {
            echo '<span style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">PILLAR PAGE</span><br>';
            echo 'このページはピラーページです';
            
            // クラスター数の表示
            $cluster_count = count($analysis['available_clusters']);
            echo '<br><small style="color: #666;">関連クラスター: ' . $cluster_count . '件</small>';
        } else {
            echo '<span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">CLUSTER PAGE</span><br>';
            echo '関連ピラー: <a href="' . get_edit_post_link($analysis['pillar_id']) . '" target="_blank" class="tcl-pillar-link">' . esc_html($analysis['pillar_title']) . '</a>';
        }
        echo '</div>';
    }
    
    /**
     * リンク統計表示
     */
    private function display_link_statistics($analysis) {
        echo '<div class="tcl-stats-grid">';
        
        echo '<div class="tcl-stat-item">';
        echo '<span class="tcl-stat-number">' . $analysis['existing_links'] . '/' . $analysis['max_links'] . '</span>';
        echo '<span>設定済みリンク</span>';
        echo '</div>';
        
        echo '<div class="tcl-stat-item">';
        echo '<span class="tcl-stat-number">' . $analysis['remaining_links'] . '</span>';
        echo '<span>追加可能</span>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * リンク候補表示
     */
    private function display_link_candidates($post, $analysis) {
        if ($analysis['is_pillar']) {
            echo '<div class="tcl-section-title">📋 推奨クラスターページ</div>';
            if (!empty($analysis['available_clusters'])) {
                $this->display_cluster_link_candidates($post, $analysis);
            } else {
                echo '<div class="tcl-no-clusters" style="text-align: center; padding: 20px; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 6px;">';
                echo '<div style="font-size: 24px; margin-bottom: 10px;">📄</div>';
                echo '<strong>関連するクラスターページはまだありません</strong><br>';
                echo '<small style="color: #666; margin-top: 8px; display: block;">';
                echo '• <a href="' . admin_url('admin.php?page=topic-cluster-linker') . '" class="tcl-pillar-link">管理画面</a>で「クラスターページ再提案」を実行<br>';
                echo '• 関連記事を作成すると自動的に提案されます';
                echo '</small>';
                echo '</div>';
            }
            
            // 新機能: クラスターページ作成提案を追加
            echo '<div class="tcl-section-title" style="margin-top: 25px;">💡 新しいクラスターページを作成</div>';
            $this->display_cluster_creation_suggestion($post, $analysis);
        }
        
        if (!$analysis['is_pillar']) {
            echo '<div class="tcl-section-title">📌 ピラーページへのリンク</div>';
            $this->display_pillar_link_candidate($post, $analysis);
        }
    }
    
    /**
     * クラスターページへのリンク候補表示
     */
    private function display_cluster_link_candidates($post, $analysis) {
        $count = 0;
        $remaining = $analysis['remaining_links'];
        
        foreach ($analysis['available_clusters'] as $cluster) {
            if ($count >= $remaining) break;
            
            $cluster_post = get_post($cluster['cluster_id']);
            if (!$cluster_post) continue;
            
            echo '<div class="tcl-link-box" data-cluster-id="' . $cluster['cluster_id'] . '">';
            echo '<div class="tcl-link-title">' . esc_html($cluster_post->post_title) . '</div>';
            
            if (!empty($cluster['matched_keywords'])) {
                echo '<div class="tcl-keywords">🏷️ ' . implode(', ', array_map('esc_html', $cluster['matched_keywords'])) . '</div>';
            }
            
            $link_text = $this->generate_link_text_safe($post, $cluster_post);
            
            echo '<div class="tcl-preview" id="preview-' . $cluster['cluster_id'] . '">' . $link_text . '</div>';
            
            echo '<div class="tcl-button-group">';
            echo '<button type="button" class="tcl-button tcl-button-primary insert-tcl-link" data-insert="' . esc_attr($link_text) . '" data-target-id="' . $cluster['cluster_id'] . '">';
            echo '🔗 挿入</button>';
            echo '<button type="button" class="tcl-button tcl-button-secondary tcl-reload" data-post-id="' . $post->ID . '" data-cluster-id="' . $cluster['cluster_id'] . '">';
            echo '🔄 再生成</button>';
            echo '</div>';
            echo '</div>';
            
            $count++;
        }
        
        if ($count === 0) {
            echo '<div class="tcl-no-clusters">利用可能なクラスターページがありません</div>';
        }
    }
    
    /**
     * ピラーページへのリンク候補表示
     */
    private function display_pillar_link_candidate($post, $analysis) {
        $pillar_post = get_post($analysis['pillar_id']);
        if (!$pillar_post) return;
        
        echo '<div class="tcl-link-box" data-cluster-id="' . $analysis['pillar_id'] . '">';
        echo '<div class="tcl-link-title">' . esc_html($pillar_post->post_title) . '</div>';
        
        $link_text = $this->generate_link_text_safe($post, $pillar_post);
        
        echo '<div class="tcl-preview" id="preview-' . $analysis['pillar_id'] . '">' . $link_text . '</div>';
        
        echo '<div class="tcl-button-group">';
        echo '<button type="button" class="tcl-button tcl-button-primary insert-tcl-link" data-insert="' . esc_attr($link_text) . '" data-target-id="' . $analysis['pillar_id'] . '">';
        echo '🔗 挿入</button>';
        echo '<button type="button" class="tcl-button tcl-button-secondary tcl-reload" data-post-id="' . $post->ID . '" data-cluster-id="' . $analysis['pillar_id'] . '">';
        echo '🔄 再生成</button>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * 最大リンク数到達メッセージ
     */
    private function display_max_links_reached() {
        echo '<div class="tcl-status-box tcl-error">';
        echo '<strong>⚠️ リンク上限到達</strong><br>';
        echo 'このページは既に最大数のリンクが設定されています。<br>';
        echo '<div class="tcl-help-text">';
        echo 'SEO効果を最大化するため、1投稿あたりのリンク数を制限しています。';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * ヘルプテキスト表示
     */
    private function display_help_text() {
        echo '<div class="tcl-help-text" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">';
        echo '<strong>💡 使い方のヒント:</strong><br>';
        echo '• 「🔗 挿入」でエディタにリンクを追加<br>';
        echo '• 「🔄 再生成」でAIが新しいリンクテキストを作成<br>';
        echo '• リンクは文脈に合わせて自動調整されます<br>';
        echo '• <a href="' . admin_url('admin.php?page=topic-cluster-linker') . '" class="tcl-pillar-link">管理画面</a>で全体の構造を確認';
        echo '</div>';
    }
    
    /**
     * 既存リンク数をカウント
     */
    private function count_existing_links($post_id, $proposals) {
        $content = get_post_field('post_content', $post_id);
        $count = 0;
        
        foreach ($proposals as $pillar_id => $clusters) {
            // ピラーページへのリンクをチェック
            if (strpos($content, get_permalink($pillar_id)) !== false) {
                $count++;
            }
            
            // クラスターページへのリンクをチェック
            foreach ($clusters as $cluster) {
                if (strpos($content, get_permalink($cluster['cluster_id'])) !== false) {
                    $count++;
                }
            }
        }
        
        return min($count, get_option('tcl_max_links_per_post', 2));
    }
    
    /**
     * 安全なリンクテキスト生成
     */
    private function generate_link_text_safe($post, $target_post) {
        if (function_exists('tcl_generate_contextual_link_text')) {
            $generated = tcl_generate_contextual_link_text(
                $post->post_content,
                $target_post->post_title,
                get_permalink($target_post->ID)
            );
            
            // エラーでない場合は生成されたテキストを返す
            if (strpos($generated, 'エラー') === false && strpos($generated, '❌') === false) {
                return $generated;
            }
        }
        
        // フォールバック: シンプルなリンク
        return sprintf(
            '詳しくは<a href="%s">%s</a>をご覧ください。',
            get_permalink($target_post->ID),
            esc_html($target_post->post_title)
        );
    }
    
    /**
     * メタボックスデータの保存
     */
    public function save_metabox_data($post_id, $post) {
        // 自動保存やリビジョンをスキップ
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        
        // ナンスチェック
        if (!isset($_POST['tcl_metabox_nonce_field']) || 
            !wp_verify_nonce($_POST['tcl_metabox_nonce_field'], 'tcl_metabox_nonce')) {
            return;
        }
        
        // 権限チェック
        if (!current_user_can('edit_post', $post_id)) return;
        
        if (function_exists('tcl_log_message')) {
            tcl_log_message("メタボックス保存処理: Post ID {$post_id}");
        }
    }
    
    /**
     * クラスターページ作成提案表示（修正版）
     */
    private function display_cluster_creation_suggestion($post, $analysis) {
        echo '<div class="tcl-cluster-creation-box">';
        
        // セクション1: クラスターキーワード生成方法（修正版）
        echo '<div style="margin-bottom: 20px;">';
        echo '<h4 style="margin: 0 0 10px 0; color: #0073aa; font-size: 14px;">🎯 クラスターキーワード生成方法</h4>';
        echo '<p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">2つの方法から選択してください</p>';
        
        echo '<button type="button" id="tcl-generate-keyword-map-basic" class="tcl-button tcl-button-secondary" style="width: 48%; margin-bottom: 10px; margin-right: 4%;" data-post-id="' . $post->ID . '">';
        echo '🤖 AI生成（基本）';
        echo '</button>';
        
        echo '<button type="button" id="tcl-generate-keyword-map-advanced" class="tcl-button tcl-button-primary" style="width: 48%; margin-bottom: 10px;" data-post-id="' . $post->ID . '">';
        echo '🔍 検索データ連携（推奨）';
        echo '</button>';
        
        echo '<div id="tcl-keyword-map" style="display: none; margin-top: 10px;"></div>';
        echo '</div>';
        
        // セクション2: 記事アイデア生成
        echo '<div style="border-top: 1px solid #ddd; padding-top: 15px;">';
        echo '<h4 style="margin: 0 0 10px 0; color: #0073aa; font-size: 14px;">💡 具体的な記事アイデア提案</h4>';
        echo '<p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">選択したキーワードから具体的な記事を提案します</p>';
        
        echo '<button type="button" id="tcl-suggest-cluster-ideas" class="tcl-button tcl-button-secondary" style="width: 100%; margin-bottom: 10px;" data-post-id="' . $post->ID . '">';
        echo '📝 記事アイデア生成';
        echo '</button>';
        
        echo '<div id="tcl-cluster-suggestions" style="display: none; margin-top: 10px;"></div>';
        echo '</div>';
        
        echo '</div>';
        
        // JavaScript追加
        $this->add_cluster_suggestion_script();
    }
    
    /**
     * クラスター提案用JavaScript（修正版）
     */
    private function add_cluster_suggestion_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // キーワードマップ生成（基本版）
            $('#tcl-generate-keyword-map-basic').on('click', function() {
                const $button = $(this);
                const postId = $button.data('post-id');
                const originalText = $button.text();
                
                $button.text('🤖 AI分析中...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'tcl_generate_keyword_map',
                        post_id: postId,
                        nonce: $('#tcl_metabox_nonce_field').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            displayKeywordMap(response.data);
                            $('#tcl-keyword-map').show();
                        } else {
                            alert('エラー: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('通信エラーが発生しました');
                    },
                    complete: function() {
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // キーワードマップ生成（高度版）
            $('#tcl-generate-keyword-map-advanced').on('click', function() {
                const $button = $(this);
                const postId = $button.data('post-id');
                const originalText = $button.text();
                
                $button.text('🔍 検索データ取得中...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'tcl_generate_keyword_map_advanced',
                        post_id: postId,
                        nonce: $('#tcl_metabox_nonce_field').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            displayKeywordMap(response.data);
                            $('#tcl-keyword-map').show();
                        } else {
                            alert('エラー: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('通信エラーが発生しました');
                    },
                    complete: function() {
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // 記事アイデア生成
            $('#tcl-suggest-cluster-ideas').on('click', function() {
                const $button = $(this);
                const postId = $button.data('post-id');
                const originalText = $button.text();
                
                // 選択されたキーワードを取得
                const selectedKeywords = [];
                $('.keyword-checkbox:checked').each(function() {
                    selectedKeywords.push($(this).val());
                });
                
                if (selectedKeywords.length === 0) {
                    alert('記事にしたいキーワードを先に選択してください');
                    return;
                }
                
                $button.text('🤖 記事アイデア生成中...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'tcl_suggest_cluster_ideas',
                        post_id: postId,
                        selected_keywords: selectedKeywords,
                        nonce: $('#tcl_metabox_nonce_field').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            displayClusterSuggestions(response.data);
                            $('#tcl-cluster-suggestions').show();
                        } else {
                            alert('エラー: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('通信エラーが発生しました');
                    },
                    complete: function() {
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            });
            
          // リンク挿入機能
           $(document).on('click', '.insert-tcl-link', function() {
               const linkText = $(this).data('insert');
               
               if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                   // ビジュアルエディタに挿入
                   tinymce.activeEditor.execCommand('mceInsertContent', false, linkText);
                   alert('🔗 リンクを挿入しました！');
               } else {
                   // テキストエディタに挿入
                   const textarea = document.getElementById('content');
                   if (textarea) {
                       const cursorPos = textarea.selectionStart;
                       const textBefore = textarea.value.substring(0, cursorPos);
                       const textAfter = textarea.value.substring(cursorPos);
                       textarea.value = textBefore + '\n\n' + linkText + '\n\n' + textAfter;
                       alert('🔗 リンクを挿入しました！');
                   } else {
                       // フォールバック: クリップボードにコピー
                       if (navigator.clipboard) {
                           navigator.clipboard.writeText(linkText).then(function() {
                               alert('📋 リンクをクリップボードにコピーしました！手動でエディタに貼り付けてください。');
                           });
                       } else {
                           alert('リンクテキスト: ' + linkText);
                       }
                   }
               }
           });
           
           // リンク再生成機能
           $(document).on('click', '.tcl-reload', function() {
               const $button = $(this);
               const postId = $button.data('post-id');
               const clusterId = $button.data('cluster-id');
               const originalText = $button.text();
               
               $button.text('🔄 生成中...').prop('disabled', true);
               
               $.ajax({
                   url: ajaxurl,
                   method: 'POST',
                   data: {
                       action: 'tcl_regenerate_link',
                       post_id: postId,
                       cluster_id: clusterId,
                       nonce: $('#tcl_metabox_nonce_field').val()
                   },
                   success: function(response) {
                       if (response.success) {
                           const $preview = $('#preview-' + clusterId);
                           const $insertButton = $button.siblings('.insert-tcl-link');
                           
                           $preview.html(response.data.text);
                           $insertButton.data('insert', response.data.text);
                           
                           // ボタンを少し光らせるエフェクト
                           $preview.css('background', '#e8f4fd').animate({backgroundColor: 'white'}, 1000);
                       } else {
                           alert('エラー: ' + response.data);
                       }
                   },
                   error: function() {
                       alert('通信エラーが発生しました');
                   },
                   complete: function() {
                       $button.text(originalText).prop('disabled', false);
                   }
               });
           });
           
           // キーワードマップ表示
           function displayKeywordMap(data) {
               const $container = $('#tcl-keyword-map');
               let html = '<div style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">';
               
               if (data.keyword_categories) {
                   html += '<div style="margin-bottom: 15px; padding: 10px; background: #e8f4fd; border-left: 3px solid #0073aa;">';
                   html += '<strong>🎯 総キーワード数: ' + data.total_keywords + '個</strong><br>';
                   html += '<small>検索ボリュームと競合レベルを考慮して優先度付けしています</small>';
                   html += '</div>';
                   
                   Object.keys(data.keyword_categories).forEach(function(category) {
                       const keywords = data.keyword_categories[category];
                       if (keywords.length > 0) {
                           html += '<div style="margin-bottom: 20px;">';
                           html += '<h5 style="margin: 0 0 10px 0; color: #0073aa; font-size: 13px;">' + category + ' (' + keywords.length + '個)</h5>';
                           
                           keywords.forEach(function(keyword, index) {
                               const priority = keyword.priority || 'medium';
                               const priorityColor = priority === 'high' ? '#28a745' : priority === 'medium' ? '#ffc107' : '#6c757d';
                               const difficultyText = keyword.difficulty || '不明';
                               const keywordId = 'kw-' + category.replace(/[^a-zA-Z0-9]/g, '') + '-' + index;
                               
                               html += '<div style="display: flex; align-items: center; margin-bottom: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">';
                               html += '<input type="checkbox" class="keyword-checkbox" value="' + keyword.text + '" id="' + keywordId + '" style="margin-right: 8px;">';
                               html += '<label for="' + keywordId + '" style="flex: 1; font-size: 12px; cursor: pointer;">' + keyword.text + '</label>';
                               html += '<span style="font-size: 10px; padding: 2px 6px; background: ' + priorityColor + '; color: white; border-radius: 3px; margin-left: 8px;">優先度: ' + priority + '</span>';
                               html += '<span style="font-size: 10px; color: #666; margin-left: 8px;">難易度: ' + difficultyText + '</span>';
                               html += '</div>';
                           });
                           
                           html += '</div>';
                       }
                   });
                   
                   // 一括選択ボタン
                   html += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
                   html += '<button type="button" id="select-high-priority" style="margin-right: 8px; padding: 4px 8px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">高優先度を選択</button>';
                   html += '<button type="button" id="select-all-keywords" style="margin-right: 8px; padding: 4px 8px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">全て選択</button>';
                   html += '<button type="button" id="clear-selection" style="padding: 4px 8px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">選択解除</button>';
                   html += '<div style="margin-top: 8px; font-size: 11px; color: #666;">💡 記事にしたいキーワードを選択して「記事アイデア生成」ボタンを押してください</div>';
                   html += '</div>';
               }
               
               if (data.strategy_notes) {
                   html += '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;">';
                   html += '<strong>💡 キーワード戦略メモ:</strong><br>' + data.strategy_notes;
                   html += '</div>';
               }
               
               html += '</div>';
               
               $container.html(html);
               
               // 一括選択機能
               $('#select-high-priority').on('click', function() {
                   $('.keyword-checkbox').prop('checked', false);
                   $('.keyword-checkbox').each(function() {
                       const $row = $(this).closest('div');
                       if ($row.find('span:contains("high")').length > 0) {
                           $(this).prop('checked', true);
                       }
                   });
               });
               
               $('#select-all-keywords').on('click', function() {
                   $('.keyword-checkbox').prop('checked', true);
               });
               
               $('#clear-selection').on('click', function() {
                   $('.keyword-checkbox').prop('checked', false);
               });
           }
           
           // 記事提案表示
           function displayClusterSuggestions(data) {
               const $container = $('#tcl-cluster-suggestions');
               let html = '<div style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">';
               
               if (data.cluster_ideas && data.cluster_ideas.length > 0) {
                   html += '<h4 style="margin: 0 0 15px 0; color: #0073aa;">📝 提案されたクラスター記事</h4>';
                   
                   data.cluster_ideas.forEach(function(idea, index) {
                       html += '<div style="border: 1px solid #e1e1e1; margin-bottom: 15px; padding: 12px; border-radius: 4px; background: #f9f9f9;">';
                       html += '<div style="font-weight: 600; color: #23282d; margin-bottom: 8px;">' + (index + 1) + '. ' + idea.title + '</div>';
                       
                       if (idea.keywords) {
                           html += '<div style="margin-bottom: 8px;"><strong>🏷️ 狙いキーワード:</strong> ' + idea.keywords.join(', ') + '</div>';
                       }
                       
                       if (idea.outline) {
                           html += '<div style="margin-bottom: 8px;"><strong>📋 記事構成:</strong></div>';
                           html += '<ul style="margin: 0; padding-left: 20px; font-size: 12px;">';
                           idea.outline.forEach(function(point) {
                               html += '<li>' + point + '</li>';
                           });
                           html += '</ul>';
                       }
                       
                       if (idea.connection_strategy) {
                           html += '<div style="margin-top: 8px; font-size: 12px; color: #666;">';
                           html += '<strong>🔗 内部リンク戦略:</strong> ' + idea.connection_strategy;
                           html += '</div>';
                       }
                       
                       html += '<div style="margin-top: 10px;">';
                       html += '<button type="button" class="tcl-copy-idea" data-title="' + idea.title + '" data-keywords="' + (idea.keywords ? idea.keywords.join(', ') : '') + '" style="font-size: 11px; padding: 4px 8px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">📋 コピー</button>';
                       html += '</div>';
                       
                       html += '</div>';
                   });
               }
               
               if (data.strategy_notes) {
                   html += '<div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 3px solid #0073aa;">';
                   html += '<strong>💡 戦略メモ:</strong><br>' + data.strategy_notes;
                   html += '</div>';
               }
               
               html += '</div>';
               
               $container.html(html);
               
               // コピー機能
               $('.tcl-copy-idea').on('click', function() {
                   const title = $(this).data('title');
                   const keywords = $(this).data('keywords');
                   const copyText = '記事タイトル: ' + title + '\nキーワード: ' + keywords;
                   
                   if (navigator.clipboard) {
                       navigator.clipboard.writeText(copyText).then(function() {
                           alert('📋 記事アイデアをクリップボードにコピーしました！');
                       });
                   } else {
                       // フォールバック
                       const textArea = document.createElement('textarea');
                       textArea.value = copyText;
                       document.body.appendChild(textArea);
                       textArea.select();
                       document.execCommand('copy');
                       document.body.removeChild(textArea);
                       alert('📋 記事アイデアをクリップボードにコピーしました！');
                   }
               });
           }
       });
       </script>
       <?php
   }
   
   /**
    * メタボックス用アセット読み込み
    */
   public function enqueue_metabox_assets($hook) {
       if (!in_array($hook, ['post.php', 'post-new.php'])) {
           return;
       }
       
       wp_add_inline_style('wp-admin', '
           #tcl-cluster-links .inside { padding: 0; }
           #tcl-cluster-links .tcl-metabox-container { padding: 12px; }
       ');
       
       // jQuery UI for animations
       wp_enqueue_script('jquery-ui-effects-core');
       wp_enqueue_script('jquery-effects-highlight');
   }
}

// メタボックスクラスのインスタンス化
new TCL_Metabox();