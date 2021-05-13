<?php


require_once DIR_SYSTEM . 'library/saferoute/SafeRouteWidgetApi.php';


class ControllerModuleSaferoute extends Controller
{
    /**
     * Отправляет в браузер данные в формате JSON
     *
     * @param $data array Данные для отправки
     */
    private function sendJSON($data = [])
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    /**
     * Возвращает значение GET-параметра
     *
     * @param $name string Имя параметра
     * @return mixed
     */
    private function getParam($name)
    {
        return isset($this->request->get[$name]) ? $this->request->get[$name] : null;
    }

    /**
     * Возвращает значение POST-параметра
     *
     * @param $name string Имя параметра
     * @return mixed
     */
    private function postParam($name)
    {
        return isset($this->request->post[$name]) ? $this->request->post[$name] : null;
    }

    /**
     * Проверяет, совпадает ли переданный токен c токеном, указанным в настройках модуля SafeRoute
     *
     * @param $token string Токен для проверки
     * @return boolean
     */
    private function checkToken($token)
    {
        return ($token && $token === $this->config->get('saferoute_token'));
    }

    /**
     * Наполняет массив атрибутами товара
     *
     * @param $product_id int ID товара
     * @param $attributes array Массив атрибутов
     */
    private function getProductAttributes($product_id, &$attributes)
    {
        $this->load->model('catalog/product');

        $attrs = $this->model_catalog_product->getProductAttributes($product_id);

        foreach($attrs as $attrs_group)
        {
            foreach($attrs_group['attribute'] as $attr)
            {
                if (isset($attributes[$attr['name']]))
                    $attributes[$attr['name']] = trim($attr['text']);
            }
        }
    }

    /**
     * Возвращает полные данные товара
     *
     * @param $id int ID товара
     * @return array
     */
    private function getProductData($id)
    {
        $this->load->model('catalog/product');
        return $this->model_catalog_product->getProduct($id);
    }

    /**
     * Возвращает габариты товара строго в см
     *
     * @param $product array Данные товара
     * @return array
     */
    private function getProductDimensions(array $product)
    {
        $dimensions = [
            'width' => (float) $product['width'],
            'height' => (float) $product['height'],
            'length' => (float) $product['length'],
        ];

        // Если габариты в мм
        if ($product['length_class_id'] === '2')
        {
            $dimensions['width'] /= 10;
            $dimensions['height'] /= 10;
            $dimensions['length'] /= 10;
        }

        return $dimensions;
    }


    /**
     * Возвращает настройки сайта / модуля, необходимые для работы виджета
     */
    public function get_settings()
    {
        $this->sendJSON([
            'lang' => $this->language->get('code'),
            'currency' => strtolower($this->session->data['currency']),
        ]);
    }

    /**
     * Возвращает содержимое корзины для передачи в виджет
     */
    public function get_cart()
    {
        $data = [];

        // Массив товаров корзины
        $data['products'] = [];
        // Общий вес товаров корзины
        $data['weight'] = $this->cart->getWeight();

        foreach ($this->cart->getProducts() as $product)
        {
            $attributes = [
                'barcode'          => '', // Штрих-код
                'vat'              => '', // НДС
                'tnved'            => '', // Код товара
                'nameEn'           => '', // Название на англ.
                'producingCountry' => '', // Код страны-производителя
            ];

            $this->getProductAttributes($product['product_id'], $attributes);
            $dimensions = $this->getProductDimensions($product);

            $product_data = $this->getProductData($product['product_id']);

            $data['products'][] = [
                'name'             => $product['name'],
                'vendorCode'       => (isset($product_data['sku'])) ? $product_data['sku'] : '',
                'brand'            => (isset($product_data['manufacturer'])) ? $product_data['manufacturer'] : '',
                'barcode'          => $attributes['barcode'],
                'vat'              => $attributes['vat'] ? (int) $attributes['vat'] : null,
                'tnved'            => $attributes['tnved'],
                'nameEn'           => $attributes['nameEn'],
                'producingCountry' => $attributes['producingCountry'],
                'price'            => $product['price'],
                'count'            => (int) $product['quantity'],
                'width'            => $dimensions['width'],
                'height'           => $dimensions['height'],
                'length'           => $dimensions['length'],
            ];
        }

        // Определение размера скидок по купонам
        $data['discount'] = 0;
        if (isset($this->session->data['coupon']))
        {
            $total_data = [
                'totals' => &$totals,
                'total'  => &$total,
            ];

            $total_data['total'] = $this->cart->getTotal();
            $this->load->model('extension/total/coupon');
            $this->model_extension_total_coupon->getTotal($total_data);

            foreach ($totals as $item)
                $data['discount'] += abs($item['value']);
        }

        $this->sendJSON($data);
    }

    /**
     * API для виджета
     */
    public function widget_api()
    {
        $widgetApi = new SafeRouteWidgetApi($this->config->get('saferoute_token'), $this->config->get('saferoute_shop_id'));

        $request = ($_SERVER['REQUEST_METHOD'] === 'POST')
            ? json_decode(file_get_contents('php://input'), true)
            : $_REQUEST;

        $widgetApi->setMethod($_SERVER['REQUEST_METHOD']);
        $widgetApi->setData(isset($request['data']) ? $request['data'] : []);

        $this->response->setOutput($widgetApi->submit($request['url']));
    }

    /**
     * API для взаимодействия с бэком SafeRoute
     */
    public function api()
    {
        // Проверка токена, передаваемого в запросе
        if ($this->checkToken($this->request->server['HTTP_TOKEN']))
        {
            $r = $this->request->get['route'];

            // Список статусов заказа
            if (strpos($r, 'statuses.json'))
            {
                $this->load->model('shipping/saferoute');
                $this->sendJSON($this->model_shipping_saferoute->getOrderStatuses());
            }
            // Список способов оплаты
            elseif (strpos($r, 'payment-methods.json'))
            {
                $this->load->model('extension/extension');

                $payment_extensions = $this->model_extension_extension->getExtensions('payment');
                $payment_methods = [];

                foreach ($payment_extensions as $payment_extension)
                {
                    $this->load->language('extension/payment/' . $payment_extension['code']);
                    $payment_methods[$payment_extension['code']] = $this->language->get('text_title');
                }

                $this->sendJSON($payment_methods);
            }
            // Уведомления об изменениях статуса заказа в SafeRoute
            elseif (strpos($r, 'order-status-update'))
            {
                $this->load->model('checkout/order');
                $this->load->language('api/order');

                // Данные запроса
                $id = $this->postParam('id');
                $status_cms = $this->postParam('statusCMS');
                $track_number = $this->postParam('trackNumber');

                // id и statusCMS обязательно должны быть переданы
                if ($id && $status_cms)
                {
                    // Сохранение трекинг-номера заказа
                    $this->db->query("UPDATE `" . DB_PREFIX . "order` SET tracking='$track_number' WHERE saferoute_id='$id'");

                    // Получение ID заказа в CMS
                    $order_id = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` WHERE saferoute_id='$id'")->row['order_id'];

                    // Добавление нового статуса в историю статусов заказа
                    $this->model_checkout_order->addOrderHistory($order_id, $status_cms, '', true);
                }
                else
                {
                    header($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');
                }
            }
            // Неправильный запрос
            else
            {
                header($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
            }
        }
        // Неправильный API-ключ
        else
        {
            header($this->request->server['SERVER_PROTOCOL'] . ' 401 Unauthorized');
        }
    }
}