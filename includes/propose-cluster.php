<?php

// ✅ クラスターページの提案処理
function tcl_run_propose_clusters() {
    tcl_log_message('tcl_run_propose_clusters 実行開始');
    
    $api_key = get_option('tcl_api_key');
    if (!$api_key) {
        tcl_log_message('APIキーが未設定です');
        echo '<div class="notice notice-error"><p>APIキーが設定されていません。設定画面でAPIキーを入力してください。</p></div>';
        return;
    }

    // ACFの pillar_keywords フィールドを持つ投稿を取得
    $pillar_args = array(
        'post_type' => ['post', 'local_trouble'],
        'meta_query' => array(
            array(
                'key' => 'pillar_keywords',
                'compare' => 'EXISTS',
            )
        ),
        'posts_per_page' => -1,
    );
    $pillar_posts = get_posts($pillar_args);

    if (empty($pillar_posts)) {
        tcl_log_message('ピラーページが見つかりません');
        echo '<div class="notice notice-warning"><p>ピラーページが見つかりません。ACFで「pillar_keywords」フィールドを設定した投稿を作成してください。</p></div>';
        return;
    }

    $proposals = [];
    $pillar_count = 0;
    $total_clusters = 0;

    foreach ($pillar_posts as $pillar) {
        $raw_keywords = get_field('pillar_keywords', $pillar->ID);
        if (!$raw_keywords) continue;

        $pillar_count++;

        // カンマで分割してトリム（複数の区切り文字に対応）
        $keywords = array_map('trim', explode('、', str_replace(['，', ','], '、', $raw_keywords)));
        $keywords = array_filter($keywords); // 空の要素を除去
        
        if (empty($keywords)) continue;

        // ピラーページ以外の投稿を取得
        $cluster_args = array(
            'post_type' => ['post', 'local_trouble'],
            'posts_per_page' => -1,
            'post__not_in' => [$pillar->ID],
        );
        $cluster_posts = get_posts($cluster_args);

        foreach ($cluster_posts as $cluster) {
            $found_keywords = [];
            $content = $cluster->post_content . ' ' . $cluster->post_title;
            
            // キーワードマッチング
            foreach ($keywords as $kw) {
                if (stripos($content, $kw) !== false) {
                    $found_keywords[] = $kw;
                }
            }

            // マッチしたキーワードがある場合、提案に追加
            if (!empty($found_keywords)) {
                $proposals[$pillar->ID][] = [
                    'cluster_id' => $cluster->ID,
                    'matched_keywords' => $found_keywords
                ];
                $total_clusters++;
            }
        }
    }

    // 提案結果を保存
    update_option('tcl_cluster_proposals', $proposals);
    tcl_log_message("提案結果: " . print_r($proposals, true));
    
    // 結果を表示
    echo '<div class="notice notice-success"><p>';
    echo "✅ クラスターページの再提案が完了しました。<br>";
    echo "🎯 ピラーページ数: {$pillar_count}件<br>";
    echo "🔗 関連クラスター数: {$total_clusters}件<br>";
    echo "📊 下記の提案結果をご確認ください。";
    echo '</p></div>';
}

// ✅ Authorizationヘッダーを有効化（OpenAI用）
add_filter('http_request_args', function ($args, $url) {
    if (strpos($url, 'api.openai.com') !== false && !empty($args['headers']['Authorization'])) {
        $args['blocking'] = true;
    }
    return $args;
}, 10, 2);

