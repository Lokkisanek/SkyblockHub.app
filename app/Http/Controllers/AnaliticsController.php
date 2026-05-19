<?php

namespace App\Http\Controllers;

use App\Services\AdminOperationsService;
use Inertia\Inertia;
use Inertia\Response;

class AnaliticsController extends Controller
{
    public function __construct(
        private readonly AdminOperationsService $operationsService,
    ) {}

    public function index(): Response
    {
        $operations = $this->operationsService->buildSnapshot();

        return Inertia::render('Admin/Index', [
            'operations' => $operations,
            'guildCrawl' => $operations['guild_crawl'] ?? [],
        ]);
    }
}
