<?php
add_action( 'widgets_init', create_function( '', 'register_widget("relme_widget");' ) );

class RelMe_Widget extends WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'relme_widget',				// Base ID
			'Rel-Me Widget',		// Name
			array(
				'classname'		=> 'relme_widget',
				'description'	=> __( 'A widget that allows you to display rel-me links as icons for a site', 'framework' ),
			)
		);

	} // end constructor

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		extract( $args );
		extract( $instance );

		// Our variables from the widget settings
		$title = '';

		// Before widget (defined by theme functions file)
		echo $before_widget;

		// Display the widget title if one was input
		if ( $title ) {
			echo $before_title . $title . $after_title; }

		echo '<div id="relme_widget" class="relmewidget">';
		/* get options */
		$options = get_option( 'syndication_content_options' );
		$urls = explode( "\n", $options['relme_links'] );
		$urls = syn_meta::clean_urls( $urls );
		// Allow URLs to be added by other plugins
		$urls = apply_filters( 'syn_head_links', $urls );
		echo '<ul class="social-icon">';
		if ( ! empty( $urls ) ) {
			foreach ( $urls as $url ) {
				if ( empty( $url ) ) { continue; }
				echo '<li><a';
				if ( (is_front_page()||is_home()) ) {
					echo ' rel="me"';
				}
				echo ' href="' . $url . '" ></a></li>' . "\n";
			}
		}
		 echo '</ul></div>';

		// After widget (defined by theme functions file)
		echo $after_widget;
	}

}
?>
