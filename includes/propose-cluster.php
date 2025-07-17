<?php

// ✅ クラスターページの提案処理
function tcl_run_propose_clusters() {
    tcl_log_message('tcl_run_propose_clusters 実行開始');
    $api_key = get_option('tcl_api_key');
    if (!$api_key) {
        tcl_log_message('APIキーが未設定です');
        return;
    }

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
        return;
    }

    $proposals = [];

    foreach ($pillar_posts as $pillar) {
        $raw_keywords = get_field('pillar_keywords', $pillar->ID);
        if (!$raw_keywords) continue;

        // カンマで分割してトリム
        $keywords = array_map('trim', explode('、', str_replace(['，', ','], '、', $raw_keywords)));
        if (empty($keywords)) continue;

        $cluster_args = array(
            'post_type' => ['post', 'local_trouble'],
            'posts_per_page' => -1,
            'post__not_in' => [$pillar->ID],
        );
        $cluster_posts = get_posts($cluster_args);

        foreach ($cluster_posts as $cluster) {
            $found = false;
            $content = $cluster->post_content;
            foreach ($keywords as $kw) {
                if (stripos($content, $kw) !== false) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $proposals[$pillar->ID][] = [
                    'cluster_id' => $cluster->ID,
                    'matched_keywords' => $keywords
                ];
            }
        }
    }

    update_option('tcl_cluster_proposals', $proposals);
    tcl_log_message("提案結果: " . print_r($proposals, true));
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
    tcl_log_message('取得されたAPIキー: ' . ($api_key ? substr($api_key, 0, 10) . '...' : '取得できませんでした'));

    if (!$api_key) return 'APIキーが設定されていません';

    $excerpt = wp_strip_all_tags($post_content);
    $excerpt = mb_substr($excerpt, 0, 800);

    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    ];

    $body = [
    'model' => 'gpt-4o',
    'messages' => [
        ['role' => 'system', 'content' => 'あなたはSEOに詳しい日本語のWebライターです。'],
        ['role' => 'user', 'content' =>
            "以下の投稿本文に関連した自然な文を1文生成してください。\n" .
            "必ずHTMLのaタグでリンクURLを文中に含めてください。\n\n" .
            "【投稿本文（抜粋）】\n" . $excerpt . "\n\n" .
            "【クラスターページタイトル】\n" . $cluster_title . "\n\n" .
            "【リンクURL】\n" . $cluster_url . "\n\n" .
            "例：「詳しくは<a href='{$cluster_url}'>〇〇の修理事例</a>をご覧ください。」\n" .
            "※ 必ず a タグを含めてください。SEO内部リンクとして効果的に機能する文にしてください。"
        ]
    ],
    'temperature' => 0.7,
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
        return 'OpenAIエラー: ' . esc_html($data['error']['message']);
    }

    if (!empty($data['choices'][0]['message']['content'])) {
        return trim($data['choices'][0]['message']['content']);
    }

    return 'AI応答が不正です: ' . esc_html($body_raw);
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

    if (!$post || !$cluster) return '投稿またはクラスターページが見つかりません。';

    $text = tcl_generate_contextual_link_text($post->post_content, $cluster->post_title, get_permalink($cluster));

    return '<div style="border:1px solid #ccc; padding:10px;">' . $text . '</div>';
});

