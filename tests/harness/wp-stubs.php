<?php
/**
 * Minimal WordPress stub harness.
 *
 * Enough of the WordPress API to render the theme's templates outside a real
 * install, so template logic can be smoke-tested in CI without a database.
 *
 * This is a test double, not a reimplementation. It returns plausible data and
 * records what was called; anything the templates rely on that is not stubbed
 * here will fail loudly, which is the point.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions, WordPress.Security, Squiz.Commenting

if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wp/');
}
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', dirname(__DIR__, 2) . '/');
}
if (! defined('SAFARI_THEME_DIR')) {
    define('SAFARI_THEME_DIR', dirname(__DIR__, 2) . '/theme/');
}
if (! defined('SAFARI_THEME_URI')) {
    define('SAFARI_THEME_URI', 'https://example.test/wp-content/themes/safari-theme/');
}
if (! defined('SAFARI_THEME_VERSION')) {
    define('SAFARI_THEME_VERSION', '1.0.0');
}

/* ---------------------------------------------------------------------------
 * Fixture state
 * ------------------------------------------------------------------------ */

$GLOBALS['wp_stub'] = [
    'posts'        => [],
    'post_types'   => [],
    'options'      => [],
    'theme_mods'   => [],
    'menus'        => [],
    'terms'        => [],
    'shortcodes'   => [],
    'actions'      => [],
    'notices'      => [],
    'enqueued'     => [],
    'images'       => [],
];

/**
 * Register a post fixture.
 *
 * @param int    $id   Post ID.
 * @param string $type Post type.
 * @param array  $meta Meta.
 * @param array  $args Extra.
 * @return array Post array.
 */
function wp_stub_post(int $id, string $type, array $meta = [], array $args = []): array
{
    $post = array_merge([
        'ID'          => $id,
        'post_type'   => $type,
        'post_title'  => $args['title'] ?? ('Sample ' . strtoupper($type) . ' ' . $id),
        'post_name'   => $args['slug'] ?? ('sample-' . $type . '-' . $id),
        'post_excerpt' => $args['excerpt'] ?? 'A short, honest summary of what this content is about and why it matters to a traveller deciding where to go next.',
        'post_content' => $args['content'] ?? '<p>Body copy.</p>',
        'post_status' => 'publish',
        'post_date'   => $args['date'] ?? '2025-03-14',
    ], $args);

    $GLOBALS['wp_stub']['posts'][ $id ] = $post;
    $GLOBALS['wp_stub']['post_types'][ $id ] = $type;

    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }

    return $post;
}

/* ---------------------------------------------------------------------------
 * Core
 * ------------------------------------------------------------------------ */

$GLOBALS['post'] = null;
$GLOBALS['wp_query'] = null;

function get_the_ID(): int
{
    return isset($GLOBALS['post']) ? (int) $GLOBALS['post']->ID : 0;
}

function is_singular($types = ''): bool
{
    return true;
}

function is_front_page(): bool
{
    return (bool) ($GLOBALS['wp_stub']['is_front'] ?? false);
}

function is_home(): bool
{
    return false;
}

function is_page(): bool
{
    return true;
}

function is_search(): bool
{
    return false;
}

function is_404(): bool
{
    return false;
}

function is_archive(): bool
{
    return false;
}

function is_post_type_archive($types = ''): bool
{
    return false;
}

function is_category(): bool
{
    return false;
}

function is_tag(): bool
{
    return false;
}

function is_tax(): bool
{
    return false;
}

function is_paged(): bool
{
    return false;
}

function is_admin(): bool
{
    return false;
}

function is_feed(): bool
{
    return false;
}

function is_post(): bool
{
    return (get_post_type() === 'post');
}

function is_singular_post(): bool
{
    return is_post();
}

function post_type_archive_title(string $prefix = '', bool $display = true)
{
    return 'Safaris';
}

function single_term_title(string $prefix = '', bool $display = true)
{
    return 'Term';
}

function get_queried_object_id(): int
{
    return get_the_ID();
}

function get_post_type($post = null): string
{
    if (is_object($post)) {
        return (string) $post->post_type;
    }
    $id = get_the_ID();
    return (string) ($GLOBALS['wp_stub']['post_types'][ $id ] ?? 'page');
}

function get_post_type_object(string $type)
{
    $map = [
        'destination'  => ['destinations', 'Destinations', true],
        'tour'         => ['tours', 'Tours', true],
        'event'        => ['events', 'Events & Offers', true],
        'safari_guide' => ['guides', 'Travel Guides', true],
    ];

    if (! isset($map[ $type ])) {
        return null;
    }

    return (object) [
        'name'        => $type,
        'labels'      => (object) ['name' => $map[ $type ][1], 'singular_name' => rtrim($map[ $type ][1], 's')],
        'has_archive' => $map[ $type ][2],
    ];
}

