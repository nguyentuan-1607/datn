<?php

namespace App\Http\Controllers\Pages;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Advertise;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with(['alert' => [
                'type' => 'warning',
                'title' => 'Cảnh Báo',
                'content' => 'Bạn phải đăng nhập để sử dụng chức năng này!'
            ]]);
        }

        // Không cho admin truy cập trang khách
        if (Auth::user()->admin) {
            return redirect()->route('admin.dashboard')->with(['alert' => [
                'type' => 'warning',
                'title' => 'Cảnh Báo',
                'content' => 'Bạn không có quyền truy cập vào trang này!'
            ]]);
        }

        // Quảng cáo
        $advertises = Advertise::where([
            ['start_date', '<=', date('Y-m-d')],
            ['end_date', '>=', date('Y-m-d')],
            ['at_home_page', '=', false],
        ])->latest()->limit(5)->get(['product_id','title','image']);

        // ĐƠN HÀNG: lấy đúng cột status để hiển thị badge / label
        $orders = Order::where('user_id', Auth::id())
            ->select('id','user_id','payment_method_id','order_code','status','created_at')
            ->with([
                'payment_method:id,name',
                'order_details:id,order_id,quantity,price',
            ])
            ->latest()
            ->get();

        if ($orders->isEmpty()) {
            return redirect()->route('home_page')->with(['alert' => [
                'type' => 'info',
                'title' => 'Thông Báo',
                'content' => 'Bạn không có đơn hàng nào. Hãy mua hàng để thực hiện chức năng này!'
            ]]);
        }

        return view('pages.orders')->with('data', [
            'orders'     => $orders,
            'advertises' => $advertises,
        ]);
    }

    public function show($id)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with(['alert' => [
                'type' => 'warning',
                'title' => 'Cảnh Báo',
                'content' => 'Bạn phải đăng nhập để sử dụng chức năng này!'
            ]]);
        }

        if (Auth::user()->admin) {
            return redirect()->route('admin.dashboard')->with(['alert' => [
                'type' => 'warning',
                'title' => 'Cảnh Báo',
                'content' => 'Bạn không có quyền truy cập vào trang này!'
            ]]);
        }

        $advertises = Advertise::where([
            ['start_date', '<=', date('Y-m-d')],
            ['end_date', '>=', date('Y-m-d')],
            ['at_home_page', '=', false],
        ])->latest()->limit(5)->get(['product_id','title','image']);

        // Lấy đơn chi tiết + status để render đúng label
        $order = Order::where('id', $id)
            ->select('id','user_id','payment_method_id','order_code','name','email','phone','address','status','created_at','updated_at')
            ->with([
                'payment_method:id,name',
                'user:id,name,email,phone,address',
                'order_details:id,order_id,product_detail_id,quantity,price',
                'order_details.product_detail:id,product_id,color',
                'order_details.product_detail.product:id,name,image,sku_code',
            ])
            ->first();

        if (!$order) abort(404);

        if (Auth::id() !== (int) $order->user_id) {
            return redirect()->route('home_page')->with(['alert' => [
                'type' => 'warning',
                'title' => 'Cảnh Báo',
                'content' => 'Bạn không có quyền truy cập vào trang này!'
            ]]);
        }

        return view('pages.order')->with('data', [
            'order'      => $order,
            'advertises' => $advertises,
        ]);
    }
}
