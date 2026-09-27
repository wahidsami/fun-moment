<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobPostTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('buyer_jobs')) {
            Schema::create('buyer_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id')->index();
                $table->unsignedBigInteger('subcategory_id')->nullable()->index();
                $table->unsignedBigInteger('child_category_id')->nullable()->index();
                $table->unsignedBigInteger('buyer_id')->index();
                $table->unsignedBigInteger('country_id')->default(0)->index();
                $table->unsignedBigInteger('city_id')->default(0)->index();
                $table->string('title', 191);
                $table->string('slug', 191)->unique();
                $table->longText('description')->nullable();
                $table->string('image')->nullable();
                $table->tinyInteger('is_job_online')->default(0);
                $table->decimal('price', 12, 2)->default(0.00);
                $table->dateTime('dead_line')->nullable();
                $table->unsignedBigInteger('view')->default(0);
                $table->tinyInteger('is_job_on')->default(1)->comment('1=active/accepting bids, 0=off');
                $table->tinyInteger('status')->default(1)->comment('0=pending/draft, 1=active, 2=hired/completed, 3=cancelled');
                $table->timestamps();

                $table->foreign('buyer_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('job_requests')) {
            Schema::create('job_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_post_id')->index();
                $table->unsignedBigInteger('buyer_id')->index();
                $table->unsignedBigInteger('seller_id')->index();
                $table->decimal('expected_salary', 12, 2)->default(0.00);
                $table->text('cover_letter')->nullable();
                $table->tinyInteger('is_hired')->default(0)->comment('0=no, 1=yes');
                $table->tinyInteger('is_rejected')->default(0)->comment('0=no, 1=yes');
                $table->tinyInteger('status')->default(0)->comment('0=pending, 1=accepted/hired, 2=rejected');
                $table->timestamps();

                $table->foreign('job_post_id')->references('id')->on('buyer_jobs')->onDelete('cascade');
                $table->foreign('buyer_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['job_post_id', 'seller_id']);
            });
        }

        if (!Schema::hasTable('job_request_conversations')) {
            Schema::create('job_request_conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_request_id')->index();
                $table->string('type', 50)->comment('buyer, seller');
                $table->text('message');
                $table->string('attachment')->nullable();
                $table->string('notify', 20)->default('off');
                $table->timestamps();

                $table->foreign('job_request_id')->references('id')->on('job_requests')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('seller_view_jobs')) {
            Schema::create('seller_view_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_post_id')->index();
                $table->unsignedBigInteger('seller_id')->index();
                $table->timestamps();

                $table->foreign('job_post_id')->references('id')->on('buyer_jobs')->onDelete('cascade');
                $table->foreign('seller_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['job_post_id', 'seller_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seller_view_jobs');
        Schema::dropIfExists('job_request_conversations');
        Schema::dropIfExists('job_requests');
        Schema::dropIfExists('buyer_jobs');
    }
}
