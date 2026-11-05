<?php
declare(strict_types=1);

namespace App\Modules\ReportesAnalitica\Controllers;

use App\Core\AiSqlReportService;
use App\Core\Controller;
use PDOException;
use Throwable;

/**
 * Controlador del Asistente Clínico Inteligente de Voz y Lenguaje Natural (Flotante Global).
 * Blindado contra exposición de errores SQL crudos.
 */
final class AsistenteIaController extends Controller
{
    public function consultar(): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(200);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rawBody  = file_get_contents('php://input');
        $jsonBody = is_string($rawBody) && trim($rawBody) !== '' ? json_decode($rawBody, true) : null;

        $question = trim((string) (
            $_POST['pregunta']
            ?? $_POST['prompt']
            ?? $_POST['q']
            ?? ($jsonBody['pregunta'] ?? $jsonBody['prompt'] ?? $jsonBody['q'] ?? '')
            ?? $_GET['pregunta']
            ?? $_GET['q']
            ?? ''
        ));

        if ($question === '') {
            $msg = 'Por favor, dime o escribe tu consulta sobre la Clínica Vida Salud. Puedo informarte sobre médicos, citas, pacientes, especialidades, roles y horarios.';
            echo json_encode([
                'ok'          => false,
                'message'     => $msg,
                'answer'      => $msg,
                'speech_text' => $msg,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $result = AiSqlReportService::answerClinicalQuestion($question);
            echo json_encode([
                'ok'           => $result['ok'],
                'question'     => $result['question'],
                'answer'       => $result['answer'],
                'speech_text'  => $result['speech_text'],
                'intent'       => $result['intent'],
                'engine'       => $result['engine'],
                'sql_used'     => $result['sql_used'],
                'data_preview' => $result['data_preview'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException | Throwable) {
            http_response_code(200);
            $friendly = 'Estoy disponible para ayudarte con información sobre la Clínica Vida Salud. Puedes consultarme sobre quiénes son los administradores, qué médicos atienden por especialidad, el resumen de citas médicas, el padrón de pacientes o los horarios de atención.';
            echo json_encode([
                'ok'          => true,
                'message'     => $friendly,
                'answer'      => $friendly,
                'speech_text' => $friendly,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
