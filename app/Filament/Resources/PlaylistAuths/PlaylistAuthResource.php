<?php

namespace App\Filament\Resources\PlaylistAuths;

use App\Facades\PlaylistFacade;
use App\Filament\Resources\PlaylistAuths\Pages\ListPlaylistAuths;
use App\Models\CustomPlaylist;
use App\Models\MergedPlaylist;
use App\Models\Playlist;
use App\Models\PlaylistAuth;
use App\Traits\HasUserFiltering;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
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
                Section::make('General Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->helperText('Used to reference these credentials internally.'),
                        Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true),
                    ])->columns(2),

                Section::make('Credentials')
                    ->description('Xtream API / M3U authentication details')
                    ->schema([
                        TextInput::make('username')
                            ->label('Username')
                            ->required()
                            ->autocomplete('off'),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required()
                            ->revealable()
                            ->autocomplete('off'),
                    ])->columns(2),

                Section::make('Settings & Limits')
                    ->schema([
                        TextInput::make('max_connections')
                            ->label('Max Connections')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Uses playlist default')
                            ->helperText('Maximum concurrent streams allowed for this user. 0 for unlimited (uses playlist default).'),
                        DateTimePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->nullable()
                            ->helperText('Leave empty for no expiration.'),
                    ])->columns(2),

                Section::make('Playlist Assignment')
                    ->description('Assign these credentials to a specific playlist.')
                    ->schema([
                        Select::make('assigned_playlist')
                            ->label('Assigned Playlist')
                            ->options(function () {
                                $userId = Auth::id();
                                $playlists = Playlist::where('user_id', $userId)->get(['id', 'name', 'uuid'])->pluck('name', 'uuid')->toArray();
                                $mergedPlaylists = MergedPlaylist::where('user_id', $userId)->get(['id', 'name', 'uuid'])->pluck('name', 'uuid')->toArray();
                                $customPlaylists = CustomPlaylist::where('user_id', $userId)->get(['id', 'name', 'uuid'])->pluck('name', 'uuid')->toArray();

                                $options = [];
                                foreach ($playlists as $uuid => $name) {
                                    $options["Playlist:{$uuid}"] = "Playlist: {$name}";
                                }
                                foreach ($mergedPlaylists as $uuid => $name) {
                                    $options["Merged:{$uuid}"] = "Merged: {$name}";
                                }
                                foreach ($customPlaylists as $uuid => $name) {
                                    $options["Custom:{$uuid}"] = "Custom: {$name}";
                                }

                                return $options;
                            })
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Select $component, ?PlaylistAuth $record) {
                                if (! $record) {
                                    return;
                                }
                                try {
                                    $model = $record->getAssignedModel();
                                    if ($model) {
                                        $type = match (get_class($model)) {
                                            Playlist::class => 'Playlist',
                                            MergedPlaylist::class => 'Merged',
                                            CustomPlaylist::class => 'Custom',
                                            default => 'Playlist',
                                        };
                                        $uuid = $model->uuid;
                                        $component->state("{$type}:{$uuid}");
                                    }
                                } catch (\Exception $e) {
                                }
                            })
                            ->saveRelationshipsUsing(function (PlaylistAuth $record, $state) {
                                if (empty($state)) {
                                    $record->clearAssignment();

                                    return;
                                }

                                [$type, $uuid] = explode(':', $state);
                                $modelClass = match ($type) {
                                    'Playlist' => Playlist::class,
                                    'Merged' => MergedPlaylist::class,
                                    'Custom' => CustomPlaylist::class,
                                };

                                $model = $modelClass::where('uuid', $uuid)->first();
                                if ($model) {
                                    $record->assignTo($model);
                                }
                            }),
                    ]),

                Section::make('Client Setup Details')
                    ->visible(fn (?PlaylistAuth $record) => $record && $record->exists)
                    ->schema([
                        TextInput::make('m3u_url')
                            ->label('M3U URL')
                            ->helperText('Copy this URL to your IPTV player (OTT Navigator, TiviMate, etc.)')
                            ->readOnly()
                            ->formatStateUsing(function (?PlaylistAuth $record) {
                                if (! $record) {
                                    return '';
                                }
                                try {
                                    $model = $record->getAssignedModel();
                                    if (! $model) {
                                        return 'Assign a playlist to generate M3U link';
                                    }

                                    if (! in_array(get_class($model), [Playlist::class, MergedPlaylist::class, CustomPlaylist::class])) {
                                        return 'Invalid assigned model type';
                                    }

                                    // Use the facade to get URLs.
                                    return PlaylistFacade::getUrls($model)['m3u'];
                                } catch (\Exception $e) {
                                    return 'Error generating URL';
                                }
                            }),
                        TextInput::make('xtream_url')
                            ->label('Xtream API Host')
                            ->readOnly()
                            ->formatStateUsing(fn () => rtrim(config('app.url') ?? '', '/'))
                            ->helperText('Use this as the Server URL in Xtream API players.'),
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
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Enabled' : 'Disabled')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
