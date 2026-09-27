<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLiveChatTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('live_chat_conversations')) {
            Schema::create('live_chat_conversations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('buyer_id');
                $table->unsignedBigInteger('seller_id');
                $table->text('last_message')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->integer('buyer_unread_count')->default(0);
                $table->integer('seller_unread_count')->default(0);
                $table->smallInteger('status')->default(1); // 1 = active, 0 = archived/muted
                $table->timestamps();

                $table->unique(['buyer_id', 'seller_id'], 'uq_buyer_seller_conversation');
                $table->index('buyer_id');
                $table->index('seller_id');
                $table->index('last_message_at');
            });
        }

        if (!Schema::hasTable('live_chat_messages')) {
            Schema::create('live_chat_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->unsignedBigInteger('from_user');
                $table->unsignedBigInteger('to_user');
                $table->unsignedBigInteger('buyer_id');
                $table->unsignedBigInteger('seller_id');
                $table->text('message')->nullable();
                $table->string('image', 255)->nullable();
                $table->string('attachment_type', 50)->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index('conversation_id');
                $table->index('from_user');
                $table->index('to_user');
                $table->index('buyer_id');
                $table->index('seller_id');
                $table->index(['conversation_id', 'created_at']);
                $table->index(['from_user', 'to_user']);
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
        Schema::dropIfExists('live_chat_messages');
        Schema::dropIfExists('live_chat_conversations');
    }
}