function get_post_type_archive_link($type)
{
    $object = get_post_type_object($type);
    return $object ? home_url('/' . $object->labels->name . '/') : false;
}

function get_permalink($post = 0): string
{
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    $id = $id ?: get_the_ID();
    $type = $GLOBALS['wp_stub']['post_types'][ $id ] ?? 'page';
    $slug = $GLOBALS['wp_stub']['posts'][ $id ]['post_name'] ?? $id;
    return home_url('/' . $type . '/' . $slug . '/');
}

function get_the_permalink($post = 0): string
{
    return get_permalink($post);
}

function the_permalink(): void
{
    echo esc_url(get_permalink());
}

function get_the_title($post = 0): string
{
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    $id = $id ?: get_the_ID();
    return (string) ($GLOBALS['wp_stub']['posts'][ $id ]['post_title'] ?? '');
}

function the_title(): void
{
    echo esc_html(get_the_title());
}

function get_the_excerpt($post = null): string
{
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    $id = $id ?: get_the_ID();
    return (string) ($GLOBALS['wp_stub']['posts'][ $id ]['post_excerpt'] ?? '');
}

function has_excerpt($post = null): bool
{
    return get_the_excerpt($post) !== '';
}

function get_the_excerpt_lite(): string
{
    return get_the_excerpt();
}

function get_the_content($more_link_text = null, $strip_teaser = false, $post = null)
{
    $id = get_the_ID();
    return (string) ($GLOBALS['wp_stub']['posts'][ $id ]['post_content'] ?? '');
}

function the_content($more_link_text = null, $strip_teaser = false): void
{
    echo wp_kses_post(get_the_content());
}

function get_the_date($format = '', $post = null): string
{
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    $id = $id ?: get_the_ID();
    $date = (string) ($GLOBALS['wp_stub']['posts'][ $id ]['post_date'] ?? '2025-03-14');
    return $format ? date($format, strtotime($date)) : date('j F Y', strtotime($date));
}

function get_post_field(string $field, $post = null)
{
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    $id = $id ?: get_the_ID();
    return $GLOBALS['wp_stub']['posts'][ $id ][ $field ] ?? '';
}

function get_post_ancestors($post): array
{
    return [];
}

function get_post($post = null)
{
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    $id = $id ?: get_the_ID();
    return isset($GLOBALS['wp_stub']['posts'][ $id ] ) ? (object) $GLOBALS['wp_stub']['posts'][ $id ] : null;
}

function get_post_type_object_or_null($type)
{
    return get_post_type_object($type);
}

function get_nav_menu_locations(): array
{
    return [];
}

function has_nav_menu(string $location): bool
{
    return false;
}

function wp_get_nav_menu_object($location)
{
    return null;
}

function wp_nav_menu(array $args = []): void
{
    echo '<ul class="' . esc_attr($args['menu_class'] ?? 'menu') . '"></ul>';
}

function get_page_by_path(string $path, $output = OBJECT, $type = 'page')
{
    return $GLOBALS['wp_stub']['pages'][ $path ] ?? null;
}

function paginate_links(array $args = []): string
{
    return '<a class="page-numbers current">1</a>';
}

/* ---------------------------------------------------------------------------
 * Escaping & sanitising
 * ------------------------------------------------------------------------ */

function esc_html($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url): string
{
    return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
}

function esc_url_raw($url): string
{
    return (string) $url;
}

function esc_textarea($text): string
{
    return esc_html($text);
}

function wp_kses_post($content): string
{
    return (string) $content;
}

function wp_kses($content, $allowed = []): string
{
    return (string) $content;
}

function wp_strip_all_tags($text, $remove_breaks = false): string
{
    $text = strip_tags((string) $text);
    return $remove_breaks ? trim(preg_replace('/[\r\n\t ]+/', ' ', $text)) : $text;
}

function sanitize_text_field($text): string
{
    return trim(strip_tags((string) $text));
}

function sanitize_textarea_field($text): string
{
    return trim(strip_tags((string) $text));
}

function sanitize_email($email): string
{
    return trim((string) $email);
}

function sanitize_html_class($class): string
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class);
}

function sanitize_title($title): string
{
    $title = strtolower(trim((string) $title));
    $title = preg_replace('/[^a-z0-9]+/', '-', $title);
    return trim((string) $title, '-');
}

