<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


use App\Models\Product;
use App\Models\Producer;
use App\Models\Promotion;
use App\Models\ProductDetail;
use App\Models\ProductImage;
use App\Models\OrderDetail;

class ProductController extends Controller
{

  public function index()
  {
    // Lấy sản phẩm + hãng + chi tiết (để tính tồn kho, màu sắc,...)
    $products = Product::select('id', 'producer_id', 'name', 'image', 'sku_code', 'OS', 'rate', 'created_at')
      ->whereHas('product_details', function (Builder $query) {
        $query->where('import_quantity', '>', 0);
      })
      ->with([
        'producer' => function ($query) {
          $query->select('id', 'name');
        },
        'product_details' => function ($query) {
          $query->select(
            'id',
            'product_id',
            'color',
            'import_quantity',
            'quantity',
            'sale_price'
          )->where('import_quantity', '>', 0);
        }
      ])
      ->withCount([
        'product_details' => function (Builder $query) {
          $query->where([['import_quantity', '>', 0], ['quantity', '>', 0]]);
        }
      ])
      ->latest()
      ->get();

    // 🔹 Phân tích nội bộ: nên nhập / không nên nhập / bán chạy / sắp hết / màu sắc
    $baseInsightsHtml = $this->buildProductInsights($products);

    // 🔹 Gọi ChatGPT phân tích thêm dựa trên dữ liệu thật
    $gptInsightsHtml  = $this->buildProductInsightsByGpt($products, $baseInsightsHtml);

    // Gộp hai phần: nội bộ + ChatGPT
    $productInsightsHtml = $baseInsightsHtml;
    if ($gptInsightsHtml) {
      $productInsightsHtml .= '<hr style="margin:8px 0;">' . $gptInsightsHtml;
    }

    return view('admin.product.index')->with([
      'products'            => $products,
      'productInsightsHtml' => $productInsightsHtml,
    ]);
  }

