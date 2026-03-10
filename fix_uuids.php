<?php
use App\Models\Playlist;
use App\Models\MergedPlaylist;
use App\Models\CustomPlaylist;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking UUIDs...\n";

Playlist::whereNull('uuid')->orWhere('uuid', '')->get()->each(function ($p) {
    $p->uuid = (string) Str::uuid();
    $p->save();
    echo "Set UUID for Playlist: {$p->name}\n";
});

MergedPlaylist::whereNull('uuid')->orWhere('uuid', '')->get()->each(function ($m) {
    $m->uuid = (string) Str::uuid();
    $m->save();
    echo "Set UUID for MergedPlaylist: {$m->name}\n";
});

CustomPlaylist::whereNull('uuid')->orWhere('uuid', '')->get()->each(function ($c) {
    $c->uuid = (string) Str::uuid();
    $c->save();
    echo "Set UUID for CustomPlaylist: {$c->name}\n";
});

echo "Done.\n";
