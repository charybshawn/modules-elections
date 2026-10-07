<?php

use App\Models\User;
use Cultpantry\Elections\Support\CityFinances;

describe('City finances page', function () {
    it('renders for admins and invited viewers, not customers', function () {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.finances'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Finances/Show', false)
                ->has('finances.revenue.items', 7)
                ->has('finances.analysis'));

        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();
        $this->actingAs($viewer->fresh())->get(route('admin.elections.finances'))->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('admin.elections.finances'))
            ->assertForbidden();
    });

    it('cites only sources it lists', function () {
        $sheet = CityFinances::sheet();
        $cited = collect([
            ...$sheet['stats'], $sheet['revenue'], $sheet['expenses'], $sheet['tax_bill'],
            ...$sheet['balance'], ...$sheet['budget_2026'], ...$sheet['projects']['items'],
            $sheet['scenarios'], ...$sheet['analysis'],
        ])->flatMap(fn ($item) => $item['sources']);

        expect($cited)->not->toBeEmpty();
        foreach ($cited as $key) {
            expect($sheet['sources'])->toHaveKey($key);
        }
    });

    it('reconciles its breakdowns to the audited totals', function () {
        $sheet = CityFinances::sheet();
        foreach (['revenue', 'expenses', 'tax_bill'] as $part) {
            expect(array_sum(array_column($sheet[$part]['items'], 'amount')))->toBe($sheet[$part]['total']);
        }
    });

    it('matches the stated borrowing arithmetic', function () {
        $r = CityFinances::RATE;
        $n = CityFinances::TERM_YEARS;
        $payment = fn (float $amount) => (int) round($amount * $r / (1 - (1 + $r) ** -$n));
        $sheet = CityFinances::sheet();

        expect($sheet['scenarios']['payments'][0]['annual'])->toBe($payment(93_000_000))
            ->and($sheet['scenarios']['payments'][1]['annual'])->toBe($payment(60_000_000))
            ->and($sheet['scenarios']['payments'][2]['annual'])->toBe($payment(90_000_000))
            ->and($sheet['scenarios']['steps'][2]['annual'])
            ->toBe($sheet['scenarios']['current']['annual'] + $payment(93_000_000) + $payment(60_000_000) + $payment(90_000_000));
    });

    it('derives each property tax scenario from the stated payments and 2025 tax totals', function () {
        $r = CityFinances::RATE;
        $n = CityFinances::TERM_YEARS;
        $payment = fn (float $amount) => (int) round($amount * $r / (1 - (1 + $r) ** -$n));
        $cityTax = 24130799;   // City property taxes, 2025
        $allTax = 39240788;    // all property taxes on the notice, 2025
        $revenue = 49288098;
        $current = 2333936;

        $projects = [[93_000_000], [93_000_000, 60_000_000], [93_000_000, 60_000_000, 90_000_000]];
        foreach (CityFinances::sheet()['tax_scenarios']['items'] as $i => $s) {
            $annual = array_sum(array_map($payment, $projects[$i]));
            expect($s['annual'])->toBe($annual)
                ->and($s['borrowed'])->toBe(array_sum($projects[$i]))
                ->and($s['city_pct'])->toBe(round($annual / $cityTax * 100, 1))
                ->and($s['bill_pct'])->toBe(round($annual / $allTax * 100, 1))
                ->and($s['per_thousand'])->toBe((int) round($annual / $cityTax * 1000))
                ->and($s['servicing_pct'])->toBe(round(($current + $annual) / $revenue * 100, 1));
        }
    });
});
