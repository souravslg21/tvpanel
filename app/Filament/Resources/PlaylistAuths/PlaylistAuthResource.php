<?php

namespace App\Filament\Resources\PlaylistAuths;

use App\Filament\Resources\PlaylistAuths\Pages\ListPlaylistAuths;
use App\Models\PlaylistAuth;
use App\Traits\HasUserFiltering;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PlaylistAuthResource extends Resource
{
    use HasUserFiltering;

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
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->helperText('Used to reference this auth internally.'),
                Toggle::make('enabled')
                    ->label('Enabled')
                    ->default(true),
                TextInput::make('username')
                    ->label('Username')
                    ->required(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required()
                    ->revealable(),
                DateTimePicker::make('expires_at')
                    ->label('Expiration')
                    ->nullable(),
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
                ToggleColumn::make('enabled')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ])->button()->hiddenLabel()->size('sm'),
            ], position: RecordActionsPosition::BeforeCells)
            ->toolbarActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaylistAuths::route('/'),
        ];
    }
}
