@extends('admin.layouts.master')

@section('title', 'Quản Lý Đơn Hàng')

@section('embed-css')
<link rel="stylesheet" href="{{ asset('AdminLTE/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css') }}">
@endsection

@section('custom-css')
<style>
  /* Căn giữa header */
  #order-table thead th{ text-align:center !important; }
  /* Căn giữa các cột quan trọng (header + body) */
  #order-table th:nth-child(1),  #order-table td:nth-child(1)  { text-align:center; } /* ID */
  #order-table th:nth-child(6),  #order-table td:nth-child(6)  { text-align:center; } /* Điện thoại */
  #order-table th:nth-child(7),  #order-table td:nth-child(7)  { text-align:center; } /* Phương thức */
  #order-table th:nth-child(8),  #order-table td:nth-child(8)  { text-align:center; } /* Trạng thái */
  #order-table th:nth-child(9),  #order-table td:nth-child(9)  { text-align:center; } /* Ngày tạo */
  #order-table th:nth-child(10), #order-table td:nth-child(10) { text-align:center; } /* Tác vụ */

  #order-table td, #order-table th{ vertical-align: middle !important; }

  /* ==== Trạng thái: căn giữa + bo tròn ==== */
  .status-cell{
    min-width: 180px;
    display: flex;
    flex-direction: column;
    align-items: center;     /* ngang */
    justify-content: center; /* dọc */
    gap: 8px;
  }
  .status-label .label{
    display: inline-block;
    min-width: 120px;
    text-align: center;
    border-radius: 999px;
    padding: 6px 12px;
    font-weight: 700;
  }
  .status-select{
    width: 160px;
    border-radius: 999px;
    padding: 6px 12px;
    height: 34px;
    line-height: 20px;
    text-align: center;
    border: 1px solid #d1d5db;
    background: #fff;
    transition: .2s;
  }
  .status-select:hover{ border-color:#16a34a; box-shadow:0 0 0 3px #22c55e33; }
  .status-select:disabled{ background:#f3f4f6; color:#9ca3af; cursor:not-allowed; }

  /* Search box */
  #search-input .input-group-addon{
    padding:0;position:absolute;top:0;left:0;bottom:0;width:34px;border:none;background:none;
  }
  #search-input .input-group-addon i{font-size:18px;line-height:34px;width:34px;color:#00a65a;}
  #search-input input{
    position:static;width:100%;font-size:15px;line-height:22px;
    padding:5px 5px 5px 34px;border-color:#fbfbfb;box-shadow:none;background:#e8f0fe;border-radius:6px;
  }
</style>
@endsection

@section('breadcrumb')
<ol class="breadcrumb">
  <li><a href="{{ route('admin.dashboard') }}"><i class="fa fa-dashboard"></i> Home</a></li>
  <li class="active">Quản Lý Đơn Hàng</li>
