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
final class OWOneCallApiService implements OWOneCallApiServiceInterface {

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
  public static string $GEOCODE_BASE = 'http://api.openweathermap.org/geo/1.0/direct';

  /**
   * @var int $CALL_TYPE_GEOCODE
   */
  private static int $CALL_TYPE_GEOCODE = 0;

  /**
   * @var int $CALL_TYPE_WEATHER
   */
  private static int $CALL_TYPE_WEATHER = 1;

  /**
   * Constructs an OneCallService object.
   */
  public function __construct(
    Connection $conn,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    Client $client
  ) {
    $this->conn = $conn;
    $this->config = $configFactory->get('ow_onecall.settings');
    $this->debug = (bool) $this->config->get('debug') ?? FALSE;
    $this->logger = $loggerChannelFactory->get((new ReflectionClass($this))->getShortName());
    $this->client = $client;
  }

  /**
   * @inheritDoc
   */
  public function geocode(string $country, string $city, string $state = "", int $limit = 1): array
  {
    $data = [];
    $fp = NULL;
    $params = [
      'timeout' => 30,
      'query' => [
        'q' => ($city . ',' . (!empty($state) ? strtoupper($state) . ',' : '') . strtoupper($country)),
        'limit' => $limit,
        'appid' => $this->getApiKey()
      ]
    ];

    return $this->call( self::$CALL_TYPE_GEOCODE, $params );
  }

  /**
   * @inheritDoc
   */
  public function currentWeather(float $lat, float $lon, string $exclude="", string $units="standard", string $lang=""): mixed
  {
    $params = [
      'timeout' => 30,
      'query' => [
        'lat' => $lat,
        'lon' => $lon,
        'appid' => $this->getApiKey(),
      ]
    ];
    if( !empty($exclude) ) {
      $params['query']['exclude'] = $exclude;
    }
    if( !empty($units) ) {
      $params['query']['units'] = $units;
    }
    if( !empty($lang) ) {
      $params['query']['lang'] = $lang;
    }

    return $this->call(self::$CALL_TYPE_WEATHER, $params );
  }

