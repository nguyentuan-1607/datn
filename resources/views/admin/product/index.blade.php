@extends('admin.layouts.master')

@section('title', 'Quản Lý Sản Phẩm')

@section('embed-css')
<link rel="stylesheet" href="{{ asset('AdminLTE/bower_components/datatables.net-bs/css/dataTables.bootstrap.min.css') }}">
@endsection

@section('custom-css')
<style>
  #product-table td,
  #product-table th {
    vertical-align: middle !important;
  }
  #product-table span.status-label {
    display: block;
    width: 85px;
    text-align: center;
    padding: 2px 0px;
  }
  #search-input span.input-group-addon {
    padding: 0;
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 34px;
    border: none;
    background: none;
  }
  #search-input span.input-group-addon i {
    font-size: 18px;
    line-height: 34px;
    width: 34px;
    color: #00a65a;
  }
  #search-input input {
    position: static;
    width: 100%;
    font-size: 15px;
    line-height: 22px;
    padding: 5px 5px 5px 34px;
    float: none;
    height: unset;
    border-color: #fbfbfb;
    box-shadow: none;
    background-color: #e8f0fe;
    border-radius: 5px;
  }

  /* Hiển thị số lượng gọn đẹp */
  .qty-badge{
    display:inline-block;
    min-width:54px;
    padding:3px 8px;
    border-radius:12px;
    text-align:center;
    font-weight:600;
    background:#f1f5f9;
    color:#0f172a;
    font-size:13px;
  }
  .qty-badge.on { background:#ecfdf5; color:#065f46; }   /* >0 */
  .qty-badge.off{ background:#fef2f2; color:#991b1b; }   /* =0 */

  /* Box phân tích nhập hàng – ĐỂ DƯỚI CÙNG & CHỮ ĐẬM HƠN */
  .ai-insight-box{
    margin: 18px 0 0;
    padding: 12px 14px;
    border-radius: 6px;
    background: #ecfdf5;
    border: 1px dashed #16a34a;
    font-size: 13.5px;
    line-height: 1.6;
    color: #022c22;
    font-weight: 500; /* chữ toàn box đậm hơn bình thường */
  }
  .ai-insight-box h4{
    margin-top: 0;
    margin-bottom: 8px;
    font-size: 14.5px;
    font-weight: 800;    /* tiêu đề đậm rõ hơn */
    color: #064e3b;
  }
  .ai-insight-box ul{
    padding-left: 18px;
    margin-bottom: 6px;
  }
  .ai-insight-box li{
    margin-bottom: 4px;
    font-weight: 600;    /* nội dung list đậm hơn chút */
  }
  .ai-insight-box p{
    margin-bottom: 4px;
    font-weight: 600;    /* đoạn văn trong phân tích cũng đậm hơn */
  }
  .ai-insight-box strong{
    font-weight: 700;
  }
</style>
@endsection

@section('breadcrumb')
<ol class="breadcrumb">
  <li><a href="{{ route('admin.dashboard') }}"><i class="fa fa-dashboard"></i> Home</a></li>
  <li class="active">Quản Lý Sản Phẩm</li>
</ol>
@endsection

@section('content')

  <!-- Main row -->
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
                <a href="{{ route('admin.product.index') }}" class="btn btn-flat btn-primary" title="Refresh" style="margin-right: 5px;">
                  <i class="fa fa-refresh"></i><span class="hidden-xs"> Refresh</span>
                </a>
                <a href="{{ route('admin.product.new') }}" class="btn btn-success btn-flat" title="New Product">
                  <i class="fa fa-plus" aria-hidden="true"></i><span class="hidden-xs"> New Product</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="box-body">

          <table id="product-table" class="table table-hover" style="width:100%; min-width: 1080px;">
            <thead>
              <tr>
                <th data-width="10px">ID</th>
                <th data-orderable="false" data-width="75px">Hình Ảnh</th>
                <th data-orderable="false" data-width="85px">Mã Sản Phẩm</th>
                <th data-orderable="false">Tên Sản Phẩm</th>
                <th data-width="90px">Hãng Sản Xuất</th>
                <th data-width="85px">Hệ Điều Hành</th>
                <th data-width="60px">Đánh Giá</th>
                <th data-width="90px" class="text-center">Số Lượng</th>
                <th data-width="60px" data-type="date-euro">Ngày Tạo</th>
                <th data-width="66px">Trạng Thái</th>
                <th data-orderable="false" data-width="70px">Tác Vụ</th>
              </tr>
            </thead>
            <tbody>
              @foreach($products as $product)
                @php
                  // Dùng thuộc tính đã gán sẵn từ controller, fallback sang sum() nếu cần
                  $totalQty = isset($product->total_quantity)
                                ? (int)$product->total_quantity
                                : (int)(($product->product_details ?? collect())->sum('quantity'));
                @endphp
                <tr>
                  <td class="text-center">
                    {{ $product->id }}
                  </td>
                  <td>
                    <div style="background-image: url('{{ Helper::get_image_product_url($product->image) }}'); padding-top: 100%; background-size: contain; background-repeat: no-repeat; background-position: center;"></div>
                  </td>
                  <td>
                    #<a class="text-left" href="{{ route('product_page', ['id' => $product->id]) }}" title="{{ $product->name }}">{{ $product->sku_code }}</a>
                  </td>
                  <td>
                    <a class="text-left" href="{{ route('product_page', ['id' => $product->id]) }}" title="{{ $product->name }}">{{ $product->name }}</a>
                  </td>
                  <td>{{ $product->producer->name }}</td>
                  <td>{{ $product->OS }}</td>
                  <td>{{ $product->rate }}/5 Điểm</td>

                  {{-- CỘT SỐ LƯỢNG (MỚI) --}}
                  <td class="text-center">
                    <span class="qty-badge {{ $totalQty > 0 ? 'on' : 'off' }}">{{ number_format($totalQty,0,',','.') }}</span>
                  </td>

                  <td>{{ \Carbon\Carbon::parse($product->created_at)->format('d/m/Y') }}</td>
                  <td>
                    @if($totalQty > 0)
                      <span class="label-success status-label">Còn Hàng</span>
                    @else
                      <span class="label-danger status-label">Hết Hàng</span>
                    @endif
                  </td>
                  <td>
                    <a href="{{ route('admin.product.edit', ['id' => $product->id]) }}" class="btn btn-icon btn-sm btn-primary tip" title="Chỉnh Sửa">
                      <i class="fa fa-pencil" aria-hidden="true"></i>
                    </a>
                    <a href="javascript:void(0);" data-id="{{ $product->id }}" class="btn btn-icon btn-sm btn-danger deleteDialog tip" title="Xóa" data-url="{{ route('admin.product.delete') }}">
                      <i class="fa fa-trash"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

          {{-- 🔹 Box phân tích sản phẩm nên nhập / không nên nhập / bán chạy / sắp hết / màu sắc – ĐẶT Ở CUỐI --}}
          <div class="ai-insight-box">
            <h4>🧠 Phân tích tồn kho & gợi ý nhập hàng để tăng doanh thu</h4>
            <div id="ai-product-insight">
              {!! $productInsightsHtml ?? '<p>Chưa có dữ liệu phân tích.</p>' !!}
            </div>
          </div>

        </div>
        <!-- /.box-body -->
      </div>
      <!-- /.box -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->
@endsection

@section('embed-js')
  <!-- DataTables -->
  <script src="{{ asset('AdminLTE/bower_components/datatables.net/js/jquery.dataTables.min.js') }}"></script>
  <script src="{{ asset('AdminLTE/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js') }}"></script>
  <!-- SlimScroll -->
  <script src="{{ asset('AdminLTE/bower_components/jquery-slimscroll/jquery.slimscroll.min.js') }}"></script>
  <!-- FastClick -->
  <script src="{{ asset('AdminLTE/bower_components/fastclick/lib/fastclick.js') }}"></script>
  <script src="https://cdn.datatables.net/plug-ins/1.10.20/sorting/date-euro.js"></script>
@endsection

@section('custom-js')
<script>
  $(function () {
    var table = $('#product-table').DataTable({
      "language": {
        "zeroRecords":    "Không tìm thấy kết quả phù hợp",
        "info":           "Hiển thị trang <b>_PAGE_/_PAGES_</b> của <b>_TOTAL_</b> sản phẩm",
        "infoEmpty":      "Hiển thị trang <b>1/1</b> của <b>0</b> sản phẩm",
        "infoFiltered":   "(Tìm kiếm từ <b>_MAX_</b> sản phẩm)",
        "emptyTable": "Không có dữ liệu sản phẩm",
      },
      "lengthChange": false,
      "autoWidth": false,
      "order": [],
      "dom": '<"table-responsive"t><<"row"<"col-md-6 col-sm-6"i><"col-md-6 col-sm-6"p>>>',
      "drawCallback": function(settings) {
        var api = this.api();
        if (api.page.info().pages <= 1) {
          $('#'+ $(this).attr('id') + '_paginate').hide();
        }
      }
    });

    $('#search-input input').on('keyup', function() {
        table.search(this.value).draw();
    });
  });

  $(document).ready(function(){
    $(".deleteDialog").click(function() {
      var product_id = $(this).attr('data-id');
      var url = $(this).attr('data-url');

      Swal.fire({
        type: 'question',
        title: 'Thông báo',
        text: 'Bạn có chắc muốn xóa sản phẩm này?',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#16A34A',
        showLoaderOnConfirm: true,
        preConfirm: () => {
          return fetch(url, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({'product_id': product_id}),
          })
          .then(response => {
            if (!response.ok) { throw new Error(response.statusText); }
            return response.json();
          })
          .catch(error => {
            Swal.showValidationMessage(error);
            Swal.update({
              type: 'error',
              title: 'Lỗi!',
              text: '',
              showConfirmButton: false,
              cancelButtonText: 'Ok',
            });
          })
        },
      }).then((result) => {
        if (result.value) {
          Swal.fire({
            type: result.value.type,
            title: result.value.title,
            text: result.value.content,
          }).then((result) => {
            if (result.value) location.reload(true);
          });
        }
      })
    });
  });
</script>
@endsection
