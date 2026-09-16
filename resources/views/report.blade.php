<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formal Test Execution Record - {{ $meta['document_id'] ?? '' }}</title>
    @include('unit-tester-documenter::partials.styles')
</head>
<body>
    <div class="report-sheet">
        @include('unit-tester-documenter::partials.header')
        @include('unit-tester-documenter::partials.metadata')
        @include('unit-tester-documenter::partials.summary')
        @include('unit-tester-documenter::partials.suites-table')
        @include('unit-tester-documenter::partials.cases')
        @include('unit-tester-documenter::partials.screenshots')
        @include('unit-tester-documenter::partials.signoff')
    </div>
</body>
</html>
