<?php
/**
 * キーワード提案機能
 * Google Ads API + ChatGPT APIを使用してクラスターキーワードを提案
 */

// セキュリティ：直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Ads APIクライアントのセットアップチェック
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
 * メインキーワード提案機能（Google APIとChatGPT APIの両方に対応）
 */
function tcl_get_keyword_suggestions($pillar_keyword, $existing_keywords = [], $use_google_api = true) {
    try {
        $results = [];
        
        // Google Ads APIを試行
        if ($use_google_api) {
            $google_setup = tcl_check_google_ads_api_setup();
            if ($google_setup['ready']) {
                tcl_log_message("Google Ads APIでキーワード提案を実行: {$pillar_keyword}");
                $google_result = tcl_get_keywords_from_google_api($pillar_keyword, $existing_keywords);
                
                if ($google_result['success']) {
                    $results['google'] = $google_result;
                } else {
                    tcl_log_message("Google API失敗、ChatGPTにフォールバック: " . $google_result['error']);
                    $results['google_error'] = $google_result['error'];
                }
            } else {
                tcl_log_message("Google API設定不完全、ChatGPTを使用");
                $results['google_error'] = 'Google Ads API設定が不完全です';
            }
        }
        
        // ChatGPT APIでキーワード提案（メイン結果またはフォールバック）
        if (!isset($results['google']) || !$results['google']['success']) {
            tcl_log_message("ChatGPT APIでキーワード提案を実行: {$pillar_keyword}");
            $chatgpt_result = tcl_get_keywords_from_chatgpt_api($pillar_keyword, $existing_keywords);
            $results['chatgpt'] = $chatgpt_result;
        }
        
        // 結果をマージして統一形式で返す
        return tcl_merge_keyword_results($results, $pillar_keyword);
        
    } catch (Exception $e) {
        tcl_log_message("キーワード提案エラー: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'キーワード提案中にエラーが発生しました: ' . $e->getMessage(),
            'suggestions' => []
        ];
    }
}

/**
 * Google Ads APIからキーワード提案を取得
 */
