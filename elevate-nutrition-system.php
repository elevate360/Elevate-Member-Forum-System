<?php
/*
Plugin Name: Elevate Nutrition System
Plugin URI: https://elevate360.com.au/
Description: Nutrition messaging / forum system. Used to provide a way for users and practitioners to interact to exchange nutrition advice. 
Version: 1.0.0
Author: Simon Codrington
Author URI: http://simoncodrington.com.au
Text Domain: elevate-nutrition-system
Domain Path: /languages
*/


class elevate_nutrition_system{
	
	public static $messaging_system = null;
	private static $instance = null;
	
	//constructor
	public function __construct(){
		
		//include messaging class 
		include(plugin_dir_path(__FILE__) . '/inc/elevate-messaging-system.php'); 
		//create messaging class instance
		$this->messaging_system = elevate_messaging::getInstance();
		
		
		add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts_and_styles'));
		
		
		add_action('rcp_form_errors', array($this, 'validate_new_rcp_fields'), 10, 1); //check for errors
		add_action('rcp_form_processing', array($this, 'save_user_extra_meta_fields'), 10, 2); //save from front end registration form (signup)
		add_action('rcp_user_profile_updated', array($this, 'update_user_extra_meta_fields'), 10, 2); //save from front end user account edit form (user edit)		
		add_action('rcp_profile_editor_after', array($this, 'display_user_extra_meta_fields_frontend'), 10, 1); //display extra user fields in user account\
		
		//allow SVG
		add_action('upload_mimes', array($this, 'add_file_types_to_uploads'));
		

	}
	
	public function add_file_types_to_uploads($file_types){
		
		$new_filetypes = array();
	    $new_filetypes['svg'] = 'image/svg+xml';
	    $file_types = array_merge($file_types, $new_filetypes );
	
	    return $file_types;
	}
	
