<div class="header-grid">
    <div class="header-title-box">
        <h1>Formal Test Execution Record</h1>
        <p>Quality Assurance & Compliance Audit Evidence | {{ $meta['company_name'] ?? '' }}</p>
    </div>
    <div class="header-logo-box">
        @if (!empty($meta['logo_path']) && file_exists($meta['logo_path']))
            <img src="{{ $meta['logo_path'] }}" alt="Logo" style="max-height: 52px;" />
        @else
            <svg width="180" height="52" viewBox="0 0 180 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="180" height="52" rx="6" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="1.5"/>
                <rect x="8" y="8" width="36" height="36" rx="4" fill="#0F172A"/>
                <path d="M26 16L33 28H19L26 16Z" fill="#38BDF8"/>
                <text x="52" y="24" fill="#0F172A" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="12" font-weight="700">COMPANY LOGO</text>
                <text x="52" y="38" fill="#64748B" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="10" font-weight="500">Official Audit Record</text>
            </svg>
        @endif
    </div>
</div>
