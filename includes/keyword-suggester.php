<?php
/**
 * Topic Cluster Linker - キーワード提案機能
 * AIを活用したピラーページキーワードの自動提案
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCL_KeywordSuggester {
    
    private static $instance = null;
    private $api_key;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('tcl_api_key', '');
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX処理
        add_action('wp_ajax_tcl_suggest_keywords', [$this, 'ajax_suggest_keywords']);
        add_action('wp_ajax_tcl_analyze_for_pillar', [$this, 'ajax_analyze_for_pillar']);
        
        // 管理画面にメタボックス追加
        add_action('add_meta_boxes', [$this, 'add_keyword_suggestion_metabox']);
    }
    
    /**
     * キーワード提案メタボックス追加
     */
    public function add_keyword_suggestion_metabox() {
        add_meta_box(
            'tcl-keyword-suggester',
            '🎯 ピラーキーワード提案 - AI分析',
            [$this, 'keyword_suggestion_metabox_callback'],
            ['post', 'local_trouble'],
            'side',
            'high'
        );
    }
    
    /**
     * キーワード提案メタボックスのコンテンツ
     */
    public function keyword_suggestion_metabox_callback($post) {
        wp_nonce_field('tcl_keyword_suggestion_nonce', 'tcl_keyword_nonce_field');
        
        $current_keywords = get_field('pillar_keywords', $post->ID);
        $is_pillar = !empty($current_keywords);
        
        ?>
        <style>
            .tcl-keyword-container { margin-bottom: 15px; }
            .tcl-suggestion-box { 
                border: 1px solid #ddd; 
                padding: 12px; 
                margin: 10px 0; 
                background: #f9f9f9; 
                border-radius: 6px; 
            }
            .tcl-keyword-tag {
                display: inline-block;
                background: #0073aa;
                color: white;
                padding: 4px 8px;
                margin: 2px;
                border-radius: 3px;
                font-size: 12px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .tcl-keyword-tag:hover { background: #005a87; }
            .tcl-keyword-tag.selected { background: #28a745; }
            .tcl-current-keywords {
                background: #d1edff;
                border-left: 4px solid #0073aa;
                padding: 10px;
                margin-bottom: 15px;
            }
            .tcl-ai-analysis {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 10px;
                margin: 10px 0;
                border-radius: 4px;
            }
        </style>
        
        <div class="tcl-keyword-container">
            <?php if ($is_pillar): ?>
                <div class="tcl-current-keywords">
                    <strong>📍 現在のピラーキーワード:</strong><br>
                    <code><?php echo esc_html($current_keywords); ?></code>
                </div>
            <?php endif; ?>
            
            <button type="button" id="tcl-analyze-content" class="button button-primary" style="width: 100%; margin-bottom: 10px;">
                🤖 AI記事分析でキーワード提案
            </button>
            
            <div id="tcl-keyword-suggestions" style="display: none;">
                <h4>💡 提案キーワード</h4>
                <div id="tcl-suggested-keywords"></div>
                
                <h4>📝 選択したキーワード</h4>
                <div id="tcl-selected-keywords" style="min-height: 40px; border: 1px dashed #ccc; padding: 10px; margin: 10px 0;"></div>
                
                <button type="button" id="tcl-apply-keywords" class="button button-secondary" style="width: 100%;">
                    ✅ 選択キーワードをACFに設定
                </button>
            </div>
            
            <div id="tcl-analysis-result" class="tcl-ai-analysis" style="display: none;"></div>
            
            <div style="margin-top: 15px; font-size: 11px; color: #666;">
                💡 <strong>ヒント:</strong> AIが記事内容を分析し、関連するピラーキーワードを提案します。提案されたキーワードをクリックして選択し、ACFフィールドに自動設定できます。
            </div>
        </div>
        
        <script>
jQuery(document).ready(function($) {
    let selectedKeywords = [];
    
    // AI分析実行
    $('#tcl-analyze-content').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text('🤖 AI分析中...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'tcl_suggest_keywords',
                post_id: <?php echo $post->ID; ?>,
                nonce: $('#tcl_keyword_nonce_field').val()
            },
            success: function(response) {
                if (response.success) {
                    displaySuggestions(response.data);
                    $('#tcl-keyword-suggestions').show();
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
    
    // 提案結果を表示
    function displaySuggestions(data) {
        const $container = $('#tcl-suggested-keywords');
        $container.empty();
        
        if (data.keywords && data.keywords.length > 0) {
            data.keywords.forEach(function(keyword) {
                const $tag = $('<span class="tcl-keyword-tag">' + keyword + '</span>');
                $tag.on('click', function() {
                    toggleKeyword(keyword, $tag);
                });
                $container.append($tag);
            });
        }
        
        if (data.analysis) {
            $('#tcl-analysis-result').html(data.analysis).show();
        }
    }
    
    // キーワード選択/解除
    function toggleKeyword(keyword, $tag) {
        if (selectedKeywords.includes(keyword)) {
            selectedKeywords = selectedKeywords.filter(k => k !== keyword);
            $tag.removeClass('selected');
        } else {
            selectedKeywords.push(keyword);
            $tag.addClass('selected');
        }
        updateSelectedDisplay();
    }
    
    // 選択キーワード表示更新
    function updateSelectedDisplay() {
        const $container = $('#tcl-selected-keywords');
        $container.empty();
        
        selectedKeywords.forEach(function(keyword) {
            const $tag = $('<span class="tcl-keyword-tag selected">' + keyword + ' ×</span>');
            $tag.on('click', function() {
                selectedKeywords = selectedKeywords.filter(k => k !== keyword);
                updateSelectedDisplay();
                // 提案側のハイライトも解除
                $('#tcl-suggested-keywords .tcl-keyword-tag').each(function() {
                    if ($(this).text() === keyword) {
                        $(this).removeClass('selected');
                    }
                });
            });
            $container.append($tag);
        });
        
        if (selectedKeywords.length === 0) {
            $container.html('<span style="color: #999;">選択されたキーワードはありません</span>');
        }
    }
    
    // ACFに適用 - pillar_keywords フィールド専用版
    $('#tcl-apply-keywords').on('click', function() {
        if (selectedKeywords.length === 0) {
            alert('キーワードが選択されていません');
            return;
        }
        
        const keywordString = selectedKeywords.join('、');
        let fieldFound = false;
        
        console.log('=== ACFフィールド設定開始 ===');
        console.log('設定するキーワード:', keywordString);
        console.log('対象フィールド名: pillar_keywords');
        
        // 方法1: data-name属性で検索（最も確実）
        const $field1 = $('input[data-name="pillar_keywords"], textarea[data-name="pillar_keywords"]');
        if ($field1.length > 0) {
            console.log('data-name属性で発見:', $field1[0]);
            $field1.val(keywordString);
            $field1.trigger('change');
            $field1.trigger('input');
            fieldFound = true;
        }
        
        // 方法2: name属性に pillar_keywords が含まれるもの
        if (!fieldFound) {
            $('input[type="text"], textarea').each(function() {
                const name = $(this).attr('name') || '';
                if (name.includes('pillar_keywords')) {
                    console.log('name属性で発見:', name, $(this)[0]);
                    $(this).val(keywordString);
                    $(this).trigger('change');
                    $(this).trigger('input');
                    fieldFound = true;
                    return false;
                }
            });
        }
        
        // 方法3: ACFの一般的なパターン acf[pillar_keywords] or acf[field_xxx][pillar_keywords]
        if (!fieldFound) {
            const patterns = [
                'input[name="acf[pillar_keywords]"]',
                'textarea[name="acf[pillar_keywords]"]',
                'input[name*="[pillar_keywords]"]',
                'textarea[name*="[pillar_keywords]"]'
            ];
            
            patterns.forEach(function(pattern) {
                if (!fieldFound) {
                    const $field = $(pattern);
                    if ($field.length > 0) {
                        console.log('パターンで発見:', pattern, $field[0]);
                        $field.val(keywordString);
                        $field.trigger('change');
                        $field.trigger('input');
                        fieldFound = true;
                    }
                }
            });
        }
        
        // 方法4: フィールドキー field_link_keywords で検索
        if (!fieldFound) {
            const $field4 = $('input[data-key="field_link_keywords"], textarea[data-key="field_link_keywords"]');
            if ($field4.length > 0) {
                console.log('フィールドキーで発見:', $field4[0]);
                $field4.val(keywordString);
                $field4.trigger('change');
                $field4.trigger('input');
                fieldFound = true;
            }
        }
        
        // 方法5: ラベルテキストで検索
        if (!fieldFound) {
            $('.acf-field').each(function() {
                const $acfField = $(this);
                const $label = $acfField.find('label');
                const labelText = $label.text().trim();
                
                if (labelText.includes('内部リンク用キーワード') || labelText.includes('ピラー')) {
                    const $input = $acfField.find('input[type="text"], textarea');
                    if ($input.length > 0) {
                        console.log('ラベルで発見:', labelText, $input[0]);
                        $input.val(keywordString);
                        $input.trigger('change');
                        $input.trigger('input');
                        fieldFound = true;
                        return false;
                    }
                }
            });
        }
        
        // 結果の処理
        if (fieldFound) {
            alert('✅ キーワードをACFフィールドに設定しました: ' + keywordString);
            
            // 表示を更新
            $('.tcl-current-keywords').remove();
            $('.tcl-keyword-container').prepend(
                '<div class="tcl-current-keywords" style="background: #d1edff; border-left: 4px solid #0073aa; padding: 10px; margin-bottom: 15px;">' +
                '<strong>📍 現在のピラーキーワード:</strong><br>' +
                '<code>' + keywordString + '</code>' +
                '</div>'
            );
            
            // 保存確認
            setTimeout(function() {
                if (confirm('投稿を保存してキーワード設定を確定しますか？')) {
                    const $saveBtn = $('#publish, #save-post, .editor-post-publish-button').first();
                    if ($saveBtn.length > 0) {
                        $saveBtn.click();
                    } else {
                        alert('保存ボタンが見つかりません。手動で保存してください。');
                    }
                }
            }, 1000);
            
        } else {
            // デバッグ情報を出力
            console.log('=== 全フィールド調査 ===');
            $('.acf-field').each(function(i) {
                const $field = $(this);
                const $label = $field.find('label');
                const $input = $field.find('input, textarea');
                console.log('Field ' + i + ':', {
                    label: $label.text(),
                    input_name: $input.attr('name'),
                    input_id: $input.attr('id'),
                    data_name: $input.attr('data-name'),
                    data_key: $input.attr('data-key')
                });
            });
            
            alert('❌ ACFフィールド "pillar_keywords" が見つかりませんでした。\n\nコンソール（F12）でデバッグ情報を確認してください。\n\n手動でコピー: ' + keywordString);
            
            // クリップボードにコピー
            if (navigator.clipboard) {
                navigator.clipboard.writeText(keywordString).then(function() {
                    console.log('キーワードをクリップボードにコピーしました:', keywordString);
                });
            }
        }
        
        console.log('=== ACFフィールド設定完了 ===');
    });
});
</script>
        <?php
    }
    
    /**
     * AJAX: キーワード提案
     */
    public function ajax_suggest_keywords() {
        // ナンス検証
        if (!check_ajax_referer('tcl_keyword_suggestion_nonce', 'nonce', false)) {
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
            $suggestions = $this->analyze_content_for_keywords($post);
            wp_send_json_success($suggestions);
        } catch (Exception $e) {
            tcl_log_message('キーワード提案エラー: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * 投稿内容を分析してキーワードを提案
     */
    private function analyze_content_for_keywords($post) {
        if (empty($this->api_key)) {
            throw new Exception('ChatGPT APIキーが設定されていません');
        }
        
        // 投稿内容を準備
        $content = $this->prepare_content_for_analysis($post);
        
        // GPT APIでキーワード分析
        $response = $this->call_keyword_analysis_api($content, $post->post_title);
        
        return $this->process_keyword_response($response);
    }
    
    /**
     * 分析用コンテンツ準備
     */
    private function prepare_content_for_analysis($post) {
        $content = $post->post_title . "\n\n" . $post->post_content;
        
        // HTMLタグ除去
        $content = wp_strip_all_tags($content);
        
        // 改行正規化
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 適切な長さに制限
        $content = mb_substr(trim($content), 0, 1500);
        
        return $content;
    }
    
    /**
     * キーワード分析API呼び出し
     */
    private function call_keyword_analysis_api($content, $title) {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        ];
        
        $system_prompt = 'あなたはSEO専門家です。トピッククラスター戦略において、与えられた記事がピラーページとして機能するための最適なキーワードを提案してください。';
        
        $user_prompt = sprintf(
            "以下の記事内容を分析し、この記事がピラーページとして機能するための関連キーワードを5-8個提案してください。\n\n" .
            "【分析条件】\n" .
            "- この記事の主要テーマを表すキーワード\n" .
            "- 関連する複合キーワード\n" .
            "- ユーザーが検索しそうなキーワード\n" .
            "- 包括的で上位概念のキーワード\n\n" .
            "【出力形式】\n" .
            "JSON形式で以下の構造で出力してください：\n" .
            "{\n" .
            "  \"keywords\": [\"キーワード1\", \"キーワード2\", ...],\n" .
            "  \"analysis\": \"この記事の主要テーマとキーワード選定理由\",\n" .
            "  \"pillar_potential\": \"この記事がピラーページとしてどの程度適しているか\"\n" .
            "}\n\n" .
            "【記事タイトル】\n%s\n\n" .
            "【記事内容】\n%s",
            $title,
            $content
        );
        
        $body = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt]
            ],
            'temperature' => 0.3,
            'max_tokens' => 500,
        ];
        
        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('API通信エラー: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!empty($data['error'])) {
            throw new Exception('OpenAI API エラー: ' . $data['error']['message']);
        }
        
        if (empty($data['choices'][0]['message']['content'])) {
            throw new Exception('空のレスポンス');
        }
        
        return trim($data['choices'][0]['message']['content']);
    }
    
    /**
     * キーワードレスポンス処理
     */
    private function process_keyword_response($response) {
        // JSON部分を抽出
        $json_match = [];
        if (preg_match('/\{.*\}/s', $response, $json_match)) {
            $json_data = json_decode($json_match[0], true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($json_data['keywords'])) {
                return [
                    'keywords' => $json_data['keywords'],
                    'analysis' => $json_data['analysis'] ?? '',
                    'pillar_potential' => $json_data['pillar_potential'] ?? ''
                ];
            }
        }
        
        // フォールバック: 単純なキーワード抽出
        $lines = explode("\n", $response);
        $keywords = [];
        
        foreach ($lines as $line) {
            if (preg_match('/[「"](.*?)[」"]/', $line, $matches)) {
                $keywords[] = trim($matches[1]);
            }
        }
        
        return [
            'keywords' => array_slice($keywords, 0, 8),
            'analysis' => $response,
            'pillar_potential' => '分析結果の詳細な解析が必要です'
        ];
    }
}

// 初期化
TCL_KeywordSuggester::get_instance();

/**
 * 外部から呼び出し可能な関数
 */
function tcl_suggest_pillar_keywords($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return false;
    }
    
    try {
        return TCL_KeywordSuggester::get_instance()->analyze_content_for_keywords($post);
    } catch (Exception $e) {
        tcl_log_message('キーワード提案エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * Google Ads API接続テスト
 */
function tcl_test_google_ads_api_connection() {
    try {
        // 設定値を取得
        $developer_token = get_option('tcl_google_developer_token');
        $client_id = get_option('tcl_google_client_id');
        $client_secret = get_option('tcl_google_client_secret');
        $refresh_token = get_option('tcl_google_refresh_token');
        $customer_id = get_option('tcl_google_customer_id');
        
        // 必須項目チェック
        $missing = [];
        if (empty($developer_token)) $missing[] = 'Developer Token';
        if (empty($client_id)) $missing[] = 'Client ID';
        if (empty($client_secret)) $missing[] = 'Client Secret';
        if (empty($refresh_token)) $missing[] = 'Refresh Token';
        if (empty($customer_id)) $missing[] = 'Customer ID';
        
        if (!empty($missing)) {
            return [
                'success' => false,
                'error' => '以下の設定が不足しています: ' . implode(', ', $missing)
            ];
        }
        
        // Google Ads PHPライブラリが利用可能かチェック
        if (!class_exists('Google\Ads\GoogleAds\Lib\V16\GoogleAdsClientBuilder')) {
            return [
                'success' => false,
                'error' => 'Google Ads PHPライブラリが正しく読み込まれていません'
            ];
        }
        
        // 基本的な設定値テスト（実際のAPI呼び出しなし）
        return [
            'success' => true,
            'message' => 'Google Ads API設定が完了しています。' . 
                        'Developer Token: ' . substr($developer_token, 0, 8) . '..., ' .
                        'Client ID: ' . substr($client_id, 0, 12) . '..., ' .
                        'Customer ID: ' . $customer_id . ', ' .
                        'ライブラリV16が利用可能です。'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'API接続テストエラー: ' . $e->getMessage()
        ];
    }
}

/**
 * Google Ads API設定チェック関数
 */
if (!function_exists('tcl_check_google_ads_api_setup')) {
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
}

/**
 * Google Ads API用の一時設定ファイルを作成
 */
if (!function_exists('tcl_create_temp_google_ads_config')) {
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
}