function is_email($email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function untrailingslashit($string): string
{
    return rtrim((string) $string, '/\\');
}

function trailingslashit($string): string
{
    return untrailingslashit($string) . '/';
}

function wpautop($content, $br = true): string
{
    return '<p>' . str_replace("\n\n", '</p><p>', (string) $content) . '</p>';
}

function checked($checked, $current = true, $display = true): string
{
    return (string) $checked === (string) $current ? ' checked="checked"' : '';
}

function selected($selected, $current = true, $display = true): string
{
    return (string) $selected === (string) $current ? ' selected="selected"' : '';
}

function number_format_i18n($number, $decimals = 0): string
{
    return number_format((float) $number, (int) $decimals);
}

function date_i18n($format, $timestamp = null, $gmt = false): string
{
    return gmdate($format, $timestamp ?: time());
}

function current_time(string $type = 'mysql', $gmt = 0)
{
    return 'timestamp' === $type ? time() : gmdate('Y-m-d H:i:s');
}

function get_option(string $name, $default = false)
{
    return array_key_exists($name, $GLOBALS['wp_stub']['options']) ? $GLOBALS['wp_stub']['options'][ $name ] : $default;
}

function update_option(string $name, $value, $autoload = null): bool
{
    $GLOBALS['wp_stub']['options'][ $name ] = $value;
    return true;
}

function delete_option(string $name): bool
{
    unset($GLOBALS['wp_stub']['options'][ $name ]);
    return true;
}

function add_option(string $name, $value = '', $deprecated = '', $autoload = 'yes'): bool
{
    return update_option($name, $value);
}

function get_theme_mod(string $name, $default = false)
{
    return array_key_exists($name, $GLOBALS['wp_stub']['theme_mods']) ? $GLOBALS['wp_stub']['theme_mods'][ $name ] : $default;
}

function set_theme_mod(string $name, $value): void
{
    $GLOBALS['wp_stub']['theme_mods'][ $name ] = $value;
}

function has_custom_logo(): bool
{
    return false;
}

function get_theme_mod(string $name, $default = false)
{
    return array_key_exists($name, $GLOBALS['wp_stub']['theme_mods']) ? $GLOBALS['wp_stub']['theme_mods'][ $name ] : $default;
}

function bloginfo(string $show = ''): void
{
    echo esc_html(bloginfo_raw($show));
}

function bloginfo_raw(string $show = ''): string
{
    return match ($show) {
        'name'        => 'Safari Travel',
        'description' => 'Tailor-made African safaris',
        'charset'     => 'UTF-8',
        default       => '',
    };
}

function language_attributes(): void
{
    echo 'lang="en-GB"';
}

function get_bloginfo(string $show = ''): string
{
    return bloginfo_raw($show);
}

function home_url(string $path = '', $scheme = null): string
{
    return 'https://example.test' . ($path ? '/' . ltrim($path, '/') : '/');
}

function site_url(string $path = '', $scheme = null): string
{
    return home_url($path);
}

function admin_url(string $path = '', $scheme = 'admin'): string
{
    return home_url('wp-admin/' . $path);
}

function rest_url(string $path = '', $scheme = 'rest'): string
{
    return home_url('wp-json/' . ltrim($path, '/'));
}

function get_search_query(): string
{
    return (string) ($_GET['s'] ?? '');
}

function body_class($class = ''): void
{
    $classes = is_array($class) ? $class : preg_split('/\s+/', (string) $class);
    echo 'class="' . esc_attr(trim('js ' . implode(' ', array_filter($classes)))) . '"';
}

function post_class($class = '', $post = null): void
{
    $classes = is_array($class) ? $class : preg_split('/\s+/', (string) $class);
    $id = is_object($post) ? (int) $post->ID : get_the_ID();
    echo 'class="' . esc_attr(trim('post-' . $GLOBALS['wp_stub']['post_types'][ $id ] . ' ' . implode(' ', array_filter($classes)))) . '"';
}

function get_post_class($class = '', $post = null): array
{
    return array_filter((array) $class);
}

function the_tags($before = '', $sep = ', ', $after = ''): void
{
    echo $before . 'Safari' . $after;
}

function has_tag($tag = ''): bool
{
    return false;
}

function wp_link_pages(array $args = []): void
{
}

function wp_trim_words($text, $num_words = 55, $more = null): string
{
    $words = preg_split('/\s+/', trim(wp_strip_all_tags((string) $text)));
    if (count($words) <= $num_words) {
        return implode(' ', $words);
    }
    return implode(' ', array_slice($words, 0, $num_words)) . $more;
}

function wp_make_link_relative($link): string
{
    return (string) $link;
}

function get_post_meta(int $id, string $key = '', bool $single = false)
{
    return $GLOBALS['wp_stub']['post_meta'][ $id ][ $key ] ?? ($single ? '' : []);
}

function update_post_meta(int $id, string $key, $value): bool
{
    $GLOBALS['wp_stub']['post_meta'][ $id ][ $key ] = $value;
    return true;
}

function delete_post_meta(int $id, string $key): bool
{
    unset($GLOBALS['wp_stub']['post_meta'][ $id ][ $key ]);
    return true;
}

function get_field(string $name, $post = null)
{
    return null; // ACF not installed in the harness.
}

function get_terms(array $args = [])
{
    return [];
}

function get_term_by(string $field, $value, string $taxonomy)
{
    return false;
}

function get_the_terms(int $id, string $taxonomy)
{
    $terms = $GLOBALS['wp_stub']['post_terms'][ $id ][ $taxonomy ] ?? [];
    return $terms ? array_map(static fn (string $t): object => (object) ['name' => $t, 'slug' => sanitize_title($t), 'count' => 3, 'term_id' => crc32($t)], $terms) : false;
}

function wp_get_object_terms($id, $taxonomy, $args = [])
{
    return $GLOBALS['wp_stub']['post_terms'][ $id ][ $taxonomy ] ?? [];
}

function wp_set_object_terms($id, $terms, $taxonomy, $append = false)
{
    $GLOBALS['wp_stub']['post_terms'][ $id ][ $taxonomy ] = (array) $terms;
    return $terms;
}

function get_post_thumbnail_id($post = null): int
{
    $id = is_object($post) ? (int) $post->ID : (int) $post;
    $id = $id ?: get_the_ID();
    return (int) ($GLOBALS['wp_stub']['thumbs'][ $id ] ?? 0);
}

function has_post_thumbnail($post = null): bool
{
    return get_post_thumbnail_id($post) > 0;
}

function get_the_post_thumbnail($id = null, $size = 'post-thumbnail', $attr = '')
{
    $out = '<img src="' . esc_url(SAFARI_THEME_URI . 'assets/dist/hero.jpg') . '" alt=""';
    foreach ((array) $attr as $k => $v) {
        $out .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
    }
    return $out . '>';
}

function the_post_thumbnail($size = 'post-thumbnail', $attr = ''): void
{
    $id = get_the_ID();
    echo get_the_post_thumbnail($id, $size, $attr);
}

function wp_get_attachment_image($id, $size = 'thumbnail', $icon = false, $attr = '')
{
    $out = '<img src="' . esc_url(SAFARI_THEME_URI . 'assets/dist/attachment.jpg') . '" alt=""';
    foreach ((array) $attr as $k => $v) {
        $out .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
    }
    return $out . '>';
}

function wp_get_attachment_image_url($id, $size = 'thumbnail', $icon = false)
{
    return SAFARI_THEME_URI . 'assets/dist/attachment.jpg';
}

function wp_get_attachment_image_src($id, $size = 'thumbnail', $icon = false)
{
    return [SAFARI_THEME_URI . 'assets/dist/hero.jpg', 1920, 1080, false];
}

function wp_get_attachment_image_srcset($id, $size = 'thumbnail')
{
    return SAFARI_THEME_URI . 'assets/dist/hero-600.jpg 600w, ' . SAFARI_THEME_URI . 'assets/dist/hero-1200.jpg 1200w';
}

/* ---------------------------------------------------------------------------
 * Queries & loops
 * ------------------------------------------------------------------------ */

class WP_Query
{
    /** @var array */
    public array $posts = [];
    /** @var int */
    public int $found_posts = 0;
    /** @var int */
    public int $current_post = -1;
    /** @var array */
    private array $args;

    public function __construct(array $args = [])
    {
        $this->args = $args;

        $type = $args['post_type'] ?? 'post';
        $limit = (int) ($args['posts_per_page'] ?? 3);

        $pool = array_values(array_filter(
            $GLOBALS['wp_stub']['post_types'],
            static fn (string $t): bool => $t === $type
        ));

        $ids = array_slice($pool, 0, $limit);

        foreach ($ids as $id) {
            $this->posts[] = (object) $GLOBALS['wp_stub']['posts'][ $id ];
        }

        $this->found_posts = count($pool);
    }

    public function have_posts(): bool
    {
        return $this->current_post + 1 < count($this->posts);
    }

    public function the_post(): void
    {
        $this->current_post++;
        $post = $this->posts[ $this->current_post ];
        $GLOBALS['post'] = $post;
        setup_postdata($post);
    }
}

function have_posts(): bool
{
    return $GLOBALS['wp_query']->have_posts();
}

function the_post(): void
{
    $GLOBALS['wp_query']->the_post();
}

function setup_postdata($post): void
{
}

function wp_reset_postdata(): void
{
}

function get_posts(array $args = [])
{
    return (new WP_Query($args))->posts;
}

/* ---------------------------------------------------------------------------
 * Hooks (no-ops that record)
 * ------------------------------------------------------------------------ */

function add_action(string $hook, $callback, int $priority = 10, int $args = 1): bool
{
    $GLOBALS['wp_stub']['actions'][ $hook ][] = $callback;
    return true;
}

function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): bool
{
    $GLOBALS['wp_stub']['actions'][ $hook ][] = $callback;
    return true;
}

