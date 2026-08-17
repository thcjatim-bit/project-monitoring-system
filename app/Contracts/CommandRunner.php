<?php

namespace App\Contracts;

interface CommandRunner
{
    /**
     * @param  array<int, string>  $arguments
     */
    public function run(array $arguments): string;
}
