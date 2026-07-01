@extends('layouts.app')

@section('content')
<div class="max-w-xl mx-auto py-16 text-center">
    <h1 class="text-xl font-semibold mb-4">Processing your PDF…</h1>
    <p class="text-gray-500 mb-8">This usually takes under a minute. You can leave this page open --
        it'll redirect automatically once it's done.</p>

    <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden mb-6">
        <div class="h-2 bg-blue-500 animate-pulse" style="width: 100%"></div>
    </div>

    <p id="status-text" class="text-sm text-gray-400">Status: processing…</p>
</div>

<script>
(function poll() {
    fetch('{{ route("job-postings.import.status", $batch->id) }}')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ready') {
                window.location.href = '{{ route("job-postings.import.review", $batch->id) }}';
            } else if (data.status === 'failed') {
                document.getElementById('status-text').innerHTML =
                    '<span class="text-red-500">' + (data.error_message || 'Something went wrong.') + '</span>';
            } else {
                setTimeout(poll, 2000);
            }
        })
        .catch(() => setTimeout(poll, 3000));
})();
</script>
@endsection