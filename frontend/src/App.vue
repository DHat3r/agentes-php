<script setup>
import { ref } from 'vue'
import QaEvaluator from './components/QaEvaluator.vue'
import Tutor from './components/Tutor.vue'
import Conciliacion from './components/Conciliacion.vue'

const tabs = [
  { id: 'qa', label: 'Evaluador QA', component: QaEvaluator },
  { id: 'tutor', label: 'Tutor', component: Tutor },
  { id: 'conciliacion', label: 'Conciliación', component: Conciliacion },
]

const activeTab = ref(tabs[0].id)
</script>

<template>
  <div class="min-h-screen bg-gray-100 text-gray-800">
    <header class="bg-white shadow-sm">
      <div class="mx-auto max-w-5xl px-4 py-4">
        <h1 class="text-xl font-semibold">Agentes PHP</h1>
        <nav class="mt-3 flex flex-wrap gap-2">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
            :class="
              activeTab === tab.id
                ? 'bg-gray-800 text-white'
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            "
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6">
      <component :is="tabs.find((t) => t.id === activeTab).component" />
    </main>
  </div>
</template>
