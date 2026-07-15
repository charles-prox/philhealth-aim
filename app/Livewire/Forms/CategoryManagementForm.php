<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use Livewire\Attributes\Rule;
use Illuminate\Validation\Rule as ValidationRule;

class CategoryManagementForm extends Form
{
    public ?int $categoryId = null;

    public string $name = '';
    public string $uacs_code = '';
    public string $budget_class = '';
    public string $tracking_type = '';
    public string $audit_requirement = '';

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:100',
                ValidationRule::unique('procurement_categories', 'name')->ignore($this->categoryId),
            ],
            'uacs_code' => 'required|string|size:8', // Strict COA UACS code format
            'budget_class' => 'required|in:MOOE,CAPITAL_OUTLAY',
            'tracking_type' => 'required|in:CONSUMABLE,UTILITY,CONTRACT,SERVICE,ICS,PAR',
            'audit_requirement' => 'required|string|min:3|max:150',
        ];
    }

    public function loadFromModel($category): void
    {
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->uacs_code = $category->uacs_code;
        $this->budget_class = $category->budget_class;
        $this->tracking_type = $category->tracking_type;
        $this->audit_requirement = $category->audit_requirement;
    }
}