  /**
   * Phân tích danh sách sản phẩm để gợi ý nhập hàng, xả hàng, màu sắc,...
   * => Dùng logic nội bộ (rule-based) để ra các nhóm gợi ý.
   */
  protected function buildProductInsights($products)
  {
    $shouldImport     = []; // nên ưu tiên nhập thêm (bán tốt + sắp hết)
    $shouldNotImport  = []; // không nên nhập thêm / tồn kho lớn, bán chậm
    $hotProducts      = []; // sản phẩm bán chạy
    $lowStockProducts = []; // sắp hết hàng
    $slowProducts     = []; // bán chậm
    $colorData        = []; // thống kê màu sắc

    foreach ($products as $product) {
      $details = $product->product_details ?? collect();

      $totalImport = (int) $details->sum('import_quantity');
      $totalStock  = (int) $details->sum('quantity');
      $sold        = max($totalImport - $totalStock, 0);

      // Gán thêm thuộc tính để view + GPT dùng được
      $product->import_quantity_total = $totalImport;
      $product->total_quantity        = $totalStock;
      $product->sold_quantity         = $sold;

      if ($totalImport <= 0) {
        continue;
      }

      $stockRatio = $totalStock / max($totalImport, 1); // tỷ lệ tồn / nhập

      // 🔸 Nhóm nên nhập thêm: đã bán >= 5, tồn còn ít, tỷ lệ tồn < 30%
      if ($sold >= 5 && $stockRatio <= 0.3 && $totalStock <= 10) {
        $shouldImport[] = [
          'name'     => $product->name,
          'sku'      => $product->sku_code,
          'producer' => optional($product->producer)->name,
          'stock'    => $totalStock,
          'sold'     => $sold,
        ];
      }

      // 🔸 Nhóm không nên nhập thêm: bán rất chậm, tồn nhiều
      if ($sold <= 1 && $totalStock >= 15) {
        $shouldNotImport[] = [
          'name'     => $product->name,
          'sku'      => $product->sku_code,
          'producer' => optional($product->producer)->name,
          'stock'    => $totalStock,
          'sold'     => $sold,
        ];
      }

      // 🔸 Sản phẩm bán chạy: đã bán >= 10
      if ($sold >= 10) {
        $hotProducts[] = [
          'name'     => $product->name,
          'sku'      => $product->sku_code,
          'producer' => optional($product->producer)->name,
          'stock'    => $totalStock,
          'sold'     => $sold,
        ];
      }

      // 🔸 Sắp hết hàng: còn rất ít nhưng có bán
      if ($totalStock > 0 && $totalStock <= 3 && $sold > 0) {
        $lowStockProducts[] = [
          'name'     => $product->name,
          'sku'      => $product->sku_code,
          'producer' => optional($product->producer)->name,
          'stock'    => $totalStock,
          'sold'     => $sold,
        ];
      }

      // 🔸 Bán chậm: tỷ lệ tồn > 70% và đã nhập tương đối
      if ($totalImport >= 10 && $stockRatio >= 0.7 && $sold <= 3) {
        $slowProducts[] = [
          'name'     => $product->name,
          'sku'      => $product->sku_code,
          'producer' => optional($product->producer)->name,
          'stock'    => $totalStock,
          'sold'     => $sold,
        ];
      }

      // 🔸 Thống kê màu sắc
      foreach ($details as $detail) {
        $color = $detail->color ?: 'Khác';
        $imp   = (int) $detail->import_quantity;
        $stk   = (int) $detail->quantity;
        $sld   = max($imp - $stk, 0);

        if (!isset($colorData[$color])) {
          $colorData[$color] = [
            'import' => 0,
            'stock'  => 0,
            'sold'   => 0,
          ];
        }

        $colorData[$color]['import'] += $imp;
        $colorData[$color]['stock']  += $stk;
        $colorData[$color]['sold']   += $sld;
      }
    }

    // Sắp xếp các nhóm cho đẹp
    usort($shouldImport, function ($a, $b) {
      return $b['sold'] <=> $a['sold'];
    });
    usort($shouldNotImport, function ($a, $b) {
      return $b['stock'] <=> $a['stock'];
    });
    usort($hotProducts, function ($a, $b) {
      return $b['sold'] <=> $a['sold'];
    });
    usort($lowStockProducts, function ($a, $b) {
      return $a['stock'] <=> $b['stock'];
    });
    usort($slowProducts, function ($a, $b) {
      return $b['stock'] <=> $a['stock'];
    });

    // Sắp xếp màu theo số lượng bán
    uasort($colorData, function ($a, $b) {
      return $b['sold'] <=> $a['sold'];
    });

    // 🔹 Build HTML hiển thị trong view
    $html  = '<div style="font-size:13px; line-height:1.5;">';

    // 1. Sản phẩm nên nhập thêm
    $html .= '<p><strong>1. Sản phẩm nên ưu tiên nhập thêm (bán tốt, sắp hết hàng)</strong></p>';
    if (count($shouldImport)) {
      $html .= '<ul>';
      foreach (array_slice($shouldImport, 0, 10) as $p) {
        $html .= '<li>'
          . e($p['name']) . ' (Mã: ' . e($p['sku']) . ', Hãng: ' . e($p['producer'] ?? 'N/A') . ')'
          . ' – Tồn kho: <strong>' . $p['stock'] . '</strong>, đã bán khoảng <strong>' . $p['sold'] . '</strong> máy.'
          . ' ⇒ Nên nhập thêm để tránh cháy hàng và mất doanh thu.'
          . '</li>';
      }
      $html .= '</ul>';
    } else {
      $html .= '<p style="margin-left:10px;">Hiện chưa có sản phẩm nào vừa bán tốt vừa sắp hết hàng theo dữ liệu tồn kho.</p>';
    }

    // 2. Sản phẩm không nên nhập thêm
    $html .= '<p style="margin-top:10px;"><strong>2. Sản phẩm không nên nhập thêm / dễ tồn kho</strong></p>';
    if (count($shouldNotImport) || count($slowProducts)) {
      $html .= '<ul>';
      foreach (array_slice($shouldNotImport, 0, 10) as $p) {
        $html .= '<li>'
          . e($p['name']) . ' (Mã: ' . e($p['sku']) . ')'
          . ' – Tồn kho: <strong>' . $p['stock'] . '</strong>, đã bán rất ít (≈ <strong>' . $p['sold'] . '</strong> máy).'
          . ' ⇒ Hạn chế nhập thêm, ưu tiên chạy khuyến mãi, combo, xả hàng.'
          . '</li>';
      }
      foreach (array_slice($slowProducts, 0, 5) as $p) {
        $html .= '<li>'
          . e($p['name']) . ' (Mã: ' . e($p['sku']) . ')'
          . ' – Tỉ lệ tồn kho cao, bán chậm.'
          . ' ⇒ Cân nhắc giảm giá, tặng kèm phụ kiện, hoặc dừng nhập mẫu này.'
          . '</li>';
      }
      $html .= '</ul>';
    } else {
      $html .= '<p style="margin-left:10px;">Chưa phát hiện mẫu nào tồn kho quá lớn và bán quá chậm theo tiêu chí đang dùng.</p>';
    }

    // 3. Sản phẩm bán chạy
    $html .= '<p style="margin-top:10px;"><strong>3. Sản phẩm bán chạy (Top trending)</strong></p>';
    if (count($hotProducts)) {
      $html .= '<ul>';
      foreach (array_slice($hotProducts, 0, 10) as $p) {
        $html .= '<li>'
          . e($p['name']) . ' (Mã: ' . e($p['sku']) . ', Hãng: ' . e($p['producer'] ?? 'N/A') . ')'
          . ' – Ước tính đã bán: <strong>' . $p['sold'] . '</strong> máy, tồn kho còn <strong>' . $p['stock'] . '</strong>.'
          . ' ⇒ Nên duy trì lượng nhập ổn định, kết hợp đẩy mạnh quảng cáo / upsell phụ kiện.'
          . '</li>';
      }
      $html .= '</ul>';
    } else {
      $html .= '<p style="margin-left:10px;">Chưa có sản phẩm nào đạt ngưỡng "bán chạy" (đã bán ≥ 10 máy) theo dữ liệu thống kê.</p>';
    }

    // 4. Sản phẩm sắp hết hàng
    $html .= '<p style="margin-top:10px;"><strong>4. Sản phẩm sắp hết hàng (cần nhập gấp)</strong></p>';
    if (count($lowStockProducts)) {
      $html .= '<ul>';
      foreach (array_slice($lowStockProducts, 0, 10) as $p) {
        $html .= '<li>'
          . e($p['name']) . ' (Mã: ' . e($p['sku']) . ')'
          . ' – Tồn kho còn <strong>' . $p['stock'] . '</strong> máy, trong khi đã bán khoảng <strong>' . $p['sold'] . '</strong>.'
          . ' ⇒ Cần cân nhắc nhập thêm sớm để không bị gián đoạn bán hàng.'
          . '</li>';
      }
      $html .= '</ul>';
    } else {
      $html .= '<p style="margin-left:10px;">Hiện chưa có mẫu nào rơi vào trạng thái "sắp hết hàng" theo tiêu chí ≤ 3 máy.</p>';
    }

    // 5. Màu sắc nên ưu tiên nhập
    $html .= '<p style="margin-top:10px;"><strong>5. Màu sắc nên ưu tiên nhập để tăng doanh thu</strong></p>';
    if (count($colorData)) {
      $html .= '<ul>';
      $topColors = array_slice($colorData, 0, 5, true);
      foreach ($topColors as $color => $stat) {
        $html .= '<li>'
          . 'Màu <strong>' . e($color) . '</strong>: đã bán ước tính <strong>' . $stat['sold'] . '</strong> máy'
          . ', tồn kho khoảng <strong>' . $stat['stock'] . '</strong> / tổng nhập <strong>' . $stat['import'] . '</strong>.'
          . ' ⇒ Nên ưu tiên nhập thêm biến thể màu này trong các đợt nhập hàng tới.'
          . '</li>';
      }
      $html .= '</ul>';
    } else {
      $html .= '<p style="margin-left:10px;">Chưa có dữ liệu màu sắc đủ để phân tích.</p>';
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * 🔥 Gọi ChatGPT để phân tích thêm dựa trên dữ liệu tồn kho + bán ra
   * Không tạo route mới, gọi thẳng trong controller.
   */
    /**
   * 🔥 Gọi ChatGPT để phân tích thêm dựa trên dữ liệu tồn kho + bán ra
   */
  protected function buildProductInsightsByGpt($products, string $baseInsightsHtml = '')
{
    // Lấy API key từ config hoặc env
    $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');
    if (!$apiKey) {
        return '<p style="font-size:12px; margin-top:6px;"><em>Chưa cấu hình API key cho ChatGPT. Đang hiển thị phân tích nội bộ.</em></p>';
    }

    // Chuẩn hóa data gọn cho GPT
    $summaryData = $products->map(function ($p) {
        $details = $p->product_details ?? collect();

        $totalImport = (int)($p->import_quantity_total ?? $details->sum('import_quantity'));
        $totalStock  = (int)($p->total_quantity ?? $details->sum('quantity'));
        $sold        = (int)($p->sold_quantity ?? max($totalImport - $totalStock, 0));

        return [
            'id'           => $p->id,
            'name'         => $p->name,
            'sku'          => $p->sku_code,
            'producer'     => optional($p->producer)->name,
            'os'           => $p->OS,
            'rate'         => $p->rate,
            'total_import' => $totalImport,
            'total_stock'  => $totalStock,
            'sold'         => $sold,
            'colors'       => $details->pluck('color')->filter()->unique()->values()->all(),
        ];
    })->values()->all();

    $prompt = "Bạn là chuyên gia phân tích kinh doanh cho cửa hàng bán điện thoại.\n"
        ."Dưới đây là dữ liệu tồn kho và bán ra của các sản phẩm (JSON):\n\n"
        . json_encode($summaryData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        . "\n\nHãy phân tích ngắn gọn (tiếng Việt, khoảng 6–10 gạch đầu dòng):\n"
        . "- Sản phẩm nào nên nhập thêm, lý do (bán chạy, sắp hết...)\n"
        . "- Sản phẩm nào nên hạn chế nhập, lý do (tồn nhiều, bán chậm...)\n"
        . "- Gợi ý nhóm sản phẩm / phân khúc nên đẩy mạnh marketing.\n"
        . "- Gợi ý chung để tối ưu doanh thu và quay vòng vốn.\n"
        . "Tránh nhắc lại y nguyên dữ liệu số liệu; hãy diễn giải, nêu insight.";

    // Payload gửi lên OpenAI
    $payload = [
        'model' => 'gpt-4o-mini', // thay bằng model bạn dùng
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là trợ lý phân tích dữ liệu bán hàng, trả lời bằng tiếng Việt, súc tích, kiểu bullet list.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.4,
    ];

    try {
        // ✅ Gọi API bằng cURL thuần PHP
        $ch = curl_init('https://api.openai.com/v1/chat/completions');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,

            // ⬇⬇⬇ BỎ KIỂM TRA SSL – KHẮC PHỤC LỖI CAfile TRÊN XAMPP LOCAL ⬇⬇⬇
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return '<p style="font-size:12px; margin-top:6px;"><em>Lỗi khi gọi ChatGPT (cURL): '
                . e($error) . '. Đang hiển thị phân tích nội bộ.</em></p>';
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            return '<p style="font-size:12px; margin-top:6px;"><em>Không gọi được ChatGPT (HTTP '
                . (int)$httpCode . '). Đang hiển thị phân tích nội bộ.</em></p>';
        }

        $body = json_decode($result, true);
        $text = trim($body['choices'][0]['message']['content'] ?? '');

        if ($text === '') {
            return '<p style="font-size:12px; margin-top:6px;"><em>ChatGPT không trả về nội dung. Đang hiển thị phân tích nội bộ.</em></p>';
        }

        // Đưa text GPT vào khung HTML, giữ xuống dòng
        $html  = '<p><strong>🔎 Phân tích bổ sung từ ChatGPT (tham khảo thêm)</strong></p>';
        $html .= '<div style="font-size:13px; line-height:1.5; white-space:pre-line;">'
              . e($text)
              . '</div>';

        return $html;
    } catch (\Throwable $e) {
        return '<p style="font-size:12px; margin-top:6px;"><em>Lỗi khi gọi ChatGPT: '
            . e($e->getMessage()) . '. Đang hiển thị phân tích nội bộ.</em></p>';
    }
}


  public function delete(Request $request)
  {
    $product = Product::whereHas('product_details', function (Builder $query) {
      $query->where('import_quantity', '>', 0);
    })->where('id', $request->product_id)->first();

    if(!$product) {

      $data['type'] = 'error';
      $data['title'] = 'Thất Bại';
      $data['content'] = 'Bạn không thể xóa sản phẩm không tồn tại!';
    } else {

      $can_delete = 1;
      $product_details = $product->product_details;
      foreach($product_details as $product_detail) {
        if($product_detail->import_quantity == 0 || $product_detail->import_quantity != $product_detail->quantity) {
          $can_delete = 0;
          break;
        }
      }

      if($can_delete) {

        foreach($product_details as $product_detail) {
          foreach($product_detail->product_images as $image) {
            Storage::disk('public')->delete('images/products/' . $image->image_name);
            $image->delete();
          }
          $product_detail->delete();
        }
        foreach($product->promotions as $promotion) {
          $promotion->delete();
        }
        foreach($product->product_votes as $product_vote) {
          $product_vote->delete();
        }
        $product->delete();
      } else {
        foreach($product_details as $product_detail) {
          if($product_detail->import_quantity > 0 && $product_detail->import_quantity == $product_detail->quantity) {

            foreach($product_detail->product_images as $image) {
              Storage::disk('public')->delete('images/products/' . $image->image_name);
              $image->delete();
            }
            $product_detail->delete();
          } else {

            $product_detail->import_quantity = 0;
            $product_detail->quantity = 0;
            $product_detail->save();
          }
        }
        foreach($product->promotions as $promotion) {
          $promotion->delete();
        }
      }

      $data['type'] = 'success';
      $data['title'] = 'Thành Công';
      $data['content'] = 'Xóa sản phẩm thành công!';
    }

    return response()->json($data, 200);
  }

  public function new(Request $request)
  {
    $producers = Producer::select('id', 'name')->orderBy('name', 'asc')->get();
    return view('admin.product.new')->with('producers', $producers);
  }

  public function save(Request $request)
  {
    $product = new Product;

    if($request->information_details != null) {
      //Xử lý Ảnh trong nội dung
      $information_details = $request->information_details;

      $dom = new \DomDocument();

      // conver utf-8 to html entities
      $information_details = mb_convert_encoding($information_details, 'HTML-ENTITIES', "UTF-8");

      $dom->loadHtml($information_details, LIBXML_HTML_NODEFDTD);

      $images = $dom->getElementsByTagName('img');

      foreach($images as $k => $img){

          $data = $img->getAttribute('src');

          if(Str::containsAll($data, ['data:image', 'base64'])){

              list(, $type) = explode('data:image/', $data);
              list($type, ) = explode(';base64,', $type);

              list(, $data) = explode(';base64,', $data);

              $data = base64_decode($data);

              $image_name = time().$k.'_'.Str::random(8).'.'.$type;

              Storage::disk('public')->put('images/posts/'.$image_name, $data);

              $img->removeAttribute('src');
              $img->setAttribute('src', '/storage/images/posts/'.$image_name);
          }
      }

      $information_details = $dom->saveHTML();

      //conver html-entities to utf-8
      $information_details = mb_convert_encoding($information_details, "UTF-8", 'HTML-ENTITIES');

      //get content
      list(, $information_details) = explode('<html><body>', $information_details);
      list($information_details, ) = explode('</body></html>', $information_details);

      $product->information_details = $information_details;
    }
    if($request->product_introduction != null) {
      //Xử lý Ảnh trong nội dung
      $product_introduction = $request->product_introduction;

      $dom = new \DomDocument();

      // conver utf-8 to html entities
      $product_introduction = mb_convert_encoding($product_introduction, 'HTML-ENTITIES', "UTF-8");

      $dom->loadHtml($product_introduction, LIBXML_HTML_NODEFDTD);

      $images = $dom->getElementsByTagName('img');

      foreach($images as $k => $img){

          $data = $img->getAttribute('src');

          if(Str::containsAll($data, ['data:image', 'base64'])){

              list(, $type) = explode('data:image/', $data);
              list($type, ) = explode(';base64,', $type);

              list(, $data) = explode(';base64,', $data);

              $data = base64_decode($data);

              $image_name = time().$k.'_'.Str::random(8).'.'.$type;

              Storage::disk('public')->put('images/posts/'.$image_name, $data);

              $img->removeAttribute('src');
              $img->setAttribute('src', '/storage/images/posts/'.$image_name);
          }
      }

      $product_introduction = $dom->saveHTML();

      //conver html-entities to utf-8
      $product_introduction = mb_convert_encoding($product_introduction, "UTF-8", 'HTML-ENTITIES');

      //get content
      list(, $product_introduction) = explode('<html><body>', $product_introduction);
      list($product_introduction, ) = explode('</body></html>', $product_introduction);

      $product->product_introduction = $product_introduction;
    }

    $product->name = $request->name;
    $product->producer_id = $request->producer_id;
    $product->sku_code = $request->sku_code;
    $product->monitor = $request->monitor;
    $product->front_camera = $request->front_camera;
    $product->rear_camera = $request->rear_camera;
    $product->CPU = $request->CPU;
    $product->GPU = $request->GPU;
    $product->RAM = $request->RAM;
    $product->ROM = $request->ROM;
    $product->OS = $request->OS;
    $product->pin = $request->pin;
    $product->rate = 5.0;

    if($request->hasFile('image')){
      $image = $request->file('image');
      $image_name = time().'_'.Str::random(8).'_'.$image->getClientOriginalName();
      $image->storeAs('images/products',$image_name,'public');
      $product->image = $image_name;
    }

    $product->save();

    if ($request->has('product_promotions')) {
      foreach ($request->product_promotions as $product_promotion) {
        $promotion = new Promotion;
        $promotion->product_id = $product->id;
        $promotion->content = $product_promotion['content'];

        //Xử lý ngày bắt đầu, ngày kết thúc
        list($start_date, $end_date) = explode(' - ', $product_promotion['promotion_date']);

        $start_date = str_replace('/', '-', $start_date);
        $start_date = date('Y-m-d', strtotime($start_date));

        $end_date = str_replace('/', '-', $end_date);
        $end_date = date('Y-m-d', strtotime($end_date));

        $promotion->start_date = $start_date;
        $promotion->end_date = $end_date;

        $promotion->save();
      }
    }

    if ($request->has('product_details')) {
      foreach ($request->product_details as $key => $product_detail) {
        $new_product_detail = new ProductDetail;
        $new_product_detail->product_id = $product->id;
        $new_product_detail->color = $product_detail['color'];
        $new_product_detail->import_quantity = $product_detail['quantity'];
        $new_product_detail->quantity = $product_detail['quantity'];
        $new_product_detail->import_price = str_replace('.', '', $product_detail['import_price']);
        $new_product_detail->sale_price = str_replace('.', '', $product_detail['sale_price']);
        if($product_detail['promotion_price'] != null) {
          $new_product_detail->promotion_price = str_replace('.', '', $product_detail['promotion_price']);
        }
        if($product_detail['promotion_date'] != null) {
          //Xử lý ngày bắt đầu, ngày kết thúc
          list($start_date, $end_date) = explode(' - ', $product_detail['promotion_date']);

          $start_date = str_replace('/', '-', $start_date);
          $start_date = date('Y-m-d', strtotime($start_date));

          $end_date = str_replace('/', '-', $end_date);
          $end_date = date('Y-m-d', strtotime($end_date));

          $new_product_detail->promotion_start_date = $start_date;
          $new_product_detail->promotion_end_date = $end_date;
        }

        $new_product_detail->save();

        foreach ($request->file('product_details')[$key]['images'] as $image) {
          $image_name = time().'_'.Str::random(8).'_'.$image->getClientOriginalName();
          $image->storeAs('images/products',$image_name,'public');

          $new_image = new ProductImage;
          $new_image->product_detail_id = $new_product_detail->id;
          $new_image->image_name = $image_name;

          $new_image->save();
        }
      }
    }

    return redirect()->route('admin.product.index')->with(['alert' => [
      'type' => 'success',
      'title' => 'Thành Công',
      'content' => 'Thêm sản phẩm thành công.'
    ]]);
  }

  public function edit($id)
  {
    $producers = Producer::select('id', 'name')->orderBy('name', 'asc')->get();
    $product = Product::select('id', 'producer_id', 'name', 'image', 'sku_code', 'monitor', 'front_camera', 'rear_camera', 'CPU', 'GPU', 'RAM', 'ROM', 'OS', 'pin', 'information_details', 'product_introduction')
    ->whereHas('product_details', function (Builder $query) {
      $query->where('import_quantity', '>', 0);
    })->where('id', $id)->with([
      'promotions' => function ($query) {
        $query->select('id', 'product_id', 'content', 'start_date', 'end_date');
      },
      'product_details' => function ($query) {
        $query->select(
            'id', 'product_id', 'color',
            'import_quantity', 'quantity',
            'import_price', 'sale_price',
            'promotion_price', 'promotion_start_date', 'promotion_end_date'
          )->where('import_quantity', '>', 0)
        ->with([
          'product_images' => function ($query) {
            $query->select('id', 'product_detail_id', 'image_name');
          },
          'order_details' => function ($query) {
            $query->select('id', 'product_detail_id', 'quantity');
          }
        ]);
      }
    ])->first();
    if(!$product) abort(404);
    return view('admin.product.edit')->with(['product' => $product, 'producers' =>$producers]);
  }

  public function update(Request $request, $id) {

    $product = Product::whereHas('product_details', function (Builder $query) {
      $query->where('import_quantity', '>', 0);
    })->where('id', $id)->first();
    if(!$product) abort(404);

    if($request->information_details != null) {
      //Xử lý Ảnh trong nội dung
      $information_details = $request->information_details;

      $dom = new \DomDocument();

      // conver utf-8 to html entities
      $information_details = mb_convert_encoding($information_details, 'HTML-ENTITIES', "UTF-8");

      $dom->loadHtml($information_details, LIBXML_HTML_NODEFDTD);

      $images = $dom->getElementsByTagName('img');

      foreach($images as $k => $img){

          $data = $img->getAttribute('src');

          if(Str::containsAll($data, ['data:image', 'base64'])){

              list(, $type) = explode('data:image/', $data);
              list($type, ) = explode(';base64,', $type);

              list(, $data) = explode(';base64,', $data);

              $data = base64_decode($data);

              $image_name = time().$k.'_'.Str::random(8).'.'.$type;

              Storage::disk('public')->put('images/posts/'.$image_name, $data);

              $img->removeAttribute('src');
              $img->setAttribute('src', '/storage/images/posts/'.$image_name);
          }
      }

      $information_details = $dom->saveHTML();

      //conver html-entities to utf-8
      $information_details = mb_convert_encoding($information_details, "UTF-8", 'HTML-ENTITIES');

      //get content
      list(, $information_details) = explode('<html><body>', $information_details);
      list($information_details, ) = explode('</body></html>', $information_details);

      $product->information_details = $information_details;
    }
    if($request->product_introduction != null) {
      //Xử lý Ảnh trong nội dung
      $product_introduction = $request->product_introduction;

      $dom = new \DomDocument();

      // conver utf-8 to html entities
      $product_introduction = mb_convert_encoding($product_introduction, 'HTML-ENTITIES', "UTF-8");

      $dom->loadHtml($product_introduction, LIBXML_HTML_NODEFDTD);

      $images = $dom->getElementsByTagName('img');

      foreach($images as $k => $img){

          $data = $img->getAttribute('src');

          if(Str::containsAll($data, ['data:image', 'base64'])){

              list(, $type) = explode('data:image/', $data);
              list($type, ) = explode(';base64,', $type);

              list(, $data) = explode(';base64,', $data);

              $data = base64_decode($data);

              $image_name = time().$k.'_'.Str::random(8).'.'.$type;

              Storage::disk('public')->put('images/posts/'.$image_name, $data);

              $img->removeAttribute('src');
              $img->setAttribute('src', '/storage/images/posts/'.$image_name);
          }
      }

      $product_introduction = $dom->saveHTML();

      //conver html-entities to utf-8
      $product_introduction = mb_convert_encoding($product_introduction, "UTF-8", 'HTML-ENTITIES');

      //get content
      list(, $product_introduction) = explode('<html><body>', $product_introduction);
      list($product_introduction, ) = explode('</body></html>', $product_introduction);

      $product->product_introduction = $product_introduction;
    }

    $product->name = $request->name;
    $product->producer_id = $request->producer_id;
    $product->sku_code = $request->sku_code;
    $product->monitor = $request->monitor;
    $product->front_camera = $request->front_camera;
    $product->rear_camera = $request->rear_camera;
    $product->CPU = $request->CPU;
    $product->GPU = $request->GPU;
    $product->RAM = $request->RAM;
    $product->ROM = $request->ROM;
    $product->OS = $request->OS;
    $product->pin = $request->pin;

    if($request->hasFile('image')){
      $image = $request->file('image');
      $image_name = time().'_'.Str::random(8).'_'.$image->getClientOriginalName();
      $image->storeAs('images/products',$image_name,'public');
      Storage::disk('public')->delete('images/products/' . $product->image);
      $product->image = $image_name;
    }

    $product->save();

    if ($request->has('old_product_promotions')) {
      foreach ($request->old_product_promotions as $key => $old_product_promotion) {
        $promotion = Promotion::where('id', $key)->first();
        if(!$promotion) abort(404);

        $promotion->content = $old_product_promotion['content'];

        //Xử lý ngày bắt đầu, ngày kết thúc
        list($start_date, $end_date) = explode(' - ', $old_product_promotion['promotion_date']);

        $start_date = str_replace('/', '-', $start_date);
        $start_date = date('Y-m-d', strtotime($start_date));

        $end_date = str_replace('/', '-', $end_date);
        $end_date = date('Y-m-d', strtotime($end_date));

        $promotion->start_date = $start_date;
        $promotion->end_date = $end_date;

        $promotion->save();
      }
    }

    if ($request->has('product_promotions')) {
      foreach ($request->product_promotions as $product_promotion) {
        $promotion = new Promotion;
        $promotion->product_id = $product->id;
        $promotion->content = $product_promotion['content'];

        //Xử lý ngày bắt đầu, ngày kết thúc
        list($start_date, $end_date) = explode(' - ', $product_promotion['promotion_date']);

        $start_date = str_replace('/', '-', $start_date);
        $start_date = date('Y-m-d', strtotime($start_date));

        $end_date = str_replace('/', '-', $end_date);
        $end_date = date('Y-m-d', strtotime($end_date));

        $promotion->start_date = $start_date;
        $promotion->end_date = $end_date;

        $promotion->save();
      }
    }

    if ($request->has('old_product_details')) {
      foreach ($request->old_product_details as $key => $product_detail) {
        // ❌ BỎ cơ chế tự trừ đã bán
        $old_product_detail = ProductDetail::where('id', $key)->first();
        if(!$old_product_detail) abort(404);

        $old_product_detail->color = $product_detail['color'];

        // ✅ CHỈ sửa tồn kho hiện tại
        $old_product_detail->quantity = (int)$product_detail['quantity'];

        $old_product_detail->import_price = str_replace('.', '', $product_detail['import_price']);
        $old_product_detail->sale_price = str_replace('.', '', $product_detail['sale_price']);
        if($product_detail['promotion_price'] != null) {
          $old_product_detail->promotion_price = str_replace('.', '', $product_detail['promotion_price']);
        }
        if($product_detail['promotion_date'] != null) {
          //Xử lý ngày bắt đầu, ngày kết thúc
          list($start_date, $end_date) = explode(' - ', $product_detail['promotion_date']);

          $start_date = str_replace('/', '-', $start_date);
          $start_date = date('Y-m-d', strtotime($start_date));

          $end_date = str_replace('/', '-', $end_date);
          $end_date = date('Y-m-d', strtotime($end_date));

          $old_product_detail->promotion_start_date = $start_date;
          $old_product_detail->promotion_end_date = $end_date;
        }

        $old_product_detail->save();
      }
    }

    if ($request->has('product_details')) {
      foreach ($request->product_details as $key => $product_detail) {
        $new_product_detail = new ProductDetail;
        $new_product_detail->product_id = $product->id;
        $new_product_detail->color = $product_detail['color'];
        $new_product_detail->import_quantity = $product_detail['quantity'];
        $new_product_detail->quantity = $product_detail['quantity'];
        $new_product_detail->import_price = str_replace('.', '', $product_detail['import_price']);
        $new_product_detail->sale_price = str_replace('.', '', $product_detail['sale_price']);
        if($product_detail['promotion_price'] != null) {
          $new_product_detail->promotion_price = str_replace('.', '', $product_detail['promotion_price']);
        }
        if($product_detail['promotion_date'] != null) {
          //Xử lý ngày bắt đầu, ngày kết thúc
          list($start_date, $end_date) = explode(' - ', $product_detail['promotion_date']);

          $start_date = str_replace('/', '-', $start_date);
          $start_date = date('Y-m-d', strtotime($start_date));

          $end_date = str_replace('/', '-', $end_date);
          $end_date = date('Y-m-d', strtotime($end_date));

          $new_product_detail->promotion_start_date = $start_date;
          $new_product_detail->promotion_end_date = $end_date;
        }

        $new_product_detail->save();

        if ($request->file('product_details') && isset($request->file('product_details')[$key]['images'])) {
          foreach ($request->file('product_details')[$key]['images'] as $image) {
            $image_name = time().'_'.Str::random(8).'_'.$image->getClientOriginalName();
            $image->storeAs('images/products',$image_name,'public');

            $new_image = new ProductImage;
            $new_image->product_detail_id = $new_product_detail->id;
            $new_image->image_name = $image_name;

            $new_image->save();
          }
        }
      }
    }

    if($request->file('old_product_details') != null){
      foreach ($request->file('old_product_details') as $key => $images) {
        if (!isset($images['images'])) continue;
        foreach($images['images'] as $image) {
          $image_name = time().'_'.Str::random(8).'_'.$image->getClientOriginalName();
          $image->storeAs('images/products',$image_name,'public');

          $new_image = new ProductImage;
          $new_image->product_detail_id = $key;
          $new_image->image_name = $image_name;

          $new_image->save();
        }
      }
    }

    return redirect()->route('admin.product.index')->with(['alert' => [
      'type' => 'success',
      'title' => 'Thành Công',
      'content' => 'Chỉnh sửa sản phẩm thành công.'
    ]]);
  }

  public function delete_promotion(Request $request)
  {
    $promotion = Promotion::where('id', $request->promotion_id)->first();

    if(!$promotion) {

      $data['type'] = 'error';
      $data['title'] = 'Thất Bại';
      $data['content'] = 'Bạn không thể xóa khuyễn mãi không tồn tại!';
    } else {

      $promotion->delete();

      $data['type'] = 'success';
      $data['title'] = 'Thành Công';
      $data['content'] = 'Xóa khuyến mãi thành công!';
    }

    return response()->json($data, 200);
  }

  public function delete_product_detail(Request $request)
  {
    $product_detail = ProductDetail::where([['id', $request->product_detail_id], ['import_quantity', '>', 0]])->first();

    if(!$product_detail) {

      $data['type'] = 'error';
      $data['title'] = 'Thất Bại';
      $data['content'] = 'Bạn không thể xóa chi tiết sản phẩm không tồn tại!';
    } else {

      if($product_detail->import_quantity == $product_detail->quantity) {
        foreach($product_detail->product_images as $image) {
          Storage::disk('public')->delete('images/products/' . $image->image_name);
          $image->delete();
        }
        $product_detail->delete();
      } else {
        $product_detail->import_quantity = 0;
        $product_detail->quantity = 0;
        $product_detail->save();
      }

      $data['type'] = 'success';
      $data['title'] = 'Thành Công';
      $data['content'] = 'Xóa chi tiết sản phẩm thành công!';
    }

    return response()->json($data, 200);
  }

  public function delete_image(Request $request)
  {
    $image = ProductImage::find($request->key);
    if ($image) {
      Storage::disk('public')->delete('images/products/' . $image->image_name);
      $image->delete();
    }
    return response()->json();
  }
}
