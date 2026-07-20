<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ProcurementForm extends Form
{
    public string $trackingNumber = '';

    public string $purpose = '';

    public ?int $recommendedById = null;

    public ?int $approvedById = null;

    public string $procurementCategory = '';

    public ?bool $isTiedToEvent = null;

    public ?string $eventDate = null;

    /**
     * Validate Step 2 metadata inputs specifically.
     */
    public function validateStepTwo(string $validRecommenderIds, string $validApproverIds, ?string $folderId = null): void
    {
        $this->validate([
            'trackingNumber' => 'required|string|max:50|unique:procurement_folders,tracking_number,' . ($folderId ?? 'NULL') . ',id',
            'purpose' => 'required|string|max:1000',
            'recommendedById' => 'required|integer|in:' . $validRecommenderIds,
            'approvedById' => 'required|integer|in:' . $validApproverIds,
            'procurementCategory' => 'required|string',
            'isTiedToEvent' => 'required|boolean',
            'eventDate' => 'required_if:isTiedToEvent,true|nullable|date|after:today',
        ], [
            'recommendedById.in' => 'The selected recommending officer is not an authorized signatory for this PR.',
            'approvedById.in' => 'The selected approving officer is not an authorized signatory for this PR.',
            'procurementCategory.required' => 'Operational Constraint: Please select a baseline procurement classification category.',
            'eventDate.required_if' => 'Logistics Requirement: Because this request is tied to an event, you must specify the scheduled date of the event.',
            'eventDate.after' => 'Timeline Violation: The targeted event date must be a future calendar date (after today).',
        ]);
    }

    /**
     * Validate the entire form at final compilation step.
     */
    public function validateFinal(string $validRecommenderIds, string $validApproverIds, ?string $folderId = null): void
    {
        $this->validateStepTwo($validRecommenderIds, $validApproverIds, $folderId);
    }
}
