<?php

namespace App\Modules\Gyms\Application;

use App\Models\Gym;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GymIndexQuery
{
    public function handle(?string $search, ?string $status): LengthAwarePaginator
    {
        return Gym::query()
            ->with('owner:id,name,email')
            ->withCount([
                'users',
                'modules as active_modules_count' => fn ($query) => $query->where('gym_modules.enabled', true),
            ])
            ->when($search, fn ($query, $value) => $query->where(function ($query) use ($value): void {
                $query->where('name', 'like', "%{$value}%")
                    ->orWhere('slug', 'like', "%{$value}%")
                    ->orWhereHas('owner', fn ($owner) => $owner->where('email', 'like', "%{$value}%"));
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();
    }
}