function tcl_get_keywords_from_google_api($pillar_keyword, $existing_keywords = []) {
    try {
        // Composer autoload（Google Ads PHP ライブラリが必要）
        $autoload_paths = [
            TCL_PLUGIN_DIR . 'vendor/autoload.php',
            ABSPATH . 'vendor/autoload.php',
            get_home_path() . 'vendor/autoload.php'
        ];
        
        $autoload_loaded = false;
        foreach ($autoload_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $autoload_loaded = true;
                break;
            }
        }
        
        if (!$autoload_loaded) {
            return [
                'success' => false,
                'error' => 'Google Ads PHPライブラリが見つかりません。Composerでインストールしてください。'
            ];
        }
        
        // Google Ads APIクライアントの設定
        $credentials = [
            'DEVELOPER_TOKEN' => get_option('tcl_google_developer_token'),
            'OAUTH2' => [
                'clientId' => get_option('tcl_google_client_id'),
                'clientSecret' => get_option('tcl_google_client_secret'),
                'refreshToken' => get_option('tcl_google_refresh_token')
            ]
        ];
        
        $customer_id = get_option('tcl_google_customer_id');
        
        // 設定ファイルを一時的に作成
        $config_file = tcl_create_temp_google_ads_config($credentials);
        
        // Google Ads APIクライアントを初期化
        $googleAdsClient = (new GoogleAdsClientBuilder())
            ->fromFile($config_file)
            ->build();
        
        // KeywordPlanIdeaServiceクライアントを取得
        $keywordPlanIdeaServiceClient = $googleAdsClient->getKeywordPlanIdeaServiceClient();
        
        // リクエストパラメータの設定
        $language_id = get_option('tcl_google_language_id', 1005); // 日本語のデフォルト
        $geo_target_ids = [get_option('tcl_google_geo_target_id', 2392)]; // 日本のデフォルト
        
        // リクエストの構築
        $request = new \Google\Ads\GoogleAds\V17\Services\GenerateKeywordIdeasRequest([
            'customer_id' => $customer_id,
            'language' => \Google\Ads\GoogleAds\Util\V17\ResourceNames::forLanguageConstant($language_id),
            'geo_target_constants' => array_map(function($geo_id) {
                return \Google\Ads\GoogleAds\Util\V17\ResourceNames::forGeoTargetConstant($geo_id);
            }, $geo_target_ids),
            'keyword_plan_network' => \Google\Ads\GoogleAds\V17\Enums\KeywordPlanNetworkEnum::GOOGLE_SEARCH,
            'keyword_seed' => new \Google\Ads\GoogleAds\V17\Services\KeywordSeed([
                'keywords' => [$pillar_keyword]
            ])
        ]);
        
        // APIリクエストを実行
        $response = $keywordPlanIdeaServiceClient->generateKeywordIdeas($request);
        
        // 結果を解析
        $suggestions = [];
        $count = 0;
        $max_suggestions = 20;
        
        foreach ($response->iterateAllElements() as $result) {
            if ($count >= $max_suggestions) break;
            
            $keyword = $result->getText();
            
            // 既存キーワードをスキップ
            if (in_array($keyword, $existing_keywords)) {
                continue;
            }
            
            $metrics = $result->getKeywordIdeaMetrics();
            $monthly_searches = $metrics ? $metrics->getAvgMonthlySearches() : 0;
            $competition = $metrics ? $metrics->getCompetition() : 0;
            $high_top_of_page_bid = $metrics && $metrics->getHighTopOfPageBidMicros() ? 
                $metrics->getHighTopOfPageBidMicros() / 1000000 : 0;
            
            $suggestions[] = [
                'keyword' => $keyword,
                'description' => tcl_generate_keyword_description($keyword, $pillar_keyword),
                'monthly_searches' => $monthly_searches,
                'competition' => tcl_convert_competition_level($competition),
                'cpc' => $high_top_of_page_bid,
                'relevance_score' => tcl_calculate_relevance_score($keyword, $pillar_keyword),
                'search_intent' => tcl_determine_search_intent($keyword),
                'source' => 'google_ads_api'
            ];
            
            $count++;
        }
        
        // 一時設定ファイルを削除
        if (file_exists($config_file)) {
            unlink($config_file);
        }
        
        tcl_log_message("Google Ads API成功: {$count}件のキーワードを取得");
        
        return [
            'success' => true,
            'suggestions' => $suggestions,
            'total_count' => $count,
            'source' => 'google_ads_api'
        ];
        
    } catch (Exception $e) {
        // 一時設定ファイルがあれば削除
        if (isset($config_file) && file_exists($config_file)) {
            unlink($config_file);
        }
        
        tcl_log_message("Google Ads APIエラー: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'Google Ads API エラー: ' . $e->getMessage(),
            'suggestions' => []
        ];
    }
}

/**
 * ChatGPT APIからキーワード提案を取得（フォールバック用）
 */
function tcl_get_keywords_from_chatgpt_api($pillar_keyword, $existing_keywords = []) {
    $api_key = get_option('tcl_api_key');
    
    if (empty($api_key)) {
        return [
            'success' => false,
            'error' => 'ChatGPT APIキーが設定されていません',
            'suggestions' => []
        ];
    }
    
    try {
        // 除外キーワードリスト作成
        $exclude_text = '';
        if (!empty($existing_keywords)) {
            $exclude_text = "\n\n除外するキーワード（これらは提案しないでください）:\n" . implode(', ', $existing_keywords);
        }
        
        // より具体的なプロンプト
        $prompt = "あなたは日本のSEO専門家です。以下のピラーキーワードに関連する検索ボリュームがあるロングテールキーワードを提案してください。

【ピラーキーワード】
{$pillar_keyword}

【提案条件】
1. 実際に日本で検索されている具体的なキーワード
2. 月間検索ボリューム100回以上が期待できるキーワード
3. 商業的価値がある、またはコンテンツ化しやすいキーワード
4. 2-5語の組み合わせ
5. 検索意図が明確

【出力形式】必ず以下の形式で10個提案：
1. キーワード|説明|予想月間検索数|競合度(低/中/高)|検索意図
2. キーワード|説明|予想月間検索数|競合度(低/中/高)|検索意図

例：
1. WordPress 初心者 使い方|初心者向けの基本操作方法|1200|中|情報収集
2. WordPress テーマ おすすめ 無料|無料で使える人気テーマの比較|800|高|商業

{$exclude_text}

提案してください：";

        // ChatGPT API リクエスト
        $response = tcl_send_chatgpt_request($prompt, $api_key);
        
        if ($response['success']) {
            $suggestions = tcl_parse_chatgpt_keyword_response($response['content']);
            
            tcl_log_message("ChatGPT API成功: " . count($suggestions) . "件のキーワードを生成");
            
            return [
                'success' => true,
                'suggestions' => $suggestions,
                'total_count' => count($suggestions),
                'source' => 'chatgpt_api'
            ];
        } else {
            return [
                'success' => false,
                'error' => $response['error'],
                'suggestions' => []
            ];
        }
        
    } catch (Exception $e) {
        tcl_log_message("ChatGPT APIエラー: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'ChatGPT API エラー: ' . $e->getMessage(),
            'suggestions' => []
        ];
    }
}

