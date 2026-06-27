<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users (admin-only user management per ТЗ role table).
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return response()->json(
            $query->orderBy('name')->get()->map(fn (User $user) => $this->formatUser($user))
        );
    }

    /**
     * Change a user's role.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        return response()->json($this->formatUser($user->fresh()));
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            abort(422, 'Нельзя удалить собственную учётную запись.');
        }

        $user->delete();

        return response()->json(null, 204);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->created_at,
        ];
    }
}
