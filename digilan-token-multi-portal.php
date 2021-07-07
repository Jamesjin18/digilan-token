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

class DigilanToken_Multi_Portal {

    add_action('admin_post_portal_post', 'DigilanToken_Multi_Portal::portal_post', 10, 2);
    add_shortcode('digilan_custom_portal_form', 'DigilanToken_Multi_Portal::custom_portal_form_shortcode');

    public static function add_new_ap_to_client($hostname, $mac, $user_id)
    {
        if (!$user_id) {
            error_log('Invalid user id');
            return;
        }
        $new_ap = array(
            $hostname => array(
                'mac' => $mac
            )
        );
        $ap_list = get_user_meta($user_id,'digilan-token-ap-list',true);
        if ($ap_list !== '') {
            $ap_list = (array) maybe_unserialize($ap_list);
        } else {
            $ap_list = array();
        }
        
        $ap_list = array_merge($ap_list,$new_ap);
        if (!update_user_meta($user_id,'digilan-token-ap-list',$ap_list)) {
            error_log('Fail to update ap list of a user ');
            return;
        }
    }

    public function update_client_ap_portal_data($hostname, $portal, $landing, $timeout)
    {
        $access_points = DigilanToken::$settings->get('access-points');
        $new_data = array(
            'portal' => $portal,
            'landing' => $landing,
            'timeout' => $timeout
        );
        $ap_list = self::search_ap_list_with_hostname($hostname);
        if (!$ap_list) {
            error_log('There is no hostname associated with a client');
            return;
        }
        foreach ($ap_list as $key=>$value) {
            $access_points[$key] = array_merge($access_points[$key],$new_data);
        }
        $data = array(
            'access-point' => $access_points
        )
        DigilanToken::$settings->update($data);
    }

    public static function search_ap_list_with_hostname($hostname)
    {
        $ap_list = array();
        $query = "SELECT 'user_id','meta_value' FROM {$wpdb->prefix}usermeta AS meta WHERE meta_key = '%s'";
        $query = $wpdb->prepare($query, 'digilan-token-ap-list');
        $rows = $wpdb->get_results($query);
        if (null === $rows)) {
            error_log('Access points are not available.');
            return false;
        } else {
            foreach ($rows as $row) {
                $row = (array) maybe_unserialize($row);
                $aps = $row->meta_value;
                foreach ($aps as $ap) {
                    if ($ap[$hostname]) {
                        $ap_list = $aps;
                        break 2;
                    }
                }
            }
        }
        if (count($ap_list)>0) {
            return $ap_list;
        }
        return false;
    }
    
    public function custom_portal_form_shortcode()
    {
        if (!get_current_user_id()) {
            $url_home = get_home_url();
            wp_redirect($url_home);
            exit;
        } else {
            $custom_form =
            '<div class="sub_form">	
                <h3>Personnaliser de votre portail</h3>
                <form action="<?php echo admin_url(\'admin-post.php\'); ?>" method="post">
                    <input type="hidden" name="action" value="portal_post">
                    <fieldset>
                        <legend>Portal Appearance</legend>
                        <p>
                            <label for="portal_heading">Entete:</label>
                            <input id="portal_heading" type="text" placeholder="Mon message d\'entete" name="portal_heading" required>
                        </p>
                        <p>
                            <label for="portal_button_style">Style des buttons:</label>
                            <select name="portal_button_style" id="portal_button_style_select">
                                <option value="default">Defaut</option>
                                <option value="icon">Icon</option>
                            </select>
                        </p>
                        <p>
                            <label for="portal_txt_color">Couleur:</label>
                            <input id="portal_txt_color" type="color" name="portal_txt_color" >
                        </p>
                        <p>
                            <label for="portal_front_size">Taille de la police:</label>
                            <input id="portal_front_size" type="number" name="portal_front_size" min="12" >
                        </p>
                    </fieldset>
                    <fieldset>
                        <legend>Portal-Captive Interaction</legend>
                        <p>
                            <label for="portal_redirect">Redirection:</label>
                            <input id="portal_redirect" type="text" placeholder="exemple.com" name="portal_redirect" required>
                        </p>
                        <p>
                            <label for="portal_provider">Moyen de connexion:</label>
                            <select name="portal_provider" id="portal_provider_select" multiple>
                                <option value="facebook">Facebook</option>
                                <option value="twitter">Twitter</option>s
                                <option value="google">Google</option>
                                <option value="mail">Mail</option>
                                <option value="transprent">Transparent</option>
                            </select>
                        <br>
                        <span style ="color: red">*Pour selectionner plusieurs moyen de connexion, maintenez le button Ctrl(Windows) ou Cmd (Mac)</span>
                        </p>
                    </fieldset>
                    <p>
                        <input id="submit_btn" type="submit" name="submit_btn" value=\'Ajouter\'>
                    </p>
                </form>
            </div>';
            return $custom_form;
        }
    }
    public function portal_post()
    {
        if (!empty($_POST)) {
            if(isset($_POST['submit_btn'])) {
                global $wpdb;
                $user_id = get_current_user_id();
                $corp_name = get_user_meta($user_id,'corp_name');
                if ($corp_name) {
                    $query = "SELECT ID FROM {$wpdb->prefix}posts WHERE post_name = '%s'";
                    $query = $wpdb->prepare($query, 'captive-portal-'.$corp_name);
                } else {
                    error_log(__('You don\'t have a company name','digilan-token'));
                    return;
                }
                $heading_msg = $_POST['portal_heading'];
                $button_style = $_POST['portal_button_style'];
                $color = $_POST['portal_txt_color'];
                $front_size = $_POST['portal_front_size'];
                $redirect = $_POST['portal_redirect'];
                $providers = $_POST['portal_provider'];
                $validate_providers = array(
                    'google' => 0,
                    'twitter' => 0,
                    'facebook' => 0,
                    'transparent' => 0,
                    'mail' => 0
                );
                foreach ($providers as $provider) {
                    $validate_providers[$provider] = 1;
                }
                $custom_array = array(
                    'heading' => $heading_msg,
                    'style' => $button_style,
                    'color' => $color,
                    'frontsize' =>$front_size,
                    'redirect' => $redirect,
                    'providers' => $validate_providers
                );
                $in = '[digilan_token style="%s" google="%s" twitter="%s" facebook="%s" transparent="%s" mail="%s" color="%s" fontsize="%s" redirect="%s" heading="%s"]';
                $shortcode = sprintf($in, $button_style, $validate_providers['google'], $validate_providers['twitter'],
                                    $validate_providers['facebook'], $validate_providers['transparent'], $validate_providers['mail'],
                                    $color, $front_size,$redirect, $heading_msg);
                if (!$shortcode) {
                    error_log('Could not format shortcode string.');
                    return;
                }
                $my_post_id = $wpdb->get_row($query, ARRAY_A);
                if (null === $my_post_id ) {
                    $current_user = wp_get_current_user();
                    $page = array(
                        'post_title' => 'captive-portal-'.$corp_name,
                        'post_status' => 'publish',
                        'post_author' => $user_id,
                        'post_type' => 'page',
                        'post_content' => do_shortcode($shortcode)
                    );
                    $post_id = wp_insert_post($page);
                } else {
                    $post_id = wp_update_post(array(
                        'ID' => $my_post_id,
                        'post_content' => do_shortcode($shortcode)
                    ));
                }
                if (is_wp_error($post_id)) {
                    error_log($post_id->get_error_message());
                    return;
                } else if (!update_user_meta($user_id,'digilan-token-custom-portal',$custom_array)) {
                    error_log('Fail to store custom data');
                    return;
                }
                
            }
        }
    }

}
?>