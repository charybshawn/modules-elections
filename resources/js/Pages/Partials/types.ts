// Shapes of the module's JSON resources (src/Http/Resources) as the pages
// receive them.

export interface Candidate {
  id: number
  slug: string
  name: string
  office: string
  status: string
  is_incumbent: boolean
  occupation: string | null
  bio: string | null
  bio_source_url: string | null
  website: string | null
  facebook_url: string | null
  instagram_url: string | null
  email: string | null
  phone: string | null
  photo_url: string | null
  /** Who the photo belongs to, e.g. "Friday AM". */
  photo_credit: string | null
  /** Admins only -- omitted for invited read-only viewers. */
  notes?: string | null
  entries_count?: number
  articles_count?: number
  updated_at: string | null
  /** When the public profile last changed (the About tab's own update time). */
  profile_changed_at: string | null
  /** When the research notes last changed -- admins only. */
  notes_changed_at?: string | null
}

export interface TagHeat {
  /** Headings hottest first, each with its tags hottest first. */
  headings: {
    topic: string
    title: string
    tags: { slug: string; name: string; heat: number; level: number; level_all: number; mentions: number; recent: number }[]
  }[]
  /** Biggest rise this week over last week. */
  heatingUp: { slug: string; name: string }[]
  halfLifeDays?: number
}

export interface TagRef {
  slug: string
  name: string
  /** Options.topics key -- the heading it files under. */
  topic: string
}

export interface Entry {
  id: number
  /** Subject tags; statements filed under a plank are tagged through the plank. */
  tags: TagRef[]
  kind: string
  topic: string
  summary: string
  quote: string | null
  question: string | null
  source_url: string
  source_type: string
  source_name: string | null
  published_on: string | null
  /** When it went on file (ISO). */
  added_at: string | null
  /** The candidate-page tab it is shown on. */
  tab: string | null
}

export interface Article {
  id: number
  tags: TagRef[]
  title: string
  url: string
  outlet: string | null
  published_on: string | null
  summary: string | null
  /** When it was linked to this candidate (ISO); only on a candidate's page. */
  linked_at?: string | null
  candidates?: { name: string; slug: string }[]
}

export interface ElectionEvent {
  id: number
  title: string
  kind: string
  /** Local wall-clock time, YYYY-MM-DDTHH:MM. */
  starts_at: string
  ends_at: string | null
  location: string | null
  url: string | null
  description: string | null
}

export interface Options {
  offices: Record<string, string>
  statuses: Record<string, string>
  kinds: Record<string, string>
  topics: Record<string, string>
  backgroundTopics: Record<string, string>
  sourceTypes: Record<string, string>
  plankTiers: Record<string, string>
  scorecardStances: Record<string, string>
  planStatuses: Record<string, string>
  planAspects: Record<string, string>
  analysisParts: Record<string, string>
  eventKinds: Record<string, string>
}

/** AI analysis of a pillar: Options.analysisParts key -> points, each with its footnoted sources. */
export interface PlankAnalysis {
  on: string | null
  parts: Record<string, { text: string; sources: string[] }[]>
}

export interface PlanDetail {
  /** Options.planAspects key. */
  aspect: string
  text: string
  source_url: string
}

export interface Plank {
  id: number
  tags: TagRef[]
  /** AI analysis (pillars only); null until written. */
  analysis: PlankAnalysis | null
  /** What they've conveyed about carrying it out; null until assessed. */
  plan: { status: string; summary: string | null; details: PlanDetail[] } | null
  key: string
  title: string
  topic: string
  summary: string | null
  tier: string
  rank: number
  /** Why it ranks where it does, in the candidate's own emphasis. */
  rationale: string | null
  /** Their own position for it when they number or list their priorities. */
  priority_position: number | null
  has_commitment: boolean
  source_count: number
  sources: Entry[]
  /** When it went on file (ISO). */
  added_at: string | null
  /** Its last edit (a new plan, analysis or rank), ISO. */
  changed_at: string | null
  /** Every tier/rank it has held, oldest first. */
  history: { tier: string; rank: number; recorded_at: string }[]
}

