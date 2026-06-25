<?php

use App\Models\AppHeader;
use App\Models\AppLineItem;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $fiscalYear;
    public $csv_file;
    public $pdf_file;
    public bool $showCsvForm = false;
    public bool $isModal = false;

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(?int $fiscalYear = null): void
    {
        $this->fiscalYear = $fiscalYear ?? (\App\Models\BudgetYear::where('status', 'OPEN')->value('fiscal_year') ?? now()->year);
        
        $header = AppHeader::where('fiscal_year', $this->fiscalYear)->first();
        $lineItemsCount = $header ? $header->lineItems()->count() : 0;
        $this->showCsvForm = $lineItemsCount === 0;
    }

    // 1. PHASE 1: Ingest and Parse Data Structure (CSV Only)
    public function uploadCsv()
    {
        $this->validate([
            'fiscalYear' => 'required|integer|min:2020|max:2050',
            'csv_file'   => 'required|file|mimes:csv,txt|max:10240', // 10MB Limit
        ], [
            'csv_file.mimes' => 'The uploaded file must be a valid CSV layout.',
        ]);

        $actor = auth()->user()->employee;
        if (!$actor) {
            $this->errorMessage = "System error: Your account is not linked to an Employee record. Please contact the administrator.";
            return;
        }

        try {
            DB::transaction(function () use ($actor) {
                // Safety Reset Guard: Create or target parent record; explicitly ensure approval status flips to false
                // Wipe old entries and lock PR creation instantly.
                $header = AppHeader::updateOrCreate(
                    ['fiscal_year' => $this->fiscalYear],
                    [
                        'is_approved' => false,
                        'approved_at' => null,
                        'uploaded_by_id' => $actor->id,
                    ]
                );

                // Wipe previous unapproved/approved items for this year to prevent row stacking
                AppLineItem::where('app_header_id', $header->id)->delete();

                // Ingest CSV Rows
                $path = $this->csv_file->getRealPath();
                if (($handle = fopen($path, 'r')) !== false) {
                    // Skip header row
                    fgetcsv($handle, 2000, ',');

                    while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                        if (count($data) < 10) continue; 

                        // Extract and clean values
                        $projectTitle = trim($data[0] ?? '');
                        $implementingUnit = trim($data[1] ?? '');
                        $description = trim($data[2] ?? '');
                        $procurementMode = trim($data[3] ?? '');
                        $epaRaw = strtolower(trim($data[4] ?? ''));
                        $isEpa = $epaRaw === 'yes' || $epaRaw === '1' || $epaRaw === 'y' || $epaRaw === 'true';
                        $evaluationCriteria = trim($data[5] ?? '');
                        $activityStart = trim($data[6] ?? '');
                        $activityEnd = trim($data[7] ?? '');
                        $sourceOfFund = trim($data[8] ?? '');
                        
                        $approvedBudgetRaw = str_replace([',', '₱', 'Php', 'PHP', ' ', '$'], '', $data[9] ?? '0');
                        $approvedBudget = (float) $approvedBudgetRaw;
                        
                        $strategyTools = trim($data[10] ?? '');
                        $remarks = trim($data[11] ?? '');

                        AppLineItem::create([
                            'app_header_id'       => $header->id,
                            'project_title'       => $projectTitle,
                            'implementing_unit'   => $implementingUnit,
                            'description'         => $description,
                            'procurement_mode'    => $procurementMode,
                            'is_epa'              => $isEpa,
                            'evaluation_criteria' => $evaluationCriteria ?: null,
                            'activity_start'      => $activityStart,
                            'activity_end'        => $activityEnd,
                            'source_of_fund'      => $sourceOfFund,
                            'approved_budget'     => $approvedBudget,
                            'strategy_tools'      => $strategyTools ?: null,
                            'remarks'             => $remarks ?: null,
                            'utilized_budget'     => 0.00,
                        ]);
                    }
                    fclose($handle);
                }

                // Store CSV file securely outside the public root
                $csvFilename = "app_{$this->fiscalYear}_" . time() . '.' . $this->csv_file->getClientOriginalExtension();
                $csvPathDb = $this->csv_file->storeAs('secure/app_csvs', $csvFilename, 'local');
                
                $header->update(['csv_file_path' => $csvPathDb]);
            });

            $this->reset('csv_file');
            $this->successMessage = "CSV layout lines successfully ingested! Please review the row metrics below and attach the signed PDF proof to activate.";
            $this->errorMessage = null;
            $this->showCsvForm = false;
            $this->dispatch('app-status-updated');

        } catch (\Exception $e) {
            $this->errorMessage = "Failed to process CSV layout: " . $e->getMessage();
            $this->successMessage = null;
        }
    }

    // 2. PHASE 2: Attach Legal Verification and Unlock System (PDF Only)
    public function finalizeWithPdf()
    {
        $this->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:51200', // 50MB Limit
        ], [
            'pdf_file.mimes' => 'The uploaded file must be a valid PDF layout.',
        ]);

        $appHeader = AppHeader::where('fiscal_year', $this->fiscalYear)->first();
        $lineItemsCount = $appHeader ? $appHeader->lineItems()->count() : 0;

        if (!$appHeader || $lineItemsCount === 0) {
            $this->errorMessage = "Process Blocked: You must upload and parse the master APP CSV file before uploading the signed PDF scan.";
            return;
        }

        try {
            // Securely store the file outside the public web root directory
            $pdfFilename = "APP_{$this->fiscalYear}_" . time() . ".pdf";
            $pdfPath = $this->pdf_file->storeAs('secure/app_scans', $pdfFilename, 'local');

            // Hard-lock the approval states
            $appHeader->update([
                'scanned_pdf_path' => $pdfPath,
                'is_approved' => true,
                'approved_at' => now(),
            ]);

            $this->reset('pdf_file');
            $this->successMessage = "Annual Procurement Plan officially APPROVED and activated for FY {$this->fiscalYear}!";
            $this->errorMessage = null;
            
            // Fire event to unlock user interfaces dynamically across pages
            $this->dispatch('app-status-updated');
            $this->dispatch('app-approved'); 

        } catch (\Exception $e) {
            $this->errorMessage = "Failed to finalize APP approval: " . $e->getMessage();
            $this->successMessage = null;
        }
    }

    public function revokeApp()
    {
        $header = AppHeader::where('fiscal_year', $this->fiscalYear)->first();
        if (!$header) return;

        // Check if there are PRs using this APP
        $hasPrs = \App\Models\PrItem::whereIn('app_line_item_id', $header->lineItems()->pluck('id'))->exists();
        if ($hasPrs) {
            $this->errorMessage = "Cannot revoke this APP. Some Purchase Requests have already linked to its line items.";
            return;
        }

        $header->update([
            'is_approved' => false,
            'approved_at' => null,
        ]);

        $this->successMessage = "APP approval revoked. PR compilation for FY {$this->fiscalYear} has been suspended.";
        $this->errorMessage = null;
        $this->dispatch('app-status-updated');
    }

    public function downloadPdf($id)
    {
        $header = AppHeader::findOrFail($id);
        if ($header->scanned_pdf_path && \Storage::disk('local')->exists($header->scanned_pdf_path)) {
            return \Storage::disk('local')->download($header->scanned_pdf_path, "APP_{$header->fiscal_year}_scanned.pdf");
        }
        $this->errorMessage = "The PDF scanned file could not be found on storage.";
    }

    public function deleteApp()
    {
        $header = AppHeader::where('fiscal_year', $this->fiscalYear)->first();
        if (!$header) return;

        // Check if there are PRs using this APP
        $hasPrs = \App\Models\PrItem::whereIn('app_line_item_id', $header->lineItems()->pluck('id'))->exists();
        if ($hasPrs) {
            $this->errorMessage = "Cannot delete this APP. Some Purchase Requests have already linked to its line items.";
            return;
        }

        DB::transaction(function () use ($header) {
            if ($header->scanned_pdf_path) {
                \Storage::disk('local')->delete($header->scanned_pdf_path);
            }
            if ($header->csv_file_path) {
                \Storage::disk('local')->delete($header->csv_file_path);
            }
            $header->delete();
        });

        $this->successMessage = "APP for FY {$this->fiscalYear} has been deleted.";
        $this->errorMessage = null;
        $this->showCsvForm = true;
        $this->dispatch('app-status-updated');
    }

    public function with(): array
    {
        $appHeader = AppHeader::where('fiscal_year', $this->fiscalYear)
            ->with(['uploadedBy'])
            ->first();

        $lineItemsCount = 0;
        $totalBudget = 0;
        $utilizedBudget = 0;

        if ($appHeader) {
            $lineItemsCount = $appHeader->lineItems()->count();
            $totalBudget = $appHeader->lineItems()->sum('approved_budget');
            $utilizedBudget = $appHeader->lineItems()->sum('utilized_budget');
        }

        return [
            'appHeader' => $appHeader,
            'lineItemsCount' => $lineItemsCount,
            'totalBudget' => $totalBudget,
            'utilizedBudget' => $utilizedBudget,
        ];
    }
}; ?>

