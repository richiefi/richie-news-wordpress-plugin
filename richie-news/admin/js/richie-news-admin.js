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
            $.post(ajaxurl, data, function(response){

            });
        }
      });

      $('.feed-source-list').on('click', '.remove-source-item', function() {
        var data = {
          action: 'remove_source_item',
          id: $(this).parents('tr').data('data-source-id')
        }
        alert(JSON.stringify(data));
      })
    });
})( jQuery );
