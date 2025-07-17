jQuery(document).ready(function ($) {
  $('.insert-tcl-link').on('click', function () {
    const insertText = $(this).data('insert');
    let content = $('#content').val();

    // 最初の<h2>直後に挿入（シンプルな正規表現処理）
    const pattern = /(<h2[^>]*>.*?<\\/h2>)/i;
    if (pattern.test(content)) {
      content = content.replace(pattern, '$1\n\n' + insertText + '\n\n');
    } else {
      content += '\n\n' + insertText;
    }

    $('#content').val(content);
    alert('本文に挿入しました');
  });

  $('.tcl-reload').on('click', function () {
    alert('このボタンは次フェーズで実装予定です。');
    // 再生成API呼び出し予定（別途PHP+Ajax実装）
  });
});
