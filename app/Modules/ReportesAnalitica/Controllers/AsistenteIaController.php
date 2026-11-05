<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\AiSqlReportService;
use App\Core\Auth;
use App\Core\Controller;
use Throwable;

/**
 * Controlador del Asistente Clínico Inteligente de Voz y Lenguaje Natural (Flotante Global).
 */
final class AsistenteIaController extends Controller
{
    public function consultar(): void
    {
        Auth::requireLogin();

        $rawBody = file_get_contents('php://input');
        $jsonBody = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;

        $question = trim((string) (
            $_POST['pregunta']
            ?? $_POST['prompt']
            ?? ($jsonBody['pregunta'] ?? $jsonBody['prompt'] ?? '')
        ));

        if ($question === '') {
            $this->json([
                'ok'          => false,
                'message'     => 'Por favor, habla o escribe una pregunta sobre la clínica.',
                'answer'      => 'Por favor, dime o escribe tu consulta sobre la Clínica Vida Salud.',
                'speech_text' => 'Por favor, dime o escribe tu consulta sobre la Clínica Vida Salud.',
            ], 422);
        }

        try {
            $result = AiSqlReportService::answerClinicalQuestion($question);
            $this->json([
                'ok'           => $result['ok'],
                'question'     => $result['question'],
                'answer'       => $result['answer'],
                'speech_text'  => $result['speech_text'],
                'intent'       => $result['intent'],
                'engine'       => $result['engine'],
                'sql_used'     => $result['sql_used'],
                'data_preview' => $result['data_preview'],
            ]);
        } catch (Throwable $e) {
            $fallback = 'Ocurrió un inconveniente al consultar la base de datos: ' . $e->getMessage();
            $this->json([
                'ok'          => false,
                'message'     => $fallback,
                'answer'      => $fallback,
                'speech_text' => 'Ocurrió un inconveniente al consultar la base de datos.',
            ], 500);
        }
    }
}
