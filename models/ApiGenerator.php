<?php namespace AhmadFatoni\ApiGenerator\Models;

use File;
use Model;
use ReflectionClass;
use ValidationException;
use AhmadFatoni\ApiGenerator\Classes\ApiBuilder;
use Tailor\Classes\BlueprintIndexer;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * ApiGenerator stores an API definition, the controller and routes
 * are (re)generated automatically after save and delete.
 */
class ApiGenerator extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * Prefix used for Tailor blueprint options, e.g. tailor:Blog\Category
     */
    const TAILOR_PREFIX = 'tailor:';

    /*
     * Validation
     */
    public $rules = [
        'name'          => 'required|unique:ahmadfatoni_apigenerator_data,name|regex:/^[A-Za-z][A-Za-z0-9\s]*$/',
        'endpoint'      => 'required|unique:ahmadfatoni_apigenerator_data,endpoint|regex:/^\/?[A-Za-z0-9\-_\/]+$/',
        'model'         => 'required',
        'custom_format' => 'nullable|json'
    ];

    public $customMessages = [
        'name.regex'         => 'API Name may only contain letters, numbers and spaces, and must start with a letter.',
        'endpoint.regex'     => 'Base Endpoint may only contain letters, numbers, dashes, underscores and slashes.',
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
     * beforeValidate only allows models listed in the dropdown, since the
     * value is written into generated PHP code
     */
    public function beforeValidate()
    {
        if ($this->model && !array_key_exists($this->model, $this->getModelOptions())) {
            throw new ValidationException(['model' => 'Please select a valid model.']);
        }

        if ($this->require_auth && !static::hasUserPlugin()) {
            throw new ValidationException(['require_auth' => 'Authentication requires the RainLab.User plugin.']);
        }
    }

    /**
     * afterSave regenerates the controller and routes
     */
    public function afterSave()
    {
        (new ApiBuilder)->build($this, $this->getOriginal('name'));
    }

    /**
     * afterDelete removes the controller and regenerates routes
     */
    public function afterDelete()
    {
        (new ApiBuilder)->remove($this);
    }

    /**
     * getControllerName returns the class name prefix for the generated controller
     */
    public function getControllerName($name = null): string
    {
        return str_replace(' ', '', $name ?? $this->name);
    }

    /**
     * isTailor returns true if the model is a Tailor blueprint
     */
    public function isTailor(): bool
    {
        return strpos((string) $this->model, self::TAILOR_PREFIX) === 0;
    }

    /**
     * getBlueprintHandle returns the Tailor blueprint handle
     */
    public function getBlueprintHandle(): ?string
    {
        return $this->isTailor() ? substr($this->model, strlen(self::TAILOR_PREFIX)) : null;
    }

    /**
     * getRouteMiddleware returns the middleware applied to the generated routes
     */
    public function getRouteMiddleware(): array
    {
        if ($this->require_auth) {
            return [\AhmadFatoni\ApiGenerator\Http\ApiAuthMiddleware::class];
        }

        return [];
    }

    /**
     * hasUserPlugin returns true if RainLab.User is installed and enabled
     */
    public static function hasUserPlugin(): bool
    {
        return \System\Classes\PluginManager::instance()->exists('RainLab.User');
    }


    /**
     * get model List
     * @return array
     */
    public function getModelOptions()
    {
        return $this->getPluginModels() + $this->getTailorBlueprints();
    }

    /**
     * getPluginModels scans plugin model directories for Eloquent models
     * @return array
     */
    protected function getPluginModels()
    {
        $models = [];

        foreach (File::directories(plugins_path()) as $vendorPath) {
            $vendor = basename($vendorPath);

            foreach (File::directories($vendorPath) as $pluginPath) {
                $plugin = basename($pluginPath);
                $modelsPath = $pluginPath . '/models';

                if (!File::isDirectory($modelsPath)) {
                    continue;
                }

                foreach (File::files($modelsPath) as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $class = $vendor . '\\' . $plugin . '\\Models\\' . $file->getFilenameWithoutExtension();

                    if (!class_exists($class) || !is_subclass_of($class, EloquentModel::class)) {
                        continue;
                    }

                    // Normalize casing from the real class name
                    $reflection = new ReflectionClass($class);
                    $class = $reflection->getName();

                    if ($class === static::class || $reflection->isAbstract()) {
                        continue;
                    }

                    $models[$class] = $class;
                }
            }
        }

        ksort($models);

        return $models;
    }

    /**
     * getTailorBlueprints returns Tailor sections and globals
     * @return array
     */
    protected function getTailorBlueprints()
    {
        $blueprints = [];
        $indexer = BlueprintIndexer::instance();

        foreach ($indexer->listSections() as $blueprint) {
            $blueprints[self::TAILOR_PREFIX . $blueprint->handle] = "Tailor: {$blueprint->handle} ({$blueprint->type})";
        }

        foreach ($indexer->listGlobals() as $blueprint) {
            $blueprints[self::TAILOR_PREFIX . $blueprint->handle] = "Tailor: {$blueprint->handle} (global)";
        }

        ksort($blueprints);

        return $blueprints;
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