</ol>
@endsection

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="box">
      <div class="box-header with-border">
        <div class="row">
          <div class="col-md-5 col-sm-6 col-xs-6">
            <div id="search-input" class="input-group">
              <span class="input-group-addon"><i class="fa fa-search" aria-hidden="true"></i></span>
              <input type="text" class="form-control" placeholder="search...">
            </div>
          </div>
          <div class="col-md-7 col-sm-6 col-xs-6">
            <div class="btn-group pull-right">
              <a href="{{ route('admin.order.index') }}" class="btn btn-flat btn-primary" title="Refresh">
                <i class="fa fa-refresh"></i><span class="hidden-xs"> Refresh</span>
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="box-body">
        <table id="order-table" class="table table-hover" style="width:100%; min-width:1100px;">
          <thead>
            <tr>
              <th>ID</th>
              <th>Mã Đơn Hàng</th>
              <th>Tài Khoản</th>
              <th>Tên</th>
              <th>Email</th>
              <th>Điện Thoại</th>
              <th>Phương Thức</th>
              <th>Trạng thái</th>
              <th>Ngày Tạo</th>
              <th>Tác Vụ</th>
            </tr>
          </thead>
          <tbody>
            @foreach($orders as $order)
              @php
                $nexts  = $order->availableNextStatuses();
                $locked = (count($nexts) === 1 && (int)$nexts[0] === (int)$order->status); // ví dụ: Hoàn thành/Đã huỷ/Hoàn tiền
              @endphp
              <tr data-id="{{ $order->id }}">
                <td class="text-center">{{ $order->id }}</td>
                <td>{{ '#'.$order->order_code }}</td>
                <td>
                  <a href="{{ route('admin.user_show', ['id' => $order->user->id]) }}" title="{{ $order->user->name }}">
                    {{ $order->user->name }}
                  </a>
                </td>
                <td>{{ $order->name }}</td>
                <td>{{ $order->email }}</td>
                <td>{{ $order->phone }}</td>
                <td>{{ $order->payment_method->name }}</td>

                {{-- STATUS CELL --}}
                <td>
                  <div class="status-cell">
                    <div class="status-label" data-role="label">{!! $order->status_label !!}</div>
                    <select class="form-control status-select" data-role="status-select" @if($locked) disabled @endif>
                      @foreach($nexts as $val)
                        <option value="{{ $val }}" {{ (int)$val === (int)$order->status ? 'selected="selected"' : '' }}>
                          {{ \App\Models\Order::STATUS[$val]['label'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </td>

                <td>{{ \Carbon\Carbon::parse($order->created_at)->format('d/m/Y') }}</td>
                <td>
                  <a href="{{ route('admin.order.show', ['id' => $order->id]) }}" class="btn btn-icon btn-sm btn-primary tip" title="Chi Tiết">
                    <i class="fa fa-eye"></i>
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>
@endsection

@section('embed-js')
<script src="{{ asset('AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('AdminLTE/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
<script src="{{ asset('AdminLTE/bower_components/jquery-slimscroll/jquery.slimscroll.min.js') }}"></script>
<script src="{{ asset('AdminLTE/bower_components/fastclick/lib/fastclick.js') }}"></script>
<script src="https://cdn.datatables.net/plug-ins/1.10.20/sorting/date-euro.js"></script>
@endsection

@section('custom-js')
<script>
$(function () {
  var table = $('#order-table').DataTable({
    language: {
      zeroRecords: "Không tìm thấy kết quả phù hợp",
      info: "Hiển thị trang <b>_PAGE_/_PAGES_</b> của <b>_TOTAL_</b> đơn hàng",
      infoEmpty: "Hiển thị trang <b>1/1</b> của <b>0</b> đơn hàng",
      infoFiltered: "(Tìm kiếm từ <b>_MAX_</b> đơn hàng)",
      emptyTable: "Không có dữ liệu đơn hàng",
    },
    lengthChange: false,
    autoWidth: false,
    order: [],
    dom: '<"table-responsive"t><<"row"<"col-md-6 col-sm-6"i><"col-md-6 col-sm-6"p>>>',
    drawCallback: function(settings) {
      var api = this.api();
      if (api.page.info().pages <= 1) {
        $('#'+ $(this).attr('id') + '_paginate').hide();
      }
    }
  });

  $('#search-input input').on('keyup', function() {
    table.search(this.value).draw();
  });

  // ===== AJAX đổi trạng thái =====
  $.ajaxSetup({ headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'} });

  $('#order-table').on('change', '[data-role="status-select"]', function(){
    const $row = $(this).closest('tr');
    const id   = $row.data('id');
    const val  = $(this).val();
    const $sel = $(this);
    const $lbl = $row.find('[data-role="label"]');

    $sel.prop('disabled', true);

    $.ajax({
      url: "{{ route('admin.order.updateStatus', ['order' => 'ORDER_ID']) }}".replace('ORDER_ID', id),
      type: 'PATCH',
      data: { status: val },
      success: function(resp){
        if(resp.ok){
          $lbl.html(resp.label);
          $sel.empty();
          resp.options.forEach(function(op){
            $sel.append($('<option>', {value: op.value, text: op.text, selected: op.selected}));
          });
          // Khoá nếu chỉ còn 1 option (trạng thái cuối)
          if ($sel.find('option').length === 1 || resp.locked) $sel.prop('disabled', true);
          else $sel.prop('disabled', false);
        } else {
          alert(resp.message || 'Không thể cập nhật trạng thái.');
          $sel.prop('disabled', false);
        }
      },
      error: function(xhr){
        const msg = (xhr.responseJSON && xhr.responseJSON.message)
          ? xhr.responseJSON.message
          : (xhr.status===403 ? 'Đơn đã ở trạng thái cuối, không thể thay đổi.' : 'Có lỗi xảy ra.');
        alert(msg);
        $sel.prop('disabled', false);
      }
    });
  });
});
</script>
@endsection
