<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ProcurementCategory;
use App\Livewire\Forms\CategoryManagementForm;
use App\Services\CategoryManagementService;
use Exception;

class CategoryManagementPortal extends Component
{
    use WithPagination;

    public CategoryManagementForm $form;

    // Search and UI state
    public string $search = '';
    public ?int $selectedCategoryId = null;
    public bool $showManageModal = false;
    public bool $showDeleteModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getSelectedCategoryProperty()
    {
        return $this->selectedCategoryId ? ProcurementCategory::find($this->selectedCategoryId) : null;
    }

    public function openCreateModal(): void
    {
        $this->form->reset();
        $this->selectedCategoryId = null;
        $this->showManageModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->selectedCategoryId = $id;
        $category = $this->selectedCategory;

        $this->form->reset();
        $this->form->loadFromModel($category);
        $this->showManageModal = true;
    }

    public function openDeleteModal(int $id): void
    {
        $this->selectedCategoryId = $id;
        $this->showDeleteModal = true;
    }

    public function save(CategoryManagementService $service): void
    {
        $validated = $this->form->validate();

        if ($this->selectedCategoryId) {
            $service->update($this->selectedCategory, $validated);
            $message = 'Category updated successfully!';
        } else {
            $service->create($validated);
            $message = 'New category registered successfully!';
        }

        $this->reset(['showManageModal', 'selectedCategoryId']);
        $this->dispatch('toast', ['type' => 'success', 'message' => $message, 'title' => 'Success']);
    }

    public function destroy(CategoryManagementService $service): void
    {
        try {
            $service->delete($this->selectedCategory);
            $this->reset(['showDeleteModal', 'selectedCategoryId']);
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Category deleted.', 'title' => 'Deleted']);
        } catch (Exception $e) {
            $this->reset(['showDeleteModal', 'selectedCategoryId']);
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage(), 'title' => 'Action Blocked']);
        }
    }

    public function render()
    {
        $query = ProcurementCategory::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('uacs_code', 'like', '%' . $this->search . '%');
        }

        return view('livewire.admin.category-management.category-management-portal', [
            'categories' => $query->orderBy('name')->paginate(10)
        ]);
    }
}
