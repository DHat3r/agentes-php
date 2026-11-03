<script setup>
import { ref, onMounted } from 'vue'
import { request } from '../api.js'
import AgentTrace from './AgentTrace.vue'

const students = ref([])
const selectedStudentId = ref('')
const newStudentName = ref('')
const studentsError = ref('')

const topics = ref([])
const selectedTopic = ref('')
const topicsError = ref('')

const progress = ref([])
const progressError = ref('')

const exercise = ref(null)
const answer = ref('')
const answerResult = ref(null)
const exerciseError = ref('')
const thinking = ref(false)

function jsonHeaders() {
  return { 'Content-Type': 'application/json' }
}

async function loadStudents() {
  studentsError.value = ''
  try {
    students.value = await request('/tutor/api/students')
    if (!selectedStudentId.value && students.value.length) {
      selectStudent(students.value[0].id)
    }
  } catch (e) {
    studentsError.value = e.message
  }
}

async function createStudent() {
  studentsError.value = ''
  try {
    const created = await request('/tutor/api/students', {
      method: 'POST',
      headers: jsonHeaders(),
      body: JSON.stringify({ name: newStudentName.value }),
    })
    newStudentName.value = ''
    await loadStudents()
    selectStudent(created.id)
  } catch (e) {
    studentsError.value = e.message
  }
}

async function loadTopics() {
  topicsError.value = ''
  try {
    topics.value = await request('/tutor/api/topics')
    if (!selectedTopic.value && topics.value.length) {
      selectedTopic.value = topics.value[0]
    }
  } catch (e) {
    topicsError.value = e.message
  }
}

async function loadProgress() {
  progressError.value = ''
  if (!selectedStudentId.value) {
    progress.value = []
    return
  }
  try {
    progress.value = await request(`/tutor/api/students/${selectedStudentId.value}/progress`)
  } catch (e) {
    progressError.value = e.message
  }
}

function selectStudent(id) {
  selectedStudentId.value = id
  exercise.value = null
  answerResult.value = null
  answer.value = ''
  loadProgress()
}

async function requestExercise() {
  exerciseError.value = ''
  exercise.value = null
  answerResult.value = null
  answer.value = ''
  thinking.value = true
  try {
    exercise.value = await request(`/tutor/api/students/${selectedStudentId.value}/exercises`, {
      method: 'POST',
      headers: jsonHeaders(),
      body: JSON.stringify({ topic: selectedTopic.value }),
    })
  } catch (e) {
    exerciseError.value = e.message
  } finally {
    thinking.value = false
  }
}

async function submitAnswer() {
  exerciseError.value = ''
  thinking.value = true
  try {
    answerResult.value = await request(`/tutor/api/exercises/${exercise.value.id}/answer`, {
      method: 'POST',
      headers: jsonHeaders(),
      body: JSON.stringify({ answer: answer.value }),
    })
    await loadProgress()
  } catch (e) {
    exerciseError.value = e.message
  } finally {
    thinking.value = false
  }
}

