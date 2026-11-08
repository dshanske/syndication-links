<?php
/**
 * Tests for the WP_DEBUG syndication log (REVIEW.md S9).
 *
 * @package Syndication_Links
 */

class Test_Debug_Log extends WP_UnitTestCase {

	/**
	 * The file error_log() writes to during a test.
	 *
	 * @var string
	 */
	private $log;

	/**
	 * The error_log setting before the test.
	 *
	 * @var string
	 */
	private $saved;

	public function set_up() {
		parent::set_up();
		if ( ! WP_DEBUG ) {
			$this->markTestSkipped( 'The log is only written under WP_DEBUG.' );
		}
		$this->log   = wp_tempnam( 'syn-debug-log' );
		$this->saved = ini_set( 'error_log', $this->log ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	}

	public function tear_down() {
		if ( $this->log ) {
			ini_set( 'error_log', $this->saved ); // phpcs:ignore WordPress.PHP.IniSet.Risky
			unlink( $this->log );
		}
		parent::tear_down();
	}

	private function logged() {
		return file_get_contents( $this->log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	private function target() {
		return new class() extends Syndication_Provider {
			public function __construct() {
				$this->uid  = 'secret-target';
				$this->name = 'Secret Target';
			}

			public function posse( $post_id = null ) {
				return null;
			}
		};
	}

	public function test_an_error_logs_its_code_and_status_but_not_the_body() {
		$error = new WP_Error( 'invalid_response', '{"account":"private details"}', array( 'status' => 401 ) );

		Post_Syndication::error_log( $error, $this->target(), 12 );

		$logged = $this->logged();
		$this->assertStringContainsString( 'syndicating post 12 to secret-target failed: invalid_response (HTTP 401)', $logged );
		$this->assertStringNotContainsString( 'private details', $logged );
	}

	public function test_a_success_logs_its_status_and_location_but_not_the_body() {
		$dictionary = class_exists( 'WpOrg\\Requests\\Utility\\CaseInsensitiveDictionary' ) ? 'WpOrg\\Requests\\Utility\\CaseInsensitiveDictionary' : 'Requests_Utility_CaseInsensitiveDictionary';
		$response   = array(
			'headers'  => new $dictionary( array( 'location' => 'https://target.example/post/1' ) ),
			'body'     => '{"account":"private details"}',
			'response' => array(
				'code'    => 201,
				'message' => 'Created',
			),
		);

		Post_Syndication::error_log( $response, $this->target(), 12 );

		$logged = $this->logged();
		$this->assertStringContainsString( 'syndicated post 12 to secret-target (HTTP 201) https://target.example/post/1', $logged );
		$this->assertStringNotContainsString( 'private details', $logged );
	}

	public function test_the_old_one_argument_call_still_works() {
		Post_Syndication::error_log( array( 'anything' => 'private details' ) );

		$this->assertStringContainsString( 'syndicated unknown target', $this->logged() );
		$this->assertStringNotContainsString( 'private details', $this->logged() );
	}
}
