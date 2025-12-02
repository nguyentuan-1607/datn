@extends('admin.layouts.master')

@section('title', 'Dashboard')

@section('embed-css')
  {{-- Giữ lại CSS gốc nếu có --}}
@endsection

@section('custom-css')
<style>
  /* ==== Theme: White Clean (nền trắng, chữ đen) ==== */
  :root{
    --g1:#22c55e; --g2:#16a34a; --g3:#0ea5e9;
    --bg-soft:#ffffff; --card:#f8fafc; --border:#d1d5db;
    --text:#111827; --muted:#6b7280; --ring:#22c55e33;
  }

  body { background: #ffffff; color: var(--text); }

  .breadcrumb { background: transparent; margin-bottom: 12px; }
  .breadcrumb > li > a, .breadcrumb > .active { color: black; }

  .small-box {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid var(--border);
    box-shadow: 0 2px 10px #00000010;
    transition: .2s;
    background: var(--card);
  }
  .small-box:hover { transform: translateY(-3px); box-shadow: 0 4px 20px #00000020; }
  .small-box .inner { padding:18px; color: var(--text); }
  .small-box .inner h3 { font-weight:800;margin:0 0 6px;font-size:32px; }
  .small-box .inner p { margin:0;color:black;font-weight:600; }
  .small-box .icon { color:#0003; right:16px; top:6px; opacity:.15; transition:.2s;font-size:64px; }
  .small-box:hover .icon { opacity:.25; }
  .small-box.bg-green { background: linear-gradient(135deg, var(--g1), var(--g2)); color: white; }
  .small-box.bg-aqua-active { background: linear-gradient(135deg, #22d3ee, #0ea5e9); color: white; }
  .small-box.bg-orange { background: linear-gradient(135deg, #f59e0b, #ea580c); color: white; }
  .small-box.bg-purple { background: linear-gradient(135deg, #992cffff, #762cf5ff); color: white; }
  .small-box.bg-pink { background: linear-gradient(135deg, #ec4899, #db2777); color: white; }
  .small-box.bg-slate { background: linear-gradient(135deg, #64748b, #475569); color: white; }
  .small-box-footer { background:#f1f5f9!important;color:#111!important;border-top:1px solid var(--border)!important;font-weight:600; }

  .box {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 2px 10px #00000010;
  }
  .box-header.with-border {
    border-bottom: 1px solid var(--border);
    background: #f9fafb;
    color: var(--text);
  }
  .box-title { font-weight:800; letter-spacing:.3px; }
  .box-body { color: var(--text); }

  .table { color: var(--text); background: white; }
  .table > thead > tr > th { color:#374151; border-bottom: 1px solid var(--border); background:#f9fafb; }
  .table > tbody > tr > td { border-top: 1px solid var(--border); }
  .label { border-radius:999px;padding:4px 8px;display:inline-block;font-weight:700; }

  .progress { background:#e5e7eb; border-radius:999px; height:8px; box-shadow:inset 0 0 0 1px #d1d5db; }
  .progress-bar { border-radius:999px; background-image:linear-gradient(90deg,var(--g1),var(--g2)); }

  .qa-grid .btn {
    margin:6px 8px 0 0;
    border-radius:12px;
    border:1px solid var(--border);
    color:var(--text);
    background:#f9fafb;
  }
  .qa-grid .btn:hover { box-shadow:0 0 0 4px var(--ring); }

  .muted { color: black; }
</style>
@endsection

@section('breadcrumb')
<ol class="breadcrumb">
  <li><a href="{{ route('admin.dashboard') }}"><i class="fa fa-dashboard"></i> Home</a></li>
  <li class="active">Dashboard</li>
</ol>
@endsection

@section('content')
  @php
    use Illuminate\Support\Facades\Route as R;
    // Helper tạo link an toàn: nếu route không tồn tại -> không crash
    $link = function(string $name, array $params = []) {
      return R::has($name) ? route($name, $params) : 'javascript:void(0)';
    };
  @endphp

  {{-- ====== HÀNG 1: KPI nhanh ====== --}}
  <div class="row">
    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-green">
        <div class="inner">
          <h3>{{ number_format($kpi['revenue_today'] ?? 12500000) }}₫</h3>
          <p>Doanh thu hôm nay</p>
        </div>
        <div class="icon"><i class="ion ion-cash"></i></div>
        <a href="{{ $link('admin.order.index', ['range'=>'today']) }}" class="small-box-footer">Chi tiết <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-aqua-active">
        <div class="inner">
          <h3>{{ $kpi['orders_pending'] ?? 8 }}</h3>
          <p>Đơn hàng chờ xử lý</p>
        </div>
        <div class="icon"><i class="fa fa-clock-o"></i></div>
        <a href="{{ $link('admin.order.index', ['status'=>'pending']) }}" class="small-box-footer">Xử lý ngay <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-orange">
        <div class="inner">
          <h3>{{ $kpi['low_stock'] ?? 5 }}</h3>
          <p>Sản phẩm sắp hết hàng</p>
        </div>
        <div class="icon"><i class="ion ion-alert-circled"></i></div>
        <a href="{{ $link('admin.product.index', ['filter'=>'low_stock']) }}" class="small-box-footer">Kiểm tra <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>

    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-purple">
        <div class="inner">
          <h3>{{ $kpi['new_users_today'] ?? 12 }}</h3>
          <p>Người dùng mới hôm nay</p>
        </div>
        <div class="icon"><i class="ion ion-person-add"></i></div>
        <a href="{{ $link('admin.users', ['range'=>'today']) }}" class="small-box-footer">Xem danh sách <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>
  </div>

  {{-- ====== HÀNG 2: KPI mở rộng ====== --}}
  <div class="row">
    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-pink">
        <div class="inner">
          <h3>{{ ($kpi['conversion_rate'] ?? 3.6) }}%</h3>
          <p>Tỷ lệ chuyển đổi</p>
        </div>
        <div class="icon"><i class="fa fa-line-chart"></i></div>
        <a href="{{ $link('admin.analytics.conversion') }}" class="small-box-footer">Phân tích <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>
    <div class="col-lg-3 col-xs-6">
      <div class="small-box bg-slate">
        <div class="inner">
          <h3>{{ ($kpi['refund_rate'] ?? 0.8) }}%</h3>
          <p>Tỷ lệ hoàn/đổi</p>
        </div>
        <div class="icon"><i class="fa fa-undo"></i></div>
        <a href="{{ $link('admin.order.index', ['filter'=>'refund']) }}" class="small-box-footer">Xem đơn hoàn <i class="fa fa-arrow-circle-right"></i></a>
      </div>
    </div>
  </div>

  {{-- ====== HÀNG 3: Bảng trái + Tiện ích phải ====== --}}
  <div class="row">
    {{-- Cột trái --}}
    <div class="col-md-8">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Đơn hàng gần đây</h3>
          <div class="box-tools pull-right">
            <a href="{{ $link('admin.order.index') }}" class="btn btn-sm btn-success">Tất cả đơn</a>
          </div>
        </div>
        <div class="box-body">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Mã</th>
                  <th>Khách hàng</th>
                  <th>Tổng</th>
                  <th>Ngày</th>
                  <th>Trạng thái</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              @forelse(($recentOrders ?? []) as $o)
                <tr>
                  <td><strong>#{{ $o['code'] }}</strong></td>
                  <td>{{ $o['customer'] }}</td>
                  <td>{{ number_format($o['total']) }}₫</td>
                  <td>{{ $o['date'] }}</td>
                  <td>
                    @php $status = $o['status']; @endphp
                    <span class="label
                      @if($status === 'pending') label-warning
                      @elseif($status === 'paid') label-success
                      @elseif($status === 'shipped') label-primary
                      @else label-default @endif
                    ">{{ strtoupper($status) }}</span>
                  </td>
                  <td><a class="btn btn-xs btn-primary" href="{{ $link('admin.order.show', $o['id'] ?? null) }}">Chi tiết</a></td>
                </tr>
              @empty
                <tr><td colspan="6" class="muted">Chưa có đơn hàng nào gần đây.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="box-footer">
          <span class="muted">Cập nhật lần cuối: {{ $ordersUpdatedAt ?? now() }}</span>
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Sản phẩm bán chạy</h3>
          <div class="box-tools pull-right">
            <a href="{{ $link('admin.product.index', ['sort'=>'best_seller']) }}" class="btn btn-sm btn-success">Quản lý</a>
          </div>
        </div>
        <div class="box-body">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Sản phẩm</th>
                  <th>Đã bán</th>
                  <th>Doanh thu</th>
                  <th>Đạt mục tiêu</th>
                </tr>
              </thead>
              <tbody>
              @php $tops = $topProducts ?? [
                ['name'=>'ĐIỆN THOẠI A15','sold'=>120,'revenue'=>120000000,'goal'=>150],
                ['name'=>'Chuột G Pro','sold'=>80,'revenue'=>32000000,'goal'=>100],
                ['name'=>'Bàn phím 75%','sold'=>60,'revenue'=>42000000,'goal'=>80],
              ]; @endphp
              @foreach($tops as $i => $p)
                @php $pct = round(($p['sold']/max(1,$p['goal']))*100); @endphp
                <tr>
                  <td>{{ $i+1 }}</td>
                  <td>{{ $p['name'] }}</td>
                  <td>{{ $p['sold'] }}</td>
                  <td>{{ number_format($p['revenue']) }}₫</td>
                  <td style="min-width:160px;">
                    <div class="progress" title="{{ $pct }}%">
                      <div class="progress-bar" style="width: {{ min($pct,100) }}%"></div>
                    </div>
                    <small class="muted">{{ $pct }}% mục tiêu</small>
                  </td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Cột phải --}}
    <div class="col-md-4">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Sắp hết hàng</h3>
          <div class="box-tools pull-right">
            <a href="{{ $link('admin.product.index', ['filter'=>'low_stock']) }}" class="btn btn-xs btn-warning">Xem tất cả</a>
          </div>
        </div>
        <div class="box-body">
          @php $low = $lowStocks ?? [
            ['name'=>'SSD 1TB NVMe','stock'=>4],
            ['name'=>'RAM 16GB DDR4','stock'=>6],
            ['name'=>'Màn hình 27"','stock'=>2],
          ]; @endphp
          <ul class="list-unstyled">
            @forelse($low as $l)
              <li style="margin-bottom:10px;">
                <strong>{{ $l['name'] }}</strong>
                <span class="pull-right label label-danger">Còn {{ $l['stock'] }}</span>
              </li>
            @empty
              <li class="muted">Kho đang ổn, không có sản phẩm nào thấp.</li>
            @endforelse
          </ul>
        </div>
      </div>

      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Người dùng mới</h3>
          <div class="box-tools pull-right">
            <a href="{{ $link('admin.users') }}" class="btn btn-xs btn-info">Quản lý</a>
          </div>
        </div>
        <div class="box-body">
          @php $newUsers = $recentUsers ?? [
            ['name'=>'Nguyễn Văn A','email'=>'a@example.com','date'=>'2025-10-29'],
            ['name'=>'Trần Thị B','email'=>'b@example.com','date'=>'2025-10-29'],
            ['name'=>'Lê C','email'=>'c@example.com','date'=>'2025-10-28'],
          ]; @endphp
          <ul class="list-unstyled">
            @foreach($newUsers as $u)
              <li style="margin-bottom:10px;">
                <i class="fa fa-user-circle-o"></i>
                <strong> {{ $u['name'] }} </strong>
                <div class="muted">{{ $u['email'] }} • {{ $u['date'] }}</div>
              </li>
            @endforeach
          </ul>
        </div>
      </div>

     

      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Sức khỏe hệ thống</h3>
        </div>
        <div class="box-body">
          @php
            $health = $systemHealth ?? [
              ['label'=>'Hàng đợi email','value'=>($queues ?? 2),'ok'=>($queues ?? 2) < 20],
              ['label'=>'Nền tảng thanh toán','value'=>'OK','ok'=>true],
              ['label'=>'Tốc độ phản hồi API','value'=>'120ms','ok'=>true],
              ['label'=>'Lỗi 5xx 24h','value'=>'0.12%','ok'=>true],
            ];
          @endphp
          <ul class="list-unstyled">
            @foreach($health as $h)
              <li style="margin-bottom:8px;">
                <i class="fa {{ $h['ok'] ? 'fa-check-circle text-green' : 'fa-exclamation-circle text-red' }}"></i>
                <strong> {{ $h['label'] }}:</strong>
                <span class="muted"> {{ $h['value'] }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>

    </div>
  </div>
@endsection

@section('embed-js')
  {{-- Không dùng Chart.js --}}
@endsection

@section('custom-js')
<script>
  (function(){
    var btn = document.getElementById('copyInvite');
    if(btn){
      btn.addEventListener('click', function(){
        var link = "{{ url('/register?ref=admin') }}";
        var ta = document.createElement('textarea');
        ta.value = link; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch(e){}
        document.body.removeChild(ta);
        btn.innerHTML = '<i class="fa fa-check"></i> Đã copy';
        setTimeout(function(){ btn.innerHTML = '<i class="fa fa-link"></i> Copy link mời'; }, 1800);
      });
    }
  })();
</script>
@endsection
