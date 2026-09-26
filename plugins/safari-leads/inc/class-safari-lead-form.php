<?php
/**
 * Lead form: Gutenberg block + shortcode rendering.
 *
 * @package Safari_Leads
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Renders the four form presets as a block and shortcode.
 *
 * Shortcode: [safari_lead_form preset="tour"]
 * Presets  : plan_my_safari | tour_page | contact | quick
 */
final class Safari_Lead_Form {

	public static function init(): void {
		add_shortcode('safari_lead_form', [self::class, 'render_shortcode']);
		add_action('init', [self::class, 'register_block']);
		add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
	}

	// ── Shortcode ─────────────────────────────────────────────────────────

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode(array|string $atts): string {
		$atts = shortcode_atts(
			[
				'preset'         => 'contact',
				'destination_id' => '',
				'tour_id'        => '',
				'class'          => '',
			],
			(array) $atts,
			'safari_lead_form'
		);

		ob_start();
		self::render((string) $atts['preset'], [
			'destination_id' => absint($atts['destination_id']),
			'tour_id'        => absint($atts['tour_id']),
			'class'          => sanitize_html_class((string) $atts['class']),
		]);
		return (string) ob_get_clean();
	}

	// ── Block ─────────────────────────────────────────────────────────────

	public static function register_block(): void {
		if (! function_exists('register_block_type')) {
			return;
		}

		register_block_type('safari-leads/lead-form', [
			'editor_script'   => 'safari-leads-block',
			'render_callback' => [self::class, 'render_block'],
			'attributes'      => [
				'preset'         => ['type' => 'string', 'default' => 'contact'],
				'destination_id' => ['type' => 'integer', 'default' => 0],
				'tour_id'        => ['type' => 'integer', 'default' => 0],
			],
		]);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render_block(array $attributes): string {
		ob_start();
		self::render(
			(string) ($attributes['preset'] ?? 'contact'),
			[
				'destination_id' => absint($attributes['destination_id'] ?? 0),
				'tour_id'        => absint($attributes['tour_id'] ?? 0),
			]
		);
		return (string) ob_get_clean();
	}

	// ── Core render ───────────────────────────────────────────────────────

	/**
	 * Output the form HTML for a given preset.
	 *
	 * @param string               $preset  One of plan_my_safari|tour_page|contact|quick.
	 * @param array<string, mixed> $context Optional context (destination_id, tour_id, …).
	 */
	public static function render(string $preset = 'contact', array $context = []): void {
		$allowed_presets = ['plan_my_safari', 'tour_page', 'contact', 'quick', 'product'];
		if (! in_array($preset, $allowed_presets, true)) {
			$preset = 'contact';
		}

		$nonce          = wp_create_nonce('safari_lead_submit');
		$rest_url       = rest_url('safari/v1/leads');
		$turnstile_key  = (string) Safari_Lead_Settings::get('turnstile_site_key');
		$destination_id = (int) ($context['destination_id'] ?? 0);
		$tour_id        = (int) ($context['tour_id'] ?? 0);

		// Pre-fill destination / tour labels if available.
		$destination_label = $destination_id ? get_the_title($destination_id) : '';
		$tour_label        = $tour_id ? get_the_title($tour_id) : '';

		// Destinations for the select (exclude current if pre-filled).
		$destinations = get_posts([
			'post_type'      => 'destination',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		]);

		$form_class = 'safari-lead-form safari-lead-form--' . esc_attr($preset);
		if (! empty($context['class'])) {
			$form_class .= ' ' . esc_attr((string) $context['class']);
		}
		?>
		<form
			class="<?php echo esc_attr($form_class); ?>"
			data-rest-url="<?php echo esc_url($rest_url); ?>"
			data-preset="<?php echo esc_attr($preset); ?>"
			data-nonce="<?php echo esc_attr($nonce); ?>"
			novalidate
			aria-label="<?php esc_attr_e('Safari inquiry form', 'safari-leads'); ?>"
		>
			<!-- Hidden fields -->
			<input type="hidden" name="source_form" value="<?php echo esc_attr($preset); ?>">
			<input type="hidden" name="source_url" value="<?php echo esc_url(home_url($_SERVER['REQUEST_URI'] ?? '/')); ?>">
			<input type="hidden" name="destination_id" value="<?php echo esc_attr((string) $destination_id); ?>">
			<input type="hidden" name="tour_id" value="<?php echo esc_attr((string) $tour_id); ?>">
			<input type="hidden" name="_timestamp" value="<?php echo esc_attr((string) time()); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

			<!-- Honeypot — hidden from humans via CSS, must remain empty -->
			<div class="safari-lead-form__honeypot" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
				<label for="slf-website">Website</label>
				<input type="text" id="slf-website" name="website" autocomplete="off" tabindex="-1">
			</div>

			<!-- Global error / success banner -->
			<div class="safari-lead-form__messages" role="alert" aria-live="polite"></div>

			<?php
			match ($preset) {
				'plan_my_safari' => self::render_plan_my_safari($destinations, $destination_id, $destination_label),
				'tour_page'      => self::render_tour_page($destination_id, $destination_label, $tour_label),
				'contact'        => self::render_contact(),
				'quick'          => self::render_quick($destination_id, $destination_label),
				'product'        => self::render_contact(),
				default          => self::render_contact(),
			};
			?>

			<!-- Turnstile widget (invisible, renders when key present) -->
			<?php if ('' !== $turnstile_key) : ?>
				<div class="cf-turnstile safari-lead-form__turnstile"
					data-sitekey="<?php echo esc_attr($turnstile_key); ?>"
					data-callback="safariTurnstileCallback"
					data-theme="light"
					data-size="invisible">
				</div>
			<?php else : ?>
				<input type="hidden" name="cf_turnstile_token" value="">
			<?php endif; ?>

			<div class="safari-lead-form__actions">
				<button type="submit" class="safari-btn safari-btn--primary safari-btn--full-mobile" id="slf-submit-<?php echo esc_attr($preset); ?>">
					<span class="safari-btn__label"><?php esc_html_e('Send inquiry', 'safari-leads'); ?></span>
					<span class="safari-btn__spinner" aria-hidden="true"></span>
				</button>
			</div>
		</form>
		<?php
	}

	// ── Preset-specific field groups ──────────────────────────────────────

	/**
	 * Multi-step "Plan My Safari" form (3 steps).
	 *
	 * @param int[]  $destinations Array of destination post IDs.
	 * @param int    $destination_id Pre-filled destination.
	 * @param string $destination_label Pre-filled destination name.
	 */
	private static function render_plan_my_safari(array $destinations, int $destination_id, string $destination_label): void {
		?>
		<!-- Progress bar -->
		<div class="safari-lead-form__progress" aria-label="<?php esc_attr_e('Form progress', 'safari-leads'); ?>">
			<div class="safari-lead-form__progress-bar" style="--progress:33%" role="progressbar" aria-valuenow="1" aria-valuemin="1" aria-valuemax="3">
				<span class="screen-reader-text"><?php esc_html_e('Step 1 of 3', 'safari-leads'); ?></span>
			</div>
		</div>

		<!-- Step 1: Destination + Dates -->
		<fieldset class="safari-lead-form__step safari-lead-form__step--active" data-step="1">
			<legend class="safari-lead-form__step-title"><?php esc_html_e('Where & when?', 'safari-leads'); ?></legend>

			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slf-destination"><?php esc_html_e('Destination', 'safari-leads'); ?></label>
				<select id="slf-destination" name="destination_id" class="safari-lead-form__select">
					<option value=""><?php esc_html_e('Not sure yet', 'safari-leads'); ?></option>
					<?php foreach ($destinations as $dest_id) : ?>
						<option value="<?php echo esc_attr((string) $dest_id); ?>"<?php selected($destination_id, $dest_id); ?>>
							<?php echo esc_html(get_the_title($dest_id)); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slf-date-from"><?php esc_html_e('From', 'safari-leads'); ?></label>
				<input type="date" id="slf-date-from" name="date_from" class="safari-lead-form__input" autocomplete="off" min="<?php echo esc_attr(date('Y-m-d')); ?>">
			</div>

			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slf-date-to"><?php esc_html_e('To', 'safari-leads'); ?></label>
				<input type="date" id="slf-date-to" name="date_to" class="safari-lead-form__input" autocomplete="off">
			</div>

			<div class="safari-lead-form__field safari-lead-form__field--checkbox">
				<label class="safari-lead-form__checkbox-label">
					<input type="checkbox" name="dates_flexible" value="1" class="safari-lead-form__checkbox">
					<?php esc_html_e('My dates are flexible', 'safari-leads'); ?>
				</label>
			</div>

			<button type="button" class="safari-btn safari-btn--primary safari-lead-form__next" data-next-step="2"><?php esc_html_e('Next →', 'safari-leads'); ?></button>
		</fieldset>

		<!-- Step 2: Travellers + Budget -->
		<fieldset class="safari-lead-form__step" data-step="2" hidden>
			<legend class="safari-lead-form__step-title"><?php esc_html_e('Who & budget?', 'safari-leads'); ?></legend>

			<div class="safari-lead-form__row">
				<div class="safari-lead-form__field">
					<label class="safari-lead-form__label" for="slf-adults"><?php esc_html_e('Adults', 'safari-leads'); ?></label>
					<input type="number" id="slf-adults" name="adults" class="safari-lead-form__input" min="1" max="99" value="2" autocomplete="off">
				</div>
				<div class="safari-lead-form__field">
					<label class="safari-lead-form__label" for="slf-children"><?php esc_html_e('Children', 'safari-leads'); ?></label>
					<input type="number" id="slf-children" name="children" class="safari-lead-form__input" min="0" max="99" value="0" autocomplete="off">
				</div>
			</div>

			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slf-budget"><?php esc_html_e('Budget per person (USD)', 'safari-leads'); ?></label>
				<select id="slf-budget" name="budget_range" class="safari-lead-form__select">
					<option value=""><?php esc_html_e('Prefer not to say', 'safari-leads'); ?></option>
					<option value="under_2000"><?php esc_html_e('Under $2,000', 'safari-leads'); ?></option>
					<option value="2000_5000">$2,000 – $5,000</option>
					<option value="5000_10000">$5,000 – $10,000</option>
					<option value="10000_20000">$10,000 – $20,000</option>
					<option value="over_20000"><?php esc_html_e('Over $20,000', 'safari-leads'); ?></option>
				</select>
			</div>

			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slf-style"><?php esc_html_e('Travel style', 'safari-leads'); ?></label>
				<select id="slf-style" name="travel_style" class="safari-lead-form__select">
					<option value=""><?php esc_html_e('Any', 'safari-leads'); ?></option>
					<option value="luxury"><?php esc_html_e('Luxury', 'safari-leads'); ?></option>
					<option value="mid-range"><?php esc_html_e('Mid-range', 'safari-leads'); ?></option>
					<option value="budget"><?php esc_html_e('Budget-friendly', 'safari-leads'); ?></option>
				</select>
			</div>

			<div class="safari-lead-form__step-nav">
				<button type="button" class="safari-btn safari-btn--ghost safari-lead-form__prev" data-prev-step="1">← <?php esc_html_e('Back', 'safari-leads'); ?></button>
				<button type="button" class="safari-btn safari-btn--primary safari-lead-form__next" data-next-step="3"><?php esc_html_e('Next →', 'safari-leads'); ?></button>
			</div>
		</fieldset>

		<!-- Step 3: Contact details -->
		<fieldset class="safari-lead-form__step" data-step="3" hidden>
			<legend class="safari-lead-form__step-title"><?php esc_html_e('Your details', 'safari-leads'); ?></legend>
			<?php self::render_contact_fields(true); ?>
			<div class="safari-lead-form__step-nav">
				<button type="button" class="safari-btn safari-btn--ghost safari-lead-form__prev" data-prev-step="2">← <?php esc_html_e('Back', 'safari-leads'); ?></button>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Compact tour inquiry form.
	 */
	private static function render_tour_page(int $destination_id, string $destination_label, string $tour_label): void {
		?>
		<div class="safari-lead-form__intro">
			<?php if ($tour_label) : ?>
				<p class="safari-lead-form__tour-name"><?php echo esc_html($tour_label); ?></p>
			<?php endif; ?>
		</div>

		<div class="safari-lead-form__row">
			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slft-date-from"><?php esc_html_e('Preferred start', 'safari-leads'); ?></label>
				<input type="date" id="slft-date-from" name="date_from" class="safari-lead-form__input" min="<?php echo esc_attr(date('Y-m-d')); ?>">
			</div>
			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slft-adults"><?php esc_html_e('Adults', 'safari-leads'); ?></label>
				<input type="number" id="slft-adults" name="adults" class="safari-lead-form__input" min="1" max="99" value="2">
			</div>
		</div>

		<?php self::render_contact_fields(false); ?>
		<?php
	}

	/**
	 * Quick inquiry form (destination/event pages).
	 */
	private static function render_quick(int $destination_id, string $destination_label): void {
		if ($destination_id && $destination_label) : ?>
			<p class="safari-lead-form__context"><?php echo esc_html(sprintf(__('Inquiry about: %s', 'safari-leads'), $destination_label)); ?></p>
		<?php endif; ?>
		<div class="safari-lead-form__row">
			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slfq-date-from"><?php esc_html_e('Preferred dates', 'safari-leads'); ?></label>
				<input type="date" id="slfq-date-from" name="date_from" class="safari-lead-form__input" min="<?php echo esc_attr(date('Y-m-d')); ?>">
			</div>
			<div class="safari-lead-form__field">
				<label class="safari-lead-form__label" for="slfq-adults"><?php esc_html_e('Travellers', 'safari-leads'); ?></label>
				<input type="number" id="slfq-adults" name="adults" class="safari-lead-form__input" min="1" max="99" value="2">
			</div>
		</div>
		<?php self::render_contact_fields(false); ?>
		<?php
	}

	/**
	 * Standard contact form.
	 */
	private static function render_contact(): void {
		?>
		<div class="safari-lead-form__field">
			<label class="safari-lead-form__label" for="slfc-subject"><?php esc_html_e('Subject', 'safari-leads'); ?></label>
			<input type="text" id="slfc-subject" name="subject" class="safari-lead-form__input" autocomplete="off" maxlength="190">
		</div>
		<?php self::render_contact_fields(true); ?>
		<?php
	}

	/**
	 * Shared contact fields (name, email, phone, message, consent).
	 *
	 * @param bool $include_message Include the message textarea.
	 */
	private static function render_contact_fields(bool $include_message): void {
		?>
		<div class="safari-lead-form__field safari-lead-form__field--required">
			<label class="safari-lead-form__label" for="slf-name">
				<?php esc_html_e('Full name', 'safari-leads'); ?>
				<span class="safari-lead-form__required" aria-hidden="true">*</span>
			</label>
			<input
				type="text"
				id="slf-name"
				name="name"
				class="safari-lead-form__input"
				required
				autocomplete="name"
				aria-required="true"
				aria-describedby="slf-name-error"
				maxlength="190"
			>
			<span class="safari-lead-form__error" id="slf-name-error" role="alert" aria-live="polite"></span>
		</div>

		<div class="safari-lead-form__field safari-lead-form__field--required">
			<label class="safari-lead-form__label" for="slf-email">
				<?php esc_html_e('Email address', 'safari-leads'); ?>
				<span class="safari-lead-form__required" aria-hidden="true">*</span>
			</label>
			<input
				type="email"
				id="slf-email"
				name="email"
				class="safari-lead-form__input"
				required
				autocomplete="email"
				aria-required="true"
				aria-describedby="slf-email-error"
				inputmode="email"
			>
			<span class="safari-lead-form__error" id="slf-email-error" role="alert" aria-live="polite"></span>
		</div>

		<div class="safari-lead-form__field">
			<label class="safari-lead-form__label" for="slf-phone">
				<?php esc_html_e('Phone (optional)', 'safari-leads'); ?>
			</label>
			<input
				type="tel"
				id="slf-phone"
				name="phone"
				class="safari-lead-form__input safari-lead-form__tel"
				autocomplete="tel"
				inputmode="tel"
				maxlength="50"
			>
		</div>

		<?php if ($include_message) : ?>
		<div class="safari-lead-form__field">
			<label class="safari-lead-form__label" for="slf-message">
				<?php esc_html_e('Your message', 'safari-leads'); ?>
			</label>
			<textarea
				id="slf-message"
				name="message"
				class="safari-lead-form__textarea"
				rows="4"
				aria-describedby="slf-message-hint"
			></textarea>
			<span class="safari-lead-form__hint" id="slf-message-hint">
				<?php esc_html_e('Tell us about your dream safari — destinations, experiences, anything special.', 'safari-leads'); ?>
			</span>
		</div>
		<?php endif; ?>

		<div class="safari-lead-form__field safari-lead-form__field--checkbox safari-lead-form__field--required">
			<label class="safari-lead-form__checkbox-label">
				<input type="checkbox" name="consent_privacy" value="1" class="safari-lead-form__checkbox" required aria-required="true">
				<?php
				printf(
					/* translators: %s: privacy policy link */
					wp_kses_post(__('I agree to the %s. *', 'safari-leads')),
					'<a href="' . esc_url((string) get_privacy_policy_url()) . '" target="_blank" rel="noopener">' . esc_html__('Privacy Policy', 'safari-leads') . '</a>'
				);
				?>
			</label>
		</div>

		<div class="safari-lead-form__field safari-lead-form__field--checkbox">
			<label class="safari-lead-form__checkbox-label">
				<input type="checkbox" name="consent_marketing" value="1" class="safari-lead-form__checkbox">
				<?php esc_html_e('Keep me updated with safari offers and travel inspiration.', 'safari-leads'); ?>
			</label>
		</div>
		<?php
	}

	// ── Assets ────────────────────────────────────────────────────────────

	public static function enqueue_assets(): void {
		/*
		 * The form's submit handler, step transitions and validation live in
		 * the theme's single JS runtime (theme/assets/src/js/lead-form.js,
		 * bundled by Vite into the `safari-main` handle). This plugin
		 * deliberately ships no second copy: a duplicate would be a second
		 * source of truth for the same behaviour, and the file this code used
		 * to point at does not exist, which meant a 404 on every page load.
		 *
		 * All this plugin still has to contribute is Cloudflare Turnstile,
		 * which is optional and off unless a site key is configured.
		 */
		if ((string) Safari_Lead_Settings::get('turnstile_site_key') !== '') {
			wp_enqueue_script(
				'cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				['safari-main'],
				null,
				['in_footer' => true, 'strategy' => 'defer']
			);
		}
	}
}
