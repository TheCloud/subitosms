<?php
/**
 * Plugin Name: SubitoSMS
 * Plugin URI: http://www.subitosms.it/wordpress
 * Description: Send SMS messages to all your subscribed users and add mobile number to registration form
 * Version: 1.2
 * Author: SubitoSMS (by Linkas SRL)
 * Author URI: http://www.subitosms.it/wordpress
 * License: GPLv3
 */
 
 /**
	Copyright 2013  Garda Informatica  (email : info@gardainformatica.it)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action('plugins_loaded', 'subitosms_plugins_loaded');
add_action('admin_init', 'subitosms_admin_init' );
add_action('admin_menu', 'subitosms_admin_menu');
add_action('admin_notices', 'subitosms_admin_notices_options' );

define("SUBITOSMS_DEFAULT_SENDER", "SubitoSMS");
define("SUBITOSMS_REGEX_VALIDATE_MOB_NUMBER", '/^[\+]{1}[0-9]{1,3}[0-9]{8,10}$/');
define("SUBITOSMS_TEST", false);

function subitosms_admin_notices_options() {
    settings_errors();
}
function subitosms_plugins_loaded(){
	load_plugin_textdomain('subitosms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

function subitosms_admin_init(){
	register_setting( 'subitosms_options_group', 'subitosms_options_array', 'subitosms_options_validate' );
	add_settings_section('subitosms_options_main', __('SubitoSMS Account','subitosms'), 'subitosms_options_main_callback', 'subitosms-admin-settings');
	add_settings_field('subitosms_options_username', __('Username','subitosms'), 'subitosms_options_username_callback', 'subitosms-admin-settings', 'subitosms_options_main');
	add_settings_field('subitosms_options_password', __('Password','subitosms'), 'subitosms_options_password_callback', 'subitosms-admin-settings', 'subitosms_options_main');
	add_settings_field('subitosms_options_sms_sender', __('SMS Sender','subitosms'), 'subitosms_options_smssender_callback', 'subitosms-admin-settings', 'subitosms_options_main');
	
	wp_register_style( 'subitosms_css', plugins_url('css/subitosms.css', __FILE__) );
	wp_enqueue_style( 'subitosms_css' );
	wp_register_script( 'subitosms_js', plugins_url('js/subitosms.js', __FILE__) );
	wp_enqueue_script( 'subitosms_js' );
	wp_localize_script( 'subitosms_js', 'subitosmsL10n', array(
			'publishOnFuture' =>  __('Schedule for:'),
			/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
			'dateFormat' => __('%1$s %2$s, %3$s @ %4$s : %5$s'),
		) );
		
}

function subitosms_admin_menu() {
	add_menu_page(__('SubitoSMS','subitosms'), __('SubitoSMS','subitosms'), 'manage_options', 'subitosms-admin-menu', 'subitosms_admin_send_sms', plugins_url( 'images/icon_16x16.png' , __FILE__ ) );
	add_submenu_page('subitosms-admin-menu', __('Send SMS','subitosms'), __('Send SMS','subitosms'), 'manage_options', 'subitosms-admin-menu', 'subitosms_admin_send_sms');
	add_submenu_page('subitosms-admin-menu', __('Settings','subitosms'), __('Settings','subitosms'), 'manage_options', 'subitosms-admin-settings', 'subitosms_admin_settings');
}

function subitosms_admin_send_sms() {
	if ( ! current_user_can( 'list_users' ) ){
		wp_die( __( 'You do not have sufficient permissions to sendsms.' ) );
	}
	$datef = __( 'M j, Y @ G:i' );
	$delay=0;
	$sms_message='';
	$edit = 0;
	
	$send_result_id=0;
	
    if (isset($_POST['action']) && 'action_sendsms'==$_POST['action']) { 
		$errors=false;
		$sms_message = $_POST['sms_message'];
		
		if (strlen( utf8_decode( trim($sms_message) ) )==0){
			add_settings_error('', 'sms_message', esc_html__( 'You can not send an empty message.'  ,'subitosms'), 'error' ); 
			$errors=true;
		}
		
		
		if (strlen( utf8_decode( trim($sms_message) ) )>160){
			add_settings_error('', 'sms_message', esc_html__( 'The message can not be longer than 160 characters.'  ,'subitosms'), 'error' ); 
			$errors=true;
		}
		
		foreach ( array('aa', 'mm', 'jj', 'hh', 'mn') as $timeunit ) {
			if ( !empty( $_POST['hidden_' . $timeunit] ) && $_POST['hidden_' . $timeunit] != $_POST[$timeunit] ) {
				$_POST['edit_date'] = '1';
				break;
			}
		}

		if ( !empty( $_POST['edit_date'] ) ) {
			$aa = $_POST['aa'];
			$mm = $_POST['mm'];
			$jj = $_POST['jj'];
			$hh = $_POST['hh'];
			$mn = $_POST['mn'];
			$ss = $_POST['ss'];
			$aa = ($aa <= 0 ) ? date('Y') : $aa;
			$mm = ($mm <= 0 ) ? date('n') : $mm;
			$jj = ($jj > 31 ) ? 31 : $jj;
			$jj = ($jj <= 0 ) ? date('j') : $jj;
			$hh = ($hh > 23 ) ? $hh -24 : $hh;
			$mn = ($mn > 59 ) ? $mn -60 : $mn;
			$ss = ($ss > 59 ) ? $ss -60 : $ss;
			$_POST['send_date'] = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $aa, $mm, $jj, $hh, $mn, $ss );
			$valid_date = wp_checkdate( $mm, $jj, $aa, $_POST['send_date'] );
			if ( !$valid_date ) {
				add_settings_error('', 'send_date', esc_html__( 'Whoops, the provided date is invalid.' ,'subitosms' ), 'error' ); 
				$errors=true;
			}
			$_POST['send_date_gmt'] = get_gmt_from_date( $_POST['send_date'] );
			$timestamp=subitosms_get_gmttime_from_date( $_POST['send_date'] );
			$delay=$timestamp-time();
			if ($delay<0){
				$delay=0;
			}
			
			$send_date =$_POST['send_date'];
		}		
		

        if (! (isset( $_POST['subitosms_nonce'] ) && wp_verify_nonce($_POST['subitosms_nonce'], 'send-sms')) )
        {
			$errors=true;
			add_settings_error('', 'nonce', esc_html__( 'Invalid nonce. Please retry' ,'subitosms'), 'error' ); 
        }
		$options = get_option('subitosms_options_array');
		if (!$errors && (empty($options['username']) || empty($options['password']))){
			add_settings_error('', 'no_settings',esc_html__('Before you can use this plugin you must configure it in the settings page.','subitosms'));
			$errors=true;
		}
		$mobnumbers=array();
		if (!$errors){
			$users = get_users('meta_key=subitosms_mobnumber&orderby=nicename');
			foreach ($users as $user) {
				$mob=get_user_meta($user->ID, 'subitosms_mobnumber', true);
				if (preg_match(SUBITOSMS_REGEX_VALIDATE_MOB_NUMBER,$mob)){
					$mobnumbers[]=$mob;
				}
			}
			if (empty($mobnumbers)){
				add_settings_error('', 'no_users', esc_html__( 'There are no users registered with mobile phone to which send the message.' ,'subitosms'), 'error' ); 
				$errors=true;
			}
		}
		if (!$errors){
			$errors=!subitosms_ws_send_message($options,$mobnumbers,$delay,$sms_message,$send_result_id);
		}
		
		if ($errors){
			$edit = 1;
		}else{
		
			$check_sped='https://www.subitosms.it/check_sped.php?'.build_query(array('tok'=>md5($options['username'].":".$options['password']),'sped'=>$send_result_id));
			add_settings_error('', 'take_over', esc_html__( 'Your message has been taken over by Subito SMS.' ,'subitosms'), 'updated' ); 
			add_settings_error('', 'submission_status', sprintf(__('You can see the status of submission at the <a href="%s" target="blank">following link</a>.','subitosms'),esc_attr($check_sped)), 'updated' ); 
		}
    } else {
		$edit = 0;
    }
	
	if (!$edit){
		$delay=0;
		$sms_message='';
	}
	if ($delay>0){
		$stamp = __('Scheduled for: <b>%1$s</b>');
		$date = date_i18n( $datef, strtotime( $send_date ) );
	}else{
		$send_date=current_time('mysql');
		$stamp = __('Send <b>immediately</b>');
		$date = date_i18n( $datef, strtotime( $send_date ) );
	}
	
	
	$options = get_option('subitosms_options_array');
	if (empty($options['username']) || empty($options['password'])){
		$settings_page_url=add_query_arg('page','subitosms-admin-settings', admin_url('admin.php') );
		?>
		<div class="wrap">
			<?php screen_icon('subitosms-send'); ?>
			<h2><?php echo esc_html__('SubitoSMS Send','subitosms'); ?></h2>
			<p><?php echo sprintf(__('Before you can use this plugin you must configure it in the <a href="%s">settings page</a>.','subitosms'),esc_attr($settings_page_url)); ?></p>
		</div>
		<?php
	}else{
		$credit=0;
		subitosms_ws_get_credit($options,$credit);
		$users = get_users('meta_key=subitosms_mobnumber&orderby=nicename');
		$users_count=count($users);
		$buy_credit_url='https://www.subitosms.it/listino_prezzi.php?'.build_query(array('tok'=>md5($options['username'].":".$options['password'])));
		
    ?>
		<div class="wrap">
			<?php screen_icon('subitosms-send'); ?>
			<h2><?php echo esc_html__('SubitoSMS Send','subitosms'); ?></h2>
			<?php settings_errors();?>
			<p><?php echo esc_html__('Send an sms to all registered users.','subitosms'); ?></p>
			<p><?php echo sprintf(__('You have a credit of %d messages and %d users with mobile.','subitosms'),$credit,$users_count); ?><br />
			<?php if($users_count>$credit): ?>
				<?php echo sprintf(__('You haven\'t enough credit to send messages, <a href="%s">buy more credit</a>.','subitosms'),esc_attr($buy_credit_url)); ?>
			<?php endif;?></p>
			<?php if ($credit>0){ ?>
				<form method="post" action="" name="sendsms_form" id="sendsms_form" class="validate">
				<?php wp_nonce_field('send-sms','subitosms_nonce'); ?>
				<input name="action" type="hidden" value="action_sendsms" />
	
	
				<table class="form-table">
				<tr class="form-field">
				<th scope="row"><label for="sms_datesend"><?php _e('Date','subitosms'); ?></label></th>
				
				<td id="submitdiv">
				<div>
					<span id="timestamp">
					<?php printf($stamp, $date); ?></span>
					<a href="#edit_timestamp" class="edit-timestamp hide-if-no-js"><?php _e('Edit') ?></a>
					<div id="timestampdiv" class="hide-if-js"><?php subitosms_touch_time($edit, $send_date); ?></div>
				</div>
				</td>
				
				</tr>
					<tr class="form-field form-required">
						<th scope="row"><label for="sms_message"><?php _e('Message','subitosms'); ?> <span class="description"><?php _e('(required)'); ?></span></label></th>
						<td><textarea name="sms_message" rows="5" cols="30" id="sms_message" maxlength="160" /><?php esc_textarea($sms_message); ?></textarea></td>
					</tr>
				</table>
	
				<?php submit_button( __( 'Send','subitosms'), 'primary', 'submit_sendsms' ); ?>
	
	
				</form>
			<?php } ?>
		</div>
    <?php
	}
	
}

function subitosms_admin_settings() {
	if ( ! current_user_can( 'manage_options' ) ){
		wp_die( __( 'You do not have sufficient permissions to manage options for this site.' ) );
	}
	?>
	<div class="wrap">
	<?php screen_icon('options-general'); ?>
		<h2><?php echo esc_html__('SubitoSMS Settings','subitosms'); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields('subitosms_options_group'); ?>
			<?php do_settings_sections('subitosms-admin-settings'); ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php	
}
function subitosms_options_main_callback() {
	echo '<p>'.esc_html__('Configure the plugin with the credentials of your account.','subitosms').'</p>';
	echo '<p>'.sprintf(__('If you do not have an account yet, sign up by following <a href="%s" target="_blank">this link</a>','subitosms'),'https://www.subitosms.it/signin.php').'</p>';
}
function subitosms_options_username_callback() {
	$options = get_option('subitosms_options_array');
	echo '<input id="subitosms_options_username" name="subitosms_options_array[username]" size="40" type="text" value="'.esc_attr($options['username']).'" />';
}
function subitosms_options_password_callback() {
	$options = get_option('subitosms_options_array');
	echo '<input id="subitosms_options_password" name="subitosms_options_array[password]" size="40" type="password" autocomplete="off" value="'.esc_attr($options['password']).'" />';
}
function subitosms_options_smssender_callback() {
	$options = get_option('subitosms_options_array');
	if (empty($options['smssender'])){
		$options['smssender']=SUBITOSMS_DEFAULT_SENDER;
	}
	echo '<input id="subitosms_options_smssender" name="subitosms_options_array[smssender]" size="40" type="text" value="'.esc_attr($options['smssender']).'" />';
}

function subitosms_options_validate($input) {
	//add_settings_error('', 'username_error', __('Expecting a Numeric value! Please fix.','subitosms'), 'error' );  
	$newinput=array();
	$newinput['username'] = trim($input['username']);
	$newinput['password'] = trim($input['password']);
	$newinput['smssender'] = trim($input['smssender']);
	if (empty($newinput['username'])){
		add_settings_error('', 'username_error', esc_html__('Please enter a valid Username','subitosms'), 'error' );  
	}
	if (empty($newinput['password'])){
		add_settings_error('', 'password_error', esc_html__('Please enter a valid Password','subitosms'), 'error' );  
	}
	if (empty($newinput['smssender'])){
		$newinput['smssender']=SUBITOSMS_DEFAULT_SENDER;
	}
	return $newinput;
}

function subitosms_touch_time( $edit = 1, $post_date, $tab_index = 0, $multi = 0 ) {
	global $wp_locale;

	$tab_index_attribute = '';
	if ( (int) $tab_index > 0 )
		$tab_index_attribute = " tabindex=\"$tab_index\"";

	$time_adj = current_time('timestamp');
	$jj = ($edit) ? mysql2date( 'd', $post_date, false ) : gmdate( 'd', $time_adj );
	$mm = ($edit) ? mysql2date( 'm', $post_date, false ) : gmdate( 'm', $time_adj );
	$aa = ($edit) ? mysql2date( 'Y', $post_date, false ) : gmdate( 'Y', $time_adj );
	$hh = ($edit) ? mysql2date( 'H', $post_date, false ) : gmdate( 'H', $time_adj );
	$mn = ($edit) ? mysql2date( 'i', $post_date, false ) : gmdate( 'i', $time_adj );
	$ss = ($edit) ? mysql2date( 's', $post_date, false ) : gmdate( 's', $time_adj );

	$cur_jj = gmdate( 'd', $time_adj );
	$cur_mm = gmdate( 'm', $time_adj );
	$cur_aa = gmdate( 'Y', $time_adj );
	$cur_hh = gmdate( 'H', $time_adj );
	$cur_mn = gmdate( 'i', $time_adj );

	$month = "<select " . ( $multi ? '' : 'id="mm" ' ) . "name=\"mm\"$tab_index_attribute>\n";
	for ( $i = 1; $i < 13; $i = $i +1 ) {
		$monthnum = zeroise($i, 2);
		$month .= "\t\t\t" . '<option value="' . $monthnum . '"';
		if ( $i == $mm )
			$month .= ' selected="selected"';
		/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
		$month .= '>' . sprintf( __( '%1$s-%2$s' ), $monthnum, $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) ) . "</option>\n";
	}
	$month .= '</select>';

	$day = '<input type="text" ' . ( $multi ? '' : 'id="jj" ' ) . 'name="jj" value="' . $jj . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	$year = '<input type="text" ' . ( $multi ? '' : 'id="aa" ' ) . 'name="aa" value="' . $aa . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" />';
	$hour = '<input type="text" ' . ( $multi ? '' : 'id="hh" ' ) . 'name="hh" value="' . $hh . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	$minute = '<input type="text" ' . ( $multi ? '' : 'id="mn" ' ) . 'name="mn" value="' . $mn . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';

	echo '<div class="timestamp-wrap">';
	/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
	printf( __( '%1$s %2$s, %3$s @ %4$s : %5$s' ), $month, $day, $year, $hour, $minute );

	echo '</div><input type="hidden" id="ss" name="ss" value="' . $ss . '" />';

	if ( $multi ) return;

	echo "\n\n";
	foreach ( array('mm', 'jj', 'aa', 'hh', 'mn') as $timeunit ) {
		echo '<input type="hidden" id="hidden_' . $timeunit . '" name="hidden_' . $timeunit . '" value="' . $$timeunit . '" />' . "\n";
		$cur_timeunit = 'cur_' . $timeunit;
		echo '<input type="hidden" id="'. $cur_timeunit . '" name="'. $cur_timeunit . '" value="' . $$cur_timeunit . '" />' . "\n";
	}
?>

<p>
<a href="#edit_timestamp" class="save-timestamp hide-if-no-js button"><?php _e('OK'); ?></a>
<a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js"><?php _e('Cancel'); ?></a>
</p>
<?php
}

function subitosms_get_gmttime_from_date( $string, $format = 'Y-m-d H:i:s' ) {
	$tz = get_option( 'timezone_string' );
	if ( $tz ) {
		$datetime = date_create( $string, new DateTimeZone( $tz ) );
		if ( ! $datetime )
			return 0;
		$datetime->setTimezone( new DateTimeZone( 'UTC' ) );
		return $datetime->getTimestamp();
	} else {
		if ( ! preg_match( '#([0-9]{1,4})-([0-9]{1,2})-([0-9]{1,2}) ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})#', $string, $matches ) )
			return 0;
		$string_time = gmmktime( $matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1] );
		return $string_time - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
	}
	return 0;
}


/**
 * Add subitosms_mobnumber field to profile page and edit user
 */

 
