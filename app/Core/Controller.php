<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Controlador Base MVC Modular
 */
abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        Auth::sendNoCacheHeaders();

        $viewFile = ROOT_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }

        $data['currentUser'] = Auth::user();
        $data['csrfToken'] = Auth::csrfToken();
        $data['flashes'] = Auth::getFlashes();

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = ROOT_PATH . '/views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        require $layoutFile;
    }

    protected function json(array $payload, int $statusCode = 200): never
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $route = '', array $params = []): never
    {
        $target = url($route, $params);
        if (!headers_sent()) {
            header('Location: ' . $target);
        }
        exit;
    }

    protected function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    protected function validatePostWithCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'message' => 'Método HTTP no permitido.'], 405);
            }
            $this->redirect('dashboard');
        }

        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Auth::verifyCsrf(is_string($token) ? $token : null)) {
            if ($this->isAjax()) {
                $this->json(['ok' => false, 'message' => 'La sesión del formulario expiró. Recargue la página e intente nuevamente.'], 419);
            }
            Auth::setFlash('error', 'Token de seguridad inválido o expirado. Intente nuevamente.', 'Seguridad CSRF');
            $this->redirect('dashboard');
        }
    }
}
