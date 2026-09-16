<style>
    :root {
        --primary: #1e293b;
        --primary-light: #f8fafc;
        --border: #cbd5e1;
        --border-light: #e2e8f0;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
    }

    html {
        scroll-behavior: smooth;
    }

    :target {
        scroll-margin-top: 24px;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: var(--text-main);
        background-color: #f1f5f9;
        padding: 30px 15px;
        font-size: 13px;
        line-height: 1.5;
    }

    .report-sheet {
        max-width: 1020px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        padding: 40px;
    }

    .header-grid {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 2px solid var(--primary);
        padding-bottom: 20px;
        margin-bottom: 25px;
    }

    .header-title-box h1 {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--primary);
        text-transform: uppercase;
    }

    .header-title-box p {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .section-header {
        background-color: #f8fafc;
        border-left: 4px solid var(--primary);
        padding: 8px 12px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--primary);
        margin-top: 30px;
        margin-bottom: 12px;
    }

    .meta-table, .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    .meta-table td {
        border: 1px solid var(--border-light);
        padding: 7px 10px;
        font-size: 12px;
    }

    .meta-label {
        background-color: #f8fafc;
        font-weight: 600;
        color: var(--text-muted);
        width: 16%;
    }

    .meta-val {
        font-weight: 500;
        width: 34%;
    }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    .metric-card {
        border: 1px solid var(--border-light);
        border-radius: 6px;
        padding: 12px;
        background: #ffffff;
        text-align: center;
    }

    .metric-card.verdict {
        background-color: {{ $verdictBg ?? '#10b981' }};
        color: #ffffff;
        border-color: {{ $verdictBg ?? '#10b981' }};
    }

    .metric-card.verdict .metric-title {
        color: rgba(255, 255, 255, 0.9);
    }

    .metric-card.verdict .metric-number {
        color: #ffffff;
    }

    .metric-title {
        font-size: 11px;
        text-transform: uppercase;
        font-weight: 600;
        color: var(--text-muted);
    }

    .metric-number {
        font-size: 20px;
        font-weight: 800;
        margin-top: 4px;
        color: var(--primary);
    }

    .data-table th {
        background-color: #f1f5f9;
        border: 1px solid var(--border);
        padding: 8px 10px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--primary);
    }

    .data-table td {
        border: 1px solid var(--border-light);
        padding: 8px 10px;
        font-size: 12px;
    }

    .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .badge-passed {
        background-color: #dcfce7;
        color: #15803d;
    }

    .badge-failed {
        background-color: #fee2e2;
        color: #b91c1c;
    }

    .suite-box {
        border: 1px solid var(--border-light);
        border-radius: 6px;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .suite-header {
        background: #f8fafc;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 12px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
    }

    .case-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 12px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
    }

    .case-row:last-child {
        border-bottom: none;
    }

    .case-id {
        display: inline-block;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        font-weight: 700;
        color: #334155;
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
        margin-left: 6px;
    }

    .case-evidence-link {
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        color: inherit;
    }

    .case-evidence-link:hover .case-id {
        background-color: #e2e8f0;
        border-color: #94a3b8;
    }

    .back-to-case {
        font-size: 11px;
        color: #64748b;
        text-decoration: none;
        padding: 3px 8px;
        border-radius: 4px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        font-weight: 500;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .back-to-case:hover {
        color: #0f172a;
        background: #e2e8f0;
    }

    .failure-trace {
        background: #fff1f2;
        border: 1px solid #fecdd3;
        border-radius: 4px;
        padding: 10px;
        margin: 8px 12px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 11px;
        color: #9f1239;
        white-space: pre-wrap;
    }

    .screenshot-item {
        border: 1px solid var(--border-light);
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 15px;
        background: #fafafa;
    }

    .screenshot-meta {
        font-size: 11px;
        color: var(--text-muted);
        margin-bottom: 8px;
    }

    .screenshot-img {
        max-width: 100%;
        height: auto;
        border: 1px solid var(--border);
        border-radius: 4px;
        display: block;
    }

    .signoff-section {
        margin-top: 30px;
        page-break-inside: avoid;
    }

    .signoff-category-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--primary);
        margin: 15px 0 6px 0;
    }

    .signoff-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    .signoff-table th {
        background-color: #f8fafc;
        border: 1px solid var(--border);
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 700;
        text-align: left;
        text-transform: uppercase;
    }

    .signoff-table td {
        border: 1px solid var(--border-light);
        padding: 10px 10px;
        font-size: 12px;
        vertical-align: middle;
    }

    .signature-line {
        display: inline-block;
        min-width: 140px;
        border-bottom: 1px dashed var(--border);
        color: var(--text-muted);
        font-style: italic;
    }

    @media print {
        body {
            background: #ffffff;
            padding: 0;
        }

        .report-sheet {
            border: none;
            box-shadow: none;
            padding: 0;
            max-width: 100%;
        }

        .signoff-section, .suite-box, .screenshot-item {
            page-break-inside: avoid;
        }
    }
</style>
