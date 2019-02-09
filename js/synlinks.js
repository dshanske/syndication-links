jQuery( document ).ready( function( $ ) {

jQuery( document )
	$( '#add-syn-link-button' ).click( function( event ) {
		 $( '<input type="text" name="syndication_urls[]" class="widefat" id="syndication_urls" value="" /><br />' ).appendTo( '.syndication_url_list' );
	});
	$( '#add-custom-webmention-button' ).click( function( event ) {
		var n = $( "#custom_webmention > li" ).length;
		var s = '<li><input type="text" placeholder="Name" name="syndication_links_custom_posse[' + n + '][name]" /><input placeholder="UID" type="text" name="syndication_links_custom_posse[' + n + '][uid]" /><input type="text" placeholder="Target URL" name="syndication_links_custom_posse[' + n + '][target]" /></li>' 
		$( s ).appendTo( '#custom_webmention' );
	});
	$( '#delete-custom-webmention-button' ).click( function( event ) {
		$( '#custom_webmention li:last-of-type' ).remove();
	});
});


