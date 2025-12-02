{{-- CSS riêng cho nút Chat AI (có thể chuyển sang file .css nếu muốn) --}}
<style>
    /* 🔹 Định vị lại MINI CART để không trùng với Chat AI */
    .support-cart.mini-cart {
        position: fixed;
        bottom: 95px;   /* 🛒 giỏ hàng cao hơn */
        right: 25px;
        z-index: 9998;  /* thấp hơn chat một chút để không đè popup chat */
    }

    /* 🔹 Floating Chat Bubble */
    #chat-ai-support {
        position: fixed;
        bottom: 25px;   /* 💬 chat AI thấp hơn giỏ hàng */
        right: 25px;
        width: 55px;
        height: 55px;
        background: #16a34a;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.24);
        transition: 0.3s;
    }
    #chat-ai-support:hover {
        background: #22c55e;
        transform: scale(1.1);
    }

    #chat-box {
        position: fixed;
        bottom: 95px;   /* hộp chat nằm phía trên nút chat một đoạn */
        right: 25px;
        width: 330px;
        height: 420px;
        background: #ffffff;
        border-radius: 14px;
        display: none;
        flex-direction: column;
        border: 2px solid #16a34a;
        z-index: 9999;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .chat-header {
        padding: 10px;
        background: #16a34a;
        color: #ffffff;
        font-weight: 700;          /* đậm hơn */
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 15px;           /* to hơn một chút */
    }
    .chat-content {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
        font-size: 14px;           /* tăng cỡ chữ nội dung */
    }
    .chat-input {
        display: flex;
        border-top: 1px solid #ddd;
    }
    .chat-input input {
        flex: 1;
        border: none;
        padding: 12px;
        outline: none;
        font-size: 14px;           /* to hơn */
        font-weight: 600;          /* đậm hơn */
        color: #111111;            /* chữ đen đậm dễ nhìn */
    }
    .chat-input input::placeholder {
        color: #6b7280;            /* xám đậm hơn placeholder mặc định */
        font-weight: 500;
    }
    .chat-input button {
        background: #16a34a;
        border: none;
        color: #ffffff;
        padding: 12px 18px;
        font-size: 14px;           /* to hơn */
        font-weight: 700;          /* đậm hơn */
        cursor: pointer;
    }

    .message {
        margin: 8px 0;
        padding: 9px 12px;         /* rộng hơn chút cho dễ đọc */
        border-radius: 12px;
        width: fit-content;
        max-width: 80%;
        line-height: 1.5;
        font-size: 14px;           /* to hơn */
        font-weight: 600;          /* chữ đậm */
        color: #111111;            /* chữ đen đậm */
    }
    .user-msg {
        background: #dcfce7;
        margin-left: auto;
    }
    .bot-msg {
        background: #e4e4e7;
    }
</style>

