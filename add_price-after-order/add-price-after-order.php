
/**
 * WooCommerce Per-Order Price Estimation System
 * - Prices are hidden until admin sets them for each ORDER
 * - Prices apply ONLY to that order, NOT to products or future orders
 * - Admin can set/update prices in order edit page
 */

// 1. Hide product prices on frontend until estimation
add_filter('woocommerce_get_price_html', 'hide_product_price_show_estimation_text', 10, 2);
function hide_product_price_show_estimation_text($price, $product) {
    return '<span class="price-estimation">اعلام قیمت پس از تخمین</span>';
}

// 2. Force 0 price in cart/checkout (until admin sets per-order price)
add_filter('woocommerce_product_get_price', 'set_zero_price_for_estimation', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'set_zero_price_for_estimation', 10, 2);
add_filter('woocommerce_product_get_sale_price', 'set_zero_price_for_estimation', 10, 2);
function set_zero_price_for_estimation($price, $product) {
    return 0; // always 0 until order-specific price is set
}

// 3. Add inline price estimation box in order edit page
add_action('woocommerce_admin_order_data_after_billing_address', 'add_price_estimation_section_inline');
function add_price_estimation_section_inline($order) {
    ?>
    <div class="order_data_column" style="width: 100%; clear: both; margin-top: 20px;">
        <div class="address" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
            <h3>تخمین قیمت سفارش</h3>
            <?php price_estimation_inline_content($order); ?>
        </div>
    </div>
    <?php
}

function price_estimation_inline_content($order) {
    if (!$order) return;

    wp_nonce_field('save_price_estimation', 'price_estimation_nonce');

    echo '<div class="price-estimation-container">';
    echo '<p><strong>تنظیم قیمت نهایی برای محصولات:</strong></p>';

    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $estimated_price = $item->get_meta('_estimated_price');
        $is_estimated   = $item->get_meta('_price_estimated_by_admin');

        echo '<div class="price-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; background: white;">';
        echo '<strong>' . $item->get_name() . '</strong><br>';
        echo '<small>تعداد: ' . $item->get_quantity() . '</small><br>';
        echo '<label>قیمت واحد: </label>';
        echo '<input type="number" step="0.01" name="estimated_prices[' . $item_id . ']" value="' . esc_attr($estimated_price) . '" style="width: 150px; margin: 5px 0;" />';
        echo '<span> تومان</span>';

        if ($is_estimated === 'yes') {
            echo '<span style="color: green; margin-left: 10px;">✓ قیمت تنظیم شده</span>';
            if ($estimated_price > 0) {
                echo '<br><small>جمع کل آیتم: ' . wc_price($estimated_price * $item->get_quantity()) . '</small>';
            }
        } else {
            echo '<span style="color: orange; margin-left: 10px;">⏳ در انتظار تخمین</span>';
        }
        echo '</div>';
    }

    echo '<p><strong>جمع کل سفارش فعلی: ' . wc_price($order->get_total()) . '</strong></p>';
    echo '<p><button type="button" id="update-order-prices" class="button button-primary" style="margin-top: 10px;">بروزرسانی قیمت‌ها و سفارش</button></p>';
    echo '</div>';
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#update-order-prices').off('click').on('click', function() {
            var button = $(this);
            var originalText = button.text();
            button.text('در حال بروزرسانی...').prop('disabled', true);

            var priceData = {};
            var hasValidPrice = false;

            $('input[name^="estimated_prices"]').each(function() {
                var itemId = $(this).attr('name').match(/\[(\d+)\]/)[1];
                var price = $(this).val();
                if (price && parseFloat(price) > 0) {
                    priceData[itemId] = price;
                    hasValidPrice = true;
                }
            });

            if (!hasValidPrice) {
                alert('لطفاً حداقل یک قیمت معتبر وارد کنید');
                button.text(originalText).prop('disabled', false);
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_order_prices',
                    order_id: <?php echo $order->get_id(); ?>,
                    prices: priceData,
                    nonce: $('#price_estimation_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert('قیمت‌ها با موفقیت بروزرسانی شدند!');
                        location.reload();
                    } else {
                        alert('خطا در بروزرسانی: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('خطا در ارتباط با سرور: ' + error);
                    console.log('AJAX Error:', xhr.responseText);
                },
                complete: function() {
                    button.text(originalText).prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

// 4. AJAX handler for saving order-specific prices
add_action('wp_ajax_update_order_prices', 'handle_update_order_prices');
function handle_update_order_prices() {
    if (!wp_verify_nonce($_POST['nonce'], 'save_price_estimation')) {
        wp_die('Security check failed');
    }

    if (!current_user_can('manage_woocommerce')) {
        wp_die('Insufficient permissions');
    }

    $order_id = intval($_POST['order_id']);
    $prices   = $_POST['prices'];

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
        return;
    }

    foreach ($order->get_items() as $item_id => $item) {
        if (isset($prices[$item_id]) && is_numeric($prices[$item_id]) && $prices[$item_id] > 0) {
            $new_price = floatval($prices[$item_id]);

            $item->set_subtotal($new_price * $item->get_quantity());
            $item->set_total($new_price * $item->get_quantity());

            $item->update_meta_data('_estimated_price', $new_price);
            $item->update_meta_data('_price_estimated_by_admin', 'yes');
            $item->save();
        }
    }

    $order->calculate_totals();
    $order->save();

    $order->add_order_note('قیمت محصولات توسط مدیر برای این سفارش تنظیم شد.');

    send_price_update_notification($order);

    wp_send_json_success('Prices updated successfully');
}

// 5. Email customer when prices updated
function send_price_update_notification($order) {
    $customer_email = $order->get_billing_email();
    $order_id       = $order->get_id();

    $subject = 'بروزرسانی قیمت سفارش #' . $order_id;

    $message = '<h3>قیمت سفارش شما تنظیم شد</h3>';
    $message .= '<p>سفارش شماره: #' . $order_id . '</p>';
    $message .= '<p>قیمت نهایی سفارش: ' . wc_price($order->get_total()) . '</p>';
    $message .= '<p>برای مشاهده جزئیات سفارش، <a href="' . $order->get_view_order_url() . '">اینجا کلیک کنید</a></p>';

    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail($customer_email, $subject, $message, $headers);
}

// 6. Custom column in orders list (status)
add_filter('manage_edit-shop_order_columns', 'add_price_estimation_column');
function add_price_estimation_column($columns) {
    $new_columns = array();
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key == 'order_status') {
            $new_columns['price_estimation'] = 'وضعیت تخمین قیمت';
        }
    }
    return $new_columns;
}

