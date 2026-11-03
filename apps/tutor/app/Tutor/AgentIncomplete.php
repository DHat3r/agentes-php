<?php

namespace App\Tutor;

use RuntimeException;

/** El agente terminó sin hacer lo que se le pidió (por ejemplo, no registró el ejercicio). */
final class AgentIncomplete extends RuntimeException {}
