@php
    $level = (int) ($level ?? 0);
    $count = match ($level) {
        1 => 1,
        2 => 2,
        default => 0,
    };
    $label = match ($count) {
        1 => 'Spicy',
        2 => 'Very spicy',
        default => 'Not spicy',
    };
@endphp
<span class="inline-flex items-center gap-0.5 align-middle" role="img" aria-label="{{ $label }}">
    @for ($icon = 0; $icon < $count; $icon++)
        <svg viewBox="0 0 16 16" class="h-2 w-2 text-rose-500" fill="currentColor" aria-hidden="true">
            <path d="M9.2 1.2c.3.9-.2 1.6-.9 1.9.2.9 0 1.8-.5 2.5 1.6.3 3 1.3 3.8 2.9 1 2.2-.1 4.8-2.4 5.9-1.8.8-3.8.4-5.2-.9-1.2-1.1-1.6-2.7-1.2-4.2.4-1.3 1.3-2.4 2.5-3.1-.4-.9-.5-1.9-.1-2.8-.6-.5-.9-1.4-.5-2.1.6.7.8 1.2 1.1 1.2.4 0 .9-.5 1.1-1.2.3.5.8.9 1.5.1.2-.3.5-.5 1.1-.1z"/>
        </svg>
    @endfor
</span>
