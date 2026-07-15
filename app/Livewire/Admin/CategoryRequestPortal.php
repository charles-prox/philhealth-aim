<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ProcurementCategoryRequest;
use App\Livewire\Forms\CategoryRequestForm;
use App\Services\CategoryRequestService;

class CategoryRequestPortal extends Component
{
    use WithPagination;

    public CategoryRequestForm $form;
    
    public ?int $selectedRequestId = null;
    public bool $showApproveModal = false;
    public bool $showRejectModal = false;

    public function getSelectedRequestProperty()
    {
        return $this->selectedRequestId 
            ? ProcurementCategoryRequest::with('user.office')->find($this->selectedRequestId) 
            : null;
    }

    public function selectRequest(int $id, string $action)
    {
        $this->selectedRequestId = $id;
        $request = $this->selectedRequest;

        if ($action === 'approve') {
            $this->form->reset();
            $this->form->loadFromRequest($request);
            $this->showApproveModal = true;
        } else {
            $this->form->rejection_reason = '';
            $this->showRejectModal = true;
        }
    }

    public function processApproval(CategoryRequestService $service)
    {
        $validated = $this->form->validate();
        $request = $this->selectedRequest;

        $service->approve($request, $validated);

        $this->reset(['showApproveModal', 'selectedRequestId']);
        $this->dispatch('toast', [
            'type' => 'success', 
            'message' => 'New category created & user notified!', 
            'title' => 'Approved'
        ]);
    }

    public function processRejection(CategoryRequestService $service)
    {
        $this->validate([
            'form.rejection_reason' => 'required|string|min:5|max:200'
        ]);

        $service->reject($this->selectedRequest, $this->form->rejection_reason);

        $this->reset(['showRejectModal', 'selectedRequestId']);
        $this->dispatch('toast', [
            'type' => 'warning', 
            'message' => 'Category request rejected.', 
            'title' => 'Rejected'
        ]);
    }

    public function render()
    {
        return view('livewire.admin.category-request.category-request-portal', [
            'requests' => ProcurementCategoryRequest::with('user.office')
                ->latest()
                ->paginate(10)
        ])->layout('layouts.app');
    }
}
