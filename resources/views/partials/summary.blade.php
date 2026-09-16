<div class="section-header">2. Executive Test Verdict & Summary</div>
<div class="metrics-grid">
    <div class="metric-card verdict">
        <div class="metric-title">Audit Verdict</div>
        <div class="metric-number">{{ $data['verdict'] ?? 'UNKNOWN' }} ({{ $passRate ?? 0 }}%)</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Total Tests</div>
        <div class="metric-number">{{ $totalTests ?? ($data['total_tests'] ?? 0) }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Passed / Failed</div>
        <div class="metric-number"><span style="color:#10b981;">{{ $passedTests ?? ($data['passed_tests'] ?? 0) }}</span> / <span style="color:#ef4444;">{{ $failedTests ?? ($data['failed_tests'] ?? 0) }}</span></div>
    </div>
    <div class="metric-card">
        <div class="metric-title">Assertions / Duration</div>
        <div class="metric-number" style="font-size:16px;">{{ $totalAssertions ?? ($data['total_assertions'] ?? 0) }} / {{ $duration ?? ($data['duration'] ?? '0.00s') }}</div>
    </div>
</div>
