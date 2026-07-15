<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficeSeeder extends Seeder
{
    /**
     * Seeds the offices table using the official PhilHealth Regional Office X
     * organizational hierarchy structure with exact names, acronyms, and types.
     */
    public function run(): void
    {
        // 1. Map legacy acronyms in offices/employees to the new canonical matrix
        $legacyAcronymMap = [
            'LEGAL' => 'LO',
            'LHIO BUKIDNON' => 'LHIO-BUK',
            'LHIO CDO' => 'LHIO-CDO',
            'LHIO GINGOOG' => 'LHIO-GIN',
            'LHIO ILIGAN' => 'LHIO-ILI',
            'LHIO OZAMIZ' => 'LHIO-OZA',
            'PBC CAMIGUIN' => 'PBC-CAM',
            'OFOD' => 'FOD',
            'OMSD' => 'MSD',     // Merged proper office into division
            'OHCDMD' => 'HCDMD',   // Merged proper office into division
            'ORVP-O' => 'ORVP',    // Merged proper office into division
            'ORVP_DIV' => 'ORVP',    // Cleaned up duplicate division
        ];

        // Perform clean migration of raw acronym text fields to keep all relations intact
        foreach ($legacyAcronymMap as $legacy => $new) {
            DB::table('employees')
                ->where('office_division', $legacy)
                ->update(['office_division' => $new]);

            DB::table('procurement_folders')
                ->where('requesting_unit', $legacy)
                ->update(['requesting_unit' => $new]);
        }

        // Clean up legacy/proper offices that are now merged (delete by acronym and by name to handle NULL acronym rows)
        Office::whereIn('acronym', ['OMSD', 'OHCDMD', 'ORVP-O', 'ORVP_DIV'])->delete();
        Office::whereIn('name', [
            'Office of the Management Services Division',
            'Office of the HCDMD Chief',
            'Office of the Regional Vice President (Proper)',
            'Office of the Regional Vice President (Division)',
        ])->delete();

        // Temporarily clear parents to avoid FK cycle conflicts during name updates
        DB::table('offices')->update(['parent_id' => null]);

        $matrix = [
            // DIVISIONs
            ['name' => 'Office of the Regional Vice President', 'acronym' => 'ORVP', 'type' => 'DIVISION', 'parent' => null],
            ['name' => 'Management Services Division', 'acronym' => 'MSD', 'type' => 'DIVISION', 'parent' => 'ORVP'],
            ['name' => 'Health Care Delivery Management Division', 'acronym' => 'HCDMD', 'type' => 'DIVISION', 'parent' => 'ORVP'],
            ['name' => 'Field Operations Division', 'acronym' => 'FOD', 'type' => 'DIVISION', 'parent' => 'ORVP'],

            // SECTIONs & UNITs under ORVP
            ['name' => 'Public Affairs Unit', 'acronym' => 'PAU', 'type' => 'UNIT', 'parent' => 'ORVP'],
            ['name' => 'Legal Office', 'acronym' => 'LO', 'type' => 'SECTION', 'parent' => 'ORVP'],
            ['name' => 'Information Technology Management Section', 'acronym' => 'ITMS', 'type' => 'SECTION', 'parent' => 'ORVP'],
            ['name' => 'Planning and Research Unit', 'acronym' => 'PRU', 'type' => 'UNIT', 'parent' => 'ORVP'],

            // SECTIONs & UNITs under MSD
            ['name' => 'Administrative Services Section', 'acronym' => 'ASS', 'type' => 'SECTION', 'parent' => 'MSD'],
            ['name' => 'General Services Unit', 'acronym' => 'GSU', 'type' => 'UNIT', 'parent' => 'ASS'],
            ['name' => 'Human Resource Unit', 'acronym' => 'HRU', 'type' => 'UNIT', 'parent' => 'ASS'],
            ['name' => 'Fund Management Section', 'acronym' => 'FMS', 'type' => 'SECTION', 'parent' => 'MSD'],
            ['name' => 'Comptrollership Unit', 'acronym' => 'CU', 'type' => 'UNIT', 'parent' => 'FMS'],
            ['name' => 'Cash Management Unit', 'acronym' => 'CMU', 'type' => 'UNIT', 'parent' => 'FMS'],

            // SECTIONs & UNITs under HCDMD
            ['name' => 'Benefit Administration Section', 'acronym' => 'BAS', 'type' => 'SECTION', 'parent' => 'HCDMD'],
            ['name' => 'Accreditation & Quality Assurance Section', 'acronym' => 'AQAS', 'type' => 'SECTION', 'parent' => 'HCDMD'],
            ['name' => 'PhilHealth Customer Assistance, Relations & Empowerment Staff', 'acronym' => 'PCARES', 'type' => 'SECTION', 'parent' => 'HCDMD'],

            // SECTIONs & UNITs under FOD
            ['name' => 'Collection & Premium Accounts Management Section', 'acronym' => 'CPAMS', 'type' => 'SECTION', 'parent' => 'FOD'],
            ['name' => 'Membership & Marketing Section', 'acronym' => 'MMS', 'type' => 'SECTION', 'parent' => 'FOD'],
            ['name' => 'Bukidnon Local Health Insurance Office', 'acronym' => 'LHIO-BUK', 'type' => 'SECTION', 'parent' => 'FOD'],
            ['name' => 'Iligan Local Health Insurance Office', 'acronym' => 'LHIO-ILI', 'type' => 'SECTION', 'parent' => 'FOD'],
            ['name' => 'PhilHealth Business Center - Tubod', 'acronym' => 'PBC-TUB', 'type' => 'UNIT', 'parent' => 'LHIO-ILI'],
            ['name' => 'PhilHealth Business Center - Lala', 'acronym' => 'PBC-LAL', 'type' => 'UNIT', 'parent' => 'LHIO-ILI'],
            ['name' => 'Ozamiz Local Health Insurance Office', 'acronym' => 'LHIO-OZA', 'type' => 'SECTION', 'parent' => 'FOD'],
            ['name' => 'PhilHealth Business Center - Oroquieta', 'acronym' => 'PBC-ORO', 'type' => 'UNIT', 'parent' => 'LHIO-OZA'],
            ['name' => 'PhilHealth Business Center - Tangub', 'acronym' => 'PBC-TAN', 'type' => 'UNIT', 'parent' => 'LHIO-OZA'],
            ['name' => 'Cagayan De Oro Local Health Insurance Office', 'acronym' => 'LHIO-CDO', 'type' => 'SECTION', 'parent' => 'FOD'],
            ['name' => 'PhilHealth Business Center - Carmen', 'acronym' => 'PBC-CAR', 'type' => 'UNIT', 'parent' => 'LHIO-CDO'],
            ['name' => 'Gingoog Local Health Insurance Office', 'acronym' => 'LHIO-GIN', 'type' => 'SECTION', 'parent' => 'FOD'],
            ['name' => 'PBC Camiguin', 'acronym' => 'PBC-CAM', 'type' => 'UNIT', 'parent' => 'LHIO-GIN'],
        ];

        // 2. Perform safe update or insert
        foreach ($matrix as $data) {
            $searchAcronyms = [$data['acronym']];
            foreach ($legacyAcronymMap as $legacy => $new) {
                if ($new === $data['acronym']) {
                    $searchAcronyms[] = $legacy;
                }
            }

            // Look for existing registry record to rename/update
            $existing = Office::whereIn('acronym', $searchAcronyms)->first();

            if (!$existing) {
                $existing = Office::where('name', $data['name'])->first();
            }

            if ($existing) {
                $existing->update([
                    'name' => $data['name'],
                    'acronym' => $data['acronym'],
                    'type' => $data['type'],
                ]);
            } else {
                Office::create([
                    'name' => $data['name'],
                    'acronym' => $data['acronym'],
                    'type' => $data['type'],
                ]);
            }
        }

        // 3. Resolve and map parent_id references
        foreach ($matrix as $data) {
            if ($data['parent'] !== null) {
                $parent = Office::where('acronym', $data['parent'])->first();
                if ($parent) {
                    Office::where('acronym', $data['acronym'])->update([
                        'parent_id' => $parent->id,
                    ]);
                }
            }
        }
    }
}
