<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGroupIdToTagihanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->string('group_id')->nullable()->after('kode_tagihan');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tagihan', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
}
