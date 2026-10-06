<?php

namespace Cultpantry\Elections\Support;

/**
 * Plain-language info sheets for the City of Salmon Arm plans that council
 * works from, shown under Elections -> City plans so readers can weigh what
 * candidates say against them.
 *
 * Every section cites the page(s) of the City's own PDF it summarizes
 * (`pages`, linked as source_url#page=N). Summaries are neutral paraphrase
 * of the documents; anything not in them stays out. To refresh a sheet when
 * the City revises a plan, re-read the PDF and update the text and pages here.
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
                'summary' => [
                    'The Corporate Strategic Plan sets direction for the significant projects the City expects to take on over roughly 10 years, on top of the core services it delivers every day. Council and staff built it from 2020 to 2022, with a community survey in March and April 2022.',
                    'It updates the 2013 plan: of that plan\'s 25 projects, the City reports 24 complete, in progress or partly addressed. The plan calls itself a living document, to be reviewed each year and updated every four years when a new council is elected.',
                ],
                'sections' => [
                    [
                        'heading' => 'What it is for',
                        'pages' => [9],
                        'items' => [
                            'Separate the core services the City must deliver from extra projects it chooses to take on.',
                            'Set a list of priority projects for the next 10 years, chosen with a scoring framework.',
                            'Keep staff focused on what matters to the community, while leaving room for new ideas.',
                            'Reduce reactive, spur-of-the-moment decisions and point to partners who can help.',
                            'Give council and staff simple tools to check whether a project fits the plan.',
                        ],
                    ],
                    [
                        'heading' => 'Guiding principles',
                        'pages' => [7, 13, 14],
                        'items' => [
                            'Support a prosperous, vibrant and welcoming community ("a small city with big ideas").',
                            'Look after City resources responsibly: infrastructure, finances, environment, recreation, health and safety.',
                            'Be clear with the community about where the City\'s time and money will go.',
                            'Bring community partners together, and support them where they are better placed to lead.',
                            'Deliver services to a high standard that can keep up with growth.',
                        ],
                    ],
                    [
                        'heading' => 'Five strategic drivers',
                        'pages' => [15, 16, 17, 18, 19],
                        'text' => 'Every proposed project is scored against these five drivers.',
                        'items' => [
                            'People: make Salmon Arm a great place to live.',
                            'Places: keep the "small city" lifestyle in the heart of the Shuswap.',
                            'Assets: invest steadily in the infrastructure the community rests on.',
                            'Environment: protect and enhance the natural environment.',
                            'Economy: support initiatives that enable economic prosperity.',
                        ],
                    ],
                    [
                        'heading' => 'Priority projects',
                        'pages' => [23, 24],
                        'text' => 'Each project is marked as a plan, a capital (build) project, or an operational change.',
                        'groups' => [
                            [
                                'label' => 'Short term (2022 to 2024)',
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
                                'label' => 'Medium term (2025 to 2027)',
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
                                'label' => 'Long term (2028 to 2031)',
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
                        'pages' => [25],
                        'items' => [
                            'Reviewed each year alongside the City\'s budget planning.',
                            'Revisited every four years so each newly elected council can add projects and confirm or reorder priorities.',
                            'Projects can move when funding, staff capacity or regulations change.',
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
                'summary' => [
                    'An Official Community Plan is a bylaw, required by the provincial Local Government Act, that guides council\'s planning and land use decisions, usually looking 20 years or more ahead. Salmon Arm\'s new plan, Bylaw 4707, was adopted on December 8, 2025.',
                    'Residents told the City they largely support the approach of the plan it replaces and the 2002 plan before that: a compact city centre, growth kept inside an Urban Containment Boundary, and protected natural, farm and forest land beyond it.',
                ],
                'sections' => [
                    [
                        'heading' => 'Vision',
                        'pages' => [8],
                        'text' => 'A comfortable, safe and vibrant community with a strong sense of belonging, built around connection, recognizing the Secwépemc people who have lived here since time immemorial. The plan pictures a future with healthy ecosystems and farmland, well-kept and accessible recreation facilities, varied jobs, affordable housing for all ages, a walkable Downtown as the civic and cultural heart, green spaces and safe walking and cycling routes throughout, more resilience to climate change, and stronger community safety and food security.',
                    ],
                    [
                        'heading' => 'Themes and lenses',
                        'pages' => [9, 10],
                        'text' => 'Public input clustered around themes such as balanced growth, connectivity, community vibrancy and sustainability. Five lenses run through every section of the plan:',
                        'items' => [
                            'Affordability: access to housing, food and other basics.',
                            'Equity: removing barriers for groups that have been disadvantaged.',
                            'Reconciliation: addressing harms experienced by Secwépemc peoples.',
                            'Safety: physical and psychological safety, and protection from hazards.',
                            'Sustainability: not compromising what future generations will need.',
                        ],
                    ],
                    [
                        'heading' => 'Growth and housing',
                        'pages' => [14, 16, 19, 21],
                        'items' => [
                            'Grow up, not out: new development is kept inside the Urban Containment Boundary, with priority for areas that already have water, sewer and roads.',
                            'Farmland is protected from urban development.',
                            'The 2024 Housing Needs Report calls for about 245 new homes a year: roughly 1,228 over five years and 4,100 over 20.',
                            'Current zoning already allows about 46,298 homes in total (8,517 exist today), far more than projected demand.',
                            'In Residential High Density areas, density can rise from 130 to as much as 200 units per hectare for projects that are at least half affordable or purpose-built rental, in exchange for amenities.',
                            'Redeveloping rental buildings or mobile home parks is strongly discouraged unless residents are rehoused at comparable rents.',
                        ],
                    ],
                    [
                        'heading' => 'Objectives by topic',
                        'pages' => [13, 23, 27, 31, 34, 37, 41, 43, 47, 49, 54, 57],
                        'items' => [
                            'Secwépemc peoples: advance Truth and Reconciliation and build trust-based relationships.',
                            'Rural and agriculture: keep farm, forest and rural land outside the boundary, and expand local food production and processing.',
                            'Commercial: keep Downtown the commercial and cultural focus; limit highway commercial to land near the Trans-Canada.',
                            'Industrial: protect industrial land and intensify it for jobs, with training opportunities and transit links.',
                            'Environment and climate: protect sensitive areas and watercourses, build climate resilience, cut emissions.',
                            'Parks, recreation and greenways: year-round facilities sized to the population, and a connected trail network.',
                            'Arts, culture and heritage: art and culture in public places, and heritage preservation.',
                            'Community and social services: inclusive, accessible spaces, and work with agencies and community groups.',
                            'Economic development: a diverse economy and labour force, working with partners such as the Salmon Arm Economic Development Society.',
                            'Transportation: safe, accessible, lower-carbon options, adding walking and cycling routes as roads are built and upgraded.',
                            'Utilities: services phased with growth, managed for energy conservation and fiscal responsibility.',
                            'Hazards: steer development away from hazard areas and plan for climate-related risks.',
                        ],
                    ],
                    [
                        'heading' => 'Climate target',
                        'pages' => [36],
                        'text' => 'Community-wide emissions are to fall in line with the IPCC values for limiting warming to 1.5°C, currently 48% by 2030, 65% by 2035, 80% by 2040 and 99% by 2050, compared with 2019 levels.',
                    ],
                    [
                        'heading' => 'Paying for it',
                        'pages' => [60],
                        'text' => 'The plan notes that an OCP cannot pre-approve spending: each project still needs funding through the City\'s annual budget, and doing everything quickly would mean significant tax increases. Borrowing needs public consent, and the City will apply for grants where it can. From public input, the order of priorities is:',
                        'items' => [
                            '1. New or improved recreation facilities.',
                            '2. Active transportation improvements (the 2022 network plan was costed at over $90 million).',
                            '3. Transit improvements.',
                            '4. Emissions reduction and climate programs.',
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