add_action ( 'show_user_profile', 'subitosms_show_extra_profile_fields' );
add_action ( 'edit_user_profile', 'subitosms_show_extra_profile_fields' );

function subitosms_show_extra_profile_fields ( $user )
{
?>
	<h3><?php echo esc_html__('SubitoSMS Extra profile information','subitosms'); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="subitosms_mobnumber"><?php echo esc_html__('Mobile number','subitosms'); ?></label></th>
			<td>
				<input type="text" name="subitosms_mobnumber" id="subitosms_mobnumber" value="<?php echo esc_attr( get_the_author_meta( 'subitosms_mobnumber', $user->ID ) ); ?>" class="regular-text" /><br />
				<span class="description"><?php echo esc_html__('Please enter your mobile number. Eg. +39 338...','subitosms'); ?></span>
			</td>
		</tr>
	</table>
<?php
}

add_filter('user_profile_update_errors', 'subitosms_extra_user_profile_update_errors', 10, 3);

function subitosms_extra_user_profile_update_errors($errors, $update, $user) {
	if ( !current_user_can( 'edit_user', $user->ID ) ){
		$errors->add('no_edit_user_permission',__( '<strong>ERROR</strong>: You do not have permission to edit this user' ,'subitosms'));
		return false;
	}
	if (!empty($_POST['subitosms_mobnumber'])){
		if (!preg_match(SUBITOSMS_REGEX_VALIDATE_MOB_NUMBER,$_POST['subitosms_mobnumber'])){
			$errors->add( 'not_valid_subitosms_mobnumber', __( '<strong>ERROR</strong>: Please enter a valid mobile number. Eg. +39 338...' ,'subitosms') );
			return;
		}
	}
	update_user_meta($user->ID, 'subitosms_mobnumber', isset($_POST['subitosms_mobnumber'])?$_POST['subitosms_mobnumber']:'' );
}

