<?php

namespace App\Filament\Resources\PlaylistAuths;

use App\Models\PlaylistAuth;
use App\Filament\Resources\PlaylistAuths\Pages\ListPlaylistAuths;
use App\Filament\Resources\PlaylistAuths\Pages\CreatePlaylistAuth;
use App\Filament\Resources\PlaylistAuths\Pages\EditPlaylistAuth;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PlaylistAuthResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id());
    }

    protected static ?string $model = PlaylistAuth::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Playlist Auths';

    protected static ?string $label = 'Playlist Auth';

    protected static ?string $pluralLabel = 'Playlist Auths';

    protected static string|\UnitEnum|null $navigationGroup = 'Playlist';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'username'];
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Credentials')
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        Toggle::make('enabled')
                            ->default(true),
                        TextInput::make('username')
                            ->required(),
                        TextInput::make('password')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('enabled')
                    ->badge()
                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaylistAuths::route('/'),
            'create' => CreatePlaylistAuth::route('/create'),
            'edit' => EditPlaylistAuth::route('/{record}/edit'),
        ];
    }
}
