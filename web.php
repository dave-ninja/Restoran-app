<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();
Route::get('/user/verify/{token}', 'Auth\RegisterController@verifyUser');
Route::get( 'storage/app/{userName}/{objName}/{filename}', function( $userName, $objName, $filename ) {
	if(Auth::check()) {
		if(Auth::user()->name == $userName){
			$path = storage_path( 'app/' . $userName . '/' . $objName . '/' . $filename );
		}
	} else {
		$path = null;
		abort( 404 );
	}
	if ( ! File::exists( $path ) ) {
		abort( 404 );
	}
	$file     = File::get( $path );
	$type     = File::mimeType( $path );
	$response = Response::make( $file, 200 );
	$response->header( "Content-Type", $type );

	return $response;
} );
Route::get( 'storage/app/{userName}/workers/{objName}/{filename}', function( $userName, $objName, $filename ) {
	if(Auth::check()) {
		if(Auth::user()->name == $userName){
			$path = storage_path( 'app/' . $userName . '/workers/' . $objName . '/' . $filename );
		}
	} else {
		$path = null;
		abort( 404 );
	}
	if ( ! File::exists( $path ) ) {
		abort( 404 );
	}
	$file     = File::get( $path );
	$type     = File::mimeType( $path );
	$response = Response::make( $file, 200 );
	$response->header( "Content-Type", $type );

	return $response;
} );

Route::get('/home', 'HomeController@index')->name('home');

//Route::get('/my-account', 'MyAccountController@index')->name('my-account');

Route::group(['prefix' => 'user'],function () {//, 'middleware' => ['role:admin|owner']]
	Route::get( '/', 'UserController@users' )->name( 'users' );
	Route::get( 'create', 'UserController@create' )->name( 'create-user' );
	Route::post('create', 'UserController@createAction')->name('create-user-action');
	Route::get('/{id}', 'UserController@user')->name('user');
	Route::post('/{id}', 'UserController@updateAction')->name('update-user-action')->where('id', '[0-9]+');
});

Route::group(['prefix' => 'my-account'],function () {//, 'middleware' => ['role:admin|owner']]
	Route::get( '/', 'MyAccountController@index' )->name( 'my-account' );
	Route::post('/', 'MyAccountController@updateAction')->name('update-my-info-action');
});

Route::prefix('storage')->group(function () {
	Route::get('categories', 'StorageController@categories')->name('storage-categories');
	Route::get('category/{cat_id}', 'StorageController@category')->name('storage-category');
	Route::get('product/{pr_id}', 'StorageController@product')->name('product-storage');
	Route::get('product-out', 'StorageController@productOut')->name('product-storage-out');
	Route::get('product-out/{id}/{cat_id?}', 'StorageController@productOutObject')->name('product-storage-out-object');
	Route::get('product-in', 'StorageController@productIn')->name('product-storage-in');

	Route::post('create-category', 'StorageController@createCategoryAction')->name('create-storage-category');
	Route::post('create-product', 'StorageController@createProductAction')->name('create-storage-product');
	Route::post('update-product/{id}', 'StorageController@updateProductAction')->name('update-storage-product');
	Route::post('get-product-by-keyup', 'StorageController@getProductByKeyup');
	Route::post('product-out', 'StorageController@productOutAction')->name('product-storage-out');
	Route::post('update-ins-product', 'StorageController@updateInsProductAction')->name('update-product-storage-in');
});

Route::prefix('worker')->group(function () {
	Route::get('/create', 'WorkerController@create')->name('create-worker');
	Route::post('/create-action', 'WorkerController@createAction')->name('create-worker-action');
	Route::get('/', 'WorkerController@workers')->name('workers');
	Route::get('/{id}', 'WorkerController@worker')->name('worker')->where('id', '[0-9]+');
	Route::post('/{id}', 'WorkerController@workerAction')->name('update-worker-action')->where('id', '[0-9]+');

});

Route::prefix('object')->group(function () {
	Route::get('/', 'ObjectController@objects')->name('objects');
	Route::get('create', 'ObjectController@create')->name('create-object');
	Route::post('create', 'ObjectController@createAction')->name('create-object-action');
	Route::get('/{id}', 'ObjectController@object')->name('object');
	Route::post('/{id}', 'ObjectController@updateAction')->name('update-object-action')->where('id', '[0-9]+');
	Route::get('/{obj_id}/{menu_id}', 'ObjectController@menu')->name('object-menu')->where('obj_id', '[0-9]+')->where('menu_id', '[0-9]+');
	Route::post('create-menu', 'ObjectController@createMenuAction')->name('create-menu-action');
	Route::post('/add-worker', 'ObjectController@createWorkerAction'); // ajax
});

Route::prefix('profession')->group(function () {
	Route::get( '/', 'ProfessionController@professions' )->name( 'professions' );
	Route::get( 'create', 'ProfessionController@create' )->name( 'create-profession' );
	Route::post('create', 'ProfessionController@createAction')->name('create-profession-action');
	Route::get('/{id}', 'ProfessionController@profession')->name('profession');
	Route::post('/{id}', 'ProfessionController@updateAction')->name('update-profession-action')->where('id', '[0-9]+');
});













Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
