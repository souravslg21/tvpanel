<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PlaylistAuth;
use App\Models\Playlist;
use App\Models\CustomPlaylist;
use App\Models\MergedPlaylist;

$username = '1122';
$auth = PlaylistAuth::where('username', $username)->first();

if (!$auth) {
    echo "Auth '{$username}' not found\n";
    exit;
}

echo "Auth found: ID={$auth->id}, Username={$auth->username}\n";
$model = $auth->getAssignedModel();

if (!$model) {
    echo "No assigned model found for this auth!\n";
    exit;
}

echo "Assigned model: Type=" . get_class($model) . ", ID=" . $model->id . ", Name=" . $model->name . "\n";

if ($model instanceof Playlist) {
    echo "Playlist status: enabled=" . ($model->enabled ? 'YES' : 'NO') . "\n";
    echo "Active channels count: " . $model->active_channels_count . "\n";
    echo "Total channels count: " . $model->channels()->count() . "\n";
} elseif ($model instanceof MergedPlaylist) {
    echo "MergedPlaylist status: enabled=" . ($model->enabled ? 'YES' : 'NO') . "\n";
    echo "Channels count: " . $model->channels()->count() . "\n";
} elseif ($model instanceof CustomPlaylist) {
    echo "CustomPlaylist status: enabled=" . ($model->enabled ? 'YES' : 'NO') . "\n";
    echo "Channels count: " . $model->channels()->count() . "\n";
}
