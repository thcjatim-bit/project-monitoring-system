<?php

use App\Livewire\ApplicationStatus;
use Illuminate\Support\Facades\Route;

Route::get('/', ApplicationStatus::class);