/**
 * Google Ads API用の一時設定ファイルを作成
 */
function tcl_create_temp_google_ads_config($credentials) {
    $upload_dir = wp_upload_dir();
    $config_file = $upload_dir['basedir'] . '/tcl_google_ads_config_' . uniqid() . '.ini';
    
    $config_content = "[GOOGLE_ADS]\n";
    $config_content .= "developerToken = \"{$credentials['DEVELOPER_TOKEN']}\"\n\n";
    $config_content .= "[OAUTH2]\n";
    $config_content .= "clientId = \"{$credentials['OAUTH2']['clientId']}\"\n";
    $config_content .= "clientSecret = \"{$credentials['OAUTH2']['clientSecret']}\"\n";
    $config_content .= "refreshToken = \"{$credentials['OAUTH2']['refreshToken']}\"\n";
    
    file_put_contents($config_file, $config_content);
    
    return $config_file;
}

/**
 * ChatGPTの構造化レスポンスを解析
 */
function tcl_parse_chatgpt_keyword_response($content) {
    $suggestions = [];
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // 番号付きリストパターン
        if (preg_match('/^\d+\.\s*(.+)$/', $line, $matches)) {
            $data = explode('|', $matches[1]);
            
            if (count($data) >= 4) {
                $keyword = trim($data[0]);
                $description = trim($data[1]);
                $monthly_searches = intval(preg_replace('/[^\d]/', '', $data[2]));
                $competition = tcl_normalize_competition_level(trim($data[3]));
                $search_intent = isset($data[4]) ? tcl_normalize_search_intent(trim($data[4])) : 'informational';
                
                if (!empty($keyword) && !empty($description)) {
                    $suggestions[] = [
                        'keyword' => $keyword,
                        'description' => $description,
                        'monthly_searches' => $monthly_searches,
                        'competition' => $competition,
                        'cpc' => 0, // ChatGPTでは取得不可
                        'relevance_score' => 85,
                        'search_intent' => $search_intent,
                        'source' => 'chatgpt_api'
                    ];
                }
            }
        }
    }
    
    return $suggestions;
}

/**
 * 結果をマージして統一形式で返す
 */
