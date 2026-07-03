<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Membres';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->getStateUsing(fn (User $record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('role_label')
                    ->label('Rôle')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => match (true) {
                        $record->hasRole('company_admin') => 'Admin société',
                        default                           => 'Membre',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Admin société' => 'warning',
                        default         => 'gray',
                    }),
                TextColumn::make('level.code')
                    ->label('Niveau')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Membre depuis')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at');
    }
}
