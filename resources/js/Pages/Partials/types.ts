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
  /** Admins only -- omitted for invited read-only viewers. */
  notes?: string | null
  entries_count?: number
  articles_count?: number
  updated_at: string | null
}

export interface Entry {
  id: number
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
}

export interface Article {
  id: number
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
  eventKinds: Record<string, string>
}

export interface Plank {
  id: number
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

export interface Portfolio {
  candidate: Candidate
  /** Background facts for the About section, grouped by Options.backgroundTopics. */
  background: EntryGroup[]
  /** The Platform tab; null until anything is on file. */
  platform: Platform | null
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
  /** Candidates with a plank on this topic, by plank tier; null for the "other" topic. */
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
