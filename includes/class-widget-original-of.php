<?php
/**
 * Original Of widget.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;

/**
 * A widget with a form that looks up the original of a syndicated copy.
 *
 * @see get_original_of_form()
 */
class Widget_Original_Of extends WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'Widget_Original_Of',                // Base ID.
			__( 'Original Of', 'syndication-links' ),        // Name.
			array(
				'classname'   => 'original_of_widget',
				'description' => __( 'Displays a search box to search for posts by their syndicated URLs', 'syndication-links' ),
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
		if ( ! array_key_exists( 'title', $instance ) ) {
			$instance['title'] = '';
		}

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		// phpcs:ignore
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- before/after_title come from the theme's sidebar.
		}
		get_original_of_form();
		// phpcs:ignore
		if ( isset( $args['after_widget'] ) ) {
			echo $args['after_widget']; // phpcs:ignore
		}
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}


	/**
	 * Create the form for the Widget admin
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		printf(
			'<p><label for="%1$s">%2$s</label> <input type="text" class="widefat" name="%3$s" id="%1$s" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title:', 'syndication-links' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);
	}
}
