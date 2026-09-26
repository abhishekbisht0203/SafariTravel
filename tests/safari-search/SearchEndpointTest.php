<?php
/**
 * The safari-search REST endpoint.
 *
 * The theme's search overlay calls GET /wp-json/safari/v1/search and expects an
 * array of `{ type, label, items }` groups; the Laravel API proxies the same
 * endpoint. These tests pin the contract and the limits.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

namespace Safari\Tests\Safari_Search;

use Safari\Tests\WordPressTestCase;

final class SearchEndpointTest extends WordPressTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->loadPluginClass( 'plugins/safari-search/inc/class-safari-search-rest.php' );
	}

	public function test_it_publishes_under_a_stable_namespace_and_route(): void {
		// The theme hard-codes this path in theme/assets/src/js/shell.js, and
		// the API proxies it, so neither may drift.
		$this->assertSame( 'safari/v1', \Safari_Search_REST::NAMESPACE_V1 );
		$this->assertSame( '/search', \Safari_Search_REST::ROUTE );
	}

	public function test_it_declares_the_minimum_query_length(): void {
		// Two characters, matching the theme's client-side guard.
		$this->assertSame( 2, \Safari_Search_REST::MIN_QUERY_LENGTH );
	}

	public function test_it_caps_results_per_group(): void {
		$this->assertSame( 20, \Safari_Search_REST::MAX_PER_PAGE );
	}

	public function test_the_limit_is_below_what_a_single_request_could_exploit(): void {
		// A sanity check on the ceiling itself: a live search overlay should
		// never be allowed to ask WordPress for an unbounded result set.
		$this->assertLessThanOrEqual( 50, \Safari_Search_REST::MAX_PER_PAGE );
	}

	/**
	 * The post types the search covers are the ones safari-core registers and
	 * the ones the API content proxy is allowed to serve.
	 */
	public function test_the_searchable_post_types_match_the_registered_content(): void {
		$searchable = array( 'destination', 'tour', 'event', 'safari_guide', 'post', 'page' );

		foreach ( $searchable as $type ) {
			$core = 'post' === $type || 'page' === $type
				? true
				: str_contains( $this->readRepoFile( 'plugins/safari-core/inc/post-types.php' ), "'{$type}' =>" );

			$this->assertTrue( $core, sprintf( '"%s" is searchable but not registered by safari-core.', $type ) );
		}
	}

	public function test_the_api_proxy_allow_list_only_contains_registered_types(): void {
		$config = $this->readRepoFile( 'backend/config/safari.php' );

		$this->assertMatchesRegularExpression(
			"/'content_types' => \[(.*?)\]/s",
			$config,
			'backend/config/safari.php must declare content_types'
		);

		preg_match( "/'content_types' => \[(.*?)\]/s", $config, $block );
		preg_match_all( "/'([a-z_]+)'/", $block[1], $types );

		$registered = $this->readRepoFile( 'plugins/safari-core/inc/post-types.php' );

		foreach ( $types[1] as $type ) {
			if ( in_array( $type, array( 'post', 'page' ), true ) ) {
				continue;
			}

			$this->assertStringContainsString(
				"'{$type}' =>",
				$registered,
				sprintf( 'The API content proxy serves "%s", which safari-core does not register.', $type )
			);
		}
	}
}
