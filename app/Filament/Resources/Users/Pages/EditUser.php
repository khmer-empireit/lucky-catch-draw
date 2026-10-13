<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $roleToAssign = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (User $record) => auth()->user()->isNot($record)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roleToAssign = $data['role'] ?? null;
        unset($data['role']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        if ($this->roleToAssign) {
            $user->syncRoles([$this->roleToAssign]);
        }
    }
}
