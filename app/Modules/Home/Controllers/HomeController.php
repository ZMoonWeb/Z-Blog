<?php

declare(strict_types=1);

namespace App\Modules\Home\Controllers;

use App\Core\Controller;
use App\Modules\Home\Services\HomePageService;

class HomeController extends Controller
{
    public function __construct(private ?HomePageService $home = null)
    {
        parent::__construct();
        $this->home ??= new HomePageService();
    }

    public function index(?string $panel = null, string $guestbookView = 'list', ?int $guestbookMessageId = null): void
    {
        $data = $this->home->pageData($panel, $guestbookView, $guestbookMessageId);

        $this->render('home/index', $data);

        if (($data['activePanel'] ?? '') === 'guestbook' && session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['guestbook_error'], $_SESSION['guestbook_old'], $_SESSION['guestbook_success']);
        }
    }
}
