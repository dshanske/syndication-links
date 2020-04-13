jQuery( document ).ready( function( $ ) {
	jQuery( document );
	$( '#add-syn-link-button' ).click( function( event ) {
		event.preventDefault();
		$( this ).prev( '.syndication_url_list > ul' ).append( '<li><input type="text" name="syndication_urls[]" class="widefat" value="" /></li>' );
	} );
	$( '#add-custom-webmention-button' ).click( function( event ) {
		const n = $( '#custom_webmention > li' ).length;
		const s = '<li><input type="text" placeholder="Name" name="syndication_links_custom_posse[' + n + '][name]" /><input placeholder="UID" type="text" name="syndication_links_custom_posse[' + n + '][uid]" /><input type="text" placeholder="Target URL" name="syndication_links_custom_posse[' + n + '][target]" /></li>';
		$( s ).appendTo( '#custom_webmention' );
	} );
	$( '#delete-custom-webmention-button' ).click( function( event ) {
		$( '#custom_webmention li:last-of-type' ).remove();
	} );
} );
