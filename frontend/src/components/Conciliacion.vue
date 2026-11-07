<script setup>
import { ref, computed, onMounted } from 'vue'
import { request } from '../api.js'
import AgentTrace from './AgentTrace.vue'

const formatter = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' })

const cartolaFile = ref(null)
const facturasFile = ref(null)
const submitting = ref(false)
const submitError = ref('')

const historial = ref([])
const historialError = ref('')

const conciliacion = ref(null)
const detailError = ref('')
const detailLoading = ref(false)

const estadoLabels = {
  conciliado: 'Conciliado',
  revision: 'En revisión',
  pendiente: 'Pendiente',
}

const estadoClasses = {
  conciliado: 'bg-emerald-100 text-emerald-800',
  revision: 'bg-yellow-100 text-yellow-800',
  pendiente: 'bg-gray-200 text-gray-700',
}

const resumenFields = computed(() => {
  const r = conciliacion.value?.resumen
  if (!r) return []
  return [
    { key: 'total_movimientos', label: 'Movimientos', value: r.total_movimientos },
    { key: 'conciliados', label: 'Conciliados', value: r.conciliados },
    { key: 'en_revision', label: 'En revisión', value: r.en_revision },
    { key: 'pendientes', label: 'Pendientes', value: r.pendientes },
  ]
})

function onCartolaChange(event) {
  cartolaFile.value = event.target.files[0] || null
}

function onFacturasChange(event) {
  facturasFile.value = event.target.files[0] || null
}

async function loadHistorial() {
  historialError.value = ''
  try {
    historial.value = await request('/conciliacion/api/conciliaciones')
  } catch (e) {
    historialError.value = e.message
  }
}

async function loadDetalle(id) {
  detailError.value = ''
  detailLoading.value = true
  try {
    conciliacion.value = await request(`/conciliacion/api/conciliaciones/${id}`)
  } catch (e) {
    detailError.value = e.message
  } finally {
    detailLoading.value = false
  }
}

