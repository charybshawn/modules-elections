import { inject, ref, type InjectionKey, type Ref } from 'vue'

// The element a link asked the candidate page to scroll to and mark
// (?focus=plank-<key> or ?focus=entry-<id>, e.g. from the dashboard's Latest
// updates). Show.vue provides it; cards read it to mark themselves.
export const focusKey: InjectionKey<Ref<string | null>> = Symbol('elections-focus')

export const useFocus = (): Ref<string | null> => inject(focusKey, ref(null))

/** Classes that mark the focused item. */
export const focusClass = 'rounded-md bg-amber-100 ring-2 ring-amber-400 ring-offset-4 ring-offset-white dark:bg-amber-500/20 dark:ring-amber-500 dark:ring-offset-gray-800'
