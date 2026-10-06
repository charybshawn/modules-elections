<?php

namespace Cultpantry\Elections\Support;

/**
 * Plain-language info sheets for the City of Salmon Arm plans that council
 * works from, shown under Elections -> City plans so readers can weigh what
 * candidates say against them.
 *
 * Every point cites the page(s) of the City's own PDF it summarizes
 * (`pages`, linked as source_url#page=N; the City serves its PDFs inline, so
 * the link opens at that page). Summaries are neutral paraphrase of
 * the documents; anything not in them stays out. To refresh a sheet when the
 * City revises a plan, re-read the PDF and update the text and pages here.
 *
 * Shapes: a point is ['text' => ..., 'pages' => [n, ...]]. A section has a
 * 'layout' the page knows how to draw: list, quote, cards, groups, topics,
 * timeline.
 */
class CityPlans
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'strategic-plan' => [
                'slug' => 'strategic-plan',
                'title' => 'Corporate Strategic Plan',
                'short' => "The City's 10-year list of priority projects beyond day-to-day services.",
                'status' => 'Adopted 2022; last revised September 13, 2022',
                'source_url' => 'https://salmonarm.ca/DocumentCenter/View/4114/2022-FINAL-CSP-Digital-Version',
                'source_label' => '2022 Corporate Strategic Plan (PDF, 47 pages)',
                'page_url' => 'https://salmonarm.ca/292/Corporate-Strategic-Plan',
                'read_on' => '2026-10-05',
                'stats' => [
                    ['value' => '22', 'label' => 'priority projects', 'pages' => [24]],
                    ['value' => '10 yrs', 'label' => 'planning horizon, 2022 to 2031', 'pages' => [23]],
                    ['value' => '5', 'label' => 'strategic drivers', 'pages' => [14]],
                    ['value' => '24 of 25', 'label' => '2013 plan projects done or under way', 'pages' => [10]],
                ],
                'summary' => [
                    ['text' => 'The Corporate Strategic Plan sets direction for the significant projects the City expects to take on over about 10 years, on top of the core services it delivers every day.', 'pages' => [9]],
                    ['text' => 'Council and staff began the update in December 2020, and residents commented on a draft project list in a survey in March and April 2022.', 'pages' => [11]],
                    ['text' => 'It updates the 2013 plan, of whose 25 projects the City reports 24 complete, in progress or partly addressed. It calls itself a living document, reviewed every year and updated every four years when a new council is elected.', 'pages' => [10, 25]],
                ],
                'sections' => [
                    [
                        'heading' => 'What it is for',
                        'layout' => 'list',
                        'points' => [
                            ['text' => 'Separate the core services the City must deliver from extra projects it chooses to take on.', 'pages' => [9]],
                            ['text' => 'Set a list of priority projects for the next 10 years, chosen with a scoring framework.', 'pages' => [9]],
                            ['text' => 'Keep staff focused on what matters to the community, while leaving room for new ideas.', 'pages' => [9]],
                            ['text' => 'Reduce reactive, spur-of-the-moment decisions, and identify partners who can help.', 'pages' => [9]],
                            ['text' => 'Give council and staff simple tools to check whether a project fits the plan.', 'pages' => [9]],
                        ],
                    ],
                    [
                        'heading' => 'Guiding principles',
                        'layout' => 'list',
                        'points' => [
                            ['text' => 'Support a prosperous, vibrant and welcoming community ("a small city with big ideas").', 'pages' => [13]],
                            ['text' => 'Look after City resources responsibly: infrastructure, finances, environment, recreation, health and safety.', 'pages' => [13]],
                            ['text' => 'Be clear with the community about where the City\'s time and money will go.', 'pages' => [13]],
                            ['text' => 'Bring community partners together, and support them where they are better placed to lead.', 'pages' => [13]],
                            ['text' => 'Deliver services to a high standard that can keep up with growth.', 'pages' => [14]],
                        ],
                    ],
                    [
                        'heading' => 'Five strategic drivers',
                        'layout' => 'cards',
                        'intro' => ['text' => 'Every proposed project is scored against these five drivers.', 'pages' => [14]],
                        'points' => [
                            ['title' => 'People', 'text' => 'Make Salmon Arm a great place to live.', 'pages' => [15]],
                            ['title' => 'Places', 'text' => 'Keep the "small city" lifestyle in the heart of the Shuswap.', 'pages' => [16]],
                            ['title' => 'Assets', 'text' => 'Invest steadily in the infrastructure the community rests on.', 'pages' => [17]],
                            ['title' => 'Environment', 'text' => 'Protect and enhance the natural environment.', 'pages' => [18]],
                            ['title' => 'Economy', 'text' => 'Support initiatives that enable economic prosperity.', 'pages' => [19]],
                        ],
                    ],
                    [
                        'heading' => 'Priority projects',
                        'layout' => 'groups',
                        'intro' => ['text' => 'Each project is marked as a plan, a capital (build) project, or an operational change.', 'pages' => [23, 24]],
                        'groups' => [
                            [
                                'label' => 'Short term',
                                'years' => '2022 to 2024',
                                'pages' => [24],
                                'items' => [
                                    ['text' => 'Lakeshore Road improvements', 'tag' => 'Capital'],
                                    ['text' => 'Wastewater Pollution Control Centre upgrade', 'tag' => 'Capital'],
                                    ['text' => 'Major planning bylaw review (OCP, development cost charges and zoning)', 'tag' => 'Plan'],
                                    ['text' => 'Asset management programs', 'tag' => 'Plan'],
                                    ['text' => 'Canoe Beach master plan initiatives', 'tag' => 'Capital'],
                                    ['text' => 'Storm water utility', 'tag' => 'Operational'],
                                    ['text' => 'Urban Indigenous strategy / Truth and Reconciliation', 'tag' => 'Plan'],
                                    ['text' => 'Long-term financial plan, department strategy and capital plan', 'tag' => 'Plan'],
                                    ['text' => 'Climate action initiatives', 'tag' => 'Operational'],
                                    ['text' => 'Transportation master plan', 'tag' => 'Plan'],
                                ],
                            ],
                            [
                                'label' => 'Medium term',
                                'years' => '2025 to 2027',
                                'pages' => [24],
                                'items' => [
                                    ['text' => 'Auto Road connector', 'tag' => 'Capital'],
                                    ['text' => 'Blackburn Park master plan initiatives', 'tag' => 'Capital'],
                                    ['text' => 'Community facilities and assets strategic plan', 'tag' => 'Plan'],
                                    ['text' => 'Human resources strategy', 'tag' => 'Plan'],
                                    ['text' => 'Comprehensive information technology plan', 'tag' => 'Plan'],
                                    ['text' => 'New pool and retrofit of the existing recreation centre', 'tag' => 'Capital'],
                                    ['text' => 'Food and urban agricultural plan', 'tag' => 'Plan'],
                                    ['text' => 'West Bay connector trail', 'tag' => 'Capital'],
                                ],
                            ],
                            [
                                'label' => 'Long term',
                                'years' => '2028 to 2031',
                                'pages' => [24],
                                'items' => [
                                    ['text' => '4 Avenue connector', 'tag' => 'Capital'],
                                    ['text' => 'Downtown parkade', 'tag' => 'Capital'],
                                    ['text' => 'Klahani Park master plan initiatives', 'tag' => 'Capital'],
                                    ['text' => 'Universal water metering and cost-benefit analysis', 'tag' => 'Operational'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'heading' => 'How it is kept up to date',
                        'layout' => 'list',
                        'points' => [
                            ['text' => 'Reviewed each year alongside the City\'s financial planning and budget.', 'pages' => [25]],
                            ['text' => 'Revisited every four years so each newly elected council can add projects and confirm or reorder priorities.', 'pages' => [25]],
                            ['text' => 'Projects can move when funding, staff capacity or regulations change.', 'pages' => [25]],
                        ],
                    ],
                ],
            ],

            'ocp' => [
                'slug' => 'ocp',
                'title' => 'Official Community Plan',
                'short' => 'The long-range land use bylaw (Bylaw 4707) that guides growth and development for 20+ years.',
                'status' => 'Bylaw 4707, adopted December 8, 2025',
                'source_url' => 'https://salmonarm.ca/DocumentCenter/View/52',
                'source_label' => 'Official Community Plan Bylaw 4707, Schedule A (PDF, 138 pages)',
                'page_url' => 'https://salmonarm.ca/464/OCP2024',
                'read_on' => '2026-10-05',
                'stats' => [
                    ['value' => 'Dec. 8, 2025', 'label' => 'adopted as Bylaw 4707', 'pages' => [1]],
                    ['value' => '245', 'label' => 'new homes needed each year', 'pages' => [19]],
                    ['value' => '46,298', 'label' => 'homes current zoning allows (8,517 exist)', 'pages' => [19]],
                    ['value' => '48%', 'label' => 'emissions cut targeted by 2030', 'pages' => [36]],
                ],
                'summary' => [
                    ['text' => 'An Official Community Plan is a bylaw, required by the provincial Local Government Act, that guides council\'s planning and land use decisions, usually looking 20 years or more ahead.', 'pages' => [4]],
                    ['text' => 'Residents told the City they largely support the approach of the plan this one replaces and the 2002 plan before it: a compact city centre, growth kept inside an Urban Containment Boundary, and protected natural, farm and forest land beyond it.', 'pages' => [8]],
                ],
                'sections' => [
                    [
                        'heading' => 'Vision',
                        'layout' => 'quote',
                        'points' => [
                            ['text' => 'A comfortable, safe and vibrant community with a strong sense of belonging, built around connection, that recognizes the Secwépemc people who have lived here since time immemorial. The plan pictures healthy ecosystems and farmland, well-kept and accessible recreation facilities, varied jobs, affordable housing for all ages, a walkable Downtown as the civic and cultural heart, green spaces with safe walking and cycling routes, more resilience to climate change, and stronger community safety and food security.', 'pages' => [8]],
                        ],
                    ],
                    [
                        'heading' => 'Five lenses on every section',
                        'layout' => 'cards',
                        'intro' => ['text' => 'Public input clustered around themes such as balanced growth, connectivity, community vibrancy and sustainability. These five lenses run through the whole plan.', 'pages' => [9, 10]],
                        'points' => [
                            ['title' => 'Affordability', 'text' => 'Access to housing, food and other basics.', 'pages' => [10]],
                            ['title' => 'Equity', 'text' => 'Removing barriers for groups that have been disadvantaged.', 'pages' => [10]],
                            ['title' => 'Reconciliation', 'text' => 'Addressing harms experienced by Secwépemc peoples.', 'pages' => [10]],
                            ['title' => 'Safety', 'text' => 'Physical and psychological safety, and protection from hazards.', 'pages' => [10]],
                            ['title' => 'Sustainability', 'text' => 'Not compromising what future generations will need.', 'pages' => [10]],
                        ],
                    ],
                    [
                        'heading' => 'Growth and housing',
                        'layout' => 'list',
                        'points' => [
                            ['text' => 'Grow up, not out: new development stays inside the Urban Containment Boundary.', 'pages' => [9, 14]],
                            ['text' => 'Development is prioritized where water, sewer and roads already exist.', 'pages' => [16]],
                            ['text' => 'Farmland is protected from urban development.', 'pages' => [16]],
                            ['text' => 'The 2024 Housing Needs Report calls for about 245 new homes a year: roughly 1,228 over five years and 4,100 over 20.', 'pages' => [19]],
                            ['text' => 'Current zoning already allows about 46,298 homes in total (8,517 exist today), far more than projected demand.', 'pages' => [19]],
                            ['text' => 'In Residential High Density areas, density can rise from 130 to as much as 200 units per hectare for projects that are at least half affordable or purpose-built rental, in exchange for amenities.', 'pages' => [21]],
                            ['text' => 'Redeveloping rental buildings or mobile home parks is strongly discouraged unless residents are rehoused at comparable rents.', 'pages' => [20]],
                        ],
                    ],
                    [
                        'heading' => 'Objectives by topic',
                        'layout' => 'topics',
                        'points' => [
                            ['title' => 'Secwépemc peoples', 'text' => 'Advance Truth and Reconciliation and build trust-based relationships.', 'pages' => [13]],
                            ['title' => 'Rural and agriculture', 'text' => 'Keep farm, forest and rural land outside the boundary, and expand local food production and processing.', 'pages' => [23]],
                            ['title' => 'Commercial', 'text' => 'Keep Downtown the commercial and cultural focus; limit highway commercial to land near the Trans-Canada.', 'pages' => [27]],
                            ['title' => 'Industrial', 'text' => 'Protect industrial land and intensify it for jobs, with training opportunities and transit links.', 'pages' => [31]],
                            ['title' => 'Environment and climate', 'text' => 'Protect sensitive areas and watercourses, build climate resilience, cut emissions.', 'pages' => [34]],
                            ['title' => 'Parks, recreation and greenways', 'text' => 'Year-round facilities sized to the population, and a connected trail network.', 'pages' => [37]],
                            ['title' => 'Arts, culture and heritage', 'text' => 'Art and culture in public places, and heritage preservation.', 'pages' => [41]],
                            ['title' => 'Community and social services', 'text' => 'Inclusive, accessible spaces, and work with agencies and community groups.', 'pages' => [43]],
                            ['title' => 'Economic development', 'text' => 'A diverse economy and labour force, working with partners such as the Salmon Arm Economic Development Society.', 'pages' => [47]],
                            ['title' => 'Transportation', 'text' => 'Safe, accessible, lower-carbon options, adding walking and cycling routes as roads are built and upgraded.', 'pages' => [49]],
                            ['title' => 'Utilities', 'text' => 'Services phased with growth, managed for energy conservation and fiscal responsibility.', 'pages' => [54]],
                            ['title' => 'Hazards', 'text' => 'Steer development away from hazard areas and plan for climate-related risks.', 'pages' => [57]],
                        ],
                    ],
                    [
                        'heading' => 'Climate target',
                        'layout' => 'timeline',
                        'intro' => ['text' => 'Community-wide emissions are to fall in line with the IPCC values for limiting warming to 1.5°C, compared with 2019 levels.', 'pages' => [36]],
                        'points' => [
                            ['title' => '2030', 'text' => '48% lower', 'pages' => [36]],
                            ['title' => '2035', 'text' => '65% lower', 'pages' => [36]],
                            ['title' => '2040', 'text' => '80% lower', 'pages' => [36]],
                            ['title' => '2050', 'text' => '99% lower', 'pages' => [36]],
                        ],
                    ],
                    [
                        'heading' => 'Paying for it',
                        'layout' => 'list',
                        'intro' => ['text' => 'An OCP cannot pre-approve spending: each project still needs money through the City\'s annual budget, and doing everything quickly would mean significant tax increases. Borrowing needs public consent, and the City will apply for grants where it can. From public input, the order of priorities is:', 'pages' => [60]],
                        'ordered' => true,
                        'points' => [
                            ['text' => 'New or improved recreation facilities.', 'pages' => [60]],
                            ['text' => 'Active transportation improvements (the 2022 network plan was costed at over $90 million).', 'pages' => [60]],
                            ['text' => 'Transit improvements.', 'pages' => [60]],
                            ['text' => 'Emissions reduction and climate programs.', 'pages' => [60]],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }
}
