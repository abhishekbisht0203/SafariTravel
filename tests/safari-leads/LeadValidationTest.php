<?php
/**
 * Lead intake validation, as the WordPress endpoint performs it.
 *
 * Safari_Lead_Save::validate() is the server-authoritative gate every
 * submission passes through. These tests run the real, unmodified method, so a
 * regression here is a regression in production.
 *
 * @package Safari_Travel\Tests
 */

declare(strict_types=1);

namespace Safari\Tests\Safari_Leads;

use Safari\Tests\WordPressTestCase;

/**
 * @covers \Safari_Lead_Save
 */
final class LeadValidationTest extends WordPressTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->loadPluginClass( 'plugins/safari-leads/inc/class-safari-lead-save.php' );
	}

	/**
	 * A minimal submission that must always validate.
	 *
	 * @param  array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function payload( array $overrides = array() ): array {
		return array_merge(
			array(
				'name'            => 'Amara Okafor',
				'email'           => 'amara@example.com',
				'consent_privacy' => 1,
			),
			$overrides
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Required fields
	|--------------------------------------------------------------------------
	*/

	public function test_a_minimal_valid_submission_is_accepted(): void {
		$data = \Safari_Lead_Save::validate( $this->payload() );

		$this->assertIsArray( $data );
		$this->assertSame( 'Amara Okafor', $data['name'] );
		$this->assertSame( 'amara@example.com', $data['email'] );
		$this->assertSame( 1, $data['consent_privacy'] );
		$this->assertSame( 'contact', $data['source_form'] );
		$this->assertSame( 'new', $data['status'] );
	}

	public function test_a_missing_name_is_rejected(): void {
		$result = \Safari_Lead_Save::validate( $this->payload( array( 'name' => '' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'name', $result->get_error_data() );
	}

	public function test_an_overlong_name_is_rejected(): void {
		$result = \Safari_Lead_Save::validate(
			$this->payload( array( 'name' => str_repeat( 'a', 191 ) ) )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'name', $result->get_error_data() );
	}

	public function test_a_name_of_exactly_190_characters_is_accepted(): void {
		$data = \Safari_Lead_Save::validate(
			$this->payload( array( 'name' => str_repeat( 'a', 190 ) ) )
		);

		$this->assertIsArray( $data );
		$this->assertSame( 190, mb_strlen( $data['name'] ) );
	}

	public function test_an_invalid_email_is_rejected(): void {
		$result = \Safari_Lead_Save::validate( $this->payload( array( 'email' => 'not-an-email' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'email', $result->get_error_data() );
	}

	public function test_consent_is_required(): void {
		$result = \Safari_Lead_Save::validate( $this->payload( array( 'consent_privacy' => 0 ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'consent_privacy', $result->get_error_data() );
	}

	public function test_every_problem_is_reported_at_once(): void {
		// A form that fixes one field at a time is a bad form.
		$result = \Safari_Lead_Save::validate(
			array(
				'name'            => '',
				'email'           => 'nope',
				'consent_privacy' => 0,
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertCount( 3, $result->get_error_data() );
	}

	/*
	|--------------------------------------------------------------------------
	| Allow-lists
	|--------------------------------------------------------------------------
	*/

	public function test_an_unknown_source_form_falls_back_to_contact(): void {
		$data = \Safari_Lead_Save::validate( $this->payload( array( 'source_form' => 'evil' ) ) );

		$this->assertSame( 'contact', $data['source_form'] );
	}

	/**
	 * @dataProvider knownSourceForms
	 */
	public function test_known_source_forms_are_preserved( string $form ): void {
		$data = \Safari_Lead_Save::validate( $this->payload( array( 'source_form' => $form ) ) );

		$this->assertSame( $form, $data['source_form'] );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function knownSourceForms(): array {
		return array(
			'plan my safari' => array( 'plan_my_safari' ),
			'tour page'     => array( 'tour_page' ),
			'contact'       => array( 'contact' ),
			'popup'         => array( 'popup' ),
			'event'         => array( 'event' ),
			'product'       => array( 'product' ),
		);
	}

	public function test_an_unknown_budget_range_becomes_null(): void {
		$data = \Safari_Lead_Save::validate( $this->payload( array( 'budget_range' => 'free_trip' ) ) );

		$this->assertNull( $data['budget_range'] );
	}

	/**
	 * @dataProvider knownBudgetRanges
	 */
	public function test_known_budget_ranges_are_preserved( string $range ): void {
		$data = \Safari_Lead_Save::validate( $this->payload( array( 'budget_range' => $range ) ) );

		$this->assertSame( $range, $data['budget_range'] );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function knownBudgetRanges(): array {
		return array(
			'under 2000'  => array( 'under_2000' ),
			'2000 to 5000' => array( '2000_5000' ),
			'5000 to 10000' => array( '5000_10000' ),
			'10000 to 20000' => array( '10000_20000' ),
			'over 20000'  => array( 'over_20000' ),
		);
	}

	public function test_an_unknown_travel_style_becomes_null(): void {
		$data = \Safari_Lead_Save::validate( $this->payload( array( 'travel_style' => 'ultra' ) ) );

		$this->assertNull( $data['travel_style'] );
	}

	/**
	 * @dataProvider knownTravelStyles
	 */
	public function test_known_travel_styles_are_preserved( string $style ): void {
		$data = \Safari_Lead_Save::validate( $this->payload( array( 'travel_style' => $style ) ) );

		$this->assertSame( $style, $data['travel_style'] );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function knownTravelStyles(): array {
		return array(
			'luxury'    => array( 'luxury' ),
			'mid range' => array( 'mid-range' ),
			'budget'    => array( 'budget' ),
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Coercion
	|--------------------------------------------------------------------------
	*/

	public function test_party_sizes_are_clamped(): void {
		$data = \Safari_Lead_Save::validate(
			$this->payload(
				array(
					'adults'   => -5,
					'children' => 5000,
				)
			)
		);

		$this->assertSame( 0, $data['adults'] );
		$this->assertSame( 999, $data['children'] );
	}

	public function test_absent_party_sizes_stay_null(): void {
		$data = \Safari_Lead_Save::validate( $this->payload() );

		$this->assertNull( $data['adults'] );
		$this->assertNull( $data['children'] );
	}

	public function test_empty_optional_text_becomes_null(): void {
		$data = \Safari_Lead_Save::validate(
			$this->payload(
				array(
					'phone'            => '',
					'subject'          => '',
					'destination_text' => '',
				)
			)
		);

		$this->assertNull( $data['phone'] );
		$this->assertNull( $data['subject'] );
		$this->assertNull( $data['destination_text'] );
	}

	public function test_zero_ids_become_null(): void {
		$data = \Safari_Lead_Save::validate(
			$this->payload(
				array(
					'destination_id' => 0,
					'tour_id'        => '0',
				)
			)
		);

		$this->assertNull( $data['destination_id'] );
		$this->assertNull( $data['tour_id'] );
	}

	public function test_ids_are_cast_to_positive_integers(): void {
		$data = \Safari_Lead_Save::validate(
			$this->payload(
				array(
					'destination_id' => '42',
					'tour_id'        => 7,
				)
			)
		);

		$this->assertSame( 42, $data['destination_id'] );
		$this->assertSame( 7, $data['tour_id'] );
	}

	/*
	|--------------------------------------------------------------------------
	| Dates
	|--------------------------------------------------------------------------
	*/

	public function test_valid_dates_are_normalised(): void {
		$data = \Safari_Lead_Save::validate(
			$this->payload(
				array(
					'date_from' => '2027-07-01',
					'date_to'   => '2027-07-12',
				)
			)
		);

		$this->assertSame( '2027-07-01', $data['date_from'] );
		$this->assertSame( '2027-07-12', $data['date_to'] );
	}

	public function test_an_absurd_date_is_discarded(): void {
		// Anything before 2000 or more than ten years out is a typo, not a plan.
		$data = \Safari_Lead_Save::validate(
			$this->payload( array( 'date_from' => '1970-01-01' ) )
		);

		$this->assertNull( $data['date_from'] );
	}

	public function test_an_unparseable_date_is_discarded(): void {
		$data = \Safari_Lead_Save::validate(
			$this->payload( array( 'date_to' => 'sometime next summer' ) )
		);

		$this->assertNull( $data['date_to'] );
	}

	/*
	|--------------------------------------------------------------------------
	| Privacy
	|--------------------------------------------------------------------------
	*/

	public function test_the_ip_is_stored_only_as_a_salted_hash(): void {
		$data = \Safari_Lead_Save::validate( $this->payload() );

		$this->assertIsArray( $data );
		$this->assertSame( 64, strlen( (string) $data['ip_hash'] ) );
		$this->assertStringNotContainsString( $this->remoteAddr, (string) $data['ip_hash'] );

		// Same address, same salt, same digest — so abuse reports still work.
		$again = \Safari_Lead_Save::validate( $this->payload() );
		$this->assertIsArray( $again );
		$this->assertSame( $data['ip_hash'], $again['ip_hash'] );
	}

	public function test_marketing_consent_defaults_to_false(): void {
		$data = \Safari_Lead_Save::validate( $this->payload() );

		$this->assertIsArray( $data );
		$this->assertSame( 0, $data['consent_marketing'] );
	}
}
