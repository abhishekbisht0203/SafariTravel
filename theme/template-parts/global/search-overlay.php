<?php
/**
 * Full-screen search overlay.
 *
 * Progressive enhancement: the form is a real GET to ?s= so search works with
 * JavaScript disabled, and the JS runtime upgrades it to live suggestions
 * grouped by content type (plan §6.1).
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="search-overlay" id="safari-search-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Search', 'safari-travel'); ?>">
	<div class="container">

		<div class="search-overlay__head">
			<p class="search-overlay__brand"><?php esc_html_e('Search the site', 'safari-travel'); ?></p>
			<button type="button" class="icon-btn search-overlay__close" data-search-close>
				<?php safari_icon('close'); ?>
				<span class="visually-hidden"><?php esc_html_e('Close search', 'safari-travel'); ?></span>
			</button>
		</div>

		<form class="search-overlay__form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
			<label class="visually-hidden" for="safari-search-input"><?php esc_html_e('Search destinations, tours and guides', 'safari-travel'); ?></label>
			<?php safari_icon('search', ['class' => 'search-overlay__icon']); ?>
			<input
				type="search"
				id="safari-search-input"
				class="search-overlay__input"
				name="s"
				placeholder="<?php esc_attr_e('Where do you want to go?', 'safari-travel'); ?>"
				autocomplete="off"
				autocapitalize="off"
				spellcheck="false"
				enterkeyhint="search"
			>
			<button type="submit" class="btn btn--primary btn--sm">
				<?php esc_html_e('Search', 'safari-travel'); ?>
			</button>
		</form>

		<p class="search-overlay__hint">
			<?php esc_html_e('Press Enter to see all results · Esc to close', 'safari-travel'); ?>
		</p>

		<div class="search-overlay__results" aria-live="polite"></div>
	</div>
</div>
