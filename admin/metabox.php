<?php
// 投稿編集画面にメタボックスを追加
add_action('add_meta_boxes', 'tcl_add_metabox');

function tcl_add_metabox() {
    add_meta_box(
        'tcl-cluster-links',
        'トピッククラスターリンク',
        'tcl_metabox_callback',
        ['post', 'local_trouble'],
        'side',
        'high'
    );
}

function tcl_metabox_callback($post) {
    $proposals = get_option('tcl_cluster_proposals', []);
    $current_post_id = $post->ID;
    
    // 現在の投稿に関連するピラーページを特定
    $related_pillar = null;
    $available_clusters = [];
    
    // 現在の投稿がピラーページかチェック
    $pillar_keywords = get_field('pillar_keywords', $current_post_id);
    if ($pillar_keywords && isset($proposals[$current_post_id])) {
        $related_pillar = $current_post_id;
        $available_clusters = $proposals[$current_post_id];
    } else {
        // 現在の投稿がクラスターページの場合、関連ピラーを検索
        foreach ($proposals as $pillar_id => $clusters) {
            foreach ($clusters as $cluster) {
                if ($cluster['cluster_id'] == $current_post_id) {
                    $related_pillar = $pillar_id;
                    break 2;
                }
            }
        }
    }
    
    if (!$related_pillar) {
        echo '<p>この投稿はトピッククラスターに含まれていません。</p>';
        return;
    }
    
    // 既存のリンク数をチェック
    $existing_links = tcl_count_existing_links($current_post_id);
    $remaining_links = 2 - $existing_links;
    
    wp_nonce_field('tcl_generate_links', 'tcl_nonce');
    
    echo '<div style="margin-bottom: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa;">';
    echo '<strong>📍 関連ピラーページ:</strong><br>';
    echo '<a href="' . get_edit_post_link($related_pillar) . '" target="_blank">' . get_the_title($related_pillar) . '</a>';
    echo '</div>';
    
    echo '<div style="margin-bottom: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">';
    echo '<strong>🔗 リンク状況:</strong> ' . $existing_links . '/2 (残り: ' . $remaining_links . '個)';
    echo '</div>';
    
    if ($remaining_links <= 0) {
        echo '<p style="color: #d63384;">⚠️ このページは既に最大数（2個）のリンクが設定されています。</p>';
        return;
    }
    
    // ピラーページの場合はクラスターページへのリンクを提案
    if ($related_pillar == $current_post_id && !empty($available_clusters)) {
        echo '<h4>📋 推奨クラスターページ</h4>';
        $count = 0;
        foreach ($available_clusters as $cluster) {
            if ($count >= $remaining_links) break;
            
            $cluster_post = get_post($cluster['cluster_id']);
            if (!$cluster_post) continue;
            
            echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">';
            echo '<strong>' . esc_html($cluster_post->post_title) . '</strong><br>';
            echo '<small style="color: #666;">マッチキーワード: ' . implode(', ', $cluster['matched_keywords']) . '</small><br>';
            
            $link_text = tcl_generate_contextual_link_text(
                $post->post_content,
                $cluster_post->post_title,
                get_permalink($cluster['cluster_id'])
            );
            
            echo '<div style="font-size: 12px; margin: 5px 0; padding: 5px; background: white; border: 1px solid #eee;">' . $link_text . '</div>';
            echo '<button type="button" class="button button-primary insert-tcl-link" data-insert="' . esc_attr($link_text) . '">挿入</button> ';
            echo '<button type="button" class="button tcl-reload" data-post-id="' . $post->ID . '" data-cluster-id="' . $cluster['cluster_id'] . '">再生成</button>';
            echo '</div>';
            
            $count++;
        }
    }
    
    // クラスターページの場合はピラーページへのリンクを提案
    if ($related_pillar != $current_post_id) {
        echo '<h4>📌 関連ピラーページへのリンク</h4>';
        $pillar_post = get_post($related_pillar);
        
        $link_text = tcl_generate_contextual_link_text(
            $post->post_content,
            $pillar_post->post_title,
            get_permalink($related_pillar)
        );
        
        echo '<div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">';
        echo '<strong>' . esc_html($pillar_post->post_title) . '</strong><br>';
        echo '<div style="font-size: 12px; margin: 5px 0; padding: 5px; background: white; border: 1px solid #eee;">' . $link_text . '</div>';
        echo '<button type="button" class="button button-primary insert-tcl-link" data-insert="' . esc_attr($link_text) . '">挿入</button> ';
        echo '<button type="button" class="button tcl-reload" data-post-id="' . $post->ID . '" data-cluster-id="' . $related_pillar . '">再生成</button>';
        echo '</div>';
    }
}

// 既存のリンク数をカウント
function tcl_count_existing_links($post_id) {
    $content = get_post_field('post_content', $post_id);
    $proposals = get_option('tcl_cluster_proposals', []);
    
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
    
    return min($count, 2); // 最大2個まで
}

// 管理画面でJavaScriptを読み込み
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook == 'post.php' || $hook == 'post-new.php') {
        wp_enqueue_script('tcl-admin', plugin_dir_url(__FILE__) . '../tcl-admin.js', ['jquery'], '1.0', true);
        wp_localize_script('tcl-admin', 'tcl_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcl_ajax_nonce')
        ]);
    }
});