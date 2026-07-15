<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use Livewire\Attributes\Rule;
use Carbon\Carbon;

class ProcurementFolderForm extends Form
{
    // ... your existing form properties (category_id, pr_number, purpose, etc.) ...
    public ?int $category_id = null;
    public ?string $pr_number = null;
    public ?string $purpose = null;

    public string $event_range = ''; // Binds to <x-date-range-picker>
    
    public ?string $event_start_date = null;
    public ?string $event_end_date = null;

    /**
     * Parse and validate the date range.
     */
    public function validateEventDates(): bool
    {
        if (empty($this->event_range)) {
            $this->event_start_date = null;
            $this->event_end_date = null;
            return true;
        }

        $dates = explode(' to ', $this->event_range);
        $startString = $dates[0] ?? null;
        $endString = $dates[1] ?? $startString;

        try {
            $start = Carbon::createFromFormat('Y-m-d', $startString)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', $endString)->startOfDay();
        } catch (\Exception $e) {
            $this->addError('event_range', 'Invalid date format. Please select from the calendar.');
            return false;
        }

        // 🛡️ Strict Audit Guard: Start date must be tomorrow or later (Today: July 15, 2026)
        if ($start->lessThanOrEqualTo(Carbon::today())) {
            $this->addError('event_range', 'The event start date must be a future date (tomorrow or later).');
            return false;
        }

        // 🛡️ Chronological Guard
        if ($end->lt($start)) {
            $this->addError('event_range', 'The event end date cannot be earlier than the start date.');
            return false;
        }

        $this->event_start_date = $start->toDateString();
        $this->event_end_date = $end->toDateString();

        return true;
    }
}
