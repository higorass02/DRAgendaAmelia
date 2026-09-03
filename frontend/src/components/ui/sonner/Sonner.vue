<script setup>
import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
  XIcon,
} from "@lucide/vue";
import { reactiveOmit } from "@vueuse/core";
import { Toaster as Sonner } from "vue-sonner";
import { cn } from "@/lib/utils";

const props = defineProps({
  id: { type: String, required: false },
  invert: { type: Boolean, required: false },
  theme: { type: String, required: false },
  position: { type: String, required: false },
  closeButtonPosition: { type: String, required: false },
  hotkey: { type: Array, required: false },
  richColors: { type: Boolean, required: false },
  expand: { type: Boolean, required: false },
  duration: { type: Number, required: false },
  gap: { type: Number, required: false },
  visibleToasts: { type: Number, required: false },
  closeButton: { type: Boolean, required: false },
  toastOptions: { type: Object, required: false },
  class: { type: String, required: false },
  style: { type: Object, required: false },
  offset: { type: [Object, String, Number], required: false },
  mobileOffset: { type: [Object, String, Number], required: false },
  dir: { type: String, required: false },
  swipeDirections: { type: Array, required: false },
  icons: { type: Object, required: false },
  containerAriaLabel: { type: String, required: false },
});
const delegatedProps = reactiveOmit(props, "class", "toastOptions");
</script>

<template>
  <Sonner
    :class="cn('toaster group', props.class)"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
      '--border-radius': 'var(--radius)',
      '--gray2': 'hsl(var(--popover) / 0.9)',
      '--gray3': 'var(--border)',
      '--gray4': 'var(--border)',
      '--gray5': 'var(--border)',
      '--gray12': 'var(--popover-foreground)',
      // Banners sólidos e saturados em vez do padrão pastel do Sonner — o
      // toast precisa competir visualmente com o overlay escurecido de um
      // modal aberto atrás dele, então cor de fundo quase-branca (padrão da
      // lib) não é opção aqui.
      '--success-bg': 'oklch(0.52 0.15 155)',
      '--success-border': 'oklch(0.44 0.15 155)',
      '--success-text': 'oklch(0.98 0.01 155)',
      '--error-bg': 'var(--destructive)',
      '--error-border': 'oklch(0.45 0.2 27)',
      '--error-text': 'oklch(0.98 0.01 27)',
      '--warning-bg': 'oklch(0.65 0.17 70)',
      '--warning-border': 'oklch(0.55 0.17 70)',
      '--warning-text': 'oklch(0.2 0.03 70)',
      '--info-bg': 'oklch(0.5 0.17 255)',
      '--info-border': 'oklch(0.42 0.17 255)',
      '--info-text': 'oklch(0.98 0.01 255)',
    }"
    :toast-options="
      props.toastOptions ?? {
        classes: {
          toast: 'rounded-2xl',
        },
      }
    "
    v-bind="delegatedProps"
  >
    <template #success-icon>
      <CircleCheckIcon class="size-4" />
    </template>
    <template #info-icon>
      <InfoIcon class="size-4" />
    </template>
    <template #warning-icon>
      <TriangleAlertIcon class="size-4" />
    </template>
    <template #error-icon>
      <OctagonXIcon class="size-4" />
    </template>
    <template #loading-icon>
      <div>
        <Loader2Icon class="size-4 animate-spin" />
      </div>
    </template>
    <template #close-icon>
      <XIcon class="size-4" />
    </template>
  </Sonner>
</template>
