<script setup>
import { RouterLink, useRoute } from 'vue-router'
import { Button } from '@/components/ui/button'
import { Plus } from '@lucide/vue'

defineEmits(['schedule'])

const route = useRoute()

const tabs = [
  { name: 'appointments.kanban', label: 'Kanban' },
  { name: 'appointments.list', label: 'Lista' },
  { name: 'appointments.agenda', label: 'Agenda' },
]
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-1 rounded-md bg-muted p-1">
      <RouterLink
        v-for="tab in tabs"
        :key="tab.name"
        :to="{ name: tab.name }"
        class="rounded-sm px-3 py-1.5 text-sm transition-colors"
        :class="
          route.name === tab.name
            ? 'bg-background text-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground'
        "
      >
        {{ tab.label }}
      </RouterLink>
    </div>

    <Button @click="$emit('schedule')">
      <Plus class="size-4" />
      Agendar consulta
    </Button>
  </div>
</template>
