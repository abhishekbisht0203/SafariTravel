<?php
/**
 * Demo content seeder.
 *
 * `wp safari seed` populates a fresh install with realistic destinations,
 * tours, offers, guides, FAQs and testimonials so every template renders as it
 * will in production. Images are pulled from Unsplash and sideloaded into the
 * media library, so the result is a self-contained staging site.
 *
 * Safe to re-run: content is matched on a stable slug and updated, not
 * duplicated. Pass --force to delete the previously seeded content first.
 *
 * @package Safari_Core
 */

declare(strict_types=1);

if (! defined('WP_CLI') || ! WP_CLI) {
    return;
}

/**
 * Seed the site with demo content.
 *
 * ## OPTIONS
 *
 * [--force]
 * : Delete previously seeded content before running.
 *
 * [--images]
 * : Sideload images from Unsplash (the default). Pass --no-images to skip it,
 * which is much faster and is what CI should use.
 *
 * [--posts-per-destination=<n>]
 * : How many tours to create per destination.
 *
 * ## EXAMPLES
 *
 *     wp safari seed
 *     wp safari seed --force --no-images
 *
 * @package Safari_Core
 */
class Safari_Seed_Command
{
    /**
     * Seed the site.
     *
     * ## OPTIONS
     *
     * [--force]
     * : Delete previously seeded content before running.
     *
     * [--images]
     * : Sideload images from Unsplash. Use --no-images to skip.
     *
     * [--posts-per-destination=<n>]
     * : Tours to create per destination.
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $force     = (bool) ($assoc_args['force'] ?? false);
        $no_images = array_key_exists('images', $assoc_args) ? ! $assoc_args['images'] : false;
        $per_dest  = max(1, (int) ($assoc_args['posts-per-destination'] ?? 2));

        WP_CLI::log('Seeding Safari Travel demo content…');

        if ($force) {
            $this->purge();
        }

        $this->set_options();
        $this->create_pages();
        $this->create_menus();
        $taxonomy_ids = $this->create_taxonomies();

        $destinations = $this->create_destinations($taxonomy_ids, $no_images);
        $tours        = $this->create_tours($destinations, $taxonomy_ids, $per_dest, $no_images);
        $this->create_events($destinations, $taxonomy_ids, $no_images);
        $this->create_guides($destinations, $taxonomy_ids, $no_images);
        $this->create_faqs($taxonomy_ids);
        $this->create_testimonials();

        WP_CLI::success(
            sprintf(
                'Done. %d destinations, %d tours, %d events, %d guides, %d FAQs, %d testimonials.',
                count($destinations),
                count($tours),
                count($this->event_slugs ?? []),
                count($this->guide_slugs ?? []),
                count($this->faq_slugs ?? []),
                count($this->testimonial_slugs ?? [])
            )
        );
    }

    /**
     * Recorded slugs, for the summary line.
     *
     * @var array<int,string>
     */
    private array $event_slugs = [];

    /** @var array<int,string> */
    private array $guide_slugs = [];

    /** @var array<int,string> */
    private array $faq_slugs = [];

    /** @var array<int,string> */
    private array $testimonial_slugs = [];

    /* ======================================================================
     * Pruning
     * =================================================================== */

    /**
     * Delete everything this command previously created.
     */
    private function purge(): void
    {
        $slugs = $this->seeded_slugs();

        foreach ($slugs as $type => $list) {
            foreach ($list as $slug) {
                $existing = get_page_by_path($slug, OBJECT, $type);
                if ($existing) {
                    wp_delete_post((int) $existing->ID, true);
                }
            }
        }

        // ACF fields live outside posts.
        foreach (['destination', 'tour', 'event', 'testimonial', 'faq'] as $type) {
            foreach (get_posts([
                'post_type'      => $type,
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => '_safari_seeded',
            ]) as $id) {
                wp_delete_post((int) $id, true);
            }
        }

        WP_CLI::log('  · Removed previously seeded content.');
    }

    /**
     * All slugs this command owns.
     *
     * @return array<string,array<int,string>> Post type to slugs.
     */
    private function seeded_slugs(): array
    {
        $pages = [
            'home',
            'about-us',
            'contact',
            'faq',
            'plan-my-safari',
            'thank-you',
            'privacy-policy',
            'terms',
            'cookie-policy',
        ];

        return [
            'page'         => $pages,
            'safari_guide' => ['mara-migration-guide', 'visa-requirements', 'safari-photography-tips', 'what-to-pack-kenya'],
        ];
    }

    /* ======================================================================
     * Site configuration
     * =================================================================== */

    /**
     * Set the options the theme reads, so the front end is fully configured.
     */
    private function set_options(): void
    {
        update_option('blogdescription', 'Tailor-made African safaris');
        update_option('timezone_string', 'Africa/Nairobi');

        update_option('safari_hero_subtitle', 'Tailor-made safaris built by specialists who have walked the ground. Small groups, private reserves, and itineraries shaped around what you actually want to see.');

        // Site Settings (ACF options page reads these too).
        update_option('safari_header_cta_text', 'Plan My Safari');
        update_option('safari_header_cta_url', '/plan-my-safari/');
        update_option('safari_phone', '+254 700 000 000');
        update_option('safari_whatsapp', '+254700000000');
        update_option('safari_announcement_enabled', 1);
        update_option('safari_announcement_text', 'Peak season bookings for July–October are filling — ask us about shoulder-season pricing.');
        update_option('safari_footer_note', 'Licensed tour operator and bonded member of the Kenya Tourist Board. Tailor-made safaris across East and Southern Africa since 2009.');

        // Social profiles (read as safari_social_1..5).
        $socials = [
            'https://facebook.com/',
            'https://instagram.com/',
            'https://wa.me/254700000000',
            'https://youtube.com/',
            'https://linkedin.com/',
        ];
        foreach ($socials as $index => $url) {
            set_theme_mod('safari_social_' . ($index + 1), $url);
        }

        // Front-page hero copy override.
        update_option('safari_hero_post_id', 0);

        WP_CLI::log('  · Site options configured.');
    }

    /* ======================================================================
     * Pages, menus, taxonomies
     * =================================================================== */