export interface Platform {
  tiers: { tier: string; title: string; planks: Plank[] }[]
  /** Plank statements not yet tied to a plank. */
  unranked: Entry[]
  source_count: number
  limited_sources: boolean
}

export interface EntryGroup {
  topic: string | null
  entries: Entry[]
}

export interface PortfolioSection {
  key: string
  title: string
  groups: EntryGroup[]
}

export interface ScorecardItem {
  key: string
  /** The publisher's statement, verbatim. */
  statement: string
  tags: TagRef[]
  /** Options.scorecardStances key; 'no_response' when unanswered. */
  stance: string
  /** How everyone who answered split on it, by stance. */
  field: Record<string, number>
  /** Every other respondent's answer, for the field strip. */
  others: { name: string; slug: string; stance: string }[]
}

export interface ScorecardCategory {
  key: string
  /** The publisher's header and intro. */
  name: string
  intro: string | null
  /** Our neutral note on this area in Salmon Arm, and its sources. */
  local_context: string | null
  sources: string[]
  items: ScorecardItem[]
  /** AI-assisted reading of this candidate's stances here; null when none or they didn't answer. */
  takeaway: string | null
}

export interface Scorecard {
  key: string
  title: string
  publisher: string | null
  url: string | null
  about: string | null
  retrieved_on: string | null
  /** Latest change to anything this candidate's scorecard rests on (ISO). */
  changed_at: string | null
  /** The candidate's own page on the publisher's site. */
  source_url: string | null
  responded: boolean
  /** How many candidates answered. */
  respondents: number
  /** This candidate's stance counts across every statement. */
  totals: Record<string, number>
  categories: ScorecardCategory[]
}

export interface Portfolio {
  candidate: Candidate
  /** Background facts for the About section, grouped by Options.backgroundTopics. */
  background: EntryGroup[]
  /** Community, public service, affiliations and past activities, for the Background & Affiliations tab. */
  affiliations: EntryGroup[]
  /** The Platform tab; null until anything is on file. */
  platform: Platform | null
  /** Third-party scorecards (e.g. Vote4Tomorrow) the candidate has a response on file for. */
  scorecards: Scorecard[]
  sections: PortfolioSection[]
  articles: Article[]
  entryCount: number
  /** Server time (Unix seconds). */
  now: number
}

export interface PulseIssue {
  key: string
  title: string
  topic: string
  /** Distinct people who raised it. */
  voices: number
  /** Change since the previous snapshot: a difference, 'new', or null when there's no previous snapshot. */
  voices_change: number | 'new' | null
  support_pct: number | null
  oppose_pct: number | null
  mixed_pct: number | null
  heat: string
  summary: string | null
  wants: string[]
  questions: string[]
  tags: TagRef[]
  /** How coverage was matched: shared tags, or (untagged issues) the broad topic. */
  coverage_by: 'tags' | 'topic'
  /** Candidates with a plank on this issue's tags (or topic), by plank tier; null for an untagged "other" issue. */
  coverage: Record<string, { name: string; slug: string; plank: string }[]> | null
}

export interface PulseSnapshot {
  id: number
  taken_on: string
  period_from: string | null
  period_to: string | null
  threads_read: number
  commenters: number
  sources: string | null
  method_note: string | null
  conclusions: { text: string; issue: string | null }[]
  previous_taken_on: string | null
  issues: PulseIssue[]
  mentions: { name: string; slug: string; mentions: number; commenters: number; mentions_change: number | 'new' | null }[]
}

/** One row of the dashboard's Latest updates: a candidate's day of additions, or a news story. */
export interface CandidateUpdate {
  type: 'candidate'
  id: string
  /** When it went on file (ISO). */
  at: string
  name: string
  slug: string
  photo_url: string | null
  total: number
  /** e.g. "12 statements", "3 council-record items". */
  counts: string[]
  /** The first new plank title that day (the rest are counted in more_planks). */
  planks: string[]
  more_planks: number
  /** Candidate tab to open, or null for the default. */
  tab: string | null
}

export interface ArticleUpdate {
  type: 'article'
  id: string
  at: string
  title: string
  url: string
  outlet: string | null
  published_on: string | null
  /** How many candidates it covers. */
  candidates: number
}

export type LatestUpdate = CandidateUpdate | ArticleUpdate
