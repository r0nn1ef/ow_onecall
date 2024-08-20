<?php

declare(strict_types=1);

namespace Drupal\ow_onecall;

/**
 * @todo Add interface description.
 */
interface OWOneCallApiServiceInterface {

  /**
   * Geocode a city/state/country.
   * @see https://openweathermap.org/api/geocoding-api Geocode API documentation.
   *
   * @param string $country Two-letter ISO 3166 country code.
   * @param string $city Name of the city.
   * @param string $state Optional two-letter state code (US only).
   * @param int $limit Number of results in the response. Max of 5 results can be returned.
   * @return array
   */
  public function geocode(string $country, string $city, string $state="", int $limit=1): array;

  /**
   * Returns the current weather, minute forecast for 1 hour, hourly forecast for 48 hours, daily forecast for 8 days
   * and government weather alerts.
   *
   * @param float $lat Latitude
   * @param float $lon Longitude
   * @param string $exclude Optional comma-delimited list of weather data parts to be excluded from the API response.
   *  Valid values:
   *   - current
   *   - minutely
   *   - hourly
   *   - daily
   *   - alerts
   * @param string $units Units of measurement. Valid values:
   *  - standard (default)
   *  - metric
   *  - imperial
   * @param string $lang Output in specific language.
   * @return mixed \stdClass or boolean false
   */
  public function currentWeather(float $lat, float $lon, string $exclude="", string $units="standard", string $lang=""): mixed;

  /**
   * Returns weather data for given timestamp from 1st January 1979 till 4 days ahead forecast.
   *
   * @param float $lat Latitude
   * @param float $lon Longitude
   * @param int $date Timestamp (Unix time, UTC time zone). Data is available from January 1st, 1979 till 4 days ahead.
   * @param string $timezone If the service detected timezone for your location incorrectly you can specify correct
   *   timezone manually by adding timezone parameter in the ±XX:XX format
   * @param string $units Units of measurement. Valid values:
   *  - standard (default)
   *  - metric
   *  - imperial
   * @param string $lang Output in specific language.
   *
   * @return array
   */
  public function historicalWeather( float $lat, float $lon, int $date, string $timezone="", string $units="standard", string $lang="" ): array;

  /**
   * Returns aggregated weather data for a particular date.
   *
   * @param float $lat Latitude
   * @param float $lon Longitude
   * @param int $date int $date Timestamp (Unix time, UTC time zone). Data is available from
   *                  January 2nd, 1979 till a year and a half ahead.
   * @param string $units Units of measurement. standard, metric and imperial. Valid values:
   *  - standard (default)
   *  - metric
   *  - imperial
   * @param string $lang Output in specific language.
   * @return mixed \stdClass or boolean false
   */
  // public function dailyWeather(float $lat, float $lon, int $date, string $units="standard", string $lang=""): mixed;

  /**
   * @param float $lat Latitude
   * @param float $lon Longitude
   * @param int $date int $date Timestamp (Unix time, UTC time zone). Data is available from
   *                January 2nd, 1979 till a year and a half ahead.
   * @param string $units Units of measurement. standard, metric and imperial. Valid values:
   *  - standard (default)
   *  - metric
   *  - imperial
   * @return mixed array
   */
  public function weatherOverview(float $lat, float $lon, int $date, string $units="standard"): array;

  /**
   * @param float $lat Latitude
   * @param float $lon Longitude
   * @param int $date int $date Timestamp (Unix time, UTC time zone). Data is available from
   *                January 2nd, 1979 till a year and a half ahead.
   * @param string $units Units of measurement. Valid values:
   *  - standard (default)
   *  - metric
   *  - imperial
   * @param string $lang Output in specific language.
   * @return array
   */
  public function dailyAggregation( float $lat, float $lon, int $date, string $units="standard", string $lang="" ): array;

}
