<?php
function tcl_log_message($message) {
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit($upload_dir['basedir']) . 'topic-cluster-log.txt';
    $datetime = date('Y-m-d H:i:s');
    $entry = "[" . $datetime . "] " . print_r($message, true) . "\n";
    file_put_contents($log_file, $entry, FILE_APPEND);
}