    /**
     * Create the pages in the plan.md §4.3 sitemap.
     *
     * @return array<string,int> Slug to post ID.
     */
    private function create_pages(): array
    {
        $front_id = (int) get_option('page_on_front');

        $pages = [
            'home'            => [
                'title' => 'Home',
                'content' => '',
                'front'  => true,
            ],
            'plan-my-safari'  => [
                'title'    => 'Plan My Safari',
                'template' => 'page-templates/template-plan-my-safari.php',
                'lead'     => 'Three short steps. A named safari specialist reads it, checks the camps and availability for your dates, and sends back a written itinerary with a firm price — usually within one business day, and always at no cost.',
            ],
            'contact'         => [
                'title'    => 'Contact Us',
                'template' => 'page-templates/template-contact.php',
                'lead'     => 'Questions about a route, a visa, a season, or whether your dates will work? Ask. There is no such thing as a silly question before a safari.',
            ],
            'faq'             => [
                'title'    => 'Frequently Asked Questions',
                'template' => 'page-templates/template-faq.php',
                'lead'     => 'If the answer is not here, ask us directly — we would rather answer a question than have you guess.',
            ],
            'thank-you'       => [
                'title'    => 'Thank You',
                'template' => 'page-templates/template-thank-you.php',
                'lead'     => 'Thank you. Your message is with our team and a named safari specialist will come back to you within one business day — usually much sooner.',
            ],
            'about-us'        => [
                'title' => 'About Safari Travel',
                'content' => '<h2>Sixteen years on the ground</h2>'
                    . '<p>We started in 2009 with one vehicle, one guide and a stubborn belief that safaris should be built for the people travelling them, not the other way round. Sixteen years later we run safaris across nine countries — still with the same principle: a specialist reads every enquiry personally, and nobody travels on an itinerary we have not walked.</p>'
                    . '<h2>Who we are</h2>'
                    . '<p>Our team is made up of guides, camp managers, wildlife researchers and one very patient logistics coordinator. Between us we have probably slept in every camp we sell. That is not a boast — it is the reason we can tell you honestly which ones are worth the transfer.</p>'
                    . '<h2>How we work</h2>'
                    . '<ul><li>Maximum six guests per vehicle. No exceptions, no seventh seat.</li>'
                    . '<li>One written proposal, itemised, with park fees shown separately.</li>'
                    . '<li>Free to refine as many times as you need before you commit.</li>'
                    . '<li>A WhatsApp line to a real person in-region, at any hour.</li></ul>',
            ],
            'privacy-policy'  => [
                'title'    => 'Privacy Policy',
                'template' => 'page-templates/template-no-title.php',
                'content'  => '<p>This policy explains what personal data we collect through this website, why we collect it, and what we do with it.</p>'
                    . '<h2>What we collect</h2>'
                    . '<p>When you submit an enquiry we collect the details you provide: your name, email address, phone number, intended travel dates, party size, budget range and anything you write in the message field. We also record which page the enquiry came from, and any campaign parameters present in the link you arrived through.</p>'
                    . '<h2>What we do not collect</h2>'
                    . '<p>We do not store your full IP address. Where spam prevention requires it, we store a one-way salted hash of it, which cannot be reversed.</p>'
                    . '<h2>How long we keep it</h2>'
                    . '<p>Enquiries that do not become bookings are deleted or anonymised on a rolling basis after the retention period set in our internal settings. Booking-related correspondence is retained for the period required by Kenyan tax and tourism record-keeping law.</p>'
                    . '<h2>Your rights</h2>'
                    . '<p>You can ask us to export or erase your data at any time by emailing us. We respond to such requests within 30 days.</p>',
            ],
            'terms'           => [
                'title'    => 'Terms & Conditions',
                'template' => 'page-templates/template-no-title.php',
                'content'  => '<h1>Terms & Conditions</h1>'
                    . '<h2>1. These terms</h2>'
                    . '<p>By booking with Safari Travel you accept these terms. They apply to every traveller named on a booking confirmation.</p>'
                    . '<h2>2. Pricing</h2>'
                    . '<p>Prices are quoted per person sharing and are itemised. Park entry fees, conservancy levies, visas and international flights are shown separately where applicable, and are payable directly unless stated otherwise.</p>'
                    . '<h2>3. Deposits and cancellation</h2>'
                    . '<p>A deposit confirms your booking. Cancellation and amendment terms for each booking are stated on that booking\'s confirmation and vary by supplier. Your safari specialist will walk you through them before you pay anything.</p>'
                    . '<h2>4. Travel documents</h2>'
                    . '<p>It is your responsibility to hold valid passports, visas and any required health documentation for the duration of your trip.</p>'
                    . '<h2>5. Wildlife and safety</h2>'
                    . '<p>Safari travel carries inherent risk. You must follow the instructions of your guide and camp staff at all times. We are not liable for behaviour outside those instructions.</p>'
                    . '<h2>6. Changes to the itinerary</h2>'
                    . '<p>We may modify an itinerary where weather, park regulations, or supplier circumstances make the original unsafe or impossible. Where a material change is required we will contact you with alternatives.</p>',
            ],
            'cookie-policy'   => [
                'title'    => 'Cookie Policy',
                'template' => 'page-templates/template-no-title.php',
                'content'  => '<h1>Cookie Policy</h1>'
                    . '<h2>What cookies are</h2>'
                    . '<p>Cookies are small files a website stores on your device. They let a site remember things between page loads.</p>'
                    . '<h2>What we use</h2>'
                    . '<ul><li><strong>Essential</strong> — required for the site to function, e.g. keeping you signed in and protecting forms from spam. These cannot be switched off.</li>'
                    . '<li><strong>Attribution</strong> — a first-party cookie recording which campaign brought you here, so we can attribute enquiries correctly. Set only when you arrive through a tracked link.</li>'
                    . '<li><strong>Analytics</strong> — optional, set only with your consent, and used in aggregate to understand which pages are useful.</li></ul>'
                    . '<h2>Managing cookies</h2>'
                    . '<p>You can clear or block cookies in your browser settings at any time. Blocking essential cookies will prevent the enquiry form from working.</p>',
            ],
        ];

        $ids = [];

        foreach ($pages as $slug => $config) {
            $existing = get_page_by_path($slug);

            $postarr = [
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => $config['title'],
                'post_name'    => $slug,
                'post_content' => $config['content'] ?? '',
                'post_excerpt' => $config['lead'] ?? '',
                'meta_input'   => [
                    '_safari_seeded' => '1',
                ],
            ];

            if (! empty($config['template'])) {
                $postarr['_wp_page_template'] = $config['template'];
            }
            if (! empty($config['lead'])) {
                $postarr['meta_input']['safari_page_lead'] = $config['lead'];
            }

            if ($existing instanceof WP_Post) {
                // wp_update_post() keys off the ID, so it must be set explicitly.
                $postarr['ID'] = (int) $existing->ID;
                $id            = wp_update_post($postarr, true);
            } else {
                $id = wp_insert_post($postarr, true);
            }

            if (is_wp_error($id) || ! $id) {
                WP_CLI::warning(
                    'Could not create page ' . $slug . ': '
                    . (is_wp_error($id) ? $id->get_error_message() : 'unknown error')
                );
                continue;
            }

            $ids[ $slug ] = (int) $id;

            if (! empty($config['front'])) {
                $front_id = (int) $id;
            }
        }

        // Point the front page at Home and the permalinks at the pretty structure.
        update_option('show_on_front', 'page');
        update_option('page_on_front', $front_id);
        update_option('permalink_structure', '/%postname%/');

        WP_CLI::log('  · Created ' . count($ids) . ' pages.');

        return $ids;
    }

