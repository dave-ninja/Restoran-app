<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Role;
use App\Unit;
use App\Profession;
use DB;
use Illuminate\Support\Facades\Storage;
use Mail;
use App\Obj;
use App\Worker;
use App\Storage AS Store;
use App\Storage_cat;
use Illuminate\Support\Facades\Hash;

class WorkerController extends Controller
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

	public function create()
	{
		$profs = Profession::all();
		return view('worker.create',compact('profs'));
	}

	public function createAction(Request $request)
	{
		$check_email = Worker::where('email',$request->email)->first();
		if(is_null($check_email)) {
			if(!is_null($request->firstName) && !is_null($request->email)) {
				if($request->role == 1) $role = "Admin";
				else if($request->role == 2) $role = "Manager";
				else $role = "Worker";
				$worker = Worker::create([
					'user_id'   =>  Auth::id(),
					'first_name' => $request->firstName,
					'last_name' => $request->lastName,
					'address' => $request->address,
					'phone' => $request->phone,
					'email' => $request->email,
					'gender' => $request->gender,
					'social' => $request->social,
					'role' => $role
				])->id;
				if(!is_null($worker)) {
					DB::table('profession_worker')->insert(
						['worker_id' => $worker, 'profession_id' => $request->profession]
					);
					if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
						$file = $request->file( 'avatar' );
						$path = $request->avatar->path();
						//$extension = $request->avatar->extension();
						$path = $request->avatar->storeAs(Auth::user()->name."/workers/".$request->firstName.$worker, 'avatar.jpg');
						$objLogo = Worker::where('id',$worker)->update([
							'avatar' => $path
						]);
					}
					if(isset($request->c_user) && $request->c_user == "yes") {
						$user = new \App\User();
						$pass = str_random(8);
						$user->password = Hash::make($pass);
						$user->email = $request->email;
						$user->name = $request->firstName.$worker;
						$user->save();
						$user->attachRole($request->role);
						if($user) {
							$data = [
								'subject' => 'Welcome to MyNinjaDev',
								'email' => $request->email,
								'username' => $request->firstName.$worker,
								'password' => $pass
							];
							Mail::send('emails.send-invite', ['data' => $data], function($msg) use($data)
							{
								$msg->to(Auth::user()->email)->subject($data['subject']);
							});
							Mail::send('emails.send-invite', ['data' => $data], function($msg) use($data)
							{
								$msg->to($data['email'])->subject($data['subject']);
							});

						}
					}
					return redirect( 'worker' )->with( 'success', 'Worker '.$request->firstName. ' added' );
				}
			} else {
				return redirect('/create-worker')->with('msg', 'Empty field(s)');
			}
		} else {
			return redirect()->back()->with('msg','ERROR email exists');
		}

	}

	protected function workers()
	{
		$workers = Worker::where( 'user_id', $this->userID )->orderBy( 'first_name', 'asc' )->get();
		return view('worker.workers', [ 'workers' => $workers ]);

	}

	protected function worker($id)
	{

		$roles = Role::all();
		$currentRole = DB::table('role_user')
		                 ->select('role_id')
		                 ->where('user_id',$this->userID)
		                 ->first();
		$profs = Profession::all();
		$worker = Worker::where( 'user_id', $this->userID )->where('id', $id)->first();
		$currentProf = DB::table('profession_worker')
		                 ->select('profession_id')
		                 ->where('worker_id',$id)
		                 ->first();
		if($worker) {
			return view( 'worker.worker', compact( 'worker','roles', 'currentRole', 'profs','currentProf' ) );
		} else {
			return redirect( '/worker' );
		}
	}

	protected function workerAction($id, Request $request)
	{
		if(!is_null($request->firstName) && !is_null($request->lastName)) {
			$firstName = $request->firstName;
			$checkExsName = Worker::select('first_name')->where('id',$id)->first();

			if($request->firstName != $checkExsName->first_name) { // rename folder
				if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) { // yes new avatar
					$file = $request->file( 'avatar' );
					$path = $request->avatar->path();
					$extension = $request->avatar->extension();
					$path = $request->avatar->storeAs(Auth::user()->name."/workers/".$firstName.$id, 'avatar.jpg');
					Storage::deleteDirectory(Auth::user()->name.'/workers/'.$checkExsName->first_name.$id);
					if($path) {
						$avatar = Worker::where('id',$id)->update([
							'avatar' => $path
						]);
					}
				} else { // no new avatar
					$path = Storage::move(Auth::user()->name.'/workers/'.$checkExsName->first_name.$id.'/avatar.jpg', Auth::user()->name.'/workers/'.$firstName.$id.'/avatar.jpg');
					Storage::deleteDirectory(Auth::user()->name.'/workers/'.$checkExsName->first_name.$id);
					if($path) {
						$avatar = Worker::where('id',$id)->update([
							'avatar' => Auth::user()->name."/workers/".$firstName.$id."/avatar.jpg"
						]);
					}
				}
			}

			if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
				$file = $request->file( 'avatar' );
				$path = $request->avatar->path();

				$extension = $request->avatar->extension();
				$path = $request->avatar->storeAs(Auth::user()->name."/workers/".$firstName.$id, 'avatar.jpg');
				if($path) {

					$avatar = Worker::where('id',$id)->update([
						'avatar' => $path
					]);
				}
			}

			$updWorker = Worker::where('id',$id)->update([
				'first_name' => $firstName,
				'last_name' => $request->lastName,
				'address' => $request->address,
				'phone' => $request->phone,
				'email' => $request->email,
				//'profession' => $request->profession,
				'social' => $request->social,
				'gender' => $request->gender,
				//'role' => $request->role,
			]);
			if($updWorker) {
				/*DB::table('profession_worker')->where(
					['worker_id' => $id, 'profession_id' => $request->profession]
				)->update(['profession_id' => $request->profession]);*/ // BUG
				$data = [
					'subject' => 'Updated your information',
					'email' => $request->email,
				];
				Mail::send('emails.send-update-worker', ['data' => $data], function($msg) use($data)
				{
					$msg->to($data['email'])->subject($data['subject']);
				});
			}

			return redirect( 'worker' )->with( 'success', 'Worker '.$request->firstName. ' updated' );
		} else {
			return redirect()->back()->with('msg','Error Unknown');
		}
	}
}
