<?php namespace AhmadFatoni\ApiGenerator\Classes;

use File;
use AhmadFatoni\ApiGenerator\Models\ApiGenerator;

/**
 * ApiBuilder writes the API controllers and routes.php from the templates
 */
class ApiBuilder
{
    /**
     * @var string pluginPath
     */
    protected $pluginPath;

    /**
     * __construct
     */
    public function __construct()
    {
        $this->pluginPath = plugins_path('ahmadfatoni/apigenerator');
    }

    /**
     * build writes the controller for an API and regenerates the routes
     */
    public function build(ApiGenerator $api, ?string $oldName = null)
    {
        if ($oldName && $api->getControllerName($oldName) !== $api->getControllerName()) {
            $this->deleteController($api->getControllerName($oldName));
        }

        File::ensureDirectoryExists($this->getControllerPath());

        File::put(
            $this->getControllerFile($api->getControllerName()),
            $this->compileController($api)
        );

        $this->buildRoutes();
    }

    /**
     * remove deletes the controller for an API and regenerates the routes
     */
    public function remove(ApiGenerator $api)
    {
        $this->deleteController($api->getControllerName());

        $this->buildRoutes();
    }

    /**
     * buildRoutes regenerates routes.php from all saved APIs
     */
    public function buildRoutes()
    {
        $routes = '';

        foreach (ApiGenerator::orderBy('id')->get() as $api) {
            $routes .= $this->parseTemplate('route.dot', [
                'endpoint' => trim($api->endpoint, '/'),
                'controllername' => $api->getControllerName(),
            ]);
        }

        File::put(
            $this->pluginPath . '/routes.php',
            $this->parseTemplate('routes.dot', ['route' => $routes])
        );
    }

    /**
     * compileController
     */
    protected function compileController(ApiGenerator $api): string
    {
        [$fillable, $relations] = $this->parseCustomFormat($api->custom_format);

        return $this->parseTemplate('controller.dot', [
            'controllername' => $api->getControllerName(),
            'model' => $api->model,
            'newmodel' => $this->compileNewModel($api),
            'fillable' => $this->exportArray($fillable),
            'relations' => $this->exportArray($relations),
        ]);
    }

    /**
     * compileNewModel returns PHP code that creates an empty model instance
     */
    protected function compileNewModel(ApiGenerator $api): string
    {
        if (!$api->isTailor()) {
            return 'new \\' . ltrim($api->model, '\\');
        }

        $handle = var_export($api->getBlueprintHandle(), true);

        if ($this->isTailorGlobal($api->getBlueprintHandle())) {
            return '\\Tailor\\Models\\GlobalRecord::inGlobal(' . $handle . ')';
        }

        return '\\Tailor\\Models\\EntryRecord::inSection(' . $handle . ')';
    }

    /**
     * isTailorGlobal
     */
    protected function isTailorGlobal(string $handle): bool
    {
        return (bool) \Tailor\Classes\BlueprintIndexer::instance()->findGlobalByHandle($handle);
    }

    /**
     * parseCustomFormat converts the custom condition JSON to column lists
     * @return array [fillable, relations]
     */
    protected function parseCustomFormat($customFormat): array
    {
        $fillable = [];
        $relations = [];

        $json = json_decode(str_replace('\t', '', (string) $customFormat));
        if (!$json) {
            return [$fillable, $relations];
        }

        if (!empty($json->fillable)) {
            $fillable = $this->splitColumns($json->fillable);
        }

        if (!empty($json->relation) && is_array($json->relation)) {
            foreach ($json->relation as $relation) {
                if (empty($relation->name)) {
                    continue;
                }

                $relations[trim($relation->name)] = $this->splitColumns($relation->fillable ?? '');
            }
        }

        return [$fillable, $relations];
    }

    /**
     * splitColumns
     */
    protected function splitColumns($columns): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $columns))));
    }

    /**
     * exportArray returns a short array PHP literal
     */
    protected function exportArray(array $value): string
    {
        $items = [];
        $isList = array_is_list($value);

        foreach ($value as $key => $item) {
            $item = is_array($item) ? $this->exportArray($item) : var_export($item, true);
            $items[] = $isList ? $item : var_export($key, true) . ' => ' . $item;
        }

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * parseTemplate
     */
    protected function parseTemplate(string $template, array $vars): string
    {
        $contents = File::get($this->pluginPath . '/template/' . $template);

        foreach ($vars as $key => $value) {
            $contents = str_replace('{{' . $key . '}}', $value, $contents);
        }

        return $contents;
    }

    /**
     * deleteController
     */
    protected function deleteController(string $controllerName)
    {
        $file = $this->getControllerFile($controllerName);

        if (File::exists($file)) {
            File::delete($file);
        }
    }

    /**
     * getControllerPath
     */
    protected function getControllerPath(): string
    {
        return $this->pluginPath . '/controllers/api';
    }

    /**
     * getControllerFile
     */
    protected function getControllerFile(string $controllerName): string
    {
        return $this->getControllerPath() . '/' . $controllerName . 'Controller.php';
    }
}