    /**
     * Build the primary and footer menus and assign them to their locations.
     */
    private function create_menus(): void
    {
        $locations = get_theme_mod('nav_menu_locations', []);

        // Primary.
        $primary_name = 'Primary Menu';
        $primary = wp_get_nav_menu_object($primary_name);
        if (! $primary) {
            $primary_id = wp_create_nav_menu($primary_name);
        } else {
            $primary_id = (int) $primary->term_id;
        }

        // Clear and rebuild so re-running is idempotent.
        foreach (wp_get_nav_menu_items($primary_id) ?: [] as $item) {
            wp_delete_post((int) $item->ID, true);
        }

        $this->add_post_type_item($primary_id, 'destination');
        $this->add_post_type_item($primary_id, 'tour');
        $this->add_post_type_item($primary_id, 'event');
        $this->add_post_type_item($primary_id, 'safari_guide');

        // Support, with children.
        $contact = get_page_by_path('contact');
        $faq     = get_page_by_path('faq');
        $plan    = get_page_by_path('plan-my-safari');

        if ($contact || $faq || $plan) {
            $support_id = wp_update_nav_menu_item($primary_id, 0, [
                'menu-item-title'  => 'Support',
                'menu-item-url'    => $contact ? (string) get_permalink($contact) : home_url('/contact/'),
                'menu-item-status' => 'publish',
            ]);

            foreach ([$plan, $contact, $faq] as $child) {
                if (! $child) {
                    continue;
                }
                wp_update_nav_menu_item($primary_id, (int) $support_id, [
                    'menu-item-title'     => get_the_title($child),
                    'menu-item-object'    => 'page',
                    'menu-item-object-id' => (int) $child->ID,
                    'menu-item-type'      => 'post_type',
                    'menu-item-status'    => 'publish',
                ]);
            }
        }

        $locations['primary'] = $primary_id;

        // Footer.
        $footer_name = 'Footer Menu';
        $footer = wp_get_nav_menu_object($footer_name);
        $footer_id = $footer ? (int) $footer->term_id : (int) wp_create_nav_menu($footer_name);

        foreach (wp_get_nav_menu_items($footer_id) ?: [] as $item) {
            wp_delete_post((int) $item->ID, true);
        }

        foreach (['about-us', 'contact', 'faq', 'plan-my-safari'] as $slug) {
            $page = get_page_by_path($slug);
            if (! $page) {
                continue;
            }
            wp_update_nav_menu_item($footer_id, 0, [
                'menu-item-title'     => get_the_title($page),
                'menu-item-object'    => 'page',
                'menu-item-object-id' => (int) $page->ID,
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ]);
        }

        $locations['footer'] = $footer_id;

        // Legal.
        $legal_name = 'Legal Menu';
        $legal = wp_get_nav_menu_object($legal_name);
        $legal_id = $legal ? (int) $legal->term_id : (int) wp_create_nav_menu($legal_name);

        foreach (wp_get_nav_menu_items($legal_id) ?: [] as $item) {
            wp_delete_post((int) $item->ID, true);
        }

        foreach (['privacy-policy', 'terms', 'cookie-policy'] as $slug) {
            $page = get_page_by_path($slug);
            if (! $page) {
                continue;
            }
            wp_update_nav_menu_item($legal_id, 0, [
                'menu-item-title'     => get_the_title($page),
                'menu-item-object'    => 'page',
                'menu-item-object-id' => (int) $page->ID,
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ]);
        }

        $locations['legal'] = $legal_id;

        set_theme_mod('nav_menu_locations', $locations);

        WP_CLI::log('  · Built primary, footer and legal menus.');
    }

