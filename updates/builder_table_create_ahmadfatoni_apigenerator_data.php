<?php namespace AhmadFatoni\ApiGenerator\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateAhmadfatoniApigeneratorData extends Migration
{
    public function up()
    {
        Schema::create('ahmadfatoni_apigenerator_data', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');
            $table->string('endpoint');
            $table->string('model');
            $table->string('description')->nullable();
            $table->text('custom_format')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('ahmadfatoni_apigenerator_data');
    }
}
