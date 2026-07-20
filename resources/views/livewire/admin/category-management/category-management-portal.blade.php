<div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-on-surface">COA Category Management</h1>
            <p class="text-xs text-on-surface-variant">Register and manage active procurement categories, budget classes, and UACS object codes.</p>
        </div>
        <button wire:click="openCreateModal" class="px-4 py-2 bg-primary text-on-primary hover:bg-primary-hover rounded-lg text-xs font-bold shadow-sm transition-colors flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Add New Category
        </button>
    </div>

    <!-- Search Controls -->
    <div class="flex gap-4">
        <input type="text" wire:model.live="search" placeholder="Search by category name or UACS code..." class="w-full max-w-sm text-xs p-2.5 bg-surface border border-outline-variant rounded-lg focus:outline-primary shadow-sm" />
    </div>

    <!-- Categories List Table -->
    <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
        <table class="w-full border-collapse text-left text-xs">
            <thead class="bg-surface-container-high border-b border-outline-variant font-bold text-on-surface-variant">
                <tr>
                    <th class="p-4">Category Name</th>
                    <th class="p-4">UACS Code</th>
                    <th class="p-4">Budget Classification</th>
                    <th class="p-4">Tracking Requirement</th>
                    <th class="p-4">Required Attachment</th>
                    <th class="p-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant font-semibold text-on-surface">
                @forelse($categories as $cat)
                    <tr class="hover:bg-surface-container-low transition-colors">
                        <td class="p-4 font-bold">{{ $cat->name }}</td>
                        <td class="p-4 font-mono text-outline">{{ $cat->uacs_code }}</td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $cat->budget_class === 'MOOE' ? 'bg-primary-container text-on-primary-container' : 'bg-warning-container text-on-warning-container' }}">
                                {{ $cat->budget_class === 'MOOE' ? 'MOOE' : 'Capital Outlay' }}
                            </span>
                        </td>
                        <td class="p-4">
                            <span class="px-2 py-0.5 rounded bg-surface-container-highest text-on-surface text-[10px] font-bold">
                                {{ $cat->tracking_type }}
                            </span>
                        </td>
                        <td class="p-4 text-on-surface-variant">{{ $cat->audit_requirement }}</td>
                        <td class="p-4 text-right space-x-1">
                            <button wire:click="openEditModal({{ $cat->id }})" class="px-2 py-1 bg-surface-container-highest text-on-surface hover:bg-surface-container-high rounded text-[11px]">Edit</button>
                            <button wire:click="openDeleteModal({{ $cat->id }})" class="px-2 py-1 bg-error/10 text-error hover:bg-error/20 rounded text-[11px]">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-on-surface-variant">No matching categories found in the registry.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 bg-surface-container-low border-t border-outline-variant">
            {{ $categories->links() }}
        </div>
    </div>

    <!-- Compliant Partials Modal Inclusion -->
    @include('livewire.admin.category-management.partials.category-modals')
</div>
