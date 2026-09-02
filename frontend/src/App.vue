<script setup>
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <header class="border-b border-border">
      <nav class="mx-auto flex max-w-5xl items-center gap-4 px-6 py-4">
        <RouterLink to="/" class="font-semibold">Agenda</RouterLink>
        <RouterLink to="/about" class="text-sm text-muted-foreground hover:text-foreground">
          Sobre
        </RouterLink>
      </nav>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-8">
      <RouterView />
    </main>
  </div>
</template>
