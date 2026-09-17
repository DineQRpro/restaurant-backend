@props(['dashboardRoute', 'title'])

<div {{ $attributes->class('report-page') }}>
    <nav class="report-breadcrumb" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route($dashboardRoute) }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">{{ $title }}</li>
        </ol>
    </nav>

    <section class="report-card">
        <div class="report-toolbar">
            {{ $toolbar }}
        </div>
        <div class="report-body">
            {{ $slot }}
        </div>
    </section>
</div>
