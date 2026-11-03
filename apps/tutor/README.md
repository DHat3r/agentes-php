# Tutor adaptativo (Laravel)

Ver el [README principal](../../README.md) para ejecutar todo.

## Dónde está cada cosa

| Archivo | Qué hace |
|---------|----------|
| `app/Tutor/TutorAgent.php` | Dos flujos con su propio prompt: generar un ejercicio y corregir una respuesta |
| `app/Tutor/Tools/` | `consultar_progreso`, `registrar_ejercicio`, `obtener_ejercicio`, `corregir`, `ajustar_nivel` |
| `app/Tutor/LevelPolicy.php` | Regla determinista: 3 correctas seguidas suben un nivel y 2 incorrectas lo bajan (1 a 5) |
| `app/Tutor/Topics.php` | Temas y qué significa cada nivel |

## Garantías que no dependen del modelo

- La respuesta esperada nunca viaja al navegador (`$hidden` en `Exercise`).
- El ejercicio siempre es del nivel real del estudiante.
- El nivel se ajusta aunque el modelo olvide llamar a `ajustar_nivel`.
- Errores: 503 si el modelo no responde, 502 si no completa la tarea, 409 si el ejercicio ya fue respondido.
