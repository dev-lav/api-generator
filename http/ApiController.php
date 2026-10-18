<?php namespace AhmadFatoni\ApiGenerator\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Database\QueryException;
use Tailor\Models\EntryRecord;
use Tailor\Models\GlobalRecord;
use Tailor\Classes\BlueprintIndexer;
use October\Rain\Exception\ValidationException;
use AhmadFatoni\ApiGenerator\Classes\ApiRegistry;
use AhmadFatoni\ApiGenerator\Helpers\Helpers;

/**
 * ApiController serves the CRUD endpoints of every API, the API definition
 * is taken from the "apigenerator" key of the route action.
 */
class ApiController extends Controller
{
    /**
     * @var Helpers helpers
     */
    protected $helpers;

    /**
     * @var array|null api definition for the current route
     */
    protected $api;

    public function __construct(Helpers $helpers)
    {
        $this->helpers = $helpers;
    }

    public function index(Request $request)
    {
        if (!$this->resolveApi($request)) {
            return $this->unavailable();
        }

        $data = $this->newQuery()->get()->toArray();

        return $this->helpers->apiArrayResponseBuilder(200, 'success', $data);
    }

    public function show(Request $request, $id)
    {
        if (!$this->resolveApi($request)) {
            return $this->unavailable();
        }

        $data = $this->newQuery()->find($id);

        if (!$data) {
            return $this->notFound($id);
        }

        return $this->helpers->apiArrayResponseBuilder(200, 'success', [$data->toArray()]);
    }

    public function store(Request $request)
    {
        if (!$this->resolveApi($request)) {
            return $this->unavailable();
        }

        return $this->saveModel($this->newModel(), $request, 201, 'created');
    }

    public function update(Request $request, $id)
    {
        if (!$this->resolveApi($request)) {
            return $this->unavailable();
        }

        $model = $this->newModel()->newQuery()->find($id);

        if (!$model) {
            return $this->notFound($id);
        }

        return $this->saveModel($model, $request, 200, 'success');
    }

    public function destroy(Request $request, $id)
    {
        if (!$this->resolveApi($request)) {
            return $this->unavailable();
        }

        $model = $this->newModel()->newQuery()->find($id);

        if (!$model) {
            return $this->notFound($id);
        }

        $model->delete();

        return $this->helpers->apiArrayResponseBuilder(200, 'success', ['message' => 'Data has been deleted successfully.']);
    }

    /**
     * resolveApi loads the API definition and checks its model is still available
     */
    protected function resolveApi(Request $request): bool
    {
        $this->api = ApiRegistry::find($request->route()->getAction('apigenerator'));

        if (!$this->api) {
            return false;
        }

        if ($handle = $this->api['tailor_handle']) {
            $indexer = BlueprintIndexer::instance();
            return $indexer->findSectionByHandle($handle) || $indexer->findGlobalByHandle($handle);
        }

        return class_exists($this->api['model']) &&
            is_subclass_of($this->api['model'], \Illuminate\Database\Eloquent\Model::class);
    }

    /**
     * newModel returns an empty model instance
     */
    protected function newModel()
    {
        if (!$handle = $this->api['tailor_handle']) {
            return new $this->api['model'];
        }

        if (BlueprintIndexer::instance()->findGlobalByHandle($handle)) {
            return GlobalRecord::inGlobal($handle);
        }

        return EntryRecord::inSection($handle);
    }

    /**
     * newQuery applies the custom condition to the query
     */
    protected function newQuery()
    {
        $query = $this->newModel()->newQuery();

        foreach ($this->api['relations'] as $name => $columns) {
            $query->with([$name => function ($query) use ($columns) {
                if ($columns) {
                    $query->select($columns);
                }
            }]);
        }

        if ($this->api['fillable']) {
            $query->select($this->api['fillable']);
        }

        return $query;
    }

    /**
     * saveModel fills the model from the request and saves it
     */
    protected function saveModel($model, Request $request, $statusCode, $message)
    {
        try {
            $model->forceFill($request->except(['id']));
            $model->save();
        }
        catch (ValidationException $ex) {
            return $this->helpers->apiArrayResponseBuilder(400, 'fail', $ex->getErrors()->toArray());
        }
        catch (QueryException $ex) {
            return $this->helpers->apiArrayResponseBuilder(400, 'bad request', ['error' => 'Data failed to save, please check the fields sent.']);
        }

        return $this->helpers->apiArrayResponseBuilder($statusCode, $message, ['id' => $model->getKey()]);
    }

    /**
     * notFound
     */
    protected function notFound($id)
    {
        return $this->helpers->apiArrayResponseBuilder(404, 'not found', ['error' => 'Resource id=' . $id . ' could not be found']);
    }

    /**
     * unavailable is returned when the model of the API no longer exists
     */
    protected function unavailable()
    {
        return $this->helpers->apiArrayResponseBuilder(500, 'error', ['error' => 'The model for this API is not available.']);
    }
}
