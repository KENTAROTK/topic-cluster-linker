/**
 * Topic Cluster Linker - 管理画面JavaScript
 * 投稿編集画面でのSEO内部リンク管理機能
 */
// dtags変数を初期化（エラー防止）
if (typeof dtags === 'undefined') {
    var dtags = [];
}
(function($) {
    'use strict';

    /**
     * Topic Cluster Linker 管理クラス
     */
    class TCLAdmin {
        constructor() {
            this.initialized = false;
            this.currentEditor = null;
            this.pendingRequests = new Map();
            this.linkCache = new Map();
            
            this.init();
        }

        /**
         * 初期化
         */
        init() {
            if (this.initialized) return;
            
            console.log('🔗 Topic Cluster Linker: 管理画面JS初期化開始');
            
            // DOM準備完了後に実行
            $(document).ready(() => {
                this.detectEditor();
                this.bindEvents();
                this.setupTooltips();
                this.initialized = true;
                console.log('✅ Topic Cluster Linker: 初期化完了');
            });
        }

        /**
         * エディタータイプを検出
         */
        detectEditor() {
            // Gutenberg（ブロックエディタ）の検出
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
                this.currentEditor = 'gutenberg';
                console.log('📝 エディタータイプ: Gutenberg');
                this.setupGutenbergIntegration();
            }
            // クラシックエディタの検出
            else if ($('#content').length) {
                this.currentEditor = 'classic';
                console.log('📝 エディタータイプ: Classic');
            }
            else {
                this.currentEditor = 'unknown';
                console.warn('⚠️ エディタータイプを検出できませんでした');
            }
        }

        /**
         * Gutenbergエディタとの統合
         */
        setupGutenbergIntegration() {
            // エディタの準備完了を待機
            const checkEditor = () => {
                if (wp.data.select('core/block-editor').getBlocks) {
                    console.log('✅ Gutenbergエディタ準備完了');
                    this.gutenbergReady = true;
                } else {
                    setTimeout(checkEditor, 100);
                }
            };
            checkEditor();
        }

        /**
         * イベントハンドラーのバインド
         */
        bindEvents() {
            // リンク挿入ボタン
            $(document).on('click', '.insert-tcl-link', (e) => {
                this.handleLinkInsertion(e);
            });

            // GPT再生成ボタン
            $(document).on('click', '.tcl-reload', (e) => {
                this.handleLinkRegeneration(e);
            });

            // メタボックスの展開/折りたたみ
            $(document).on('click', '#tcl-cluster-links .handlediv', () => {
                this.handleMetaboxToggle();
            });

            // キーボードショートカット
            $(document).on('keydown', (e) => {
                this.handleKeyboardShortcuts(e);
            });

            // ウィンドウのリサイズ
            $(window).on('resize', this.debounce(() => {
                this.handleWindowResize();
            }, 250));
        }

        /**
         * リンク挿入処理
         */
        handleLinkInsertion(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const insertText = $button.data('insert');
            const targetId = $button.data('target-id');
            
            if (!insertText) {
                this.showNotification('❌ 挿入するリンクテキストが見つかりません', 'error');
                return;
            }

            // ボタンを無効化
            $button.prop('disabled', true).text('🔄 挿入中...');
            
            // エディタータイプに応じて挿入
            this.insertLinkToEditor(insertText)
                .then(() => {
                    this.onLinkInsertSuccess($button, targetId);
                })
                .catch((error) => {
                    this.onLinkInsertError($button, error);
                });
        }

        /**
         * エディターにリンクを挿入
         */
        async insertLinkToEditor(insertText) {
            return new Promise((resolve, reject) => {
                let inserted = false;

                // Gutenbergエディタへの挿入
                if (this.currentEditor === 'gutenberg' && this.gutenbergReady) {
                    try {
                        inserted = this.insertToGutenberg(insertText);
                    } catch (error) {
                        console.warn('Gutenberg挿入エラー:', error);
                    }
                }

                // クラシックエディタへの挿入
                if (!inserted && this.currentEditor === 'classic') {
                    try {
                        inserted = this.insertToClassicEditor(insertText);
                    } catch (error) {
                        console.warn('クラシックエディタ挿入エラー:', error);
                    }
                }

                // TinyMCEエディタへの挿入（フォールバック）
                if (!inserted && typeof tinyMCE !== 'undefined') {
                    try {
                        inserted = this.insertToTinyMCE(insertText);
                    } catch (error) {
                        console.warn('TinyMCE挿入エラー:', error);
                    }
                }

                if (inserted) {
                    resolve();
                } else {
                    reject(new Error('すべてのエディタへの挿入が失敗しました'));
                }
            });
        }

        /**
         * Gutenbergエディタに挿入
         */
        insertToGutenberg(insertText) {
            try {
                const blocks = wp.data.select('core/block-editor').getBlocks();
                const newBlock = wp.blocks.createBlock('core/paragraph', {
                    content: insertText
                });

                // 最初のH2ブロックの後に挿入を試行
                let insertIndex = this.findInsertionPoint(blocks);
                
                if (insertIndex > -1) {
                    wp.data.dispatch('core/block-editor').insertBlock(newBlock, insertIndex);
                } else {
                    wp.data.dispatch('core/block-editor').insertBlock(newBlock);
                }

                console.log('✅ Gutenbergエディタに挿入成功');
                return true;
            } catch (error) {
                console.error('Gutenberg挿入エラー:', error);
                return false;
            }
        }

        /**
         * 適切な挿入位置を検索
         */
        findInsertionPoint(blocks) {
            for (let i = 0; i < blocks.length; i++) {
                const block = blocks[i];
                
                // H2見出しの後
                if (block.name === 'core/heading' && block.attributes.level === 2) {
                    return i + 1;
                }
                
                // 最初の段落の後（フォールバック）
                if (i === 0 && block.name === 'core/paragraph') {
                    return i + 1;
                }
            }
            return -1;
        }

        /**
         * クラシックエディタに挿入
         */
        insertToClassicEditor(insertText) {
            const $content = $('#content');
            if (!$content.length) return false;

            try {
                let content = $content.val();
                const h2Pattern = /(<h2[^>]*>.*?<\/h2>)/i;
                
                if (h2Pattern.test(content)) {
                    content = content.replace(h2Pattern, '$1\n\n' + insertText + '\n\n');
                } else {
                    content += '\n\n' + insertText + '\n\n';
                }
                
                $content.val(content);
                
                // エディタにフォーカス
                $content.focus();
                
                console.log('✅ クラシックエディタに挿入成功');
                return true;
            } catch (error) {
                console.error('クラシックエディタ挿入エラー:', error);
                return false;
            }
        }

        /**
         * TinyMCEエディタに挿入
         */
        insertToTinyMCE(insertText) {
            try {
                const editor = tinyMCE.get('content');
                if (!editor) return false;

                const content = editor.getContent();
                const h2Pattern = /(<h2[^>]*>.*?<\/h2>)/i;
                
                let newContent;
                if (h2Pattern.test(content)) {
                    newContent = content.replace(h2Pattern, '$1<p>' + insertText + '</p>');
                } else {
                    newContent = content + '<p>' + insertText + '</p>';
                }
                
                editor.setContent(newContent);
                editor.focus();
                
                console.log('✅ TinyMCEエディタに挿入成功');
                return true;
            } catch (error) {
                console.error('TinyMCE挿入エラー:', error);
                return false;
            }
        }

        /**
         * リンク挿入成功時の処理
         */
        onLinkInsertSuccess($button, targetId) {
            $button.removeClass('tcl-button-primary')
                   .addClass('tcl-button-success')
                   .html('✅ 挿入完了')
                   .prop('disabled', true);

            // 成功通知
            this.showNotification(tcl_ajax.messages.insert_success, 'success');

            // 統計を更新
            this.updateLinkStatistics();

            // しばらく後にページをリロード（統計更新のため）
            setTimeout(() => {
                if (confirm('リンクが挿入されました。ページを更新して最新の状態を表示しますか？')) {
                    window.location.reload();
                }
            }, 2000);
        }

        /**
         * リンク挿入エラー時の処理
         */
        onLinkInsertError($button, error) {
            console.error('リンク挿入エラー:', error);
            
            $button.prop('disabled', false)
                   .text('🔗 挿入')
                   .addClass('tcl-button-error');

            this.showNotification(tcl_ajax.messages.insert_error + ': ' + error.message, 'error');

            // エラー状態を一定時間後にリセット
            setTimeout(() => {
                $button.removeClass('tcl-button-error');
            }, 3000);
        }

        /**
         * GPTリンク再生成処理
         */
        handleLinkRegeneration(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const postId = $button.data('post-id');
            const clusterId = $button.data('cluster-id');
            
            if (!postId || !clusterId) {
                this.showNotification('❌ 必要なデータが不足しています', 'error');
                return;
            }

            // 重複リクエストを防ぐ
            const requestKey = `${postId}-${clusterId}`;
            if (this.pendingRequests.has(requestKey)) {
                this.showNotification('🤖 生成処理中です...', 'info');
                return;
            }

            this.performRegeneration($button, postId, clusterId, requestKey);
        }

        /**
         * 再生成の実行
         */
        performRegeneration($button, postId, clusterId, requestKey) {
            const originalText = $button.text();
            const $preview = $button.closest('.tcl-link-box').find('.tcl-preview');
            const $insertButton = $button.siblings('.insert-tcl-link');

            // UI状態を更新
            $button.prop('disabled', true)
                   .html('<span class="tcl-loading-spinner"></span> ' + tcl_ajax.messages.generating);
            
            $preview.addClass('loading').text('🤖 AIが新しいリンクテキストを生成中...');

            // リクエストを記録
            this.pendingRequests.set(requestKey, true);

            // AJAX リクエスト
            const ajaxData = {
                action: 'tcl_regenerate_link',
                post_id: postId,
                cluster_id: clusterId,
                nonce: tcl_ajax.nonce
            };

            $.ajax({
                url: tcl_ajax.ajax_url,
                method: 'POST',
                data: ajaxData,
                timeout: 30000, // 30秒タイムアウト
                success: (response) => {
                    this.onRegenerationSuccess(response, $button, $preview, $insertButton, originalText);
                },
                error: (xhr, status, error) => {
                    this.onRegenerationError(xhr, status, error, $button, $preview, originalText);
                },
                complete: () => {
                    this.pendingRequests.delete(requestKey);
                }
            });
        }

        /**
         * 再生成成功時の処理
         */
        onRegenerationSuccess(response, $button, $preview, $insertButton, originalText) {
            if (response.success && response.data && response.data.text) {
                const newText = response.data.text;
                
                // プレビューを更新
                $preview.removeClass('loading').html(newText);
                
                // 挿入ボタンのデータを更新
                $insertButton.data('insert', newText);
                
                // キャッシュに保存
                const cacheKey = $insertButton.data('target-id');
                this.linkCache.set(cacheKey, newText);
                
                // 成功通知
                this.showNotification('✨ 新しいリンクテキストを生成しました', 'success');
                
                console.log('✅ GPT再生成成功:', newText);
            } else {
                const errorMsg = response.data || '不明なエラーが発生しました';
                this.showNotification('❌ 再生成に失敗: ' + errorMsg, 'error');
                console.error('再生成レスポンスエラー:', response);
            }
            
            // ボタンを復元
            $button.prop('disabled', false).text(originalText);
        }

        /**
         * 再生成エラー時の処理
         */
        onRegenerationError(xhr, status, error, $button, $preview, originalText) {
            console.error('AJAX エラー:', { status, error, xhr });
            
            let errorMessage = tcl_ajax.messages.communication_error;
            
            if (status === 'timeout') {
                errorMessage = '⏱️ タイムアウトが発生しました。もう一度お試しください。';
            } else if (xhr.status === 403) {
                errorMessage = '🔒 権限がありません。ログインし直してください。';
            } else if (xhr.status >= 500) {
                errorMessage = '🔧 サーバーエラーが発生しました。管理者にお問い合わせください。';
            }
            
            this.showNotification(errorMessage, 'error');
            
            // プレビューとボタンを復元
            $preview.removeClass('loading').text('❌ 生成に失敗しました。再度お試しください。');
            $button.prop('disabled', false).text(originalText);
        }

        /**
         * キーボードショートカット処理
         */
        handleKeyboardShortcuts(e) {
            // Ctrl/Cmd + Shift + L でメタボックスにフォーカス
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
                e.preventDefault();
                const $metabox = $('#tcl-cluster-links');
                if ($metabox.length) {
                    $metabox[0].scrollIntoView({ behavior: 'smooth' });
                    $metabox.find('.insert-tcl-link:first').focus();
                }
            }
        }

        /**
         * メタボックス展開/折りたたみ処理
         */
        handleMetaboxToggle() {
            // メタボックスの状態を保存（ユーザー設定として）
            const $metabox = $('#tcl-cluster-links');
            const isOpen = !$metabox.hasClass('closed');
            
            // ローカルストレージに保存
            localStorage.setItem('tcl_metabox_state', isOpen ? 'open' : 'closed');
        }

        /**
         * ウィンドウリサイズ処理
         */
        handleWindowResize() {
            // レスポンシブ対応の調整が必要な場合ここに実装
            console.log('Window resized, adjusting layout if needed');
        }

        /**
         * ツールチップのセットアップ
         */
        setupTooltips() {
            // カスタムツールチップの実装
            $(document).on('mouseenter', '[data-tcl-tooltip]', function() {
                const $this = $(this);
                const tooltipText = $this.data('tcl-tooltip');
                
                const $tooltip = $('<div class="tcl-tooltip">' + tooltipText + '</div>');
                $('body').append($tooltip);
                
                const offset = $this.offset();
                $tooltip.css({
                    position: 'absolute',
                    top: offset.top - $tooltip.outerHeight() - 10,
                    left: offset.left + ($this.outerWidth() / 2) - ($tooltip.outerWidth() / 2),
                    zIndex: 999999
                }).fadeIn(200);
            });

            $(document).on('mouseleave', '[data-tcl-tooltip]', function() {
                $('.tcl-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        }

        /**
         * 通知表示
         */
        showNotification(message, type = 'info', duration = 4000) {
            const $notification = $(`
                <div class="tcl-notification tcl-notification-${type}">
                    <span class="tcl-notification-message">${message}</span>
                    <button class="tcl-notification-close">&times;</button>
                </div>
            `);

            // 既存の通知を削除
            $('.tcl-notification').remove();

            // 通知を追加
            $('body').append($notification);

            // アニメーション
            $notification.slideDown(300);

            // 自動削除
            setTimeout(() => {
                $notification.slideUp(300, () => $notification.remove());
            }, duration);

            // 閉じるボタン
            $notification.find('.tcl-notification-close').on('click', () => {
                $notification.slideUp(300, () => $notification.remove());
            });
        }

        /**
         * リンク統計を更新
         */
        updateLinkStatistics() {
            const $statsGrid = $('.tcl-stats-grid');
            if ($statsGrid.length) {
                // 統計の再計算と更新
                // この機能は必要に応じて実装
            }
        }

        /**
         * デバウンス関数
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * デバッグ情報を出力
         */
        debug() {
            console.group('🔗 Topic Cluster Linker Debug Info');
            console.log('Editor Type:', this.currentEditor);
            console.log('Gutenberg Ready:', this.gutenbergReady);
            console.log('Pending Requests:', this.pendingRequests.size);
            console.log('Cache Size:', this.linkCache.size);
            console.log('Ajax Config:', tcl_ajax);
            console.groupEnd();
        }
    }

    /**
     * 通知用CSS
     */
    const notificationCSS = `
        <style>
            .tcl-notification {
                position: fixed;
                top: 32px;
                right: 20px;
                min-width: 300px;
                max-width: 500px;
                padding: 12px 16px;
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 999999;
                display: none;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 14px;
                line-height: 1.4;
            }
            .tcl-notification-success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
            }
            .tcl-notification-error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
            }
            .tcl-notification-info {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                color: #0c5460;
            }
            .tcl-notification-message {
                display: block;
                margin-right: 30px;
            }
            .tcl-notification-close {
                position: absolute;
                top: 8px;
                right: 12px;
                background: none;
                border: none;
                font-size: 18px;
                font-weight: bold;
                cursor: pointer;
                color: inherit;
                opacity: 0.7;
            }
            .tcl-notification-close:hover {
                opacity: 1;
            }
            .tcl-tooltip {
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                max-width: 200px;
                text-align: center;
                display: none;
            }
            .tcl-button-success {
                background: #28a745 !important;
                color: white !important;
            }
            .tcl-button-error {
                background: #dc3545 !important;
                color: white !important;
            }
        </style>
    `;

    // CSSを頭部に追加
    $('head').append(notificationCSS);

    /**
     * グローバルオブジェクトとしてTCLAdminを公開
     */
    window.TCLAdmin = TCLAdmin;

    /**
     * インスタンスを作成
     */
    const tclAdmin = new TCLAdmin();

    // デバッグ用にグローバルアクセス可能にする
    window.tclAdmin = tclAdmin;

    // デバッグモード（開発時のみ）
    if (typeof TCL_DEBUG !== 'undefined' && TCL_DEBUG) {
        console.log('🔍 Topic Cluster Linker: デバッグモード有効');
        window.tclDebug = () => tclAdmin.debug();
    }

})(jQuery);