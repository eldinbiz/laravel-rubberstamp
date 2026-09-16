<div class="section-header">3. Test Suites Summary Breakdown</div>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 55%;">Test Suite (File Path)</th>
            <th style="text-align: center;">Tests</th>
            <th style="text-align: center;">Passed</th>
            <th style="text-align: center;">Failed</th>
            <th style="text-align: center;">Duration</th>
            <th style="text-align: center;">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($suites as $s)
            <tr>
                <td><code>{{ $s['file'] }}</code></td>
                <td style="text-align: center;">{{ $s['total'] }}</td>
                <td style="text-align: center; color: #10b981; font-weight: 600;">{{ $s['passed'] }}</td>
                <td style="text-align: center; color: #ef4444; font-weight: 600;">{{ $s['failed'] }}</td>
                <td style="text-align: center; color: #64748b;">{{ $s['duration'] }}</td>
                <td style="text-align: center;"><span class="badge {{ $s['status'] === 'PASSED' ? 'badge-passed' : 'badge-failed' }}">{{ $s['status'] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align: center; color: #94a3b8;">No test suites recorded.</td></tr>
        @endforelse
    </tbody>
</table>
