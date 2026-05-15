<?php

namespace App\Http\Controllers;

use App\Services\AdminAnalyticsReportService;
use App\Support\AdminGuildCrawlStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnaliticsController extends Controller
{
    public function __construct(
        private readonly AdminAnalyticsReportService $reportService,
    ) {
    }

    public function index(Request $request): Response
    {
        $days = (int) $request->integer('days', 30);
        return Inertia::render('Admin/Index', array_merge(
            $this->reportService->buildReport($days),
            ['guildCrawl' => AdminGuildCrawlStatus::snapshot()],
        ));
    }
}
