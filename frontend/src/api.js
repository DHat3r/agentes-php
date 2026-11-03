/**
 * Llama a una ruta relativa del gateway y devuelve el JSON parseado.
 * Lanza Error con el mensaje del backend (body.message o body.error) si la respuesta no es ok.
 */
export async function request(path, options = {}) {
  const headers = { Accept: 'application/json', ...(options.headers || {}) }

  const response = await fetch(path, { ...options, headers })

  const text = await response.text()
  let body = null
  try {
    body = text ? JSON.parse(text) : null
  } catch {
    body = null
  }

  if (!response.ok) {
    const message = body?.message || body?.error || `Error HTTP ${response.status}`
    throw new Error(message)
  }

  return body
}
