<?php
/**
 * The search form.
 *
 * @package Safari_Travel
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$safari_id = 'safari-search-' . wp_unique_id();
?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="visually-hidden" for="<?php echo esc_attr($safari_id); ?>">
        <?php esc_html_e('Search this site', 'safari-travel'); ?>
    </label>
    <input
        type="search"
        id="<?php echo esc_attr($safari_id); ?>"
        class="search-form__input"
        name="s"
        value="<?php echo esc_attr(get_search_query()); ?>"
        placeholder="<?php esc_attr_e('Search destinations, tours, guides…', 'safari-travel'); ?>"
        autocomplete="off"
        enterkeyhint="search"
    >
    <button type="submit" class="btn btn--primary">
        <?php safari_icon('search'); ?>
        <span class="visually-hidden"><?php esc_html_e('Search', 'safari-travel'); ?></span>
    </button>
</form>