{{-- MINI CART CŨ --}}
<div class="support-cart mini-cart">
  <a class="btn-support-cart" href="{{ route('show_cart') }}">
    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 435.104 435.104" style="enable-background:new 0 0 435.104 435.104;" xml:space="preserve" width="30px" height="30px">
      <g>
        <circle cx="154.112" cy="377.684" r="52.736" data-original="#000000" class="active-path" data-old_color="#Ffffff" fill="#FFFFFF"></circle>
        <path d="M323.072,324.436L323.072,324.436c-29.267-2.88-55.327,18.51-58.207,47.777c-2.88,29.267,18.51,55.327,47.777,58.207     c3.468,0.341,6.962,0.341,10.43,0c29.267-2.88,50.657-28.94,47.777-58.207C368.361,346.928,348.356,326.924,323.072,324.436z" data-original="#000000" class="active-path" data-old_color="#F8F8F8" fill="#FFFFFF"></path>
        <path d="M431.616,123.732c-2.62-3.923-7.059-6.239-11.776-6.144h-58.368v-1.024C361.476,54.637,311.278,4.432,249.351,4.428     C187.425,4.424,137.22,54.622,137.216,116.549c0,0.005,0,0.01,0,0.015v1.024h-43.52L78.848,50.004     C77.199,43.129,71.07,38.268,64,38.228H0v30.72h51.712l47.616,218.624c1.257,7.188,7.552,12.397,14.848,12.288h267.776     c7.07-0.041,13.198-4.901,14.848-11.776l37.888-151.552C435.799,132.019,434.654,127.248,431.616,123.732z M249.344,197.972     c-44.96,0-81.408-36.448-81.408-81.408s36.448-81.408,81.408-81.408s81.408,36.448,81.408,81.408     C330.473,161.408,294.188,197.692,249.344,197.972z" data-original="#000000" class="active-path" data-old_color="#F8F8F8" fill="#FFFFFF"></path>
        <path d="M237.056,118.1l-28.16-28.672l-22.016,21.504l38.912,39.424c2.836,2.894,6.7,4.55,10.752,4.608     c3.999,0.196,7.897-1.289,10.752-4.096l64.512-60.928l-20.992-22.528L237.056,118.1z" data-original="#000000" class="active-path" data-old_color="#F8F8F8" fill="#FFFFFF"></path>
      </g>
    </svg>
    <div class="animated infinite zoomIn kenit-alo-circle"></div>
    <div class="animated infinite pulse kenit-alo-circle-fill"></div>
    <span class="cnt crl-bg count_item_pr">{{ $cart->totalQty }}</span>
  </a>
  <div class="top-cart-content">
    <ul id="cart-sidebar" class="mini-products-list count_li">
      @if($cart->totalQty)
        <ul class="list-item-cart">
          @foreach($cart->items as $key => $item)
            <li class="item productid-{{$key}}">
              <a class="product-image" href="{{ route('product_page', ['id' => $item['item']->product->id]) }}" title="{{ $item['item']->product->name . ' - ' . $item['item']->color }}">
                <img alt="{{ $item['item']->product->name . ' - ' . $item['item']->color }}" src="{{ Helper::get_image_product_url($item['item']->product->image) }}" width="80">
              </a>
              <div class="detail-item">
                <div class="product-details">
                  <a href="javascript:;" data-id="{{ $key }}" title="Xóa" class="remove-item-cart fa fa-remove" data-url="{{ route('remove_cart') }}" onclick="removeItem($(this));">
                    <i class="fas fa-times"></i>
                  </a>
                  <p class="product-name">
                    <a href="{{ route('product_page', ['id' => $item['item']->product->id]) }}" title="{{ $item['item']->product->name . ' - ' . $item['item']->color }}">{{ $item['item']->product->name . ' - ' . $item['item']->color }}
                    </a>
                  </p>
                </div>
                <div class="product-details-bottom">
                  <span class="price pricechange">{{ number_format($item['price'],0,',','.') }}₫</span>
                  <div class="quantity-select">
                    <input class="variantID" type="hidden" name="variantId" value="{{ $key }}">
                    <button onclick="minus({{ $key }});" class="reduced items-count btn-minus" type="button">–</button>
                    <input type="text" disabled="" maxlength="3" min="1" max="{{ $item['item']->quantity }}" onchange="if(this.value == 0)this.value = 1;" class="input-text number-sidebar qty{{ $key }}" id="qty{{ $key }}" name="Lines" size="4" value="{{ $item['qty'] }}" data-url="{{ route('update_minicart') }}">
                    <button onclick="plus({{ $key }});" class="increase items-count btn-plus" type="button">+</button>
                  </div>
                </div>
              </div>
            </li>
          @endforeach
        </ul>
        <div>
          <div class="top-subtotal">Tổng cộng: <span class="price">{{ number_format($cart->totalPrice,0,',','.') }}₫</span></div>
        </div>
        <div>
          <div class="actions clearfix">
            <a href="javascript:;" onclick="showCheckout($(this));" class="btn btn-gray btn-checkout" title="Thanh toán" data-url="{{ route('show_checkout') }}">
              <span>Thanh toán</span>
            </a>
            <a href="{{ route('show_cart') }}" class="view-cart btn btn-white margin-left-5" title="Giỏ hàng">
              <span>Giỏ hàng</span>
            </a>
          </div>
        </div>
      @else
        <div class="no-item"><p>Không có sản phẩm nào trong giỏ hàng.</p></div>
      @endif
    </ul>
  </div>
</div>
<div id="menu-overlay"></div>

{{-- 🔹 Nút Chat AI + Hộp Chat --}}
<div id="chat-ai-support" onclick="toggleChatBox()">
    <i class="fas fa-comment-dots" style="font-size: 26px; color:white;"></i>
</div>

<div id="chat-box">
    <div class="chat-header">
        AI tư vấn mua điện thoại
        <span style="cursor:pointer" onclick="toggleChatBox()">✖</span>
    </div>
    <div class="chat-content" id="chat-content">
        <div class="message bot-msg">
            Chào bạn 👋<br>
            Hãy cho mình biết nhu cầu (tầm giá, chơi game, chụp ảnh, pin trâu…) mình tư vấn mẫu phù hợp nhé!
        </div>
    </div>
    <div class="chat-input">
        <input type="text" id="chat-input-text" placeholder="Nhập câu hỏi của bạn...">
        <button type="button" onclick="sendMessage()">Gửi</button>
    </div>
</div>

<script>
    function toggleChatBox() {
        const box = document.getElementById('chat-box');
        box.style.display = (box.style.display === 'flex') ? 'none' : 'flex';
    }

    function sendMessage() {
        let text = document.getElementById('chat-input-text').value.trim();
        if (!text) return;

        let content = document.getElementById('chat-content');

        // escape text để tránh lỗi XSS
        let safeText = $('<div/>').text(text).html();

        content.insertAdjacentHTML('beforeend',
            `<div class="message user-msg">${safeText}</div>`
        );
        document.getElementById('chat-input-text').value = '';
        content.scrollTop = content.scrollHeight;

        // lấy CSRF từ meta, fallback về blade token
        let csrf = document.querySelector('meta[name="csrf-token"]')
            ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            : "{{ csrf_token() }}";

        $.ajax({
            url: "{{ route('chat_ai') }}",
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': csrf
            },
            data: { message: text },
            success: function (res) {
                let reply = res.reply ?? 'Xin lỗi, hiện không nhận được phản hồi từ AI.';
                content.insertAdjacentHTML('beforeend',
                    `<div class="message bot-msg">${reply}</div>`
                );
                content.scrollTop = content.scrollHeight;
            },
            error: function (xhr, status, error) {
                console.error('chat_ai error:', status, error, xhr.status, xhr.responseText);
                content.insertAdjacentHTML('beforeend',
                    `<div class="message bot-msg">⚠️ Lỗi kết nối đến server AI (${xhr.status}). Vui lòng thử lại!</div>`
                );
                content.scrollTop = content.scrollHeight;
            }
        });
    }
</script>
