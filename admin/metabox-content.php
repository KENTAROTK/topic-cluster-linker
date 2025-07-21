<?php
/**
 * Topic Cluster Linker - メタボックスコンテンツ
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCL_Metabox_Content {
    
    /**
     * メタボックスコンテンツを表示
     */
    public function display_content($post, $proposals) {
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
        
        // ピラーページかチェック
        $pillar_keywords = get_field('pillar_keywords', $post_id);
        if ($pillar_keywords && isset($proposals[$post_id])) {
            $analysis['is_in_cluster'] = true;
            $analysis['is_pillar'] = true;
            $analysis['pillar_id'] = $post_id;
            $analysis['pillar_title'] = get_the_title($post_id);
            $analysis['available_clusters'] = $proposals[$post_id];
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
        if ($analysis['is_pillar'] && !empty($analysis['available_clusters'])) {
            echo '<div class="tcl-section-title">📋 推奨クラスターページ</div>';
            $this->display_cluster_link_candidates($post, $analysis);
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
            
            $metabox = new TCL_Metabox();
            $link_text = $metabox->generate_link_text_safe($post, $cluster_post);
            
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
        
        $metabox = new TCL_Metabox();
        $link_text = $metabox->generate_link_text_safe($post, $pillar_post);
        
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
}