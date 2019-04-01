(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
    $(function() {
      $('.sortable-list tbody').sortable({
        items: '.source-item',
        opacity: 0.5,
        cursor: 'pointer',
        axis: 'y',
        helper: function(e, tr)
        {
          var $originals = tr.children();
          var $helper = tr.clone();
          $helper.children().each(function(index)
          {
            // Set helper cell sizes to match the original sizes
            $(this).width($originals.eq(index).width());
          });
          return $helper;
        },
        update: function() {

            var data = {
              action: 'list_update_order',
              source_items: $(this).sortable('toArray', {attribute: 'data-source-id'})
            }
            $.post(ajaxurl, data)
            .done(function(response){
              console.log(response);
            })
            .fail(function(err) {
              console.error(err.responseJSON);
            });
        }
      });

      $('.feed-source-list').on('click', '.remove-source-item', function() {
        var result = confirm('Are you sure?');
        if (!result) {
          return;
        }
        var row = $(this).parents('tr');
        var data = {
          action: 'remove_source_item',
          source_id: $(this).parents('tr').data('source-id')
        }
        $.post(ajaxurl, data, function(response) {
          if (response.deleted) {
            row.remove();
          }
        });
      });

      $('.feed-source-list').on('click', '.disable-summary', function() {
        var data = {
          action: 'set_disable_summary',
          source_id: $(this).parents('tr').data('source-id'),
          disable_summary: $(this)[0].checked
        }
        $.post(ajaxurl, data, function(response) {
        });
      });

      if( $('#code_editor_page_js').length ) {
        var editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
        editorSettings.codemirror = _.extend(
            {},
            editorSettings.codemirror,
            {
                indentUnit: 2,
                tabSize: 2,
                mode: {name: "javascript", json: true},
                viewportMargin: Infinity
            }
        );
        var editor = wp.codeEditor.initialize( $('#code_editor_page_js'), editorSettings );
        $('button#generate-assets').on('click', function() {
          $.getJSON(assetUrl + '?generate=true')
          .then(function(assets) {
            if (assets && assets.app_assets) {
              editor.codemirror.setValue(JSON.stringify(assets.app_assets, null, 2));
            }
          });
        });
      }

      $('a#publish-sources').on('click', function() {
        var data = {
          action: 'publish_source_changes',
        }
        $.post(ajaxurl, data, function(response) {
          console.log(response);
          location.reload();
        });
        return false;
      });
      $('a#revert-source-changes').on('click', function() {
        var data = {
          action: 'revert_source_changes',
        }
        $.post(ajaxurl, data, function(response) {
          console.log(response);
          location.reload();
        });
        return false;
      });

      $('.slot-list').on('click', '.remove-slot-item', function() {
        var result = confirm('Are you sure?');
        if (!result) {
          return;
        }
        var row = $(this).parents('tr');
        var data = {
          action: 'remove_ad_slot',
          index: row.data('slot-id'),
          article_set_id: row.data('slot-article-set')
        };
        $.post(ajaxurl, data, function(response) {
          console.log(response);
          if (response.deleted) {
            row.remove();
          }
        });
      })
    });
})( jQuery );
