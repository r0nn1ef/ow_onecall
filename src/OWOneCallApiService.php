<?php

declare(strict_types=1);

namespace Drupal\ow_onecall;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use ReflectionClass;
use PDO;
use Drupal\ow_onecall\OWOneCallApiServiceInterface;

/**
 * @todo Add class description.
 */
final class OWOneCallApiService implements OWOneCallApiServiceInterface
{

    /**
     * @var \Drupal\Core\Database\Connection
     */
    protected Connection $conn;

    /**
     * @var ImmutableConfig $config
     */
    protected ImmutableConfig $config;

    /**
     * @var LoggerChannelInterface $logger
     */
    protected LoggerChannelInterface $logger;

    /**
     * @var Client $client
     */
    protected Client $client;

    /**
     * @var bool $debug
     */
    protected bool $debug;

    /**
     * @var string $ONECALL_BASE
     */
    public static string $ONECALL_BASE = 'https://api.openweathermap.org/data/3.0/onecall';

    /**
     * @var string $GEOCODE_BASE
     */
    public static string $GEOCODE_BASE = 'http://api.openweathermap.org/geo/1.0';

    /**
     * @var int $CALL_TYPE_GEOCODE
     */
    private static int $CALL_TYPE_GEOCODE = 0;

    /**
     * @var int $CALL_TYPE_WEATHER
     */
    private static int $CALL_TYPE_WEATHER = 1;

    /**
     * @var int $CALL_TYPE_REVERSE_GEOCODE
     */
    private static int $CALL_TYPE_REVERSE_GEOCODE = 2;

    /**
     * @var int $CALL_TYPE_HISTORICAL
     */
    private static int $CALL_TYPE_HISTORICAL = 3;

    /**
     * @var int $CALL_TYPE_DAILY_AGGREGATION
     */
    private static int $CALL_TYPE_DAILY_AGGREGATION = 4;

    /**
     * @var int $CALL_TYPE_OVERVIEW
     */
    private static int $CALL_TYPE_OVERVIEW = 5;

    /**
     * Constructs an OneCallService object.
     */
    public function __construct(
        Connection                    $conn,
        ConfigFactoryInterface        $configFactory,
        LoggerChannelFactoryInterface $loggerChannelFactory,
        Client                        $client
    )
    {
        $this->conn = $conn;
        $this->config = $configFactory->get('ow_onecall.settings');
        $this->debug = (bool)$this->config->get('debug') ?? FALSE;
        $this->logger = $loggerChannelFactory->get((new ReflectionClass($this))->getShortName());
        $this->client = $client;
    }

    /**
     * @inheritDoc
     */
    public function geocode(string $country, string $city, string $state="", int $limit = 1): array
    {
        /*
         * If our api key is empty, we can't do anything so return an error.
         */
        $api_key = $this->getApiKey();
        if( $api_key == '' ) {
            return [
                'code' => 400,
                'error' => 'One Call app ID can not be empty.'
            ];
        }
        $params = [
            'timeout' => 30,
            'query' => [
                'q' => ($city . ',' . (!empty($state) ? strtoupper($state) . ',' : '') . strtoupper($country)),
                'limit' => $limit,
                'appid' => $api_key
            ]
        ];

        return $this->call(self::$CALL_TYPE_GEOCODE, $params, 'direct');
    }

    /**
     * {@inheritDoc}
     */
    public function reverseGeocode(float $lat, float $lon, $limit = 1): array
    {
        /*
         * If our api key is empty, we can't do anything so return an error.
         */
        $api_key = $this->getApiKey();
        if( $api_key == '' ) {
            return [
                'code' => 400,
                'error' => 'One Call app ID can not be empty.'
            ];
        }

        $params = [
            'timeout' => 30,
            'query' => [
                'lat' => $lat,
                'lon' => $lon,
                'limit' => $limit,
                'appid' => $api_key
            ]
        ];

        return $this->call(self::$CALL_TYPE_REVERSE_GEOCODE, $params, 'reverse');
    }

    /**
     * @inheritDoc
     */
    public function currentWeather(float $lat, float $lon, string $exclude = "", string $units = "standard", string $lang = ""): mixed
    {
        /*
         * If our api key is empty, we can't do anything so return an error.
         */
        $api_key = $this->getApiKey();
        if( $api_key == '' ) {
            return [
                'code' => 400,
                'error' => 'One Call app ID can not be empty.'
            ];
        }
        $params = [
            'timeout' => 30,
            'query' => [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $api_key,
            ]
        ];
        if (!empty($exclude)) {
            $params['query']['exclude'] = $exclude;
        }
        if (!empty($units)) {
            $params['query']['units'] = $units;
        }
        if (!empty($lang)) {
            $params['query']['lang'] = $lang;
        }

        return $this->call(self::$CALL_TYPE_WEATHER, $params);
    }

