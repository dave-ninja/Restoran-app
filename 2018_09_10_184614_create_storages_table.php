<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStoragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('storages', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('unit_id');
            $table->integer('cat_id');
	        $table->integer('parent_id');
            $table->string('product_name');
            $table->string('product_price');
            $table->string('qty');
            $table->string('total_qty');
            $table->boolean('movements');
            $table->string('vendor_name');
            $table->string('vendor_phone');
            $table->text('comment');
            $table->string('picture')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('storages');
    }
}
