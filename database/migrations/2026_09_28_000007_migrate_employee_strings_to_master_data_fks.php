<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec Section B1's master lists are the backbone other modules draw from —
 * `employees.job_title`/`department`/`location` were plain strings from
 * this prototype's very first iteration (pre-dating Phase 1), which this
 * migration corrects by pointing them at real `job_titles`/`sub_units`/
 * `locations` rows instead. Every existing distinct string value in the
 * seeded demo data becomes a real master row (data-preserving, not
 * destructive — matches this project's "keep demo data" rule throughout);
 * nothing is lost, only re-pointed. Uses raw `DB::table()` rather than the
 * Eloquent models so this migration stays correct even if those models'
 * behavior (Auditable, Media Library, casts) changes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('job_title_id')->nullable()->after('job_title')->constrained('job_titles')->nullOnDelete();
            $table->foreignId('sub_unit_id')->nullable()->after('department')->constrained('sub_units')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('location')->constrained('locations')->nullOnDelete();
        });

        $now = now();

        foreach (DB::table('employees')->whereNotNull('job_title')->distinct()->pluck('job_title') as $name) {
            $id = DB::table('job_titles')->where('name', $name)->value('id')
                ?? DB::table('job_titles')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('employees')->where('job_title', $name)->update(['job_title_id' => $id]);
        }

        foreach (DB::table('employees')->whereNotNull('department')->distinct()->pluck('department') as $name) {
            $id = DB::table('sub_units')->where('name', $name)->value('id')
                ?? DB::table('sub_units')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('employees')->where('department', $name)->update(['sub_unit_id' => $id]);
        }

        foreach (DB::table('employees')->whereNotNull('location')->distinct()->pluck('location') as $name) {
            $id = DB::table('locations')->where('name', $name)->value('id')
                ?? DB::table('locations')->insertGetId(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('employees')->where('location', $name)->update(['location_id' => $id]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'department', 'location']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('job_title_id');
            $table->string('department')->nullable()->after('sub_unit_id');
            $table->string('location')->nullable()->after('location_id');
        });

        DB::table('employees')->update([
            'job_title' => DB::raw('(select name from job_titles where job_titles.id = employees.job_title_id)'),
            'department' => DB::raw('(select name from sub_units where sub_units.id = employees.sub_unit_id)'),
            'location' => DB::raw('(select name from locations where locations.id = employees.location_id)'),
        ]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_title_id');
            $table->dropConstrainedForeignId('sub_unit_id');
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
