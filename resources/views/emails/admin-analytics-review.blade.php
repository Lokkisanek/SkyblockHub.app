<!doctype html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <h1 style="margin: 0 0 12px;">SkyblockHub admin analytics review</h1>
    <p style="margin: 0 0 16px;">Owner: {{ $report['owner'] ?? 'growth' }}</p>

    <h2 style="margin: 0 0 8px;">Weekly review</h2>
    <ul>
        <li>Landing to billing: {{ $report['weeklyReview']['current']['rates']['landing_to_billing'] ?? 'n/a' }}%</li>
        <li>Billing to checkout: {{ $report['weeklyReview']['current']['rates']['billing_to_checkout'] ?? 'n/a' }}%</li>
        <li>Checkout success: {{ $report['weeklyReview']['current']['rates']['checkout_success'] ?? 'n/a' }}%</li>
        <li>Trial starts delta: {{ $report['weeklyReview']['delta']['trial_starts'] ?? 0 }}</li>
    </ul>

    <h2 style="margin: 16px 0 8px;">Alerts</h2>
    <ul>
        @foreach (($report['conversionAlerts'] ?? []) as $alert)
            <li>{{ $alert['title'] ?? 'Alert' }} - {{ $alert['message'] ?? '' }} (owner: {{ $alert['owner'] ?? 'n/a' }})</li>
        @endforeach
    </ul>

    <h2 style="margin: 16px 0 8px;">Onboarding experiment</h2>
    <ul>
        @foreach (($report['onboardingExperimentVariants'] ?? []) as $row)
            <li>Variant {{ strtoupper((string) ($row['variant'] ?? '?')) }} - completion {{ $row['completion_rate_pct'] ?? 'n/a' }}%, dismiss {{ $row['dismiss_rate_pct'] ?? 'n/a' }}%</li>
        @endforeach
    </ul>

    <h2 style="margin: 16px 0 8px;">Upgrade prompt experiment</h2>
    <ul>
        @foreach (($report['experimentVariants'] ?? []) as $row)
            <li>Variant {{ strtoupper((string) ($row['variant'] ?? '?')) }} - CTA {{ $row['cta_rate_pct'] ?? 'n/a' }}%, compare {{ $row['compare_rate_pct'] ?? 'n/a' }}%</li>
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