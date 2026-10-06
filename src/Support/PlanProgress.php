<?php

namespace Cultpantry\Elections\Support;

/**
 * A scoresheet for the Corporate Strategic Plan: for each of its 22 priority
 * projects, what the public record shows the City has done, what is in the
 * works, and what stands in the way, plus the major capital spending expected
 * inside the plan's years (2022 to 2031).
 *
 * Rules for editing:
 *  - Every claim cites a page that was opened and read (`sources`). Search
 *    results and summaries are not sources.
 *  - The City does not appear to publish a project-by-project progress report
 *    against this plan, so this sheet is assembled from council coverage, City
 *    web pages and City documents. Where nothing public was found the status is
 *    'unknown' and the sheet says so rather than guessing.
 *  - Neutral wording: say what happened, not whether it was good.
 *  - Bump `as_of` (and re-read the sources) whenever a row changes; it drives
 *    the "updated" badge.
 *
 * Status: complete | underway | planning | paused | unknown.
 * Timing is measured against the plan's own window for that project:
 * behind (window has closed or the dates now published fall after it),
 * within, or null when it can't be told.
 */
class PlanProgress
{
    public const SLUG = 'strategic-plan-progress';

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [self::SLUG => self::strategicPlan()];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** One-line card for the City plans index. */
    public static function card(): array
    {
        $sheet = self::strategicPlan();

        return [
            'slug' => $sheet['slug'],
            'title' => $sheet['title'],
            'short' => $sheet['short'],
            'status' => 'Checked '.date('F j, Y', strtotime($sheet['as_of'])),
            'tally' => $sheet['tally'],
        ];
    }

    private static function src(string $label, string $url, ?string $date = null): array
    {
        return ['label' => $label, 'url' => $url, 'date' => $date];
    }

