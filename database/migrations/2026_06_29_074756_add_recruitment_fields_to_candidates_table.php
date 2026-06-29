<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable()->after('phone');
            $table->enum('sex', ['Male','Female'])->nullable()->after('age');
            $table->enum('civil_status', ['Single','Married','Legally Separated','Widowed'])->nullable()->after('sex');
            $table->string('religion', 100)->nullable()->after('civil_status');
            $table->string('disability', 255)->nullable()->after('religion');
            $table->string('ethnic_group', 100)->nullable()->after('disability');
            $table->text('education')->nullable()->after('ethnic_group');
            $table->string('training_hours', 100)->nullable()->after('education');
            $table->string('years_experience', 100)->nullable()->after('training_hours');
            $table->string('eligibility', 255)->nullable()->after('years_experience');
        });
    }

    public function down(): void {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn([
                'age','sex','civil_status','religion','disability',
                'ethnic_group','education','training_hours','years_experience','eligibility',
            ]);
        });
    }
};