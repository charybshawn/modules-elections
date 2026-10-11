import type { TagRef } from './types'

// News-type tags (kind "news") label what sort of story an article is:
// personal, legislative, opinion, background. A story can carry several.
// Subject tags stay on the subject chips; these never link to a subject page.

export const isNewsTag = (tag: TagRef): boolean => tag.kind === 'news'
export const isSubjectTag = (tag: TagRef): boolean => tag.kind !== 'news'

const GREY = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'

/** Badge colours, written out in full so Tailwind keeps them. Matched on the end of the slug. */
export const newsTagClass = (slug: string): string => {
  const key = slug.replace(/^news-/, '')
  return (
    {
      personal: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200',
      legislative: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200',
      opinion: 'bg-orange-100 text-orange-800 dark:bg-orange-500/20 dark:text-orange-200',
      background: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
    } as Record<string, string>
  )[key] ?? GREY
}
