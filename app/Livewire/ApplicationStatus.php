<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class ApplicationStatus extends Component
{
    public function render(): View
    {
        return view('livewire.application-status');
    }
}
