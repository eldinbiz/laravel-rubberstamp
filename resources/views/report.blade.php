<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formal Test Execution Record - {{ $meta['document_id'] ?? '' }}</title>
    @include('rubberstamp::partials.styles')
</head>
<body>
    <div class="report-sheet">
        @include('rubberstamp::partials.header')
        @include('rubberstamp::partials.metadata')
        @include('rubberstamp::partials.summary')
        @include('rubberstamp::partials.suites-table')
        @include('rubberstamp::partials.cases')
        @include('rubberstamp::partials.screenshots')
        @include('rubberstamp::partials.signoff')
    </div>
</body>
</html>
