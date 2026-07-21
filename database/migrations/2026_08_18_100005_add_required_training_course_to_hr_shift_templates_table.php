<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::table('hr_shift_templates',function(Blueprint $t){$t->foreignId('required_training_course_id')->nullable()->after('is_active')->constrained('hr_training_courses')->nullOnDelete();});} public function down():void{Schema::table('hr_shift_templates',function(Blueprint $t){$t->dropConstrainedForeignId('required_training_course_id');});} };
