<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $roleToAssign = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->roleToAssign = $data['role'] ?? null;
        unset($data['role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        if ($this->roleToAssign) {
            $user->syncRoles([$this->roleToAssign]);
        }
    }
}
