<?php

namespace App\Http\Controllers;

use App\Role;
use App\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Obj;
use App\Obj_menu;
use App\Worker;
use App\Profession;
use Mail;
use DB;

class ObjectController extends Controller
{
	protected $userAuth;

	protected $userID;

	public function __construct()
	{
		$this->middleware('auth');
		$this->middleware(function ($request, $next) {
			$this->userAuth = Auth::user();

			if(!is_null($this->userAuth) && $this->userAuth->hasRole('admin')) {
				$this->userID = $this->userAuth->id;
			} else {
				$workerUserID = Worker::select('user_id')->where('email',$this->userAuth->email)->first();
				if($workerUserID) $this->userID = $workerUserID->user_id;
			}
			return $next($request);
		});
	}

	protected function create()
	{
		return view('object.create');
	}

	protected function createAction(Request $request)
	{
		if(!is_null($request->name)) {
			$object = Obj::create([
				'user_id'   =>  Auth::id(),
				'name'      =>  $request->name,
				'address'   =>  $request->address,
				'phone'     =>  $request->phone
			])->id;
			if(!is_null($object)) {
				if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
					$file = $request->file( 'logo' );
					$path = $request->logo->path();
					//$extension = $request->logo->extension();
					$path = $request->logo->storeAs(Auth::user()->name."/".$request->name, 'logo.jpg');
					$objLogo = Obj::where('id',$object)->update([
						'logo' => $path
					]);
				}
			}
			return redirect( 'object' )->with( 'success', 'Information has been added' );
		} else {
			return redirect('object/create')->with('msg', 'Empty Name field');
		}
	}

	protected function objects ()
	{
		$obj = Obj::where( 'user_id', $this->userID )->orderBy( 'name', 'asc' )->get();
		return view('object.objects', compact('obj'));
	}

	protected function object($id)
	{
		$obj = Obj::where( 'user_id', $this->userID )->where('id', $id)->first();
		$workers = Worker::where( 'user_id', $this->userID )->orderBy( 'first_name', 'asc' )->get();
		$obj_workers = Worker::select('first_name','last_name')
		                     ->leftJoin('obj_worker','obj_worker.worker_id','=','workers.id')
		                     ->leftJoin('objs','objs.id','=','obj_worker.obj_id')
		                     ->where('objs.id',$id)
		                     ->get();
		$obj_menus = Obj_menu::with('children')->where('parent_id',0)->where('obj_id',$id)->get();
		if(!is_null($obj)) {
			return view( 'object.single-object', [
				'obj' => $obj, 'workers' => $workers, 'obj_workers' => $obj_workers,
				'obj_menus' => $obj_menus
			] );
		} else {
			return redirect( '/objects' );
		}

	}

	protected function updateAction($id, Request $request)
	{
		if(!is_null($request->name)) {
			$name = $request->name;
			$checkExsName = Obj::select('name')->where('id',$id)->first();

			if($request->name != $checkExsName->name) { // rename folder

				if ($request->hasFile('logo') && $request->file('logo')->isValid()) { // yes new logo

					$file = $request->file( 'logo' );
					$path = $request->logo->path();
					$extension = $request->logo->extension();
					$path = $request->logo->storeAs(Auth::user()->name."/".$name, 'logo.jpg');
					Storage::deleteDirectory(Auth::user()->name.'/'.$checkExsName->name);
					if($path) {
						$objLogo = Obj::where('id',$id)->update([
							'logo' => $path
						]);
					}
				} else { // no new logo
					$path = Storage::move(Auth::user()->name.'/'.$checkExsName->name.'/logo.jpg', Auth::user()->name.'/'.$name.'/logo.jpg');
					Storage::deleteDirectory(Auth::user()->name.'/'.$checkExsName->name);
					if($path) {
						$objLogo = Obj::where('id',$id)->update([
							'logo' => Auth::user()->name."/".$name."/logo.jpg"
						]);
					}
				}
			}

			if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
				$file = $request->file( 'logo' );
				$path = $request->logo->path();

				$extension = $request->logo->extension();
				$path = $request->logo->storeAs(Auth::user()->name."/".$name, 'logo.jpg');
				if($path) {

					$objLogo = Obj::where('id',$id)->update([
						'logo' => $path
					]);
				}
			}

			$address = $request->address;
			$phone = $request->phone;
			$updObj = Obj::where('id',$id)->update([
				'name' => $name,
				'address' => $address,
				'phone' => $phone
			]);

			return redirect()->back()->with('success',$name.' object updated');
		} else {
			return redirect()->back()->with('msg',$request->name.' object Not updated');
		}
	}

	protected function createMenuAction(Request $request)
	{
		$obj_id = $request->obj_id;
		$name = $request->name;
		if(is_null($name) || is_null($obj_id)) return redirect()->back()->with('msg', 'unknown Error!');
		$obj_menu = Obj_menu::where('obj_id',$obj_id)->first();
		if(is_null($obj_menu) || $obj_menu->name != $name) {
			if(isset($request->current_menu_id) && !is_null($request->current_menu_id)) {
				$menu = Obj_menu::create([
					'obj_id' => $obj_id,
					'parent_id' => $request->current_menu_id,
					'name' => $name
				]);
			} else {
				$menu = Obj_menu::create([
					'obj_id' => $obj_id,
					'parent_id' => 0,
					'name' => $name
				]);
			}
			if($menu) {
				return redirect()->back()->with('success', $name.' menu created');
			}
		} else {
			return redirect()->back()->with('msg', $name.' menu exists');
		}
	}

	protected function menu($obj_id,$menu_id)
	{
		$obj = Obj::where( 'user_id', $this->userID )->where('id', $obj_id)->first();
		$current_menu = Obj_menu::where('id',$menu_id)->first();
		$obj_child_menus = Obj_menu::with('children')->where('parent_id',$menu_id)->where('obj_id',$obj_id)->get();
		return view('object.menu', compact('obj','current_menu','obj_child_menus'));
	}

	protected function createWorkerAction(Request $request)
	{
		$worker_id = $request->workerId;
		$obj_id = $request->objId;
		$addWorker = DB::table('obj_worker')->insert(
			['worker_id' => $worker_id, 'obj_id' => $obj_id]
		);
		if($addWorker) {
			echo "success";
		}
	}
}
