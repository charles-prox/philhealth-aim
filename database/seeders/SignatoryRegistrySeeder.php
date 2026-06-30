<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SignatoryRegistrySeeder extends Seeder
{
    /**
     * Seeds the canonical position slot structure for the PhilHealth AIM
     * Signatory Matrix Registry.
     *
     * All slots are seeded with primary_employee_id = NULL intentionally.
     * The Admin must assign the correct employee for each slot via the
     * Signatory Switchboard before the PR Wizard becomes operational.
     */
    public function run(): void
    {
        // 1. Define all valid slots (Global, Section, and Unit tiers)
        $slots = [
            [
                'position_code'  => 'RVP',
                'position_title' => 'Regional Vice President',
                'office_id'      => null,
            ],
            [
                'position_code'  => 'MSD_HEAD',
                'position_title' => 'Management Support Division Head',
                'office_id'      => null,
            ],
            [
                'position_code'  => 'HCDMD_CHIEF',
                'position_title' => 'Health Care Delivery Management Division Chief',
                'office_id'      => null,
            ],
            [
                'position_code'  => 'FOD_CHIEF',
                'position_title' => 'Field Operations Division Chief',
                'office_id'      => null,
            ],
            [
                'position_code'  => 'BUDGET_OFFICER',
                'position_title' => 'Budget Officer',
                'office_id'      => null,
            ],
        ];

        // Local Section Slots (type = SECTION)
        $sections = DB::table('offices')->where('type', 'SECTION')->get(['id', 'name']);
        foreach ($sections as $section) {
            $slots[] = [
                'position_code'  => 'SECTION_HEAD',
                'position_title' => "Head — {$section->name}",
                'office_id'      => $section->id,
            ];
        }

        // Local Unit Slots (type = UNIT)
        $units = DB::table('offices')->where('type', 'UNIT')->get(['id', 'name']);
        foreach ($units as $unit) {
            $slots[] = [
                'position_code'  => 'UNIT_HEAD',
                'position_title' => "Head — {$unit->name}",
                'office_id'      => $unit->id,
            ];
        }

        // 2. Clean up any slots in the database that are not in this seeded list
        $keepKeys = [];
        foreach ($slots as $slot) {
            $officeIdStr = $slot['office_id'] === null ? 'null' : (string) $slot['office_id'];
            $keepKeys[] = $slot['position_code'] . ':' . $officeIdStr;
        }

        $existingSlots = DB::table('signatory_registry')->get(['id', 'position_code', 'office_id']);
        foreach ($existingSlots as $existing) {
            $officeIdStr = $existing->office_id === null ? 'null' : (string) $existing->office_id;
            $key = $existing->position_code . ':' . $officeIdStr;
            if (!in_array($key, $keepKeys)) {
                DB::table('signatory_registry')->where('id', $existing->id)->delete();
            }
        }

        // 5. Safe upsert: preserves existing assignments
        foreach ($slots as $slot) {
            $exists = DB::table('signatory_registry')
                ->where('position_code', $slot['position_code'])
                ->where('office_id', $slot['office_id'])
                ->exists();

            if ($exists) {
                // Only update the position title (handles office rename or typo fix)
                DB::table('signatory_registry')
                    ->where('position_code', $slot['position_code'])
                    ->where('office_id', $slot['office_id'])
                    ->update([
                        'position_title' => $slot['position_title'],
                        'updated_at'     => now(),
                    ]);
            } else {
                // New slot: seed with empty/null defaults
                DB::table('signatory_registry')->insert([
                    'position_code'             => $slot['position_code'],
                    'position_title'            => $slot['position_title'],
                    'office_id'                 => $slot['office_id'],
                    'primary_employee_id'       => null,
                    'oic_primary_employee_id'   => null,
                    'oic_secondary_employee_id' => null,
                    'active_holder'             => 'PRIMARY',
                    'created_at'                => now(),
                    'updated_at'                => now(),
                ]);
            }
        }

        $count = count($slots);
        $this->command->info("✅  SignatoryRegistrySeeder: {$count} position slot(s) seeded. Assign employees via the Signatory Switchboard (Admin → Signatory Matrix).");
    }
}
