<?php
/**
 * Topic Cluster Linker - クラスター提案機能
 * AIを活用したピラーページとクラスターページの関係構築
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// 文字エンコーディングの設定
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}
if (function_exists('mb_regex_encoding')) {
    mb_regex_encoding('UTF-8');
}

/**
 * クラスター提案管理クラス
 */
class TCL_ClusterProposer {
    
    private static $instance = null;
    private $api_key;
    private $max_tokens;
    private $temperature;
    private $model;
    
    /**
     * シングルトンパターン
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('tcl_api_key', '');
        $this->max_tokens = apply_filters('tcl_max_tokens', 200);
        $this->temperature = apply_filters('tcl_temperature', 0.7);
        $this->model = apply_filters('tcl_openai_model', 'gpt-4o-mini');
        
        $this->init_hooks();
    }
    
    /**
     * フックの初期化
     */
    private function init_hooks() {
        // AJAX処理
        add_action('wp_ajax_tcl_regenerate_link', [$this, 'ajax_regenerate_link']);
        add_action('wp_ajax_tcl_generate_keyword_map', [$this, 'ajax_generate_keyword_map']);
        add_action('wp_ajax_tcl_suggest_cluster_ideas', [$this, 'ajax_suggest_cluster_ideas']);
        add_action('wp_ajax_tcl_generate_keyword_map_advanced', [$this, 'ajax_generate_keyword_map_advanced']);
        
        // APIリクエスト用フィルター
        add_filter('http_request_args', [$this, 'modify_http_request_args'], 10, 2);
        
        // ショートコード登録
        add_shortcode('tcl_test_link', [$this, 'shortcode_test_link']);
        add_shortcode('tcl_cluster_stats', [$this, 'shortcode_cluster_stats']);
    }
    