<div class="{{ $isModal ? 'space-y-6' : 'bg-white border border-[#c3c6d1] rounded-2xl shadow-sm p-6 space-y-6' }}">
    {{-- Header Bar --}}
    @if(!$isModal)
        <div class="flex justify-between items-center border-b border-[#eeedf2] pb-4">
            <div>
                <h3 class="font-bold text-[#001e40] text-lg flex items-center gap-2">
                    <span class="material-symbols-outlined text-[24px]">gavel</span>
                    APP Upload & Activation
                </h3>
                <p class="text-xs text-[#43474f] mt-1">Ingest, activate, and manage the official APP to control system-wide PR creation rights.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3.5 py-1.5 bg-[#001e40]/8 text-[#001e40] text-xs font-bold uppercase rounded-lg border border-[#001e40]/10">
                    Fiscal Year: {{ $fiscalYear }}
                </span>
            </div>
        </div>
    @endif

    {{-- Feedback Messages --}}
    @if($successMessage)
        <div class="flex items-start gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl shadow-xs animate-in fade-in duration-200">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <p class="text-xs font-bold flex-1 leading-relaxed">{{ $successMessage }}</p>
            <button @click="$wire.set('successMessage', null)" class="p-0.5 hover:bg-green-100 rounded">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    @endif

    @if($errorMessage)
        <div class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl shadow-xs animate-in fade-in duration-200">
            <span class="material-symbols-outlined text-red-600">error</span>
            <p class="text-xs font-bold flex-1 leading-relaxed">{{ $errorMessage }}</p>
            <button @click="$wire.set('errorMessage', null)" class="p-0.5 hover:bg-red-100 rounded">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left & Center Panels (Form Actions) --}}
        <div class="lg:col-span-2 space-y-4">
            
            {{-- STEP 1: CSV INGESTION PANEL --}}
            @if(!$appHeader || !$appHeader->is_approved)
                @if(!$showCsvForm && $lineItemsCount > 0)
                    <div class="bg-[#f9f9fe] border border-[#c3c6d1] p-5 rounded-2xl flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-green-50 border border-green-200 flex items-center justify-center text-green-700">
                                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1">
                                    Step 1: Layout Ingested
                                </h4>
                                <p class="text-[11px] text-[#43474f] mt-0.5 leading-normal">
                                    {{ number_format($lineItemsCount) }} budget lines mapped for FY {{ $fiscalYear }}.
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showCsvForm', true)" class="border border-[#c3c6d1] text-[#001e40] hover:bg-[#001e40]/5 py-2 px-4 rounded-lg text-xs font-bold transition-all flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">edit</span>
                            Re-upload CSV
                        </button>
                    </div>
                @else
                    <form wire:submit.prevent="uploadCsv" 
                          x-data="{ isDragging: false }"
                          @dragover.prevent="isDragging = true"
                          @dragleave.prevent="isDragging = false"
                          @drop.prevent="isDragging = false; $refs.csvInput.files = $event.dataTransfer.files; $refs.csvInput.dispatchEvent(new Event('change'))"
                          class="bg-[#f9f9fe] border border-[#c3c6d1] p-5 rounded-2xl space-y-4 shadow-2xs">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">table_chart</span>
                                    Step 1: Structural Layout Ingestion (CSV)
                                </h4>
                                <p class="text-[11px] text-[#43474f] mt-1 leading-relaxed">
                                    Upload and parse the database rows (CSV) from the Central Office. This maps out layout lines and locks them as unapproved drafts.
                                </p>
                            </div>
                            @if($lineItemsCount > 0)
                                <button type="button" wire:click="$set('showCsvForm', false)" class="text-[#43474f] hover:text-[#001e40] text-xs font-bold flex items-center gap-0.5 transition-all">
                                    <span class="material-symbols-outlined text-[18px]">expand_less</span>
                                    Collapse
                                </button>
                            @endif
                        </div>

                        {{-- Drop Zone --}}
                        <div class="relative border-2 border-dashed rounded-xl p-6 text-center transition-all duration-200"
                             :class="isDragging ? 'border-[#001e40] bg-[#f4f3f8]' : 'border-[#c3c6d1] bg-white'">
                            
                            <input type="file" class="hidden" x-ref="csvInput" wire:model="csv_file" accept=".csv" />

                            @if(!$csv_file)
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-12 h-12 rounded-full bg-[#f4f3f8] flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[24px] text-[#001e40]">cloud_upload</span>
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#001e40] text-xs">Click to upload or drag and drop</p>
                                        <p class="text-[10px] text-[#43474f]/80 mt-0.5">CSV layouts up to 10MB</p>
                                    </div>
                                    <button type="button" @click="$refs.csvInput.click()" 
                                            class="px-4 py-1.5 bg-[#001e40]/10 text-[#001e40] font-bold text-xs rounded-lg hover:bg-[#001e40]/15 transition-all">
                                        Select CSV File
                                    </button>
                                </div>
                            @else
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[24px] text-green-700">description</span>
                                    </div>
                                    <div>
                                        <p class="font-bold text-green-800 text-xs">CSV Layout Selected</p>
                                        <p class="text-[11px] text-[#001e40] font-mono mt-0.5">{{ $csv_file->getClientOriginalName() }}</p>
                                    </div>
                                    <button type="button" wire:click="$set('csv_file', null)" class="text-[10px] text-red-600 font-bold hover:underline">Remove File</button>
                                </div>
                            @endif

                            <div wire:loading wire:target="csv_file" class="absolute inset-0 bg-white/80 backdrop-blur-[1px] flex items-center justify-center rounded-xl">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined animate-spin text-[20px] text-[#001e40]">sync</span>
                                    <span class="text-xs font-bold text-[#001e40]">Uploading CSV...</span>
                                </div>
                            </div>
                        </div>

                        @if($csv_file)
                            <div class="flex justify-end">
                                <button type="submit" wire:loading.attr="disabled" class="w-full bg-[#001e40] text-white py-2.5 px-5 rounded-lg text-xs font-bold hover:bg-[#001e40]/90 active:scale-95 transition-all flex items-center justify-center gap-1.5 shadow-sm disabled:opacity-60">
                                    <span wire:loading wire:target="uploadCsv" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                    <span wire:loading.remove wire:target="uploadCsv" class="material-symbols-outlined text-[16px]">publish</span>
                                    Parse Layout Items
                                </button>
                            </div>
                        @endif
                        @error('csv_file') <p class="text-[10px] text-[#ba1a1a] mt-1 font-bold">{{ $message }}</p> @enderror
                    </form>
                @endif
            @endif

            {{-- STEP 2: PDF LEGAL ACTIVATION PANEL --}}
            @if($appHeader && !$appHeader->is_approved && $lineItemsCount > 0)
                <form wire:submit.prevent="finalizeWithPdf" 
                      x-data="{ isDragging: false }"
                      @dragover.prevent="isDragging = true"
                      @dragleave.prevent="isDragging = false"
                      @drop.prevent="isDragging = false; $refs.pdfInput.files = $event.dataTransfer.files; $refs.pdfInput.dispatchEvent(new Event('change'))"
                      class="bg-green-50/50 border border-green-200/80 p-5 rounded-2xl space-y-4 shadow-xs animate-in fade-in slide-in-from-top-2 duration-300">
                    <div>
                        <h4 class="text-xs font-bold text-green-800 uppercase tracking-wider flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">verified_user</span>
                            Step 2: Upload Legal Scan & Open Portal (PDF)
                        </h4>
                        <p class="text-[11px] text-green-700/90 mt-1 leading-relaxed">
                            Attach the physically signed, finalized APP copy. This will instantly approve the layout lines, stamp the approval time, and activate system-wide PR creation.
                        </p>
                    </div>

                    {{-- Drop Zone --}}
                    <div class="relative border-2 border-dashed rounded-xl p-6 text-center transition-all duration-200"
                         :class="isDragging ? 'border-green-700 bg-green-50' : 'border-green-300 bg-white'">
                        
                        <input type="file" class="hidden" x-ref="pdfInput" wire:model="pdf_file" accept=".pdf" />

                        @if(!$pdf_file)
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[24px] text-green-800">cloud_upload</span>
                                </div>
                                <div>
                                    <p class="font-bold text-green-800 text-xs">Click to upload or drag and drop</p>
                                    <p class="text-[10px] text-green-700/80 mt-0.5">Scanned PDF scan up to 50MB</p>
                                </div>
                                <button type="button" @click="$refs.pdfInput.click()" 
                                        class="px-4 py-1.5 bg-green-700/10 text-green-800 font-bold text-xs rounded-lg hover:bg-green-700/15 transition-all">
                                    Select PDF File
                                </button>
                            </div>
                        @else
                            <div class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[24px] text-green-900">picture_as_pdf</span>
                                </div>
                                <div>
                                    <p class="font-bold text-green-950 text-xs">PDF Scan Selected</p>
                                    <p class="text-[11px] text-green-900 font-mono mt-0.5">{{ $pdf_file->getClientOriginalName() }}</p>
                                </div>
                                <button type="button" wire:click="$set('pdf_file', null)" class="text-[10px] text-red-600 font-bold hover:underline">Remove File</button>
                            </div>
                        @endif

                        <div wire:loading wire:target="pdf_file" class="absolute inset-0 bg-white/80 backdrop-blur-[1px] flex items-center justify-center rounded-xl">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined animate-spin text-[20px] text-green-800">sync</span>
                                <span class="text-xs font-bold text-green-800">Uploading PDF...</span>
                            </div>
                        </div>
                    </div>

                    @if($pdf_file)
                        <div class="flex justify-end">
                            <button type="submit" wire:loading.attr="disabled" class="w-full bg-green-700 text-white py-2.5 px-5 rounded-lg text-xs font-bold hover:bg-green-800 active:scale-95 transition-all flex items-center justify-center gap-1.5 shadow-sm disabled:opacity-60">
                                <span wire:loading wire:target="finalizeWithPdf" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span wire:loading.remove wire:target="finalizeWithPdf" class="material-symbols-outlined text-[16px]">verified</span>
                                Approve & Activate APP
                            </button>
                        </div>
                    @endif
                    @error('pdf_file') <p class="text-[10px] text-[#ba1a1a] mt-1 font-bold">{{ $message }}</p> @enderror
                </form>
            @endif

            {{-- COMPLETED ACTIVATION STATUS PANEL --}}
            @if($appHeader && $appHeader->is_approved)
                <div class="bg-green-50 border border-green-200 p-6 rounded-2xl space-y-4 shadow-sm flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-green-600/10 border border-green-200 flex items-center justify-center text-green-700 flex-shrink-0">
                        <span class="material-symbols-outlined text-[28px]" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-green-900 leading-tight">APP Approved & Live</h4>
                        <p class="text-xs text-green-700 leading-relaxed">
                            The Annual Procurement Plan for fiscal year <strong class="text-green-950">{{ $fiscalYear }}</strong> is fully approved and active. System-wide PR compilation is unlocked.
                        </p>
                        <p class="text-[10px] text-green-700/80 pt-1 font-semibold">
                            Activated at: {{ \Carbon\Carbon::parse($appHeader->approved_at)->format('M d, Y h:i A') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        {{-- APP Snapshot Metrics Panel (Right Panel) --}}
        <div class="bg-[#f9f9fe] border border-[#eeedf2] p-5 rounded-2xl flex flex-col justify-between shadow-2xs space-y-4">
            <div class="space-y-4">
                <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1.5 border-b border-[#eeedf2] pb-2">
                    <span class="material-symbols-outlined text-[18px]">query_stats</span>
                    APP Snapshot Metrics
                </h4>

                <div class="space-y-3.5">
                    {{-- Status Badge --}}
                    <div>
                        <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block mb-1">Gate Status</span>
                        @if($appHeader && $appHeader->is_approved)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 border border-green-200 text-[10px] font-bold uppercase rounded-full shadow-3xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-600 animate-pulse"></span>
                                Live & Active
                            </span>
                        @elseif($appHeader)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#fff8e1] text-[#f57f17] border border-[#ffe082] text-[10px] font-bold uppercase rounded-full shadow-3xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#f57f17]"></span>
                                Ingestion Phase 1 (Unapproved)
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 text-[#ba1a1a] border border-red-200 text-[10px] font-bold uppercase rounded-full shadow-3xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#ba1a1a]"></span>
                                Not Ingested
                            </span>
                        @endif
                    </div>

                    {{-- Allocation Details --}}
                    <div class="space-y-1 bg-white border border-[#eeedf2] p-3 rounded-xl">
                        <span class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider block">Line Items Count</span>
                        <p class="text-lg font-black text-[#001e40]">{{ number_format($lineItemsCount) }}</p>
                    </div>

                    <div class="space-y-1 bg-white border border-[#eeedf2] p-3 rounded-xl">
                        <span class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider block">Total Approved Budget</span>
                        <p class="text-lg font-black text-[#001e40]">₱{{ number_format($totalBudget, 2) }}</p>
                    </div>

                    @if($appHeader && $appHeader->is_approved)
                        <div class="space-y-1 bg-white border border-[#eeedf2] p-3 rounded-xl">
                            <span class="text-[9px] font-bold text-[#43474f] uppercase tracking-wider block">Utilized Budget</span>
                            <p class="text-lg font-black text-blue-700">₱{{ number_format($utilizedBudget, 2) }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Actions Panel --}}
            <div class="pt-3 border-t border-[#eeedf2] space-y-2">
                @if($appHeader)
                    <div class="flex flex-col gap-2">
                        @if($appHeader->is_approved)
                            <button wire:click="revokeApp" class="w-full border border-[#ba1a1a] text-[#ba1a1a] py-2 px-3 rounded-lg text-xs font-bold hover:bg-red-50 active:scale-95 transition-all flex items-center justify-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">block</span>
                                Revoke Approval
                            </button>
                        @endif
                        <div class="flex gap-2">
                            @if($appHeader->scanned_pdf_path)
                                <button wire:click="downloadPdf({{ $appHeader->id }})" class="flex-1 border border-[#c3c6d1] text-[#43474f] py-2 px-3 rounded-lg text-xs font-bold hover:bg-gray-100 flex items-center justify-center gap-1.5" title="Download Approved PDF">
                                    <span class="material-symbols-outlined text-[16px]">picture_as_pdf</span>
                                    Download PDF
                                </button>
                            @endif
                            <button wire:click="deleteApp" wire:confirm="Are you sure you want to completely delete this APP layout and reset all configurations?" class="flex-1 border border-[#ba1a1a] text-[#ba1a1a] py-2 px-3 rounded-lg text-xs font-bold hover:bg-red-50 flex items-center justify-center gap-1.5" title="Delete APP">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                Delete Layout
                            </button>
                        </div>
                    </div>
                @else
                    <p class="text-[10px] text-[#43474f]/60 text-center italic">Awaiting structural layout files to display actions.</p>
                @endif
            </div>
        </div>
    </div>
</div>
