<?php namespace AhmadFatoni\ApiGenerator\Controllers\API;

use Cms\Classes\Controller;
use BackendMenu;

use Illuminate\Http\Request;
use {{model}};
class ApiListController extends Controller
{
	protected ${{modelname}};

    public function __construct({{modelname}} ${{modelname}})
    {
        parent::__construct();
        $this->{{modelname}} = ${{modelname}};
    }

    public static function getAfterFilters() {return [];}
    public static function getBeforeFilters() {return [];}
    public static function getMiddleware() {return [];}
    public function callAction($method, $parameters=false) {
        return call_user_func_array(array($this, $method), $parameters);
    }
    
    // public function create(Request $request){

    // 	$arr = $request->all();

    // 	while ( $data = current($arr)) {
    // 		$this->
    // 	}
    // 	return json_encode($this->{{modelname}}->store($request));

    // }
}
