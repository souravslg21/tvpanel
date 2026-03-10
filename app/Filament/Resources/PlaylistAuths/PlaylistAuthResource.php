<?php // Trigger Redeploy

namespace App\Filament\Resources\PlaylistAuths;

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
                            ->required(),
                        Toggle::make('enabled')
                            ->default(true),
                        TextInput::make('username')
                            ->required(),
                        TextInput::make('password')
                            ->required(),
                    ])->columns(2),

                Section::make('Playlist Assignment')
                    ->schema([
                        Select::make('assigned_model')
                            ->label('Assigned Playlist')
                            ->options(function () {
                                $userId = Auth::id();
                                $options = [];
                                try {
                                    Playlist::where('user_id', $userId)->get(['name', 'uuid'])->each(function ($item) use (&$options) {
                                        $options["Playlist:{$item->uuid}"] = "Playlist: {$item->name}";
                                    });
                                    MergedPlaylist::where('user_id', $userId)->get(['name', 'uuid'])->each(function ($item) use (&$options) {
                                        $options["Merged:{$item->uuid}"] = "Merged: {$item->name}";
                                    });
                                    CustomPlaylist::where('user_id', $userId)->get(['name', 'uuid'])->each(function ($item) use (&$options) {
                                        $options["Custom:{$item->uuid}"] = "Custom: {$item->name}";
                                    });
                                } catch (\Exception $e) {}
                                return $options;
                            })
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function (Select $component, ?PlaylistAuth $record) {
                                if (!$record) return;
                                try {
                                    $model = $record->getAssignedModel();
                                    if ($model && !empty($model->uuid)) {
                                        $className = get_class($model);
                                        $type = null;
                                        if ($className === Playlist::class) $type = 'Playlist';
                                        elseif ($className === MergedPlaylist::class) $type = 'Merged';
                                        elseif ($className === CustomPlaylist::class) $type = 'Custom';
                                        
                                        if ($type) {
                                            $component->state("{$type}:{$model->uuid}");
                                        }
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

                Section::make('URLs')
                    ->visible(fn (?PlaylistAuth $record) => $record && $record->exists)
                    ->schema([
                        TextInput::make('m3u_display')
                            ->label('User M3U URL')
                            ->readOnly()
                            ->formatStateUsing(function (?PlaylistAuth $record) {
                                if (!$record || !$record->exists) return 'N/A';
                                try {
                                    $model = $record->getAssignedModel();
                                    if ($model && !empty($model->uuid)) {
                                        $baseUrl = request()->getSchemeAndHttpHost();
                                        if (str_contains($baseUrl, 'localhost')) {
                                            $baseUrl = rtrim(config('app.url') ?? $baseUrl, '/');
                                        }
                                        
                                        // Simplified M3U route: /playlist.m3u
                                        return "{$baseUrl}/playlist.m3u?username=" . urlencode($record->username) . "&password=" . urlencode($record->password);
                                    }
                                } catch (\Exception $e) {}
                                return 'Not assigned';
                            }),
                        TextInput::make('epg_display')
                            ->label('User EPG URL')
                            ->readOnly()
                            ->formatStateUsing(function (?PlaylistAuth $record) {
                                if (!$record || !$record->exists) return 'N/A';
                                try {
                                    $model = $record->getAssignedModel();
                                    if ($model && !empty($model->uuid)) {
                                        $baseUrl = request()->getSchemeAndHttpHost();
                                        if (str_contains($baseUrl, 'localhost')) {
                                            $baseUrl = rtrim(config('app.url') ?? $baseUrl, '/');
                                        }
                                        
                                        // Simplified EPG route: /epg.xml
                                        return "{$baseUrl}/epg.xml?username=" . urlencode($record->username) . "&password=" . urlencode($record->password);
                                    }
                                } catch (\Exception $e) {}
                                return 'Not assigned';
                            }),
                        TextInput::make('xtream_host')
                            ->label('Xtream API Host')
                            ->readOnly()
                            ->formatStateUsing(function () {
                                $baseUrl = request()->getSchemeAndHttpHost();
                                if (str_contains($baseUrl, 'localhost')) {
                                    $baseUrl = rtrim(config('app.url') ?? $baseUrl, '/');
                                }
                                return $baseUrl;
                            }),
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
