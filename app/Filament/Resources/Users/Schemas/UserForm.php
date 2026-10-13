<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->minLength(8),
                Select::make('role')
                    ->label('Role')
                    ->options(collect(RoleName::cases())->mapWithKeys(fn ($role) => [$role->value => $role->label()]))
                    ->required()
                    ->native(false)
                    ->formatStateUsing(fn (?User $record) => $record?->roles->first()?->name),
                Toggle::make('active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Deactivated users cannot sign in to the admin panel.'),
            ]);
    }
}