function tcl_merge_keyword_results($results, $pillar_keyword) {
    $final_suggestions = [];
    $sources_used = [];
    $errors = [];
    
    // Google APIの結果を優先
    if (isset($results['google']) && $results['google']['success']) {
        $final_suggestions = array_merge($final_suggestions, $results['google']['suggestions']);
        $sources_used[] = 'google_ads_api';
    } elseif (isset($results['google_error'])) {
        $errors[] = $results['google_error'];
    }
    
    // ChatGPTの結果を追加（Google APIがない場合、または補完として）
    if (isset($results['chatgpt']) && $results['chatgpt']['success']) {
        $chatgpt_suggestions = $results['chatgpt']['suggestions'];
        
        // Google APIの結果がある場合は、重複を除いて追加
        if (!empty($final_suggestions)) {
            $existing_keywords = array_column($final_suggestions, 'keyword');
            $chatgpt_suggestions = array_filter($chatgpt_suggestions, function($suggestion) use ($existing_keywords) {
                return !in_array($suggestion['keyword'], $existing_keywords);
            });
        }
        
        $final_suggestions = array_merge($final_suggestions, $chatgpt_suggestions);
        $sources_used[] = 'chatgpt_api';
    } elseif (isset($results['chatgpt'])) {
        $errors[] = $results['chatgpt']['error'];
    }
    
    // 関連度でソート
    usort($final_suggestions, function($a, $b) {
        return $b['relevance_score'] <=> $a['relevance_score'];
    });
    
    // 最大20件に制限
    $final_suggestions = array_slice($final_suggestions, 0, 20);
    
    $success = !empty($final_suggestions);
    $primary_error = !empty($errors) ? implode('; ', $errors) : null;
    
    tcl_log_message("キーワード提案完了: {$pillar_keyword} - " . count($final_suggestions) . "件、ソース: " . implode(', ', $sources_used));
    
    return [
        'success' => $success,
        'suggestions' => $final_suggestions,
        'total_count' => count($final_suggestions),
        'sources_used' => $sources_used,
        'error' => $primary_error,
        'pillar_keyword' => $pillar_keyword
    ];
}

/**
 * 競合レベルを正規化
 */
function tcl_convert_competition_level($google_competition_value) {
    switch ($google_competition_value) {
        case 1:
            return 'low';
        case 2:
            return 'medium';
        case 3:
            return 'high';
        default:
            return 'medium';
    }
}

function tcl_normalize_competition_level($competition_text) {
    $competition_lower = strtolower($competition_text);
    
    if (strpos($competition_lower, '低') !== false || strpos($competition_lower, 'low') !== false) {
        return 'low';
    } elseif (strpos($competition_lower, '高') !== false || strpos($competition_lower, 'high') !== false) {
        return 'high';
    } else {
        return 'medium';
    }
}

/**
 * 検索意図を正規化
 */
function tcl_normalize_search_intent($intent_text) {
    $intent_lower = strtolower($intent_text);
    
    if (strpos($intent_lower, '情報') !== false || strpos($intent_lower, 'informational') !== false) {
        return 'informational';
    } elseif (strpos($intent_lower, '商業') !== false || strpos($intent_lower, 'commercial') !== false) {
        return 'commercial';
    } elseif (strpos($intent_lower, '取引') !== false || strpos($intent_lower, 'transactional') !== false) {
        return 'transactional';
    } else {
        return 'informational';
    }
}

/**
 * キーワードの説明を生成
 */
function tcl_generate_keyword_description($keyword, $pillar_keyword) {
    return "「{$pillar_keyword}」に関連するキーワード: {$keyword}";
}

/**
 * 関連度スコアを計算
 */
function tcl_calculate_relevance_score($keyword, $pillar_keyword) {
    $keyword_lower = strtolower($keyword);
    $pillar_lower = strtolower($pillar_keyword);
    
    // ピラーキーワードが含まれているかチェック
    if (strpos($keyword_lower, $pillar_lower) !== false) {
        return 90;
    }
    
    // 単語の一致度をチェック
    $keyword_words = explode(' ', $keyword_lower);
    $pillar_words = explode(' ', $pillar_lower);
    
    $matches = array_intersect($keyword_words, $pillar_words);
    $match_ratio = count($matches) / max(count($pillar_words), 1);
    
    return max(70, intval(70 + ($match_ratio * 20)));
}

/**
 * 検索意図を判定
 */
function tcl_determine_search_intent($keyword) {
    $keyword_lower = strtolower($keyword);
    
    // 情報収集系
    $informational_patterns = ['方法', '使い方', 'とは', 'について', '仕組み', '原因', 'いつ', '理由', 'how', 'what', 'why'];
    foreach ($informational_patterns as $pattern) {
        if (strpos($keyword_lower, $pattern) !== false) {
            return 'informational';
        }
    }
    
    // 商業系
    $commercial_patterns = ['おすすめ', 'ランキング', '比較', '選び方', '価格', '料金', '口コミ', 'レビュー', 'best', 'review', 'compare'];
    foreach ($commercial_patterns as $pattern) {
        if (strpos($keyword_lower, $pattern) !== false) {
            return 'commercial';
        }
    }
    
    // トランザクション系
    $transactional_patterns = ['購入', '申し込み', '登録', 'ダウンロード', '無料', '体験', 'buy', 'purchase', 'download'];
    foreach ($transactional_patterns as $pattern) {
        if (strpos($keyword_lower, $pattern) !== false) {
            return 'transactional';
        }
    }
    
    return 'informational'; // デフォルト
}

