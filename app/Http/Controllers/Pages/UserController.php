<?php

namespace App\Http\Controllers\Pages;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;   // <-- thêm
use Illuminate\Support\Str;

use App\User;
use App\Models\Advertise;

class UserController extends Controller
{
  public function show()
  {
    if(Auth::check()) {
      if (Auth::user()->admin) {
        return redirect()->route('admin.dashboard')->with(['alert' => [
          'type' => 'warning',
          'title' => 'Cảnh Báo',
          'content' => 'Bạn không có quyền truy cập vào trang này!'
        ]]);
      } else {
        $advertises = Advertise::where([
          ['start_date', '<=', date('Y-m-d')],
          ['end_date', '>=', date('Y-m-d')],
          ['at_home_page', '=', false]
        ])->latest()->limit(5)->get(['product_id', 'title', 'image']);

        $user = User::select('id', 'name', 'email', 'phone', 'address', 'avatar_image')
          ->where('id', Auth::user()->id)->first();

        return view('pages.show_user')->with('data',['user' => $user, 'advertises' => $advertises]);
      }
    } else {
      return redirect()->route('login')->with(['alert' => [
        'type' => 'warning',
        'title' => 'Cảnh Báo',
        'content' => 'Bạn phải đăng nhập để sử dụng chức năng này!'
      ]]);
    }
  }

  public function edit()
  {
    if(Auth::check()) {
      if (Auth::user()->admin) {
        return redirect()->route('admin.dashboard')->with(['alert' => [
          'type' => 'warning',
          'title' => 'Cảnh Báo',
          'content' => 'Bạn không có quyền truy cập vào trang này!'
        ]]);
      } else {
        $advertises = Advertise::where([
          ['start_date', '<=', date('Y-m-d')],
          ['end_date', '>=', date('Y-m-d')],
          ['at_home_page', '=', false]
        ])->latest()->limit(5)->get(['product_id', 'title', 'image']);

        $user = User::select('id', 'name', 'email', 'phone', 'address', 'avatar_image')
          ->where('id', Auth::user()->id)->first();

        return view('pages.edit_user')->with('data',['user' => $user, 'advertises' => $advertises]);
      }
    } else {
      return redirect()->route('login')->with(['alert' => [
        'type' => 'warning',
        'title' => 'Cảnh Báo',
        'content' => 'Bạn phải đăng nhập để sử dụng chức năng này!'
      ]]);
    }
  }