add_action('manage_shop_order_posts_custom_column', 'show_price_estimation_column_content');
function show_price_estimation_column_content($column) {
    global $post;
    if ($column == 'price_estimation') {
        $order = wc_get_order($post->ID);
        if (!$order) return;

        $all_estimated = true;
        $items_count   = 0;

        foreach ($order->get_items() as $item) {
            $items_count++;
            if ($item->get_meta('_price_estimated_by_admin') !== 'yes') {
                $all_estimated = false;
            }
        }

        if ($items_count == 0) {
            echo '<span style="color: gray;">بدون محصول</span>';
        } elseif ($all_estimated) {
            echo '<span style="color: green;">✓ تخمین زده شده</span>';
        } else {
            echo '<span style="color: orange;">⏳ در انتظار تخمین</span>';
        }
    }
}

// 7. Cart/checkout display text
add_filter('woocommerce_cart_item_price', 'show_estimation_text_in_cart', 10, 3);
function show_estimation_text_in_cart($price_html, $cart_item, $cart_item_key) {
    return '<span class="price-estimation">قیمت پس از تخمین اعلام می‌شود</span>';
}

// 8. Custom CSS
add_action('wp_head', 'price_estimation_custom_css');
function price_estimation_custom_css() {
    ?>
    <style>
    .price-estimation {
        color: #e47911;
        font-weight: bold;
        font-size: 14px;
    }
    .woocommerce .price-estimation {
        background: #fff3cd;
        padding: 5px 10px;
        border-radius: 3px;
        border: 1px solid #ffeaa7;
    }
    </style>
    <?php
}

// 9. Prevent checkout if prices not estimated
add_action('woocommerce_checkout_process', 'validate_estimation_before_checkout');
function validate_estimation_before_checkout() {
    foreach (WC()->cart->get_cart() as $cart_item) {
        wc_add_notice('لطفاً منتظر تخمین قیمت محصولات بمانید.', 'error');
        return;
    }
}
