<div class="section-header">4. Detailed Test Case Execution Evidence</div>
@if (empty($suites))
    <p style="color:#64748b; font-size:12px;">No test cases recorded.</p>
@else
    @foreach ($suites as $suite)
        @php
            $suiteFile = $suite['file'];
            $status = (string) $suite['status'];
            $badgeClass = $status === 'PASSED' ? 'badge-passed' : 'badge-failed';
        @endphp
        <div class="suite-box">
            <div class="suite-header">
                <span><code>{{ $suiteFile }}</code></span>
                <span class="badge {{ $badgeClass }}">{{ $status }}</span>
            </div>
            @foreach ($suite['cases'] ?? [] as $case)
                @php
                    $caseId = (string) ($case['test_case_id'] ?? '');
                    $caseIdLower = strtolower($caseId);
                    $hasEvidence = ! empty($case['has_evidence']);
                    $caseName = (string) $case['name'];
                    $caseStatus = (string) $case['status'];
                    $caseDuration = (string) $case['duration'];
                @endphp
                <div class="case-row" @if($caseId !== '') id="case-{{ $caseIdLower }}" @endif>
                    <div>
                        @if ($caseStatus === 'PASSED')
                            <span class="badge badge-passed">✔ PASSED</span>
                        @else
                            <span class="badge badge-failed">✖ FAILED</span>
                        @endif

                        @if ($hasEvidence && $caseId !== '')
                            <a href="#evidence-{{ $caseIdLower }}" class="case-evidence-link" title="Jump to visual snapshot evidence"><code class="case-id" style="cursor: pointer;">{{ $caseId }} <span style="font-size: 10px;">📷</span></code></a>
                            <a href="#evidence-{{ $caseIdLower }}" style="margin-left: 8px; font-weight: 500; color: inherit; text-decoration: none;" title="Jump to visual snapshot evidence">{{ $caseName }}</a>
                        @else
                            @if ($caseId !== '')
                                <code class="case-id">{{ $caseId }}</code>
                            @endif
                            <span style="margin-left: 8px; font-weight: 500;">{{ $caseName }}</span>
                        @endif
                    </div>
                    <div style="color: #64748b; font-size: 11px;">{{ $caseDuration }}</div>
                </div>

                @if ($caseStatus === 'FAILED' && ! empty($case['failure']))
                    @php
                        $failureHeader = $caseId !== '' ? "FAILURE AUDIT TRACE [{$caseId}]:" : 'FAILURE AUDIT TRACE:';
                    @endphp
                    <div class="failure-trace">
<strong>{{ $failureHeader }}</strong>
Location: {{ $case['failure']['location'] }}
Error: {{ $case['failure']['message'] }}

{{ $case['failure']['snippet'] }}
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
@endif
