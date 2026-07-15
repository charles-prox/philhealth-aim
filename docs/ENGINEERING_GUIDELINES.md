# PhilHealth-AIM Engineering Guidelines & Code Standards

To keep our application secure, lightweight, and maintainable, all developers must adhere to the **"Holy Trinity" of Clean Laravel Engineering**:

## 1. Blade Partials (HTML Separation)
*   **The 150-Line Rule:** If any single Blade file exceeds 150 lines of HTML markup, split it up. 
*   Move steps, modals, complex tables, and sidebars into a `/partials` sub-directory.
*   *Implementation:* Use `@include('livewire.procurement.partials.filename')` within the main file. Do not rewrite variables; `@include` natively inherits parent state.

## 2. Service Classes (Business Logic Isolation)
*   Livewire components and HTTP Controllers must remain lean.
*   **Never** write `DB::transaction`, run raw Eloquent loops, or manage files directly inside components.
*   *Implementation:* Inject a service class from `app/Services/` into your component methods and delegate the heavy operations there.

## 3. Form Objects (Form State & Validation Isolation)
*   Do not clutter the top of Livewire components with dozens of flat public variables.
*   *Implementation:* Create a dedicated Form Object via `php artisan make:form [FormName]`. Enclose properties and validation rule matrices inside the form class.

## ⚖️ Core Architectural Laws for Livewire & Volt Components

To prevent files from ballooning beyond our 500-line warning threshold, all new components must adhere to the following logic boundaries:

1.  **Component Class Limit:** Keep controllers under 300 lines of pure PHP. If you exceed this, you are likely writing business logic that belongs elsewhere.
2.  **No Raw Joins in Controllers:** Direct SQL joins, unions, or complex Eloquent builders belong in Eloquent Model **Query Scopes**.
3.  **No Financial/Routing Rule Checks in Components:** Signatory routing, cost thresholds, and approval matrices must live in model scopes or specialized service classes.
4.  **No Manual Mapping Loops:** Form properties and model database columns must be hydrated using Form Object `fill()` or custom hydrator methods.

## 🛠️ Custom Generator Command

To make scaffolding new modules easy and ensure directory structure compliance, run the custom generator command instead of manually creating files:
```bash
./sail artisan make:aim-module [ModuleName]
```
This scaffolds:
1. **Form Object:** `app/Livewire/Forms/[ModuleName]Form.php`
2. **Service Class:** `app/Services/[ModuleName]Service.php`
3. **Livewire Component:** `app/Livewire/Procurement/[ModuleName]Portal.php`
4. **Views & Partials Folder:** `resources/views/livewire/procurement/[module-name]/partials/`


