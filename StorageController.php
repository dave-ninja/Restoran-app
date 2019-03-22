<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Storage_cat;
use App\Storage AS Store;
use App\Obj;
use App\Obj_menu;
use App\Worker;
use App\Role;
use App\Unit;
use Carbon\Carbon;
use DB;
use Mail;

class StorageController extends Controller
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

	protected function categories()
	{
		$storage_cats = Storage_cat::where( 'user_id', $this->userID )->where('parent_id',0)->orderBy('title','asc')->get();
		return view('storage.categories',compact('storage_cats'));
	}

	protected function category($cat_id)
	{
		$current_cat = Storage_cat::where('user_id',$this->userID)->where('id',$cat_id)->first();
		$cats = Storage_cat::with('children')
		                   ->where('user_id','=',$this->userID)
							//->where('parent_id','=',0)
			               ->where('id','=',$current_cat->parent_id)
		                   ->first();
		$store_child_cats = Storage_cat::where('user_id',$this->userID)->where('parent_id',$cat_id)->get();
		$store_products = Store::where('user_id',$this->userID)->where('cat_id',$cat_id)->get();

		$units = Unit::all();
		if(!is_null($current_cat)) {
			return view('storage.category', compact('store_products','current_cat','store_child_cats', 'units','cats'));
		} else {
			return redirect('/storage/categories')->with('msg','Error');
		}
	}

	protected function product($pr_id)
	{
		$units = Unit::all();
		$product = Store::select('units.title as u_title','storage_cats.title as cat_title',
								'storage_cats.parent_id','storages.*')
		                ->leftJoin('storage_cats','storage_cats.id','storages.cat_id')
		                ->leftJoin('units','units.id','storages.unit_id')
		                ->where('storages.user_id',$this->userID)
		                ->where('storage_cats.user_id',$this->userID)
		                ->where('storages.id',$pr_id)
		                ->first();
		$categories = Storage_cat::with('children')
		                         ->where('user_id','=',$this->userID)
		                         ->where('id','=',$product->parent_id) //
		                         ->get();
		if($product) {
			return view('storage.product',compact('categories','product','units'));
		} else {
			return redirect()->back()->with('msg','Error');
		}
	}

	protected function productIn()
    {
	    $units = Unit::all();
    	return view('storage.product-in',compact('units'));
    }

	protected function productOut()
	{
		$objs = Obj::where( 'user_id', $this->userID )->get();
		return view('storage.product-out',compact('objs'));
	}

	protected function productOutAction(Request $request)
	{
		if($request->cat_id) {
			$product = Store::with('children')
			                ->where('user_id','=',$this->userID)
				//->where('parent_id','=',0)
				            ->where('id','=',$request->product_id)
			                ->first();
			$total_qty = $product->total_qty - $request->unit_qty_out;
			$ins_pr = Store::create([
				'user_id' => $this->userID,
				'unit_id' => $request->unit,
				'cat_id' => $product->cat_id,
				'parent_id' => $product->id,
				'product_name' => $product->product_name,
				'product_price' => $request->product_price,
				'qty' => $request->unit_qty_out,
				'total_qty' => $total_qty,
				'movements' => 0,
				'comment' => $request->product_comment_out
			]);
			if($ins_pr) {
				return redirect()->back()->with('success',$product->product_name.' Sended');
			}
		} else {
			return redirect()->back()->with('msg','Unknown Product');
		}
	}

	protected function updateInsProductAction(Request $request) // insert in storage (Vahag i gordz)
	{
		if($request->cat_id) {
			$product = Store::with('children')
			                ->where('user_id','=',$this->userID)
				//->where('parent_id','=',0)
				            ->where('id','=',$request->product_id)
			                ->first();
			$total_qty = $product->total_qty + $request->unit_qty;
			$ins_pr = Store::create([
				'user_id' => $this->userID,
				'unit_id' => $request->unit,
				'cat_id' => $product->cat_id,
				'parent_id' => $product->id,
				'product_name' => $product->product_name,
				'product_price' => $request->product_price,
				'qty' => $request->unit_qty,
				'total_qty' => $total_qty,
				'movements' => 1,
				'vendor_name' => $request->vendor_name,
				'vendor_phone' => $request->vendor_phone,
				'comment' => $request->product_comment
			]);
			if($ins_pr) {
				return redirect()->back()->with('success',$product->product_name.' updated');
			}
		} else {
			return redirect()->back()->with('msg','Unknown Product');
		}
	}

	protected function productOutObject($id,$cat_id = null)
	{
		$obj = Obj::where('id',$id)->first();
		$obj_child_menus = Obj_menu::with('children')
		                           ->where('parent_id',0)
		                           ->where('obj_id',$id)
		                           ->get();
		$obj_current_menu = null;
		$units = Unit::all();
		if(!is_null($cat_id)) {
			$obj_current_menu = Obj_menu::where('id',$cat_id)->first();
		}
		return view('storage.product-out',compact('obj','obj_child_menus','cat_id','units','obj_current_menu'));
	}

	protected function createCategoryAction(Request $request)
	{
		$title = $request->title;
		if(is_null($title)) return redirect()->back()->with('msg', 'unknown Error!');
		$store_cat = Storage_cat::where('user_id',$this->userID)->where('title',$title)->get();
		if(!$store_cat->first()) {
			if(isset($request->current_cat_id) && !is_null($request->current_cat_id)) {
				$cat = Storage_cat::create([
					'user_id' => $this->userID,
					'parent_id' => $request->current_cat_id,
					'title' => $title
				]);
			} else {
				$cat = Storage_cat::create([
					'user_id' => $this->userID,
					'parent_id' => 0,
					'title' => $title
				]);
			}
			if($cat) {
				return redirect()->back()->with('success', $title.' menu created');
			} else {
				return redirect()->back()->with('msg', 'unknown Error!');
			}
		} else {
			return redirect()->back()->with('msg', $title.' menu exists');
		}
	}

	protected function createProductAction(Request $request)
	{
		$product_name = $request->product_name;
		$product = Store::create([
			'user_id' => $this->userID,
			'unit_id' => $request->unit,
			'cat_id' => $request->storage_cat,
			'parent_id' => 0,
			'product_name' => $product_name,
			'product_price' => $request->product_price,
			'qty' => $request->unit_qty,
			'vendor_name' => $request->vendor_name,
			'vendor_phone' => $request->vendor_phone,
			'comment' => $request->product_comment
		]);
		if($product) {
			return redirect()->back()->with('success', $product_name.' product created');
		} else {
			return redirect()->back()->with('msg', $product_name.' Error');
		}
	}

	protected function updateProductAction($id, Request $request)
	{
		dd($id);
	}

	protected function getProductByKeyup(Request $request)
	{
		if(isset($request->pr_name) && !empty($request->pr_name)) {
			$pr_name = trim($request->pr_name);
			$product = Store::where('user_id', '=',$this->userID)
			                ->where('product_name','like','%'.$pr_name.'%')
			                ->groupBy('product_name')
			                ->distinct()
			                ->get();
			if($product->first()) {
				echo json_encode( $product );
			} else {
				return "no";
			}
		} elseif (isset($request->value) && !empty($request->value)) {
			$val = trim($request->value);

			$product_avg_price = Store::select('product_price')
			                 ->where('user_id', $this->userID)
			                 ->where('product_name', $val)
			                 ->where('movements', 1)
			                 ->whereMonth('created_at', date('m'))
							 ->avg('product_price');
			$products = Store::where('user_id', $this->userID)
			                            ->where('product_name', $val)
			                            ->whereMonth('created_at', date('m'))
										->orderBy('id','DESC')
				                        ->first();
			$products->avg_price = $product_avg_price;
			if($products) {
				return $products;
			} else {
				return "no";
			}
		}

	}

}
