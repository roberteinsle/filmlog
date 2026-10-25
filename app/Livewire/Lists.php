<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Listen')]
class Lists extends Component
{
    public function render(): View
    {
        return view('livewire.lists');
    }
}