async function subirArchivos() {
  submitError.value = ''
  if (!cartolaFile.value || !facturasFile.value) {
    submitError.value = 'Debes seleccionar la cartola y las facturas.'
    return
  }

  const formData = new FormData()
  formData.append('cartola', cartolaFile.value)
  formData.append('facturas', facturasFile.value)

  submitting.value = true
  try {
    const created = await request('/conciliacion/api/conciliaciones', {
      method: 'POST',
      body: formData,
    })
    conciliacion.value = created
    await loadHistorial()
  } catch (e) {
    submitError.value = e.message
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadHistorial()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Subida de archivos -->
    <section class="rounded-xl border border-gray-200 bg-white p-5">
      <h2 class="mb-3 text-lg font-semibold">Nueva conciliación</h2>

      <form class="space-y-4" @submit.prevent="subirArchivos">
        <div class="grid gap-4 md:grid-cols-2">
          <label class="block text-sm">
            Cartola (CSV)
            <input
              type="file"
              name="cartola"
              accept=".csv"
              class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-cobalto-tenue file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-cobalto"
              @change="onCartolaChange"
            />
          </label>
          <label class="block text-sm">
            Facturas (CSV)
            <input
              type="file"
              name="facturas"
              accept=".csv"
              class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-cobalto-tenue file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-cobalto"
              @change="onFacturasChange"
            />
          </label>
        </div>

        <div class="flex flex-wrap items-center gap-4 text-sm">
          <a
            href="/conciliacion/api/ejemplos/cartola"
            download
            class="text-cobalto underline underline-offset-2 hover:text-cobalto-oscuro"
          >
            Descargar ejemplo de cartola
          </a>
          <a
            href="/conciliacion/api/ejemplos/facturas"
            download
            class="text-cobalto underline underline-offset-2 hover:text-cobalto-oscuro"
          >
            Descargar ejemplo de facturas
          </a>
        </div>

        <button
          type="submit"
          :disabled="submitting"
          class="rounded-md bg-cobalto px-4 py-2 text-sm font-medium text-white hover:bg-cobalto-oscuro disabled:opacity-50"
        >
          {{ submitting ? 'Conciliando…' : 'Conciliar' }}
        </button>
        <p v-if="submitError" class="text-sm text-red-700">{{ submitError }}</p>
      </form>
    </section>

    <div class="grid gap-6 md:grid-cols-3">
      <!-- Historial -->
      <section class="rounded-xl border border-gray-200 bg-white p-5 md:col-span-1">
        <h2 class="mb-3 text-lg font-semibold">Historial</h2>
        <p v-if="historialError" class="mb-3 text-sm text-red-700">{{ historialError }}</p>
        <ul class="divide-y divide-gray-200">
          <li v-for="item in historial" :key="item.id">
            <button
              type="button"
              class="w-full py-2 text-left text-sm hover:bg-gray-50"
              :class="conciliacion?.id === item.id ? 'font-semibold' : ''"
              @click="loadDetalle(item.id)"
            >
              <span class="block">{{ item.creada_en }}</span>
              <span class="block text-xs text-gray-500">
                {{ item.total_movimientos }} movimientos · {{ item.conciliados }} conciliados
              </span>
            </button>
          </li>
          <li v-if="!historial.length" class="py-2 text-sm text-gray-500">
            Aún no hay conciliaciones.
          </li>
        </ul>
      </section>

      <!-- Detalle -->
      <section class="rounded-xl border border-gray-200 bg-white p-5 md:col-span-2">
        <h2 class="mb-3 text-lg font-semibold">Detalle</h2>
        <p v-if="detailLoading" class="text-sm text-gray-500">Cargando…</p>
        <p v-if="detailError" class="text-sm text-red-700">{{ detailError }}</p>

        <div v-if="conciliacion && !detailLoading" class="space-y-6">
          <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div v-for="campo in resumenFields" :key="campo.key" class="rounded-md bg-gray-50 p-3">
              <p class="text-xs text-gray-500">{{ campo.label }}</p>
              <p class="text-lg font-semibold">{{ campo.value }}</p>
            </div>
          </div>

          <div class="overflow-x-auto">
            <h3 class="mb-2 text-sm font-semibold text-gray-600">Movimientos</h3>
            <table class="w-full text-left text-sm">
              <thead class="border-b text-xs text-gray-500">
                <tr>
                  <th class="py-2 pr-2">Fecha</th>
                  <th class="py-2 pr-2">Descripción</th>
                  <th class="py-2 pr-2 text-right">Monto</th>
                  <th class="py-2 pr-2">Estado</th>
                  <th class="py-2 pr-2">Factura</th>
                  <th class="py-2">Motivo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="mov in conciliacion.movimientos" :key="mov.id" class="border-b last:border-0 align-top">
                  <td class="py-2 pr-2 whitespace-nowrap">{{ mov.fecha }}</td>
                  <td class="py-2 pr-2">{{ mov.descripcion }}</td>
                  <td class="py-2 pr-2 text-right whitespace-nowrap">{{ formatter.format(mov.monto) }}</td>
                  <td class="py-2 pr-2">
                    <span
                      class="rounded-full px-2 py-0.5 text-xs font-medium"
                      :class="estadoClasses[mov.estado] || estadoClasses.pendiente"
                    >
                      {{ estadoLabels[mov.estado] || mov.estado }}
                    </span>
                    <span v-if="mov.origen" class="ml-1 text-xs text-gray-500">({{ mov.origen }})</span>
                  </td>
                  <td class="py-2 pr-2">{{ mov.factura_numero || '—' }}</td>
                  <td class="py-2 text-xs text-gray-600">{{ mov.motivo || '—' }}</td>
                </tr>
                <tr v-if="!conciliacion.movimientos?.length">
                  <td colspan="6" class="py-2 text-gray-500">Sin movimientos.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <AgentTrace :trace="conciliacion.trace || []" />
        </div>

        <p v-else-if="!detailLoading" class="text-sm text-gray-500">
          Sube archivos o elige una conciliación del historial.
        </p>
      </section>
    </div>
  </div>
</template>
