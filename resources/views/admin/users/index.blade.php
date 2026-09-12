@extends('layouts.admin')

@section('title', 'Restaurant Staff')
@section('page-title', 'Restaurant Staff')
@section('page-subtitle', 'User management')

@section('content')
    @php
        $actor = auth()->user();
        $canCreate = $actor->hasPermission('users.create');
        $canUpdate = $actor->hasPermission('users.update');
        $canDelete = $actor->hasPermission('users.delete');
        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    @endphp

    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Users</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#1f2f3e]">Restaurant Staff</h2>
                <p class="text-sm text-[#898989]">Manage staff accounts, roles, and active status. Users are deactivated instead of permanently deleted.</p>
            </div>
            @if ($canCreate)
                <a
                    href="{{ route('admin.users.create') }}"
                    class="inline-flex items-center justify-center rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]"
                >
                    Add New Staff
                </a>
            @endif
        </div>

        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search name, username, phone, or email"
                    class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a] xl:col-span-2"
                >
                <select name="role" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->slug }}" @selected(($filters['role'] ?? '') === $role->slug)>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                <select name="is_active" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                        Filter
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[880px] w-full text-left text-sm">
                <thead class="bg-[#16324f] text-white">
                    <tr>
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-4 py-3 font-semibold">Name</th>
                        <th class="px-4 py-3 font-semibold">Username</th>
                        <th class="px-4 py-3 font-semibold">Phone</th>
                        <th class="px-4 py-3 font-semibold">Email</th>
                        <th class="px-4 py-3 font-semibold">Role</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $index => $staff)
                        @php
                            $isSelf = $actor->is($staff);
                        @endphp
                        <tr class="border-b border-[#eef1f5] last:border-0">
                            <td class="px-4 py-3 text-[#898989]">{{ $users->firstItem() + $index }}</td>
                            <td class="px-4 py-3 font-semibold text-[#1f2f3e]">{{ $staff->name }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $staff->username }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $staff->mobile ?: '—' }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $staff->email ?: '—' }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $staff->role?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-700' => $staff->is_active,
                                    'bg-slate-100 text-slate-600' => ! $staff->is_active,
                                ])>
                                    {{ $staff->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canUpdate)
                                        <a
                                            href="{{ route('admin.users.edit', $staff) }}"
                                            class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a] hover:bg-[#04346a]/5"
                                        >
                                            Edit
                                        </a>
                                        @unless ($isSelf)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.users.status', $staff) }}"
                                                onsubmit="return confirm('Update status for this staff member?')"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="is_active" value="{{ $staff->is_active ? 0 : 1 }}">
                                                <button type="submit" class="rounded-md border border-[#dfdfdf] px-2.5 py-1 text-xs font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                                                    {{ $staff->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endunless
                                    @endif
                                    @if ($canDelete && ! $isSelf)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.users.destroy', $staff) }}"
                                            onsubmit="return confirm('Users cannot be permanently deleted. Continue to see the confirmation message?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                    @if (! $canUpdate && ! $canDelete)
                                        <span class="text-xs text-[#898989]">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center">
                                @if ($hasFilters)
                                    <p class="font-semibold text-[#1f2f3e]">No staff match your search or filters.</p>
                                @else
                                    <p class="font-semibold text-[#1f2f3e]">No restaurant staff found.</p>
                                    @if ($canCreate)
                                        <a href="{{ route('admin.users.create') }}" class="mt-3 inline-flex rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                                            Add New Staff
                                        </a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-[#eef1f5] px-5 py-4">
                {{ $users->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection
