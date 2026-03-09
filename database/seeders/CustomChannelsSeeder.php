<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Group;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomChannelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@admin.com',
                'password' => bcrypt('admin'),
            ]);
        }

        // Create the provided main playlist
        $mainPlaylist = Playlist::updateOrCreate(
            ['url' => 'https://m3u.ch/pl/b425af0fcb90211c765f6f3599512544_939ad57dec37203be6f003af5a038fb6.m3u'],
            [
                'name' => 'Main Global Playlist',
                'user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'source_type' => \App\Enums\PlaylistSourceType::M3u,
                'enabled' => true,
            ]
        );

        // Create a default playlist (local) if none exists
        $playlist = Playlist::where('url', 'local')->first();
        if (!$playlist) {
            $playlist = Playlist::create([
                'name' => 'Default Playlist',
                'user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'source_type' => \App\Enums\PlaylistSourceType::Local,
                'url' => 'local',
            ]);
        }

        // Create a group
        $group = Group::firstOrCreate(['name' => 'Premium Channels', 'user_id' => $user->id]);

        $channels = [
            [
                'name' => 'Star Sports',
                'url' => 'https://example.com/star-sports.m3u8',
                'group_id' => $group->id,
            ],
            [
                'name' => 'Jalsha Movies',
                'url' => 'https://example.com/jalsha-movies.m3u8',
                'group_id' => $group->id,
            ],
        ];

        foreach ($channels as $channelData) {
            Channel::updateOrCreate(
                ['name' => $channelData['name']],
                array_merge($channelData, [
                    'user_id' => $user->id,
                    'playlist_id' => $playlist->id,
                    'enabled' => true,
                    'is_custom' => true,
                ])
            );
        }
    }
}
