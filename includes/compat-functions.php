<?php
/**
 * Compatibility functions: polyfills for functions newer than the minimum
 * supported versions (PHP 7.4, WordPress 6.2, ClassicPress).
 *
 * Wrap each polyfill in its own function_exists() check, note the version that
 * introduced it, and match core's signature and behaviour.
 *
 * @package Syndication_Links
 */

defined( 'ABSPATH' ) || exit;
