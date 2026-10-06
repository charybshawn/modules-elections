<?php

namespace Cultpantry\Elections\Support;

/**
 * Elections -> City finances: where the City's money comes from and goes,
 * its debt and reserves, the big capital projects ahead, and an analysis of
 * what borrowing for them could mean.
 *
 * Facts come from the City's audited 2025 financial statements and pages
 * opened and read (each figure names its source key in `sources`). The
 * "what it could mean" section is AI-assisted analysis built only on those
 * facts, with its assumptions stated; it is labelled as such on the page.
 * Derived figures (percentages, scenario payments) are computed from the
 * sourced numbers -- see the comments where they are.
 *
 * When the City publishes its 2026 statements, update the figures, the
 * `read_on` date and the scenario arithmetic together.
 */
class CityFinances
{
    /** MFA 10-year long-term rate (mfa.bc.ca, Oct. 5, 2026), used for the borrowing illustrations. */
    public const RATE = 0.0454;

    public const TERM_YEARS = 30;

    /** Key for this page in the update feed. */
    public const SLUG = 'city-finances';

    public static function sheet(): array
    {
        $fs = 'fs2025';

        return [
            'title' => 'City finances',
            'subtitle' => 'Where Salmon Arm\'s money comes from, where it goes, and the big bills ahead.',
            'read_on' => '2026-10-06',
            'year' => 2025,

            'sources' => [
                'fs2025' => ['label' => 'City of Salmon Arm, audited financial statements for 2025', 'url' => 'https://www.salmonarm.ca/ArchiveCenter/ViewFile/Item/1286', 'date' => '2025-12-31'],
                'castBudget' => ['label' => 'Castanet: council approves 2026 budget with a 3.88% tax increase', 'url' => 'https://www.castanet.net/news/Salmon-Arm/590228/Salmon-Arm-city-council-approves-2026-budget-with-3-88-tax-increase', 'date' => '2025-12-19'],
                'obsBudget' => ['label' => 'Salmon Arm Observer: council taps parkade reserve in the 2026 budget', 'url' => 'https://saobserver.net/2025/12/18/salmon-arm-council-taps-into-parkade-reserve-to-bolster-priorities-in-2026-budget/', 'date' => '2025-12-18'],
                'obsFiveYear' => ['label' => 'Salmon Arm Observer: five per cent tax increase proposed for 2026', 'url' => 'https://saobserver.net/2025/12/15/five-per-cent-tax-increase-proposed-for-salmon-arm-in-2026/', 'date' => '2025-12-15'],
                'pentWpcc' => ['label' => 'Penticton Western News: sewage plant upgrade now estimated at $78M', 'url' => 'https://pentictonwesternnews.com/2025/07/22/salmon-arm-sewage-treatment-plan-upgrade-now-estimated-to-cost-78m/', 'date' => '2025-07-22'],
                'obsWpcc' => ['label' => 'Salmon Arm Observer: $7M design contract for the sewage plant upgrade', 'url' => 'https://saobserver.net/2026/09/25/salmon-arm-looking-at-7m-bill-for-engineering-design-works-for-sewage-plant-upgrade/', 'date' => '2026-09-25'],
                'castWestBay' => ['label' => 'Castanet: West Bay Connector Trail estimated at $57 million', 'url' => 'https://www.castanet.net/news/Salmon-Arm/553441/Preliminary-estimate-for-West-Bay-Connector-Trail-puts-it-at-57-million', 'date' => '2025-05-31'],
                'castRec' => ['label' => 'Castanet: council hears 22% of recreation facility users live outside the city', 'url' => 'https://www.castanet.net/news/Salmon-Arm/540619/Salmon-Arm-council-eyes-differential-pricing-upon-hearing-22-of-facility-users-hail-from-outside-city', 'date' => '2025-03-26'],
                'evnRec' => ['label' => 'Eagle Valley News: mayor says now is not the time to build a new recreation facility', 'url' => 'https://eaglevalleynews.com/2026/02/09/salmon-arm-mayor-says-now-is-not-the-time-to-build-new-recreation-facility/', 'date' => '2026-02-09'],
                'ocp' => ['label' => 'Official Community Plan Bylaw 4707, p. 60 (active transportation costs)', 'url' => 'https://salmonarm.ca/DocumentCenter/View/52#page=60', 'date' => '2025-12-08'],
                'mfa' => ['label' => 'Municipal Finance Authority of BC: current lending rates', 'url' => 'https://mfa.bc.ca/', 'date' => '2026-10-05'],
                'charter' => ['label' => 'Community Charter, s. 165 (balanced financial plan) and s. 174 (borrowing limits)', 'url' => 'https://www.bclaws.gov.bc.ca/civix/document/id/complete/statreg/03026_06', 'date' => null],
                'liabReg' => ['label' => 'Municipal Liabilities Regulation, s. 2 (the 25% servicing limit)', 'url' => 'https://www.bclaws.gov.bc.ca/civix/document/id/complete/statreg/254_2004', 'date' => null],
            ],

            // Statement of Operations and Statement of Financial Position, 2025.
            'stats' => [
                ['value' => '$49.3M', 'label' => 'revenue in 2025 (up 4.9%)', 'sources' => [$fs]],
                ['value' => '$41.1M', 'label' => 'spent on services in 2025 (up 1.5%)', 'sources' => [$fs]],
                ['value' => '$17.2M', 'label' => 'long-term debt, down from $19.9M', 'sources' => [$fs]],
                ['value' => '3.88%', 'label' => '2026 property tax increase (staff first sought 11%)', 'sources' => ['castBudget', 'obsBudget']],
            ],

            // Statement of Operations, 2025 actuals; groupings ours, totals reconcile to $49,288,098.
            'revenue' => [
                'total' => 49288098,
                'items' => [
                    ['label' => 'Property taxes (City share)', 'amount' => 24130799],
                    ['label' => 'User fees and sales of services', 'amount' => 9727893],
                    ['label' => 'Parcel and frontage taxes', 'amount' => 4073625],
                    ['label' => 'Grants from other governments', 'amount' => 3719527],
                    ['label' => 'Investment income', 'amount' => 3277408],
                    ['label' => 'Permits, rentals, penalties and other', 'amount' => 2621764],
                    ['label' => 'Developer contributions', 'amount' => 1737082],
                ],
                'note' => 'Investment income fell $1.1M from 2024 as the return on the City\'s pooled investments dropped from 4.82% to 2.88%.',
                'sources' => [$fs],
            ],

            'expenses' => [
                'total' => 41066378,
                'items' => [
                    ['label' => 'Transportation (roads, sidewalks, transit, airport)', 'amount' => 11134372],
                    ['label' => 'Water and sewer', 'amount' => 7576079],
                    ['label' => 'Police, fire and bylaw', 'amount' => 7120130],
                    ['label' => 'General government', 'amount' => 6544992],
                    ['label' => 'Recreation and culture', 'amount' => 4825371],
                    ['label' => 'Environment, health and development', 'amount' => 3865434],
                ],
                'note' => 'Wages and benefits are a third of spending ($13.6M). Another quarter ($9.9M) is amortization: the yearly wear on roads, pipes and buildings, which the City must eventually pay to replace.',
                'sources' => [$fs],
            ],

            // Note 11, Taxation: taxes collected vs collections for other governments.
            'tax_bill' => [
                'total' => 43666061,
                'items' => [
                    ['label' => 'City of Salmon Arm', 'amount' => 28204424],
                    ['label' => 'Province (school taxes)', 'amount' => 11109752],
                    ['label' => 'Hospital district, regional district, library and others', 'amount' => 4351885],
                ],
                'note' => 'Of every tax dollar the City collected in 2025, about 65 cents stayed with the City; about 25 cents went to the Province for schools.',
                'sources' => [$fs],
            ],

            // Notes 8 (debt), 2 (investments), 10 (reserves), 9 (capital assets).
            'balance' => [
                ['label' => 'Long-term debt', 'value' => '$17.2M', 'detail' => 'Mostly City Hall and the Ross Street underpass. Interest paid in 2025: $1.43M. Principal due in 2026: $0.90M.', 'sources' => [$fs]],
                ['label' => 'Investments', 'value' => '$93.9M', 'detail' => 'Much of it is spoken for: $17.4M in development cost charges held for growth projects and $17.9M in statutory reserves for set purposes.', 'sources' => [$fs]],
                ['label' => 'Statutory reserves', 'value' => '$17.9M', 'detail' => 'Largest: Growing Communities Fund $4.0M, sewer major maintenance $3.0M, equipment replacement $2.6M, general capital $2.2M, water major maintenance $2.0M.', 'sources' => [$fs]],
                ['label' => 'How worn the assets are', 'value' => '47%', 'detail' => 'Roads, pipes, buildings and equipment are on average 47% through their recorded life, measured at original cost (today\'s replacement cost is higher).', 'sources' => [$fs]],
                ['label' => 'Reinvestment', 'value' => '1.2×', 'detail' => 'The City spent $12.1M on capital in 2025, 1.2 times the $9.9M its assets wore out by.', 'sources' => [$fs]],
            ],

            'budget_2026' => [
                ['text' => 'Staff\'s first draft needed an 11% tax increase; management cuts brought it to 5%, and council finished at 3.88%.', 'sources' => ['obsBudget', 'castBudget']],
                ['text' => 'The gap was closed largely with one-time money: about $1.7M moved out of the parkade reserve, leaving about $363,000, and yearly transfers to the wharf and parking reserves were paused.', 'sources' => ['castBudget', 'obsFiveYear']],
                ['text' => 'Policing was budgeted to rise by $515,000, mostly for staffing.', 'sources' => ['obsFiveYear']],
                ['text' => 'Road paving gets about $1.2M a year from the parcel tax, which the mayor said is "not enough to maintain the roads that we have"; $500,000 more was added for 2026.', 'sources' => ['obsBudget']],
            ],

            // Capital projects ahead with a published cost. Bars are scaled against today's debt.
            'projects' => [
                'reference' => ['label' => 'Today\'s total long-term debt', 'amount' => 17177040],
                'items' => [
                    ['label' => 'Sewage treatment plant (WPCC Stage IV)', 'amount' => 100000000, 'term' => 'Short term in the strategic plan', 'detail' => 'About $100M early estimate (Sept. 2026), up from $78.5M (July 2025) and $14M (2004). A $7M grant application is in; long-term borrowing is planned for the rest.', 'sources' => ['obsWpcc', 'pentWpcc']],
                    ['label' => 'Pool and recreation centre', 'amount' => 60000000, 'term' => 'Medium term', 'detail' => 'A councillor put new pools at "about $60 million" (March 2025); no City estimate has been published. In February 2026 the mayor paused talks on a new indoor recreation facility, saying the City must "focus on core infrastructure needs".', 'sources' => ['castRec', 'evnRec']],
                    ['label' => 'West Bay Connector Trail', 'amount' => 57000000, 'term' => 'Medium term', 'detail' => 'Preliminary estimate $57M (May 2025); the mayor said the City cannot afford it without federal and provincial money.', 'sources' => ['castWestBay']],
                    ['label' => 'Active transportation network (full plan)', 'amount' => 90000000, 'term' => 'Years to decades', 'detail' => 'The OCP puts the whole walking and cycling network at over $90M (2022 costs), to be built over many years.', 'sources' => ['ocp']],
                ],
                'note' => 'Other plan projects (Lakeshore Road, the Auto Road Connector, the 4 Avenue Connector, a downtown parkade) have no published total cost. See City plans → Strategic Plan progress for the project-by-project record.',
            ],

            // Scenario arithmetic: level annual payment = amount × r / (1 − (1 + r)^−n),
            // r = 4.54% (MFA 10-year rate), n = 30 years. Servicing ratio = (current interest
            // $1,430,460 + 2026 principal $903,476 + new payments) / 2025 revenue $49,288,098.
            'scenarios' => [
                'limit_pct' => 25,
                'current' => ['label' => 'Today', 'annual' => 2333936, 'pct' => 4.7],
                'steps' => [
                    ['label' => '+ sewage plant ($93M after the $7M grant)', 'annual' => 8070247, 'pct' => 16.4],
                    ['label' => '+ recreation centre ($60M)', 'annual' => 11771094, 'pct' => 23.9],
                    ['label' => '+ West Bay trail ($57M)', 'annual' => 15286897, 'pct' => 31.0],
                ],
                'payments' => [
                    ['label' => 'Sewage plant, $93M', 'annual' => 5736311, 'compare' => 'about 127% of everything the sewer utility collected in 2025 ($4.5M)'],
                    ['label' => 'Recreation centre, $60M', 'annual' => 3700846, 'compare' => 'about a 15% property tax increase at 2025 levels'],
                    ['label' => 'West Bay trail, $57M', 'annual' => 3515804, 'compare' => 'about a 15% property tax increase at 2025 levels'],
                ],
                'rule_of_thumb' => 'Every $1 million of new yearly cost is about a 4.1% property tax increase at 2025 levels ($24.1M raised).',
                'assumptions' => 'Illustration only: each project fully borrowed over 30 years at 4.54% (the Municipal Finance Authority\'s 10-year rate on Oct. 5, 2026), with no other grants, development cost charges or partners. The ratio uses total 2025 revenue; the regulation uses a narrower "calculation revenue", so real headroom is likely somewhat smaller, though revenue growth would raise it over time.',
                'sources' => [$fs, 'mfa', 'liabReg'],
            ],

            // Property tax scenarios: the same level-payment formula as 'scenarios' (4.54%, 30
            // years), with each yearly payment set against 2025 figures: City property taxes
            // $24,130,799 (Statement of Operations) and all property taxes collected on the
            // tax notice $39,240,788 (Note 11, incl. school and regional levies). Active
            // transportation network payment on $90M: $5,551,269.
            'tax_scenarios' => [
                'intro' => 'How much property taxes might have to rise if these projects were borrowed for and paid entirely from property tax, holding everything else at 2025 levels.',
                'items' => [
                    [
                        'label' => 'Sewage plant only',
                        'projects' => 'Sewage treatment plant ($93M after the $7M grant)',
                        'borrowed' => 93000000,
                        'annual' => 5736311,
                        'city_pct' => 23.8,
                        'bill_pct' => 14.6,
                        'per_thousand' => 238,
                        'servicing_pct' => 16.4,
                        'note' => 'The plant is more likely to be charged to sewer users through sewer fees and frontage tax than to general taxes. Charged that way, it would mean sewer charges more than doubling: the payments are about 127% of what sewer users paid in 2025.',
                    ],
                    [
                        'label' => 'Sewage plant and recreation centre',
                        'projects' => 'Sewage plant ($93M) and a pool and recreation centre ($60M)',
                        'borrowed' => 153000000,
                        'annual' => 9437157,
                        'city_pct' => 39.1,
                        'bill_pct' => 24.0,
                        'per_thousand' => 391,
                        'servicing_pct' => 23.9,
                        'note' => 'This would bring debt payments to just under the 25% provincial cap, leaving almost no room to borrow for anything else.',
                    ],
                    [
                        'label' => 'All four projects',
                        'projects' => 'Sewage plant ($93M), recreation centre ($60M), West Bay trail ($57M) and the full active transportation network ($90M)',
                        'borrowed' => 300000000,
                        'annual' => 18504230,
                        'city_pct' => 76.7,
                        'bill_pct' => 47.2,
                        'per_thousand' => 767,
                        'servicing_pct' => 42.3,
                        'note' => 'Debt payments would reach about 42% of revenue, far over the 25% cap, so this could not be borrowed without provincial approval. It shows the scale, not a likely path: these projects depend on grants and would be spread over many years.',
                    ],
                ],
                'how_to_read' => 'To estimate your own increase, find the City of Salmon Arm (municipal) line on your tax notice: each $1,000 you pay there would rise by the amount shown. School, hospital and regional levies are not affected.',
                'caveats' => [
                    'Grants, development cost charges and partners would lower these numbers; so would spreading projects over more years.',
                    'Growth spreads the cost: new homes and businesses add to the tax base, so the increase per existing household would be smaller over time.',
                    'Interest rates, construction costs and the final project scopes are all still moving; the sewage plant estimate has already risen from $78.5M to about $100M in a year.',
                    'Increases from other pressures (wages, policing, road maintenance) would come on top of these.',
                ],
                'sources' => ['fs2025', 'mfa', 'obsWpcc', 'castRec', 'castWestBay', 'ocp', 'liabReg'],
            ],

            // AI-assisted analysis, built only on the facts above.
            'analysis' => [
                ['heading' => 'Not bankruptcy, but trade-offs', 'text' => 'A B.C. city cannot run a deficit budget: its yearly financial plan must balance by law, and its yearly debt payments are capped at 25% of revenue unless the Province approves more. So "solvency" pressure does not show up as default. It shows up as higher taxes and utility fees, projects delayed or shrunk, maintenance put off, and reserves drawn down.', 'sources' => ['charter', 'liabReg']],
                ['heading' => 'The sewage plant is the defining bill', 'text' => 'At about $100M, the plant upgrade costs nearly six times today\'s entire debt. Borrowed in full, its payments would exceed everything sewer users paid in 2025, so sewer rates and frontage taxes would likely have to rise substantially unless grants, development cost charges or senior-government money cover a large share. Cost and funding are still being worked out, and the estimate has risen sharply since 2022.', 'sources' => ['obsWpcc', 'pentWpcc', $fs]],
                ['heading' => 'The big projects cannot all be borrowed at once', 'text' => 'Borrowing for the plant, a new recreation centre and the West Bay trail together would push debt payments from about 5% of revenue to about 31%, over the provincial cap. In practice that means sequencing (the mayor has already paused talks on a new indoor recreation facility to "focus on core infrastructure needs"), and that grants decide what gets built.', 'sources' => ['evnRec', 'liabReg']],
                ['heading' => 'Pressure on the day-to-day budget is already visible', 'text' => 'The 2026 budget needed an 11% increase before cuts and one-time reserve money brought it to 3.88%. One-time money cannot be reused, investment income is falling, policing costs are rising, and the mayor says road paving is underfunded. These pressures arrive before any big borrowing.', 'sources' => ['obsBudget', 'castBudget', 'obsFiveYear', $fs]],
                ['heading' => 'The starting position is relatively strong', 'text' => 'Debt is low and falling, the City ran surpluses in 2024 and 2025, and it holds sizeable investments and reserves. That gives room to borrow for one major project. The question for the next council is less "can the City pay" than "which projects, in what order, and how much will taxes and utility rates rise to pay for them".', 'sources' => [$fs]],
            ],

            'questions' => [
                'How should the sewage plant be paid for between sewer users, development charges, grants and general taxes?',
                'Which big project comes after the sewage plant, and what tax increase would it take?',
                'How will the City replace the one-time reserve money used to hold down the 2026 increase?',
                'Is the City saving enough to replace aging roads, pipes and buildings?',
            ],

            'gaps' => [
                'The City\'s 2026–2030 financial plan is not readable here: its website links point to the wrong documents, and the budget reports are behind a bot check. Figures are from the audited 2025 statements, with 2026 decisions from news coverage.',
            ],
        ];
    }
}
