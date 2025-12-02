<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatAIController extends Controller
{
    public function chat(Request $request)
    {
        $message = $request->input('message');

        if (!$message) {
            return response()->json([
                'reply' => 'Bạn chưa nhập nội dung cần tư vấn.'
            ]);
        }

        $apiKey = env('OPENAI_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'reply' => 'Server chưa cấu hình OPENAI_API_KEY trong file .env.'
            ]);
        }

        /**
         * 1️⃣ LẤY DỮ LIỆU ĐIỆN THOẠI TỪ CSDL CỦA BẠN
         *    Join: producers + products + product_details
         */
        try {
            $products = DB::table('product_details as pd')
                ->join('products as p', 'pd.product_id', '=', 'p.id')
                ->join('producers as pr', 'p.producer_id', '=', 'pr.id')
                ->select(
                    'p.id',
                    'p.name',
                    'pr.name as brand',
                    'p.RAM',
                    'p.ROM',
                    'p.CPU',
                    'p.OS',
                    'p.pin',
                    'pd.color',
                    'pd.sale_price',
                    'pd.promotion_price'
                )
                ->orderBy('pd.sale_price', 'asc')
                ->get();
        } catch (\Exception $e) {
            // Nếu lỗi DB thì vẫn trả lời cho user biết, không chết trắng
            return response()->json([
                'reply' => 'Lỗi truy vấn dữ liệu sản phẩm từ CSDL: ' . $e->getMessage()
            ]);
        }

        if ($products->isEmpty()) {
            return response()->json([
                'reply' => 'Hiện chưa có sản phẩm nào trong hệ thống để tư vấn.'
            ]);
        }

        // 2️⃣ FORMAT DỮ LIỆU SẢN PHẨM THÀNH TEXT RÕ RÀNG GỬI CHO AI
        $productLines = [];
        foreach ($products as $p) {
            $giaBan = number_format($p->sale_price, 0, ',', '.') . '₫';
            $giaKM  = $p->promotion_price
                ? number_format($p->promotion_price, 0, ',', '.') . '₫'
                : 'Không khuyến mãi';

            $productLines[] =
                "ID: {$p->id} | Tên: {$p->name} | Hãng: {$p->brand} | Màu: {$p->color} | ".
                "RAM: {$p->RAM}GB | ROM: {$p->ROM}GB | CPU: {$p->CPU} | HĐH: {$p->OS} | Pin: {$p->pin} | ".
                "Giá bán: {$giaBan} | Giá khuyến mãi: {$giaKM}";
        }

        $productContext = implode("\n", $productLines);

        /**
         * 3️⃣ TẠO PAYLOAD GỬI LÊN OPENAI
         *    - System: bắt buộc CHỈ tư vấn trong danh sách sản phẩm trên
         *    - Thêm context sản phẩm từ CSDL
         */
        $payload = [
            'model'    => 'gpt-4o-mini',
            'messages' => [
                [
                    'role'    => 'system',
                    'content' =>
                        "Bạn là chuyên gia tư vấn mua điện thoại cho một shop bán lẻ.\n".
                        "Chỉ được tư vấn dựa trên danh sách sản phẩm cung cấp bên dưới (dữ liệu thật từ CSDL cửa hàng).\n".
                        "Khi tư vấn:\n".
                        "- Luôn nêu rõ TÊN máy và HÃNG.\n".
                        "- Ưu tiên gợi ý 2–3 mẫu phù hợp nhất với nhu cầu khách.\n".
                        "- Ghi rõ giá khuyến mãi (nếu có), nếu không có thì dùng giá bán.\n".
                        "- Không được bịa thêm mẫu máy khác ngoài danh sách. Nếu không phù hợp, hãy nói không có mẫu phù hợp."
                ],
                [
                    'role'    => 'system',
                    'content' =>
                        "📦 Danh sách sản phẩm hiện có trong cửa hàng (lấy từ CSDL webbandienthoai_2025):\n".
                        $productContext
                ],
                [
                    'role'    => 'user',
                    'content' =>
                        "Nhu cầu của khách: {$message}\n".
                        "Hãy chọn trong danh sách sản phẩm trên và gợi ý chi tiết (lý do chọn, phù hợp chơi game / chụp ảnh / pin / thương hiệu...)."
                ],
            ],
            'temperature' => 0.7,
        ];

        /**
         * 4️⃣ GỌI API OPENAI (ĐÃ TẮT VERIFY SSL CHO XAMPP LOCAL)
         */
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,

            // ⚠️ CHỈ DÙNG CHO LOCALHOST – tránh lỗi CAfile / certificate
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $result     = curl_exec($ch);
        $curlErrNo  = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo) {
            return response()->json([
                'reply' => 'Không gọi được tới OpenAI (cURL): ' . $curlErrMsg
            ]);
        }

        $data = json_decode($result, true);

        if ($statusCode >= 400) {
            $errorMsg = $data['error']['message'] ?? 'Lỗi không xác định từ OpenAI.';
            return response()->json([
                'reply' => 'OpenAI trả lỗi (' . $statusCode . '): ' . $errorMsg
            ]);
        }

        $reply = $data['choices'][0]['message']['content'] ?? 'Xin lỗi, mình chưa có câu trả lời phù hợp.';

        return response()->json([
            'reply' => nl2br(e($reply)),
        ]);
    }
}
