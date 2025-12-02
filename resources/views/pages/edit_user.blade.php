@extends('layouts.master')

@section('title', 'Thay Đổi Thông Tin')

@section('content')

  <section class="bread-crumb">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home_page') }}">{{ __('header.Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('show_user') }}">Tài Khoản</a></li>
        <li class="breadcrumb-item active" aria-current="page">Thay Đổi Thông Tin</li>
      </ol>
    </nav>
  </section>

  <div class="site-user">
    <section class="section-advertise">
      <div class="content-advertise">
        <div id="slide-advertise" class="owl-carousel">
          @foreach($data['advertises'] as $advertise)
            <div class="slide-advertise-inner" style="background-image: url('{{ Helper::get_image_advertise_url($advertise->image) }}');" data-dot="<button>{{ $advertise->title }}</button>"></div>
          @endforeach
        </div>
      </div>
    </section>

    <section class="section-user">
      <div class="section-header">
        <h2 class="section-title">Thông Tin Tài Khoản</h2>
      </div>
      <div class="section-content">
        <div class="row">
          <div class="col-md-9">
            <div class="user">
              <form class="form-user" action="{{ route('save_user') }}" method="POST" accept-charset="utf-8" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <input type="hidden" name="user_id" value="{{ $data['user']->id }}">
                <div class="row">
                  <div class="col-md-3 col-sm-4 col-xs-4">
                    <div class="upload-avatar">
                      <div title="Avatar Preview" class="avatar-preview" style="background-image: url('{{ Helper::get_image_avatar_url($data['user']->avatar_image) }}'); padding-top: 100%;"></div>
                      <label for="upload" title="Upload Avatar"><i class="fas fa-folder-open"></i> Upload Avatar</label>
                      <input type="file" accept="image/*" id="upload" style="display:none" name="avatar_image">
                    </div>
                  </div>
                  <div class="col-md-9 col-sm-8 col-xs-8">
                    <div class="user-info">
                      <div class="info">
                        <div class="info-label">Tên Tài Khoản</div>
                        <div class="info-content">
                          <input id="name" type="text" class="@error('name') is-invalid @enderror" name="name" placeholder="Name" value="{{ old('name') ?: $data['user']->name }}" required autocomplete="name" autofocus>
                          @error('name')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                          @enderror
                        </div>
                      </div>

                      <div class="info">
                        <div class="info-label">Email</div>
                        <div class="info-content">
                          <input type="email" name="email" placeholder="Email" value="{{ $data['user']->email }}" disabled>
                        </div>
                      </div>

                      <div class="info">
                        <div class="info-label">Số Điện Thoại</div>
                        <div class="info-content">
                          <input id="phone" type="tel" class="@error('phone') is-invalid @enderror" name="phone" placeholder="Phone" value="{{ old('phone') ?: $data['user']->phone }}" required autocomplete="phone">
                          @error('phone')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                          @enderror
                        </div>
                      </div>

                      <div class="info">
                        <div class="info-label">Địa Chỉ</div>
                        <div class="info-content">
                          <textarea name="address" class="@error('address') is-invalid @enderror" rows="3" required>{{ old('address') ?: $data['user']->address }}</textarea>
                          @error('address')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                          @enderror
                        </div>
                      </div>
                    </div>

                    {{-- ====== KHỐI ĐỔI MẬT KHẨU (TÙY CHỌN) ====== --}}
                    <hr class="mt-4 mb-4">
                    <div class="change-password">
                      <h3 class="mb-3" style="font-weight:600;">Đổi Mật Khẩu (không bắt buộc)</h3>

                      <div class="info">
                        <div class="info-label">Mật khẩu hiện tại</div>
                        <div class="info-content">
                          <div class="input-group-pw">
                            <input id="current_password" type="password" name="current_password" placeholder="Nhập mật khẩu hiện tại (chỉ khi đổi mật khẩu)">
                            <button type="button" class="btn btn-default btn-toggle" onclick="togglePw('current_password')" aria-label="Hiện/ẩn mật khẩu"><i class="fas fa-eye"></i></button>
                          </div>
                          @error('current_password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                          @enderror
                        </div>
                      </div>

                      <div class="info">
                        <div class="info-label">Mật khẩu mới</div>
                        <div class="info-content">
                          <div class="input-group-pw">
                            <input id="new_password" type="password" name="new_password" placeholder="Mật khẩu mới (để trống nếu không đổi)">
                            <button type="button" class="btn btn-default btn-toggle" onclick="togglePw('new_password')" aria-label="Hiện/ẩn mật khẩu"><i class="fas fa-eye"></i></button>
                          </div>
                          @error('new_password')
                            <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                          @enderror
                        </div>
                      </div>

                      <div class="info">
                        <div class="info-label">Xác nhận mật khẩu mới</div>
                        <div class="info-content">
                          <div class="input-group-pw">
                            <input id="new_password_confirmation" type="password" name="new_password_confirmation" placeholder="Nhập lại mật khẩu mới">
                            <button type="button" class="btn btn-default btn-toggle" onclick="togglePw('new_password_confirmation')" aria-label="Hiện/ẩn mật khẩu"><i class="fas fa-eye"></i></button>
                          </div>
                        </div>
                      </div>
                    </div>
                    {{-- ====== HẾT: KHỐI ĐỔI MẬT KHẨU ====== --}}

                    <div class="action-edit">
                      <button type="submit" class="btn btn-default" title="Lưu Thay Đổi">Lưu Thay Đổi</button>
                    </div>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <div class="col-md-3">
            <div class="online_support">
              <h2 class="title">CHÚNG TÔI LUÔN SẴN SÀNG<br>ĐỂ GIÚP ĐỠ BẠN</h2>
              <img src="{{ asset('images/support_online.jpg') }}" alt="Support">
              <h3 class="sub_title">Để được hỗ trợ tốt nhất. Hãy gọi</h3>
              <div class="phone">
                <a href="tel:18006750" title="1800 6750">1800 6750</a>
              </div>
              <div class="or"><span>HOẶC</span></div>
              <h3 class="title">Chat hỗ trợ trực tuyến</h3>
              <h3 class="sub_title">Chúng tôi luôn trực tuyến 24/7.</h3>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

@endsection

@section('css')
  <style>
    .slide-advertise-inner {
      background-repeat: no-repeat;
      background-size: cover;
      padding-top: 21.25%;
    }
    #slide-advertise.owl-carousel .owl-item.active {
      -webkit-animation-name: zoomIn;
      animation-name: zoomIn;
      -webkit-animation-duration: .6s;
      animation-duration: .6s;
    }
    /* Nhóm nút show/hide mật khẩu */
    .input-group-pw {
      display: flex; gap: 8px; align-items: stretch;
    }
    .input-group-pw input { flex: 1; }
    .btn-toggle { white-space: nowrap; }
    .invalid-feedback{ display:block; margin-top:6px; color:#e3342f; font-size:90%; }
  </style>
@endsection

@section('js')
  <script>
    $(document).ready(function(){
      $("#slide-advertise").owlCarousel({
        items: 2, autoplay: true, loop: true, margin: 10,
        autoplayHoverPause: true, nav: true, dots: false,
        responsive:{ 0:{items:1}, 992:{items:2, animateOut:'zoomInRight', animateIn:'zoomOutLeft'} },
        navText: ['<i class="fas fa-angle-left"></i>', '<i class="fas fa-angle-right"></i>']
      });

      $("#upload").change(function() {
        $('.site-user .upload-avatar .avatar-preview')
          .css('background-image', 'url("' + getImageURL(this) + '")');
      });

      @if(session('alert'))
        Swal.fire(
          '{{ session('alert')['title'] }}',
          '{{ session('alert')['content'] }}',
          '{{ session('alert')['type'] }}'
        )
      @endif
    });

    function getImageURL(input) {
      return URL.createObjectURL(input.files[0]);
    };

    function togglePw(id){
      var el = document.getElementById(id);
      if(!el) return;
      el.type = (el.type === 'password') ? 'text' : 'password';
    }
  </script>
@endsection
