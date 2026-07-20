<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use Livewire\Attributes\Rule;

class CategoryRequestForm extends Form
{
    #[Rule('required|string|min:3|max:100')]
    public string $name = '';

    #[Rule('required|string|size:8')] // Strict COA UACS 8-digit requirement
    public string $uacs_code = '';

    #[Rule('required|in:MOOE,CAPITAL_OUTLAY')]
    public string $budget_class = '';

    #[Rule('required|in:CONSUMABLE,UTILITY,CONTRACT,SERVICE,ICS,PAR')]
    public string $tracking_type = '';

    #[Rule('required|string|min:3|max:150')]
    public string $audit_requirement = '';

    public ?string $rejection_reason = null;

    public function loadFromRequest($request)
    {
        $this->name = $request->requested_name;
    }
}
