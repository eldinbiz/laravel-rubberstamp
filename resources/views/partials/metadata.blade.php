<div class="section-header">1. Document Control & Audit Metadata</div>
<table class="meta-table">
    <tr>
        <td class="meta-label">Document ID</td>
        <td class="meta-val"><code>{{ $meta['document_id'] ?? '' }}</code></td>
        <td class="meta-label">SOP Reference</td>
        <td class="meta-val"><code>{{ $meta['sop'] ?? 'N/A' }}</code></td>
    </tr>
    <tr>
        <td class="meta-label">Author (Tester)</td>
        <td class="meta-val">{{ $meta['author'] ?? '' }}</td>
        <td class="meta-label">Executed At</td>
        <td class="meta-val">{{ $meta['executed_at'] ?? '' }}</td>
    </tr>
    <tr>
        <td class="meta-label">Git Branch</td>
        <td class="meta-val"><code>{{ $meta['git']['branch'] ?? 'main' }}</code></td>
        <td class="meta-label">Git Commit</td>
        <td class="meta-val"><code>{{ $meta['git']['commit'] ?? 'N/A' }}</code></td>
    </tr>
    <tr>
        <td class="meta-label">Classification</td>
        <td class="meta-val">{{ $meta['classification'] ?? '' }}</td>
        <td class="meta-label">Environment</td>
        <td class="meta-val"><code>testing</code></td>
    </tr>
    <tr>
        <td class="meta-label">Runtime Stack</td>
        <td class="meta-val" colspan="3"><code>{{ $meta['runtime_stack'] ?? '' }}</code></td>
    </tr>
</table>
