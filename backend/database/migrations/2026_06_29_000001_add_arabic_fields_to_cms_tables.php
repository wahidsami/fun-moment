<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('blogs', function (Blueprint $table) {
            if (!Schema::hasColumn('blogs', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (!Schema::hasColumn('blogs', 'blog_content_ar')) {
                $table->longText('blog_content_ar')->nullable()->after('blog_content');
            }
        });

        Schema::table('pages', function (Blueprint $table) {
            if (!Schema::hasColumn('pages', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (!Schema::hasColumn('pages', 'page_content_ar')) {
                $table->longText('page_content_ar')->nullable()->after('page_content');
            }
        });

        Schema::table('menus', function (Blueprint $table) {
            if (!Schema::hasColumn('menus', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
        });

        Schema::table('widgets', function (Blueprint $table) {
            if (!Schema::hasColumn('widgets', 'widget_title_ar')) {
                $table->text('widget_title_ar')->nullable()->after('widget_name');
            }
            if (!Schema::hasColumn('widgets', 'widget_content_ar')) {
                $table->longText('widget_content_ar')->nullable()->after('widget_content');
            }
        });
    }

    public function down()
    {
        Schema::table('widgets', function (Blueprint $table) {
            if (Schema::hasColumn('widgets', 'widget_content_ar')) {
                $table->dropColumn('widget_content_ar');
            }
            if (Schema::hasColumn('widgets', 'widget_title_ar')) {
                $table->dropColumn('widget_title_ar');
            }
        });

        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'title_ar')) {
                $table->dropColumn('title_ar');
            }
        });

        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'page_content_ar')) {
                $table->dropColumn('page_content_ar');
            }
            if (Schema::hasColumn('pages', 'title_ar')) {
                $table->dropColumn('title_ar');
            }
        });

        Schema::table('blogs', function (Blueprint $table) {
            if (Schema::hasColumn('blogs', 'blog_content_ar')) {
                $table->dropColumn('blog_content_ar');
            }
            if (Schema::hasColumn('blogs', 'title_ar')) {
                $table->dropColumn('title_ar');
            }
        });
    }
};
