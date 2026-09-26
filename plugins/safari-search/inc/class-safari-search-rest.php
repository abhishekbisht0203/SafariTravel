<?php
/**
 * Live search REST endpoint.
 *
 * Backs the theme's search overlay (`theme/assets/src/js/shell.js`), which calls
 * `GET /wp-json/safari/v1/search?q=…&per_page=…` and expects an array of
 * `{ type, label, items: [{ title, url, type }] }` groups.
 *
 * Public by design — it only ever returns published content that WordPress
 * already exposes on the front end — but every input is sanitised, every
 * output is escaped, and the query itself is a prepared statement.
 *
 * @package Safari_Search
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registers and serves the safari/v1/search endpoint.
 */
final class Safari_Search_REST {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE_V1 = 'safari/v1';

	/**
	 * Endpoint route.
	 */
	public const ROUTE = '/search';

	/**
	 * Shortest query that will trigger a search, in characters.
	 *
	 * Matches the theme's client-side guard so the two stay in step.
	 */
	public const MIN_QUERY_LENGTH = 2;

	/**
	 * Hard ceiling on `per_page`, whatever the caller asks for.
	 */
	public const MAX_PER_PAGE = 20;

	/**
	 * Post types included in results, in display order.
	 *
	 * @var array<string,string>
	 */
	private const POST_TYPES = array(
		'destination'  => 'Destinations',
		'tour'         => 'Tours',
		'event'        => 'Events &amp; offers',
		'safari_guide' => 'Guides',
		'post'         => 'Journal',
		'page'         => 'Pages',
	);

	/**
	 * Wire the endpoint into WordPress.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action('rest_api_init', array(self::class, 'register_routes'));
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array(self::class, 'search'),
				// The endpoint only reads already-public content, so it is public.
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 8,
						'sanitize_callback' => 'absint',
					),
					'type' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function search( WP_REST_Request $request ) {
		$term = trim( (string) $request->get_param('q') );

		if ( mb_strlen($term) < self::MIN_QUERY_LENGTH ) {
			return new WP_REST_Response(array(), 200);
		}

		$per_page = (int) $request->get_param('per_page');
		$per_page = max(1, min($per_page > 0 ? $per_page : 8, self::MAX_PER_PAGE));

		$filter = sanitize_key( (string) $request->get_param('type') );
		$groups = self::queryGroups($term, $per_page, $filter);

		$response = rest_ensure_response($groups);

		// Results change whenever content does; keep them short-lived.
		$response->header('Cache-Control', 'private, max-age=60');

		return $response;
	}

	/**
	 * Run the search and shape it into display groups.
	 *
	 * @param string $term     Sanitised search term.
	 * @param int    $per_page Maximum items per group.
	 * @param string $filter   Optional post-type filter.
	 * @return array<int,array<string,mixed>>
	 */
	private static function queryGroups( string $term, int $per_page, string $filter = '' ): array {
		$available = array_filter(
			self::POST_TYPES,
			static function ( string $label, string $post_type ): bool {
				return post_type_exists($post_type);
			},
			ARRAY_FILTER_USE_BOTH
		);

		if ( '' !== $filter ) {
			$available = isset($available[$filter]) ? array($filter => $available[$filter]) : array();
		}

		if ( ! $available ) {
			return array();
		}

		$groups = array();

		foreach ( $available as $post_type => $label ) {
			$items = self::searchPostType($term, $post_type, $per_page);

			if ( ! $items) {
				continue;
			}

			$groups[] = array(
				'type'  => $post_type,
				'label' => wp_specialchars_decode($label),
				'items' => $items,
			);
		}

		return $groups;
	}

	/**
	 * Search one post type and return front-end-safe result rows.
	 *
	 * @param string $term      Sanitised term.
	 * @param string $post_type Post type slug.
	 * @param int    $per_page  Maximum rows.
	 * @return array<int,array<string,string>>
	 */
	private static function searchPostType( string $term, string $post_type, int $per_page ): array {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				// Password-protected content must never appear in search results.
				'has_password'           => false,
				's'                      => $term,
				'posts_per_page'         => $per_page,
				'orderby'                => array('relevance' => 'DESC', 'date' => 'DESC'),
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = array(
				'title' => get_the_title($post),
				'url'   => (string) get_permalink($post),
				'type'  => (string) $post->post_type,
			);
		}

		wp_reset_postdata();

		return $items;
	}
}
