<?php
/**
 * Attaches the theme's local photography to posts as featured images.
 *
 * The site deliberately does not fetch photography at seed time. Every image
 * lives in theme/assets/images/, committed to the repository and downloaded
 * once by `php scripts/safari.php images`. That keeps seeding fast, offline and
 * deterministic, and it means a fresh clone produces a complete-looking site
 * without reaching any third-party CDN.
 *
 * Importing through the media library (rather than referencing the theme files
 * directly) is what lets WordPress generate the derivative sizes, the srcset
 * and the width/height attributes that responsive images depend on.
 *
 * @package Safari_Core
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Featured-image management backed by theme/assets/images.
 */
final class Safari_Images {

	/**
	 * Meta key recording which local file an attachment came from.
	 *
	 * Deduplicated on it so re-seeding updates an existing attachment instead
	 * of creating a second copy of the same photograph.
	 */
	public const SOURCE_META = '_safari_local_image';

	/**
	 * Meta key recording the attachment ID on the post it was set for.
	 *
	 * Tours, events and guides inherit their destination's photograph, so the
	 * seeder needs to look the attachment up again by value.
	 */
	public const POST_SOURCE_META = '_seed_image_id';

	/**
	 * Memoised list of files that exist on disk, keyed by "dir/id".
	 *
	 * @var array<string,string>|null
	 */
	private static ?array $index = null;

	/**
	 * Attach the named local photograph to a post and make it the featured image.
	 *
	 * @param int    $post_id Post to attach to.
	 * @param string $slot    Manifest slot id, e.g. "maasai-mara".
	 * @param string $alt     Alt text. Falls back to the post title.
	 * @return int Attachment ID, or 0 when the slot has no file on disk.
	 */
	public static function attach( int $post_id, string $slot, string $alt = '' ): int {
		$file = self::locate( $slot );

		if ( '' === $file ) {
			return 0;
		}

		$attachment_id = self::find_imported( $slot );

		if ( 0 === $attachment_id ) {
			$attachment_id = self::import( $file, $slot, $alt );
		}

		if ( 0 === $attachment_id ) {
			return 0;
		}

		// Reuse the existing attachment but keep alt text current: a card with a
		// correct image and a filename as alt text is not finished work.
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, self::POST_SOURCE_META, $attachment_id );

		return $attachment_id;
	}

	/**
	 * Copy one post's featured image onto another.
	 *
	 * @param int $source_id Post holding the photograph.
	 * @param int $target_id Post to copy onto.
	 * @return int Attachment ID, or 0 when the source has no featured image.
	 */
	public static function inherit( int $source_id, int $target_id ): int {
		$attachment_id = (int) get_post_thumbnail_id( $source_id );

		if ( $attachment_id <= 0 ) {
			return 0;
		}

		set_post_thumbnail( $target_id, $attachment_id );
		update_post_meta( $target_id, self::POST_SOURCE_META, $attachment_id );

		return $attachment_id;
	}

	/**
	 * Find the attachment previously imported for a slot.
	 *
	 * @param string $slot Slot id.
	 * @return int Attachment ID, or 0.
	 */
	private static function find_imported( string $slot ): int {
		$found = get_posts(
			[
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => self::SOURCE_META,
				'meta_value'       => $slot,
			]
		);

		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Import a local file into the media library.
	 *
	 * @param string $file Absolute path to the image.
	 * @param string $slot Slot id, recorded for deduplication.
	 * @param string $alt  Alt text.
	 * @return int Attachment ID, or 0 on failure.
	 */
	private static function import( string $file, string $slot, string $alt ): int {
		// media_handle_sideload() moves the file, so it is handed a copy in a
		// temporary location rather than the theme's own copy.
		$temp = wp_tempnam( basename( $file ) );

		if ( ! $temp ) {
			return 0;
		}

		if ( ! copy( $file, $temp ) ) {
			wp_delete_file( $temp );

			return 0;
		}

		$attachment_id = media_handle_sideload(
			[
				'name'     => sanitize_file_name( $slot ) . '.jpg',
				'tmp_name' => $temp,
			],
			0,
			$alt
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temp );

			return 0;
		}

		update_post_meta( (int) $attachment_id, self::SOURCE_META, $slot );

		return (int) $attachment_id;
	}

	/**
	 * Absolute path to a slot's file on disk.
	 *
	 * @param string $slot Slot id.
	 * @return string Path, or an empty string when the file is absent.
	 */
	public static function locate( string $slot ): string {
		$index = self::index();

		return $index[ $slot ] ?? '';
	}

	/**
	 * Whether a slot has a file on disk.
	 *
	 * @param string $slot Slot id.
	 */
	public static function has( string $slot ): bool {
		return '' !== self::locate( $slot );
	}

	/**
	 * Map of slot id to absolute file path, for every image in the theme.
	 *
	 * @return array<string,string>
	 */
	private static function index(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}

		self::$index = [];

		$base = get_template_directory() . '/assets/images';

		if ( ! is_dir( $base ) ) {
			return self::$index;
		}

		foreach ( (array) glob( $base . '/*/*.jpg' ) as $file ) {
			$slot = basename( (string) $file, '.jpg' );

			// First match wins, so a slot in a more specific directory is not
			// shadowed by a same-named file elsewhere.
			if ( ! isset( self::$index[ $slot ] ) ) {
				self::$index[ $slot ] = (string) $file;
			}
		}

		return self::$index;
	}
}
