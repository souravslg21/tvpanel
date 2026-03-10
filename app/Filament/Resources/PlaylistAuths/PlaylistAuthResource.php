<?php

namespace App\Filament\Resources\PlaylistAuths;

use App\Facades\PlaylistFacade;
use App\Models\CustomPlaylist;
use App\Models\MergedPlaylist;
use App\Models\Playlist;
use App\Models\PlaylistAuth;
use App\Filament\Resources\PlaylistAuths\Pages\ListPlaylistAuths;
use App\Filament\Resources\PlaylistAuths\Pages\CreatePlaylistAuth;
use App\Filament\Resources\PlaylistAuths\Pages\EditPlaylistAuth;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
                            ->required()
                            ->maxLength(255),
                        Toggle::make('enabled')
                            ->default(true),
                        TextInput::make('username')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->required()
                            ->maxLength(255),
                        // Removing max_connections and expires_at for now to rule out schema issues
                    ])->columns(2),

                Section::make('Assignment')
                    ->schema([
                        Select::make('assigned_model')
                            ->label('Assign to Playlist')
                            ->options(function () {
                                try {
                                    $userId = Auth::id();
                                    if (!$userId) return [];

                                    $options = [];
                                    
                                    // Robust check for tables and columns
                                    try {
                                        $playlists = Playlist::where('user_id', $userId)->get(['name', 'uuid']);
                                        foreach ($playlists as $p) $options["Playlist:{$p->uuid}"] = "Playlist: {$p->name}";
                                    } catch (\Exception $e) {}

                                    try {
                                        $merged = MergedPlaylist::where('user_id', $userId)->get(['name', 'uuid']);
                                        foreach ($merged as $m) $options["Merged:{$m->uuid}"] = "Merged: {$m->name}";
                                    } catch (\Exception $e) {}

                                    try {
                                        $custom = CustomPlaylist::where('user_id', $userId)->get(['name', 'uuid']);
                                        foreach ($custom as $c) $options["Custom:{$c->uuid}"] = "Custom: {$c->name}";
                                    } catch (\Exception $e) {}
                                    
                                    return $options;
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function (Select $component, ?PlaylistAuth $record) {
                                if (!$record) return;
                                try {
                                    $model = $record->getAssignedModel();
                                    if (!$model) return;
                                    
                                    $type = match (get_class($model)) {
                                        Playlist::class => 'Playlist',
                                        MergedPlaylist::class => 'Merged',
                                        CustomPlaylist::class => 'Custom',
                                        default => null,
                                    };
                                    
                                    if ($type) {
                                        $component->state("{$type}:{$model->uuid}");
                                    }
                                } catch (\Exception $e) {}
                            })
                            ->afterStateUpdated(function ($state, ?PlaylistAuth $record) {
                                if (!$state || !$record) return;
                                try {
                                    if (strpos($state, ':') === false) return;
                                    [$type, $uuid] = explode(':', $state);
                                    $modelClass = match ($type) {
                                        'Playlist' => Playlist::class,
                                        'Merged' => MergedPlaylist::class,
                                        'Custom' => CustomPlaylist::class,
                                        default => null,
                                    };
                                    if ($modelClass) {
                                        $model = $modelClass::where('uuid', $uuid)->first();
                                        if ($model) {
                                            $record->assignTo($model);
                                        }
                                    }
                                } catch (\Exception $e) {}
                            }),
                    ]),

                Section::make('Client Setup Details')
                    ->visible(fn (?PlaylistAuth $record) => $record && $record->exists)
                    ->schema([
                        TextInput::make('m3u_url_display')
                            ->label('M3U URL')
                            ->readOnly()
                            ->getStateUsing(function (?PlaylistAuth $record) {
                                if (!$record) return '';
                                try {
                                    $model = $record->getAssignedModel();
                                    if ($model) {
                                        return PlaylistFacade::getUrls($model)['m3u'] ?? 'No link';
                                    }
                                    return 'Not assigned';
                                } catch (\Exception $e) {
                                    return 'Error';
                                }
                            }),
                        TextInput::make('xtream_url_display')
                            ->label('Xtream API Host')
                            ->readOnly()
                            ->default(fn () => rtrim(config('app.url', ''), '/')),
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
