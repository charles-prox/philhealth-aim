<?php

use App\Models\BudgetTransaction;
use App\Models\BudgetYear;
use App\Models\CobItem;
use App\Models\CobVersion;
use Illuminate\Support\Facades\DB;

try {
    DB::statement("SET session_replication_role = 'replica';");

    BudgetTransaction::truncate();
    CobItem::truncate();
    CobVersion::truncate();
    BudgetYear::truncate();

    DB::statement("SET session_replication_role = 'origin';");

    echo "Successfully emptied COB, Versions, and Budget Years.\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
