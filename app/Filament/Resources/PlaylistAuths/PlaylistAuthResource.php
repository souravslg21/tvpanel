<?php

namespace App\Filament\Resources\PlaylistAuths;

use App\Facades\PlaylistFacade;
use App\Models\CustomPlaylist;
use App\Models\MergedPlaylist;
use App\Models\Playlist;
use App\Models\PlaylistAuth;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
            ->where('user_id', auth()->id());
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
                Section::make('General Information')
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
                        TextInput::make('max_connections')
                            ->numeric()
                            ->default(1),
                        DateTimePicker::make('expires_at'),
                    ])->columns(2),

                Section::make('Assignment')
                    ->schema([
                        Select::make('assigned_model')
                            ->label('Assign to Playlist')
                            ->options(function () {
                                try {
                                    $playlists = Playlist::query()->where('user_id', auth()->id())->get()->pluck('name', 'uuid');
                                    $merged = MergedPlaylist::query()->where('user_id', auth()->id())->get()->pluck('name', 'uuid');
                                    $custom = CustomPlaylist::query()->where('user_id', auth()->id())->get()->pluck('name', 'uuid');
                                    
                                    $options = [];
                                    foreach ($playlists as $uuid => $name) $options["Playlist:{$uuid}"] = "Playlist: {$name}";
                                    foreach ($merged as $uuid => $name) $options["Merged:{$uuid}"] = "Merged: {$name}";
                                    foreach ($custom as $uuid => $name) $options["Custom:{$uuid}"] = "Custom: {$name}";
                                    
                                    return $options;
                                } catch (\Exception $e) {
                                    return [];
                                }
                            })
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function (Select $component, ?PlaylistAuth $record) {
                                if (!$record) return;
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
                            })
                            ->afterStateUpdated(function ($state, ?PlaylistAuth $record) {
                                if (!$state || !$record) return;
                                try {
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
                        TextInput::make('m3u_url')
                            ->label('M3U URL')
                            ->readOnly()
                            ->getStateUsing(function (?PlaylistAuth $record) {
                                if (!$record) return '';
                                try {
                                    $model = $record->getAssignedModel();
                                    return $model ? PlaylistFacade::getUrls($model)['m3u'] : 'Not assigned';
                                } catch (\Exception $e) {
                                    return 'Error';
                                }
                            }),
                        TextInput::make('xtream_url')
                            ->label('Xtream API Host')
                            ->readOnly()
                            ->default(fn () => rtrim(config('app.url') ?? '', '/')),
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => \App\Filament\Resources\PlaylistAuths\Pages\ListPlaylistAuths::route('/'),
        ];
    }
}
