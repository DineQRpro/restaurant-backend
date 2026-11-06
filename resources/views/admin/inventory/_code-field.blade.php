@php
    $code = $code ?? null;
@endphp
<div>
    <p class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Code</p>
    @if ($code)
        <p class="rounded-md border border-[#dfdfdf] bg-[#f7f8fa] px-3 py-2.5 text-sm font-semibold text-[#04346a]">{{ $code }}</p>
        <p class="mt-1 text-xs text-[#898989]">This code cannot be changed.</p>
    @else
        <p class="rounded-md border border-dashed border-[#dfdfdf] bg-[#f7f8fa] px-3 py-2.5 text-sm text-[#898989]">Auto-generated on save</p>
    @endif
</div>