	//front end scripts and styles
	public function enqueue_public_scripts_and_styles(){
		
	     wp_enqueue_script('el_jquery_validate_script', plugins_url('/js/jquery.validate.js', __FILE__), array('jquery'));
		 wp_enqueue_style('el_nutrition_system_style', plugins_url('/css/elevate-nutrition-system-styles.css', __FILE__));
		 wp_enqueue_script('el_nutrition_system_script', plugins_url('/js/elevate_nutrition_system_script.js', __FILE__), array('jquery', 'el_jquery_validate_script'));
	}
	

	
	//TODO: REMOVE ALSO, FUNCTION MOVED INTO MESSAGHING CLASS
	//save the new user fields entered on signup
	public function save_user_extra_meta_fields($posted, $user_id ){
	
		// //check for user fields to save
		// if(isset($posted['rcp_user_lightest_weight'])){
			// update_user_meta($user_id, 'rcp_user_lightest_weight' , sanitize_text_field($posted['rcp_user_lightest_weight']));
		// }
		// if(isset($posted['rcp_user_heaviest_weight'])){
			// update_user_meta($user_id, 'rcp_user_heaviest_weight' , sanitize_text_field($posted['rcp_user_heaviest_weight']));
		// }
		// if(isset($posted['rcp_user_height'])){
			// update_user_meta($user_id, 'rcp_user_height' , sanitize_text_field($posted['rcp_user_height']));
		// }
		// if(isset($posted['rcp_user_days_gym'])){
			// update_user_meta($user_id, 'rcp_user_days_gym' , sanitize_text_field($posted['rcp_user_days_gym']));
		// }
		// if(isset($posted['rcp_user_member_of_gym'])){
			// update_user_meta($user_id, 'rcp_user_member_of_gym' , sanitize_text_field($posted['rcp_user_member_of_gym']));
		// }
// 
		// //handeled checkboxes
		// if(isset($posted['rcp_user_exercise_preference'])){
			// if(is_array($posted['rcp_user_exercise_preference'])){
				// update_user_meta($user_id, 'rcp_user_exercise_preference' , json_encode($posted['rcp_user_exercise_preference']));
			// }else{
				// update_user_meta($user_id, 'rcp_user_exercise_preference' , sanitize_text_field($posted['rcp_user_exercise_preference']));
			// }	
		// }else{
			// update_user_meta($user_id, 'rcp_user_exercise_preference' , ''); 
		// }
// 		
		// if(isset($posted['rcp_user_exercise_time'])){
			// update_user_meta($user_id, 'rcp_user_exercise_time' , sanitize_text_field($posted['rcp_user_exercise_time']));
		// }
		// if(isset($posted['rcp_user_exercise_extra'])){
			// update_user_meta($user_id, 'rcp_user_exercise_extra' , sanitize_text_field($posted['rcp_user_exercise_extra']));
		// }
		// if(isset($posted['rcp_user_medical_history'])){
			// update_user_meta($user_id, 'rcp_user_medical_history' , sanitize_text_field($posted['rcp_user_medical_history']));
		// }
		// if(isset($posted['rcp_user_occupation'])){
			// update_user_meta($user_id, 'rcp_user_occupation' , sanitize_text_field($posted['rcp_user_occupation']));
		// }
		// if(isset($posted['rcp_user_work_hours'])){
			// update_user_meta($user_id, 'rcp_user_work_hours' , sanitize_text_field($posted['rcp_user_work_hours']));
		// }
		// if(isset($posted['rcp_user_main_income_earner'])){
			// update_user_meta($user_id, 'rcp_user_main_income_earner' , sanitize_text_field($posted['rcp_user_main_income_earner']));
		// }
		// if(isset($posted['rcp_user_family_information'])){
			// update_user_meta($user_id, 'rcp_user_family_information' , sanitize_text_field($posted['rcp_user_family_information']));
		// }
		// if(isset($posted['rcp_user_smoker'])){
			// update_user_meta($user_id, 'rcp_user_smoker' , sanitize_text_field($posted['rcp_user_smoker']));
		// }
		// if(isset($posted['rcp_user_smoker_amount'])){
			// update_user_meta($user_id, 'rcp_user_smoker_amount' , sanitize_text_field($posted['rcp_user_smoker_amount']));
		// }
		// if(isset($posted['rcp_user_drinker'])){
			// update_user_meta($user_id, 'rcp_user_drinker' , sanitize_text_field($posted['rcp_user_drinker']));
		// }
		// if(isset($posted['rcp_user_drinker_amount'])){
			// update_user_meta($user_id, 'rcp_user_drinker_amount' , sanitize_text_field($posted['rcp_user_drinker_amount']));
		// }
		// if(isset($posted['rcp_user_drinker_action'])){
			// update_user_meta($user_id, 'rcp_user_drinker_action' , sanitize_text_field($posted['rcp_user_drinker_action']));
		// }
		// if(isset($posted['rcp_user_social_outing_food'])){
			// update_user_meta($user_id, 'rcp_user_social_outing_food' , sanitize_text_field($posted['rcp_user_social_outing_food']));
		// }
		// if(isset($posted['rcp_user_bought_meals_per_week'])){
			// update_user_meta($user_id, 'rcp_user_bought_meals_per_week' , sanitize_text_field($posted['rcp_user_bought_meals_per_week']));
		// }
		// if(isset($posted['rcp_user_cooked_dinners_per_week'])){
			// update_user_meta($user_id, 'rcp_user_cooked_dinners_per_week' , sanitize_text_field($posted['rcp_user_cooked_dinners_per_week']));
		// }
		// if(isset($posted['rcp_user_who_cooks'])){
			// update_user_meta($user_id, 'rcp_user_who_cooks' , sanitize_text_field($posted['rcp_user_who_cooks']));
		// }
		// if(isset($posted['rcp_user_who_shops'])){
			// update_user_meta($user_id, 'rcp_user_who_shops' , sanitize_text_field($posted['rcp_user_who_shops']));
		// }
		// if(isset($posted['rcp_user_social_extra'])){
			// update_user_meta($user_id, 'rcp_user_social_extra' , sanitize_text_field($posted['rcp_user_social_extra']));
		// }
		// if(isset($posted['rcp_user_disliked_foods'])){
			// update_user_meta($user_id, 'rcp_user_disliked_foods' , sanitize_text_field($posted['rcp_user_disliked_foods']));
		// }
		// if(isset($posted['rcp_user_liked_foods'])){
			// update_user_meta($user_id, 'rcp_user_liked_foods' , sanitize_text_field($posted['rcp_user_liked_foods']));
		// }
		// if(isset($posted['rcp_user_days_meal'])){
			// update_user_meta($user_id, 'rcp_user_days_meal' , sanitize_text_field($posted['rcp_user_days_meal']));
		// }
		// if(isset($posted['rcp_user_days_snacks'])){
			// update_user_meta($user_id, 'rcp_user_days_snacks' , sanitize_text_field($posted['rcp_user_days_snacks']));
		// }
		// if(isset($posted['rcp_user_ultimate_goal'])){
			// update_user_meta($user_id, 'rcp_user_ultimate_goal' , sanitize_text_field($posted['rcp_user_ultimate_goal']));
		// }
		// if(isset($posted['rcp_user_short_term_goal'])){
			// update_user_meta($user_id, 'rcp_user_short_term_goal' , sanitize_text_field($posted['rcp_user_short_term_goal']));
		// }
		// if(isset($posted['rcp_user_hurdles'])){
			// update_user_meta($user_id, 'rcp_user_hurdles' , sanitize_text_field($posted['rcp_user_hurdles']));
		// }
		// if(isset($posted['rcp_user_future_hurdles'])){
			// update_user_meta($user_id, 'rcp_user_future_hurdles' , sanitize_text_field($posted['rcp_user_future_hurdles']));
		// }
		// if(isset($posted['rcp_user_excited'])){
			// update_user_meta($user_id, 'rcp_user_excited' , sanitize_text_field($posted['rcp_user_excited']));
		// }
		
		
		
		
	}

