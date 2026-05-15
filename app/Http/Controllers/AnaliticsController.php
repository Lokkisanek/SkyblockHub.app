<?php

namespace App\Http\Controllers;

use App\Services\AdminAnalyticsReportService;
use App\Services\AdminOperationsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnaliticsController extends Controller
{
    public function __construct(
        private readonly AdminAnalyticsReportService $reportService,
        private readonly AdminOperationsService $operationsService,
    ) {
    }

    public function index(Request $request): Response
    {
        $days = (int) $request->integer('days', 30);
        $operations = $this->operationsService->buildSnapshot();

        return Inertia::render('Admin/Index', array_merge(
            $this->reportService->buildReport($days),
            [
                'operations' => $operations,
                'guildCrawl' => $operations['guild_crawl'] ?? [],
            ],
        ));
    }
}
