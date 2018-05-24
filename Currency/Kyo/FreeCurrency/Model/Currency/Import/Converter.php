<?php
namespace Kyo\FreeCurrency\Model\Currency\Import;

class Converter extends \Magento\Directory\Model\Currency\Import\AbstractImport
{
    /**
     * @var string
     */
    const CURRENCY_CONVERTER_URL = 'https://free.currencyconverterapi.com/api/v5/convert?q={{CURRENCY_FROM}}_{{CURRENCY_TO}}&compact=ultra';

    /**
     * Core scope config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Http Client Factory
     *
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $httpClientFactory;

    public function __construct(
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
    ){
        parent::__construct($currencyFactory);
        $this->scopeConfig = $scopeConfig;
        $this->httpClientFactory = $httpClientFactory;
    }

    private function getServiceResponse($url, $retry = 0)
    {
        /** @var \Magento\Framework\HTTP\ZendClient $httpClient */
        $httpClient = $this->httpClientFactory->create();
        $response = [];

        try {
            $jsonResponse = $httpClient->setUri(
                $url
            )->setConfig(
                [
                    'timeout' => $this->scopeConfig->getValue(
                        'currency/converter/timeout',
                        \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                    ),
                ]
            )->request(
                'GET'
            )->getBody();

            $response = json_decode($jsonResponse, true);
        }catch (\Exception $e) {
            if ($retry == 0) {
                $response = $this->getServiceResponse($url, 1);
            }
        }
        return $response;
    }

    /**
     * @param string $currencyFrom
     * @param string $currencyTo
     * @return float|int|null
     */
    protected function _convert($currencyFrom, $currencyTo)
    {
        $currency = null;

        $url = str_replace('{{CURRENCY_FROM}}', $currencyFrom, self::CURRENCY_CONVERTER_URL);
        $url = str_replace('{{CURRENCY_TO}}', $currencyTo, $url);

        set_time_limit(0);
        try {
            $response = $this->getServiceResponse($url);
        } finally {
            ini_restore('max_execution_time');
        }

        if (empty($response[$currencyFrom.'_'.$currencyTo])){
            $this->_messages[] = __('We can\'t retrieve a rate from %1 for %2.', $url, $currencyTo);
        } else {
            $currency = $this->_numberFormat(
                (double) $response[$currencyFrom.'_'.$currencyTo]
            );
        }
        return $currency;
    }
}