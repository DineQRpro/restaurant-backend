<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\IndexUserRequest;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserStatusRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function index(IndexUserRequest $request): View
    {
        return view('admin.users.index', [
            'users' => $this->userService->paginate($request->filters()),
            'filters' => $request->only(['search', 'role', 'is_active']),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($this->payload($request));

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Staff added successfully.');
    }

    public function edit(User $user): View
    {
        $user->load('role');

        return view('admin.users.edit', [
            'staff' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->userService->update($user, $this->payload($request), $request->user());
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Staff updated successfully.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        try {
            $this->userService->updateStatus($user, $request->boolean('is_active'), $request->user());
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Staff status updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        try {
            $this->userService->delete($user, $request->user());
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Staff deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreUserRequest|UpdateUserRequest $request): array
    {
        $data = $request->safe()->except('password_confirmation');

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }
}
