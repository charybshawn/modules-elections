import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * A fixed-height box that shows a batch of a list at a time and adds the next
 * batch as the box is scrolled to its end. Bind `box` to the scrolling element
 * and `end` to a sentinel placed after the list (rendered only while `hasMore`).
 */
export function useInfiniteScroll<T>(items: () => T[], step = 20) {
  const shown = ref(step)
  const box = ref<HTMLElement | null>(null)
  const end = ref<HTMLElement | null>(null)
  const visible = computed(() => items().slice(0, shown.value))
  const hasMore = computed(() => shown.value < items().length)

  let observer: IntersectionObserver | null = null
  const watchEnd = () => {
    observer?.disconnect()
    if (!box.value || !end.value) return
    observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting) && shown.value < items().length) shown.value += step
      },
      { root: box.value, rootMargin: '0px 0px 80px 0px' },
    )
    observer.observe(end.value)
  }

  onMounted(() => nextTick(watchEnd))
  // The sentinel is re-created as batches load, so watch it again each time.
  watch([shown, () => items().length], () => nextTick(watchEnd))
  onBeforeUnmount(() => observer?.disconnect())

  return { shown, box, end, visible, hasMore }
}
