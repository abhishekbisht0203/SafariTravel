<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

/**
 * The Lead model's status vocabulary and query scopes.
 */
class LeadTest extends TestCase
{
    public function test_the_status_vocabulary_matches_the_wordpress_plugin(): void
    {
        // Safari_Lead_DB::STATUSES in plugins/safari-leads.
        $expected = [
            'new' => 'New',
            'in_progress' => 'In progress',
            'contacted' => 'Contacted',
            'closed_won' => 'Closed won',
            'closed_lost' => 'Closed lost',
            'spam' => 'Spam',
        ];

        $this->assertSame($expected, Lead::STATUSES);
    }

    public function test_closed_statuses_are_a_subset_of_all_statuses(): void
    {
        foreach (Lead::CLOSED_STATUSES as $status) {
            $this->assertArrayHasKey($status, Lead::STATUSES);
        }
    }

    public function test_it_knows_when_a_lead_is_closed(): void
    {
        $this->assertFalse((new Lead(['status' => Lead::STATUS_CONTACTED]))->isClosed());

        foreach (Lead::CLOSED_STATUSES as $status) {
            $this->assertTrue((new Lead(['status' => $status]))->isClosed());
        }
    }

    public function test_it_has_a_human_label_for_every_status(): void
    {
        $this->assertSame('Closed won', (new Lead(['status' => 'closed_won']))->statusLabel());

        // An unknown status must not produce an empty label.
        $this->assertNotSame('', (new Lead(['status' => 'teleported']))->statusLabel());
    }

    public function test_the_real_scope_excludes_spam_by_default(): void
    {
        $sql = $this->sql(Lead::query()->real());

        $this->assertStringContainsString('"status" != ?', $sql);
        $this->assertSame([Lead::STATUS_SPAM], Lead::query()->real()->getBindings());
    }

    public function test_the_real_scope_can_include_spam(): void
    {
        $this->assertStringNotContainsString('!=', $this->sql(Lead::query()->real(true)));
        $this->assertSame([], Lead::query()->real(true)->getBindings());
    }

    public function test_the_status_scope_is_a_no_op_without_a_value(): void
    {
        $this->assertSame([], Lead::query()->status(null)->getBindings());
        $this->assertSame([], Lead::query()->status('')->getBindings());
    }

    public function test_the_status_scope_filters(): void
    {
        $query = Lead::query()->status('contacted');

        $this->assertStringContainsString('"status" = ?', $this->sql($query));
        $this->assertSame(['contacted'], $query->getBindings());
    }

    public function test_the_search_scope_escapes_like_wildcards(): void
    {
        $query = Lead::query()->search('100%_sure');

        // A literal % or _ in a search term must not turn into a wildcard, or a
        // visitor could enumerate every lead by searching for "%".
        foreach ($query->getBindings() as $binding) {
            $this->assertIsString($binding);
            $this->assertSame('%100\\%\\_sure%', $binding);
        }

        $this->assertStringContainsString('or', strtolower($this->sql($query)));
    }

    public function test_the_search_scope_is_a_no_op_for_blank_terms(): void
    {
        $this->assertSame([], Lead::query()->search('   ')->getBindings());
        $this->assertSame([], Lead::query()->search(null)->getBindings());
    }

    /**
     * SQL for a query, with the grammar quoted so the assertions do not depend
     * on the driver.
     */
    private function sql(Builder $query): string
    {
        return $query->toSql();
    }
}
