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

class DigilanTokenMultiPortal {
    
    public static function link_client_ap($hostname, $user_id)
    {
        $ap_list = self::get_valid_ap_list($user_id);
        if (false === $ap_list) {
            error_log($user_id.' is invalid - from link_client_ap function');
            return false;
        }
        $settings = clone DigilanToken::$settings;
        $access_points = $settings->get('access-points');
        if (empty($access_points[$hostname])) {
            error_log($hostname.' is not linked to an AP - from link_client_ap function');
            return false;
        }
        if (false === empty($ap_list[$hostname])) {
            error_log($hostname.' is already linked - from link_client_ap function');
            return false;
        }
        $specific_ap_settings = $access_points[$hostname]['specific_ap_settings'];
        if (empty($specific_ap_settings)) {
            $global_settings = array(
                'portal-page' => $settings->get('portal-page'),
                'landing-page' => $settings->get('landing-page'),
                'timeout' => $settings->get('timeout'),
                'schedule_router' => $settings->get('schedule_router')
            );
            $default_settings = array_merge($global_settings,$access_points[$hostname]);
            $specific_ap_settings = new DigilanPortalModel('Borne Autonome',current_time('mysql'), 'FR', '{"0":[],"1":[],"2":[],"3":[],"4":[],"5":[],"6":[]}');
            $specific_ap_settings->update_settings($default_settings);
            $access_points[$hostname]['specific_ap_settings'] = $specific_ap_settings;
            DigilanToken::$settings->update(array(
                'access-points' => $access_points
            ));
        }

        $ap_list[$hostname] = $specific_ap_settings;
        $update_result = self::update_client_ap_list($user_id,$ap_list);
        return $update_result;
    }

    public static function unlink_client_ap($hostname, $user_id)
    {
        $ap_list = self::get_valid_ap_list($user_id);
        $settings = clone DigilanToken::$settings;
        $access_points = $settings->get('access-points');
        if (empty($ap_list[$hostname])) {
            error_log('This user '.$user_id.'doesn t have '.$hostname.' as ap. - from unlink_client_ap function');
            return false;
        }
        if (false == empty($access_points[$hostname]['specific_ap_settings'])) {
            unset($access_points[$hostname]['specific_ap_settings']);
            DigilanToken::$settings->update(array(
                'access-points' => $access_points
            ));
        }
        unset($ap_list[$hostname]);
        $update_result = self::update_client_ap_list($user_id,$ap_list);
        return $update_result;
    }

    public static function update_settings($new_settings, $hostname='', $user_id=null)
    {
        $shared_settings = array_filter($new_settings, function($k) {
            $shared_key = ['portal-page','portal_page','landing-page','landing_page','error_page','schedule_router'];
            return in_array($k,$shared_key);
        },ARRAY_FILTER_USE_KEY);

        $ap_settings = array_filter($new_settings, function($k) {
            $ap_key = ['timeout','ssid','country_code','access','mac'];
            return in_array($k,$ap_key);
        },ARRAY_FILTER_USE_KEY);
        if (false == empty($shared_settings)) {
            $result_update_all = self::update_to_all_client_ap_settings($shared_settings,$user_id);
        }
        if (false == $result_update_all && isset($result_update_all)) {
            return false;
        }
        if (false == empty($ap_settings)) {
            $result_update_ap = self::update_to_a_client_settings($hostname, $ap_settings);
        }
        if (false == $result_update_ap && isset($result_update_ap)) {
            return false;
        }
        return true;
    }

    public static function update_to_all_client_ap_settings($new_shared_settings, $user_id=null)
    {
        $settings = clone DigilanToken::$settings;
        $access_points = $settings->get('access-points');

        if (false == isset($user_id)) {
            DigilanToken::$settings->update($new_shared_settings);
            return true;
        }
        $ap_list = self::get_valid_ap_list($user_id);
        if (empty($ap_list)) {
            error_log('There is no ap linked to user '.$user_id.' - from update_to_all_client_ap_settings function');
            return false;
        }

        foreach ($ap_list as $curr_hostname=>$value) {
            if (empty($access_points[$curr_hostname])) {
                error_log($curr_hostname.' is not registered as ap from user ap list '.$user_id.' - from update_to_all_client_ap_settings function');
                die();
            }
            if (isset($access_points[$curr_hostname]['specific_ap_settings'])) {
                $current_specific_ap_settings = clone $access_points[$curr_hostname]['specific_ap_settings'];
                $current_specific_ap_settings->update_settings($new_shared_settings);
                $access_points[$curr_hostname]['specific_ap_settings'] = $current_specific_ap_settings;
            }
        }
        DigilanToken::$settings->update(array(
            'access-points' => $access_points
        ));
        return true;
    }

