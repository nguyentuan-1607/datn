<?php

namespace App\Http\Controllers\Pages;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\ProductDetail;
use App\Models\Cart;
use App\Models\Advertise;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\OrderDetail;
use App\NL_Checkout;

class CartController extends Controller
{
    public function addCart(Request $request) {
        $product = ProductDetail::where('id', $request->id)
            ->with(['product' => function($q){
                $q->select('id','name','image','sku_code','RAM','ROM');
            }])
            ->select('id','product_id','color','quantity','sale_price','promotion_price','promotion_start_date','promotion_end_date')
            ->first();

        if (!$product) {
            return response()->json(['msg' => 'Product Not Found!'], 404);
        }

        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);

        if (!$cart->add($product, $product->id, (int)$request->qty)) {
            return response()->json(['msg' => 'Số lượng sản phẩm trong giỏ vượt quá số lượng sản phẩm trong kho!'], 412);
        }

        Session::put('cart', $cart);

        return response()->json([
            'msg' => 'Thêm giỏ hàng thành công',
            'url' => route('home_page'),
            'response' => Session::get('cart')
        ], 200);
    }

    public function removeCart(Request $request) {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);

        if (!$cart->remove($request->id)) {
            return response()->json(['msg' => 'Sản Phẩm không tồn tại!'], 404);
        }

        Session::put('cart', $cart);

        return response()->json([
            'msg' => 'Xóa sản phẩm thành công',
            'url' => route('home_page'),
            'response' => Session::get('cart')
        ], 200);
    }

    public function updateCart(Request $request) {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);

        if (!$cart->updateItem($request->id, (int)$request->qty)) {
            return response()->json(['msg' => 'Số lượng sản phẩm trong giỏ vượt quá số lượng sản phẩm trong kho!'], 412);
        }

        Session::put('cart', $cart);

        $response = [
            'id' => $request->id,
            'qty' => $cart->items[$request->id]['qty'],
            'price' => $cart->items[$request->id]['price'],
            'salePrice' => $cart->items[$request->id]['item']->sale_price,
            'totalPrice' => $cart->totalPrice,
            'totalQty' => $cart->totalQty,
            'maxQty'  => $cart->items[$request->id]['item']->quantity
        ];

        return response()->json(['response' => $response], 200);
    }

    public function updateMiniCart(Request $request) {
        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);

        if (!$cart->updateItem($request->id, (int)$request->qty)) {
            return response()->json(['msg' => 'Số lượng sản phẩm trong giỏ vượt quá số lượng sản phẩm trong kho!'], 412);
        }

        Session::put('cart', $cart);

        $response = [
            'id' => $request->id,
            'qty' => $cart->items[$request->id]['qty'],
            'price' => $cart->items[$request->id]['price'],
            'totalPrice' => $cart->totalPrice,
            'totalQty' => $cart->totalQty,
            'maxQty'  => $cart->items[$request->id]['item']->quantity
        ];

        return response()->json(['response' => $response], 200);
    }

    public function showCart() {
        $advertises = Advertise::where('start_date','<=',date('Y-m-d'))
            ->where('end_date','>=',date('Y-m-d'))
            ->where('at_home_page', false)
            ->latest()->limit(5)
            ->get(['product_id','title','image']);

        $oldCart = Session::has('cart') ? Session::get('cart') : null;
        $cart = new Cart($oldCart);

        return view('pages.cart')->with(['cart' => $cart, 'advertises' => $advertises]);
    }

    public function showCheckout(Request $request)
    {
        if (Auth::check() && !Auth::user()->admin) {
            if ($request->type === 'buy_now') {
                $payment_methods = PaymentMethod::select('id','name','describe')->get();
                $product = ProductDetail::where('id',$request->id)
                    ->with(['product' => function($q){ $q->select('id','name','image','sku_code','RAM','ROM'); }])
                    ->select('id','product_id','color','quantity','sale_price','promotion_price','promotion_start_date','promotion_end_date')
                    ->first();

                $cart = new Cart(null);
                if (!$cart->add($product, $product->id, (int)$request->qty)) {
                    return back()->with(['alert' => [
                        'type' => 'warning',
                        'title' => 'Thông Báo',
                        'content' => 'Số lượng sản phẩm trong giỏ vượt quá số lượng sản phẩm trong kho!'
                    ]]);
                }
                return view('pages.checkout')->with(['cart' => $cart, 'payment_methods' => $payment_methods, 'buy_method' => $request->type]);
            }

            if ($request->type === 'buy_cart') {
                $payment_methods = PaymentMethod::select('id','name','describe')->get();
                $oldCart = Session::has('cart') ? Session::get('cart') : null;
                $cart = new Cart($oldCart);
                $cart->update();
                Session::put('cart', $cart);
                return view('pages.checkout')->with(['cart' => $cart, 'payment_methods' => $payment_methods, 'buy_method' => $request->type]);
            }
        } elseif (Auth::check() && Auth::user()->admin) {
            return redirect()->route('home_page')->with(['alert' => [
                'type' => 'error',
                'title' => 'Thông Báo',
                'content' => 'Bạn không có quyền truy cập vào trang này!'
            ]]);
        } else {
            return redirect()->route('login')->with(['alert' => [
                'type' => 'info',
                'title' => 'Thông Báo',
                'content' => 'Bạn hãy đăng nhập để mua hàng!'
            ]]);
        }
    }

    public function payment(Request $request)
    {
        $payment_method = PaymentMethod::select('id','name')->findOrFail($request->payment_method);

        // ===================== COD & ATM (cùng xử lý, status = 2) =====================
        // CHANGED: dùng ID 1 (COD) và 2 (ATM) để gom vào 1 nhánh
        if (in_array((int)$payment_method->id, [1, 2], true)) {

            if ($request->buy_method === 'buy_now') {
                DB::transaction(function () use ($request) {
                    // Tạo đơn: ĐANG XỬ LÝ (2)
                    $order = new Order;
                    $order->user_id = Auth::id();
                    $order->payment_method_id = (int)$request->payment_method; // 1 hoặc 2
                    $order->order_code = 'PSO' . str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
                    $order->name  = $request->name;
                    $order->email = $request->email;
                    $order->phone = $request->phone;
                    $order->address = $request->address;
                    $order->status = 2; // Đang xử lý
                    $order->save();

                    // Lưu chi tiết
                    $detail = new OrderDetail;
                    $detail->order_id = $order->id;
                    $detail->product_detail_id = $request->product_id;
                    $detail->quantity = (int)$request->totalQty;
                    $detail->price = (int)$request->price;
                    $detail->save();

                    // Trừ kho
                    $product = ProductDetail::lockForUpdate()->findOrFail($request->product_id);
                    if ($product->quantity < $request->totalQty) {
                        throw new \RuntimeException('Sản phẩm không đủ tồn kho.');
                    }
                    $product->quantity -= (int)$request->totalQty;
                    $product->save();
                });

                return redirect()->route('home_page')->with(['alert' => [
                    'type' => 'success',
                    'title' => 'Đặt hàng thành công',
                    'content' => 'Chúng tôi sẽ xử lý đơn hàng của bạn trong thời gian sớm nhất.'
                ]]);
            }

            if ($request->buy_method === 'buy_cart') {
                $cart = Session::get('cart');
                if (!$cart || empty($cart->items)) {
                    return redirect()->route('home_page')->with(['alert' => [
                        'type' => 'warning',
                        'title' => 'Thông Báo',
                        'content' => 'Giỏ hàng trống.'
                    ]]);
                }

                DB::transaction(function () use ($request, $cart) {
                    $order = new Order;
                    $order->user_id = Auth::id();
                    $order->payment_method_id = (int)$request->payment_method; // 1 hoặc 2
                    $order->order_code = 'PSO' . str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
                    $order->name  = $request->name;
                    $order->email = $request->email;
                    $order->phone = $request->phone;
                    $order->address = $request->address;
                    $order->status = 2; // Đang xử lý
                    $order->save();

                    foreach ($cart->items as $item) {
                        $detail = new OrderDetail;
                        $detail->order_id = $order->id;
                        $detail->product_detail_id = $item['item']->id;
                        $detail->quantity = (int)$item['qty'];
                        $detail->price = (int)$item['price'];
                        $detail->save();

                        $product = ProductDetail::lockForUpdate()->findOrFail($item['item']->id);
                        if ($product->quantity < $item['qty']) {
                            throw new \RuntimeException('Sản phẩm '.$product->id.' không đủ tồn kho.');
                        }
                        $product->quantity -= (int)$item['qty'];
                        $product->save();
                    }
                });

                Session::forget('cart');

                return redirect()->route('home_page')->with(['alert' => [
                    'type' => 'success',
                    'title' => 'Đặt hàng thành công',
                    'content' => 'Chúng tôi sẽ xử lý đơn hàng của bạn trong thời gian sớm nhất.'
                ]]);
            }
        }

        // ===================== ONLINE PAYMENT (cổng thanh toán thật) =====================
        // CHANGED: chỉ vào đây khi KHÔNG phải id 1/2
        if (!in_array((int)$payment_method->id, [1, 2], true) && Str::contains($payment_method->name, 'Online Payment')) {
            if ($request->buy_method === 'buy_now') {
                // Tạo đơn: MỚI TẠO (0), chưa trừ kho
                $order = new Order;
                $order->user_id = Auth::id();
                $order->payment_method_id = (int)$request->payment_method;
                $order->order_code = 'PSO' . str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
                $order->name  = $request->name;
                $order->email = $request->email;
                $order->phone = $request->phone;
                $order->address = $request->address;
                $order->status = 0; // Mới tạo
                $order->save();

                $detail = new OrderDetail;
                $detail->order_id = $order->id;
                $detail->product_detail_id = $request->product_id;
                $detail->quantity = (int)$request->totalQty;
                $detail->price = (int)$request->price;
                $detail->save();

                // Build URL thanh toán
                $receiver = env('RECEIVER');
                $order_code = $order->order_code;
                $return_url = route('payment_response');
                $cancel_url = route('payment_response');
                $notify_url = route('payment_response');
                $transaction_info = $order->id;
                $currency = "vnd";
                $quantity = (int)$request->totalQty;
                $price = $detail->price * $detail->quantity;
                $tax = 0; $discount = 0; $fee_cal = 0; $fee_shipping = 0;
                $order_description = "Thanh toán đơn hàng " . config('app.name');
                $buyer_info = $request->name."*|*".$request->email."*|*".$request->phone."*|*".$request->address;
                $affiliate_code = "";

                $nl = new NL_Checkout();
                $nl->nganluong_url = env('NGANLUONG_URL');
                $nl->merchant_site_code = env('MERCHANT_ID');
                $nl->secure_pass = env('MERCHANT_PASS');

                $url = $nl->buildCheckoutUrlExpand(
                    $return_url,
                    $receiver,
                    $transaction_info,
                    $order_code,
                    $price,
                    $currency,
                    $quantity,
                    $tax,
                    $discount,
                    $fee_cal,
                    $fee_shipping,
                    $order_description,
                    $buyer_info,
                    $affiliate_code
                );
                $url .= '&cancel_url='.$cancel_url.'&notify_url='.$notify_url;

                return redirect()->away($url);
            }

            if ($request->buy_method === 'buy_cart') {
                $cart = Session::get('cart');
                if (!$cart || empty($cart->items)) {
                    return redirect()->route('home_page')->with(['alert' => [
                        'type' => 'warning',
                        'title' => 'Thông Báo',
                        'content' => 'Giỏ hàng trống.'
                    ]]);
                }

                $order = new Order;
                $order->user_id = Auth::id();
                $order->payment_method_id = (int)$request->payment_method;
                $order->order_code = 'PSO' . str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
                $order->name  = $request->name;
                $order->email = $request->email;
                $order->phone = $request->phone;
                $order->address = $request->address;
                $order->status = 0; // Mới tạo
                $order->save();

                foreach ($cart->items as $item) {
                    $detail = new OrderDetail;
                    $detail->order_id = $order->id;
                    $detail->product_detail_id = $item['item']->id;
                    $detail->quantity = (int)$item['qty'];
                    $detail->price = (int)$item['price'];
                    $detail->save();
                }

                // Build URL thanh toán
                $receiver = env('RECEIVER');
                $order_code = $order->order_code;
                $return_url = route('payment_response');
                $cancel_url = route('payment_response');
                $notify_url = route('payment_response');
                $transaction_info = $order->id;
                $currency = "vnd";
                $quantity = (int)$cart->totalQty;
                $price = (int)$cart->totalPrice;
                $tax = 0; $discount = 0; $fee_cal = 0; $fee_shipping = 0;
                $order_description = "Thanh toán đơn hàng " . config('app.name');
                $buyer_info = $request->name."*|*".$request->email."*|*".$request->phone."*|*".$request->address;
                $affiliate_code = "";

                $nl = new NL_Checkout();
                $nl->nganluong_url = env('NGANLUONG_URL');
                $nl->merchant_site_code = env('MERCHANT_ID');
                $nl->secure_pass = env('MERCHANT_PASS');

                $url = $nl->buildCheckoutUrlExpand(
                    $return_url, $receiver, $transaction_info, $order_code,
                    $price, $currency, $quantity, $tax, $discount, $fee_cal, $fee_shipping,
                    $order_description, $buyer_info, $affiliate_code
                );
                $url .= '&cancel_url='.$cancel_url.'&notify_url='.$notify_url;

                Session::forget('cart');
                return redirect()->away($url);
            }
        }
    }

    public function responsePayment(Request $request)
    {
        if ($request->filled('payment_id')) {
            $transaction_info = $request->transaction_info;
            $order_code = $request->order_code;
            $price = $request->price;
            $payment_id = $request->payment_id;
            $payment_type = $request->payment_type;
            $error_text = $request->error_text;
            $secure_code = $request->secure_code;

            $nl = new NL_Checkout();
            $nl->merchant_site_code = env('MERCHANT_ID');
            $nl->secure_pass = env('MERCHANT_PASS');

            $checkpay = $nl->verifyPaymentUrl($transaction_info, $order_code, $price, $payment_id, $payment_type, $error_text, $secure_code);

            if ($checkpay) {
                DB::transaction(function () use ($transaction_info, $order_code) {
                    /** @var Order $order */
                    $order = Order::where('id', $transaction_info)
                        ->where('order_code', $order_code)
                        ->lockForUpdate()
                        ->firstOrFail();

                    // tránh trừ kho 2 lần nếu đã hoàn thành
                    if ((int)$order->status !== 1) {
                        $order->status = 1; // Hoàn thành
                        $order->save();

                        foreach ($order->order_details as $d) {
                            $product = ProductDetail::lockForUpdate()->findOrFail($d->product_detail_id);
                            if ($product->quantity < $d->quantity) {
                                throw new \RuntimeException('Sản phẩm không đủ tồn kho để xác nhận.');
                            }
                            $product->quantity -= (int)$d->quantity;
                            $product->save();
                        }
                    }
                });

                return redirect()->route('home_page')->with(['alert' => [
                    'type' => 'success',
                    'title' => 'Thanh toán thành công!',
                    'content' => 'Cảm ơn bạn đã tin tưởng và lựa chọn chúng tôi.'
                ]]);
            }

            return redirect()->route('home_page')->with(['alert' => [
                'type' => 'error',
                'title' => 'Thanh toán không thành công!',
                'content' => $request->error_text
            ]]);
        }

        return redirect()->route('home_page')->with(['alert' => [
            'type' => 'error',
            'title' => 'Thanh toán không thành công!',
            'content' => 'Bạn đã hủy hoặc đã xảy ra lỗi trong quá trình thanh toán.'
        ]]);
    }
}
