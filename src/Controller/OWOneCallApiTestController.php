<?php

/**
 * @file
 * Controller for testing various OpenWeather API methods.
 */

declare(strict_types=1);

namespace Drupal\ow_onecall\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ow_onecall\OWOneCallApiServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Returns responses for OpenWeather API routes.
 */
final class OWOneCallApiTestController extends ControllerBase {

  /**
   * @var OWOneCallApiServiceInterface $api
   */
  protected OWOneCallApiServiceInterface $api;

  /**
   * @var RequestStack $requestStack
   */
  protected RequestStack $requestStack;

  /**
   * The controller constructor.
   */
  public function __construct(
    RequestStack $stack,
    OWOneCallApiServiceInterface $api,
  ) {
    $this->requestStack = $stack;
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('request_stack'),
      $container->get('ow_onecall.api'),
    );
  }

  public function historical():void {
    $currentRequest = $this->requestStack->getCurrentRequest();
    $city = $currentRequest->query->get('city') ?? '';
    $state = $currentRequest->query->get('state') ?? '';
    $country = $currentRequest->query->get('country') ?? '';
    $date = (int) $currentRequest->query->get('date') ?? time();
    $units = $currentRequest->query->get('units') ?? 'standard';
    $lang = $currentRequest->query->get('lang') ?? '';
    // First we need to get the lat/long for the city.
    $geo = $this->api->geocode($country, $city, $state);
    if ( $geo['code'] == 200 ) {
      $geoloc = $geo['result'][0];
      $lat = $geoloc->lat;
      $lon = $geoloc->lon;
      $data = $this->api->historicalWeather($lat, $lon, $date, $units, $lang);
      header('content-type:text/plain');
      var_dump($data);
      exit;
    }
    throw new BadRequestHttpException('Unable to get the specified weather data.');
  }

  /**
   * Test the geocode endpoint. This will dump the data returned from the api service call. You should supply the
   * following parameters as a query string values:
   * - country: Two-letter ISO 3166 country code.
   * - city: Name of the city.
   * - state: Optional two-letter state code (US only).
   * - exclude: Optional comma-delimited list of weather data parts to be excluded from the API response
   * - units: Units of measurement. standard, metric and imperial.
   * - lang: Output in specific language.
   * @return void
   */
  public function currentWeather(): void {
    $currentRequest = $this->requestStack->getCurrentRequest();
    $city = $currentRequest->query->get('city') ?? '';
    $state = $currentRequest->query->get('state') ?? '';
    $country = $currentRequest->query->get('country') ?? '';
    $exclude = $currentRequest->query->get('exclude') ?? '';
    $units = $currentRequest->query->get('units');
    $lang = $currentRequest->query->get('lang');
    // First we need to get the lat/long for the city.
    $geo = $this->api->geocode($country, $city, $state);
    if ( $geo['code'] == 200 ) {
      $geoloc = $geo['result'][0];
      $lat = $geoloc->lat;
      $lon = $geoloc->lon;
      $data = $this->api->currentWeather($lat, $lon, $exclude, $units, $lang);
      header('content-type:text/plain');
      var_dump($data);
      exit;
    }
    throw new BadRequestHttpException('Unable to get the specified weather data.');
  }

  /**
   * Test the geocode endpoint. This will dump the data returned from the api service call. You should supply the
   * following parameters as a query string values:
   * - country: Two-letter ISO 3166 country code.
   * - city: Name of the city.
   * - state: Optional two-letter state code (US only).
   *
   * @param Request $request
   * @return void
   */
  public function geocode() {
    $currentRequest = $this->requestStack->getCurrentRequest();
    $city = $currentRequest->query->get('city') ?? '';
    $state = $currentRequest->query->get('state') ?? '';
    $country = $currentRequest->query->get('country') ?? '';
    $data = $this->api->geocode($country, $city, $state);
    header('content-type:text/plain');
    var_dump($data);
    exit;
  }

}
