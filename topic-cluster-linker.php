<?php
/**
 * Plugin Name: Topic Cluster Linker
 * Description: ピラーページとクラスターページの内部リンクを自動提案・挿入します。
 * Version: 6.8
 * Author: あなた
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/propose-cluster.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';