	//TODO: REMOVE NOT TAKING MORE DATA ON SIGNUP
	//for existing users, update their metadata on profile save (front end)
	public function update_user_extra_meta_fields($user_id){
			

		// if(isset($_POST['rcp_user_lightest_weight'])){
			// update_user_meta($user_id, 'rcp_user_lightest_weight' , sanitize_text_field($_POST['rcp_user_lightest_weight']));
		// }
		// if(isset($_POST['rcp_user_heaviest_weight'])){
			// update_user_meta($user_id, 'rcp_user_heaviest_weight' , sanitize_text_field($_POST['rcp_user_heaviest_weight']));
		// }
		// if(isset($_POST['rcp_user_height'])){
			// update_user_meta($user_id, 'rcp_user_height' , sanitize_text_field($_POST['rcp_user_height']));
		// }
		// if(isset($_POST['rcp_user_days_gym'])){
			// update_user_meta($user_id, 'rcp_user_days_gym' , sanitize_text_field($_POST['rcp_user_days_gym']));
		// }
		// if(isset($_POST['rcp_user_member_of_gym'])){
			// update_user_meta($user_id, 'rcp_user_member_of_gym' , sanitize_text_field($_POST['rcp_user_member_of_gym']));
		// }
// 
		// //handeled checkboxes
		// if(isset($_POST['rcp_user_exercise_preference'])){
			// if(is_array($_POST['rcp_user_exercise_preference'])){
				// update_user_meta($user_id, 'rcp_user_exercise_preference' , json_encode($_POST['rcp_user_exercise_preference']));
			// }else{
				// update_user_meta($user_id, 'rcp_user_exercise_preference' , sanitize_text_field($_POST['rcp_user_exercise_preference']));
			// }	
		// }else{
			// update_user_meta($user_id, 'rcp_user_exercise_preference' , ''); 
		// }
// 		
		// if(isset($_POST['rcp_user_exercise_time'])){
			// update_user_meta($user_id, 'rcp_user_exercise_time' , sanitize_text_field($_POST['rcp_user_exercise_time']));
		// }
// 		
		// if(isset($_POST['rcp_user_exercise_extra'])){
			// update_user_meta($user_id, 'rcp_user_exercise_extra' , sanitize_text_field($_POST['rcp_user_exercise_extra']));
		// }
		// if(isset($_POST['rcp_user_medical_history'])){
			// update_user_meta($user_id, 'rcp_user_medical_history' , sanitize_text_field($_POST['rcp_user_medical_history']));
		// }
		// if(isset($_POST['rcp_user_occupation'])){
			// update_user_meta($user_id, 'rcp_user_occupation' , sanitize_text_field($_POST['rcp_user_occupation']));
		// }
		// if(isset($_POST['rcp_user_work_hours'])){
			// update_user_meta($user_id, 'rcp_user_work_hours' , sanitize_text_field($_POST['rcp_user_work_hours']));
		// }
		// if(isset($_POST['rcp_user_main_income_earner'])){
			// update_user_meta($user_id, 'rcp_user_main_income_earner' , sanitize_text_field($_POST['rcp_user_main_income_earner']));
		// }
		// if(isset($_POST['rcp_user_family_information'])){
			// update_user_meta($user_id, 'rcp_user_family_information' , sanitize_text_field($_POST['rcp_user_family_information']));
		// }
		// if(isset($_POST['rcp_user_smoker'])){
			// update_user_meta($user_id, 'rcp_user_smoker' , sanitize_text_field($_POST['rcp_user_smoker']));
		// }
		// if(isset($_POST['rcp_user_smoker_amount'])){
			// update_user_meta($user_id, 'rcp_user_smoker_amount' , sanitize_text_field($_POST['rcp_user_smoker_amount']));
		// }
		// if(isset($_POST['rcp_user_drinker'])){
			// update_user_meta($user_id, 'rcp_user_drinker' , sanitize_text_field($_POST['rcp_user_drinker']));
		// }
		// if(isset($_POST['rcp_user_drinker_amount'])){
			// update_user_meta($user_id, 'rcp_user_drinker_amount' , sanitize_text_field($_POST['rcp_user_drinker_amount']));
		// }
		// if(isset($_POST['rcp_user_drinker_action'])){
			// update_user_meta($user_id, 'rcp_user_drinker_action' , sanitize_text_field($_POST['rcp_user_drinker_action']));
		// }
		// if(isset($_POST['rcp_user_social_outing_food'])){
			// update_user_meta($user_id, 'rcp_user_social_outing_food' , sanitize_text_field($_POST['rcp_user_social_outing_food']));
		// }
		// if(isset($_POST['rcp_user_bought_meals_per_week'])){
			// update_user_meta($user_id, 'rcp_user_bought_meals_per_week' , sanitize_text_field($_POST['rcp_user_bought_meals_per_week']));
		// }
		// if(isset($_POST['rcp_user_cooked_dinners_per_week'])){
			// update_user_meta($user_id, 'rcp_user_cooked_dinners_per_week' , sanitize_text_field($_POST['rcp_user_cooked_dinners_per_week']));
		// }
		// if(isset($_POST['rcp_user_who_cooks'])){
			// update_user_meta($user_id, 'rcp_user_who_cooks' , sanitize_text_field($_POST['rcp_user_who_cooks']));
		// }
		// if(isset($_POST['rcp_user_who_shops'])){
			// update_user_meta($user_id, 'rcp_user_who_shops' , sanitize_text_field($_POST['rcp_user_who_shops']));
		// }
		// if(isset($_POST['rcp_user_social_extra'])){
			// update_user_meta($user_id, 'rcp_user_social_extra' , sanitize_text_field($_POST['rcp_user_social_extra']));
		// }
		// if(isset($_POST['rcp_user_disliked_foods'])){
			// update_user_meta($user_id, 'rcp_user_disliked_foods' , sanitize_text_field($_POST['rcp_user_disliked_foods']));
		// }
		// if(isset($_POST['rcp_user_liked_foods'])){
			// update_user_meta($user_id, 'rcp_user_liked_foods' , sanitize_text_field($_POST['rcp_user_liked_foods']));
		// }
		// if(isset($_POST['rcp_user_days_meal'])){
			// update_user_meta($user_id, 'rcp_user_days_meal' , sanitize_text_field($_POST['rcp_user_days_meal']));
		// }
		// if(isset($_POST['rcp_user_days_snacks'])){
			// update_user_meta($user_id, 'rcp_user_days_snacks' , sanitize_text_field($_POST['rcp_user_days_snacks']));
		// }
		// if(isset($_POST['rcp_user_ultimate_goal'])){
			// update_user_meta($user_id, 'rcp_user_ultimate_goal' , sanitize_text_field($_POST['rcp_user_ultimate_goal']));
		// }
		// if(isset($_POST['rcp_user_short_term_goal'])){
			// update_user_meta($user_id, 'rcp_user_short_term_goal' , sanitize_text_field($_POST['rcp_user_short_term_goal']));
		// }
		// if(isset($_POST['rcp_user_hurdles'])){
			// update_user_meta($user_id, 'rcp_user_hurdles' , sanitize_text_field($_POST['rcp_user_hurdles']));
		// }
		// if(isset($_POST['rcp_user_future_hurdles'])){
			// update_user_meta($user_id, 'rcp_user_future_hurdles' , sanitize_text_field($_POST['rcp_user_future_hurdles']));
		// }
		// if(isset($_POST['rcp_user_excited'])){
			// update_user_meta($user_id, 'rcp_user_excited' , sanitize_text_field($_POST['rcp_user_excited']));
		// }
	
	}