    /**
     * @inheritDoc
     */
    public function historicalWeather(float $lat, float $lon, int $date, string $timezone = "", string $units = "standard", string $lang = ""): array
    {
        /*
         * If our api key is empty, we can't do anything so return an error.
         */
        $api_key = $this->getApiKey();
        if( $api_key == '' ) {
            return [
                'code' => 400,
                'error' => 'One Call app ID can not be empty.'
            ];
        }
        $params = [
            'timeout' => 30,
            'query' => [
                'appid' => $api_key
            ],
        ];
        /*
         * @todo Check the date to make sure it is not before January 1, 1979 and not more than 4 days from the current date.
         */
        if ($date < strtotime('January 1, 1979') || $date > strtotime('+4 days')) {
            return [
                'code' => 400,
                'message' => 'Invalid date. Date must be greater than or equal to January 1, 1979 and less than or equal to four days from today.'
            ];
        }

        $params['query'] += [
            'dt' => $date,
            'lat' => $lat,
            'lon' => $lon,
        ];
        if ($units != 'standard') {
            $params['query']['units'] = $units;
        }
        if (!empty($lang)) {
            $params['query']['lang'] = $lang;
        }
        if (!empty($timezone)) {
            $params['query']['tz'] = $timezone;
        }

        return $this->call(self::$CALL_TYPE_WEATHER, $params, 'timemachine');
    }

    /**
     * @inheritDoc
     */
    public function weatherOverview(float $lat, float $lon, int $date, string $units = "standard"): array
    {
        /*
         * If our api key is empty, we can't do anything so return an error.
         */
        $api_key = $this->getApiKey();
        if( $api_key == '' ) {
            return [
                'code' => 400,
                'error' => 'One Call app ID can not be empty.'
            ];
        }
        $params = [
            'timeout' => 30,
            'query' => [
                'appid' => $api_key,
                'lat' => $lat,
                'lon' => $lon,
                'dt' => date('Y-m-d', $date)
            ]
        ];
        if ($units != 'standard') {
            $params['query']['units'] = $units;
        }
        if (!empty($lang)) {
            $params['query']['lang'] = $lang;
        }

        return $this->call(self::$CALL_TYPE_WEATHER, $params, 'overview');
    }

    /**
     * @inheritDoc
     */
    public function dailyAggregation(float $lat, float $lon, int $date, string $timezone = "", string $units = "standard", string $lang = ""): array
    {
        /*
         * If our api key is empty, we can't do anything so return an error.
         */
        $api_key = $this->getApiKey();
        if( $api_key == '' ) {
            return [
                'code' => 400,
                'error' => 'One Call app ID can not be empty.'
            ];
        }
        $params = [
            'timeout' => 30,
            'query' => [
                'appid' => $api_key,
                'lat' => $lat,
                'lon' => $lon,
                'dt' => date('Y-m-d', $date)
            ]
        ];
        if ($units != 'standard') {
            $params['query']['units'] = $units;
        }
        if (!empty($lang)) {
            $params['query']['lang'] = $lang;
        }

        return $this->call(self::$CALL_TYPE_WEATHER, $params, 'day_summary');
    }

