<?php

declare(strict_types=1);

namespace App\Modules\Profile\Controllers;

use App\Core\Controller;
use App\Modules\Profile\Services\ProfileService;

class ProfileController extends Controller
{
    public function __construct(private ?ProfileService $profile = null)
    {
        parent::__construct();
        $this->profile ??= new ProfileService();
    }

    public function show(): void
    {
        $this->render($this->profile->defaultView(), $this->profile->profileData());
    }
}
