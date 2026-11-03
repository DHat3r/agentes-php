<script setup>
defineProps({
  // Array de { type: 'tool_call'|'tool_result'|'final', tool: string|null, arguments: object, content: string }
  trace: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <div v-if="trace.length" class="space-y-2">
    <h3 class="text-sm font-semibold text-gray-600">Traza del agente</h3>
    <ul class="space-y-2">
      <li v-for="(step, index) in trace" :key="index" class="text-sm">
        <div v-if="step.type === 'tool_call'" class="font-mono text-gray-700 break-all">
          🔧 {{ step.tool }}({{ JSON.stringify(step.arguments) }})
        </div>

        <pre
          v-else-if="step.type === 'tool_result'"
          class="max-h-40 overflow-auto rounded bg-gray-100 p-2 text-xs whitespace-pre-wrap break-all"
          >{{ step.content }}</pre
        >

        <div
          v-else-if="step.type === 'final'"
          class="rounded-md border-l-4 border-emerald-500 bg-emerald-50 p-3 font-medium text-gray-800 whitespace-pre-wrap"
        >
          {{ step.content }}
        </div>
      </li>
    </ul>
  </div>
</template>