// ✅ ChatGPT APIを使って自然文リンクを生成する
function tcl_generate_contextual_link_text($post_content, $cluster_title, $cluster_url) {
    $api_key = get_option('tcl_api_key');
    tcl_log_message('API呼び出し開始 - タイトル: ' . $cluster_title);

    if (!$api_key) {
        tcl_log_message('APIキーが設定されていません');
        return 'APIキーが設定されていません';
    }

    // 投稿内容を短縮（API制限対応）
    $excerpt = wp_strip_all_tags($post_content);
    $excerpt = mb_substr($excerpt, 0, 1000);

    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    ];

    $body = [
        'model' => 'gpt-4o',
        'messages' => [
            [
                'role' => 'system', 
                'content' => 'あなたはSEOに詳しい日本語のWebライターです。トピッククラスター戦略における内部リンクの専門家です。'
            ],
            [
                'role' => 'user', 
                'content' => 
                    "以下の投稿内容に対して、自然で文脈に合った内部リンクを含む文章を1文で生成してください。\n\n" .
                    "【重要な条件】\n" .
                    "- 必ずHTMLの<a>タグを使ってリンクを作成\n" .
                    "- 投稿内容の文脈に自然に溶け込む表現\n" .
                    "- SEO効果を高める適切なアンカーテキスト\n" .
                    "- 読者にとって有益な情報への誘導\n" .
                    "- 1文で完結した自然な日本語\n\n" .
                    "【投稿内容（抜粋）】\n" . $excerpt . "\n\n" .
                    "【リンク先ページタイトル】\n" . $cluster_title . "\n\n" .
                    "【リンクURL】\n" . $cluster_url . "\n\n" .
                    "例：「より詳しい解決方法については、<a href='{$cluster_url}'>エアコンの水漏れ修理事例</a>をご参照ください。」\n\n" .
                    "※投稿内容の文脈を考慮し、読者が自然に次の記事を読みたくなるような魅力的なリンクテキストを作成してください。"
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 200,
    ];

    $response = wp_remote_post($endpoint, [
        'headers' => $headers,
        'body'    => json_encode($body),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        $error = $response->get_error_message();
        tcl_log_message('API通信エラー: ' . $error);
        return 'API通信エラー: ' . $error;
    }

    $body_raw = wp_remote_retrieve_body($response);
    tcl_log_message('ChatGPT Raw Response: ' . $body_raw);

    $data = json_decode($body_raw, true);

    if (!empty($data['error']['message'])) {
        tcl_log_message('OpenAIエラー: ' . $data['error']['message']);
        return 'OpenAIエラー: ' . esc_html($data['error']['message']);
    }

    if (!empty($data['choices'][0]['message']['content'])) {
        $generated_text = trim($data['choices'][0]['message']['content']);
        tcl_log_message('生成されたリンクテキスト: ' . $generated_text);
        return $generated_text;
    }

    tcl_log_message('AI応答が不正: ' . $body_raw);
    return 'AI応答が不正です: ' . esc_html($body_raw);
}

// ✅ AJAX処理：リンクテキスト再生成
add_action('wp_ajax_tcl_regenerate_link', 'tcl_ajax_regenerate_link');

function tcl_ajax_regenerate_link() {
    check_ajax_referer('tcl_ajax_nonce', 'nonce');
    
    $post_id = intval($_POST['post_id']);
    $cluster_id = intval($_POST['cluster_id']);
    
    $post = get_post($post_id);
    $cluster = get_post($cluster_id);
    
    if (!$post || !$cluster) {
        tcl_log_message('投稿またはクラスターページが見つかりません');
        wp_die('投稿が見つかりません');
    }
    
    $new_text = tcl_generate_contextual_link_text(
        $post->post_content,
        $cluster->post_title,
        get_permalink($cluster)
    );
    
    tcl_log_message('AJAX再生成完了: ' . $new_text);
    wp_send_json_success(['text' => $new_text]);
}

// ✅ テスト用ショートコード
// 例：[tcl_test_link_text post_id="123" cluster_id="456"]
add_shortcode('tcl_test_link_text', function($atts) {
    $atts = shortcode_atts([
        'post_id' => 0,
        'cluster_id' => 0,
    ], $atts);

    $post = get_post($atts['post_id']);
    $cluster = get_post($atts['cluster_id']);

    if (!$post || !$cluster) {
        return '<div style="border:1px solid #ff0000; padding:10px; background:#ffe6e6;">投稿またはクラスターページが見つかりません。</div>';
    }

    $text = tcl_generate_contextual_link_text(
        $post->post_content, 
        $cluster->post_title, 
        get_permalink($cluster)
    );

    return '<div style="border:1px solid #ccc; padding:10px; background:#f9f9f9;">' . 
           '<h4>生成されたリンクテキスト:</h4>' . 
           '<div>' . $text . '</div>' . 
           '</div>';
});

// ✅ デバッグ用：提案結果をリセットする関数
function tcl_reset_proposals() {
    delete_option('tcl_cluster_proposals');
    tcl_log_message('提案結果をリセットしました');
}

// ✅ デバッグ用：現在の提案状況を確認
function tcl_debug_proposals() {
    $proposals = get_option('tcl_cluster_proposals', []);
    tcl_log_message('現在の提案状況: ' . print_r($proposals, true));
    return $proposals;
}