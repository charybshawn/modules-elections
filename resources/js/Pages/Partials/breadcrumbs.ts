// Breadcrumbs for the Elections pages. The admin layout would otherwise build them
// from the URL, which gives dead links (there is no /candidates or /provincial/parties
// page) and raw slugs as labels. Each page passes its real trail instead; the last
// crumb has no link.

export interface Crumb {
  label: string
  href?: string
}

/** The Elections landing page, where an election is picked. */
export const hub = (): Crumb => ({ label: 'Elections', href: route('admin.elections.home') })

/** The Salmon Arm municipal election's dashboard. */
export const salmonArm = (): Crumb => ({ label: 'Salmon Arm 2026', href: route('admin.elections.index') })

/** The BC provincial election's dashboard. */
export const provincial = (): Crumb => ({ label: 'BC Provincial 2026', href: route('admin.elections.provincial') })

/** "Browse by subject" and "City Plans + Finances": the municipal sections other pages hang under. */
export const subjects = (): Crumb => ({ label: 'Browse by subject', href: route('admin.elections.tags.index') })
export const cityPlans = (): Crumb => ({ label: 'City Plans + Finances', href: route('admin.elections.plans.index') })

/** The trail for a page: the hub, then each step, the last one unlinked. */
export const trail = (...steps: Crumb[]): Crumb[] => [hub(), ...steps].map((c, i, all) => (i === all.length - 1 ? { label: c.label } : c))

/** A value from a page's props by dotted path (the layout function only gets the page's vnode), or a fallback. */
export const pick = (page: { props?: Record<string, unknown> }, path: string, fallback: string): string => {
  let value: unknown = page.props
  for (const key of path.split('.')) value = (value as Record<string, unknown> | undefined)?.[key]
  return typeof value === 'string' && value !== '' ? value : fallback
}
