<script setup>
import { ref, onMounted } from 'vue'
import { request } from '../api.js'
import AgentTrace from './AgentTrace.vue'

const POLL_INTERVAL_MS = 2000
const MAX_POLL_ATTEMPTS = 60

const stats = ref(null)
const statsError = ref('')

const questions = ref([])
const currentPage = ref(1)
const lastPage = ref(1)
const questionsError = ref('')
const questionsLoading = ref(false)

const evaluation = ref(null)
const evaluationError = ref('')
const evaluating = ref(false)

const traffic = ref({ count: 10, distribution: 'uniform' })
const trafficMessage = ref('')
const trafficError = ref('')
const trafficLoading = ref(false)

const scoreFields = [
  { key: 'similarity_score', label: 'Similitud' },
  { key: 'quality_score', label: 'Calidad' },
  { key: 'completeness_score', label: 'Completitud' },
  { key: 'overall_score', label: 'General' },
]

function formatScore(value) {
  return typeof value === 'number' ? value.toFixed(2) : '—'
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

async function loadStats() {
  statsError.value = ''
  try {
    stats.value = await request('/qa/api/stats')
  } catch (e) {
    statsError.value = e.message
  }
}

async function loadQuestions(page = 1) {
  questionsError.value = ''
  questionsLoading.value = true
  try {
    const body = await request(`/qa/api/questions?page=${page}`)
    questions.value = body.data
    currentPage.value = body.current_page
    lastPage.value = body.last_page
  } catch (e) {
    questionsError.value = e.message
  } finally {
    questionsLoading.value = false
  }
}

async function pollEvaluation(id) {
  for (let attempt = 0; attempt < MAX_POLL_ATTEMPTS; attempt++) {
    const current = await request(`/qa/api/evaluations/${id}`)
    evaluation.value = current
    if (current.status === 'completed' || current.status === 'failed') {
      return current
    }
    await sleep(POLL_INTERVAL_MS)
  }
  throw new Error('La evaluación sigue en curso. Intenta revisarla más tarde.')
}

async function evaluate(questionId) {
  evaluationError.value = ''
  evaluation.value = null
  evaluating.value = true
  try {
    const created = await request('/qa/api/evaluations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question_id: questionId }),
    })
    evaluation.value = created
    await pollEvaluation(created.id)
    await loadStats()
  } catch (e) {
    evaluationError.value = e.message
  } finally {
    evaluating.value = false
  }
}

async function simulateTraffic() {
  trafficError.value = ''
  trafficMessage.value = ''
  trafficLoading.value = true
  try {
    const body = await request('/qa/api/traffic', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        count: Number(traffic.value.count),
        distribution: traffic.value.distribution,
      }),
    })
    trafficMessage.value = `Se encolaron ${body.queued} evaluaciones`
    await loadStats()
  } catch (e) {
    trafficError.value = e.message
  } finally {
    trafficLoading.value = false
  }
}

onMounted(() => {
  loadStats()
  loadQuestions(1)
})
</script>

