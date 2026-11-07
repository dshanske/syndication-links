<?php
/**
 * Tests for the "Original Of" widget (#256).
 *
 * @package Syndication_Links
 */

class Test_Widget_Original_Of extends WP_UnitTestCase {

	/**
	 * Sidebar arguments, as a theme would register them.
	 *
	 * @var array
	 */
	private $args = array(
		'before_widget' => '<section>',
		'after_widget'  => '</section>',
		'before_title'  => '<h2>',
		'after_title'   => '</h2>',
	);

	/**
	 * A widget instance.
	 *
	 * @return Widget_Original_Of
	 */
	private function widget() {
		$widget = new Widget_Original_Of();
		$widget->_set( 2 );
		return $widget;
	}

	public function test_update_sanitizes_the_title() {
		$saved = $this->widget()->update( array( 'title' => "  <b>Find</b> the\noriginal  " ), array() );

		$this->assertSame( array( 'title' => 'Find the original' ), $saved );
	}

	public function test_update_keeps_only_the_title() {
		$saved = $this->widget()->update(
			array(
				'title' => 'Original',
				'extra' => '<script>',
			),
			array()
		);

		$this->assertSame( array( 'title' => 'Original' ), $saved );
	}

	public function test_update_without_a_title() {
		$this->assertSame( array( 'title' => '' ), $this->widget()->update( array(), array() ) );
	}

	public function test_widget_escapes_the_title() {
		ob_start();
		$this->widget()->widget( $this->args, array( 'title' => 'A & <i>B</i>' ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<h2>A &amp; &lt;i&gt;B&lt;/i&gt;</h2>', $html );
		$this->assertStringContainsString( 'class="original-of-form"', $html );
		$this->assertStringStartsWith( '<section>', $html );
		$this->assertStringEndsWith( '</section>', $html );
	}

	public function test_widget_without_a_title_has_no_heading() {
		ob_start();
		$this->widget()->widget( $this->args, array() );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<h2>', $html );
	}

	public function test_form_markup() {
		$widget = $this->widget();

		ob_start();
		$widget->form( array( 'title' => 'Say "hi"' ) );
		$html = ob_get_clean();

		$id   = $widget->get_field_id( 'title' );
		$name = $widget->get_field_name( 'title' );
		$this->assertStringContainsString( '<label for="' . esc_attr( $id ) . '">', $html );
		$this->assertStringContainsString( 'name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" value="Say &quot;hi&quot;"', $html );
		$this->assertStringNotContainsString( "\n", $html );
	}
}
