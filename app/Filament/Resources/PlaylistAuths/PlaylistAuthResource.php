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
                    ])->columns(2),

                Section::make('Playlist Assignment')
                    ->description('Choose which playlist this user should have access to.')
                    ->schema([
                        Select::make('assigned_model')
                            ->label('Assigned Playlist')
                            ->options(function () {
                                try {
                                    $userId = Auth::id();
                                    $options = [];
                                    
                                    // Fetch standard playlists
                                    Playlist::where('user_id', $userId)->get(['name', 'uuid'])->each(function ($item) use (&$options) {
                                        $options["Playlist:{$item->uuid}"] = "Playlist: {$item->name}";
                                    });

                                    // Fetch merged playlists
                                    MergedPlaylist::where('user_id', $userId)->get(['name', 'uuid'])->each(function ($item) use (&$options) {
                                        $options["Merged:{$item->uuid}"] = "Merged: {$item->name}";
                                    });

                                    // Fetch custom playlists
                                    CustomPlaylist::where('user_id', $userId)->get(['name', 'uuid'])->each(function ($item) use (&$options) {
                                        $options["Custom:{$item->uuid}"] = "Custom: {$item->name}";
                                    });
                                    
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
                                    
                                    if ($type && !empty($model->uuid)) {
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

                Section::make('Client Setup - M3U & Xtream')
                    ->description('Use these details in your IPTV player application.')
                    ->visible(fn (?PlaylistAuth $record) => $record && $record->exists)
                    ->schema([
                        TextInput::make('m3u_url_display')
                            ->label('M3U Playlist URL')
                            ->hint('Copy this to players like OTT Navigator')
                            ->readOnly()
                            ->getStateUsing(function (?PlaylistAuth $record) {
                                if (!$record) return 'Save record first';
                                try {
                                    $model = $record->getAssignedModel();
                                    if ($model) {
                                        $urls = PlaylistFacade::getUrls($model, $record->username, $record->password);
                                        return $urls['m3u'] ?? 'Error: Unable to generate link';
                                    }
                                    return 'Please select a playlist above and save.';
                                } catch (\Exception $e) {
                                    return 'Error: ' . $e->getMessage();
                                }
                            }),
                        TextInput::make('xtream_url_display')
                            ->label('Xtream API Host')
                            ->readOnly()
                            ->hint('Use this as the Server/Portal URL')
                            ->default(fn () => rtrim(config('app.url', ''), '/')),
                    ])->columns(1),
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
