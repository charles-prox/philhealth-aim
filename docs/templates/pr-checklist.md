## 🚀 Code Quality & Architectural Checklist

Please verify that this code fully adheres to the PhilHealth-AIM System Architecture Standards before requesting a merge review:

### 🎨 Frontend & UI View Layer
- [ ] **Blade Partials:** Any view section, complex modal, or form step exceeding 150 lines of HTML is decoupled into `resources/views/livewire/procurement/partials/`.
- [ ] **Nested Error Mapping:** Form fields utilize nested bindings (e.g., `wire:model="form.property_name"`) and match their respective UI outputs (e.g., `@error('form.property_name')`).

### 🧠 Logic & Operations Layer
- [ ] **Service Classes:** Livewire components and controllers act purely as traffic controllers. Database transactions, heavy computational logic, and file management operations are delegated to `app/Services/`.
- [ ] **Private Asset Isolation:** All dynamically generated files (PR, RFQ, ABC, Cover Letter) are read from and written to the private, secure storage disk (`secure_procurement`).

### 📋 State & Validation Layer
- [ ] **Form Objects:** Groups of form input properties and their corresponding validation rule arrays are encapsulated cleanly within a dedicated `Livewire\Form` class.
- [ ] **Step-Specific Validation:** Multi-step wizards use step-isolated validations (e.g., `validateStepOne()`) to prevent un-rendered fields from blocking state progression.
