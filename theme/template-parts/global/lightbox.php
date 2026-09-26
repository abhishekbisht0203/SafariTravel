<?php
/**
 * Shared lightbox for image galleries.
 *
 * Rendered once in the footer; the JS runtime binds it to every
 * `[data-lightbox]` figure on the page.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Image viewer', 'safari-travel'); ?>" aria-hidden="true">
	<button type="button" class="lightbox__close" aria-label="<?php esc_attr_e('Close image viewer', 'safari-travel'); ?>">
		<?php safari_icon('close'); ?>
	</button>
	<img class="lightbox__img" src="" alt="">
	<p class="lightbox__caption" hidden></p>
</div>
