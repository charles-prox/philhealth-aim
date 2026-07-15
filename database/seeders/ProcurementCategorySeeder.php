<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcurementCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Consumables
            ['name' => 'Regular Office Supplies', 'uacs_code' => '50203010', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Consumable Log'],
            ['name' => 'Printer Toner & Ink Cartridges', 'uacs_code' => '50203010', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Consumable Log'],
            ['name' => 'Accountable Forms', 'uacs_code' => '50203020', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Accountability Serial Ledger'],
            ['name' => 'Drinking Water', 'uacs_code' => '50203990', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Monthly Delivery Log'],
            ['name' => 'First-Aid & Clinic Medicines', 'uacs_code' => '50203080', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Clinic Inventory Log'],
            ['name' => 'Janitorial Cleaning Supplies', 'uacs_code' => '50203990', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Stock Card'],

            // Utilities & Fuels
            ['name' => 'Electricity Billing', 'uacs_code' => '50204020', 'budget_class' => 'MOOE', 'tracking_type' => 'UTILITY', 'audit_requirement' => 'Monthly Utility Ledger'],
            ['name' => 'Water Utility Billing', 'uacs_code' => '50204010', 'budget_class' => 'MOOE', 'tracking_type' => 'UTILITY', 'audit_requirement' => 'Monthly Utility Ledger'],
            ['name' => 'Vehicle Diesel & Gasoline', 'uacs_code' => '50203090', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Fuel Trip Tickets'],
            ['name' => 'Generator Diesel', 'uacs_code' => '50203090', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Generator Run-Time Log'],

            // Subscriptions
            ['name' => 'Office Internet Connection', 'uacs_code' => '50205030', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'Subscription Contract & Speed Test Logs'],
            ['name' => 'Mobile Postpaid Plans', 'uacs_code' => '50205020', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'Call & Data Usage Tracker'],
            ['name' => 'Software Subscriptions (SaaS)', 'uacs_code' => '50299070', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'User Licenses Allocation Log'],

            // Rentals, Venues, & Events
            ['name' => 'Office Space Rental (LHIO / Service Desks)', 'uacs_code' => '50299050', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'Lease Contract'],
            ['name' => 'Event Venue & Hotel Rental', 'uacs_code' => '50299050', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Approved Training Design'],
            ['name' => 'Catering & Meals for Internal Meetings', 'uacs_code' => '50299030', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Attendance Sheet'],
            ['name' => 'Event Materials', 'uacs_code' => '50299030', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Participant Sign-In Sheets'],
            ['name' => 'Event Decorations & Backdrops', 'uacs_code' => '50299030', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Event Photographs'],

            // Maintenance & Labor
            ['name' => 'Vehicle Maintenance & Repairs', 'uacs_code' => '50213060', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Pre & Post-Repair Inspection'],
            ['name' => 'Air Conditioner (AC) Cleaning & Maintenance', 'uacs_code' => '50213050', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Service Accomplishment Report'],
            ['name' => 'Office Building Minor Repairs', 'uacs_code' => '50213040', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'GSU Punch List & Scope of Works'],
            ['name' => 'IT Equipment Repairs (Printers / Desktops)', 'uacs_code' => '50213050', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'GSU Diagnostics Sheet'],

            // Marketing & IEC
            ['name' => 'Radio Airtime & TV Broadcasting Fees', 'uacs_code' => '50299010', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'Certificate of Performance'],
            ['name' => 'Newspaper & Print Advertising Fees', 'uacs_code' => '50299010', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'Tear-Sheet of Published Ad'],
            ['name' => 'IEC Printed Materials', 'uacs_code' => '50299020', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Distribution Log'],
            ['name' => 'Advocacy T-Shirts & Collared Shirts', 'uacs_code' => '50299020', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Recipient Sign-off Sheet'],
            ['name' => 'Promotional Giveaways', 'uacs_code' => '50299020', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'Distribution Ledger'],

            // Semi-Expendable (Under 50k) -> ICS
            ['name' => 'Semi-Expendable Laptops & Desktops', 'uacs_code' => '50203210', 'budget_class' => 'MOOE', 'tracking_type' => 'ICS', 'audit_requirement' => 'Inventory Custodian Slip (ICS)'],
            ['name' => 'Semi-Expendable Printers & Scanners', 'uacs_code' => '50203210', 'budget_class' => 'MOOE', 'tracking_type' => 'ICS', 'audit_requirement' => 'Inventory Custodian Slip (ICS)'],
            ['name' => 'Semi-Expendable Office Chairs & Desks', 'uacs_code' => '50203220', 'budget_class' => 'MOOE', 'tracking_type' => 'ICS', 'audit_requirement' => 'Inventory Custodian Slip (ICS)'],
            ['name' => 'Semi-Expendable Air Conditioning Units', 'uacs_code' => '50203210', 'budget_class' => 'MOOE', 'tracking_type' => 'ICS', 'audit_requirement' => 'Inventory Custodian Slip (ICS)'],
            ['name' => 'Emergency Power UPS Batteries', 'uacs_code' => '50203210', 'budget_class' => 'MOOE', 'tracking_type' => 'ICS', 'audit_requirement' => 'Inventory Custodian Slip (ICS)'],

            // PPE (50k or more) -> PAR
            ['name' => 'Capitalized High-End Servers', 'uacs_code' => '10605030', 'budget_class' => 'CAPITAL_OUTLAY', 'tracking_type' => 'PAR', 'audit_requirement' => 'Property Acknowledgement Receipt (PAR)'],
            ['name' => 'Capitalized Claims Document Scanners', 'uacs_code' => '10605030', 'budget_class' => 'CAPITAL_OUTLAY', 'tracking_type' => 'PAR', 'audit_requirement' => 'Property Acknowledgement Receipt (PAR)'],
            ['name' => 'Office Vehicles / Utility Vans', 'uacs_code' => '10606010', 'budget_class' => 'CAPITAL_OUTLAY', 'tracking_type' => 'PAR', 'audit_requirement' => 'Property Acknowledgement Receipt (PAR)'],
            ['name' => 'Heavy-Duty Standby Generators', 'uacs_code' => '10605020', 'budget_class' => 'CAPITAL_OUTLAY', 'tracking_type' => 'PAR', 'audit_requirement' => 'Property Acknowledgement Receipt (PAR)'],

            // Services & Overheads
            ['name' => 'Office Security Services', 'uacs_code' => '50212030', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'Monthly Guard Duty Roster'],
            ['name' => 'Office Janitorial Services', 'uacs_code' => '50212020', 'budget_class' => 'MOOE', 'tracking_type' => 'CONTRACT', 'audit_requirement' => 'Service Attendance Sheet'],
            ['name' => 'Notary, Legalization, & Filing Fees', 'uacs_code' => '50211010', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Copy of Notarized Document'],
            ['name' => 'Medical Consultants & Fraud Auditors', 'uacs_code' => '50211990', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Accomplishment & Claims Audit Report'],
            ['name' => 'Staff Training Fees', 'uacs_code' => '50202010', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Certificate of Participation & Travel Order'],
            ['name' => 'Employee Annual Physical Exam (APE)', 'uacs_code' => '50215030', 'budget_class' => 'MOOE', 'tracking_type' => 'SERVICE', 'audit_requirement' => 'Employee Medical Logs'],
            ['name' => 'Prizes & Employee Awards (PRAISE)', 'uacs_code' => '50206010', 'budget_class' => 'MOOE', 'tracking_type' => 'CONSUMABLE', 'audit_requirement' => 'PRAISE Committee Resolution & Recipient Log'],
        ];

        foreach ($categories as $category) {
            DB::table('procurement_categories')->updateOrInsert(
                ['name' => $category['name']],
                [
                    'uacs_code' => $category['uacs_code'],
                    'budget_class' => $category['budget_class'],
                    'tracking_type' => $category['tracking_type'],
                    'audit_requirement' => $category['audit_requirement'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
