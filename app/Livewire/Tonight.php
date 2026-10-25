<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Heute Abend')]
class Tonight extends Component
{
    public function render(): View
    {
        return view('livewire.tonight');
    }
}