/**
 * Add subitosms_mobnumber field to registration form
 */

add_action('register_form','subitosms_extra_register_form');
add_action('register_post','subitosms_extra_register_post',10,3);
add_action('user_register', 'subitosms_extra_user_register');

function subitosms_extra_register_form()
{
	$number=isset($_POST['subitosms_mobnumber'])?$_POST['subitosms_mobnumber']:'';
?>
	<p>
	<label><?php echo esc_html__('Mobile number','subitosms'); ?><br/>
	<input id="subitosms_mobnumber" type="text" class="input" size="14" value="<?php echo esc_attr($number); ?>" name="subitosms_mobnumber" />
	</label>
	</p>
<?php
}

function subitosms_extra_register_post ( $login, $email, $errors )
{
	global $subitosms_mobnumber;
	if (!preg_match(SUBITOSMS_REGEX_VALIDATE_MOB_NUMBER,$_POST['subitosms_mobnumber'])){
		$errors->add( 'not_valid_subitosms_mobnumber', __( '<strong>ERROR</strong>: Please enter a valid mobile number' ,'subitosms') );
		return;
	}
	
	$subitosms_mobnumber = $_POST['subitosms_mobnumber'];
}

function subitosms_extra_user_register ( $user_id, $password = "", $meta = array() )
{
	update_user_meta( $user_id, 'subitosms_mobnumber', $_POST['subitosms_mobnumber'] );
}

