# Evaluador QA (Laravel)

Versión con agente de [Sistemas2](https://github.com/DHat3r/Sistemas2). Ver el [README principal](../../README.md) para ejecutar todo.

## Dónde está cada cosa

| Archivo | Qué hace |
|---------|----------|
| `app/Agent/QaAgent.php` | Prompt de sistema y armado del agente con sus herramientas |
| `app/Agent/Tools/` | `buscar_pregunta`, `buscar_respuestas_previas`, `evaluar_respuesta`, `guardar_resultado` |
| `app/Agent/AnswerJudge.php` | LLM como juez: puntajes de similitud, calidad y completitud |
| `app/Agent/EvaluationContext.php` | Estado compartido: qué respuestas ya calificó el juez |
| `app/Jobs/EvaluateQuestion.php` | Job de cola con 3 reintentos y backoff (reemplaza a Kafka + Flink) |
| `app/Support/TrafficSampler.php` | Generador de tráfico uniform, normal y poisson |
| `app/Support/EvaluationCache.php` | Caché de evaluaciones con contador de hits y misses |

## Flujo

`POST /api/evaluations` → crea la evaluación (`queued`) y encola el job → el worker corre el agente → el agente responde, pide al juez que la califique, la mejora si no aprueba (máx. 3 intentos) y guarda la mejor → `GET /api/evaluations/{id}` muestra puntajes y traza.

Importar más preguntas desde el dataset de Kaggle (Yahoo Answers):

```bash
docker compose exec qa php artisan qa:importar /ruta/train.csv --limite=1000
```