function remove_action(string $hook, $callback, int $priority = 10): bool
{
    return true;
}

function remove_filter(string $hook, $callback, int $priority = 10): bool
{
    return true;
}

function do_action(string $hook, ...$args): void
{
}

function apply_filters(string $hook, $value, ...$args)
{
    return $value;
}

function has_action(string $hook, $callback = false): bool
{
    return false;
}

function has_filter(string $hook, $callback = false): bool
{
    return false;
}

function register_activation_hook(string $file, $callback): void
{
}

function register_deactivation_hook(string $file, $callback): void
{
}

function add_theme_support(string $feature, ...$args): void
{
}

function add_image_size(string $name, int $width = 0, int $height = 0, $crop = false): void
{
}

function load_theme_textdomain(string $domain, string $path = ''): bool
{
    return true;
}

function register_nav_menus(array $locations): void
{
}

function register_post_type($type, $args = []): void
{
}

function register_taxonomy($taxonomy, $object_type, $args = []): void
{
}

function add_role($role, $name, $caps = []): void
{
}

function get_role($role)
{
    return null;
}

function get_post_type_object_or_false($type)
{
    return get_post_type_object($type);
}

/* ---------------------------------------------------------------------------
 * Assets
 * ------------------------------------------------------------------------ */

function wp_enqueue_style(string $handle, $src = '', $deps = [], $ver = false, $media = 'all'): void
{
    $GLOBALS['wp_stub']['enqueued'][] = 'style:' . $handle;
}

