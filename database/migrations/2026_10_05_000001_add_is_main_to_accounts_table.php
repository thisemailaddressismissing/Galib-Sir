<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_main')->default(false)->after('type')->index();
        });

        // Mark the existing main account: one named "Main", otherwise the oldest account.
        $mainId = DB::table('accounts')->where('name', 'Main')->value('id')
            ?? DB::table('accounts')->orderBy('id')->value('id');
        if ($mainId) {
            DB::table('accounts')->where('id', $mainId)->update(['is_main' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('is_main');
        });
    }
};
