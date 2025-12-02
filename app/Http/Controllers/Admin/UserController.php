<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use App\User;
use App\Models\ProductVote;
use App\Models\Order;
use App\Mail\Admin\ActiveAccountMail;

class UserController extends Controller
{
  public function index()
  {
    $users = User::select('id', 'name', 'email', 'phone', 'address', 'provider', 'avatar_image', 'active', 'created_at')
      ->where('admin', '<>', true)->get();
    return view('admin.user.index')->with('users', $users);
  }

  public function new(Request $request)
  {
    $rules = array(
      'email' => array('required', 'regex:/^[a-z](\.?[a-z0-9]){5,}@gmail\.com$/', 'unique:users')
    );
    $messsages = array(
      'email.required'  =>  'Email không được để trống!',
      'email.regex'     =>  'Email không đúng định dạng!',
      'email.unique'    =>  'Email đã tồn tại!'
    );
    $validator = Validator::make($request->all(), $rules, $messsages);
    if ($validator->fails()) {
      return response()->json($validator->messages(), 400);
    } else {
      $password = Str::random(8);

      $user = new User;
      $user->name = 'New Account';
      $user->email = $request->email;
      $user->password = Hash::make($password);
      $user->active_token = Str::random(40);
      $user->save();

      $data['token'] = $user->active_token;
      $data['password'] = $password;

      Mail::to($user)->send(new ActiveAccountMail($data));

      $data['type'] = 'success';
      $data['title'] = 'Thành Công';
      $data['content'] = 'Thêm tài khoản thành công!';

      return response()->json($data, 200);
    }
  }

  /**
   * Dùng chung route admin.user_delete cho 2 tác vụ:
   * - action=reset  => reset mật khẩu về 12345678 (không đổi web.php)
   * - không có action => xóa tài khoản chưa kích hoạt (giữ nguyên behavior cũ)
   */
  public function delete(Request $request)
  {
    // ===== Nhánh RESET PASSWORD =====
    if ($request->input('action') === 'reset') {
      // Không cho reset admin
      $user = User::select('id', 'name', 'email', 'admin')
                  ->where([['id', $request->user_id], ['admin', false]])
                  ->first();

      if (!$user) {
        return response()->json([
          'type'    => 'error',
          'title'   => 'Thất Bại',
          'content' => 'Tài khoản không tồn tại hoặc không hợp lệ!'
        ], 200);
      }

      // Đặt lại mật khẩu mặc định
      $user->password = Hash::make('12345678');
      // Làm mới remember_token (đăng xuất các session “remember me” cũ)
      $user->setRememberToken(Str::random(60));
      $user->save();

      return response()->json([
        'type'    => 'success',
        'title'   => 'Thành Công',
        'content' => 'Đã reset mật khẩu về mặc định: 12345678'
      ], 200);
    }

    // ===== Nhánh DELETE (giữ nguyên logic cũ) =====
    $user = User::where([['id', $request->user_id], ['active', false]])->first();

    if (!$user) {
      $data['type'] = 'error';
      $data['title'] = 'Thất Bại';
      $data['content'] = 'Bạn không thể xóa tài khoản đã kích hoạt hoặc tài khoản không tồn tại!';
    } else {
      $user->delete();
      $data['type'] = 'success';
      $data['title'] = 'Thành Công';
      $data['content'] = 'Xóa tài khoản thành công!';
    }

    return response()->json($data, 200);
  }

  public function show($id)
  {
    $user = User::select('id', 'name', 'email', 'phone', 'address', 'provider', 'avatar_image', 'active', 'created_at')
      ->where([['id', $id], ['admin', false]])->first();
    if(!$user) abort(404);

    $product_votes = ProductVote::where('user_id', $user->id)
      ->with(['product' => function($query) {
        $query->select('id', 'name', 'image');
      }])->latest()->get();

    $orders = Order::where('user_id', $user->id)->with([
      'payment_method' => function($query) {
        $query->select('id', 'name');
      },
      'order_details' => function($query) {
        $query->select('id', 'order_id', 'product_detail_id', 'quantity', 'price')
          ->with([
            'product_detail' => function ($query) {
              $query->select('id', 'product_id', 'color')
                ->with([
                  'product' => function ($query) {
                    $query->select('id', 'name', 'image', 'sku_code');
                  }
                ]);
            }
          ]);
      }
    ])->latest()->get();

    return view('admin.user.show')->with([
      'user' => $user,
      'product_votes' => $product_votes,
      'orders' => $orders
    ]);
  }

  public function send($id)
  {
    $user = User::where([['id', $id], ['active', false], ['admin', false]])->first();
    if(!$user) abort(404);

    $data['token'] = $user->active_token;
    $data['password'] = null;

    Mail::to($user)->send(new ActiveAccountMail($data));

    return back()->with(['alert' => [
      'type' => 'success',
      'title' => 'Thành Công',
      'content' => 'Gửi email kích hoạt tài khoản thành công.'
    ]]);
  }
}
