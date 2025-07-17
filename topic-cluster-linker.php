<?php
/**
 * Plugin Name: Topic Cluster Linker
 * Description: 投稿に関連するクラスターページを提案・保存・内部リンクするWordPressプラグイン
 * Version: 0.1
 * Author: あなた
 */

if (!defined('ABSPATH')) exit;

// 管理メニュー追加
add_action('admin_menu', function () {
    add_menu_page('トピッククラスター', 'トピッククラスター', 'manage_options', 'topic-cluster-linker', 'tcl_render_admin_settings_page');
});

function tcl_render_admin_settings_page() {
    echo '<div class="wrap"><h1>トピッククラスター管理</h1>';
    echo '<p>ここにクラスターページ提案一覧などを表示予定。</p>';
    echo '</div>';
}
