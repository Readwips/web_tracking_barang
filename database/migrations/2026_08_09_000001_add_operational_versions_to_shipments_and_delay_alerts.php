<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('operational_version')->default(0)->after('delay_reported_at');
            $table->unsignedInteger('delay_report_sequence')->default(0)->after('operational_version');
        });

        Schema::table('delay_alert_deliveries', function (Blueprint $table) {
            $table->dropUnique('delay_alert_unique');
        });

        Schema::table('delay_alert_deliveries', function (Blueprint $table) {
            $table->unsignedInteger('delay_report_sequence')->default(0)->after('expected_arrival');
        });

        Schema::table('delay_alert_deliveries', function (Blueprint $table) {
            $table->unique(
                ['shipment_id', 'expected_arrival', 'delay_report_sequence', 'event', 'channel', 'destination_hash'],
                'delay_alert_unique'
            );
        });
    }

    public function down(): void
    {
        $identity = [
            'shipment_id',
            'expected_arrival',
            'event',
            'channel',
            'destination_hash',
        ];

        $duplicates = DB::table('delay_alert_deliveries')
            ->select($identity)
            ->groupBy($identity)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $group = DB::table('delay_alert_deliveries');

            foreach ($identity as $column) {
                $group->where($column, $duplicate->{$column});
            }

            $keepId = (clone $group)
                ->orderByDesc('delay_report_sequence')
                ->orderByDesc('id')
                ->value('id');

            (clone $group)->where('id', '!=', $keepId)->delete();
        }

        Schema::table('delay_alert_deliveries', function (Blueprint $table) {
            $table->dropUnique('delay_alert_unique');
        });

        Schema::table('delay_alert_deliveries', function (Blueprint $table) {
            $table->dropColumn('delay_report_sequence');
        });

        Schema::table('delay_alert_deliveries', function (Blueprint $table) {
            $table->unique(
                ['shipment_id', 'expected_arrival', 'event', 'channel', 'destination_hash'],
                'delay_alert_unique'
            );
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['operational_version', 'delay_report_sequence']);
        });
    }
};
