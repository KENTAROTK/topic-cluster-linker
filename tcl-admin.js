jQuery(document).ready(function ($) {
  $('.insert-tcl-link').on('click', function () {
    const insertText = $(this).data('insert');
    
    // クラシックエディタの場合
    if ($('#content').length) {
      let content = $('#content').val();
      const pattern = /(<h2[^>]*>.*?<\/h2>)/i;
      if (pattern.test(content)) {
        content = content.replace(pattern, '$1\n\n' + insertText + '\n\n');
      } else {
        content += '\n\n' + insertText;
      }
      $('#content').val(content);
    }
    
    // ブロックエディタの場合（Gutenberg）
    if (wp.data && wp.data.select('core/block-editor')) {
      const blocks = wp.data.select('core/block-editor').getBlocks();
      const newBlock = wp.blocks.createBlock('core/paragraph', {
        content: insertText
      });
      wp.data.dispatch('core/block-editor').insertBlock(newBlock);
    }
    
    alert('本文に挿入しました');
  });

  $('.tcl-reload').on('click', function () {
    const button = $(this);
    const postId = button.data('post-id');
    const clusterId = button.data('cluster-id');
    
    button.text('生成中...');
    
    $.ajax({
      url: tcl_ajax.ajax_url,
      method: 'POST',
      data: {
        action: 'tcl_regenerate_link',
        post_id: postId,
        cluster_id: clusterId,
        nonce: tcl_ajax.nonce
      },
      success: function(response) {
        if (response.success) {
          button.siblings('div').html(response.data.text);
          button.siblings('.insert-tcl-link').data('insert', response.data.text);
        }
        button.text('再生成');
      },
      error: function() {
        alert('エラーが発生しました');
        button.text('再生成');
      }
    });
  });
});