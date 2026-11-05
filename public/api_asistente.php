<?php
declare(strict_types=1);

/**
 * Endpoint Directo Infalible del Asistente Clínico Vida Salud (Voz / IA)
 * Garantiza respuesta JSON limpia sin interferencia de buffers o redirecciones HTML.
 */

ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/AiSqlReportService.php';

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

try {
    $rawBody  = file_get_contents('php://input');
    $jsonBody = (is_string($rawBody) && trim($rawBody) !== '') ? json_decode($rawBody, true) : null;

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
        $msg = 'Por favor, dime o escribe tu consulta sobre la Clínica Vida Salud.';
        echo json_encode([
            'ok'          => false,
            'message'     => $msg,
            'answer'      => $msg,
            'speech_text' => $msg,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = \App\Core\AiSqlReportService::answerClinicalQuestion($question);

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
} catch (\Throwable $e) {
    http_response_code(200);
    $fallback = 'Error al procesar la consulta clínica: ' . $e->getMessage();
    echo json_encode([
        'ok'          => false,
        'message'     => $fallback,
        'answer'      => $fallback,
        'speech_text' => 'Ocurrió un inconveniente al consultar la base de datos.',
    ], JSON_UNESCAPED_UNICODE);
}
exit;