    /**
     * Add one archive link to a menu.
     *
     * @param int    $menu_id  Menu ID.
     * @param string $post_type Post type.
     */
    private function add_post_type_item(int $menu_id, string $post_type): void
    {
        $link = get_post_type_archive_link($post_type);
        if (! $link) {
            return;
        }

        $object = get_post_type_object($post_type);

        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title'  => $object ? $object->labels->name : ucfirst($post_type),
            'menu-item-url'    => $link,
            'menu-item-status' => 'publish',
        ]);
    }

    /**
     * Create every taxonomy term the seeded content needs.
     *
     * @return array<string,array<string,int>> Taxonomy to slug/label to term ID.
     */
    private function create_taxonomies(): array
    {
        $spec = [
            'region' => [
                'Africa'                 => ['africa', 'Africa'],
                'East Africa'            => ['east-africa', 'East Africa'],
                'Kenya'                  => ['kenya', 'Kenya'],
                'Tanzania'               => ['tanzania', 'Tanzania'],
                'Uganda'                 => ['uganda', 'Uganda'],
                'Rwanda'                 => ['rwanda', 'Rwanda'],
                'Southern Africa'        => ['southern-africa', 'Southern Africa'],
                'Botswana'               => ['botswana', 'Botswana'],
                'Zimbabwe'               => ['zimbabwe', 'Zimbabwe'],
                'South Africa'           => ['south-africa', 'South Africa'],
            ],
            'safari_type' => [
                'Game drive'        => ['game-drive', 'Game Drive'],
                'Walking safari'    => ['walking-safari', 'Walking Safari'],
                'Gorilla trekking'  => ['gorilla-trekking', 'Gorilla Trekking'],
                'Migration'         => ['migration', 'Migration'],
                'Photography'       => ['photography', 'Photography'],
                'Honeymoon'         => ['honeymoon', 'Honeymoon'],
                'Family safari'     => ['family-safari', 'Family Safari'],
                'Rift Valley'       => ['rift-valley', 'Rift Valley'],
            ],
            'travel_style' => [
                'Luxury'    => ['luxury', 'Luxury'],
                'Mid-range' => ['mid-range', 'Mid-range'],
                'Budget'    => ['budget', 'Budget'],
            ],
            'season' => [
                'Jan' => ['jan', 'January'],
                'Feb' => ['feb', 'February'],
                'Mar' => ['mar', 'March'],
                'Apr' => ['apr', 'April'],
                'May' => ['may', 'May'],
                'Jun' => ['jun', 'June'],
                'Jul' => ['jul', 'July'],
                'Aug' => ['aug', 'August'],
                'Sep' => ['sep', 'September'],
                'Oct' => ['oct', 'October'],
                'Nov' => ['nov', 'November'],
                'Dec' => ['dec', 'December'],
            ],
            'event_type' => [
                'Promotion'       => ['promotion', 'Promotion'],
                'Group departure' => ['group-departure', 'Group Departure'],
                'Festival'        => ['festival', 'Festival'],
                'Announcement'    => ['announcement', 'Announcement'],
            ],
            'guide_topic' => [
                'Planning'     => ['planning', 'Planning'],
                'Wildlife'     => ['wildlife', 'Wildlife'],
                'Photography'  => ['photography', 'Photography'],
                'Health & visas' => ['health-visas', 'Health & Visas'],
                'Packing'      => ['packing', 'Packing'],
            ],
            'faq_category' => [
                'Booking'   => ['booking', 'Booking'],
                'Payments'  => ['payments', 'Payments'],
                'Safety'    => ['safety', 'Safety'],
                'Visas'     => ['visas', 'Visas'],
                'Payments & pricing' => ['payments-pricing', 'Payments & Pricing'],
            ],
        ];

        $map = [];

        foreach ($spec as $taxonomy => $terms) {
            $map[ $taxonomy ] = [];

            foreach ($terms as $key => [$slug, $name]) {
                $existing = get_term_by('slug', $slug, $taxonomy);

                if ($existing) {
                    $map[ $taxonomy ][ $key ] = (int) $existing->term_id;
                    continue;
                }

                $result = wp_insert_term($name, $taxonomy, ['slug' => $slug]);

                if (! is_wp_error($result)) {
                    $map[ $taxonomy ][ $key ] = (int) $result['term_id'];
                }
            }

            // Mirror children under their parent so the region tree is usable.
            $tree = [
                'region' => [
                    'kenya'    => 'East Africa',
                    'tanzania' => 'East Africa',
                    'uganda'   => 'East Africa',
                    'rwanda'   => 'East Africa',
                    'botswana' => 'Southern Africa',
                    'zimbabwe' => 'Southern Africa',
                    'south-africa' => 'Southern Africa',
                ],
            ];

            foreach ($tree[ $taxonomy ] ?? [] as $child => $parent ) {
                if (isset($map[ $taxonomy ][ $child ], $map[ $taxonomy ][ $parent ])) {
                    wp_update_term(
                        $map[ $taxonomy ][ $child ],
                        $taxonomy,
                        ['parent' => $map[ $taxonomy ][ $parent ]]
                    );
                }
            }
        }

        WP_CLI::log('  · Created taxonomy terms.');

        return $map;
    }

    /* ======================================================================
     * Destinations
     * =================================================================== */

    /**
     * Seed destination posts.
     *
     * @param array $tax       Term ID map.
     * @param bool  $no_images Skip image sideloading.
     * @return array<string,int> Slug to post ID.
     */
    private function create_destinations(array $tax, bool $no_images): array
    {
        $data = [
            [
                'slug'    => 'maasai-mara',
                'title'   => 'Maasai Mara',
                'country' => 'Kenya',
                'regions' => ['Kenya'],
                'types'   => ['Migration', 'Game drive', 'Photography'],
                'months'  => ['jul', 'aug', 'sep', 'oct'],
                'excerpt' => 'Rolling grassland, river crossings, and the densest wildlife on the continent — a million wildebeest and the cats that wait for them.',
                'content' => '<h2>Why the Mara is on everyone\'s list</h2>'
                    . '<p>The Maasai Mara is where the Great Migration spends three months of the year, and where resident populations of lion, cheetah, leopard and elephant never leave. It is busy in peak season and genuinely wild in the shoulder months — the trade-off is timing, not quality.</p>'
                    . '<h2>When to go</h2>'
                    . '<p>July to October for the migration. January to March for calving season, when predators concentrate on the plains and vehicle numbers drop. April, May and November are the green season: dramatic skies, newborn animals, and camps at their best value.</p>'
                    . '<h2>Getting there</h2>'
                    . '<p>A four-hour drive from Nairobi with two scheduled flights a day to the Mara\'s three airstrips. We recommend flying in and driving out — it saves six hours of dirt road and gives you a second camp.</p>',
                'image'   => 'photo-1516426122078-c23e76319801',
            ],
            [
                'slug'    => 'serengeti',
                'title'   => 'Serengeti',
                'country' => 'Tanzania',
                'regions' => ['Tanzania'],
                'types'   => ['Migration', 'Game drive', 'Photography'],
                'months'  => ['jan', 'feb', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov'],
                'excerpt' => 'The largest intact grassland on earth, and the stage for one of the most dramatic wildlife movements ever filmed.',
                'content' => '<h2>Endless plains, short grass</h2>'
                    . '<p>The Serengeti is not one park but an ecosystem: the short-grass plains in the south, the long-grass savannah in the centre, the kopjes of granite that host the densest lion populations in Africa, and the northern river system the herds build their whole calendar around.</p>'
                    . '<h2>The migration, properly</h2>'
                    . '<p>Calving happens in the south between January and March, when roughly half a million wildebeest are born in a three-week window. From there the herds move north in a long column, cross the Grumeti and then the Mara river in July to October. Timing your trip to a specific movement rather than a season is where a good operator earns their fee.</p>'
                    . '<h2>Getting there</h2>'
                    . '<p>Grumeti and Kogatende airstrips in the north, Seronera in the middle, and the southern plains near Ndutu. Flying between two airstrips beats driving 400 km of dirt.</p>',
                'image'   => 'photo-1516426122078-c23e76319801',
            ],
            [
                'slug'    => 'Amboseli',
                'title'   => 'Amboseli',
                'country' => 'Kenya',
                'regions' => ['Kenya'],
                'types'   => ['Game drive', 'Photography', 'Honeymoon'],
                'months'  => ['jan', 'feb', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'],
                'excerpt' => 'Elephant herds crossing the Kilimanjaro foothills at dawn, with the snow-capped peak behind them.',
                'content' => '<h2>The elephants</h2>'
                    . '<p>Big-tusked bull elephants move through the swamps below Kilimanjaro in family groups, and at first light the sight of a whole herd framed against the mountain is the reason most people put Amboseli on their list.</p>'
                    . '<h2>What else is here</h2>'
                    . '<p>Buffalo, cheetah in the open, giraffes, and one of the most reliable big-cat viewing in the country because the terrain is flat and the elephants draw the lions. The swamps also mean fewer vehicles than the Mara, which changes the feel of a morning drive considerably.</p>'
                    . '<h2>When to go</h2>'
                    . '<p>Dry season, January to February and July to October. The elephants come to the springs when the marsh dries, and the mountain is usually clearest in the dry months.</p>',
                'image'   => 'photo-1547471080-7cc2caa01a7e',
            ],
            [
                'slug'    => 'kruger',
                'title'   => 'Greater Kruger',
                'country' => 'South Africa',
                'regions' => ['South Africa'],
                'types'   => ['Game drive', 'Walking safari', 'Family safari'],
                'months'  => ['sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'],
                'excerpt' => 'Big Five country with a serious wilderness backdrop — private reserves, walking trails, and reliably good leopard viewing.',
                'content' => '<h2>A park, and a dozen parks either side of it</h2>'
                    . '<p>The Kruger is exceptional, but the surrounding private reserves are often better: lower vehicle numbers, walking trails with armed trackers, and night drives that the main park does not allow. Most of our itineraries use both.</p>'
                    . '<h2>Leopards</h2>'
                    . '<p>Kruger is as reliable a place as exists to see leopard — the riverine woodland along the Sabie and Crocodile rivers is dense with them, and the rest camps stay open late enough to photograph them in low light.</p>',
                'image'   => 'photo-1515187029135-18ee286d815b',
            ],
            [
                'slug'    => 'okavango-delta',
                'title'   => 'Okavango Delta',
                'country' => 'Botswana',
                'regions' => ['Botswana'],
                'types'   => ['Game drive', 'Walking safari', 'Photography'],
                'months'  => ['may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov'],
                'excerpt' => 'A inland delta flooded by no river — mokoro channels, walking safaris on its islands, and almost no other vehicles.',
                'content' => '<h2>Water where you do not expect it</h2>'
                    . '<p>The Okavango never reaches the sea. Instead it spreads inland across the Kalahari, creating a maze of permanent and seasonal channels best explored by mokoro — a dugout canoe poled by a local Bayei poler — or on foot with a guide.</p>'
                    . '<h2>Low density, high quality</h2>'
                    . '<p>Because access is by light aircraft and seasonal water level, camps are few and vehicle numbers are among the lowest in southern Africa. This is the closest thing in Africa to wilderness safari.</p>'
                    . '<h2>When to go</h2>'
                    . '<p>May to September for the flood and the dry-game viewing. December to March for birding and the green floodplain.</p>',
                'image'   => 'photo-1484318571209-66172429a0c9',
            ],
            [
                'slug'    => 'bwindi',
                'title'   => 'Bwindi Impenetrable Forest',
                'country' => 'Uganda',
                'regions' => ['Uganda'],
                'types'   => ['Gorilla trekking', 'Walking safari', 'Birding'],
                'months'  => ['jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'],
                'excerpt' => 'Half a square kilometre of ancient rainforest holding roughly half the world\'s remaining mountain gorillas.',
                'content' => '<h2>The trek itself</h2>'
                    . '<p>Four hours each way through steep, wet forest, to spend one hour with a habituated family. It is a hard, unglamorous slog in the best possible way: the gorillas ignore you entirely, which is exactly the point.</p>'
                    . '<h2>Permits and numbers</h2>'
                    . '<p>Only four trekking permits are issued per day per sector. This is the single hardest booking on the continent and it should be secured the moment dates are fixed, not after flights.</p>'
                    . '<h2>What else is in the forest</h2>'
                    . '<p>More than 350 bird species, including the rare Albertine Rift endemics, and thirteen primate species. Guided forest walks for chimpanzee habituation tracking are available in some sectors.</p>',
                'image'   => 'photo-1516026672322-bc52d61a55d5',
            ],
        ];

        $ids = [];

        foreach ($data as $item) {
            $id = $this->upsert('destination', [
                'post_title'   => $item['title'],
                'post_name'    => $item['slug'],
                'post_content' => $item['content'],
                'post_excerpt' => $item['excerpt'],
                'post_status'  => 'publish',
            ]);

            if (! $id) {
                continue;
            }

            $ids[ $item['slug'] ] = $id;

            update_post_meta($id, '_safari_seeded', '1');
            update_post_meta($id, 'country', $item['country']);
            update_post_meta($id, 'best_months', $item['months']);
            update_post_meta($id, 'region_info', '');

            $this->assign_terms($id, 'region', $item['regions'], $tax);
            $this->assign_terms($id, 'safari_type', $item['types'], $tax);

            if (! $no_images) {
                $this->sideload($id, $item['image'], $item['title'], 'safari-hero');
            }

            WP_CLI::log('  · Destination: ' . $item['title']);
        }

        return $ids;
    }

    /* ======================================================================
     * Tours
     * =================================================================== */

    /**
     * Seed tour posts.
     *
     * @param array $destinations Destination ID map.
     * @param array $tax          Term ID map.
     * @param int   $per_dest     Tours per destination.
     * @param bool  $no_images    Skip image sideloading.
     * @return array<string,int> Slug to post ID.
     */
    private function create_tours(array $destinations, array $tax, int $per_dest, bool $no_images): array
    {
        $templates = [
            'maasai-mara' => [
                [
                    'title'    => 'The Classic Mara Crossing',
                    'days'     => 7,
                    'price'    => 4850,
                    'style'    => 'Luxury',
                    'group'    => 6,
                    'difficulty' => 'Easy',
                    'months'   => ['Jul', 'Aug', 'Sep', 'Oct'],
                    'excerpt'  => 'Four days on the plains waiting for the river crossings, then three on a conservancy where the vehicles thin out.',
                    'inclusions' => [
                        'Six nights in luxury tented camps and suites',
                        'All meals, including full board and drinks at camp',
                        'Dedicated 4x4 with a maximum of six guests and a driver-guide',
                        'All park entry fees and conservancy levies',
                        'Internal flight Nairobi to the Mara and back',
                        '24/7 in-destination support',
                    ],
                    'exclusions' => [
                        'International flights',
                        'Travel insurance',
                        'Visas and gratuities',
                    ],
                    'itinerary' => [
                        ['Nairobi to the Mara', 'Light aircraft to the Mara airstrip in the early morning, then a game drive to camp with hippo and giraffe en route. Afternoon tea on a kopje, then an evening drive as the light goes amber.'],
                        ['Into the reserve', 'A full day on the Talek River, positioning where the crossings have been most likely this week. Picnic lunch in the open.'],
                        ['River crossing day', 'Departure at first light. This is the day the whole itinerary is built around — patience is the strategy here, and your guide will read the herds all day if that is what it takes.'],
                        ['Conservancy', 'Transfer to a private conservancy on the northern boundary. Off-road driving, walking safaris, and a fraction of the vehicles.'],
                        ['Walking safari', 'A guided walk through the conservancy, tracking on foot with an armed ranger. Elephant, giraffe and smaller cats at close range.'],
                        ['Big cat country', 'Full day back on the Mara plains, with the emphasis on the resident lion and cheetah territories.'],
                        ['Departure', 'Late morning flight back to Nairobi, or a onward connection.'],
                    ],
                ],
            ],
            'okavango-delta' => [
                [
                    'title'    => 'Okavango by Water and Foot',
                    'days'     => 8,
                    'price'    => 6200,
                    'style'    => 'Luxury',
                    'group'    => 4,
                    'difficulty' => 'Moderate',
                    'months'   => ['Jun', 'Jul', 'Aug', 'Sep'],
                    'excerpt'  => 'Mokoro channels at dawn, walking safaris on the islands, and helicopter access to a concession you cannot reach any other way.',
                    'inclusions' => [
                        'Seven nights across three camps, all full board',
                        'All light aircraft transfers between camps',
                        'Mokoro excursions with a Bayei poler',
                        'Guided walking safaris on the islands',
                        'All conservancy and park fees',
                        '24/7 in-destination support',
                    ],
                    'exclusions' => [
                        'International flights to Maun',
                        'Helicopter transfers beyond those listed',
                        'Travel insurance and visas',
                    ],
                    'itinerary' => [
                        ['Into the Delta', 'Light aircraft from Maun, skimming the floodplain before landing on a strip cut into the island. Camp by water, dinner by lantern.'],
                        ['Mokoro at first light', 'Out on the channels before the wind rises, gliding past papyrus and herons in absolute silence.'],
                        ['Walking the island', 'A three-hour walk with a guide and tracker, reading the ground for elephant, giraffe and the small cats that live here.'],
                        ['Deep Delta', 'A long transfer to a remote camp, with a game drive through woodland that has barely been touched.'],
                        ['Floodplain by vehicle', 'Full day across the seasonal pans, where the game concentrates as the water recedes.'],
                        ['Community and culture', 'A morning with the Bayei who live and fish here, and who cut the mokoro channels you travelled.'],
                        ['Heli to the next concession', 'A helicopter transfer north, banking low over the papyrus on the way to the final camp.'],
                        ['Departure', 'Light aircraft back to Maun for onward travel.'],
                    ],
                ],
            ],
        ];

        $ids = [];

        foreach ($destinations as $dest_slug => $dest_id) {
            $variants = $templates[ $dest_slug ] ?? $this->generic_tours($dest_id);
            $variants = array_slice($variants, 0, max(1, $per_dest));

            foreach ($variants as $index => $tour) {
                $slug = sanitize_title($tour['title']);

                $id = $this->upsert('tour', [
                    'post_title'   => $tour['title'],
                    'post_name'    => $slug,
                    'post_content' => $tour['excerpt'],
                    'post_excerpt' => $tour['excerpt'],
                    'post_status'  => 'publish',
                    'menu_order'   => $index,
                ]);

                if (! $id) {
                    continue;
                }

                $ids[ $slug ] = $id;

                update_post_meta($id, '_safari_seeded', '1');
                update_post_meta($id, 'destination', $dest_id);
                update_post_meta($id, 'duration_days', $tour['days']);
                update_post_meta($id, 'price_from', $tour['price']);
                update_post_meta($id, 'currency', 'USD');
                update_post_meta($id, 'group_size', $tour['group']);
                update_post_meta($id, 'difficulty', $tour['difficulty']);
                update_post_meta($id, 'inclusions', $this->as_repeater($tour['inclusions']));
                update_post_meta($id, 'exclusions', $this->as_repeater($tour['exclusions']));
                update_post_meta($id, 'itinerary', $this->as_itinerary($tour['itinerary']));

                // Fixed departures: one past, two upcoming.
                $departures = [];
                foreach ([-30, 45, 105] as $offset) {
                    $departures[] = [
                        'departure_date' => gmdate('Y-m-d', strtotime('today ' . $offset . ' days')),
                    ];
                }
                update_post_meta($id, 'departures', $departures);

                $this->assign_terms($id, 'travel_style', [$tour['style']], $tax);
                $this->assign_terms($id, 'season', $tour['months'], $tax);
                $this->inherit_terms($id, $dest_id, 'region');
                $this->inherit_terms($id, $dest_id, 'safari_type');

                if (! $no_images) {
                    $dest_image = get_post_meta($dest_id, '_seed_image_id', true);
                    if ($dest_image) {
                        set_post_thumbnail($id, (int) $dest_image);
                    }
                }

                WP_CLI::log('  · Tour: ' . $tour['title']);
            }
        }

        return $ids;
    }

    /**
     * A generic but coherent itinerary for destinations without a bespoke one.
     *
     * @param int $dest_id Destination post ID.
     * @return array<int,array> Tour definitions.
     */
    private function generic_tours(int $dest_id): array
    {
        $name = get_the_title($dest_id);

        $common_days = [
            'Arrival and first drive', 'Full day on the plains', 'Second full day, deeper into the reserve',
            'Water activities or a walking safari', 'Leisure day at camp', 'Final morning drive',
            'Departure',
        ];

        $out = [];

        for ($i = 0; $i < 2; $i++) {
            $days = 7;
            $out[] = [
                'title'      => sprintf('%s: %s Safari', $name, 0 === $i ? 'Classic' : 'In-Depth'),
                'days'       => $days,
                'price'      => 0 === $i ? 3950 : 5100,
                'style'      => 0 === $i ? 'Mid-range' : 'Luxury',
                'group'      => 0 === $i ? 6 : 4,
                'difficulty' => 'Easy',
                'months'     => ['Jul', 'Aug', 'Sep', 'Oct'],
                'excerpt'    => sprintf(
                    'A %d-day introduction to %s, paced for a first safari — longer drives broken by bush breakfasts, and enough time at camp to actually rest.',
                    $days,
                    $name
                ),
                'inclusions' => [
                    sprintf('%d nights in handpicked camps', $days - 1),
                    'All meals and drinks at camp',
                    'Dedicated 4x4 and driver-guide',
                    'All park and conservancy fees',
                    'Internal flights where required',
                    '24/7 in-destination support',
                ],
                'exclusions' => [
                    'International flights',
                    'Travel insurance',
                    'Visas and gratuities',
                ],
                'itinerary' => array_map(
                    static fn (string $t): array => [
                        'day_title'       => $t,
                        'day_description' => sprintf(
                            'A day built around %s. Your guide reads the ground in the morning, places you where the activity is, and leaves the afternoon unstructured.',
                            strtolower($t)
                        ),
                    ],
                    $common_days
                ),
            ];
        }

        return $out;
    }

    /* ======================================================================
     * Events, guides, FAQs, testimonials
     * =================================================================== */

    /**
     * Seed events and offers.
     *
     * @param array $destinations Destination ID map.
     * @param array $tax          Term ID map.
     * @param bool  $no_images    Skip image sideloading.
     */
    private function create_events(array $destinations, array $tax, bool $no_images): void
    {
        $events = [
            [
                'title'  => 'Shoulder Season Special — Mara & Nakuru',
                'type'   => 'Promotion',
                'code'   => 'SHOULDER15',
                'start'  => gmdate('Y-m-d', strtotime('today -10 days')),
                'end'    => gmdate('Y-m-d', strtotime('today +50 days')),
                'dest'   => 'maasai-mara',
                'excerpt' => '15% off any 7-night Kenya itinerary booked for May, June or November — the green season, when camps are at their best and the parks are at their quietest.',
            ],
            [
                'title'  => 'Gorilla Trekking Departure — Bwindi',
                'type'   => 'Group departure',
                'code'   => '',
                'start'  => gmdate('Y-m-d', strtotime('today +20 days')),
                'end'    => gmdate('Y-m-d', strtotime('today +20 days')),
                'dest'   => 'bwindi',
                'excerpt' => 'A fixed six-person departure with two confirmed permits. One hour with a habituated gorilla family, three nights at a forest-edge camp, and a chimp tracking walk.',
            ],
            [
                'title'  => 'Migration Positioning — Serengeti',
                'type'   => 'Group departure',
                'code'   => '',
                'start'  => gmdate('Y-m-d', strtotime('today -120 days')),
                'end'    => gmdate('Y-m-d', strtotime('today -110 days')),
                'dest'   => 'serengeti',
                'excerpt' => 'Our February calving-season departure. Now concluded — join the same group next year.',
            ],
        ];

        foreach ($events as $event) {
            $slug = sanitize_title($event['title']);

            $id = $this->upsert('event', [
                'post_title'   => $event['title'],
                'post_name'    => $slug,
                'post_excerpt' => $event['excerpt'],
                'post_content' => '<p>' . $event['excerpt'] . '</p><p>Full terms and inclusions are in your written proposal. Quote the code at the time of enquiry and we will apply it before you commit to anything.</p>',
                'post_status'  => 'publish',
            ]);

            if (! $id) {
                continue;
            }

            $this->event_slugs[] = $slug;

            update_post_meta($id, '_safari_seeded', '1');
            update_post_meta($id, 'start_date', $event['start']);
            update_post_meta($id, 'end_date', $event['end']);
            update_post_meta($id, 'promo_code', $event['code']);

            if (isset($destinations[ $event['dest'] ])) {
                update_post_meta($id, 'related_destination', $destinations[ $event['dest'] ]);
                $this->inherit_terms($id, $destinations[ $event['dest'] ], 'region');
            }

            $this->assign_terms($id, 'event_type', [$event['type']], $tax);

            if (! $no_images && isset($destinations[ $event['dest'] ])) {
                $image = get_post_meta($destinations[ $event['dest'] ], '_seed_image_id', true);
                if ($image) {
                    set_post_thumbnail($id, (int) $image);
                }
            }

            WP_CLI::log('  · Event: ' . $event['title']);
        }
    }

    /**
     * Seed guide articles.
     *
     * @param array $destinations Destination ID map.
     * @param array $tax          Term ID map.
     * @param bool  $no_images    Skip image sideloading.
     */
    private function create_guides(array $destinations, array $tax, bool $no_images): void
    {
        $guides = [
            [
                'title'   => 'The Mara Migration, month by month',
                'topic'   => 'Wildlife',
                'dest'    => 'maasai-mara',
                'excerpt' => 'Where the herds actually are from January to December, and why the popular months are not always the right ones for you.',
                'content' => '<h2>Why the migration is not a single event</h2>'
                    . '<p>Most people imagine the migration as one dramatic river crossing. In practice it is a rolling, unpredictable series of movements that happen across seven months, and a good operator is tracking it week by week rather than reading a calendar.</p>'
                    . '<h2>January to March: the calving</h2>'
                    . '<p>Half a million wildebeest are born in the southern Serengeti over about three weeks. Predator numbers concentrate to a degree you will not see at any other time of year — cheetah in particular. Vehicle numbers are also at their lowest. This is the best value month of the year and the least photographed.</p>'
                    . '<h2>April to June: the long march</h2>'
                    . '<p>The herds move north-west in loose columns. Roads are good, camps are quiet, and the long rains mean some camps close through April. The scenery is spectacular and the game viewing is spread thin.</p>'
                    . '<h2>July to October: the crossings</h2>'
                    . '<p>The herds reach the northern Serengeti and start testing the Mara and Grumeti rivers. Crossings are most likely in July through September. This is the peak of peak season: brilliant wildlife, brilliant photography, and the highest prices and vehicle numbers of the year.</p>'
                    . '<h2>November to December: the short rains</h2>'
                    . '<p>The herds drift south-east, the Mara becomes quieter, and camps offer serious value. Green, empty, and good for birds.</p>',
            ],
            [
                'title'   => 'Safari visas: what you actually need',
                'topic'   => 'Health & visas',
                'dest'    => '',
                'excerpt' => 'East Africa eVisa, Southern Africa eVisa, and the yellow fever certificate — a plain-language guide to getting through immigration.',
                'content' => '<h2>The short version</h2>'
                    . '<p>Kenya, Tanzania and Uganda all run the East Africa Tourist Visa, which is one application covering all three. Rwanda and South Africa have separate processes. Botswana and Zimbabwe use their own eVisa systems. Allow three weeks; allow more if your passport is not in good condition.</p>'
                    . '<h2>Passport validity</h2>'
                    . '<p>Six months beyond your departure date, with at least two blank pages. This is checked at every border and there is no negotiating with it.</p>'
                    . '<h2>Yellow fever</h2>'
                    . '<p>An International Certificate of Vaccination is required if you are arriving from an endemic country, including transit through many hubs. It cannot be issued retroactively and is often checked at the airport before you reach immigration.</p>'
                    . '<h2>On arrival</h2>'
                    . '<p>Kenya now requires the Kenya Electronic Travel Authorisation for all visitors, applied for online before you fly. It is quick, free, and the most common reason people are delayed at Heathrow or Schiphol.</p>',
            ],
            [
                'title'   => 'Safari photography: shooting from an open vehicle',
                'topic'   => 'Photography',
                'dest'    => 'okavango-delta',
                'excerpt' => 'Lens choices, shutter discipline, and the one setting that matters more than any other when the light goes low.',
                'content' => '<h2>What to bring</h2>'
                    . '<p>A 200–600mm for wildlife, a 24–70mm for landscapes and camps, and a 70–200mm for anything you cannot approach. Anything heavier than 1.5kg will be miserable after six hours in a vehicle with no suspension worth mentioning.</p>'
                    . '<h2>The setting that matters</h2>'
                    . '<p>Shutter priority at 1/1000s minimum for walking animals, 1/2000s for birds in flight. Auto ISO will handle the rest. Aperture wide open, f/5.6 to f/8 depending on the subject and how much depth you need.</p>'
                    . '<h2>Light</h2>'
                    . '<p>First and last hour, every day, without exception. Midday light is harsh and the animals are resting. Plan your drives around the light rather than the other way round and your whole trip improves.</p>'
                    . '<h2>Etiquette</h2>'
                    . '<p>Your guide will always ask the driver to reposition before a shot, and the driver will always do it. That is normal and is not a request for a favour.</p>',
            ],
            [
                'title'   => 'What to pack for a Kenyan safari (and what to leave behind)',
                'topic'   => 'Packing',
                'dest'    => 'maasai-mara',
                'excerpt' => 'Neutral colours, a decent hat, binoculars you will actually use, and nothing you would be upset to see covered in dust.',
                'content' => '<h2>Clothes</h2>'
                    . '<p>Khaki, olive, mid-grey, navy. Avoid white and bright blue — tsetse flies and, in some camps, a real curfew on wearing camouflage-print. Two pairs of broken-in shoes if you are walking; trainers are fine for vehicle days.</p>'
                    . '<h2>Binoculars</h2>'
                    . '<p>8x42 is the right weight for a full day. Bring a pair you have used before, not a new one you will fumble with on day one.</p>'
                    . '<h2>Camera</h2>'
                    . '<p>Dust is the thing that will break it. A blower, a microfibre cloth, and a spare battery — cold mornings and warm afternoons drain them faster than you expect.</p>'
                    . '<h2>Camps provide</h2>'
                    . '<p>Towels, toiletries, insect repellent, filtered water, and all bedding. You do not need to carry any of that, and the weight adds up fast.</p>',
            ],
        ];

        foreach ($guides as $guide) {
            $slug = sanitize_title($guide['title']);

            $id = $this->upsert('safari_guide', [
                'post_title'   => $guide['title'],
                'post_name'    => $slug,
                'post_content' => $guide['content'],
                'post_excerpt' => $guide['excerpt'],
                'post_status'  => 'publish',
            ]);

            if (! $id) {
                continue;
            }

            $this->guide_slugs[] = $slug;

            update_post_meta($id, '_safari_seeded', '1');
            $this->assign_terms($id, 'guide_topic', [$guide['topic']], $tax);

            if ('' !== $guide['dest'] && isset($destinations[ $guide['dest'] ])) {
                $this->inherit_terms($id, $destinations[ $guide['dest'] ], 'region');

                if (! $no_images) {
                    $image = get_post_meta($destinations[ $guide['dest'] ], '_seed_image_id', true);
                    if ($image) {
                        set_post_thumbnail($id, (int) $image);
                    }
                }
            }

            WP_CLI::log('  · Guide: ' . $guide['title']);
        }
    }

    /**
     * Seed FAQ entries.
     *
     * @param array $tax Term ID map.
     */
    private function create_faqs(array $tax): void
    {
        $faqs = [
            [
                'title'   => 'How far ahead should I book?',
                'cat'     => 'Booking',
                'answer'  => 'For peak season — July to October — six to nine months. For gorilla trekking, as soon as you have fixed dates, because permits are released in small batches and sell out. For shoulder and green seasons, three to four months is comfortable, and sometimes two is enough.',
            ],
            [
                'title'   => 'Is a deposit required, and how much?',
                'cat'     => 'Payments',
                'answer'  => 'A 20% deposit confirms your booking; the balance is due 60 days before arrival. Deposits are non-refundable but are transferable to a new date within twelve months. Cancellation terms per supplier are listed on your confirmation before you pay anything.',
            ],
            [
                'title'   => 'Do I need a travel insurance policy?',
                'cat'     => 'Safety',
                'answer'  => 'Yes, and we will ask for proof of it. It must cover medical evacuation, which is the single most important clause — a helicopter evacuation from a remote camp can cost more than the entire safari, and no standard policy covers it unless evacuation is explicitly included.',
            ],
            [
                'title'   => 'What vaccinations do I need?',
                'cat'     => 'Visas',
                'answer'  => 'Most travellers need a yellow fever certificate if arriving from an endemic country, plus routine cover for hepatitis A and typhoid. Malaria is present in most safari areas; your specialist will advise on prophylaxis for your specific itinerary. Consult your travel clinic six to eight weeks out.',
            ],
            [
                'title'   => 'Can I take children on safari?',
                'cat'     => 'Booking',
                'answer'  => 'Yes, on most itineraries, and it is one of the best family trips you can take. The practical limits are flight and camp age restrictions, and the need to keep room in the vehicle. We will tell you honestly which camps suit your children\'s ages rather than selling the whole thing and apologising later.',
            ],
            [
                'title'   => 'Will I see the Big Five?',
                'cat'     => 'Booking',
                'answer'  => 'Honest answer: you will almost certainly see all five across a two-week itinerary, and probably not all five in a three-day one. Lion and elephant are close to guaranteed. Leopard is the one that requires planning and patience — which is why we place camps deliberately rather than by convenience.',
            ],
            [
                'title'   => 'What is the daily structure?',
                'cat'     => 'Booking',
                'answer'  => 'Typically a game drive from around 06:00 to 10:00, breakfast at camp, a rest through the heat of the day, then an afternoon drive from about 15:30 until dark. Walking safaris and mokoro trips replace an afternoon drive. You are never required to do anything.',
            ],
            [
                'title'   => 'How is payment taken?',
                'cat'     => 'Payments',
                'answer'  => 'Bank transfer and card are both accepted, with card payments processed over a secure gateway. We do not ask for card details over email or WhatsApp under any circumstances — if you receive such a request purporting to be from us, it is a scam. Call us on the number in the footer to check.',
            ],
        ];

        foreach ($faqs as $faq) {
            $slug = sanitize_title($faq['title']);

            $id = $this->upsert('faq', [
                'post_title'   => $faq['title'],
                'post_name'    => $slug,
                'post_content' => $faq['answer'],
                'post_status'  => 'publish',
            ]);

            if (! $id) {
                continue;
            }

            $this->faq_slugs[] = $slug;

            update_post_meta($id, '_safari_seeded', '1');
            update_post_meta($id, 'short_answer', $faq['answer']);

            $this->assign_terms($id, 'faq_category', [$faq['cat']], $tax);

            WP_CLI::log('  · FAQ: ' . $faq['title']);
        }
    }

    /**
     * Seed testimonials.
     */
    private function create_testimonials(): void
    {
        $quotes = [
            ['Anneke V., Netherlands', 'Kenya, March 2025', 'We had done two safaris before this one and both were rushed. This was the opposite — two days per camp, no schedule to chase. Our guide Aiko read the Mara better than anyone we have travelled with.'],
            ['James & Priya R., United Kingdom', 'Tanzania, August 2025', 'The calving season trip was worth every pound. We were repositioned daily based on what the herds were actually doing, and the written proposal we got back was more considered than anything we had put together ourselves.'],
            ['Sofia L., Italy', 'Botswana & Zimbabwe, June 2025', 'The mokoro mornings in the Delta were the most peaceful hours of my life. Having done a conventional game drive safari first, the contrast made the Delta part of the trip unforgettable.'],
            ['Daniel M., Canada', 'Uganda, February 2025', 'Four hours of steep wet forest to sit for one hour with a gorilla family who did not care we were there at all. Exactly as described, no exaggeration. Permit booking was handled before I had to think about it.'],
            ['Marta K., Germany', 'South Africa, November 2024', 'Booking through the WhatsApp line felt unusually human. Somebody answered at 22:00 when our flight was delayed, and had already rebooked the transfer by the time we landed.'],
            ['Tom H., Australia', 'Kenya, July 2024', 'I was nervous about a nine-night trip with two small children. The team was completely straight with us about which camps would and would not work. The trip was excellent and we were never once oversold.'],
        ];

        foreach ($quotes as $index => [$meta, $role, $text]) {
            $slug = sanitize_title($meta);

            $id = $this->upsert('testimonial', [
                'post_title'   => $meta,
                'post_name'    => $slug,
                'post_content' => $text,
                'post_excerpt' => $text,
                'post_status'  => 'publish',
                'menu_order'   => $index,
            ]);

            if (! $id) {
                continue;
            }

            $this->testimonial_slugs[] = $slug;

            update_post_meta($id, '_safari_seeded', '1');
            update_post_meta($id, 'person_name', $meta);
            update_post_meta($id, 'role', $role);
            update_post_meta($id, 'rating', (string) (5 - ($index % 3 === 2 ? 1 : 0)));
        }

        WP_CLI::log('  · Created ' . count($quotes) . ' testimonials.');
    }

    /* ======================================================================
     * Helpers
     * =================================================================== */

    /**
     * Create or update a post by slug.
     *
     * @param string $post_type Post type.
     * @param array  $data      Post data.
     * @return int Post ID, or 0 on failure.
     */
    private function upsert(string $post_type, array $data): int
    {
        // wp_insert_post() defaults to `post` when post_type is absent, which
        // silently filed every destination/tour/guide as a blog post.
        $data['post_type'] = $post_type;

        $existing = get_posts([
            'post_type'   => $post_type,
            'name'        => $data['post_name'],
            'posts_per_page' => 1,
            'post_status' => 'any',
        ]);

        if ($existing) {
            $data['ID'] = (int) $existing[0]->ID;
            $result = wp_update_post($data, true);
        } else {
            $result = wp_insert_post($data, true);
        }

        return is_wp_error($result) ? 0 : (int) $result;
    }

    /**
     * Turn a plain list of strings into an ACF repeater value.
     *
     * @param array $items Strings.
     * @return array Repeater rows.
     */
    private function as_repeater(array $items): array
    {
        return array_map(
            static fn (string $item): array => ['item' => $item],
            $items
        );
    }

    /**
     * Turn an itinerary definition into an ACF repeater value.
     *
     * Accepts both shapes used in this file: a positional
     * `[title, description]` pair (the hand-written tours) and an associative
     * `['day_title' => …, 'day_description' => …]` row (the generated ones).
     *
     * @param array $days Day definitions.
     * @return array Repeater rows.
     */
    private function as_itinerary(array $days): array
    {
        return array_map(
            static function (array $day): array {
                $title = $day['day_title'] ?? $day[0] ?? '';
                $body  = $day['day_description'] ?? $day[1] ?? '';

                return [
                    'day_title'       => (string) $title,
                    'day_description' => (string) $body,
                ];
            },
            $days
        );
    }

    /**
     * Assign taxonomy terms by key.
     *
     * @param int    $post_id Post ID.
     * @param string $tax     Taxonomy.
     * @param array  $keys    Term keys from the taxonomy map.
     * @param array  $map     Term ID map.
     */
    private function assign_terms(int $post_id, string $tax, array $keys, array $map): void
    {
        $ids = [];

        foreach ($keys as $key) {
            if (isset($map[ $tax ][ $key ])) {
                $ids[] = $map[ $tax ][ $key ];
            }
        }

        if ($ids) {
            wp_set_object_terms($post_id, $ids, $tax, false);
        }
    }

    /**
     * Copy taxonomy terms from one post to another.
     *
     * @param int    $post_id Target post.
     * @param int    $from_id Source post.
     * @param string $tax     Taxonomy.
     */
    private function inherit_terms(int $post_id, int $from_id, string $tax): void
    {
        $terms = wp_get_object_terms($from_id, $tax, ['fields' => 'ids']);

        if (! is_wp_error($terms) && $terms) {
            wp_set_object_terms($post_id, $terms, $tax, false);
        }
    }

    /**
     * Sideload a remote image and attach it to a post.
     *
     * Silently skips on failure so a network hiccup cannot abort the seed.
     *
     * @param int    $post_id Post ID.
     * @param string $photo   Unsplash photo identifier.
     * @param string $alt     Alt text.
     * @param string $size    Registered size to generate.
     * @return int Attachment ID, or 0.
     */
    private function sideload(int $post_id, string $photo, string $alt, string $size): int
    {
        // Reuse a previously sideloaded copy.
        $cached = get_posts([
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields'     => 'ids',
            'meta_key'   => '_seed_image_id',
            'meta_value' => $photo,
        ]);

        if ($cached) {
            $id = (int) $cached[0];
            if (has_post_thumbnail($post_id)) {
                return $id;
            }
            set_post_thumbnail($post_id, $id);
            return $id;
        }

        $url = sprintf('https://images.unsplash.com/%s?auto=format&fit=crop&w=2400&q=80', $photo);

        $temp = wp_tempnam($photo);
        if (! $temp) {
            return 0;
        }

        // Download with a short timeout; this runs in CLI, not a request.
        $response = wp_safe_remote_get($url, [
            'timeout'     => 30,
            'redirection' => 5,
            'headers'     => ['User-Agent' => 'SafariTravelSeeder/1.0'],
        ]);

        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            WP_CLI::debug('  ! Could not fetch ' . $url);
            wp_delete_file($temp);
            return 0;
        }

        file_put_contents($temp, wp_remote_retrieve_body($response));

        $file_array = [
            'name'     => $photo . '.jpg',
            'tmp_name' => $temp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id, $alt);

        if (is_wp_error($attachment_id)) {
            WP_CLI::debug('  ! Sideload failed: ' . $attachment_id->get_error_message());
            wp_delete_file($temp);
            return 0;
        }

        update_post_meta((int) $attachment_id, '_seed_image_id', $photo);
        update_post_meta((int) $attachment_id, '_wp_attachment_image_alt', $alt);

        set_post_thumbnail($post_id, (int) $attachment_id);
        update_post_meta($post_id, '_seed_image_id', (int) $attachment_id);

        return (int) $attachment_id;
    }
}

WP_CLI::add_command('safari seed', 'Safari_Seed_Command');
