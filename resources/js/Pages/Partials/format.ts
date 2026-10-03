import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Dates arrive as YYYY-MM-DD and times as local YYYY-MM-DDTHH:MM; both are
// parsed field by field as local values so no timezone can shift them.

export const formatDate = (value: string | null | undefined): string | null => {
  if (!value) return null
  const [y, m, d] = value.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('en-CA', { month: 'short', day: 'numeric', year: 'numeric' })
}

export const formatDateTime = (value: string | null | undefined): string | null => {
  if (!value) return null
  const [date, time = '00:00'] = value.split('T')
  const [y, m, d] = date.split('-').map(Number)
  const [hh, mm] = time.split(':').map(Number)
  return new Date(y, m - 1, d, hh, mm).toLocaleString('en-CA', {
    weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

/** Whole days from today to a local date (negative once it's past). */
export const daysUntil = (value: string): number => {
  const [y, m, d] = value.slice(0, 10).split('-').map(Number)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return Math.round((new Date(y, m - 1, d).getTime() - today.getTime()) / 86_400_000)
}

// Only http(s) values become links -- these come from imports, and a
// "javascript:" URL must never end up in an href.
export const isHttpUrl = (value: string | null | undefined): value is string => !!value && /^https?:\/\//i.test(value)

/** The host's hostname, for a compact "where this came from" label. */
export const hostOf = (url: string): string => {
  try {
    return new URL(url).hostname.replace(/^www\./, '')
  } catch {
    return url
  }
}

/**
 * True for invited viewers: panel users granted this section without the
 * admin role. Every write control hides for them (the server refuses the
 * writes regardless).
 */
export const useReadOnly = () => {
  const page = usePage()
  return computed(() => Boolean((page.props.admin_access as { read_only?: boolean } | undefined)?.read_only))
}