    /**
     * Gets the API key based on the storage type in the configuration.
     * @return string
     */
    private function getApiKey()
    {
        $api_key = '';
        // API key is stored in the database.
        if ($this->config->get('api_key_storage_type') == 'database') {
            $api_key = $this->config->get('api_key');
        }
        // Try to get the api key from an environment variable.
        if (empty($api_key) && isset($_ENV[$this->config->get('environment_storage_key')])) {
            $api_key = $_ENV[$this->config->get('environment_storage_key')];
        }
        // Try to load the .env file using the phpdotenv library if it exists.
        if (empty($api_key) && class_exists('\Dotenv\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(\Drupal::root());
            $dotenv->load();
            if (isset($_ENV[$this->config->get('environment_storage_key')])) {
                $api_key = $_ENV[$this->config->get('environment_storage_key')];
            }
        }

        return $api_key;
    }

    /**
     * Utility function that makes all API calls. This function removes duplicate code from other methods.
     *
     * @param int $type
     * @param array $params
     * @param string $path
     * @return array
     */
    private function call(int $type, array $params, string $path = ""): array
    {
        switch ( $type ) {
            case self::$CALL_TYPE_GEOCODE:
                $uri = self::$GEOCODE_BASE;
                break;
            case self::$CALL_TYPE_HISTORICAL:
            case self::$CALL_TYPE_DAILY_AGGREGATION:
            case self::$CALL_TYPE_OVERVIEW:
            default:
                $uri = self::$ONECALL_BASE;
        }


        if (!empty($path)) {
            $uri .= '/' . $path;
        }
        $fp = NULL;

        /*
         * If the api config is set to debug, use a temp stream to capture the Guzzle request output so it can be logged.
         */
        if ($this->debug) {
            $fp = fopen("php://temp", 'rw');
            $params['debug'] = $fp;
        }

        try {
            $response = $this->client->request('GET', $uri, $params);
            $data = [
                'code' => 200,
                'result' => json_decode($response->getBody()->getContents())
            ];
        } catch (ClientException $c) {
            $data = [
                'code' => $c->getCode(),
                'error' => $c->getMessage()
            ];
            $this->logger->error('Error @code: @message', ['@code' => $c->getCode(), '@message' => $c->getMessage()]);
        } catch (GuzzleException $g) {
            $data = [
                'code' => $g->getCode(),
                'error' => $g->getMessage()
            ];
            $this->logger->error('Error @code: @message', ['@code' => $g->getCode(), '@message' => $g->getMessage()]);
        } catch (\Exception $e) {
            $data = [
                'code' => $e->getCode(),
                'error' => $e->getMessage()
            ];
            $this->logger->error('Error @code: @message', ['@code' => $e->getCode(), '@message' => $e->getMessage()]);
        } finally {
            // Increment the call count whether it was failed or not.
            $this->updateCallCount($type);
            /*
             * Write the stream contents to the log and close it if one was opened.
             */
            if (!is_null($fp)) {
                rewind($fp);
                $this->logger->debug('GuzzleClient Debug:<br><pre>@data</pre>', ['@data' => stream_get_contents($fp)]);
                $this->logger->debug('$data<br><pre>@data</pre>', ['@data' => print_r($data, true)]);
                fclose($fp);
            }
        }
        return $data;
    }

    /**
     * Updates the API call counter.
     *
     * @param int $type
     *
     * @return void
     * @throws \Exception
     */
    private function updateCallCount(int $type): void
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $result = $this->conn
            ->query("SELECT * FROM {onecall_count} WHERE call_date = :today", [':today' => $today->getTimestamp()]);
        $record = $result->fetch(PDO::FETCH_ASSOC);
        // We have a record for today, so we'll update the count.
        if ($record) {
            $id = (int) $record['id'];

            $fields = [];
            switch( $type ) {
                case self::$CALL_TYPE_WEATHER:
                    $fields['type_weather'] = $record['type_weather'] += 1;
                    break;
                case self::$CALL_TYPE_GEOCODE:
                    $fields['type_geocode'] = $record['type_geocde'] += 1;
                    break;
                case self::$CALL_TYPE_REVERSE_GEOCODE:
                    $fields['type_reverse_geocode'] = $record['type_reverse_geocode'] += 1;
                    break;
                case self::$CALL_TYPE_HISTORICAL:
                    $fields['type_historical'] = $record['type_historical'] += 1;
                    break;
                case self::$CALL_TYPE_DAILY_AGGREGATION:
                    $fields['type_aggregation'] = $record['type_aggregation'] += 1;
                    break;
                case self::$CALL_TYPE_OVERVIEW:
                    $fields['type_overview'] = $record['type_overview'] += 1;
                    break;
                default:
                    // do nothing.
            }

            $this->conn->update('onecall_count')
                ->fields($fields)
                ->condition('id', $id, '=')
                ->execute();
        } else {
            // We'll insert a new record.
            $fields = [
                'call_date' => $today->getTimestamp(),
                'type_weather' => $type == self::$CALL_TYPE_WEATHER ? 1 : 0,
                'type_geocode' => $type == self::$CALL_TYPE_GEOCODE ? 1 : 0,
                'type_reverse_geocode' => $type == self::$CALL_TYPE_REVERSE_GEOCODE ? 1 : 0,
                'type_historical' => $type == self::$CALL_TYPE_HISTORICAL ? 1 : 0,
                'type_aggregation' => $type == self::$CALL_TYPE_DAILY_AGGREGATION ? 1 : 0,
                'type_overview' => $type == self::$CALL_TYPE_OVERVIEW ? 1 : 0
            ];
            $this->conn->insert('onecall_count')
                ->fields($fields)
                ->execute();
        }
    }


}
