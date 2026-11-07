jQuery( document ).ready( function( $ ) {
	// Metabox: add another syndication link field.
	$( '#add-syn-link-button' ).on( 'click', function( event ) {
		event.preventDefault();
		$( this ).prev( '.syndication_url_list > ul' ).append( '<li><input type="text" name="syndication_urls[]" class="widefat" value="" /></li>' );
	} );
	// Settings: add a row for a custom webmention provider.
	$( '#add-custom-webmention-button' ).on( 'click', function( event ) {
		event.preventDefault();
		const n = $( '#custom_webmention > li' ).length;
		const s = '<li><input type="text" placeholder="Name" name="syndication_links_custom_posse[' + n + '][name]" /><input placeholder="UID" type="text" name="syndication_links_custom_posse[' + n + '][uid]" /><input type="url" placeholder="Target URL" name="syndication_links_custom_posse[' + n + '][target]" /></li>';
		$( s ).appendTo( '#custom_webmention' );
	} );
	// Settings: remove the last custom webmention provider row.
	$( '#delete-custom-webmention-button' ).on( 'click', function( event ) {
		event.preventDefault();
		$( '#custom_webmention li:last-of-type' ).remove();
	} );
} );
