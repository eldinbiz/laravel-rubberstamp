@if (! empty($screenshots))
    <div class="section-header">5. Browser Test Visual Evidence & Screenshot Gallery</div>
    @foreach ($screenshots as $shot)
        @php
            $testCase = (string) $shot['test_case'];
            $suite = (string) $shot['suite'];
            $base64 = (string) $shot['base64'];
            $caseId = (string) ($shot['test_case_id'] ?? '');
            $caseIdLower = strtolower($caseId);
        @endphp
        <div class="screenshot-item" @if($caseId !== '') id="evidence-{{ $caseIdLower }}" @endif>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                <div style="font-weight: 700; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                    @if ($caseId !== '')
                        <code class="case-id">{{ $caseId }}</code>
                    @endif
                    <span>{{ $suite }} &rarr; {{ $testCase }}</span>
                </div>
                @if ($caseId !== '')
                    <a href="#case-{{ $caseIdLower }}" class="back-to-case" title="Return to test case in execution record">&uarr; Back to Case</a>
                @endif
            </div>
            <div class="screenshot-meta">Visual snapshot evidence captured during browser execution</div>
            <img src="{{ $base64 }}" alt="{{ $testCase }}" class="screenshot-img" />
        </div>
    @endforeach
@endif
