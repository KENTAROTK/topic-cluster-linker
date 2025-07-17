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
    }
    ?>
    <hr>
    <h2>提案済みクラスターページ</h2>
    <?php
      $proposals = get_option('tcl_cluster_proposals', []);
      if (empty($proposals)) {
        echo '<p>提案はまだありません。</p>';
      } else {
        echo '<ul>';
        foreach ($proposals as $pillar_id => $clusters) {
          echo '<li><strong>' . get_the_title($pillar_id) . '</strong><ul>';
          foreach ($clusters as $item) {
            echo '<li>' . get_the_title($item['cluster_id']) . '</li>';
          }
          echo '</ul></li>';
        }
        echo '</ul>';
      }
    ?>
  </div>
  <?php
}

add_action('admin_init', function () {
  register_setting('tcl-settings-group', 'tcl_api_key');
  add_settings_section('tcl-main', 'API設定', null, 'topic-cluster-linker');
  add_settings_field('tcl-api-key', 'ChatGPT APIキー', function () {
    $value = esc_attr(get_option('tcl_api_key'));
    echo "<input type='text' name='tcl_api_key' value='{$value}' class='regular-text' />";
  }, 'topic-cluster-linker', 'tcl-main');
});
