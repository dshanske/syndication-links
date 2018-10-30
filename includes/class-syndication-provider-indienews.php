<?php

class Syndication_Provider_Indienews extends Syndication_Provider_Webmention {

	public function __construct( $args = array() ) {
		$this->name = __( 'Indienews', 'syndication-links' );
		$this->uid  = 'indienews';

		// Parent Constructor
		parent::__construct( $args );
	}

	/**
	 * Get the blogs language and check if it supported.
	 *
	 * @return string The blogs language with a default fallback.
	 */
	public function get_language() {
		$locale    = get_locale();
		$locale    = substr( $locale, 0, 2 );
		$languages = array( 'en', 'sv', 'de', 'fr', 'nl' );
		if ( in_array( $locale, $languages, true ) ) {
			return $locale;
		}
		return 'en';
	}

	public function get_target() {
		return sprintf( 'https://news.indieweb.org/%1$s', $this->get_language() );
	}
}

if ( class_exists( 'Webmention_Plugin' ) ) {
	register_syndication_provider( new Syndication_Provider_Indienews() );
}
