<?php

use App\Models\User;
use Cultpantry\Elections\Support\CityFinances;
use Cultpantry\Elections\Support\CityGrants;

describe('City grants page', function () {
    it('renders for admins and invited viewers, not customers', function () {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.grants'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vendor/elections/Grants/Show', false)
                ->has('grants.ledger', count(CityGrants::sheet()['ledger']))
                ->has('grants.peers'));

        $viewer = User::factory()->create(['role' => 'customer']);
        $viewer->forceFill(['admin_permissions' => ['elections']])->save();
        $this->actingAs($viewer->fresh())->get(route('admin.elections.grants'))->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('admin.elections.grants'))
            ->assertForbidden();
    });

    it('shows a card for it on the City Plans + Finances tab', function () {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.elections.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('grants.slug', CityGrants::SLUG)
                ->has('grants.stats', 4));
    });

    it('cites only sources it lists, each with a link', function () {
        $sheet = CityGrants::sheet();
        $cited = collect([...$sheet['stats'], ...$sheet['ledger'], ...$sheet['analysis'], ...$sheet['powell_river']['points']])->flatMap(fn ($item) => $item['sources']);

        expect($cited)->not->toBeEmpty();
        foreach ($cited as $key) {
            expect($sheet['sources'])->toHaveKey($key);
        }
        foreach ($sheet['sources'] as $source) {
            expect($source['url'])->toStartWith('https://');
        }
    });

    it('states headline counts that match the ledger', function () {
        $sheet = CityGrants::sheet();
        $ledger = collect($sheet['ledger']);
        $known = $ledger->whereIn('outcome', ['funded', 'not_funded', 'no_record']);
        $funded = $known->where('outcome', 'funded');

        expect($ledger->pluck('outcome')->unique()->diff(['funded', 'not_funded', 'no_record', 'pending', 'unknown', 'not_submitted']))->toBeEmpty()
            ->and($sheet['stats'][0]['value'])->toBe((string) $ledger->count())
            ->and($sheet['stats'][1]['value'])->toBe("{$funded->count()} of {$known->count()}")
            ->and($sheet['stats'][1]['label'])->toContain('('.round($funded->count() / $known->count() * 100).'%)');

        // Each period counts the known results for applications authorized in those years.
        $eras = [[2016, 2019], [2020, 2022], [2023, 2026]];
        foreach ($sheet['eras'] as $i => $era) {
            [$from, $to] = $eras[$i];
            $inEra = $known->filter(fn ($r) => $r['year'] >= $from && $r['year'] <= $to);
            expect($era['decided'])->toBe($inEra->count())
                ->and($era['funded'])->toBe($inEra->where('outcome', 'funded')->count());
        }
    });

    it('states money totals that match the yearly figures', function () {
        $sheet = CityGrants::sheet();
        $applied = array_sum(array_column($sheet['years'], 'applied'));
        $formula = array_sum(array_column($sheet['years'], 'formula'));

        expect($sheet['stats'][2]['value'])->toBe('$'.number_format($applied / 1e6, 1).'M')
            ->and($sheet['analysis'][2]['text'])->toContain('$'.number_format($formula / 1e6, 1).' million')
            ->and($sheet['analysis'][2]['text'])->toContain('$'.number_format(($formula + $applied) / 1e6, 1).' million');
    });

    it('ranks Salmon Arm consistently with the peer figures', function () {
        $sheet = CityGrants::sheet();
        $values = array_column($sheet['peers'], 'per_resident');
        $salmonArm = collect($sheet['peers'])->firstWhere('name', 'Salmon Arm');
        $sorted = $values;
        sort($sorted);
        $median = $sorted[intdiv(count($sorted), 2)];
        $closest = collect($sheet['groups'])->last();

        expect($values)->toBe(collect($values)->sortDesc()->values()->all())
            ->and($closest['of'])->toBe(count($values))
            ->and($closest['median'])->toBe($median)
            ->and($closest['rank'])->toBe(count(array_filter($values, fn ($v) => $v > $salmonArm['per_resident'])) + 1)
            ->and($sheet['stats'][3]['value'])->toBe('$'.$salmonArm['per_resident'])
            ->and($sheet['stats'][3]['label'])->toContain('$'.$median);
    });

    it('explains Powell River with figures that match the peer list', function () {
        $sheet = CityGrants::sheet();
        $powellRiver = collect($sheet['peers'])->firstWhere('name', 'Powell River');
        $withoutPlant = (int) round((100_989_199 - 55_730_800) / $powellRiver['population'] / 10);

        expect($sheet['powell_river']['intro'])->toContain('$'.$powellRiver['per_resident'])
            ->and($sheet['powell_river']['points'][0]['text'])->toContain('$'.$powellRiver['per_resident'].' to about $'.$withoutPlant);
    });

    it('prices the sewage plant grant with the finances page borrowing terms', function () {
        $r = CityFinances::RATE;
        $payment = 7_000_000 * $r / (1 - (1 + $r) ** -CityFinances::TERM_YEARS);

        expect(CityGrants::sheet()['analysis'][3]['text'])->toContain('$'.number_format(round($payment, -3)).' a year');
    });

    it('feeds the update badges', function () {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('admin.elections.updates'))
            ->assertOk()
            ->assertJsonPath('sections.plans.'.CityGrants::SLUG, fn ($at) => is_int($at));
    });
});