  public function save(Request $request)
  {
    if(!Auth::check()) {
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
        'content' => 'Bạn không có quyền thực hiện chức năng này!'
      ]]);
    }

    if (Auth::user()->id != $request->user_id) {
      return back()->with(['alert' => [
        'type' => 'info',
        'title' => 'Thông Báo',
        'content' => 'Đã xẩy ra lỗi trong quá trình cập nhật thông tin. Vui lòng nhập lại!'
      ]]);
    }

    // Xác định có yêu cầu đổi mật khẩu không (người dùng nhập ít nhất 1 ô mật khẩu)
    $wantsPasswordChange = $request->filled('new_password')
      || $request->filled('new_password_confirmation')
      || $request->filled('current_password');

    // RULES cơ bản
    if($request->phone != Auth::user()->phone) {
      $rules = [
        'name'    => 'required|string|max:20',
        'phone'   => 'required|string|size:10|regex:/^0[^6421][0-9]{8}$/|unique:users,phone',
        'address' => 'required',
      ];
      $messages = [
        'name.required'  => 'Tên không được để trống!',
        'name.string'    => 'Tên phải là một chuỗi ký tự!',
        'name.max'       => 'Tên không được vượt quá :max kí tự!',
        'phone.required' => 'Số điện thoại không được để trống!',
        'phone.string'   => 'Số điện thoại phải là một chuỗi ký tự!',
        'phone.size'     => 'Số điện thoại phải có độ dài :size chữ số!',
        'phone.regex'    => 'Số điện thoại không hợp lệ!',
        'phone.unique'   => 'Số điện thoại đã tồn tại!',
        'address.required' => 'Địa chỉ không được để trống!',
      ];
    } else {
      $rules = [
        'name'    => 'required|string|max:20',
        'address' => 'required',
      ];
      $messages = [
        'name.required'  => 'Tên không được để trống!',
        'name.string'    => 'Tên phải là một chuỗi ký tự!',
        'name.max'       => 'Tên không được vượt quá :max kí tự!',
        'address.required' => 'Địa chỉ không được để trống!',
      ];
    }

    // Nếu muốn đổi mật khẩu -> thêm rules bắt buộc
    if ($wantsPasswordChange) {
      $rules = array_merge($rules, [
        'current_password' => 'required|string',
        'new_password'     => 'required|string|min:8|confirmed', // cần new_password_confirmation
      ]);
      $messages = array_merge($messages, [
        'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
        'new_password.required'     => 'Vui lòng nhập mật khẩu mới.',
        'new_password.min'          => 'Mật khẩu mới tối thiểu 8 ký tự.',
        'new_password.confirmed'    => 'Xác nhận mật khẩu mới không khớp.',
      ]);
    }

    $validator = Validator::make($request->all(), $rules, $messages);
    if ($validator->fails()) {
      return back()->withErrors($validator)->withInput();
    }

    // Tải user
    $user = User::where('id', $request->user_id)->first();
    if (!$user) {
      return back()->with(['alert' => [
        'type' => 'error',
        'title' => 'Thất Bại',
        'content' => 'Không tìm thấy người dùng.'
      ]]);
    }

    // Cập nhật thông tin cơ bản
    $user->name    = $request->name;
    $user->phone   = $request->phone;
    $user->address = $request->address;

    // Ảnh đại diện
    if($request->hasFile('avatar_image')){
      $image = $request->file('avatar_image');
      $image_name = time().'_'.$image->getClientOriginalName();
      $image->storeAs('images/avatars',$image_name,'public');

      if(!empty($user->avatar_image)) {
        $old = 'images/avatars/'.$user->avatar_image;
        if (Storage::disk('public')->exists($old)) {
          Storage::disk('public')->delete($old);
        }
      }
      $user->avatar_image = $image_name;
    }

    // Nếu có yêu cầu đổi mật khẩu
    if ($wantsPasswordChange) {
      // Kiểm tra mật khẩu hiện tại đúng không
      if (!Hash::check($request->input('current_password'), $user->password)) {
        return back()
          ->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.'])
          ->withInput()
          ->with(['alert' => [
            'type' => 'error',
            'title' => 'Sai mật khẩu',
            'content' => 'Mật khẩu hiện tại không chính xác.'
          ]]);
      }

      // Tránh đặt trùng mật khẩu cũ
      if (Hash::check($request->input('new_password'), $user->password)) {
        return back()
          ->withErrors(['new_password' => 'Mật khẩu mới không được trùng mật khẩu hiện tại.'])
          ->withInput()
          ->with(['alert' => [
            'type' => 'warning',
            'title' => 'Không hợp lệ',
            'content' => 'Vui lòng chọn mật khẩu khác.'
          ]]);
      }

      // Cập nhật mật khẩu
      $user->password = Hash::make($request->input('new_password'));
      // Reset remember token để vô hiệu token cũ (nếu có)
      if (method_exists($user, 'setRememberToken')) {
        $user->setRememberToken(Str::random(60));
      }
    }

    $user->save();

    return redirect()->route('show_user')->with(['alert' => [
      'type' => 'success',
      'title' => 'Thành Công',
      'content' => $wantsPasswordChange
        ? 'Cập nhật thông tin và mật khẩu thành công.'
        : 'Cập nhật thông tin tài khoản thành công.'
    ]]);
  }
}
