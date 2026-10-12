// How a party looks wherever a candidate shows: a colour, a short label for
// tight spaces and the full name. Matched on what Elections BC prints in its
// "Affiliation" column; anything unrecognised gets a neutral grey, so a new
// party still shows its name. Classes are written out in full so Tailwind
// keeps them.

export interface PartyStyle {
  /** The full name as stored, e.g. "BC NDP". */
  label: string
  /** A few letters for small chips. */
  short: string
  /** Solid colour (background) with readable text. */
  solid: string
  /** The same colour as a border (it sits inside the box, so it never spills past a parent) and as a plain stripe. */
  border: string
  bar: string
}

const style = (label: string, short: string, hue: { solid: string; ring: string; bar: string }): PartyStyle => ({ label, short, ...hue })

const NDP = { solid: 'bg-orange-600 text-white', border: 'border-orange-600', bar: 'bg-orange-600' }
const CONSERVATIVE = { solid: 'bg-blue-700 text-white', border: 'border-blue-700', bar: 'bg-blue-700' }
const GREEN = { solid: 'bg-green-600 text-white', border: 'border-green-600', bar: 'bg-green-600' }
const CENTRE = { solid: 'bg-teal-600 text-white', border: 'border-teal-600', bar: 'bg-teal-600' }
const ONE = { solid: 'bg-rose-700 text-white', border: 'border-rose-700', bar: 'bg-rose-700' }
const LIBERAL = { solid: 'bg-red-600 text-white', border: 'border-red-600', bar: 'bg-red-600' }
const LIBERTARIAN = { solid: 'bg-yellow-400 text-gray-900', border: 'border-yellow-400', bar: 'bg-yellow-400' }
const HERITAGE = { solid: 'bg-purple-700 text-white', border: 'border-purple-700', bar: 'bg-purple-700' }
const INDEPENDENT = { solid: 'bg-slate-600 text-white', border: 'border-slate-600', bar: 'bg-slate-600' }
const OTHER = { solid: 'bg-gray-700 text-white', border: 'border-gray-700', bar: 'bg-gray-700' }

export const partyStyle = (party: string | null | undefined): PartyStyle | null => {
  const label = party?.trim()
  if (!label) return null
  const key = label.toLowerCase().replace(/[^a-z]/g, '')

  if (key.includes('ndp') || key.includes('newdemocrat')) return style(label, 'NDP', NDP)
  if (key.includes('conservative')) return style(label, 'CON', CONSERVATIVE)
  if (key.includes('green')) return style(label, 'GRN', GREEN)
  if (key.includes('centre') || key.includes('center')) return style(label, 'CTR', CENTRE)
  if (key === 'onebc' || key === 'one') return style(label, 'ONE', ONE)
  if (key.includes('liberal') || key.includes('bcunited')) return style(label, 'LIB', LIBERAL)
  if (key.includes('libertarian')) return style(label, 'LBT', LIBERTARIAN)
  if (key.includes('christianheritage')) return style(label, 'CHP', HERITAGE)
  if (key.includes('independent') || key.includes('unaffiliated')) return style(label, 'IND', INDEPENDENT)

  return style(label, label.slice(0, 3).toUpperCase(), OTHER)
}
