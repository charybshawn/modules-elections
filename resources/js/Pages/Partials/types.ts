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
}

export interface Article {
  id: number
  title: string
  url: string
  outlet: string | null
  published_on: string | null
  summary: string | null
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
  eventKinds: Record<string, string>
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
  sections: PortfolioSection[]
  articles: Article[]
  entryCount: number
}
