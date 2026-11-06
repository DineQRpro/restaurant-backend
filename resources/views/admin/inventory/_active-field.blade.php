<div class="md:col-span-2">
    <label class="inline-flex items-center gap-2 text-sm font-semibold text-[#1f2f3e]">
        <input type="hidden" name="is_active" value="0">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            class="rounded border-[#dfdfdf] text-[#04346a]"
            @checked(filter_var(old('is_active', $active ?? true), FILTER_VALIDATE_BOOLEAN))
        >
        Active
    </label>
</div>
