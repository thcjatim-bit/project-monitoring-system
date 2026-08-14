<?php

namespace App\Contracts;

interface WahaClient
{
    public function sendText(string $to, string $text): void;

    public function sessionStatus(string $session): array;

    public function restart(string $session): void;
}
