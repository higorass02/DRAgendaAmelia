<script setup>
import { statusMeta } from '@/lib/appointmentStatus'

const props = defineProps({
  status: { type: String, required: true },
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
    :class="statusMeta(props.status).badgeClass"
  >
    <component :is="statusMeta(props.status).icon" class="size-3.5" />
    {{ statusMeta(props.status).label }}
  </span>
</template>