    public static function update_to_a_client_settings($hostname,$new_settings)
    {
        $settings = clone DigilanToken::$settings;
        $access_points = $settings->get('access-points');
        
        if (false == isset($access_points[$hostname]['specific_ap_settings'])) {
            $access_points[$hostname] = array_merge($access_points[$hostname],$new_settings);
            DigilanToken::$settings->update(array(
                'access-points' => $access_points
            ));
            return true;
        }
        $specific_ap_settings = clone $access_points[$hostname]['specific_ap_settings'];
        $specific_ap_settings->update_settings($new_settings);
        $access_points[$hostname]['specific_ap_settings'] = $specific_ap_settings;
        DigilanToken::$settings->update(array(
            'access-points' => $access_points
        ));
        return true;
    }

    public static function get_client_ap_list_from_hostname($hostname)
    {
        global $wpdb;
        $ap_list = array();
        $query = "SELECT user_id,meta_value FROM {$wpdb->prefix}usermeta AS meta WHERE meta_key = '%s'";
        $query = $wpdb->prepare($query, 'digilan-token-ap-list');
        $rows = $wpdb->get_results($query);
        if (is_null($rows)) {
            error_log('There are no Access points which is linked to a client,'.$hostname.'could not be be found. - from get_client_ap_list_from_hostname function');
            return false;
        }
        $last_error = $wpdb->last_error;
        if (!empty($last_error)) {
            error_log('Database error occured during db request, '.$last_error.' - from get_client_ap_list_from_hostname function');
            die();
        }
        foreach ($rows as $row) {
            $row = (array) maybe_unserialize($row);
            $current_id = (int) maybe_unserialize($row['user_id']);
            $aps = (array) maybe_unserialize($row['meta_value']);
            if (false === empty($aps[$hostname])) {
                $user_id = $current_id;
                $ap_list = $aps;
                break;
            }
        }
        if (empty($ap_list)) {
            error_log($hostname.' is not linked to a client. - from get_client_ap_list_from_hostname function');
            return false;
        }
        $result = array(
            'ap_list' => $ap_list,
            'user_id' => $user_id
        );
        return $result;
    }
    
    public static function remove_all_ap_from_client($user_id)
    {
        $ap_list = self::get_valid_ap_list($user_id);
        $settings = clone DigilanToken::$settings;
        $access_points = $settings->get('access-points');

        foreach ($ap_list as $key => $value) {
            if (empty($access_points[$key])) {
                error_log($key.' is linked to user '.$user_id.' but it is not registered. - from remove_all_ap_from_client function');
                die();
            }
            unset($access_points[$key]);
        }
        DigilanToken::$settings->update(array(
            'access-points' => $access_points
        ));
        $update_result = self::update_client_ap_list($user_id,array());
        return $update_result;
    }

    /**
     * @param int $user_id
     * @param array $ap_list
     */
    public static function update_client_ap_list($user_id,$ap_list)
    {
        $update_result = update_user_meta($user_id,'digilan-token-ap-list',$ap_list);
        if (false === $update_result) {
            error_log('Fail to update ap list of a user '.$user_id.' - from update_client_ap_list function');
            return false;
        }
        return true;
    }

    public static function get_valid_ap_list($user_id)
    {
        if (false === self::is_user_id_exist($user_id)) {
            error_log('Invalid user id '.$user_id.' - from get_ap_list function');
            die();
        }
        $ap_list = get_user_meta($user_id,'digilan-token-ap-list',true);
        if ($ap_list === false) {
            error_log('Could not get user ap list, user id '.$user_id.'invalid - from get_ap_list function');
            die();
        }
        if (empty($ap_list)) {
            $ap_list = [];
        }
        return (array) maybe_unserialize($ap_list);
    }

    public static function is_user_id_exist($user_id)
    {
        $user = get_userdata($user_id);
        return (bool)$user;
    }
}