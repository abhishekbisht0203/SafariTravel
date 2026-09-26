<?php
/**
 * The content model safari-core registers.
 *
 * WordPress is replaced with recording stubs, so the assertions run against the
 * real registration calls in plugins/safari-core. If a post type, its archive or
 * its REST exposure changes, a template or the API content proxy that depends on
 * it fails here first.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

namespace Safari\Tests\Safari_Core;

use Safari\Tests\WordPressTestCase;

final class ContentModelTest extends WordPressTestCase {

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private array $postTypes = array();

	/**
	 * @var array<string, array<int, string>>
	 */
	private array $taxonomies = array();

	/**
	 * @var array<int, callable>
	 */
	private array $initCallbacks = array();

	protected function setUp(): void {
		parent::setUp();

		$this->postTypes      = array();
		$this->taxonomies     = array();
		$this->initCallbacks  = array();

		\Brain\Monkey\Functions\when( 'register_post_type' )->alias(
			function ( $type, $args = array() ): void {
				$this->postTypes[ $type ] = $args;
			}
		);

		\Brain\Monkey\Functions\when( 'register_taxonomy' )->alias(
			function ( $taxonomy, $objectType, $args = array() ): void {
				$this->taxonomies[ $taxonomy ] = (array) $objectType;
			}
		);

		// Both registration files hook `init`. Capture the callbacks so the test
		// can run them: the real WordPress does exactly this.
		\Brain\Monkey\Functions\when( 'add_action' )->alias(
			function ( $hook, $callback ): bool {
				if ( 'init' === $hook ) {
					$this->initCallbacks[] = $callback;
				}

				return true;
			}
		);

		\Brain\Monkey\Functions\when( '__' )->alias( static fn ( $text, $domain = 'default' ): string => (string) $text );
		\Brain\Monkey\Functions\when( 'esc_html__' )->alias( static fn ( $text, $domain = 'default' ): string => (string) $text );
		\Brain\Monkey\Functions\when( '_x' )->alias( static fn ( $text, $context, $domain = 'default' ): string => (string) $text );

		$this->loadPluginFile( 'plugins/safari-core/inc/post-types.php' );
		$this->loadPluginFile( 'plugins/safari-core/inc/taxonomies.php' );

		foreach ( $this->initCallbacks as $callback ) {
			if ( is_callable( $callback ) ) {
				$callback();
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Post types
	|--------------------------------------------------------------------------
	*/

	public function test_it_registers_every_expected_post_type(): void {
		foreach ( array( 'destination', 'tour', 'event', 'safari_guide', 'faq', 'testimonial' ) as $type ) {
			$this->assertArrayHasKey( $type, $this->postTypes, sprintf( '"%s" is not registered.', $type ) );
		}
	}

	/**
	 * `has_archive` is `false` for the post types that deliberately have no
	 * archive (FAQ, testimonials), so the parameter is a string or a bool.
	 *
	 * @dataProvider archiveSlugs
	 */
	public function test_archive_slugs_are_stable( string $type, string|bool $archive, string $slug ): void {
		$this->assertSame( $archive, $this->postTypes[ $type ]['has_archive'] );
		$this->assertSame( $slug, $this->postTypes[ $type ]['rewrite']['slug'] );
	}

	/**
	 * @return array<string, array<int, string|bool>>
	 */
	public static function archiveSlugs(): array {
		return array(
			'destination' => array( 'destination', 'destinations', 'destinations' ),
			'tour'        => array( 'tour', 'tours', 'tours' ),
			'event'       => array( 'event', 'events', 'events' ),
			'safari_guide' => array( 'safari_guide', 'guides', 'guides' ),
			'faq'         => array( 'faq', false, 'faq' ),
			'testimonial' => array( 'testimonial', false, 'testimonials' ),
		);
	}

	/**
	 * The API content proxy reads these through the core REST API, so
	 * `show_in_rest` must stay on.
	 *
	 * @dataProvider allPostTypes
	 */
	public function test_every_post_type_is_exposed_to_the_rest_api( string $type ): void {
		$this->assertTrue(
			(bool) $this->postTypes[ $type ]['show_in_rest'],
			sprintf( '"%s" is not exposed to the REST API.', $type )
		);
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function allPostTypes(): array {
		return array(
			'destination'  => array( 'destination' ),
			'tour'         => array( 'tour' ),
			'event'        => array( 'event' ),
			'safari_guide' => array( 'safari_guide' ),
			'faq'          => array( 'faq' ),
			'testimonial'  => array( 'testimonial' ),
		);
	}

	public function test_destinations_and_tours_support_the_fields_the_theme_relies_on(): void {
		foreach ( array( 'destination', 'tour', 'event' ) as $type ) {
			$supports = (array) $this->postTypes[ $type ]['supports'];

			foreach ( array( 'title', 'editor', 'thumbnail', 'excerpt' ) as $feature ) {
				$this->assertContains( $feature, $supports, sprintf( '"%s" does not support %s.', $type, $feature ) );
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Taxonomies
	|--------------------------------------------------------------------------
	*/

	/**
	 * @dataProvider taxonomyTargets
	 */
	public function test_it_registers_a_taxonomy( string $taxonomy ): void {
		$this->assertArrayHasKey( $taxonomy, $this->taxonomies );
	}

	/**
	 * @dataProvider taxonomyTargets
	 */
	public function test_a_taxonomy_is_attached_to_its_post_types( string $taxonomy ): void {
		$body = $this->readRepoFile( 'plugins/safari-core/inc/taxonomies.php' );

		$this->assertSame( 1, preg_match(
			"/'" . preg_quote( $taxonomy, '/' ) . "'\s*=>\s*\[[^\]]*'objects'\s*=>\s*\[([^\]]*)\]/",
			$body,
			$matches
		), sprintf( 'Could not read the object types for "%s".', $taxonomy ) );

		preg_match_all( "/'([a-z_]+)'/", $matches[1], $objects );

		$expected = $this->expectedObjectsFor( $taxonomy );

		$this->assertSame( $expected, $objects[1] );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function taxonomyTargets(): array {
		return array(
			'region'         => array( 'region' ),
			'safari_type'    => array( 'safari_type' ),
			'travel_style'   => array( 'travel_style' ),
			'season'         => array( 'season' ),
			'event_type'     => array( 'event_type' ),
			'guide_topic'    => array( 'guide_topic' ),
			'faq_category'   => array( 'faq_category' ),
		);
	}

	/**
	 * The declared attachments, kept here explicitly so a change to
	 * taxonomies.php is a deliberate, visible edit rather than a silent one.
	 *
	 * @return list<string>
	 */
	private function expectedObjectsFor( string $taxonomy ): array {
		$map = array(
			'region'       => array( 'destination', 'tour', 'event' ),
			'safari_type'  => array( 'destination', 'tour' ),
			'travel_style' => array( 'tour', 'destination' ),
			'season'       => array( 'tour', 'destination', 'event' ),
			'event_type'   => array( 'event' ),
			'guide_topic'  => array( 'safari_guide' ),
			'faq_category' => array( 'faq' ),
		);

		$this->assertArrayHasKey( $taxonomy, $map );

		return $map[ $taxonomy ];
	}
}
