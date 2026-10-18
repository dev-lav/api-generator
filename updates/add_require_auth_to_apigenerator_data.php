<?php namespace AhmadFatoni\ApiGenerator\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class AddRequireAuthToApigeneratorData extends Migration
{
    public function up()
    {
        Schema::table('ahmadfatoni_apigenerator_data', function($table)
        {
            $table->boolean('require_auth')->default(false);
        });
    }

    public function down()
    {
        Schema::table('ahmadfatoni_apigenerator_data', function($table)
        {
            $table->dropColumn('require_auth');
        });
    }
}
