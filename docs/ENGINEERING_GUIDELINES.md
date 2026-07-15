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
