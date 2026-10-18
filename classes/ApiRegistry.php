<?php namespace AhmadFatoni\ApiGenerator\Classes;

use Cache;
use Exception;
use AhmadFatoni\ApiGenerator\Models\ApiGenerator;

/**
 * ApiRegistry provides the saved API definitions to the router and the API controller,
 * the definitions are cached and the cache is cleared when an API is saved or deleted.
 */
class ApiRegistry
{
    /**
     * @var string cacheKey
     */
    const CACHE_KEY = 'ahmadfatoni.apigenerator.apis';

    /**
     * all returns the API definitions keyed by id
     */
    public static function all(): array
    {
        if (($apis = Cache::get(self::CACHE_KEY)) !== null) {
            return $apis;
        }

        try {
            $apis = static::load();
        }
        catch (Exception $ex) {
            // The table does not exist yet, for example before the plugin is migrated
            return [];
        }

        Cache::forever(self::CACHE_KEY, $apis);

        return $apis;
    }

    /**
     * find returns a single API definition
     */
    public static function find($id): ?array
    {
        return static::all()[$id] ?? null;
    }

    /**
     * clearCache
     */
    public static function clearCache()
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * load the definitions from the database
     */
    protected static function load(): array
    {
        $apis = [];

        foreach (ApiGenerator::orderBy('id')->get() as $api) {
            [$fillable, $relations] = static::parseCustomFormat($api->custom_format);

            $apis[$api->id] = [
                'id' => $api->id,
                'endpoint' => trim($api->endpoint, '/'),
                'model' => $api->model,
                'tailor_handle' => $api->getBlueprintHandle(),
                'middleware' => $api->getRouteMiddleware(),
                'fillable' => $fillable,
                'relations' => $relations,
            ];
        }

        return $apis;
    }

    /**
     * parseCustomFormat converts the custom condition JSON to column lists
     * @return array [fillable, relations]
     */
    protected static function parseCustomFormat($customFormat): array
    {
        $fillable = [];
        $relations = [];

        $json = json_decode(str_replace('\t', '', (string) $customFormat));
        if (!$json) {
            return [$fillable, $relations];
        }

        if (!empty($json->fillable)) {
            $fillable = static::splitColumns($json->fillable);
        }

        if (!empty($json->relation) && is_array($json->relation)) {
            foreach ($json->relation as $relation) {
                if (empty($relation->name)) {
                    continue;
                }

                $relations[trim($relation->name)] = static::splitColumns($relation->fillable ?? '');
            }
        }

        return [$fillable, $relations];
    }

    /**
     * splitColumns
     */
    protected static function splitColumns($columns): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $columns))));
    }
}