  /**
   * @inheritDoc
   */
  public function historicalWeather(float $lat, float $lon, int $date, string $timezone="", string $units="standard", string $lang="" ): array
  {
    $params = [
      'timeout' => 30,
      'query' => [
        'appid' => $this->getApiKey()
      ],
    ];
    /*
     * @todo Check the date to make sure it is not before January 1, 1979 and not more than 4 days from the current date.
     */
    if ( $date < strtotime('January 1, 1979') || $date > strtotime('+4 days') ) {
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
    if ( $units != 'standard' ) {
      $params['query']['units'] = $units;
    }
    if ( !empty($lang) ) {
      $params['query']['lang'] = $lang;
    }
    if ( !empty($timezone) ) {
      $params['query']['tz'] = $timezone;
    }

    return $this->call( self::$CALL_TYPE_WEATHER, $params, 'timemachine');
  }

  /**
   * @inheritDoc
   */
  public function weatherOverview(float $lat, float $lon, int $date, string $units = "standard"): array
  {
    $params = [
      'timeout' => 30,
      'query' => [
        'appid' => $this->getApiKey(),
        'lat' => $lat,
        'lon' => $lon,
        'dt' => date('Y-m-d', $date)
      ]
    ];
    if ( $units != 'standard' ) {
      $params['query']['units'] = $units;
    }
    if ( !empty($lang) ) {
      $params['query']['lang'] = $lang;
    }

    return $this->call( self::$CALL_TYPE_WEATHER, $params, 'overview');
  }

  /**
   * @inheritDoc
   */
  public function dailyAggregation(float $lat, float $lon, int $date, string $timezone = "", string $units = "standard", string $lang = ""): array
  {
    $params = [
      'timeout' => 30,
      'query' =>  [
        'appid' => $this->getApiKey(),
        'lat' => $lat,
        'lon' => $lon,
        'dt' => date('Y-m-d', $date)
      ]
    ];
    if ( $units != 'standard' ) {
      $params['query']['units'] = $units;
    }
    if ( !empty($lang) ) {
      $params['query']['lang'] = $lang;
    }

    return $this->call( self::$CALL_TYPE_WEATHER, $params, 'day_summary');
  }

  /**
   * Gets the API key based on the storage type in the configuration.
   * @return string
   */
  private function getApiKey() {
    $api_key = '';
    // API key is stored in the database.
    if( $this->config->get('api_key_storage_type') == 'database' ) {
      $api_key = $this->getApiKey();
    }
    // API key is in an environment variable.
    if ( isset($_ENV[$this->config->get('environment_storage_key')]) ) {
      $api_key = $_ENV[$this->config->get('environment_storage_key')];
    }
    if ( $this->config->get('use_dot_env') ) {
      $dotenv = \Dotenv\Dotenv::createImmutable(\Drupal::root());
      $dotenv->load();
      if ( isset($_ENV[$this->config->get('environment_storage_key')]) ) {
        $api_key = $_ENV[$this->config->get('environment_storage_key')];
      }
    }
    if ( empty($api_key) ) {
      trigger_error('OpenWeather One Call API key is not set.', E_USER_ERROR);
    }
    return $api_key;
  }

  /**
   * Utility function that makes all API calls. This removes duplicate code from other methods.
   *
   * @param int $type
   * @param array $params
   * @param string $path
   * @return array
   */
  private function call(int $type, array $params, string $path=""): array {
    $uri = $type === self::$CALL_TYPE_GEOCODE ? self::$GEOCODE_BASE : self::$ONECALL_BASE;
    if( !empty($path) ) {
      $uri .= '/' . $path;
    }
    $fp = NULL;

    /*
     * If the api config is set to debug, use a temp stream to capture the Guzzle request output so it can be logged.
     */
    if ( $this->debug ) {
      $fp = fopen("php://temp", 'rw');
      $params['debug'] = $fp;
    }

    try {
      $response = $this->client->request('GET', $uri, $params);
      $data = [
        'code' => 200,
        'result' => json_decode($response->getBody()->getContents())
      ];
    } catch (ClientException $c ) {
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
    } catch (\Exception $e ) {
      $data = [
        'code' => $e->getCode(),
        'error' => $e->getMessage()
      ];
      $this->logger->error('Error @code: @message', ['@code' => $e->getCode(), '@message' => $e->getMessage()]);
    } finally {
      // Increment the call count whether it was failed or not.
      $this->updateCallCount();
      /*
       * Write the stream contents to the log and close it if one was opened.
       */
      if ( !is_null($fp) ) {
        rewind( $fp );
        $this->logger->debug('GuzzleClient Debug:<br><pre>@data</pre>', ['@data' => stream_get_contents($fp)] );
        $this->logger->debug('$data<br><pre>@data</pre>', ['@data' => print_r($data, true)] );
        fclose($fp);
      }
    }
    return $data;
  }

  /**
   * Updates the API call counter.
   *
   * @return void
   * @throws \Exception
   */
  private function updateCallCount():void {
    $today = new \DateTime();
    $today->setTime(0, 0, 0);
    $result = $this->conn
      ->query("SELECT * FROM {onecall_count} WHERE call_date = :today", [':today' => $today->getTimestamp()]);
    $record = $result->fetch(PDO::FETCH_ASSOC);
    // We have a record for today, so we'll update the count.
    if ( $record ) {
      $id = (int) $record['id'];
      $count = (int) $record['call_count'];
      $count += 1;
      $this->conn->update('onecall_count')
        ->fields([
          'call_count' => $count
        ])
        ->condition('id', $id, '=')
        ->execute();
    } else {
      // We'll insert a new record.
      $this->conn->insert('onecall_count')
        ->fields([
          'call_date' => $today->getTimestamp(),
          'call_count' => 1
        ])
        ->execute();
    }
  }


}
