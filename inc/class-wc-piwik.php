<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Wpmc_woo_Piwik')) {
    class Wpmc_woo_Piwik extends WC_Integration
    {
       
        /**
         * Init and hook in the integration.
         */
        public function __construct()
        {

            $this->addActions();
        }

        /**
         * Piwik eCommerce order tracking
         *
         * @access public
         *
         * @param mixed $order_id
         *
         * @return void
         */
        public function ecommerce_tracking_code($order_id)
        {
            if (get_post_meta($order_id, '_piwik_tracked', true) == 1) {
                return;
            }

            $order = new WC_Order($order_id);
            $code  = '
	            var _paq = _paq || [];
	        ';

            if ($order->get_items()) {
                foreach ($order->get_items() as $item) {
                    $_product = $order->get_product_from_item($item);
                    $code .= '
	                _paq.push(["addEcommerceItem",
	                    "' . esc_js($_product->get_sku() ? $_product->get_sku() : $_product->id) . '",
	                    "' . esc_js($item['name']) . '",';

                    $out        = array();
                    $categories = get_the_terms($_product->id, 'product_cat');
                    if ($categories) {
                        foreach ($categories as $category) {
                            $out[] = $category->name;
                        }
                    }
                    if (count($out) > 0) {
                        $code .= '["' . join("\", \"", $out) . '"],';
                    } else {
                        $code .= '[],';
                    }

                    $code .= '"' . esc_js($order->get_item_total($item)) . '",';
                    $code .= '"' . esc_js($item['qty']) . '"';
                    $code .= "]);";
                }
            }

            $code .= '
	            _paq.push(["trackEcommerceOrder",
	                "' . esc_js($order->get_order_number()) . '",
	                "' . esc_js($order->get_total()) . '",
	                "' . esc_js($order->get_total() - $order->get_total_shipping()) . '",
	                "' . esc_js($order->get_total_tax()) . '",
	                "' . esc_js($order->get_total_shipping()) . '"
	            ]);
	        ';

            echo '<script type="text/javascript">' . $code . '</script>';

            update_post_meta($order_id, '_piwik_tracked', 1);
        }

        public function get_cart_items_js_code()
        {
            global $woocommerce;

            $cart_content = $woocommerce->cart->get_cart();
            $code         = '
	            var cartItems = [];';
            foreach ($cart_content as $item) {

                $item_sku   = esc_js(($sku = $item['data']->get_sku()) ? $sku : $item['product_id']);
                $item_price = $item['data']->get_price();
                $item_title = $item['data']->get_title();
                $cats       = $this->getProductCategories($item['product_id']);

                $code .= "
	            cartItems.push({
	                    sku: \"$item_sku\",
	                    title: \"$item_title\",
	                    price: $item_price,
	                    quantity: {$item['quantity']},
	                    categories: $cats
	                });
	            ";
            }

            return $code;
        }

        /**
         * Sends cart update request
         */
        public function update_cart()
        {

            $code = $this->get_cart_items_js_code();

            wc_enqueue_js("
	            " . $code . "
	            var arrayLength = cartItems.length, revenue = 0;

	            for (var i = 0; i < arrayLength; i++) {
	                _paq.push(['addEcommerceItem',
	                    cartItems[i].sku,
	                    cartItems[i].title,
	                    cartItems[i].categories,
	                    cartItems[i].price,
	                    cartItems[i].quantity
	                    ]);

	                revenue += cartItems[i].price * cartItems[i].quantity;
	            }


	            _paq.push(['trackEcommerceCartUpdate', revenue]);
			");
        }

        /**
         * Ajax action to get cart
         */
        public function get_cart()
        {
            global $woocommerce;

            $cart_content = $woocommerce->cart->get_cart();
            $products     = array();

            foreach ($cart_content as $item) {
                $item_sku = esc_js(($sku = $item['data']->get_sku()) ? $sku : $item['product_id']);
                $cats     = $this->getProductCategories($item['product_id']);

                $products[] = array(
                    'sku'        => $item_sku,
                    'title'      => $item['data']->get_title(),
                    'price'      => $item['data']->get_price(),
                    'quantity'   => $item['quantity'],
                    'categories' => $cats,
                );
            }

            header('Content-Type: application/json; charset=utf-8');

            echo json_encode($products);
            exit;
        }

        public function send_update_cart_request()
        {
            if (!empty($_REQUEST['add-to-cart']) && is_numeric($_REQUEST['add-to-cart'])) {
                $code = $this->get_cart_items_js_code();
                wc_enqueue_js($code . "
				    $(document).ready(function(){
	                    $('body').trigger('added_to_cart');
	                });
	            ");
            }
        }

        /**
         * @param $itemID
         *
         * @return string
         */
        protected function getProductCategories($itemID)
        {
            $out        = array();
            $categories = get_the_terms($itemID, 'product_cat');

            if ($categories) {
                foreach ($categories as $category) {
                    $out[] = $category->name;
                }
            }

            if (count($out) > 0) {
                $cats = '["' . join("\", \"", $out) . '"]';

                return $cats;
            } else {
                $cats = '[]';

                return $cats;
            }
        }

        /**
         * Add actions using WooCommerce hooks
         */
        protected function addActions()
        {
            add_action('wp_ajax_nopriv_woocommerce_piwik_get_cart', array($this, 'get_cart'));
            add_action('wp_ajax_woocommerce_piwik_get_cart', array($this, 'get_cart'));
            add_action('woocommerce_after_single_product_summary', array($this, 'product_view'));
            add_action('woocommerce_after_shop_loop', array($this, 'category_view'));

            add_action('woocommerce_thankyou', array($this, 'ecommerce_tracking_code'));

            add_action('woocommerce_after_single_product', array($this, 'send_update_cart_request'));
            add_action('woocommerce_after_cart', array($this, 'update_cart'));

            $suffix      = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            $assets_path = str_replace(array('http:', 'https:'),
                '',
                untrailingslashit(plugins_url('/', __FILE__))) . '/';
            $frontend_script_path = $assets_path . '../assets/js/';
            wp_enqueue_script('get-cart',
                $frontend_script_path . 'get-cart' . $suffix . '.js',
                array('jquery'),
                WC_VERSION,
                true);

        }

        public function category_view()
        {
            global $wp_query;

            if (isset($wp_query->query_vars['product_cat']) && !empty($wp_query->query_vars['product_cat'])) {
                $jsCode = sprintf("
	            _paq.push(['setEcommerceView',
	                    false,
	                    false,
	                    '%s'
	            ]);
	            _paq.push(['trackPageView']);
	            ", urlencode($wp_query->queried_object->name));
                wc_enqueue_js($jsCode);
            }
        }

        public function product_view()
        {
            global $product;

            $jsCode = sprintf("
	            _paq.push(['setEcommerceView',
	                    '%s',
	                    '%s',
	                    %s,
	                    %f
	            ]);
	            _paq.push(['trackPageView']);
	        ",
                $product->get_sku(),
                urlencode($product->get_title()),
                $this->getEncodedCategoriesByProduct($product),
                $product->get_price()
            );
            wc_enqueue_js($jsCode);
        }

        protected function getEncodedCategoriesByProduct($product)
        {
            $categories = get_the_terms($product->post->ID, 'product_cat');

            if (!$categories) {
                $categories = array();
            }

            $categories = array_map(function ($element) {
                return sprintf("'%s'", urlencode($element->name));
            }, $categories);

            return sprintf("[%s]", implode(", ", $categories));
        }

    }
}
