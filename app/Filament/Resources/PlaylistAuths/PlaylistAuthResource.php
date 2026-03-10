<?php

namespace App\Filament\Resources\PlaylistAuths;

use App\Filament\Resources\PlaylistAuthResource\Pages;
use App\Filament\Resources\PlaylistAuths\Pages\ListPlaylistAuths;
use App\Models\CustomPlaylist;
use App\Models\MergedPlaylist;
use App\Models\Playlist;
use App\Models\PlaylistAuth;
use App\Traits\HasUserFiltering;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PlaylistAuthResource extends Resource
{
    use HasUserFiltering;

    protected static ?string $model = PlaylistAuth::class;

    protected static ?string $recordTitleAttribute = 'name';

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
            ->components(self::getForm());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_connections')
                    ->label('Connections')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('assigned_model_name')
                    ->label('Assigned To')
                    ->toggleable(),
                Tables\Columns\ToggleColumn::make('enabled')
                    ->toggleable()
                    ->tooltip('Toggle auth status')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Tables\Actions\EditAction::make()
                    ->button()->hiddenLabel()->size('sm'),
                Tables\Actions\DeleteAction::make()
                    ->button()->hiddenLabel()->size('sm'),
            ], position: Tables\Enums\RecordActionsPosition::BeforeCells)
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

    public static function getForm(): array
    {
        $schema = [
            Forms\Components\TextInput::make('name')
                ->label('Name')
                ->required()
                ->helperText('Used to reference this auth internally.')
                ->columnSpan(1),
            Forms\Components\Toggle::make('enabled')
                ->label('Enabled')
                ->columnSpan(1)
                ->inline(false)
                ->default(true),
            Forms\Components\TextInput::make('username')
                ->label('Username')
                ->required()
                ->columnSpan(1),
            Forms\Components\TextInput::make('password')
                ->label('Password')
                ->password()
                ->required()
                ->revealable()
                ->columnSpan(1),
            Forms\Components\TextInput::make('max_connections')
                ->label('Max Connections')
                ->numeric()
                ->default(1)
                ->required()
                ->columnSpan(1),
            Forms\Components\DateTimePicker::make('expires_at')
                ->label('Expiration (date & time)')
                ->seconds(false)
                ->native(false)
                ->helperText('If set, this account will stop working at that exact time.')
                ->nullable()
                ->columnSpan(2),
        ];

        return [
            Grid::make()
                ->hiddenOn(['edit'])
                ->schema($schema)
                ->columns(2),
            Grid::make()
                ->hiddenOn(['create'])
                ->schema([
                    ...$schema,
                    Forms\Components\Select::make('assigned_playlist')
                        ->label('Assigned to Playlist')
                        ->options(function ($record) {
                            $options = [];

                            if ($record && $record->isAssigned()) {
                                $assignedModel = $record->getAssignedModel();
                                if ($assignedModel) {
                                    $type = match (get_class($assignedModel)) {
                                        Playlist::class => 'Playlist',
                                        CustomPlaylist::class => 'Custom Playlist',
                                        MergedPlaylist::class => 'Merged Playlist',
                                        default => 'Unknown'
                                    };
                                    $key = get_class($assignedModel).'|'.$assignedModel->id;
                                    $options[$key] = $assignedModel->name." ({$type}) - Currently Assigned";
                                }
                            }

                            $userId = auth()->id();

                            $playlists = Playlist::where('user_id', $userId)->get();
                            foreach ($playlists as $playlist) {
                                $key = Playlist::class.'|'.$playlist->id;
                                if (! isset($options[$key])) {
                                    $options[$key] = $playlist->name.' (Playlist)';
                                }
                            }

                            $customPlaylists = CustomPlaylist::where('user_id', $userId)->get();
                            foreach ($customPlaylists as $playlist) {
                                $key = CustomPlaylist::class.'|'.$playlist->id;
                                if (! isset($options[$key])) {
                                    $options[$key] = $playlist->name.' (Custom Playlist)';
                                }
                            }

                            $mergedPlaylists = MergedPlaylist::where('user_id', $userId)->get();
                            foreach ($mergedPlaylists as $playlist) {
                                $key = MergedPlaylist::class.'|'.$playlist->id;
                                if (! isset($options[$key])) {
                                    $options[$key] = $playlist->name.' (Merged Playlist)';
                                }
                            }

                            return $options;
                        })
                        ->searchable()
                        ->nullable()
                        ->placeholder('Select a playlist or leave empty')
                        ->helperText('Assign this auth to a specific playlist.')
                        ->default(function ($record) {
                            if ($record && $record->isAssigned()) {
                                $assignedModel = $record->getAssignedModel();
                                if ($assignedModel) {
                                    return get_class($assignedModel).'|'.$assignedModel->id;
                                }
                            }

                            return null;
                        })
                        ->afterStateHydrated(function ($component, $state, $record) {
                            if ($record && $record->isAssigned()) {
                                $assignedModel = $record->getAssignedModel();
                                if ($assignedModel) {
                                    $value = get_class($assignedModel).'|'.$assignedModel->id;
                                    $component->state($value);
                                }
                            }
                        })
                        ->afterStateUpdated(function ($state, $record) {
                            if (! $record) {
                                return;
                            }

                            if ($state) {
                                [$modelClass, $modelId] = explode('|', $state, 2);
                                $model = $modelClass::find($modelId);

                                if ($model) {
                                    $record->assignTo($model);
                                }
                            } else {
                                $record->clearAssignment();
                            }
                        })
                        ->dehydrated(false)
                        ->columnSpan(2),
                ])
                ->columns(2),
            Section::make('Access Details')
                ->description('Share these details with your customer.')
                ->schema([
                    Forms\Components\TextInput::make('m3u_url')
                        ->label('M3U Playlist URL')
                        ->readonly()
                        ->default(function ($record) {
                            if (! $record || ! $record->isAssigned()) {
                                return 'Assign to a playlist to see URL';
                            }
                            
                            $model = $record->getAssignedModel();
                            if (! $model) {
                                return 'Assign to a playlist to see URL';
                            }

                            return route('playlist.generate', [
                                'uuid' => $model->uuid,
                                'username' => $record->username,
                                'password' => $record->password,
                            ]);
                        })
                        ->suffixAction(function ($state) {
                            return Forms\Components\Actions\Action::make('copy_m3u')
                                ->icon('heroicon-m-clipboard')
                                ->action(fn () => null)
                                ->extraAttributes([
                                    'onclick' => 'window.navigator.clipboard.writeText("'.($state ? addslashes($state) : '').'"); window.$wire.dispatch("notify", { status: "success", title: "Copied to clipboard" });',
                                ]);
                        })
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('xtream_url')
                        ->label('Xtream API Server URL')
                        ->readonly()
                        ->default(fn () => url('/'))
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('xtream_username')
                        ->label('Xtream Username')
                        ->readonly()
                        ->default(fn ($record) => $record?->username)
                        ->columnSpan(1),
                    Forms\Components\TextInput::make('xtream_password')
                        ->label('Xtream Password')
                        ->readonly()
                        ->default(fn ($record) => $record?->password)
                        ->columnSpan(1),
                ])
                ->columns(2)
                ->hiddenOn(['create']),
        ];
    }
}