    /**
     * メインのクラスター提案処理
     */
    public function run_propose_clusters() {
        if (!function_exists('tcl_log_message')) {
            return false;
        }
        
        tcl_log_message('=== クラスター提案処理開始 ===');
        
        try {
            // 前処理
            $validation_result = $this->validate_requirements();
            if (!$validation_result['success']) {
                $this->display_error_message($validation_result['message']);
                return false;
            }
            
            // ピラーページを取得
            $pillar_posts = $this->get_pillar_posts();
            if (empty($pillar_posts)) {
                $this->display_warning_message('ピラーページが見つかりません');
                return false;
            }
            
            // 提案処理を実行
            $proposals = $this->generate_proposals($pillar_posts);
            
            // 結果を保存
            $this->save_proposals($proposals);
            
            // 結果を表示
            $this->display_success_message($proposals);
            
            tcl_log_message('=== クラスター提案処理完了 ===');
            return true;
            
        } catch (Exception $e) {
            if (function_exists('tcl_log_message')) {
                tcl_log_message('クラスター提案エラー: ' . $e->getMessage());
            }
            $this->display_error_message('予期しないエラーが発生しました: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 要件検証
     */
    private function validate_requirements() {
        $result = ['success' => true, 'message' => ''];
        
        // APIキーチェック
        if (empty($this->api_key)) {
            $result['success'] = false;
            $result['message'] = 'ChatGPT APIキーが設定されていません。設定画面でAPIキーを入力してください。';
            return $result;
        }
        
        // ACFプラグインチェック
        if (!function_exists('get_field')) {
            $result['success'] = false;
            $result['message'] = 'Advanced Custom Fields プラグインが必要です。インストールして有効化してください。';
            return $result;
        }
        
        return $result;
    }
    
    /**
     * ピラーページを取得
     */
    private function get_pillar_posts() {
        $args = [
            'post_type' => apply_filters('tcl_pillar_post_types', ['post', 'local_trouble']),
            'meta_query' => [
                [
                    'key' => 'pillar_keywords',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => 'pillar_keywords',
                    'value' => '',
                    'compare' => '!='
                ]
            ],
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        
        $posts = get_posts($args);
        if (function_exists('tcl_log_message')) {
            tcl_log_message('ピラーページ検索結果: ' . count($posts) . '件');
        }
        
        return $posts;
    }
    
    /**
     * 提案生成処理
     */
    private function generate_proposals($pillar_posts) {
        $proposals = [];
        $total_clusters = 0;
        
        foreach ($pillar_posts as $pillar) {
            if (function_exists('tcl_log_message')) {
                tcl_log_message("ピラーページ処理中: {$pillar->post_title} (ID: {$pillar->ID})");
            }
            
            $keywords = $this->extract_keywords($pillar->ID);
            if (empty($keywords)) {
                if (function_exists('tcl_log_message')) {
                    tcl_log_message("キーワードが空: {$pillar->ID}");
                }
                continue;
            }
            
            $clusters = $this->find_cluster_posts($pillar, $keywords);
            if (!empty($clusters)) {
                $proposals[$pillar->ID] = $clusters;
                $total_clusters += count($clusters);
                if (function_exists('tcl_log_message')) {
                    tcl_log_message("クラスター発見: {$pillar->ID} => " . count($clusters) . "件");
                }
            }
        }
        
        if (function_exists('tcl_log_message')) {
            tcl_log_message("提案生成完了 - ピラー: " . count($proposals) . "件, クラスター: {$total_clusters}件");
        }
        return $proposals;
    }
    
    /**
     * キーワード抽出
     */
    private function extract_keywords($pillar_id) {
        $raw_keywords = get_field('pillar_keywords', $pillar_id);
        if (!$raw_keywords) {
            return [];
        }
        
        // 複数の区切り文字に対応
        $keywords = preg_split('/[、，,\s]+/', $raw_keywords);
        $keywords = array_filter(array_map('trim', $keywords));
        
        // 重複除去と正規化
        $keywords = array_unique($keywords);
        
        if (function_exists('tcl_log_message')) {
            tcl_log_message("抽出キーワード (ID: {$pillar_id}): " . implode(', ', $keywords));
        }
        return $keywords;
    }
    
    /**
     * クラスターページ検索
     */
    private function find_cluster_posts($pillar, $keywords) {
        $cluster_args = [
            'post_type' => apply_filters('tcl_cluster_post_types', ['post', 'local_trouble']),
            'posts_per_page' => apply_filters('tcl_max_clusters_per_pillar', 50),
            'post__not_in' => [$pillar->ID],
            'post_status' => 'publish'
        ];
        
        $cluster_posts = get_posts($cluster_args);
        $matches = [];
        
        foreach ($cluster_posts as $cluster) {
            $match_result = $this->analyze_keyword_match($cluster, $keywords);
            if ($match_result['score'] > 0) {
                $matches[] = [
                    'cluster_id' => $cluster->ID,
                    'matched_keywords' => $match_result['keywords'],
                    'match_score' => $match_result['score'],
                    'match_details' => $match_result['details']
                ];
            }
        }
        
        // スコア順でソート
        usort($matches, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });
        
        return $matches;
    }
    
    /**
     * キーワードマッチング分析
     */
    private function analyze_keyword_match($post, $keywords) {
        $content = $post->post_content . ' ' . $post->post_title . ' ' . $post->post_excerpt;
        $content = wp_strip_all_tags($content);
        $content = strtolower($content);
        
        $matched_keywords = [];
        $total_score = 0;
        $details = [];
        
        foreach ($keywords as $keyword) {
            $keyword_lower = strtolower($keyword);
            $matches = substr_count($content, $keyword_lower);
            
            if ($matches > 0) {
                $matched_keywords[] = $keyword;
                
                // スコア計算（タイトル、本文、出現回数を考慮）
                $title_matches = substr_count(strtolower($post->post_title), $keyword_lower);
                $score = $matches + ($title_matches * 3); // タイトルマッチは3倍重要
                
                $total_score += $score;
                $details[$keyword] = [
                    'content_matches' => $matches,
                    'title_matches' => $title_matches,
                    'score' => $score
                ];
            }
        }
        
        return [
            'keywords' => $matched_keywords,
            'score' => $total_score,
            'details' => $details
        ];
    }
    
    /**
     * 提案結果を保存
     */
    private function save_proposals($proposals) {
        update_option('tcl_cluster_proposals', $proposals);
        update_option('tcl_last_proposal_time', current_time('timestamp'));
        
        // 統計情報も保存
        $stats = [
            'total_pillars' => count($proposals),
            'total_clusters' => array_sum(array_map('count', $proposals)),
            'generated_at' => current_time('mysql'),
            'version' => defined('TCL_VERSION') ? TCL_VERSION : '1.0.0'
        ];
        update_option('tcl_cluster_stats', $stats);
        
        if (function_exists('tcl_log_message')) {
            tcl_log_message('提案結果を保存しました');
        }
    }
    
    /**
     * GPTを使用したコンテキストリンクテキスト生成
     */
    public function generate_contextual_link_text($post_content, $cluster_title, $cluster_url) {
        if (function_exists('tcl_log_message')) {
            tcl_log_message("リンクテキスト生成開始: {$cluster_title}");
        }
        
        // 基本検証
        if (empty($this->api_key)) {
            return $this->create_fallback_link($cluster_title, $cluster_url, 'APIキー未設定');
        }
        
        if (empty($post_content) || empty($cluster_title)) {
            return $this->create_fallback_link($cluster_title, $cluster_url, '必要データ不足');
        }
        
        try {
            // コンテンツの前処理
            $processed_content = $this->preprocess_content($post_content);
            
            // GPT API呼び出し
            $generated_text = $this->call_openai_api($processed_content, $cluster_title, $cluster_url);
            
            if ($generated_text) {
                // 生成されたテキストの後処理
                $final_text = $this->postprocess_generated_text($generated_text, $cluster_url);
                if (function_exists('tcl_log_message')) {
                    tcl_log_message("リンクテキスト生成成功: {$final_text}");
                }
                return $final_text;
            }
            
        } catch (Exception $e) {
            if (function_exists('tcl_log_message')) {
                tcl_log_message("GPT API エラー: " . $e->getMessage());
            }
        }
        
        // フォールバック
        return $this->create_fallback_link($cluster_title, $cluster_url, 'AI生成失敗');
    }
    
    /**
     * コンテンツの前処理
     */
    private function preprocess_content($content) {
        // HTMLタグを除去
        $content = wp_strip_all_tags($content);
        
        // 改行を正規化
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 適切な長さに切り詰め（GPT APIの制限考慮）
        $content = mb_substr(trim($content), 0, 800);
        
        return $content;
    }
    
    /**
     * OpenAI API呼び出し
     */
    private function call_openai_api($content, $title, $url) {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        ];
        
        $messages = $this->build_gpt_messages($content, $title, $url);
        
        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
        ];
        
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
        ]);
        
