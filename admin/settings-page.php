<?php
add_action('admin_menu', function () {
  add_menu_page(
    'トピッククラスター管理',
    'Topic Cluster',
    'manage_options',
    'topic-cluster-linker',
    'tcl_settings_page',
    'dashicons-networking'
  );
});

function tcl_settings_page() {
  ?>
  <div class="wrap">
    <h1>トピッククラスター管理</h1>
    
    <div class="notice notice-info">
      <p><strong>設定方法：</strong></p>
      <ol>
        <li>ACFで「pillar_keywords」フィールドを作成し、ピラーページに設定してください</li>
        <li>ピラーページの「pillar_keywords」フィールドに関連キーワードを「、」区切りで入力</li>
        <li>下記「クラスターページ再提案」ボタンでクラスターページを自動提案</li>
        <li>投稿編集画面でリンクを挿入（1投稿あたり2個まで）</li>
      </ol>
    </div>

    <form method="post" action="options.php" style="margin-bottom: 30px;">
      <?php
        settings_fields('tcl-settings-group');
        do_settings_sections('topic-cluster-linker');
        submit_button('APIキーを保存');
      ?>
    </form>

    <form method="post">
      <input type="submit" name="tcl_propose_clusters" class="button button-primary" value="クラスターページ再提案">
    </form>
    
    <?php
    if (isset($_POST['tcl_propose_clusters'])) {
      tcl_run_propose_clusters();
      echo '<div class="notice notice-success"><p>クラスターページの再提案が完了しました。</p></div>';
    }
    ?>
    
    <hr>
    <h2>ピラーページ別クラスター提案</h2>
    <?php tcl_display_proposals_by_pillar(); ?>
  </div>
  <?php
}

// ピラーページごとに提案を表示
function tcl_display_proposals_by_pillar() {
  $proposals = get_option('tcl_cluster_proposals', []);
  
  if (empty($proposals)) {
    echo '<p>提案はまだありません。上記「クラスターページ再提案」ボタンをクリックしてください。</p>';
    return;
  }
  
  foreach ($proposals as $pillar_id => $clusters) {
    $pillar_post = get_post($pillar_id);
    if (!$pillar_post) continue;
    
    echo '<div style="margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
    echo '<h3>📍 ピラーページ: ' . esc_html($pillar_post->post_title) . '</h3>';
    
    $pillar_keywords = get_field('pillar_keywords', $pillar_id);
    if ($pillar_keywords) {
      echo '<p><strong>キーワード:</strong> ' . esc_html($pillar_keywords) . '</p>';
    } else {
      echo '<p><strong>キーワード:</strong> <span style="color: #d63384;">未設定</span></p>';
    }
    
    echo '<p><strong>関連クラスター数:</strong> ' . count($clusters) . '件</p>';
    echo '<p><strong>投稿編集:</strong> <a href="' . get_edit_post_link($pillar_id) . '" target="_blank">編集画面を開く</a></p>';
    
    if (!empty($clusters)) {
      echo '<h4>関連クラスターページ:</h4>';
      echo '<ul>';
      foreach ($clusters as $item) {
        $cluster_post = get_post($item['cluster_id']);
        if ($cluster_post) {
          echo '<li>';
          echo '<a href="' . get_edit_post_link($item['cluster_id']) . '" target="_blank">';
          echo esc_html($cluster_post->post_title);
          echo '</a>';
          echo ' <small style="color: #666;">(' . $cluster_post->post_type . ')</small>';
          
          // マッチしたキーワードを表示
          if (!empty($item['matched_keywords'])) {
            echo '<br><small style="color: #0073aa;">マッチキーワード: ' . implode(', ', $item['matched_keywords']) . '</small>';
          }
          echo '</li>';
        }
      }
      echo '</ul>';
    } else {
      echo '<p style="color: #666;">関連クラスターページはありません。</p>';
    }
    echo '</div>';
  }
}

add_action('admin_init', function () {
  register_setting('tcl-settings-group', 'tcl_api_key');
  add_settings_section('tcl-main', 'API設定', null, 'topic-cluster-linker');
  add_settings_field('tcl-api-key', 'ChatGPT APIキー', function () {
    $value = esc_attr(get_option('tcl_api_key'));
    echo "<input type='text' name='tcl_api_key' value='{$value}' class='regular-text' placeholder='sk-...' />";
    echo "<p class='description'>OpenAI APIキーを入力してください。</p>";
  }, 'topic-cluster-linker', 'tcl-main');
});