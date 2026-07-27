{{--
    Exact match to your layouts/app.blade.php sidebar structure (the
    tooltip attrs, nav-label span, and route()-based active check all
    mirror your other nav-link items).

    Insert this <a> right after the "Appointment & onboarding" link,
    inside the <div class="nav flex-column py-2"> block.
--}}
<a href="{{ route('salary-grades.index') }}" class="nav-link {{ request()->routeIs('salary-grades.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Salary Grade">
    <i class="bi bi-cash-coin"></i> <span class="nav-label">Salary Grade</span>
</a>
