jQuery( document ).ready( function( $ ) {

jQuery( document )
	.on( 'click', '.add-syn-link-button', function() {
		 $( '<input type="text" name="syndication_urls[]" class="widefat" id="syndication_urls" value="" /><br />' ).appendTo( '.syndication_url_list' );
	});
});
