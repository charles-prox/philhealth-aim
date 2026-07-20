<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeAimModule extends Command
{
    protected $signature = 'make:aim-module {name : The name of the module (e.g., Canvassing)}';
    protected $description = 'Scaffold a pristine PhilHealth-AIM Holy Trinity module layout';

    public function handle()
    {
        $name = ucfirst($this->argument('name'));
        $nameLower = Str::kebab($name);

        $this->info("🏗️  Scaffolding PhilHealth-AIM module: {$name}...");

        // 1. Generate Form Object
        $formPath = app_path("Livewire/Forms/{$name}Form.php");
        if (!File::exists($formPath)) {
            File::ensureDirectoryExists(dirname($formPath));
            File::put($formPath, $this->getFormTemplate($name));
            $this->line("✅ Created Form Object: app/Livewire/Forms/{$name}Form.php", 'info');
        }

        // 2. Generate Service Class
        $servicePath = app_path("Services/{$name}Service.php");
        if (!File::exists($servicePath)) {
            File::ensureDirectoryExists(dirname($servicePath));
            File::put($servicePath, $this->getServiceTemplate($name));
            $this->line("✅ Created Service Class: app/Services/{$name}Service.php", 'info');
        }

        // 3. Generate Livewire Volt / Class Component
        $componentPath = app_path("Livewire/Procurement/{$name}Portal.php");
        if (!File::exists($componentPath)) {
            File::ensureDirectoryExists(dirname($componentPath));
            File::put($componentPath, $this->getComponentTemplate($name, $nameLower));
            $this->line("✅ Created Component: app/Livewire/Procurement/{$name}Portal.php", 'info');
        }

        // 4. Generate Views Directory, Master Blade, and Partials Folder
        $viewDir = resource_path("views/livewire/procurement/{$nameLower}");
        File::ensureDirectoryExists($viewDir . '/partials');
        
        $masterViewPath = "{$viewDir}/{$nameLower}-portal.blade.php";
        if (!File::exists($masterViewPath)) {
            File::put($masterViewPath, $this->getViewTemplate($name));
            File::put("{$viewDir}/partials/.gitkeep", ""); // keep folder in git
            $this->line("✅ Created View Layout: resources/views/livewire/procurement/{$nameLower}/partials/", 'info');
        }

        $this->info("🎉 Module {$name} scaffolded successfully! Keep it clean.");
    }

    private function getFormTemplate($name) {
        return "<?php\n\nnamespace App\Livewire\Forms;\n\nuse Livewire\Form;\nuse Livewire\Attributes\Rule;\n\nclass {$name}Form extends Form\n{\n    // #[Rule('required')]\n    // public \$property = '';\n}\n";
    }

    private function getServiceTemplate($name) {
        return "<?php\n\nnamespace App\Services;\n\nclass {$name}Service\n{\n    public function execute(array \$data)\n    {\n        // Keep database transactions, calculations, and secure file saves isolated here!\n    }\n}\n";
    }

    private function getComponentTemplate($name, $nameLower) {
        return "<?php\n\nnamespace App\Livewire\Procurement;\n\nuse Livewire\Component;\nuse App\Livewire\Forms\\{$name}Form;\nuse App\Services\\{$name}Service;\n\nclass {$name}Portal extends Component\n{\n    public {$name}Form \$form;\n\n    public function save({$name}Service \$service)\n    {\n        \$this->form->validate();\n        \$service->execute(\$this->form->all());\n    }\n\n    public function render()\n    {\n        return view('livewire.procurement.{$nameLower}.{$nameLower}-portal');\n    }\n}\n";
    }

    private function getViewTemplate($name) {
        return "<!-- resources/views/livewire/procurement/... -->\n<div class=\"p-6\">\n    <h1 class=\"text-lg font-bold\">{$name} Module</h1>\n    \n    <!-- Use @include('livewire.procurement.xxx.partials.file') for sub-layouts, tables, and modals! -->\n</div>\n";
    }
}
