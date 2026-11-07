<script setup>
defineProps({
  // Array de { type: 'tool_call'|'tool_result'|'final', tool: string|null, arguments: object, content: string }
  trace: {
    type: Array,
    default: () => [],
  },
})

// Texto corto que acompaña a cada punto de la línea de tiempo.
const stepLabel = {
  tool_call: 'Llama a una herramienta',
  tool_result: 'Resultado',
  final: 'Respuesta final',
}
</script>

<template>
  <div v-if="trace.length" class="space-y-3">
    <h3 class="text-sm font-semibold text-gray-600">Traza del agente</h3>
    <!-- Línea de tiempo: cada paso es un punto sobre una línea vertical -->
    <ol class="relative ml-2 space-y-4 border-l-2 border-gray-200 pl-5">
      <li v-for="(step, index) in trace" :key="index" class="relative text-sm">
        <span
          class="absolute -left-[27px] top-1 size-3 rounded-full ring-4 ring-white"
          :class="{
            'bg-cobalto': step.type === 'tool_call',
            'bg-gray-400': step.type === 'tool_result',
            'bg-emerald-500': step.type === 'final',
          }"
          aria-hidden="true"
        />
        <p class="mb-1 text-xs font-medium text-gray-500">{{ stepLabel[step.type] }}</p>

        <div
          v-if="step.type === 'tool_call'"
          class="rounded-md bg-cobalto-tenue px-2.5 py-1.5 font-mono text-[13px] break-all text-cobalto-oscuro"
        >
          {{ step.tool }}({{ JSON.stringify(step.arguments) }})
        </div>

        <pre
          v-else-if="step.type === 'tool_result'"
          class="max-h-40 overflow-auto rounded-md border border-gray-200 bg-gray-50 p-2.5 font-mono text-xs whitespace-pre-wrap break-all"
          >{{ step.content }}</pre
        >

        <div
          v-else-if="step.type === 'final'"
          class="rounded-md border-l-4 border-emerald-500 bg-emerald-50 p-3 font-medium text-gray-800 whitespace-pre-wrap"
        >
          {{ step.content }}
        </div>
      </li>
    </ol>
  </div>
</template>
