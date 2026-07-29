{{-- TODO: swap 'layouts.app' for whatever your actual admin layout is called
     (this file wasn't provided, so this is a guess) --}}
@extends('layouts.app')

@section('title', 'Records')
@section('page-title', 'Records')

@section('page-subtitle')
Pending Application Codes
@endsection

@section('content')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form action="{{ route('records.index') }}" method="GET" class="row g-2 mb-3 align-items-center" id="recordsFilterForm">
    <div class="col-auto">
        <input type="text" name="search" value="{{ $search ?? '' }}" id="recordsSearchInput"
               class="form-control" placeholder="Search applicant name..." style="min-width: 250px;">
    </div>
    <div class="col-auto">
        <select name="position" class="form-select" onchange="this.form.submit()">
            <option value="">All positions</option>
            @foreach ($positions as $title)
                <option value="{{ $title }}" @selected(($position ?? '') === $title)>
                    {{ $title }}
                </option>
            @endforeach
        </select>
    </div>
    @if (!empty($search) || !empty($position))
    <div class="col-auto">
        <a href="{{ route('records.index') }}" class="btn btn-danger">Clear</a>
    </div>
    @endif
</form>
<script>
    // Live search: fetch results in the background and swap just the two
    // tables' contents in place, instead of a full form submit. A full
    // submit was a real page navigation on every debounced keystroke,
    // which is what triggered the global page-loader overlay to flash on
    // every letter typed -- fetching avoids navigation entirely, so the
    // loader never fires.
    (function () {
        var input = document.getElementById('recordsSearchInput');
        var form = document.getElementById('recordsFilterForm');
        if (!input || !form) return;

        var pendingTbody  = document.querySelector('#pendingTable tbody');
        var assignedTbody = document.querySelector('#assignedTable tbody');
        var pendingPagination  = document.getElementById('pendingPagination');
        var assignedPagination = document.getElementById('assignedPagination');

        function applyFilter(url) {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');

                    var newPendingTbody = doc.querySelector('#pendingTable tbody');
                    var newAssignedTbody = doc.querySelector('#assignedTable tbody');
                    var newPendingPagination = doc.getElementById('pendingPagination');
                    var newAssignedPagination = doc.getElementById('assignedPagination');

                    if (pendingTbody && newPendingTbody) pendingTbody.innerHTML = newPendingTbody.innerHTML;
                    if (assignedTbody && newAssignedTbody) assignedTbody.innerHTML = newAssignedTbody.innerHTML;
                    if (pendingPagination && newPendingPagination) pendingPagination.innerHTML = newPendingPagination.innerHTML;
                    if (assignedPagination && newAssignedPagination) assignedPagination.innerHTML = newAssignedPagination.innerHTML;

                    // Keep the address bar / back button in sync without
                    // triggering a real navigation.
                    window.history.replaceState(null, '', url);
                })
                .catch(function () {
                    // Fetch failed for some reason (network hiccup, etc.) --
                    // fall back to a real submit so search still works.
                    form.submit();
                });
        }

        var debounceTimer = null;
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var params = new URLSearchParams(new FormData(form));
                applyFilter(form.action + '?' + params.toString());
            }, 350);
        });

        // Intercept pagination link clicks too, so paging through results
        // stays on the same no-reload behavior once a search is active.
        document.addEventListener('click', function (e) {
            var link = e.target.closest('#pendingPagination a, #assignedPagination a');
            if (!link) return;
            e.preventDefault();
            applyFilter(link.href);
        });
    })();
</script>

<table class="table table-bordered align-middle" id="pendingTable">
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th>Submitted</th>
            <th class="text-end">Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pending as $application)
        <tr>
            <td>{{ $application->candidate->full_name ?? '—' }}</td>
            <td>{{ $application->jobPosting->title ?? '—' }}</td>
            <td>{{ $application->applied_at?->format('M d, Y') }}</td>
            <td class="text-end">
                <form action="{{ route('records.assign-code', $application->id) }}" method="POST" class="d-flex gap-2 justify-content-end">
                    @csrf
                    <input type="text" name="transaction_number"
                           class="form-control form-control-sm" style="max-width: 200px;"
                           value="SDO-{{ now()->format('Y') }}-"
                           placeholder="Auto-generate (or type a code)">
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap"
                        onclick="return confirm('Confirm requirements have been checked. This will assign the Application Code and email it to the applicant.');">
                        Assign Code
                    </button>
                </form>
                @error('transaction_number')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="4" class="text-center text-muted">
                @if (!empty($search) || !empty($position))
                    No pending applications match the current filters.
                @else
                    No applications pending a code.
                @endif
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<div id="pendingPagination">
{{ $pending->links() }}
</div>

<hr class="my-4">

<h5 class="mb-3">Assigned Application Codes</h5>

<table class="table table-bordered align-middle" id="assignedTable">
    <thead>
        <tr>
            <th>Applicant</th>
            <th>Position</th>
            <th style="min-width: 220px;">Application Code</th>
            <th class="text-end">Date assigned</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($assigned as $application)
        <tr>
            <td>{{ $application->candidate->full_name ?? '—' }}</td>
            <td>{{ $application->jobPosting->title ?? '—' }}</td>
            <td>
                <span class="font-monospace">{{ $application->transaction_number }}</span>
            </td>
            <td class="text-end">{{ $application->updated_at?->format('M d, Y') ?? '—' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="4" class="text-center text-muted">
                @if (!empty($search) || !empty($position))
                    No assigned applications match the current filters.
                @else
                    No Application Codes assigned yet.
                @endif
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<div id="assignedPagination">
{{ $assigned->links() }}
</div>
@endsection