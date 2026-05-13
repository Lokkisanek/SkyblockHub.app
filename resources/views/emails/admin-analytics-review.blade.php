<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="margin: 0 0 12px;">SkyblockHub admin analytics review</h1>
    <p style="margin: 0 0 16px;">Owner: {{ $report['owner'] ?? 'growth' }}</p>

    <h2 style="margin: 0 0 8px;">Key KPIs</h2>
    <ul>
        <li>Total events: {{ $report['kpis']['totalEvents'] ?? 0 }}</li>
        <li>CTA clicks: {{ $report['kpis']['landingCtaClicks'] ?? 0 }}</li>
        <li>Onboarding completion: {{ $report['kpis']['onboardingCompletionRatePct'] ?? 'n/a' }}%</li>
        <li>Top CTA: {{ array_key_first($report['topCtas'] ?? []) ?? 'n/a' }}</li>
    </ul>

    <h2 style="margin: 16px 0 8px;">Onboarding experiment</h2>
    <ul>
        @foreach (($report['onboardingExperimentVariants'] ?? []) as $row)
            <li>Variant {{ strtoupper((string) ($row['variant'] ?? '?')) }} - completion {{ $row['completion_rate_pct'] ?? 'n/a' }}%, dismiss {{ $row['dismiss_rate_pct'] ?? 'n/a' }}%</li>
        @endforeach
    </ul>

    <h2 style="margin: 16px 0 8px;">Plain English</h2>
    <ul>
        @foreach (($report['aiSummary']['plain_english'] ?? []) as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>
</body>
</html>