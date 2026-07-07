<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\JsonResponse;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\SessionManager;
use App\Core\View\View;

abstract class Controller
{
    protected Request $request;
    protected View $viewFactory;

    public function __construct(?Request $request = null, ?View $viewFactory = null)
    {
        $this->request = $request ?? Request::capture();
        $this->viewFactory = $viewFactory ?? new View();
    }

    protected function request(): Request
    {
        return $this->request;
    }

    protected function view(string $view, array $data = [], int $statusCode = 200): Response
    {
        return new Response($this->viewFactory->render($view, $data), $statusCode, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    protected function render(string $view, array $data = [], int $statusCode = 200): void
    {
        $this->view($view, $data, $statusCode)->send();
    }

    protected function response(string $content = '', int $statusCode = 200, array $headers = []): Response
    {
        return new Response($content, $statusCode, $headers);
    }

    protected function jsonResponse(array $payload, int $statusCode = 200): JsonResponse
    {
        return new JsonResponse($payload, $statusCode);
    }

    protected function json(array $payload, int $statusCode = 200): void
    {
        $this->jsonResponse($payload, $statusCode)->send();
    }

    protected function redirectResponse(string $url, int $statusCode = 302): RedirectResponse
    {
        return new RedirectResponse($url, $statusCode);
    }

    protected function redirect(string $url, int $statusCode = 302): void
    {
        $this->redirectResponse($url, $statusCode)->send();
        exit;
    }

    protected function wantsJson(): bool
    {
        return $this->request->wantsJson();
    }

    protected function startSession(): void
    {
        SessionManager::start();
    }
}
