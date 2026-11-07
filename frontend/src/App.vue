<script setup>
import { computed, ref } from 'vue'
import QaEvaluator from './components/QaEvaluator.vue'
import Tutor from './components/Tutor.vue'
import Conciliacion from './components/Conciliacion.vue'

const tabs = [
  {
    id: 'qa',
    label: 'Evaluador QA',
    detail: 'Responde preguntas de un foro y un LLM juez la compara con la respuesta humana hasta aprobarla.',
    component: QaEvaluator,
  },
  {
    id: 'tutor',
    label: 'Tutor',
    detail: 'Genera ejercicios de matemáticas del nivel del estudiante, los corrige y ajusta su nivel.',
    component: Tutor,
  },
  {
    id: 'conciliacion',
    label: 'Conciliación',
    detail: 'Cruza los abonos de una cartola con facturas y deja los casos dudosos para revisión humana.',
    component: Conciliacion,
  },
]

const activeTab = ref(tabs[0].id)
const current = computed(() => tabs.find((t) => t.id === activeTab.value))
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-800">
    <header class="border-b border-gray-200 bg-white">
      <div class="mx-auto max-w-5xl px-4 pt-6">
        <div class="flex items-center gap-3">
          <!-- Marca: tres nodos unidos, como un agente que encadena herramientas -->
          <span class="grid size-9 place-items-center rounded-lg bg-cobalto text-white" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="6" cy="6" r="2.5" />
              <circle cx="18" cy="12" r="2.5" />
              <circle cx="6" cy="18" r="2.5" />
              <path d="M8.3 7.2 15.7 10.8M15.7 13.2 8.3 16.8" />
            </svg>
          </span>
          <div>
            <h1 class="text-xl font-bold tracking-tight text-gray-900">Agentes PHP</h1>
            <p class="text-sm text-gray-500">Tres agentes con tool calling sobre un mismo núcleo</p>
          </div>
        </div>

        <nav class="-mb-px mt-5 flex gap-1 overflow-x-auto" aria-label="Agentes">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors"
            :class="
              activeTab === tab.id
                ? 'border-cobalto text-cobalto'
                : 'border-transparent text-gray-500 hover:text-gray-900'
            "
            :aria-current="activeTab === tab.id ? 'page' : undefined"
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6">
      <p class="mb-5 max-w-[70ch] text-gray-600">{{ current.detail }}</p>
      <component :is="current.component" />
    </main>
  </div>
</template>