// 以下は既存の関数（前回の実装から継続）
function tcl_send_chatgpt_request($prompt, $api_key) {
    // 前回の実装と同じ
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    $data = [
        'model' => 'gpt-4',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは日本のSEOとキーワードリサーチの専門家です。正確な検索ボリュームと競合度を考慮したキーワードを提案することが得意です。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 2000,
        'temperature' => 0.7
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        return [
            'success' => false,
            'error' => 'API通信エラー: ' . $curl_error
        ];
    }
    
    if ($http_status !== 200) {
        return [
            'success' => false,
            'error' => 'APIエラー (HTTP ' . $http_status . '): ' . $response
        ];
    }
    
    $decoded_response = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'JSON解析エラー: ' . json_last_error_msg()
        ];
    }
    
    if (isset($decoded_response['error'])) {
        return [
            'success' => false,
            'error' => 'ChatGPTエラー: ' . $decoded_response['error']['message']
        ];
    }
    
    if (!isset($decoded_response['choices'][0]['message']['content'])) {
        return [
            'success' => false,
            'error' => '予期しないAPIレスポンス形式'
        ];
    }
    
    return [
        'success' => true,
        'content' => $decoded_response['choices'][0]['message']['content']
    ];
}

/**
 * AJAX: キーワード提案の実行
 */
function tcl_ajax_suggest_keywords() {
    // セキュリティチェック
    if (!wp_verify_nonce($_POST['nonce'], 'tcl_ajax_nonce')) {
        wp_die('セキュリティチェックに失敗しました');
    }
    
    if (!current_user_can('edit_posts')) {
        wp_die('権限が不足しています');
    }
    
    $pillar_keyword = sanitize_text_field($_POST['pillar_keyword'] ?? '');
    $use_google_api = isset($_POST['use_google_api']) ? (bool) $_POST['use_google_api'] : true;
    
    if (empty($pillar_keyword)) {
        wp_send_json_error('ピラーキーワードが指定されていません');
    }
    
    // 既存のキーワードを取得（重複防止）
    $existing_proposals = get_option('tcl_cluster_proposals', []);
    $existing_keywords = [];
    
    foreach ($existing_proposals as $proposal) {
        if (isset($proposal['keywords'])) {
            foreach ($proposal['keywords'] as $keyword_data) {
                $existing_keywords[] = $keyword_data['keyword'];
            }
        }
    }
    
    // キーワード提案を実行
    $result = tcl_get_keyword_suggestions($pillar_keyword, $existing_keywords, $use_google_api);
    
    if ($result['success']) {
        wp_send_json_success([
            'suggestions' => $result['suggestions'],
            'count' => $result['total_count'],
            'sources_used' => $result['sources_used'],
            'pillar_keyword' => $pillar_keyword
        ]);
    } else {
        wp_send_json_error($result['error']);
    }
}

// AJAX フック
add_action('wp_ajax_tcl_suggest_keywords', 'tcl_ajax_suggest_keywords');

/**
 * Google Ads API設定のテスト機能
 */
function tcl_test_google_ads_api_connection() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    $setup_check = tcl_check_google_ads_api_setup();
    
    if (!$setup_check['ready']) {
        return [
            'success' => false,
            'error' => 'Google Ads API設定が不完全です: ' . implode(', ', $setup_check['missing'])
        ];
    }
    
    try {
        // 簡単なテストキーワードで接続テスト
        $result = tcl_get_keywords_from_google_api('テスト');
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Google Ads API接続成功',
                'keyword_count' => count($result['suggestions'])
            ];
        } else {
            return [
                'success' => false,
                'error' => $result['error']
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'テスト中にエラー: ' . $e->getMessage()
        ];
    }
}
