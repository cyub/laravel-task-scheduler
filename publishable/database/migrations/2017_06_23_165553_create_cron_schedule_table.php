<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCronScheduleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cron_schedule', function (Blueprint $table) {
            $table->increments('schedule_id');
            $table->string('job_code');
            $table->enum('status', ['pending','running','success','missed','error'])->default('pending');
            $table->text('messages')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('scheduled_at');
            $table->dateTime('executed_at')->nullable();
            $table->dateTime('finished_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cron_schedule');
    }
}
