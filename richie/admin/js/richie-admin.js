( function( $ ) {
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
      $('.richie-settings .cpa-color-picker').wpColorPicker();

      var tagSuggest = $('.richie-settings .richie-tag-suggest');
      if (tagSuggest.length > 0) {
        $('.richie-settings .richie-tag-suggest').suggest( window.ajaxurl + '?action=ajax-tag-search&tax=post_tag', {multiple: true, multipleSep: ','});
      }

      $('.richie-settings .sortable-list tbody').sortable({
        items: '.source-item',
        opacity: 0.5,
        cursor: 'pointer',
        axis: 'y',
        helper: function(e, tr)
        {
          var $originals = tr.children();
          var $helper = tr.clone();
          $helper.children().each(function(index) {
            // Set helper cell sizes to match the original sizes
            $(this).width($originals.eq(index).width());
          });
          return $helper;
        },
        update: function() {

            var data = {
              action: 'list_update_order',
              security: richie_ajax.security,
              source_items: $(this).sortable('toArray', {attribute: 'data-source-id'})
            }
            $.post(ajaxurl, data)
            .done(function(response) {
              console.log(response);
            })
            .fail(function(err) {
              console.error(err.responseJSON);
            });
        }
      });

      $('.richie-settings .feed-source-list').on('click', '.remove-source-item', function() {
        var result = confirm('Are you sure?');
        if (!result) {
          return;
        }
        var row = $(this).parents('tr');
        var data = {
          action: 'remove_source_item',
          security: richie_ajax.security,
          source_id: $(this).parents('tr').data('source-id')
        }
        $.post(ajaxurl, data, function(response) {
          if (response.deleted) {
            row.remove();
          }
        });
      });

      $('.richie-settings .feed-source-list').on('click', '.disable-summary', function() {
        var data = {
          action: 'set_checkbox_field',
          security: richie_ajax.security,
          source_id: $(this).parents('tr').data('source-id'),
          checked: $(this)[0].checked,
          field_name: 'disable_summary'
        }
        $.post(ajaxurl, data)
        .done(function(response) {
          console.log(response);
        })
        .fail(function(err) {
          console.error(err);
        });
      });

      $('.richie-settings .feed-source-list').on('click', '.allow-duplicates', function() {
        var data = {
          action: 'set_checkbox_field',
          security: richie_ajax.security,
          source_id: $(this).parents('tr').data('source-id'),
          checked: $(this)[0].checked,
          field_name: 'allow_duplicates'
        }
        $.post(ajaxurl, data)
        .done(function(response) {
          console.log(response);
        })
        .fail(function(err) {
          console.error(err);
        });
      });

      if( $('.richie-settings #code_editor_page_js').length ) {
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
        $('.richie-settings button#generate-assets').on('click', function() {
          $.getJSON(assetUrl + '?generate=true')
          .then(function(assets) {
            if (assets && assets.app_assets) {
              editor.codemirror.setValue(JSON.stringify(assets.app_assets, null, 2));
            }
          });
        });
      }

      $('.richie-notice a#publish-sources').on('click', function() {
        var data = {
          action: 'publish_source_changes',
          security: richie_ajax.security
        }
        $.post(ajaxurl, data, function(response) {
          console.log(response);
          location.reload();
        });
        return false;
      });
      $('.richie-notice a#revert-source-changes').on('click', function() {
        var data = {
          action: 'revert_source_changes',
          security: richie_ajax.security
        }
        $.post(ajaxurl, data, function(response) {
          console.log(response);
          location.reload();
        });
        return false;
      });

      $('.richie-settings .slot-list').on('click', '.remove-slot-item', function() {
        var result = confirm('Are you sure?');
        if (!result) {
          return;
        }
        var row = $(this).parents('tr');
        var data = {
          action: 'remove_ad_slot',
          security: richie_ajax.security,
          index: row.data('slot-id'),
          article_set_id: row.data('slot-article-set')
        };
        $.post(ajaxurl, data, function(response) {
          console.log(response);
          if (response.deleted) {
            row.remove();
          }
        });
      });

      $('.richie-settings .slot-list').on('click', '.copy-slot-value', function() {
        var row = $(this).parents('tr');
        var data = {
          action: 'get_adslot_data',
          security: richie_ajax.security,
          index: row.data('slot-id'),
          article_set_id: row.data('slot-article-set')
        };
        $.post(ajaxurl, data, function(response) {
          var slot = JSON.parse(response);
          console.log(slot);
          var $form = $('form[name=richie-adslots-form]');

          $form.find('#richie_adslots-article_set').val(slot.article_set);
          $form.find('[name*=adslot_position_index]').val(slot.index);
          $form.find('[name*=adslot_provider]').val(slot.attributes.ad_provider);
          var editor = document.querySelector('.CodeMirror').CodeMirror;
          console.log(editor);
          editor.setValue(JSON.stringify(slot.attributes.ad_data, null, 2));
          // setTimeout(function() {
          //   editor.refresh();
          // }, 1);

        });
      });
    });
}( jQuery ) );