/*
 * Web Service
*/

function subitosms_ws_get_credit($options,&$credit){
	$credit=0;
	$result=subitosms_ws_query(array('username'=>$options['username'],'password'=>$options['password']));
	if ($result===false){
		return;
	}
	$parts=explode(':',trim($result));
	if (count($parts)==2 && $parts[0]=='credito'){
		$credit=$parts[1];	
	}else{
		add_settings_error('', 'error_get_credit', esc_html__( sprintf('Error requesting the available credit to SubitoSMS - %s',$result) ,'subitosms'), 'error' ); 
	}
	
}
function subitosms_ws_send_message($options,$mobnumbers,$delay,$sms_message,&$send_result_id){
	$send_result_id=0;
	if (empty($options['smssender'])){
		$options['smssender']=SUBITOSMS_DEFAULT_SENDER;
	}
	$query=array(
			'username'=>$options['username'],
			'password'=>$options['password'],
			'mitt'=>$options['smssender'],
			'testo'=>$sms_message,
			'dest'=>implode(',',$mobnumbers)
		);
	$delay=(int)($delay/60);
	if ($delay>0){
		$query['delay']=$delay;
	}
	
	$result=subitosms_ws_query($query);
	if ($result===false){
		return false;
	}
	$parts=explode(':',trim($result));
	if (count($parts)==2 && $parts[0]=='id'){
		$send_result_id=$parts[1];	
	}else{
		add_settings_error('', 'error_send_message', esc_html__( sprintf('Error sending the message to Subito SMS - %s',$result) ,'subitosms'), 'error' ); 
		return false;
	}
	return true;
}

function subitosms_ws_query($fields){
	if (SUBITOSMS_TEST){
		$fields['test']=1;
	}
	
	$url="https://www.subitosms.it/gateway.php";
	
	$response = wp_remote_post( $url, array(
		'method' => 'POST',
		'timeout' => 30,
		'redirection' => 3,
		'httpversion' => '1.0',
		'blocking' => true,
		'headers' => array(
            "Cache-Control: no-cache",
            "Pragma: no-cache"
		),
		'body' => $fields,
		'cookies' => array(),
		'user-agent' => 'SubitoSMS Wordpress',
		'sslverify' => false
	    )
	);
	
	if ( is_wp_error( $response ) ) {
		add_settings_error('', 'error_query', esc_html__( sprintf('Error contacting Subito SMS - %s:%s',$response->get_error_code(),$response->get_error_message()) ,'subitosms'), 'error' ); 
		return false;
	} else {
		return $response['body'];
	}
		

}