function wp_enqueue_script(string $handle, $src = '', $deps = [], $ver = false, $args = []): void
{
    $GLOBALS['wp_stub']['enqueued'][] = 'script:' . $handle;
}

function wp_register_style(string $handle, $src = '', $deps = [], $ver = false, $media = 'all'): void
{
}

function wp_register_script(string $handle, $src = '', $deps = [], $ver = false, $args = []): void
{
}

function wp_script_add_data(string $handle, string $key, $value): bool
{
    return true;
}

function wp_dequeue_style(string $handle): void
{
}

function wp_dequeue_script(string $handle): void
{
}

function wp_head(): void
{
}

function wp_footer(): void
{
}

function wp_body_open(): void
{
}

function wp_json_encode($data, $options = 0, $depth = 512)
{
    return json_encode($data, (int) $options);
}

function shortcode_exists(string $tag): bool
{
    return isset($GLOBALS['wp_stub']['shortcodes'][ $tag ]);
}

function do_shortcode(string $content): string
{
    return $content;
}

function wp_unique_id(string $prefix = ''): string
{
    static $i = 0;
    return $prefix . (++$i);
}

function get_post_thumbnail_id_or_zero($post = null): int
{
    return get_post_thumbnail_id($post);
}

function file_exists_stub(string $path): bool
{
    return file_exists($path);
}

function filemtime_stub(string $path): int
{
    return (int) filemtime($path);
}

function _n(string $single, string $plural, int $number, string $domain = 'default'): string
{
    return 1 === (int) $number ? $single : $plural;
}

function __($text, $domain = 'default'): string
{
    return $text;
}

function _e($text, $domain = 'default'): void
{
    echo $text;
}

function _x($text, $context, $domain = 'default'): string
{
    return $text;
}

function esc_html__($text, $domain = 'default'): string
{
    return esc_html($text);
}

function esc_attr__($text, $domain = 'default'): string
{
    return esc_attr($text);
}

function esc_html_e($text, $domain = 'default'): void
{
    echo esc_html($text);
}

function esc_attr_e($text, $domain = 'default'): void
{
    echo esc_attr($text);
}

function is_wp_error($thing): bool
{
    return $thing instanceof WP_Error;
}

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '')
    {
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_data()
    {
        return null;
    }
}

class WP_Term
{
    public function __construct(
        public string $name = '',
        public string $slug = '',
        public int $term_id = 0,
        public int $count = 0
    ) {
    }
}