        return $this->process_api_response($response);
    }
    
    /**
     * GPTメッセージ構築
     */
    private function build_gpt_messages($content, $title, $url) {
        $system_prompt = 'あなたはSEO専門家で日本語のWebライターです。トピッククラスター戦略における内部リンクの専門家として、読者にとって自然で価値のある内部リンクテキストを生成してください。';
        
        $user_prompt = sprintf(
            "以下の投稿内容に基づいて、自然で魅力的な内部リンクを含む文章を1文で生成してください。\n\n" .
            "【重要な条件】\n" .
            "- HTMLの<a>タグを必ず使用\n" .
            "- 投稿内容の文脈に自然に溶け込む表現\n" .
            "- SEO効果を高める適切なアンカーテキスト\n" .
            "- 読者の関心を引く魅力的な誘導文\n" .
            "- 1文で完結した自然な日本語\n\n" .
            "【投稿内容（抜粋）】\n%s\n\n" .
            "【リンク先ページタイトル】\n%s\n\n" .
            "【リンクURL】\n%s\n\n" .
            "例: より詳しい解決方法については、<a href=\"%s\">%sの具体的事例</a>をご参照ください。",
            $content, $title, $url, $url, $title
        );
        
        return [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ];
    }
    
    /**
     * API レスポンス処理
     */
    private function process_api_response($response) {
        if (is_wp_error($response)) {
            throw new Exception('API通信エラー: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSONデコードエラー: ' . json_last_error_msg());
        }
        
        if (!empty($data['error'])) {
            throw new Exception('OpenAI API エラー: ' . $data['error']['message']);
        }
        
        if (empty($data['choices'][0]['message']['content'])) {
            throw new Exception('空のレスポンス');
        }
        
        return trim($data['choices'][0]['message']['content']);
    }
    
    /**
     * 生成テキストの後処理
     */
    private function postprocess_generated_text($text, $url) {
        // 不要な引用符を除去
        $text = trim($text, '"\'');
        
        // HTMLの妥当性チェック
        if (!preg_match('/<a[^>]+>.*?<\/a>/', $text)) {
            throw new Exception('生成されたテキストに有効なリンクが含まれていません');
        }
        
        return $text;
    }
    
    /**
     * フォールバックリンク作成
     */
    private function create_fallback_link($title, $url, $reason = '') {
        if (function_exists('tcl_log_message')) {
            tcl_log_message("フォールバックリンク作成: {$reason}");
        }
        
        $fallback_templates = [
            "詳しくは<a href=\"{$url}\">{$title}</a>をご覧ください。",
            "関連情報は<a href=\"{$url}\">{$title}</a>でご確認いただけます。",
            "<a href=\"{$url}\">{$title}</a>も併せてお読みください。",
        ];
        
        $template = $fallback_templates[array_rand($fallback_templates)];
        return str_replace(['{$url}', '{$title}'], [$url, esc_html($title)], $template);
    }
    
    /**
     * AJAX: リンク再生成
     */
    public function ajax_regenerate_link() {
        // ナンス検証
        if (!check_ajax_referer('tcl_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('セキュリティチェックに失敗しました');
        }
        
        // 権限チェック
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        $cluster_id = intval($_POST['cluster_id'] ?? 0);
        
        if (!$post_id || !$cluster_id) {
            wp_send_json_error('必要なパラメータが不足しています');
        }
        
        $post = get_post($post_id);
        $cluster = get_post($cluster_id);
        
        if (!$post || !$cluster) {
            wp_send_json_error('投稿が見つかりません');
        }
        
        try {
            $new_text = $this->generate_contextual_link_text(
                $post->post_content,
                $cluster->post_title,
                get_permalink($cluster)
            );
            
            wp_send_json_success(['text' => $new_text]);
            
        } catch (Exception $e) {
            if (function_exists('tcl_log_message')) {
                tcl_log_message('AJAX再生成エラー: ' . $e->getMessage());
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: クラスターキーワードマップ生成
     */
    public function ajax_generate_keyword_map() {
        // 文字エンコーディングを設定
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        
        // ナンス検証
        if (!check_ajax_referer('tcl_metabox_nonce', 'nonce', false)) {
            wp_send_json_error('セキュリティチェックに失敗しました');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('投稿IDが不正です');
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('投稿が見つかりません');
        }
        
        // デバッグ用ログ
        if (function_exists('tcl_log_message')) {
            tcl_log_message("キーワードマップ生成開始: Post ID {$post_id}");
        }
        
        try {
            $keyword_map = $this->generate_keyword_map($post);
            
            // UTF-8エンコーディングを確実にする
            if (is_array($keyword_map)) {
                $keyword_map = $this->ensure_utf8_encoding($keyword_map);
            }
            
            wp_send_json_success($keyword_map);
        } catch (Exception $e) {
            if (function_exists('tcl_log_message')) {
                tcl_log_message('キーワードマップ生成エラー: ' . $e->getMessage());
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX: サジェストAPI連携キーワード取得
     */
    public function ajax_generate_keyword_map_advanced() {
        // 文字エンコーディングを設定
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        
        // ナンス検証
        if (!check_ajax_referer('tcl_metabox_nonce', 'nonce', false)) {
            wp_send_json_error('セキュリティチェックに失敗しました');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('投稿IDが不正です');
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('投稿が見つかりません');
        }
        
        try {
            $keyword_map = $this->generate_keyword_map_with_suggest_api($post);
            wp_send_json_success($keyword_map);
        } catch (Exception $e) {
            if (function_exists('tcl_log_message')) {
                tcl_log_message('高度なキーワードマップ生成エラー: ' . $e->getMessage());
            }
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * UTF-8エンコーディングを確実にする
     */
    private function ensure_utf8_encoding($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->ensure_utf8_encoding($value);
            }
        } elseif (is_string($data)) {
            // 既にUTF-8の場合はそのまま、そうでなければ変換
            if (!mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8', 'auto');
            }
        }
        return $data;
    }
    
    /**
     * キーワードマップ生成
     */
    private function generate_keyword_map($post) {
        if (empty($this->api_key)) {
            throw new Exception('ChatGPT APIキーが設定されていません');
        }
        
        // ピラーキーワード取得と検証
        $pillar_keywords = get_field('pillar_keywords', $post->ID);
        if (empty($pillar_keywords)) {
            throw new Exception('ピラーキーワードが設定されていません。ACFの「pillar_keywords」フィールドにキーワードを入力してください。');
        }
        
        // 投稿内容を準備
        $content = $this->preprocess_content($post->post_content);
        
        try {
            // GPT API呼び出し
            $response = $this->call_keyword_map_api($post->post_title, $content, $pillar_keywords);
            return $this->process_keyword_map_response($response);
        } catch (Exception $e) {
            if (function_exists('tcl_log_message')) {
                tcl_log_message("GPT API エラー: " . $e->getMessage());
            }
            // エラー時は直接フォールバック処理を呼び出し
            return $this->process_keyword_map_response('');
        }
    }
    
    /**
     * キーワードマップ用API呼び出し
     */
    private function call_keyword_map_api($title, $content, $pillar_keywords) {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        ];
        
        $system_prompt = 'あなたはSEO専門家でキーワードリサーチの専門家です。指定されたピラーページの内容とキーワードに基づいて、そのページを支援する具体的で関連性の高いクラスターキーワードを提案してください。';
        
        $user_prompt = sprintf(
            "以下のピラーページ情報から、関連するクラスターキーワードを提案してください。\n\n" .
            "【ピラーページ情報】\n" .
            "タイトル: %s\n" .
            "ピラーキーワード: %s\n" .
            "内容抜粋: %s\n\n" .
            "【出力形式】\n" .
            "JSON形式で以下の構造で出力してください：\n" .
            "{\n" .
            "  \"total_keywords\": 数値,\n" .
            "  \"keyword_categories\": {\n" .
            "    \"基礎知識・情報系\": [\n" .
            "      {\n" .
            "        \"text\": \"キーワード\",\n" .
            "        \"priority\": \"high|medium|low\",\n" .
            "        \"difficulty\": \"競合の強さ\",\n" .
            "        \"search_intent\": \"検索意図\"\n" .
            "      }\n" .
            "    ],\n" .
            "    \"方法・手順系\": [...],\n" .
            "    \"比較・選択系\": [...],\n" .
            "    \"問題・解決系\": [...],\n" .
            "    \"事例・体験系\": [...],\n" .
            "    \"ツール・リソース系\": [...]\n" .
            "  },\n" .
            "  \"strategy_notes\": \"キーワード戦略のアドバイス\"\n" .
            "}",
            $title,
            $pillar_keywords,
            $content
        );
        
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.2,
            'max_tokens' => 1500,
        ];
        
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60,
        ]);
        
        return $this->process_api_response($response);
   }
   
   /**
    * キーワードマップレスポンス処理
    */
   private function process_keyword_map_response($response) {
       // JSON部分を抽出
       $json_match = [];
       if (preg_match('/\{.*\}/s', $response, $json_match)) {
           $json_data = json_decode($json_match[0], true);
           
           if (json_last_error() === JSON_ERROR_NONE && isset($json_data['keyword_categories'])) {
               return $json_data;
           }
       }
       
       // フォールバック: 現在の投稿のピラーキーワードに基づいた関連キーワード提案
       $current_post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
       $pillar_keywords = get_field('pillar_keywords', $current_post_id);
       
       if (empty($pillar_keywords)) {
           $pillar_keywords = 'サービス';
       }

       // ピラーキーワードを分解
       $base_keywords = array_map('trim', preg_split('/[、，,\s]+/u', $pillar_keywords));
       $main_keyword = !empty($base_keywords[0]) ? $base_keywords[0] : 'サービス';
       
       // 文字化けしない安全なキーワードパターンを作成
       $safe_patterns = [
           'basic' => ' とは',
           'method' => ' 方法', 
           'howto' => ' やり方',
           'compare' => ' 比較',
           'choose' => ' 選び方',
           'problem' => ' 問題',
           'solution' => ' 解決',
           'case' => ' 事例',
           'experience' => ' 体験談',
           'tool' => ' ツール',
           'recommend' => ' おすすめ'
       ];
       
       $fallback_categories = [
           "基礎知識・情報系" => [],
           "方法・手順系" => [],
           "比較・選択系" => [],
           "問題・解決系" => [],
           "事例・体験系" => [],
           "ツール・リソース系" => []
       ];
       
       // 安全にキーワードを生成
       foreach ($base_keywords as $keyword) {
           $keyword = trim($keyword);
           if (empty($keyword)) continue;
           
           // 文字エンコーディングチェック
           if (!mb_check_encoding($keyword, 'UTF-8')) {
               $keyword = mb_convert_encoding($keyword, 'UTF-8', 'auto');
           }
           
           // 基礎知識系
           $fallback_categories["基礎知識・情報系"][] = [
               'text' => $keyword . $safe_patterns['basic'],
               'priority' => 'high',
               'difficulty' => 'low',
               'search_intent' => '基礎知識習得',
               'relevance_to_pillar' => 'ピラーページの基本概念説明'
           ];
           
           // 方法・手順系
           $fallback_categories["方法・手順系"][] = [
               'text' => $keyword . $safe_patterns['method'],
               'priority' => 'high',
               'difficulty' => 'medium',
               'search_intent' => '実行方法習得',
               'relevance_to_pillar' => 'ピラーページの実践手順'
           ];
           
           // 比較・選択系
           $fallback_categories["比較・選択系"][] = [
               'text' => $keyword . $safe_patterns['compare'],
               'priority' => 'medium',
               'difficulty' => 'medium',
               'search_intent' => '選択肢比較',
               'relevance_to_pillar' => '複数選択肢の検討'
           ];
           
           // 問題・解決系
           $fallback_categories["問題・解決系"][] = [
               'text' => $keyword . $safe_patterns['problem'],
               'priority' => 'medium',
               'difficulty' => 'medium',
               'search_intent' => '問題特定',
               'relevance_to_pillar' => 'よくある課題'
           ];
           
           // 事例・体験系
           $fallback_categories["事例・体験系"][] = [
               'text' => $keyword . $safe_patterns['case'],
               'priority' => 'low',
               'difficulty' => 'low',
               'search_intent' => '実例確認',
               'relevance_to_pillar' => '実際の適用事例'
           ];
           
           // ツール・リソース系
           $fallback_categories["ツール・リソース系"][] = [
               'text' => $keyword . $safe_patterns['recommend'],
               'priority' => 'medium',
               'difficulty' => 'low',
               'search_intent' => '推奨品確認',
               'relevance_to_pillar' => 'おすすめの選択肢'
           ];
       }
       
       // 空のカテゴリを除去
       $fallback_categories = array_filter($fallback_categories, function($keywords) {
           return !empty($keywords);
       });
       
       $total_count = 0;
       foreach ($fallback_categories as $category => $keywords) {
           $total_count += count($keywords);
       }
       
       $result = [
           'total_keywords' => $total_count,
           'pillar_theme' => $main_keyword . '関連',
           'keyword_categories' => $fallback_categories,
           'strategy_notes' => $pillar_keywords . 'をベースにした汎用的なキーワードパターンを表示しています。より精密な分析のため、ChatGPT APIキーの設定をお勧めします。'
       ];
       
       // 最終的なUTF-8エンコーディングチェック
       return $this->ensure_utf8_encoding($result);
   }
   
   /**
    * AJAX: クラスター記事アイデア提案
    */
   public function ajax_suggest_cluster_ideas() {
       // ナンス検証
       if (!check_ajax_referer('tcl_metabox_nonce', 'nonce', false)) {
           wp_send_json_error('セキュリティチェックに失敗しました');
       }
       
       if (!current_user_can('edit_posts')) {
           wp_send_json_error('権限がありません');
       }
       
       $post_id = intval($_POST['post_id'] ?? 0);
       $selected_keywords = $_POST['selected_keywords'] ?? [];
       
       if (!$post_id) {
           wp_send_json_error('投稿IDが不正です');
       }
       
       if (empty($selected_keywords)) {
           wp_send_json_error('キーワードが選択されていません');
       }
       
       $post = get_post($post_id);
       if (!$post) {
           wp_send_json_error('投稿が見つかりません');
       }
       
       try {
           $suggestions = $this->generate_cluster_ideas_from_keywords($post, $selected_keywords);
           wp_send_json_success($suggestions);
       } catch (Exception $e) {
           if (function_exists('tcl_log_message')) {
               tcl_log_message('クラスターアイデア提案エラー: ' . $e->getMessage());
           }
           wp_send_json_error($e->getMessage());
       }
   }
   
   /**
    * 選択されたキーワードから記事アイデア生成
    */
   private function generate_cluster_ideas_from_keywords($post, $selected_keywords) {
       if (empty($this->api_key)) {
           throw new Exception('ChatGPT APIキーが設定されていません');
       }
       
       // ピラーキーワード取得
       $pillar_keywords = get_field('pillar_keywords', $post->ID);
       
       // 投稿内容を準備
       $content = $this->preprocess_content($post->post_content);
       
       // GPT API呼び出し
       $response = $this->call_cluster_ideas_from_keywords_api($post->post_title, $content, $pillar_keywords, $selected_keywords);
       
       return $this->process_cluster_ideas_response($response);
   }
   
   /**
    * 選択キーワードベースの記事アイデア用API呼び出し
    */
   private function call_cluster_ideas_from_keywords_api($title, $content, $pillar_keywords, $selected_keywords) {
       $endpoint = 'https://api.openai.com/v1/chat/completions';
       
       $headers = [
           'Authorization' => 'Bearer ' . $this->api_key,
           'Content-Type' => 'application/json',
       ];
       
       $system_prompt = 'あなたはSEO専門家でコンテンツマーケティングの専門家です。選択されたキーワードから具体的で実用的な記事アイデアを提案してください。';
       
       $user_prompt = sprintf(
           "以下の情報から、選択されたキーワードに基づく具体的なクラスター記事アイデアを提案してください。\n\n" .
           "【ピラーページ情報】\n" .
           "タイトル: %s\n" .
           "ピラーキーワード: %s\n" .
           "内容抜粋: %s\n\n" .
           "【選択されたキーワード】\n%s\n\n" .
           "【記事提案要件】\n" .
           "- 各キーワードに対して1つの記事アイデア\n" .
           "- SEOに効果的な記事タイトル\n" .
           "- 実際に記事化可能な具体的な構成\n" .
           "- ピラーページとの内部リンク戦略\n" .
           "- ユーザーの検索意図を満たす内容\n\n" .
           "【出力形式】\n" .
           "JSON形式で以下の構造で出力してください：\n" .
           "{\n" .
           "  \"cluster_ideas\": [\n" .
           "    {\n" .
           "      \"title\": \"SEO効果的な記事タイトル\",\n" .
           "      \"keywords\": [\"メインキーワード\", \"関連キーワード\"],\n" .
           "      \"outline\": [\"見出し1\", \"見出し2\", \"見出し3\", \"見出し4\"],\n" .
           "      \"connection_strategy\": \"ピラーページとの関連付け方法\"\n" .
           "    }\n" .
           "  ],\n" .
           "  \"strategy_notes\": \"記事作成時の全体的なアドバイス\"\n" .
           "}",
           $title,
           $pillar_keywords ?: '未設定',
           $content,
           implode("\n- ", $selected_keywords)
       );
       
       $body = [
           'model' => $this->model,
           'messages' => [
               ['role' => 'system', 'content' => $system_prompt],
               ['role' => 'user', 'content' => $user_prompt]
           ],
           'temperature' => 0.7,
           'max_tokens' => 1200,
       ];
       
       $response = wp_remote_post($endpoint, [
           'headers' => $headers,
           'body' => json_encode($body),
           'timeout' => 45,
       ]);
       
       return $this->process_api_response($response);
   }
   
   /**
    * クラスターアイデアレスポンス処理
    */
   private function process_cluster_ideas_response($response) {
       // JSON部分を抽出
       $json_match = [];
       if (preg_match('/\{.*\}/s', $response, $json_match)) {
           $json_data = json_decode($json_match[0], true);
           
           if (json_last_error() === JSON_ERROR_NONE && isset($json_data['cluster_ideas'])) {
               return $json_data;
           }
       }
       
       // フォールバック: 簡単な記事アイデア
       return [
           'cluster_ideas' => [
               [
                   'title' => 'AI生成に失敗しました - 手動で記事を企画してください',
                   'keywords' => ['手動でキーワードを設定'],
                   'outline' => ['記事構成を検討してください', '具体的な内容を計画', '関連情報を収集', 'まとめと次のステップ'],
                   'connection_strategy' => 'ピラーページとの関連性を考慮して内部リンクを設定'
               ]
           ],
           'strategy_notes' => 'AI生成に失敗しました。選択されたキーワードを参考に、手動で記事アイデアを検討してください。'
       ];
   }
   
   /**
    * サジェストAPIを使用したキーワードマップ生成
    */
   private function generate_keyword_map_with_suggest_api($post) {
       // ピラーキーワード取得
       $pillar_keywords = get_field('pillar_keywords', $post->ID);
       if (empty($pillar_keywords)) {
           throw new Exception('ピラーキーワードが設定されていません');
       }
       
       // キーワードを分解
       $base_keywords = array_map('trim', preg_split('/[、，,\s]+/u', $pillar_keywords));
       
       $all_suggestions = [];
       foreach ($base_keywords as $keyword) {
           if (!empty($keyword)) {
               $suggestions = $this->get_keyword_suggestions($keyword);
               $all_suggestions = array_merge($all_suggestions, $suggestions);
           }
       }
       
       // 重複除去
       $all_suggestions = array_unique($all_suggestions);
       
       // ChatGPTで分類（APIキーがある場合）
       if (!empty($this->api_key)) {
           try {
               return $this->categorize_keywords_with_gpt($pillar_keywords, $all_suggestions);
           } catch (Exception $e) {
               // GPT分類に失敗した場合は簡易分類
               return $this->simple_keyword_categorization($pillar_keywords, $all_suggestions);
           }
       }
       
       // 簡易分類
       return $this->simple_keyword_categorization($pillar_keywords, $all_suggestions);
   }
   
   /**
    * Googleサジェストから関連キーワードを取得
    */
   private function get_keyword_suggestions($keyword) {
       $suggestions = [];
       
       // Googleサジェスト取得を試行
       try {
           $google_suggestions = $this->fetch_google_suggestions($keyword);
           $suggestions = array_merge($suggestions, $google_suggestions);
       } catch (Exception $e) {
           // Google失敗時のログ
           if (function_exists('tcl_log_message')) {
               tcl_log_message('Googleサジェスト取得失敗: ' . $e->getMessage());
           }
       }
       
       // パターン生成によるフォールバック
       $pattern_suggestions = $this->generate_pattern_suggestions($keyword);
       $suggestions = array_merge($suggestions, $pattern_suggestions);
       
       return array_unique($suggestions);
   }
   
   /**
    * Googleサジェスト取得
    */
   private function fetch_google_suggestions($keyword) {
       $encoded_keyword = urlencode($keyword);
       $url = "http://suggestqueries.google.com/complete/search?client=chrome&q={$encoded_keyword}";
       
       $response = wp_remote_get($url, [
           'timeout' => 10,
           'headers' => [
               'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
           ]
       ]);
       
       if (is_wp_error($response)) {
           throw new Exception('Googleサジェスト取得エラー: ' . $response->get_error_message());
       }
       
       $body = wp_remote_retrieve_body($response);
       $data = json_decode($body, true);
       
       if (json_last_error() !== JSON_ERROR_NONE || !isset($data[1])) {
           throw new Exception('Googleサジェストレスポンス解析エラー');
       }
       
       return array_slice($data[1], 0, 10); // 上位10件
   }
   
   /**
 * SerpAPI 関連キーワード取得
 */
private function fetch_serpapi_suggestions($keyword, $api_key) {
    $encoded_keyword = urlencode($keyword);
    $url = "https://serpapi.com/search.json?engine=google&q={$encoded_keyword}&api_key={$api_key}&hl=ja&gl=jp";
    
    $response = wp_remote_get($url, ['timeout' => 15]);
    
    if (is_wp_error($response)) {
        tcl_log_message('SerpAPI エラー: ' . $response->get_error_message());
        return [];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    $suggestions = [];
    
    // 関連検索語を取得
    if (isset($data['related_searches'])) {
        foreach ($data['related_searches'] as $related) {
            if (isset($related['query'])) {
                $suggestions[] = $related['query'];
            }
        }
    }
    
    // 検索候補を取得
    if (isset($data['search_parameters']['q'])) {
        // People Also Ask セクション
        if (isset($data['people_also_ask'])) {
            foreach ($data['people_also_ask'] as $paa) {
                if (isset($paa['question'])) {
                    // 質問から検索クエリに変換
                    $question_keyword = $this->convert_question_to_keyword($paa['question'], $keyword);
                    if ($question_keyword) {
                        $suggestions[] = $question_keyword;
                    }
                }
            }
        }
    }
    
    return array_slice($suggestions, 0, 30); // 最大30件
}
   
   /**
    * パターン生成による関連キーワード作成
    */
   private function generate_pattern_suggestions($keyword) {
       $patterns = [
           $keyword . ' とは',
           $keyword . ' 方法',
           $keyword . ' やり方',
           $keyword . ' 使い方',
           $keyword . ' 比較',
           $keyword . ' 選び方',
           $keyword . ' おすすめ',
           $keyword . ' 効果',
           $keyword . ' メリット',
           $keyword . ' デメリット',
           $keyword . ' 問題',
           $keyword . ' 解決',
           $keyword . ' 事例',
           $keyword . ' 体験談',
           $keyword . ' ツール',
           $keyword . ' サービス'
       ];
       
       return $patterns;
   }
   
   /**
    * ChatGPTによるキーワード分類とクラスタリング
    */
   private function categorize_keywords_with_gpt($pillar_keywords, $suggestions) {
       $endpoint = 'https://api.openai.com/v1/chat/completions';
       
       $headers = [
           'Authorization' => 'Bearer ' . $this->api_key,
           'Content-Type' => 'application/json',
       ];
       
       $system_prompt = 'あなたはSEO専門家です。提供されたキーワードリストを適切なカテゴリに分類してください。';
       
       $keywords_text = implode("\n- ", $suggestions);
       $user_prompt = sprintf(
           "以下のキーワードリストを、検索意図とコンテンツタイプに基づいて分類してください。\n\n" .
           "【ピラーキーワード】\n%s\n\n" .
           "【キーワードリスト】\n- %s\n\n" .
           "【出力形式】\n" .
           "JSON形式で以下の構造で出力してください：\n" .
           "{\n" .
           "  \"total_keywords\": 数値,\n" .
           "  \"keyword_categories\": {\n" .
           "    \"基礎知識・情報系\": [...],\n" .
           "    \"方法・手順系\": [...],\n" .
           "    \"比較・選択系\": [...],\n" .
           "    \"問題・解決系\": [...],\n" .
           "    \"事例・体験系\": [...],\n" .
           "    \"ツール・リソース系\": [...]\n" .
           "  },\n" .
           "  \"strategy_notes\": \"キーワード戦略のアドバイス\"\n" .
           "}",
           $pillar_keywords,
           $keywords_text
       );
       
       $body = [
           'model' => $this->model,
           'messages' => [
               ['role' => 'system', 'content' => $system_prompt],
               ['role' => 'user', 'content' => $user_prompt]
           ],
           'temperature' => 0.2,
           'max_tokens' => 1000,
       ];
       
       $response = wp_remote_post($endpoint, [
           'headers' => $headers,
           'body' => json_encode($body),
           'timeout' => 30,
       ]);
       
       $gpt_response = $this->process_api_response($response);
       return $this->process_keyword_map_response($gpt_response);
   }
   
   /**
  * 簡易キーワード分類（APIエラー時のフォールバック）
 */
private function simple_keyword_categorization($pillar_keywords, $suggestions) {
    $categories = [
        '🔍 基礎知識・定義系' => [],
        '⚡ 実践・手順系' => [],
        '🆚 比較・選択系' => [],
        '🔧 問題・解決系' => [],
        '💰 費用・料金系' => [],
        '📊 事例・評価系' => []
    ];
    
    foreach ($suggestions as $keyword) {
        if (preg_match('/(とは|意味|定義|違い)/', $keyword)) {
            $categories['🔍 基礎知識・定義系'][] = [
                'text' => $keyword,
                'priority' => 'medium',
                'difficulty' => 'low',
                'search_intent' => '基礎知識習得',
                'cluster_potential' => '基本概念説明記事に適している'
            ];
        } elseif (preg_match('/(方法|やり方|手順|使い方)/', $keyword)) {
            $categories['⚡ 実践・手順系'][] = [
                'text' => $keyword,
                'priority' => 'high',
                'difficulty' => 'medium',
                'search_intent' => '実行方法習得',
                'cluster_potential' => 'ハウツー記事に適している'
            ];
        } elseif (preg_match('/(比較|選び方|おすすめ|ランキング)/', $keyword)) {
            $categories['🆚 比較・選択系'][] = [
                'text' => $keyword,
                'priority' => 'high',
                'difficulty' => 'medium',
                'search_intent' => '選択支援',
                'cluster_potential' => '比較・選択ガイド記事に適している'
            ];
        } elseif (preg_match('/(問題|トラブル|エラー|解決|対処|できない)/', $keyword)) {
            $categories['🔧 問題・解決系'][] = [
                'text' => $keyword,
                'priority' => 'high',
                'difficulty' => 'medium',
                'search_intent' => '問題解決',
                'cluster_potential' => 'トラブルシューティング記事に適している'
            ];
        } elseif (preg_match('/(料金|価格|費用|コスト|相場)/', $keyword)) {
            $categories['💰 費用・料金系'][] = [
                'text' => $keyword,
                'priority' => 'medium',
                'difficulty' => 'low',
                'search_intent' => '費用確認',
                'cluster_potential' => '料金・コスト解説記事に適している'
            ];
        } elseif (preg_match('/(評判|口コミ|レビュー|事例|体験)/', $keyword)) {
            $categories['📊 事例・評価系'][] = [
                'text' => $keyword,
                'priority' => 'low',
                'difficulty' => 'low',
                'search_intent' => '評価確認',
                'cluster_potential' => '事例・体験談記事に適している'
            ];
        }
    }
    
    // 空のカテゴリを除去
    $categories = array_filter($categories, function($keywords) {
        return !empty($keywords);
    });
    
    $total_count = 0;
    foreach ($categories as $category => $keywords) {
        $total_count += count($keywords);
    }
    
    return [
        'total_keywords' => $total_count,
        'pillar_theme' => $pillar_keywords,
        'keyword_categories' => $categories,
        'cluster_strategy' => '実際の検索データに基づくキーワードを取得しました。各カテゴリから優先度の高いキーワードを選択して記事化することをお勧めします。'
    ];
}

/**
 * 質問を検索キーワードに変換
 */
private function convert_question_to_keyword($question, $base_keyword) {
    // 簡単な変換ルール
    $question = str_replace(['？', '?'], '', $question);
    
    if (strpos($question, 'なぜ') !== false || strpos($question, 'どうして') !== false) {
        return $base_keyword . ' 理由';
    } elseif (strpos($question, 'どのように') !== false || strpos($question, 'どうやって') !== false) {
        return $base_keyword . ' 方法';
    } elseif (strpos($question, 'いつ') !== false) {
        return $base_keyword . ' タイミング';
    } elseif (strpos($question, 'どこ') !== false) {
        return $base_keyword . ' 場所';
    } elseif (strpos($question, 'いくら') !== false) {
        return $base_keyword . ' 料金';
    }
    
    return null;
}
   
   /**
    * HTTP リクエスト引数の修正
    */
   public function modify_http_request_args($args, $url) {
       if (strpos($url, 'api.openai.com') !== false) {
           $args['blocking'] = true;
           $args['timeout'] = 30;
       }
       return $args;
   }
   
   /**
    * ショートコード: テストリンク
    */
   public function shortcode_test_link($atts) {
       $atts = shortcode_atts([
           'post_id' => get_the_ID(),
           'cluster_id' => 0,
       ], $atts);

       if (!$atts['cluster_id']) {
           return '<div class="tcl-error">cluster_id パラメータが必要です</div>';
       }

       $post = get_post($atts['post_id']);
       $cluster = get_post($atts['cluster_id']);

       if (!$post || !$cluster) {
           return '<div class="tcl-error">投稿が見つかりません</div>';
       }

       $text = $this->generate_contextual_link_text(
           $post->post_content, 
           $cluster->post_title, 
           get_permalink($cluster)
       );

       return sprintf(
           '<div class="tcl-test-result">' .
           '<h4>生成されたリンクテキスト:</h4>' .
           '<div class="tcl-generated-link">%s</div>' .
           '</div>',
           $text
       );
   }
   
   /**
    * ショートコード: クラスター統計
    */
   public function shortcode_cluster_stats($atts) {
       $proposals = get_option('tcl_cluster_proposals', []);
       $stats = get_option('tcl_cluster_stats', []);
       
       if (empty($proposals)) {
           return '<div class="tcl-info">クラスター提案がまだありません</div>';
       }
       
       $total_pillars = count($proposals);
       $total_clusters = array_sum(array_map('count', $proposals));
       $last_update = $stats['generated_at'] ?? '不明';
       
       return sprintf(
           '<div class="tcl-stats-display">' .
           '<h4>📊 トピッククラスター統計</h4>' .
           '<ul>' .
           '<li>ピラーページ数: <strong>%d</strong></li>' .
           '<li>クラスターページ数: <strong>%d</strong></li>' .
           '<li>最終更新: <strong>%s</strong></li>' .
           '</ul>' .
           '</div>',
           $total_pillars,
           $total_clusters,
           $last_update
       );
   }
   
   /**
    * 成功メッセージ表示
    */
   private function display_success_message($proposals) {
       $total_pillars = count($proposals);
       $total_clusters = array_sum(array_map('count', $proposals));
       
       echo '<div class="notice notice-success is-dismissible">';
       echo '<p><strong>✅ クラスターページの再提案が完了しました</strong></p>';
       echo '<ul>';
       echo "<li>🎯 ピラーページ数: <strong>{$total_pillars}</strong>件</li>";
       echo "<li>🔗 関連クラスター数: <strong>{$total_clusters}</strong>件</li>";
       echo '<li>📊 下記の提案結果をご確認ください</li>';
       echo '</ul>';
       echo '</div>';
   }
   
   /**
    * 警告メッセージ表示
    */
   private function display_warning_message($message) {
       echo '<div class="notice notice-warning">';
       echo '<p><strong>⚠️ ' . esc_html($message) . '</strong></p>';
       echo '<p>ACFで「pillar_keywords」フィールドを設定した投稿を作成してください。</p>';
       echo '</div>';
   }
   
   /**
    * エラーメッセージ表示
    */
   private function display_error_message($message) {
       echo '<div class="notice notice-error">';
       echo '<p><strong>❌ ' . esc_html($message) . '</strong></p>';
       echo '</div>';
   }

} // クラスの終了

// シングルトンインスタンスを初期化
TCL_ClusterProposer::get_instance();

/**
* 後方互換性のための関数ラッパー
*/
function tcl_run_propose_clusters() {
   return TCL_ClusterProposer::get_instance()->run_propose_clusters();
}

function tcl_generate_contextual_link_text($post_content, $cluster_title, $cluster_url) {
   return TCL_ClusterProposer::get_instance()->generate_contextual_link_text($post_content, $cluster_title, $cluster_url);
}

function tcl_ajax_regenerate_link() {
   return TCL_ClusterProposer::get_instance()->ajax_regenerate_link();
}

/**
* デバッグ用関数
*/
function tcl_debug_proposals() {
   $proposals = get_option('tcl_cluster_proposals', []);
   $stats = get_option('tcl_cluster_stats', []);
   
   return [
       'proposals' => $proposals,
       'stats' => $stats,
       'pillar_count' => count($proposals),
       'cluster_count' => array_sum(array_map('count', $proposals))
   ];
}

/**
* 提案をリセットする関数
*/
function tcl_reset_proposals() {
   delete_option('tcl_cluster_proposals');
   delete_option('tcl_cluster_stats');
   delete_option('tcl_last_proposal_time');
   if (function_exists('tcl_log_message')) {
       tcl_log_message('提案データをリセットしました');
   }
}

/**
* キーワードマップのデバッグ情報を取得
*/
function tcl_debug_keyword_map($post_id) {
   $post = get_post($post_id);
   if (!$post) {
       return false;
   }
   
   $pillar_keywords = get_field('pillar_keywords', $post_id);
   
   
   return [
       'post_title' => $post->post_title,
       'pillar_keywords' => $pillar_keywords,
       'content_length' => strlen($post->post_content),
       'api_key_set' => !empty(get_option('tcl_api_key')),
   ];
}

/**
* キーワードマップをリセットする関数
*/
function tcl_reset_keyword_cache($post_id = null) {
   if ($post_id) {
       delete_transient("tcl_keyword_map_{$post_id}");
   } else {
       // 全てのキーワードマップキャッシュを削除
       global $wpdb;
       $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tcl_keyword_map_%'");
   }
   
   if (function_exists('tcl_log_message')) {
       tcl_log_message('キーワードマップキャッシュをリセットしました');
   }
}