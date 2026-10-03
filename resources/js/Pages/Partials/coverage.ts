/** Who campaigns on what -- BuildSubjectCoverage. */
export interface CoverageTag {
  slug: string
  name: string
  topic: string
  /** Candidates with a plank on it. */
  candidates: number
}

export interface CoverageCell {
  /** Their strongest plank tier on this subject. */
  tier: 'top' | 'also' | 'mentioned' | string
  /** Plank titles behind it, strongest first. */
  planks: string[]
}

export interface SubjectCoverage {
  tags: CoverageTag[]
  /** candidate id -> tag slug -> cell */
  cells: Record<string, Record<string, CoverageCell>>
}

/** Sort weight: top tier first, nothing on file last. */
export const tierWeight = (tier: string | undefined): number => ({ top: 0, also: 1, mentioned: 2 })[tier ?? ''] ?? 3

export const cellOf = (coverage: SubjectCoverage, candidateId: number, slug: string): CoverageCell | undefined =>
  coverage.cells[String(candidateId)]?.[slug]

/** Matrix cell shading, strongest darkest. */
export const tierCellClass = (tier: string | undefined): string =>
  ({
    top: 'bg-indigo-600 text-white dark:bg-indigo-500',
    also: 'bg-indigo-300 text-indigo-950 dark:bg-indigo-400/60 dark:text-white',
    mentioned: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-400/20 dark:text-indigo-200',
  })[tier ?? ''] ?? 'bg-gray-50 text-gray-300 dark:bg-gray-800/60 dark:text-gray-600'

/** Small badge used on candidate cards and lists. */
export const tierBadgeClass = (tier: string | undefined): string =>
  ({
    top: 'bg-indigo-600 text-white',
    also: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-200',
    mentioned: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
  })[tier ?? ''] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'

export const tierShortLabel: Record<string, string> = { top: 'Top priority', also: 'Also stated', mentioned: 'Mentioned' }

/** What the matrix's details panel shows: one cell, or a candidate's subjects. */
export type CoverageDetailData =
  | { kind: 'cell'; candidate: { id: number; slug: string; name: string }; tag: { slug: string; name: string }; cell: CoverageCell | null }
  | { kind: 'candidate'; candidate: { id: number; slug: string; name: string }; subjects: { slug: string; name: string; tier: string }[] }
