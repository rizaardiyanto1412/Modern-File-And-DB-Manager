<?php

namespace ModernFileManager\Tests;

use ModernFileManager\DB_Manager_Service;
use PHPUnit\Framework\TestCase;

class DbManagerServiceTest extends TestCase {
	private $service;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mfm_test_options']          = array();
		$GLOBALS['mfm_test_current_user_id']  = 1;
		$GLOBALS['mfm_test_environment_type'] = 'staging';
		unset( $GLOBALS['mfm_test_expected_nonce'] );
		$this->service = new DB_Manager_Service();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mfm_test_options'], $GLOBALS['mfm_test_current_user_id'], $GLOBALS['mfm_test_environment_type'], $GLOBALS['mfm_test_expected_nonce'] );
		parent::tearDown();
	}

	public function test_generate_launch_url_contains_signed_values(): void {
		$url = $this->service->generate_launch_url();
		$this->assertStringContainsString( 'adminer-launch.php', $url );
		$this->assertStringContainsString( 'token=', $url );
		$this->assertStringContainsString( 'sig=', $url );
	}

	public function test_validate_launch_request_accepts_valid_signature_and_nonce(): void {
		$url = $this->service->generate_launch_url();
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );

		$this->assertTrue( $this->service->validate_launch_request( $query ) );
	}

	public function test_validate_launch_request_rejects_invalid_signature(): void {
		$url = $this->service->generate_launch_url();
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
		$query['sig'] = 'tampered';

		$this->assertFalse( $this->service->validate_launch_request( $query ) );
	}

	public function test_validate_launch_request_rejects_expired_token(): void {
		$this->assertSame( 60, DB_Manager_Service::sanitize_launch_ttl( 2 ) );
		$GLOBALS['mfm_test_options'][ DB_Manager_Service::OPTION_LAUNCH_TTL ] = 60;

		$url = $this->service->generate_launch_url();
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
		$query['ts'] = (string) ( time() - 1000 );

		$this->assertFalse( $this->service->validate_launch_request( $query ) );
	}

	public function test_option_sanitizers_apply_bounds(): void {
		$this->assertTrue( DB_Manager_Service::sanitize_enabled( '1' ) );
		$this->assertFalse( DB_Manager_Service::sanitize_read_only( '' ) );
		$this->assertSame( 60, DB_Manager_Service::sanitize_launch_ttl( -10 ) );
		$this->assertSame( 3600, DB_Manager_Service::sanitize_launch_ttl( 999999 ) );
		$this->assertSame( 300, DB_Manager_Service::sanitize_launch_ttl( 300 ) );
	}

	public function test_detect_environment_uses_host_heuristics(): void {
		$this->assertSame( 'staging', $this->service->detect_environment() );
	}
}
