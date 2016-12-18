<?php namespace AhmadFatoni\ApiGenerator\Models;

use Model, Log;
use RainLab\Builder\Classes\ComponentHelper;

/**
 * Model
 */
class ApiGenerator extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /*
     * Validation
     */
    public $rules = [
        'name'          => 'required|unique:ahmadfatoni_apigenerator_data,name|regex:/^[\pL\s\-]+$/u',
        'endpoint'      => 'required|unique:ahmadfatoni_apigenerator_data,endpoint',
        'custom_format' => 'json'
    ];

    public $customMessages = [
        'custom_format.json' => 'Invalid Json Format Custom Condition'
    ];

    /*
     * Disable timestamps by default.
     * Remove this line if timestamps are defined in the database table.
     */
    public $timestamps = false;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ahmadfatoni_apigenerator_data';

    /**
     * get model List
     * @return [type] [description]
     */
    public function getModelOptions(){
        
        return ComponentHelper::instance()->listGlobalModels();
    }

    /**
     * [setCustomFormatAttribute description]
     * @param [type] $value [description]
     */
    public function setCustomFormatAttribute($value){

        $json   = str_replace('\t', '', $value);
        $json   = json_decode($json);

        if( $json != null){

            if( ! isset($json->fillable) AND ! isset($json->relation) ){

                return $this->attributes['custom_format'] = 'invalid format';

            }

            if( isset($json->relation) AND $json->relation != null ){
                foreach ($json->relation as $key) {
                    if( !isset($key->name) OR $key->name == null ){
                        return $this->attributes['custom_format'] = 'invalid format';
                    }
                }
            }
        }
        
        return $this->attributes['custom_format'] = $value;
        
    }
    
}