<?php

namespace App\Livewire\Procurement;

use Livewire\Component;
use App\Livewire\Forms\TestFeatureForm;
use App\Services\TestFeatureService;

class TestFeaturePortal extends Component
{
    public TestFeatureForm $form;

    public function save(TestFeatureService $service)
    {
        $this->form->validate();
        $service->execute($this->form->all());
    }

    public function render()
    {
        return view('livewire.procurement.test-feature.test-feature-portal');
    }
}
