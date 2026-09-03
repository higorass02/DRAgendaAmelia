import { ref } from 'vue'

export function useSort(defaultKey = '') {
  const sort = ref(defaultKey)
  const direction = ref('asc')

  function toggleSort(key) {
    if (sort.value === key) {
      direction.value = direction.value === 'asc' ? 'desc' : 'asc'
    } else {
      sort.value = key
      direction.value = 'asc'
    }
  }

  return { sort, direction, toggleSort }
}