onMounted(() => {
  loadStudents()
  loadTopics()
})
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-6 md:grid-cols-2">
      <!-- Estudiante -->
      <section class="rounded-lg bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-lg font-semibold">Estudiante</h2>
        <p v-if="studentsError" class="mb-3 text-sm text-red-700">{{ studentsError }}</p>

        <label class="block text-sm">
          Seleccionar estudiante
          <select
            :value="selectedStudentId"
            class="mt-1 w-full rounded-md border border-gray-300 px-2 py-1"
            @change="selectStudent($event.target.value)"
          >
            <option value="" disabled>— Elige un estudiante —</option>
            <option v-for="student in students" :key="student.id" :value="student.id">
              {{ student.name }}
            </option>
          </select>
        </label>

        <form class="mt-4 flex gap-2" @submit.prevent="createStudent">
          <input
            v-model="newStudentName"
            type="text"
            placeholder="Nombre del nuevo estudiante"
            required
            class="min-w-0 flex-1 rounded-md border border-gray-300 px-2 py-1 text-sm"
          />
          <button
            type="submit"
            class="shrink-0 rounded-md bg-gray-800 px-3 py-1 text-sm text-white hover:bg-gray-700"
          >
            Crear
          </button>
        </form>
      </section>

      <!-- Progreso -->
      <section class="rounded-lg bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-lg font-semibold">Progreso</h2>
        <p v-if="progressError" class="mb-3 text-sm text-red-700">{{ progressError }}</p>
        <p v-if="!selectedStudentId" class="text-sm text-gray-500">Selecciona un estudiante.</p>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="border-b text-xs text-gray-500">
              <tr>
                <th class="py-2 pr-2">Tema</th>
                <th class="py-2 pr-2">Nivel</th>
                <th class="py-2 pr-2">Aciertos seguidos</th>
                <th class="py-2">Errores seguidos</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in progress" :key="row.topic" class="border-b last:border-0">
                <td class="py-2 pr-2">{{ row.topic }}</td>
                <td class="py-2 pr-2">{{ row.level }}</td>
                <td class="py-2 pr-2">{{ row.correct_streak }}</td>
                <td class="py-2">{{ row.wrong_streak }}</td>
              </tr>
              <tr v-if="!progress.length">
                <td colspan="4" class="py-2 text-gray-500">Sin progreso registrado.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- Ejercicio -->
    <section class="rounded-lg bg-white p-4 shadow-sm">
      <h2 class="mb-3 text-lg font-semibold">Ejercicio</h2>
      <p v-if="topicsError" class="mb-3 text-sm text-red-700">{{ topicsError }}</p>

      <div class="flex flex-wrap items-end gap-3">
        <label class="flex flex-col text-sm">
          Tema
          <select
            v-model="selectedTopic"
            :disabled="thinking"
            class="mt-1 rounded-md border border-gray-300 px-2 py-1"
          >
            <option v-for="topic in topics" :key="topic" :value="topic">{{ topic }}</option>
          </select>
        </label>
        <button
          type="button"
          :disabled="thinking || !selectedStudentId || !selectedTopic"
          class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
          @click="requestExercise"
        >
          Pedir ejercicio
        </button>
      </div>

      <p v-if="thinking" class="mt-4 text-sm text-gray-500">El agente está pensando…</p>
      <p v-if="exerciseError" class="mt-4 text-sm text-red-700">{{ exerciseError }}</p>

      <div v-if="exercise" class="mt-4 space-y-4">
        <div class="rounded-md bg-gray-50 p-3">
          <p class="text-xs text-gray-500">{{ exercise.topic }} · nivel {{ exercise.level }}</p>
          <p class="mt-1 whitespace-pre-wrap">{{ exercise.statement }}</p>
        </div>

        <form v-if="!answerResult" class="space-y-2" @submit.prevent="submitAnswer">
          <textarea
            v-model="answer"
            rows="3"
            required
            :disabled="thinking"
            placeholder="Escribe tu respuesta"
            class="w-full rounded-md border border-gray-300 px-2 py-1 text-sm"
          ></textarea>
          <button
            type="submit"
            :disabled="thinking"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
          >
            Responder
          </button>
        </form>

        <div v-if="answerResult" class="space-y-3">
          <p
            class="font-semibold"
            :class="answerResult.correct ? 'text-emerald-700' : 'text-red-700'"
          >
            {{ answerResult.correct ? 'Correcto' : 'Incorrecto' }}
          </p>
          <p class="text-sm whitespace-pre-wrap">{{ answerResult.feedback }}</p>
          <p class="text-sm">Nuevo nivel: <strong>{{ answerResult.new_level }}</strong></p>
          <AgentTrace :trace="answerResult.trace || []" />
          <button
            type="button"
            :disabled="thinking"
            class="rounded-md border border-gray-300 px-3 py-1 text-sm disabled:opacity-50"
            @click="requestExercise"
          >
            Otro ejercicio
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
