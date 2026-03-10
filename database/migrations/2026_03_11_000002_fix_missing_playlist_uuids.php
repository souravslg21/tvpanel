<?php

use App\Models\CustomPlaylist;
use App\Models\MergedPlaylist;
use App\Models\Playlist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up() {
        Playlist::whereNull('uuid')->orWhere('uuid', '')->get()->each(function ($p) {
            $p->update(['uuid' => (string) Str::uuid()]);
        });
        MergedPlaylist::whereNull('uuid')->orWhere('uuid', '')->get()->each(function ($m) {
            $m->update(['uuid' => (string) Str::uuid()]);
        });
        CustomPlaylist::whereNull('uuid')->orWhere('uuid', '')->get()->each(function ($c) {
            $c->update(['uuid' => (string) Str::uuid()]);
        });
    }
};
