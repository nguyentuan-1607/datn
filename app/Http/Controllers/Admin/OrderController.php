<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Danh sách đơn hàng (ẩn trạng thái 0: Mới tạo nếu bạn đang dùng lọc này)
     */
    public function index()
    {
        $orders = Order::select(
                'id',
                'user_id',
                'payment_method_id',
                'order_code',
                'name',
                'email',
                'phone',
                'status',        // cần cho badge + dropdown
                'created_at'
            )
            ->where('status', '<>', 0)
            ->with([
                'user:id,name',
                'payment_method:id,name',
            ])
            ->latest()
            ->get();

        return view('admin.order.index', ['orders' => $orders]);
    }

    /**
     * Chi tiết đơn hàng
     */
    public function show($id)
    {
        $order = Order::select(
                'id',
                'user_id',
                'payment_method_id',
                'order_code',
                'name',
                'email',
                'phone',
                'address',
                'status',        // cần cho badge
                'created_at',
                'updated_at'
            )
            ->where([['status', '<>', 0], ['id', $id]])
            ->with([
                'user:id,name,email,phone,address',
                'payment_method:id,name,describe',
                'order_details:id,order_id,product_detail_id,quantity,price',
                'order_details.product_detail:id,product_id,color',
                'order_details.product_detail.product:id,name,image,sku_code',
            ])
            ->first();

        if (!$order) {
            abort(404);
        }

        return view('admin.order.show', ['order' => $order]);
    }

    /**
     * Cập nhật trạng thái (AJAX: PATCH)
     * Route gợi ý:
     *   Route::patch('admin/orders/{order}/status', [OrderController::class,'updateStatus'])
     *        ->name('admin.order.updateStatus');
     */
    public function updateStatus(Request $request, Order $order)
    {
        // Không cho đổi nếu khóa (Hoàn thành/Đã hủy/Hoàn tiền)
        if (in_array((int)$order->status, [1, 5, 6], true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Đơn đã ở trạng thái cuối, không thể thay đổi.'
            ], 403);
        }

        // Chỉ cho phép các giá trị có trong STATUS
        $allowedValues = array_keys(Order::STATUS);

        $data = $request->validate([
            'status' => ['required', 'integer', 'in:'.implode(',', $allowedValues)],
        ]);

        $new = (int) $data['status'];

        // Luật chuyển đổi
        $allowedNext = Order::TRANSITIONS[$order->status] ?? [];
        if ($new !== (int)$order->status && !in_array($new, $allowedNext, true)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Không cho phép chuyển trạng thái từ hiện tại sang trạng thái đã chọn.',
            ], 422);
        }

        // Lưu
        $order->status = $new;
        $order->save();

        // Build options mới cho dropdown (bao gồm chính trạng thái hiện tại)
        $options = [];
        foreach ($order->availableNextStatuses() as $val) {
            $options[] = [
                'value'    => $val,
                'text'     => Order::STATUS[$val]['label'],
                'selected' => $val === (int)$order->status,
            ];
        }

        return response()->json([
            'ok'      => true,
            'label'   => $order->status_label, // HTML badge
            'options' => $options,             // options cho <select>
            'locked'  => in_array((int)$order->status, [1, 5, 6], true), // để front-end disable select nếu cần
        ]);
    }
}
