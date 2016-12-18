<?php

Route::post('fatoni/generate/api', array('as' => 'fatoni.generate.api', 'uses' => 'AhmadFatoni\ApiGenerator\Controllers\ApiGeneratorController@generateApi'));
Route::post('fatoni/update/api/{id}', array('as' => 'fatoni.update.api', 'uses' => 'AhmadFatoni\ApiGenerator\Controllers\ApiGeneratorController@updateApi'));
Route::get('fatoni/delete/api/{id}', array('as' => 'fatoni.delete.api', 'uses' => 'AhmadFatoni\ApiGenerator\Controllers\ApiGeneratorController@deleteApi'));

Route::resource('halo', 'AhmadFatoni\ApiGenerator\Controllers\API\haloController', ['except' => ['destroy', 'create', 'edit']]);
Route::get('halo/{id}/delete', ['as' => 'halo.delete', 'uses' => 'AhmadFatoni\ApiGenerator\Controllers\API\haloController@destroy']);