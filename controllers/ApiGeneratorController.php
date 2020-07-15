<?php namespace AhmadFatoni\ApiGenerator\Controllers;

use Backend\Classes\Controller;
use AhmadFatoni\ApiGenerator\Models\ApiGenerator;
use BackendMenu;
use Backend;

use Illuminate\Http\Request;
use Illuminate\Filesystem\Filesystem;
use Redirect;
use Flash;

class ApiGeneratorController extends Controller
{
    public $implement = ['Backend\Behaviors\ListController','Backend\Behaviors\FormController','Backend\Behaviors\ReorderController'];
    
    public $listConfig      = 'config_list.yaml';
    public $formConfig      = 'config_form.yaml';
    public $reorderConfig   = 'config_reorder.yaml';
    protected $path         = "/api/";
    private $homePage       = 'ahmadfatoni/apigenerator/apigeneratorcontroller';
    protected $files;
    public $requiredPermissions = ['ahmadfatoni.apigenerator.manage'];

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        BackendMenu::setContext('AhmadFatoni.ApiGenerator', 'api-generator');
        $this->files         = $files;
    }

    /**
     * delete selected data (multiple delete)
     * @return [type] [description]
     */
    public function index_onDelete()
    {
        if (($checkedIds = post('checked')) && is_array($checkedIds) && count($checkedIds)) {

            foreach ($checkedIds as $id) {
                if ((!$item = ApiGenerator::find($id)))
                    continue;
                $name = $item->name;
                if($item->delete()){
                    $this->deleteApi($name);
                }
            }

            Flash::success('Successfully deleted those data.');
        }

        return $this->listRefresh();
    }

    /**
     * generate API
     * @param  Request $request [description]
     * @return [type]           [description]
     */
    public function generateApi(Request $request){

    	$data['model'] 			= $request->model;
    	$modelname 				= explode("\\", $request->model);
    	$modelname  			= $modelname[count($modelname)-1];
    	$data['modelname']		= $modelname;
    	$data['controllername']	= str_replace(" ", "", $request->name);
    	$data['endpoint']		= $request->endpoint;
        $data['custom_format']  = $request->custom_format;

        if( strpos($data['controllername'], ".") OR  strpos($data['controllername'], "/") ){

            Flash::success('Failed to create data, invalid API name.');
            return Redirect::to( Backend::url($this->homePage));
        
        }
        
        if( isset($request->id) ){
            $this->deleteApi($request->oldname, 'false');
        }

    	$this->files->put(__DIR__ . $this->path . $data['controllername'].'Controller.php', $this->compile($data));

    	$this->files->put(__DIR__ . '/'.'../routes.php', $this->compileRoute($data));

    	return Redirect::to( Backend::url($this->homePage));

    }

    /**
     * delete available API
     * @param  [type] $name     [description]
     * @param  [type] $redirect [description]
     * @return [type]           [description]
     */
    public function deleteApi($name, $redirect = null){

        $fileLocation = __DIR__ . $this->path.$name;
        $fileLocation = str_replace(".", "", $fileLocation);

        if( ! file_exists($fileLocation.'Controller.php') ){

            Flash::success('Failed to delete data, invalid file location.');
            return Redirect::to( Backend::url($this->homePage));

        }

        if( strpos( strtolower($name), 'apigenerator' ) === false){
            $data = [];

            //generate new route
            $this->files->put(__DIR__ . '/'.'../routes.php', $this->compileRoute($data));
            
            //remove controller
            if (file_exists( __DIR__ . $this->path.$name.'Controller.php' )) {

                unlink(__DIR__ . $this->path.$name.'Controller.php');

            }

            if( $redirect != null ){
                return 'success without redirect';
            }
        }

        return Redirect::to( Backend::url($this->homePage));

    }

    public function updateApi($name){

    }

    /**
     * compile controller from template
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function compile($data){
        if( $data['custom_format'] != ''){

            $template = $this->files->get(__DIR__ .'/../template/customcontroller.dot');
            $template = $this->replaceAttribute($template, $data);
            $template = $this->replaceCustomAttribute($template, $data);
        }else{
        	$template = $this->files->get(__DIR__ .'/../template/controller.dot');
    		$template = $this->replaceAttribute($template, $data);
        }
		return $template;
    }

    /**
     * replace attribute
     * @param  [type] $template [description]
     * @param  [type] $data     [description]
     * @return [type]           [description]
     */
    public function replaceAttribute($template, $data){
    	if( isset( $data['model'] ) ){
    		$template = str_replace('{{model}}', $data['model'], $template);
    	}
        $template = str_replace('{{modelname}}', $data['modelname'], $template);
        $template = str_replace('{{controllername}}', $data['controllername'], $template);
        return $template;	
    }

    /**
     * replace custom attribute
     * @param  [type] $template [description]
     * @param  [type] $data     [description]
     * @return [type]           [description]
     */
    public function replaceCustomAttribute($template, $data){

        $arr            = str_replace('\t', '', $data['custom_format']);
        $arr            = json_decode($arr);        
        $select         = str_replace('<br />', '', $this->compileOpenIndexFunction($data['modelname'], 'index'));
        $show           = str_replace('<br />', '', $this->compileOpenIndexFunction($data['modelname'], 'show'));
        $fillableParent = '';

        if( isset($arr->fillable) AND $arr->fillable != null ) {
            $fillableParent = $this->compileFillableParent($arr->fillable);
        }

        if( isset($arr->relation) AND $arr->relation != null AND is_array($arr->relation) AND count($arr->relation) > 0) {
            $select .= str_replace('<br />', '', $this->compileFillableChild($arr->relation));
            $show   .= str_replace('<br />', '', $this->compileFillableChild($arr->relation));
        }
        
        $select .= "->select(".$fillableParent.")";
        $show   .= "->select(".$fillableParent.")->where('id', '=', \$id)->first();";

        ( $fillableParent != '') ? $select .= "->get()->toArray();" : $select .= "->toArray();" ;

        $closeFunction = str_replace('<br />', '', nl2br(
        "
        return \$this->helpers->apiArrayResponseBuilder(200, 'success', \$data);
    }"));
        $select .= $closeFunction;
        $show   .= $closeFunction;

        $template = str_replace('{{select}}', $select, $template);
        $template = str_replace('{{show}}', $show, $template);

        return $template;
    }

    public function compileOpenIndexFunction($modelname, $type){
        if( $type == 'index'){
            return nl2br("
    public function index(){ 
        \$data = \$this->".$modelname);
        }else{
            return nl2br("
    public function show(\$id){ 
        \$data = \$this->".$modelname);
        }

    }

    public function compileFillableParent($fillable){

        $fillableParentArr  = explode(",", $fillable);
        $fillableParent     = '';

        foreach ($fillableParentArr as $key) {

            $fillableParent .= ",'".$key."'";

        }

        $fillableParent = substr_replace($fillableParent, '', 0 , 1);

        return $fillableParent;
    }

    public function compileFillableChild($fillable){
        
        $select = "->with(array(";

        foreach ($fillable as $key) {
            
            $fillableChild      = "";

            if( isset($key->fillable) AND $key->fillable != null ){
                $fillableChildArr   = explode(",", $key->fillable);
                
                    
                foreach ($fillableChildArr as $key2) {

                    $fillableChild .= ",'".$key2."'";

                }

                $fillableChild = substr_replace($fillableChild, '', 0 , 1);
            }
            
            $select .= nl2br(
            "
            '".$key->name."'=>function(\$query){
                \$query->select(".$fillableChild.");
            },");

        }

        $select .= " ))";
        
        return $select;
    }

    public function compileRoute($data){

    	$oldData 	= ApiGenerator::all();
    	$routeList	= "";

    	if( count($oldData) > 0 ){

	    	$routeList .= $this->parseRouteOldData($oldData, $data);

	    }

        if( count($data) > 0 ){
	        $data['modelname'] = $data['endpoint'];
            if( $data['modelname'][0] == "/" ){
                $data['modelname'] = substr_replace($data['modelname'], '', 0 , 1);
            }
        	$routeList 	.= $this->parseRoute($data);
        }

    	$route = $this->files->get(__DIR__ .'/../template/routes.dot');
    	$route = str_replace('{{route}}', $routeList, $route);

    	return $route;

    }

    public function parseRouteOldData($oldData, $data = null){
        
        $routeList = "";

        if( count($data) == 0 ) $data['modelname']='';

        foreach ( $oldData as $key ) {

            $modelname              = explode("\\", $key->model);
            $modelname              = $modelname[count($modelname)-1];
            $old['modelname']       = $key->endpoint;
            $old['controllername']  = $key->name;

            if( $data['modelname'] != $modelname ){

                if( $old['modelname'][0] == "/" ){
                    $old['modelname'] = substr_replace($old['modelname'], '', 0 , 1);
                }

                $routeList .= $this->parseRoute($old);
            }   
        }

        return $routeList;

    }

    public function parseRoute($data){

    	$template = $this->files->get(__DIR__ .'/../template/route.dot');
		$template = $this->replaceAttribute($template, $data);
		return $template;
    }


    public static function getAfterFilters() {return [];}
    public static function getBeforeFilters() {return [];}
    public function callAction($method, $parameters=false) {
        return call_user_func_array(array($this, $method), $parameters);
    }
}
