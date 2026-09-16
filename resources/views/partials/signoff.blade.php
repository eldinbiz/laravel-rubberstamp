<div class="signoff-section">
    <div class="section-header">6. Formal Sign-Off Approval Sheet</div>
    <div class="signoff-category-title">Prepared By:</div>
    <table class="signoff-table">
        <thead>
            <tr>
                <th style="width: 28%;">Name</th>
                <th style="width: 18%;">Date</th>
                <th style="width: 28%;">Role</th>
                <th style="width: 26%;">Signature</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>{{ $meta['author'] ?? '' }}</strong></td>
                <td>{{ substr((string) ($meta['executed_at'] ?? ''), 0, 10) }}</td>
                <td>Developer (Test Executor)</td>
                <td><span class="signature-line" style="border-bottom:none; color:#10b981; font-weight:700;">[VERIFIED SYSTEM LOG]</span></td>
            </tr>
        </tbody>
    </table>

    @if (! empty($meta['reviewed_by']))
        <div class="signoff-category-title">Reviewed By:</div>
        <table class="signoff-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Name</th>
                    <th style="width: 18%;">Date</th>
                    <th style="width: 28%;">Role</th>
                    <th style="width: 26%;">Signature</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($meta['reviewed_by'] as $row)
                    <tr>
                        <td>
                            @if (!empty($row['name']))
                                <strong>{{ $row['name'] }}</strong>
                            @else
                                <span class="signature-line">________________________</span>
                            @endif
                        </td>
                        <td><span class="signature-line">__________</span></td>
                        <td>{{ $row['role'] }}</td>
                        <td><span class="signature-line">________________________</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($meta['approved_by']))
        <div class="signoff-category-title">Approved By:</div>
        <table class="signoff-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Name</th>
                    <th style="width: 18%;">Date</th>
                    <th style="width: 28%;">Role</th>
                    <th style="width: 26%;">Signature</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($meta['approved_by'] as $row)
                    <tr>
                        <td>
                            @if (!empty($row['name']))
                                <strong>{{ $row['name'] }}</strong>
                            @else
                                <span class="signature-line">________________________</span>
                            @endif
                        </td>
                        <td><span class="signature-line">__________</span></td>
                        <td>{{ $row['role'] }}</td>
                        <td><span class="signature-line">________________________</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($meta['acknowledged_by']))
        <div class="signoff-category-title">Acknowledged By:</div>
        <table class="signoff-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Name</th>
                    <th style="width: 18%;">Date</th>
                    <th style="width: 28%;">Role</th>
                    <th style="width: 26%;">Signature</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($meta['acknowledged_by'] as $row)
                    <tr>
                        <td>
                            @if (!empty($row['name']))
                                <strong>{{ $row['name'] }}</strong>
                            @else
                                <span class="signature-line">________________________</span>
                            @endif
                        </td>
                        <td><span class="signature-line">__________</span></td>
                        <td>{{ $row['role'] }}</td>
                        <td><span class="signature-line">________________________</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