<template>
  <div class="space-y-6">
    <!-- Estadísticas -->
    <section>
      <p v-if="statsError" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
        {{ statsError }}
      </p>
      <div v-else-if="stats" class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
          <p class="text-xs text-gray-500">Preguntas</p>
          <p class="text-2xl font-semibold">{{ stats.questions }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5">
          <p class="text-xs text-gray-500">Evaluaciones</p>
          <p class="text-sm">
            Completadas: <strong>{{ stats.evaluations.completed }}</strong>
          </p>
          <p class="text-sm">
            Fallidas: <strong>{{ stats.evaluations.failed }}</strong>
          </p>
          <p class="text-sm">
            Pendientes: <strong>{{ stats.evaluations.pending }}</strong>
          </p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5">
          <p class="text-xs text-gray-500">Puntaje promedio</p>
          <p class="text-2xl font-semibold">{{ formatScore(stats.average_overall) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5">
          <p class="text-xs text-gray-500">Caché</p>
          <p class="text-sm">Aciertos: <strong>{{ stats.cache.hits }}</strong></p>
          <p class="text-sm">Fallos: <strong>{{ stats.cache.misses }}</strong></p>
        </div>
      </div>
    </section>

    <!-- Simular tráfico -->
    <section class="rounded-xl border border-gray-200 bg-white p-5">
      <h2 class="mb-3 text-lg font-semibold">Simular tráfico</h2>
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="simulateTraffic">
        <label class="flex flex-col text-sm">
          Cantidad (1-50)
          <input
            v-model="traffic.count"
            type="number"
            min="1"
            max="50"
            required
            class="mt-1 w-28 rounded-md border border-gray-300 px-2 py-1"
          />
        </label>
        <label class="flex flex-col text-sm">
          Distribución
          <select
            v-model="traffic.distribution"
            class="mt-1 rounded-md border border-gray-300 px-2 py-1"
          >
            <option value="uniform">uniform</option>
            <option value="normal">normal</option>
            <option value="poisson">poisson</option>
          </select>
        </label>
        <button
          type="submit"
          :disabled="trafficLoading"
          class="rounded-md bg-cobalto px-4 py-2 text-sm font-medium text-white hover:bg-cobalto-oscuro disabled:opacity-50"
        >
          {{ trafficLoading ? 'Encolando…' : 'Simular' }}
        </button>
      </form>
      <p v-if="trafficMessage" class="mt-3 text-sm text-emerald-700">{{ trafficMessage }}</p>
      <p v-if="trafficError" class="mt-3 text-sm text-red-700">{{ trafficError }}</p>
    </section>

    <div class="grid gap-6 md:grid-cols-2">
      <!-- Preguntas -->
      <section class="rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="mb-3 text-lg font-semibold">Preguntas</h2>
        <p v-if="questionsError" class="mb-3 text-sm text-red-700">{{ questionsError }}</p>
        <p v-if="questionsLoading" class="mb-3 text-sm text-gray-500">Cargando…</p>

        <ul class="divide-y divide-gray-200">
          <li
            v-for="question in questions"
            :key="question.id"
            class="flex items-start justify-between gap-3 py-3"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium break-words">{{ question.question_text }}</p>
              <p class="mt-1 text-xs text-gray-500 break-words">{{ question.human_answer }}</p>
            </div>
            <button
              type="button"
              :disabled="evaluating"
              class="shrink-0 rounded-md bg-cobalto px-3 py-1 text-sm text-white hover:bg-cobalto-oscuro disabled:opacity-50"
              @click="evaluate(question.id)"
            >
              Evaluar
            </button>
          </li>
        </ul>

        <div class="mt-4 flex items-center justify-between text-sm">
          <button
            type="button"
            :disabled="currentPage <= 1 || questionsLoading"
            class="rounded-md border border-gray-300 px-3 py-1 disabled:opacity-40"
            @click="loadQuestions(currentPage - 1)"
          >
            Anterior
          </button>
          <span>Página {{ currentPage }} de {{ lastPage }}</span>
          <button
            type="button"
            :disabled="currentPage >= lastPage || questionsLoading"
            class="rounded-md border border-gray-300 px-3 py-1 disabled:opacity-40"
            @click="loadQuestions(currentPage + 1)"
          >
            Siguiente
          </button>
        </div>
      </section>

      <!-- Resultado de la evaluación -->
      <section class="rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="mb-3 text-lg font-semibold">Evaluación</h2>
        <p v-if="evaluating" class="mb-3 text-sm text-gray-500">Evaluando…</p>
        <p v-if="evaluationError" class="mb-3 text-sm text-red-700">{{ evaluationError }}</p>

        <div v-if="evaluation" class="space-y-4">
          <p class="text-sm">
            Estado: <strong>{{ evaluation.status }}</strong>
            <span v-if="evaluation.cached" class="ml-2 text-xs text-gray-500">(desde caché)</span>
          </p>

          <div v-if="evaluation.status === 'failed' && evaluation.error" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ evaluation.error }}
          </div>

          <div v-if="evaluation.llm_answer">
            <h3 class="text-sm font-semibold text-gray-600">Respuesta del LLM</h3>
            <p class="mt-1 text-sm whitespace-pre-wrap">{{ evaluation.llm_answer }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div v-for="field in scoreFields" :key="field.key" class="rounded-md bg-gray-50 p-3">
              <p class="text-xs text-gray-500">{{ field.label }}</p>
              <p class="text-lg font-semibold">{{ formatScore(evaluation[field.key]) }}</p>
            </div>
          </div>

          <AgentTrace :trace="evaluation.trace || []" />
        </div>
        <p v-else-if="!evaluating" class="text-sm text-gray-500">
          Selecciona una pregunta para evaluarla.
        </p>
      </section>
    </div>
  </div>
</template>
