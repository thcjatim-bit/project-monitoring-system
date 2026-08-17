<?php

return [
    'disk' => env('PHOTO_SYNC_DISK', 'local'),
    'rclone_binary' => env('RCLONE_BINARY', 'rclone'),
    'remote_root' => env('PHOTO_SYNC_REMOTE_ROOT', 'gdrive-public:Foto Pekerjaan'),
];
