<?php

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */
class DigilanMultiPortal
{

  /** @var DigilanTokenSettings */
  public static $specific_settings;

  public static function init()
  {
    self::$specific_settings = new DigilanTokenSettings('digilan-token_specific_settings', array());
  }

  public function get_config($hostname) 
  {
    $settings = clone DigilanToken::$settings;
    $access_points = $settings->get('access-points');

    $specific_settings = self::$specific_settings->getAll();

    if (false == isset($access_points[$hostname])) {
      error_log($hostname.' is not link to any ap.');
      return false;
    }

    $default_settings = array(
      'portal_page' => $settings->get('portal-page'),
      'landing_page' => $settings->get('landing-page'),
      'timeout' => $access_points[$hostname]['timeout'],
      'error_page' => $settings->get('error_page'),
      'schedule_router' => $settings->get('schedule_router'),
      'ssid' => $access_points[$hostname]['ssid'],
      'country_code' => $access_points[$hostname]['country_code'],
      'access' => $access_points[$hostname]['access'],
      'schedule' => $access_points[$hostname]['schedule'],
      'mac' => $access_points[$hostname]['mac']
    );
    $mac = $access_points[$hostname]['mac'];

    foreach ($default_settings as $setting => $value) {
      if (isset($specific_settings[$mac][$setting])) {
        $default_settings[$setting] = $specific_settings[$mac][$setting];
      }
    }
    return $default_settings;
  }

}