    /** @return array<string, mixed> */
    public static function strategicPlan(): array
    {
        $obsLakeshore = self::src('Salmon Arm Observer: council reallocates Lakeshore Road budget for watermains', 'https://saobserver.net/2025/11/14/city-of-salmon-arm-taps-into-lakeshore-road-budget-for-watermain-projects/', '2025-11-14');
        $cityLakeshore = self::src('City of Salmon Arm: Lakeshore Road Slope Stabilization', 'https://www.salmonarm.ca/440/Lakeshore-Road-Slope-Stabilization');
        $pentWpcc = self::src('Penticton Western News: sewage plant upgrade now estimated at $78M', 'https://pentictonwesternnews.com/2025/07/22/salmon-arm-sewage-treatment-plan-upgrade-now-estimated-to-cost-78m/', '2025-07-22');
        $obsMbr = self::src('Salmon Arm Observer: membrane filtration selected for the sewage plant', 'https://saobserver.net/2026/03/10/fugitive-odour-wrangling-filtration-selected-for-salmon-arm-sewage-treatment-plant/', '2026-03-10');
        $evnWpcc = self::src('Eagle Valley News: council asked to approve $7M of design contracts', 'https://eaglevalleynews.com/2026/09/25/salmon-arm-looking-at-7m-bill-for-engineering-design-works-for-sewage-plant-upgrade/', '2026-09-25');
        $castOcp = self::src('Castanet: council to decide on metrics for evaluating OCP implementation', 'https://www.castanet.net/news/Salmon-Arm/592165/Salmon-Arm-council-to-decide-on-metrics-for-evaluating-OCP-implementation', '2026-01-03');
        $obsAcc = self::src('Salmon Arm Observer: recreation advocate says Salmon Arm is missing out without amenity cost charges', 'https://saobserver.net/2026/07/21/recreation-advocate-says-salmon-arm-missing-out-without-amenity-cost-charges/', '2026-07-21');
        $cityLtfp = self::src('City of Salmon Arm: $58,000 for a Long-Term Financial Plan for Asset Management', 'https://www.salmonarm.ca/m/newsflash/Home/Detail/469', '2026-08-07');
        $cityRfp = self::src('City of Salmon Arm: Facility Inventory and Asset Assessment request for proposals', 'https://salmonarm.ca/DocumentCenter/View/7072/RFP-Facility-Inventory-Assessment---Final', '2026-02');
        $pentRacism = self::src('Penticton Western News: grant for the second phase of the anti-racism strategy', 'https://pentictonwesternnews.com/2025/07/05/salmon-arm-pursuing-15k-grant-for-second-phase-of-anti-racism-strategy/', '2025-07-05');
        $castClimate = self::src('Castanet: council adopts new climate resiliency plan', 'https://www.castanet.net/news/Salmon-Arm/629595/Salmon-Arm-council-adopts-new-climate-resiliency-plan', '2026-08-29');
        $obsMixedUse = self::src('Salmon Arm Observer: council supports development permit for mixed-use building', 'https://saobserver.net/2026/08/14/salmon-arm-council-supports-development-permit-for-mixed-use-building/', '2026-08-14');
        $obsAuto = self::src('Salmon Arm Observer: council supports $1.67M Fortis project for Auto Road Connector', 'https://saobserver.net/2026/08/25/salmon-arm-council-supports-1-67m-fortis-project-for-auto-road-connector/', '2026-08-25');
        $obsBlackburnLights = self::src('Salmon Arm Observer: lighting installed at the Blackburn Park field', 'https://saobserver.net/2026/10/05/goals-achieved-with-lighting-installation-at-salmon-arm-playing-field/', '2026-10-05');
        $obsBlackburnMou = self::src('Salmon Arm Observer: council commits $153K towards lighting the new field', 'https://saobserver.net/2026/01/13/salmon-arm-council-commits-153k-towards-lighting-new-playing-field/', '2026-01-13');
        $obsBudget = self::src('Salmon Arm Observer: council taps into the parkade reserve for the 2026 budget', 'https://saobserver.net/2025/12/18/salmon-arm-council-taps-into-parkade-reserve-to-bolster-priorities-in-2026-budget/', '2025-12-18');
        $castBudget = self::src('Castanet: council approves 2026 budget with a 3.88% tax increase', 'https://www.castanet.net/news/Salmon-Arm/590228/Salmon-Arm-city-council-approves-2026-budget-with-3-88-tax-increase', '2025-12-19');
        $evnRec = self::src('Eagle Valley News: mayor says now is not the time to build a new recreation facility', 'https://eaglevalleynews.com/2026/02/09/salmon-arm-mayor-says-now-is-not-the-time-to-build-new-recreation-facility/', '2026-02-09');
        $castWestBay = self::src('Castanet: preliminary estimate for West Bay Connector Trail is $57 million', 'https://www.castanet.net/news/Salmon-Arm/553441/Preliminary-estimate-for-West-Bay-Connector-Trail-puts-it-at-57-million', '2025-05-31');
        $castWestBayGrant = self::src('Castanet: council puts $500K toward a federal grant application for the trail', 'https://www.castanet.net/news/Salmon-Arm/537930/Salmon-Arm-city-council-agrees-to-put-500K-toward-federal-grant-application-for-West-Bay-Connector-Trail', '2025-03-12');
        $castWestBayStart = self::src('Castanet: first steps toward the West Bay Connector Trail with a $280K contract', 'https://www.castanet.net/news/Salmon-Arm/501148/Salmon-Arm-takes-first-steps-towards-West-Bay-Connector-Trail-by-awarding-280K-contract', '2024-08-13');
        $obsMeters = self::src('Salmon Arm Observer: city to install water meters in a high-use area', 'https://saobserver.net/2026/07/17/city-to-install-water-meters-in-high-usage-area-of-salmon-arm/', '2026-07-17');
        $castMeters = self::src('Castanet: councillors call for a shift to universal metered billing', 'https://www.castanet.net/news/Salmon-Arm/566795/Salmon-Arm-councillors-call-for-a-shift-to-universal-metered-billing-following-water-conservation-report', '2025-08-15');
        $evnFood = self::src('Eagle Valley News: council report on food security (Deputy Mayor column)', 'https://eaglevalleynews.com/2026/04/02/council-report-food-security-in-salmon-arm-a-collaborative-effort/', '2026-04-02');
        $obsFiveYear = self::src('Salmon Arm Observer: five per cent tax increase proposed for 2026', 'https://saobserver.net/2025/12/15/five-per-cent-tax-increase-proposed-for-salmon-arm-in-2026/', '2025-12-15');
        $evnRoundabout = self::src('Eagle Valley News: $2.4M contract for the 30th Street roundabout', 'https://eaglevalleynews.com/2026/04/10/salmon-arm-council-to-consider-2-4m-contract-for-roundabout-construction/', '2026-04-10');
        $obsRail = self::src('Salmon Arm Observer: rail crossing delay opens a grant window', 'https://saobserver.net/2026/08/11/salmon-arm-rail-crossing-construction-delay-opens-window-for-grant-application/', '2026-08-11');
        $nothing = 'No public progress report found.';

        $groups = [
            [
                'label' => 'Short term',
                'years' => '2022 to 2024',
                'note' => 'This window has closed. A project still under way is behind the plan\'s own schedule.',
                'projects' => [
                    [
                        'name' => 'Lakeshore Road improvements', 'tag' => 'Capital',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'Slope work started in 2024; the road upgrade is not yet built.',
                        'done' => [
                            'On June 10, 2024 council awarded Phase 1: an earth-fill buttress at the toe of the slope and an extended storm water outfall.',
                            'Designs for curb and gutter, a 3 m multi-use path, street lights and moving overhead wires underground are being finalized.',
                        ],
                        'next' => [
                            'The City is working to secure land and rights-of-way. In November 2025 staff said construction was expected in late 2026 or early 2027, depending on property acquisition and funding.',
                            'Construction is expected to take at least a full year, with the road open to local traffic only.',
                        ],
                        'challenges' => [
                            'Land and rights-of-way must be secured, and BC Hydro and Telus agreements reached, before work can start.',
                            'Funding: in November 2025 council moved $530,000 from the project\'s account to cover two watermain jobs. The mayor said he was "nervous" about leaving the account short; staff said it should not delay the project.',
                        ],
                        'sources' => [$cityLakeshore, $obsLakeshore],
                    ],
                    [
                        'name' => 'Wastewater Pollution Control Centre upgrade', 'tag' => 'Capital',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'Still in design; the estimate has grown from $14M to about $78.5M to $100M.',
                        'done' => [
                            'Site selection (2020 to 2021) confirmed the current site on Narcisse Street NW.',
                            'In April 2025 council awarded Brown and Caldwell $1,113,998 for a conceptual design, finished in August 2026.',
                            'On March 9, 2026 council heard staff recommend a membrane bioreactor (MBR) as the preferred process.',
                        ],
                        'next' => [
                            'The September 28, 2026 council agenda asked council to award Brown and Caldwell preliminary design ($2,037,539) and, subject to funding and review, detailed design ($4,485,670), without a competitive bid. I did not find a report of the vote.',
                            'The City plans a construction-manager-at-risk contract, which sets a guaranteed maximum price near the end of detailed design.',
                            'An application for a $7M Building Communities Strong Fund grant is in; the City says it heard the project is a strong contender.',
                        ],
                        'challenges' => [
                            'Cost: $14M in the 2004 liquid waste plan, $78.5M in July 2025, and about $100M as an early estimate in September 2026. A councillor said the City cannot afford it on its own.',
                            'Part of the 2026 design budget relies on long-term borrowing that had not been started in September 2026.',
                            'MBR costs more to operate (mostly power) than the other options; odour and lake protection were the community concerns the mayor named.',
                        ],
                        'sources' => [$pentWpcc, $obsMbr, $evnWpcc],
                    ],
                    [
                        'name' => 'Major planning bylaw review (OCP, development cost charges and zoning)', 'tag' => 'Plan',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'The OCP and the development cost charge bylaw are adopted; the zoning review is not reported.',
                        'done' => [
                            'Council adopted the new Official Community Plan on December 10, 2025.',
                            'Council adopted the updated development cost charge (DCC) bylaw on July 13, 2026.',
                        ],
                        'next' => [
                            'Council was to choose key performance indicators to measure the OCP at a January 5, 2026 committee meeting. I did not find the outcome.',
                            'An amenity cost charge bylaw (to fund things like pools and community centres from new development) was deferred until the DCC bylaw was done; the City has not started it.',
                        ],
                        'challenges' => [
                            'In January 2026 staff noted the OCP steering committee had not met since April 2025 and council had to ratify the indicators itself.',
                            'No public status found for the zoning bylaw part of this project.',
                        ],
                        'sources' => [$castOcp, $obsAcc],
                    ],
                    [
                        'name' => 'Asset management programs', 'tag' => 'Plan',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'Funding and an inventory contract are in motion in 2026.',
                        'done' => [
                            'In February 2026 the City issued a request for proposals for a facility inventory and asset assessment.',
                            'On July 30, 2026 governments announced $58,000 for a Long-Term Financial Plan for Asset Management.',
                        ],
                        'next' => ['The assessment and the long-term asset financial plan are to be completed; no dates were found.'],
                        'challenges' => ['The plan listed this as a short-term (2022 to 2024) project and the funding arrived in 2026.'],
                        'sources' => [$cityRfp, $cityLtfp],
                    ],
                    [
                        'name' => 'Canoe Beach master plan initiatives', 'tag' => 'Capital',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => $nothing,
                        'done' => [], 'next' => [], 'challenges' => [],
                        'sources' => [],
                    ],
                    [
                        'name' => 'Storm water utility', 'tag' => 'Operational',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => $nothing,
                        'done' => [], 'next' => [], 'challenges' => [],
                        'sources' => [],
                    ],
                    [
                        'name' => 'Urban Indigenous strategy / TRC', 'tag' => 'Plan',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => 'No report under this name; a related anti-racism strategy exists.',
                        'done' => ['The City, with community partners, produced "Embracing Equity and Inclusivity: Anti-Racism Strategy for the Shuswap" and in July 2025 sought a $15,000 grant to carry out and monitor it. It is a related effort; this sheet cannot say it is the project the plan meant.'],
                        'next' => ['Phase two of the anti-racism strategy (implementation and monitoring), if the grant was awarded; the outcome was not found.'],
                        'challenges' => [],
                        'sources' => [$pentRacism],
                    ],
                    [
                        'name' => 'Long-term financial plan, department strategy and capital plan', 'tag' => 'Plan',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'A long-term financial plan for assets is funded in 2026.',
                        'done' => ['A $58,000 federal grant (announced July 30, 2026) will fund a Long-Term Financial Plan for Asset Management. No department strategy or published capital plan was found.'],
                        'next' => ['Complete the long-term financial plan.'],
                        'challenges' => ['Capital budgeting still leans on one-time reserve moves: the 2026 budget drew about $1.6M to $1.7M from the parkade reserve to hold the tax increase to 3.88%.'],
                        'sources' => [$cityLtfp, $obsBudget, $castBudget],
                    ],
                    [
                        'name' => 'Climate action initiatives', 'tag' => 'Operational',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'A climate resiliency plan was adopted in August 2026.',
                        'done' => ['On August 24, 2026 council endorsed a climate resiliency plan covering adaptation and mitigation, after stakeholder feedback.'],
                        'next' => ['Actions named include a climate resilience community strategy, updated emergency management plans, and sustainable funding and staffing capacity.'],
                        'challenges' => ['The plan lists funding and staffing capacity itself as an action to establish. Costs were not found.'],
                        'sources' => [$castClimate],
                    ],
                    [
                        'name' => 'Transportation master plan', 'tag' => 'Plan',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'Being written as of August 2026.',
                        'done' => ['In August 2026 the engineering director said the City is working on a transportation master plan that will prioritize upgrades for flagged intersections.'],
                        'next' => ['Finish the plan and rank intersection upgrades.'],
                        'challenges' => ['Traffic at the 10th Street and 10th Avenue intersection has increased since Highway 1 was rerouted for the Salmon Arm West project, a resident told council and staff confirmed.'],
                        'sources' => [$obsMixedUse],
                    ],
                ],
            ],
            [
                'label' => 'Medium term',
                'years' => '2025 to 2027',
                'note' => 'This window is open now.',
                'projects' => [
                    [
                        'name' => 'Auto Road connector', 'tag' => 'Capital',
                        'status' => 'underway', 'timing' => 'behind',
                        'headline' => 'Gas pipelines must move first; City construction is now pointed at 2029.',
                        'done' => [
                            'On August 24, 2026 council approved $1,676,000 for FortisBC to replace two transmission pipelines that lie too shallow for the new road.',
                            'The cost went from a preliminary $2.5M (2024) to $1.643M (a July 2025 Class 4 estimate), then up $33,000 in a July 2026 revision.',
                        ],
                        'next' => ['FortisBC does archaeological work and buys materials in 2027 and starts construction in May 2028. Staff said the City could begin the road in 2029, subject to available funds.'],
                        'challenges' => [
                            'The planned timing falls after the plan\'s 2025 to 2027 window.',
                            'The road\'s own construction cost was not found; the Shoemaker Hill / Auto Road Extension reserve holds about $3.4M, and the pipeline work will draw $1.676M of it.',
                        ],
                        'sources' => [$obsAuto],
                    ],
                    [
                        'name' => 'Blackburn Park master plan initiatives', 'tag' => 'Capital',
                        'status' => 'underway', 'timing' => 'within',
                        'headline' => 'The artificial turf field is open and lit.',
                        'done' => [
                            'The turf field opened December 13, 2025; council committed $700,000 in its 2024 budget, with the soccer association raising about $300,000 toward an estimated $1.2M installation.',
                            'Lights were installed September 30, 2026, paid for by a City contribution, the 55+ BC Games legacy fund, a $50,000 Rotary gift and other community donations.',
                        ],
                        'next' => ['Whether other Blackburn Park master plan items are scheduled was not found.'],
                        'challenges' => ['Two reports give different lighting totals ($237,000 in January, $130,000 in October), so this sheet does not quote one.'],
                        'sources' => [$obsBlackburnMou, $obsBlackburnLights],
                    ],
                    [
                        'name' => 'Community facilities and assets strategic plan', 'tag' => 'Plan',
                        'status' => 'planning', 'timing' => 'within',
                        'headline' => 'A facility inventory and assessment was put out to bid in February 2026.',
                        'done' => ['The City issued a request for proposals for a facility inventory and asset assessment. It says the pool should be planned for decommissioning in 10 to 15 years and that no start of construction is anticipated for a new aquatic centre.'],
                        'next' => ['Award and complete the assessment, then the strategic plan; no dates were found.'],
                        'challenges' => [],
                        'sources' => [$cityRfp],
                    ],
                    [
                        'name' => 'Human resources strategy', 'tag' => 'Plan',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => $nothing,
                        'done' => [], 'next' => [], 'challenges' => [],
                        'sources' => [],
                    ],
                    [
                        'name' => 'Comprehensive IT plan', 'tag' => 'Plan',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => $nothing.' The 2026 budget raised a technology support position from half to full time, which is staffing, not a plan.',
                        'done' => [], 'next' => [], 'challenges' => [],
                        'sources' => [$obsBudget],
                    ],
                    [
                        'name' => 'New pool and retrofit of the existing recreation centre', 'tag' => 'Capital',
                        'status' => 'paused', 'timing' => 'behind',
                        'headline' => 'Not scheduled: the new aquatic centre waits on the sewage plant.',
                        'done' => [
                            'The 2026 budget set money aside to extend the life of the existing pool.',
                            'In February 2026 the mayor said now is not the time to build a new recreation facility (in a statement on the former Memorial Arena site), citing limited private and government funding and rising construction costs.',
                        ],
                        'next' => ['The mayor said in December 2025 that a new aquatic centre would be "the next one" once the sewage treatment plant is done.'],
                        'challenges' => [
                            'No cost figure for a new pool was found in the sources read.',
                            'The City has no amenity cost charge bylaw, which is one way other B.C. towns fund recreation facilities; it would need an itemized project and budget first.',
                        ],
                        'sources' => [$obsBudget, $evnRec, $cityRfp, $obsAcc],
                    ],
                    [
                        'name' => 'Food and urban agricultural plan', 'tag' => 'Plan',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => $nothing.' A 2026 council column cites a bylaw change allowing more poultry and rabbits as a food-security step; that is not the plan itself.',
                        'done' => [], 'next' => [], 'challenges' => [],
                        'sources' => [$evnFood],
                    ],
                    [
                        'name' => 'West Bay connector trail', 'tag' => 'Capital',
                        'status' => 'planning', 'timing' => 'within',
                        'headline' => 'Design under way; the $57M build has no funding yet.',
                        'done' => [
                            'In August 2024 council awarded preliminary design to ISL Engineering and added $190,000 to the financial plan (about $280,000 in all) with the Adams Lake and Neskonlith bands as partners.',
                            'In March 2025 council put $500,000 toward a federal grant application; in May 2025 it authorized $15,000 for a climate risk assessment to help the grant.',
                        ],
                        'next' => ['Secure senior-government funding; the trail is 6.5 km, from the wharf west to First Nations communities.'],
                        'challenges' => ['The preliminary construction estimate is $57 million. The mayor said the City cannot build it without federal and provincial money.'],
                        'sources' => [$castWestBayStart, $castWestBayGrant, $castWestBay],
                    ],
                ],
            ],
            [
                'label' => 'Long term',
                'years' => '2028 to 2031',
                'note' => 'This window has not opened yet, so no progress is not necessarily a delay.',
                'projects' => [
                    [
                        'name' => '4 Avenue connector', 'tag' => 'Capital',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => $nothing,
                        'done' => [], 'next' => [], 'challenges' => [],
                        'sources' => [],
                    ],
                    [
                        'name' => 'Downtown parkade', 'tag' => 'Capital',
                        'status' => 'paused', 'timing' => null,
                        'headline' => 'Council drew down the parkade reserve and signalled a different approach.',
                        'done' => ['In December 2025 council moved about $1.6M (Observer) to $1.7M (Castanet) out of the parkade reserve into other projects, leaving about $363,000.'],
                        'next' => ['The mayor said a parkade has not been a high priority in strategic planning for about a decade and that any future one would more likely be a public/private partnership with commercial or residential space above.'],
                        'challenges' => ['"You cannot build a parkade for $363,000," the mayor said.'],
                        'sources' => [$obsBudget, $castBudget],
                    ],
                    [
                        'name' => 'Klahani Park master plan initiatives', 'tag' => 'Capital',
                        'status' => 'unknown', 'timing' => null,
                        'headline' => $nothing,
                        'done' => [], 'next' => [], 'challenges' => [],
                        'sources' => [],
                    ],
                    [
                        'name' => 'Universal water metering and cost-benefit analysis', 'tag' => 'Operational',
                        'status' => 'underway', 'timing' => 'within',
                        'headline' => 'Meters are going into one high-use zone first, ahead of the plan\'s window.',
                        'done' => [
                            'On July 13, 2026 council voted to install meters at every property in Zone 3A (along 30th Street NE north of Highway 1), with $125,000 in the 2026 budget.',
                            'In August 2025 some councillors asked for universal metered billing "sooner rather than later".',
                        ],
                        'next' => ['Staff are to report back to council on metered billing after collecting and reviewing the Zone 3A data. Metered billing is not part of this step.'],
                        'challenges' => [
                            'The Water Master Plan found outdoor irrigation is the biggest threat to the treatment system, and that daily demand could pass plant capacity within 20 years if drought and irrigation pressures continue.',
                            'The cost-benefit analysis the plan calls for was not found.',
                        ],
                        'sources' => [$obsMeters, $castMeters],
                    ],
                ],
            ],
        ];

        $tally = ['complete' => 0, 'underway' => 0, 'planning' => 0, 'paused' => 0, 'unknown' => 0];
        $behind = 0;
        foreach ($groups as $group) {
            foreach ($group['projects'] as $project) {
                $tally[$project['status']]++;
                $behind += $project['timing'] === 'behind' ? 1 : 0;
            }
        }
        $tally['total'] = array_sum($tally);
        $tally['behind'] = $behind;

        return [
            'slug' => self::SLUG,
            'plan_slug' => 'strategic-plan',
            'title' => 'Corporate Strategic Plan: progress scoresheet',
            'short' => 'Where each of the 22 priority projects stands, what is in the works, what stands in the way, and the big capital spending ahead.',
            'plan_title' => 'Corporate Strategic Plan',
            'as_of' => '2026-10-05',
            'read_on' => '2026-10-05',
            'scale' => [
                ['key' => 'complete', 'label' => 'Complete', 'help' => 'The project is finished and open or in use.'],
                ['key' => 'underway', 'label' => 'Under way', 'help' => 'Money spent, a contract awarded, a plan adopted, or construction started.'],
                ['key' => 'planning', 'label' => 'Planning', 'help' => 'Design, grant-seeking or study stage; nothing built.'],
                ['key' => 'paused', 'label' => 'Paused', 'help' => 'Council or staff have said it is not going ahead now.'],
                ['key' => 'unknown', 'label' => 'No report found', 'help' => 'No public progress report was found. That is not the same as no progress.'],
            ],
            'tally' => $tally,
            'summary' => [
                'As of October 5, 2026, none of the plan\'s 22 priority projects is reported complete as a whole. Ten are under way, two are in planning, two are paused, and for eight no public progress report was found.',
                'The plan\'s short-term window (2022 to 2024) has closed. Of its ten projects, seven have work reported and none is finished. The two largest, the sewage plant upgrade and the Lakeshore Road upgrade, are still in design or waiting on land and funding.',
                'The City does not appear to publish a project-by-project report against this plan. This scoresheet is built from council coverage, City web pages and City documents; each row lists the pages it rests on.',
            ],
            'groups' => $groups,
            'capex' => [
                'intro' => 'Money the public record shows is expected to be spent on the plan\'s capital projects between 2022 and 2031. Figures are estimates at the date shown, not final costs, and construction inflation has pushed several up.',
                'items' => [
                    [
                        'name' => 'Wastewater Pollution Control Centre, Stage IV',
                        'amount' => '$78.5M (July 2025); about $100M early estimate (Sept. 2026)',
                        'timing' => 'Design 2026 to 2027+; construction date not published',
                        'funding' => '$7M of the 2026 budget is for design (part from borrowing not yet started); $7M grant application in; long-term borrowing planned for construction.',
                        'note' => 'Design contracts of $2.04M and $4.49M were before council on Sept. 28, 2026; outcome not found.',
                        'sources' => [$pentWpcc, $evnWpcc],
                    ],
                    [
                        'name' => 'West Bay Connector Trail',
                        'amount' => '$57M preliminary (May 2025)',
                        'timing' => 'Not scheduled',
                        'funding' => 'City has put $500,000 toward a federal grant application; the mayor says it cannot be built without federal and provincial funds.',
                        'note' => 'About $280,000 spent or committed on preliminary design (2024).',
                        'sources' => [$castWestBay, $castWestBayGrant, $castWestBayStart],
                    ],
                    [
                        'name' => 'Auto Road Connector: FortisBC pipeline replacement',
                        'amount' => '$1,676,000 for the pipelines; the road itself not found',
                        'timing' => 'Pipelines 2027 to 2028; road from 2029',
                        'funding' => 'Shoemaker Hill / Auto Road Extension reserve (about $3.4M).',
                        'note' => 'Approved Aug. 24, 2026.',
                        'sources' => [$obsAuto],
                    ],
                    [
                        'name' => 'Lakeshore Road upgrade',
                        'amount' => 'Total not found in the sources read',
                        'timing' => 'Construction expected late 2026 or early 2027, subject to land and funding',
                        'funding' => 'Not found. $530,000 from the project\'s account was reallocated to two watermain jobs in Nov. 2025.',
                        'note' => 'Phase 1 (slope buttress) awarded June 2024.',
                        'sources' => [$cityLakeshore, $obsLakeshore],
                    ],
                    [
                        'name' => 'New aquatic centre and recreation centre retrofit',
                        'amount' => 'No cost figure found',
                        'timing' => 'No construction start anticipated (City, 2026)',
                        'funding' => 'Mayor: next after the sewage plant. No amenity cost charge bylaw yet.',
                        'note' => 'City RFP says to plan for pool decommissioning in 10 to 15 years.',
                        'sources' => [$cityRfp, $obsBudget, $obsAcc],
                    ],
                    [
                        'name' => 'Downtown parkade',
                        'amount' => 'No cost figure found; reserve cut from about $2.5M to about $363,000',
                        'timing' => 'Plan window 2028 to 2031; not scheduled',
                        'funding' => 'Reserve largely redirected in the 2026 budget; a public/private partnership is the mayor\'s stated direction.',
                        'note' => '',
                        'sources' => [$obsBudget, $castBudget],
                    ],
                    [
                        'name' => 'Blackburn Park turf field and lights',
                        'amount' => 'About $1M to $1.2M field; lighting reported as $130,000 to $237,000',
                        'timing' => 'Field open Dec. 2025; lights Sept. 2026',
                        'funding' => 'City $700,000 (2024 budget) plus up to $153,400 for lights from unused funds and the 55+ BC Games legacy fund; the soccer association and donors the rest.',
                        'note' => 'Done.',
                        'sources' => [$obsBlackburnMou, $obsBlackburnLights],
                    ],
                ],
                'other_heading' => 'Other large capital items in the same years (not plan projects)',
                'other' => [
                    ['text' => '30th Street NE and 11th Avenue NE roundabout: $2,370,295 contract before council April 13, 2026, with $2.9M budgeted; property negotiations and an Agricultural Land Commission application were still open.', 'sources' => [$evnRoundabout]],
                    ['text' => 'Highway 97B watermain (10th Avenue SE to Haney Heritage Village): $1.63M updated estimate (November 2025).', 'sources' => [$obsLakeshore]],
                    ['text' => 'Raven rail crossing on the Foreshore Trail: $420,000 in the 2026 budget; the railway could not build in 2026, so the City applied for a grant covering up to 80% (about $336,000).', 'sources' => [$obsRail]],
                    ['text' => 'Wharf marina dock replacement phase 2 ($1.4M) and decking ($2M) are future needs; the annual reserve transfer for them was paused in the 2026 budget.', 'sources' => [$obsFiveYear]],
                    ['text' => 'Road paving: about $1.2M a year comes from parcel tax; staff and the mayor say it is not enough, and the 2026 budget added $500,000.', 'sources' => [$obsBudget]],
                ],
                'gaps' => [
                    'The 2026 to 2030 five-year capital totals were not found: the City\'s financial plan links on its website point to the wrong documents, and the Chief Financial Officer\'s budget reports are behind a bot check that blocks automated reading.',
                    'Taxes: the 2026 budget raised property taxes 3.88%, down from the 11% first sought by staff and 5% when deliberations began (Salmon Arm Observer, Dec. 18, 2025).',
                ],
            ],
            'method' => [
                'Each row rests on pages opened and read; search-result summaries were not used as sources. Dates are the date of the story or page.',
                '"Behind" and "within" compare the dates now published to the plan\'s own window for that project.',
                'Where a project shows "no report found", staff or council may have more recent information than appears in public coverage. Check the City\'s council agendas for the latest.',
                'This scoresheet covers the Corporate Strategic Plan only, not the Official Community Plan.',
            ],
        ];
    }
}