	//TODO: COme back to if possible
	//gets the HTML markup for the extra meta fields (to be used on the back-end WP admin)
	public function display_user_extra_meta_fields_on_admin($user_id = ''){
		
		$rcp_user_lightest_weight = get_user_meta($user_id, 'user_id', true);
			
		$html = '';
		
		$html .= '<tr valign="top">';
		$html .=	'<th scope="row" valign="top">';
		$html .=		'<label for="rcp_profession">Lightest you have been post 18 years old (in KG)</label>';
		$html .=	'</th>';
		$html .=	'<td>';
		$html .=		'<input name="rcp_user_lightest_weight" id="rcp_user_lightest_weight" type="number" value="' . $rcp_user_lightest_weight . '" min="30" max="250" step="1" size="3"/>';
		$html .=	'</td>';
		$html .= '</tr>';
		

		echo $html;
	}
	
	
	//TODO: Confirm if we need this as no fields required
	//check submitted fields to ensure they're valid (both on new registration and update)
	public function validate_new_rcp_fields($posted){

		$user_subscription_id = rcp_get_subscription_id(); 
		
		
	}
	
	//displays the extra meta fields for the user (both registration page + user account page on front end)
	public static function display_user_extra_meta_fields_frontend(){
		
		// $instance = self::getInstance();
// 		
		// $html = '';
		// $user_id = get_current_user_id();
// 		
// 		
		// //FILE UPLOADS
		// $html .= '<p class="form-field image-upload">';
			// $html .= '<label for="rcp_user_profile_image">Chose your profile image</label>';
			// $html .= '<input type="file" name="rcp_user_profile_image" id="rcp_user_profile_image">';
		// $html .= '</p>';
// 		
		// //Check if the user has an assigned practitioner already
		// if($user_id){
			// $user_has_practitioner = $instance->messaging_system->user_has_assigned_practitioner($user_id); 
			// if($user_has_practitioner == true){
				// $practitioner_id = $instance->messaging_system->get_user_assigned_practitioner($user_id);
// 				
				// $html .= '<h3>Your Practitioner</h3>';
				// $html .= '<div class="el-notice el-notice-warning">';
					// $html .= 'You already have a practitioner assigned to you. Practitioner #' . $practitioner_id . ' has been assigned. Click here to view them';
				// $html .= '</div>';
			// }
		// }
// 
// 		
		// //GENERAL HEALTH INFORMATION
		// $html .= '<h3>General Health Information</h3>';	
// 
		// //lightest weight
		// $html .= '<p class="form-field">';
// 			
			// $rcp_user_lightest_weight = ($user_id) ? get_user_meta($user_id, 'rcp_user_lightest_weight', true) : ''; 
			// $html .= '<label for="rcp_user_lightest_weight">Lightest you have been post 18 years old (in KG)</label>';
			// $html .= '<input name="rcp_user_lightest_weight" id="rcp_user_lightest_weight" type="number" value="' . $rcp_user_lightest_weight .'" min="30" max="250" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //heaviest weight
		// $html .= '<p class="form-field">';
			// $rcp_user_heaviest_weight = ($user_id) ? get_user_meta($user_id, 'rcp_user_heaviest_weight', true) : ''; 
			// $html .= '<label for="rcp_user_heaviest_weight">Heaviest you have been post 18 years old (in KG)</label>';
			// $html .= '<input name="rcp_user_heaviest_weight" id="rcp_user_heaviest_weight" type="number" value="' . $rcp_user_heaviest_weight .'" min="40" max="250" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //height
		// $html .= '<p class="form-field" >';
			// $rcp_user_height = ($user_id) ? get_user_meta($user_id, 'rcp_user_height', true) : ''; 
			// $html .= '<label for="rcp_user_height">Current Height (cm)</label>';
			// $html .= '<input name="rcp_user_height" id="rcp_user_height" type="number" value="' . $rcp_user_height .'" min="68" max="250" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //days gym
		// $html .= '<p class="form-field">';
			// $rcp_user_days_gym = ($user_id) ? get_user_meta($user_id, 'rcp_user_days_gym', true) : '1'; 
// 			
			// $html .= '<label for="rcp_user_days_gym">How many days a week do you exercise?</label>';
			// $html .= '<select name="rcp_user_days_gym" id="rcp_user_days_gym" value="' . $rcp_user_days_gym . '">';
				// $html .= '<option value="1" ' . ($rcp_user_days_gym == '1' ? 'selected' : '') . '>1 Day</option>';
				// $html .= '<option value="2" ' . ($rcp_user_days_gym == '2' ? 'selected' : '') . '>2 Day</option>';
				// $html .= '<option value="3" ' . ($rcp_user_days_gym == '3' ? 'selected' : '') . '>3 Day</option>';
				// $html .= '<option value="4" ' . ($rcp_user_days_gym == '4' ? 'selected' : '') . '>4 Day</option>';
				// $html .= '<option value="5" ' . ($rcp_user_days_gym == '5' ? 'selected' : '') . '>5 Day</option>';
				// $html .= '<option value="6" ' . ($rcp_user_days_gym == '6' ? 'selected' : '') . '>6 Day</option>';
				// $html .= '<option value="7" ' . ($rcp_user_days_gym == '7' ? 'selected' : '') . '>7 Day</option>';
			// $html .= '</select>';
		// $html .= '</p>';
// 		
// 		
		// //gym membership
		// $html .= '<p class="form-field inline">';
			// $rcp_user_member_of_gym = ($user_id) ? get_user_meta($user_id, 'rcp_user_member_of_gym', true) : 'no'; 
			// $html .= '<span>Do you have a gym membership?</span>';
			// //no
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_member_of_gym" id="rcp_user_member_of_gym_no" type="radio" value="no" ' . ($rcp_user_member_of_gym == 'no' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_member_of_gym_no">No</label>';
			// $html .= '</span>';
			// //yes
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_member_of_gym" id="rcp_user_member_of_gym_yes" type="radio" value="yes" ' . ($rcp_user_member_of_gym == 'yes' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_member_of_gym_yes">Yes</label>';
			// $html .= '</span>';
		// $html .= '</p>';
// 		
		// //exercise preference (checkbox)
		// $html .= '<p class="form-field inline">';
// 			
			// $rcp_user_exercise_preference = ($user_id) ? get_user_meta($user_id, 'rcp_user_exercise_preference', true) : ''; 
			// //json values
			// if(!empty($rcp_user_exercise_preference)){
				// $rcp_user_exercise_preference = json_decode($rcp_user_exercise_preference);
				// $rcp_user_exercise_preference = (array_flip($rcp_user_exercise_preference));
			// }
			// $html .= '<span>What type of exercise do you prefer?</span>';
			// //in a gym
			// $html .= '<span class="wrap">';	
				// $html .= '<label for="rcp_user_exercise_preference_in_a_gym">In a Gym</label>';
				// $html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_in_a_gym" type="checkbox" value="in_a_gym" ' . ( isset($rcp_user_exercise_preference['in_a_gym']) ? 'checked' : '') .'/>';
			// $html .= '</span>';
			// //outside
			// $html .= '<span class="wrap">';	
				// $html .= '<label for="rcp_user_exercise_preference_outside">Outside</label>';
				// $html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_outside" type="checkbox" value="outside" ' . ( isset($rcp_user_exercise_preference['outside']) ? 'checked' : '') .'/>';
			// $html .= '</span>';
			// //at home
			// $html .= '<span class="wrap">';	
				// $html .= '<label for="rcp_user_exercise_preference_at_home">At Home</label>';
				// $html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_at_home" type="checkbox" value="at_home" ' . ( isset($rcp_user_exercise_preference['at_home']) ? 'checked' : '') .'/>';
			// $html .= '</span>';
			// //other
			// $html .= '<span class="wrap">';	
				// $html .= '<label for="rcp_user_exercise_preference_other">Other</label>';
				// $html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_other" type="checkbox" value="other" ' . ( isset($rcp_user_exercise_preference['other']) ? 'checked' : '') .'/>';	
			// $html .= '</span>';
		// $html .= '</p>';
// 		
		// //how long exercise section
		// $html .= '<p class="form-field">';
			// $rcp_user_exercise_time = ($user_id) ? get_user_meta($user_id, 'rcp_user_exercise_time', true) : ''; 
			// $html .= '<label for="rcp_user_exercise_time">Average Exercise Time Per Day(Minutes)</label>';
			// $html .= '<input name="rcp_user_exercise_time" id="rcp_user_exercise_time" type="number" value="' . $rcp_user_exercise_time .'" min="0" max="600" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //additional exercise info
		// $html .= '<p class="form-field">';
			// $rcp_user_exercise_extra = ($user_id) ? get_user_meta($user_id, 'rcp_user_exercise_extra', true) : ''; 
			// $html .= '<label for="rcp_user_exercise_extra ">Any extra exercise info (e.g intensity, location, type)</label>';
			// $html .= '<textarea name="rcp_user_exercise_extra" id="rcp_user_exercise_extra" maxlength="400">' . $rcp_user_exercise_extra . '</textarea>';
		// $html .= '</p>';
// 		
// 		
		// //MEDICAL INFORMATIOn
		// $html .= '<h3>Medical Information</h3>';	
// 		
		// //medical history
		// $html .= '<p class="form-field">';
			// $rcp_user_medical_history = ($user_id) ? get_user_meta($user_id, 'rcp_user_medical_history', true) : ''; 
			// $html .= '<label for="rcp_user_medical_history ">Do you have any other information medically relevant to your Dietition (even if you think it is not significant)</label>';
			// $html .= '<textarea name="rcp_user_medical_history" id="rcp_user_medical_history" maxlength="400">' . $rcp_user_medical_history . '</textarea>';
		// $html .= '</p>';
// 		
// 		
		// //SOCIAL HISTORY
		// $html .= '<h3>Social History</h3>';
// 		
		// //occupation
		// $html .= '<p class="form-field">';
			// $rcp_user_occupation = ($user_id) ? get_user_meta($user_id, 'rcp_user_occupation', true) : ''; 
			// $html .= '<label for="rcp_user_occupation">What is your occupation (eg, plumber, full time student, full time mum/dad etc)</label>';
			// $html .= '<input name="rcp_user_occupation" id="rcp_user_occupation" type="text" value="' . $rcp_user_occupation .'" maxlength="150"/>';
		// $html .= '</p>';
// 		
		// //total work hours
		// $html .= '<p class="form-field">';
			// $rcp_user_work_hours = ($user_id) ? get_user_meta($user_id, 'rcp_user_work_hours', true) : ''; 
			// $html .= '<label for="rcp_user_work_hours">How many hours a week do you work on average</label>';
			// $html .= '<input name="rcp_user_work_hours" id="rcp_user_work_hours" type="number" value="' . $rcp_user_work_hours .'" min="1" max="100" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //main income earner
		// $html .= '<p class="form-field inline">';
			// $rcp_user_main_income_earner = $user_id ? get_user_meta($user_id, 'rcp_user_main_income_earner', true) : 'no'; 
			// $html .= '<span>Are you the main income earner for your household?</span>';
			// //no
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_main_income_earner" id="rcp_user_main_income_earner_no" type="radio" value="no" ' . ($rcp_user_main_income_earner == 'no' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_main_income_earner_no">No</label>';
			// $html .= '</span>';
			// //yes
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_main_income_earner" id="rcp_user_main_income_earner_yes" type="radio" value="yes" ' . ($rcp_user_main_income_earner == 'yes' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_main_income_earner_yes">Yes</label>';
			// $html .= '</span>';
// 			
		// $html .= '</p>';
// 		
		// //family kids
		// $html .= '<p class="form-field">';
			// $rcp_user_family_information = ($user_id) ? get_user_meta($user_id, 'rcp_user_family_information', true) : ''; 
			// $html .= '<label for="rcp_user_family_information">If you have children, please enter how many and their ages</label>';
			// $html .= '<textarea name="rcp_user_family_information" id="rcp_user_exercise_extra" maxlength="400">' . $rcp_user_family_information . '</textarea>';
		// $html .= '</p>';
// 		
		// //smoking
		// $html .= '<p class="form-field inline">';
			// $rcp_user_smoker = ($user_id) ? get_user_meta($user_id, 'rcp_user_smoker', true) : 'no'; 
			// $html .= '<span>Are you a smoker?</span>';
			// //no
			// $html .= '<span class="wrap">';		
				// $html .= '<input name="rcp_user_smoker" id="rcp_user_smoker_no" type="radio" value="no" ' . ($rcp_user_smoker == 'no' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_smoker_no">No</label>';
			// $html .= '</span>';	
			// //yes
			// $html .= '<span class="wrap">';		
				// $html .= '<input name="rcp_user_smoker" id="rcp_user_smoker_yes" type="radio" value="yes" ' . ($rcp_user_smoker == 'yes' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_smoker_yes">Yes</label>';
			// $html .= '</span>';	
		// $html .= '</p>';
// 		
		// //smoker amount
		// $html .= '<p class="form-field">';
			// $rcp_user_smoker_amount = ($user_id) ? get_user_meta($user_id, 'rcp_user_smoker_amount', true) : 'no'; 
			// $html .= '<label for="rcp_user_smoker_amount">If you smoke, how many would you smoke per week?</label>';
			// $html .= '<input name="rcp_user_smoker_amount" id="rcp_user_smoker_amount" type="number" value="' . $rcp_user_exercise_time .'" min="1" max="300" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //drinker
		// $html .= '<p class="form-field inline">';
			// $rcp_user_smoker = ($user_id) ? get_user_meta($user_id, 'rcp_user_smoker', true) : 'no'; 
			// $html .= '<span>Do you drink?</span>';
			// //no
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_drinker" id="rcp_user_drinker_no" type="radio" value="no" ' . ($rcp_user_smoker == 'no' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_drinker_no">No</label>';
			// $html .= '</span>';
			// //yes
			// $html .= '<span class="wrap">';			
				// $html .= '<input name="rcp_user_drinker" id="rcp_user_drinker_yes" type="radio" value="yes" ' . ($rcp_user_smoker == 'yes' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_drinker_yes">Yes</label>';
			// $html .= '</span>';
		// $html .= '</p>';
// 		
		// //drinker amount
		// $html .= '<p class="form-field">';
			// $rcp_user_drinker_amount = ($user_id) ? get_user_meta($user_id, 'rcp_user_drinker_amount', true) : 'no'; 
			// $html .= '<label for="rcp_user_drinker_amount">If you drink, how many standard drinks would you have per week?</label>';
			// $html .= '<input name="rcp_user_drinker_amount" id="rcp_user_drinker_amount" type="number" value="' . $rcp_user_drinker_amount .'" min="1" max="300" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //drinker action
		// $html .= '<p class="form-field inline">';
			// $rcp_user_smoker = ($user_id) ? get_user_meta($user_id, 'rcp_user_drinker_action', true) : 'nothing'; 
// 			
			// $html .= '<span>Which of the following would you be willing to do?</span>';
			// //nothing
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_drinker_action" id="rcp_user_drinker_action_nothing" type="radio" value="nothing" ' . ($rcp_user_smoker == 'nothing' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_drinker_action_nothing">No Change</label>';
			// $html .= '</span>';
			// //stop drinking
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_drinker_action" id="rcp_user_drinker_action_stop_drinking" type="radio" value="stop_drinking" ' . ($rcp_user_smoker == 'stop_drinking' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_drinker_action_stop_drinking">Stop Drinking</label>';
			// $html .= '</span>';
			// //reduce drinking
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_drinker_action" id="rcp_user_drinker_action_reduce_drinking" type="radio" value="reduce_drinking" ' . ($rcp_user_smoker == 'reduce_drinking' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_drinker_action_reduce_drinking">Reduce Drinking</label>';
			// $html .= '</span>';
// 			
		// $html .= '</p>';
// 		
		// //social outings good
		// $html .= '<p class="form-field inline">';
			// $rcp_user_social_outing_food = ($user_id) ? get_user_meta($user_id, 'rcp_user_social_outing_food', true) : 'no'; 
			// $html .= '<span>Do your social outings generall include food?</span>';
			// //no
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_social_outing_food" id="rcp_user_social_outing_food_no" type="radio" value="no" ' . ($rcp_user_social_outing_food == 'no' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_social_outing_food_no">No</label>';
			// $html .= '</span>';
			// //yes
			// $html .= '<span class="wrap">';	
				// $html .= '<input name="rcp_user_social_outing_food" id="rcp_user_social_outing_food_yes" type="radio" value="yes" ' . ($rcp_user_social_outing_food == 'yes' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_social_outing_food_yes">Yes</label>';
			// $html .= '</span>';
		// $html .= '</p>';
// 		
		// //Bought meals per week
		// $html .= '<p class="form-field">';
			// $rcp_user_bought_meals_per_week = ($user_id) ? get_user_meta($user_id, 'rcp_user_bought_meals_per_week', true) : ''; 		
			// $html .= '<label for="rcp_user_bought_meals_per_week">How many meals per week are bought on the go or from a shop ready made? (this is any time of the day)</label>';
			// $html .= '<input name="rcp_user_bought_meals_per_week" id="rcp_user_bought_meals_per_week" type="number" value="' . $rcp_user_bought_meals_per_week .'" min="1" max="60" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //dinners at home
		// $html .= '<p class="form-field">';
			// $rcp_user_cooked_dinners_per_week = ($user_id) ? get_user_meta($user_id, 'rcp_user_cooked_dinners_per_week', true) : ''; 		
			// $html .= '<label for="rcp_user_bought_meals_per_week">How many dinners per week are home cooked?</label>';
			// $html .= '<input name="rcp_user_cooked_dinners_per_week" id="rcp_user_cooked_dinners_per_week" type="number" value="' . $rcp_user_cooked_dinners_per_week .'" min="1" max="60" step="1" size="3"/>';
		// $html .= '</p>';
// 		
		// //who cooks at home
		// $html .= '<p class="form-field">';
			// $rcp_user_who_cooks  = ($user_id) ? get_user_meta($user_id, 'rcp_user_who_cooks', true) : ''; 
// 		
			// $html .= '<label for="rcp_user_who_cooks">Who does the cooking at home?(put down your relation to them, eg, wife, or myself etc)</label>';
			// $html .= '<input name="rcp_user_who_cooks" id="rcp_user_who_cooks" type="text" value="' . $rcp_user_who_cooks .'" maxlength="150"/>';
		// $html .= '</p>';
// 		
		// //who shops at home
		// $html .= '<p class="form-field">';
			// $rcp_user_who_shops  = ($user_id) ? get_user_meta($user_id, 'rcp_user_who_shops', true) : ''; 
			// $html .= '<label for="rcp_user_who_shops">Who does the shopping usually?</label>';
			// $html .= '<input name="rcp_user_who_shops" id="rcp_user_who_shops" type="text" value="' . $rcp_user_who_shops .'" maxlength="150"/>';
		// $html .= '</p>';
// 		
		// //social extra
		// $html .= '<p class="form-field">';
			// $rcp_user_social_extra  = ($user_id) ? get_user_meta($user_id, 'rcp_user_social_extra', true) : ''; 
			// $html .= '<label for="rcp_user_social_extra">Any other information you would like to note about your social history?</label>';
			// $html .= '<textarea name="rcp_user_social_extra" id="rcp_user_social_extra" maxlength="400">' . $rcp_user_social_extra . '</textarea>';
		// $html .= '</p>';
// 		
// 		
// 		
		// //DIET
		// $html .= '<h3>Diet</h3>';
// 		
		// //disliked foods
		// $html .= '<p class="form-field">';
			// $rcp_user_disliked_foods  = ($user_id) ? get_user_meta($user_id, 'rcp_user_disliked_foods', true) : ''; 
			// $html .= '<label for="rcp_user_disliked_foods">What foods do you hate/will not eat at all, even if we suggested them?</label>';
			// $html .= '<textarea name="rcp_user_disliked_foods" id="rcp_user_social_extra" maxlength="400">' . $rcp_user_disliked_foods . '</textarea>';
		// $html .= '</p>';
// 		
		// //liked foods
		// $html .= '<p class="form-field">';
			// $rcp_user_liked_foods  = ($user_id) ? get_user_meta($user_id, 'rcp_user_liked_foods', true) : ''; 
			// $html .= '<label for="rcp_user_liked_foods">What foods do you love and hope to have included in your plan?(list any but please be realistic)</label>';
			// $html .= '<textarea name="rcp_user_liked_foods" id="rcp_user_liked_foods" maxlength="400">' . $rcp_user_liked_foods . '</textarea>';
		// $html .= '</p>';
// 		
		// //meal eating
		// $html .= '<p class="form-field">';
			// $rcp_user_days_meal = ($user_id) ? get_user_meta($user_id, 'rcp_user_days_meal', true) : '1'; 
// 			
			// $html .= '<label for="rcp_user_days_gym">How many times a day can you realistically eat a meal?</label>';
			// $html .= '<select name="rcp_user_days_meal" id="rcp_user_days_meal" value="' . $rcp_user_days_meal . '">';
				// $html .= '<option value="1" ' . ($rcp_user_days_meal == '1' ? 'selected' : '') . '>1 Times</option>';
				// $html .= '<option value="2" ' . ($rcp_user_days_meal == '2' ? 'selected' : '') . '>2 Times</option>';
				// $html .= '<option value="3" ' . ($rcp_user_days_meal == '3' ? 'selected' : '') . '>3 Times</option>';
				// $html .= '<option value="4" ' . ($rcp_user_days_meal == '4' ? 'selected' : '') . '>4 Times</option>';
				// $html .= '<option value="5" ' . ($rcp_user_days_meal == '5' ? 'selected' : '') . '>5 Times</option>';
			// $html .= '</select>';
		// $html .= '</p>';
// 		
		// //snack eating
		// $html .= '<p class="form-field">';
			// $rcp_user_days_snacks = ($user_id) ? get_user_meta($user_id, 'rcp_user_days_snacks', true) : '1'; 
// 			
			// $html .= '<label for="rcp_user_days_gym">How many times a day can you eat a "snack"?</label>';
			// $html .= '<select name="rcp_user_days_snacks" id="rcp_user_days_meal" value="' . $rcp_user_days_meal . '">';
				// $html .= '<option value="1" ' . ($rcp_user_days_snacks == '1' ? 'selected' : '') . '>1 Times</option>';
				// $html .= '<option value="2" ' . ($rcp_user_days_snacks == '2' ? 'selected' : '') . '>2 Times</option>';
				// $html .= '<option value="3" ' . ($rcp_user_days_snacks == '3' ? 'selected' : '') . '>3 Times</option>';
				// $html .= '<option value="4" ' . ($rcp_user_days_snacks == '4' ? 'selected' : '') . '>4 Times</option>';
				// $html .= '<option value="5" ' . ($rcp_user_days_snacks == '5' ? 'selected' : '') . '>5 Times</option>';
			// $html .= '</select>';
		// $html .= '</p>';
// 		
// 		
		// //ACHIEVE
// 		
		// //ultimate goal
		// $html .= '<p class="form-field">';
			// $rcp_user_ultimate_goal = ($user_id) ? get_user_meta($user_id, 'rcp_user_ultimate_goal', true) : ''; 
			// $html .= '<label for="rcp_user_ultimate_goal">Ultimate Goal (eg, I want to lose 60kg)</label>';
			// $html .= '<textarea name="rcp_user_ultimate_goal" id="rcp_user_ultimate_goal" maxlength="400">' . $rcp_user_ultimate_goal . '</textarea>';
		// $html .= '</p>';
// 		
		// //short term goal
		// $html .= '<p class="form-field">';
			// $rcp_user_short_term_goal = ($user_id) ? get_user_meta($user_id, 'rcp_user_short_term_goal', true) : ''; 
			// $html .= '<label for="rcp_user_short_term_goal">Short Term Goal (eg, I want to lose 2kg)</label>';
			// $html .= '<textarea name="rcp_user_short_term_goal" id="rcp_user_short_term_goal" maxlength="400">' . $rcp_user_short_term_goal . '</textarea>';
		// $html .= '</p>';
// 		
		// //biggest hurdles
		// $html .= '<p class="form-field">';
			// $rcp_user_hurdles = ($user_id) ? get_user_meta($user_id, 'rcp_user_hurdles', true) : ''; 
			// $html .= '<label for="rcp_user_hurdles">Biggest hurdles (in the past)</label>';
			// $html .= '<textarea name="rcp_user_hurdles" id="rcp_user_hurdles" maxlength="400">' . $rcp_user_hurdles . '</textarea>';
		// $html .= '</p>';
// 		
		// //future hurdles 
		// $html .= '<p class="form-field">';
			// $rcp_user_future_hurdles = ($user_id) ? get_user_meta($user_id, 'rcp_user_future_hurdles', true) : ''; 
// 			
			// $html .= '<label for="rcp_user_hurdles">What you think your major hurdles will be during this plan if any?</label>';
			// $html .= '<textarea name="rcp_user_future_hurdles" id="rcp_user_future_hurdles" maxlength="400">' . $rcp_user_future_hurdles . '</textarea>';
		// $html .= '</p>';
// 		
		// //excited!
		// $html .= '<p class="form-field inline">';
			// $rcp_user_excited  = ($user_id) ? get_user_meta($user_id, 'rcp_user_excited', true) : ''; 
// 			
			// $html .= '<span>Are you excited to start your Healthy Imaging journey towards a Healthier Image of yourself?</span>';
			// //no
			// $html .= '<span class="wrap">';
				// $html .= '<input name="rcp_user_excited" id="rcp_user_excited_no" type="radio" value="no" ' . ($rcp_user_excited == 'no' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_excited_no">No</label>';
			// $html .= '</span>';
			// //yes
			// $html .= '<span class="wrap">';
				// $html .= '<input name="rcp_user_excited" id="rcp_user_excited_yes" type="radio" value="yes" ' . ($rcp_user_excited == 'yes' ? 'checked' : '') .'/>';
				// $html .= '<label for="rcp_user_excited_yes">Yes</label>';
			// $html .= '</span>';
		// $html .= '</p>';
// 
		// echo $html;
	}
	

	
	//sets / gets singleton
	public static function getInstance(){
		if(is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	
}
$elevate_nutrition_system = elevate_nutrition_system::getInstance();




?>
