<?php
// Vendus API wrapper - configuração e função base para requests

class VendusAPI {
    private $apiKey;
    private $baseUrl;

    public function __construct($apiKey = null) {
        if ($apiKey) {
            $this->apiKey = $apiKey;
        } else {
            $config = include __DIR__ . '/config.php';
            $this->apiKey = $config['api_key'];
        }
        $this->baseUrl = 'https://www.vendus.pt/ws/v1.1/';
    }

    private function request($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . ltrim($endpoint, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey . ":");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: application/json",
            "Content-Type: application/json"
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'httpCode' => $httpCode,
            'response' => $response,
            'error' => $error
        ];
    }

    // Exemplo: obter dados da conta
    public function getAccount() {
        return $this->request('account');
    }


    public function createProduct($params) {
        /*
        Exemplo de payload completo para criar produto/serviço no Vendus:
        $params = [
            'reference'           => 'XBD001',
            'barcode'             => 'P000000XBD001',
            'supplier_code'       => 'AHSG102X',
            'title'               => 'Book XPTO',
            'description'         => 'Book XPTO 2006 Edition',
            'include_description' => 'no',
            'supply_price'        => 10.12,
            'gross_price'         => 20.00,
            'unit_id'             => 123,
            'type_id'             => 'P',
            'class_id'            => 'AL',
            'lot_control'         => 'true',
            'stock_control'       => 1,
            'stock_type'          => 'M',
            'tax_id'              => 'NOR',
            'tax_exemption'       => 'M40',
            'tax_exemption_law'   => 'Artigo 13.º do CIVA',
            'category_id'         => 123,
            'brand_id'            => 123,
            'image'               => 'https://www.site.com/img/1.png',
            'status'              => 'on',
            'prices'              => [
                [
                    'id'          => 1234,
                    'gross_price' => 20.00,
                ],
            ],
        ];
        Veja todos os parâmetros possíveis em https://www.vendus.pt/ws/#tag/Products/paths/~1products/post
        */
        return $this->request('products', 'POST', $params);
    }

    // Buscar todas as unidades de produto/serviço
    public function getUnits() {
        return $this->request('products/units');
    }

    /**
     * Cria um documento (fatura, recibo, etc) no Vendus
     * Veja todos os parâmetros possíveis em https://www.vendus.pt/ws/#tag/Documents/paths/~1documents/post
     * Exemplo de uso:
     * $vendus = new VendusAPI();
     * $result = $vendus->createInvoice([
     *   'type' => 'FT',
     *   'entity_id' => 123,
     *   'date' => '2025-06-12',
     *   'lines' => [
     *     [
     *       'product_id' => 1234,
     *       'qty' => 1,
     *       'price' => 10.00,
     *     ],
     *   ],
     *   ...
     * ]);
     */
    public function createInvoice($params) {
        return $this->request('documents', 'POST', $params);
    }

    // Podes adicionar mais funções conforme necessário
}
