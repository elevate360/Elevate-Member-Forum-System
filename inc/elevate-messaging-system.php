<?php
/*
 * Elevate Messaging 
 * Master class for functionality revolving around the messaging (forum)system
 * - Registers 'el_topics' and 'el_replies' content types to hold content
 * - Adds shortcodes for forum functionality output (topics listing, new topic form, reply form)
 */

 
 class elevate_messaging{
 	
	private static $instance = null;
	private $number_of_topics_free_member = 5; //Determines how many free topics can be posted
	
	//constructor
	public function __construct(){
		
		add_action('init', array($this, 'regsiter_topic_content_type'));
		add_action('init', array($this, 'register_reply_content_type'));
		add_action('init', array($this, 'register_topic_taxonomies'));
		add_action('init', array($this, 'register_shortcodes'));
		
		add_action('admin_menu', array($this, 'register_messaging_system_settings_menu')); //register admin settings menu
		add_action('admin_init', array($this, 'register_messaging_system_settings_sections')); //register settings and fields
		
		add_action('wp_enqueue_scripts', array($this, 'localize_script_for_js'), 15, 1); //outputs info about the user 
		add_action('template_redirect', array($this, 'check_user_access')); //check that the user can access elements
		
		
		
		
		//Ajax calls to post a new topic
		add_action('wp_ajax_post_topic', array($this, 'post_new_topic_ajax_call'));
		
		//Ajax call to get the table markup for a single topic (content to be added to the table)
		add_action('wp_ajax_get_topic_markup', array($this, 'get_single_topic_table_markup_ajax'));
		
		//AJAX call after posting a reply, check to see if we're now at the post new topic limit (and remove the topic form)
		add_action('wp_ajax_post_reply_check_limit', array($this, 'check_post_new_reply_limit_ajax')); 
		
		//Ajax call to post a new reply 
		add_action('wp_ajax_post_reply', array($this, 'post_new_reply_ajax_call'));
		
		//Ajax call to get the table markup for a single reply to a topic (after we add a new reply to a topic)
		add_action('wp_ajax_get_reply_markup', array($this, 'get_single_reply_table_markup_ajax'));
		
		//Ajax call to assign a user a practitioner (for paid members)
		add_action('wp_ajax_assign_practitioner', array($this, 'assign_practitioner_to_user_ajax'));
		
		//Ajax call to update a reply list (for a single topic). Fetches all replies in the DB to ensure the front-end UI is updated every X seconds
		add_action('wp_ajax_update_topic_with_replies', array($this, 'update_topic_with_replies_ajax'));
		
		add_action('template_redirect', array($this, 'save_profile_form'));

	}




	/**
	 * Ajax function used to assign a user to a practitioner
	 * 
	 * Users can be assigned to a practitioner as long as they are a paying member. This provides them with the ability to
	 * message the practitioner directly (and privately). Returns a success or error status to JS along with message / content 
	 */
	public function assign_practitioner_to_user_ajax(){
		
		$response = array();
		
		$user_id = isset($_POST['user_id']) ? sanitize_text_field($_POST['user_id']) : '';
		$practitioner_id = isset($_POST['practitioner_id']) ? sanitize_text_field($_POST['practitioner_id']) : '';
		
		if($user_id && $practitioner_id){
			
			//get user and practitioner to ensure they exist
			$user = get_user_by('ID', $user_id);
			$practitioner = get_user_by('ID', $practitioner_id);
			
			//check existance 
			if( ($user instanceof WP_User) && ($practitioner instanceof WP_User) ){
				
				$successfully_assigned = add_user_meta($user->ID, 'practitioner_id', $practitioner->ID);
				if($successfully_assigned){
					
					//create a pretty response to show the user 
					$content = '';
					$content .= '<div class="el-notice el-notice-success">';
						$content .= 'You have successfully been assigned this practitioner. You can send this person topics by navigating back to the dashboard';
					$content .= '</div>';
					
					$response['status'] = 'success';
					$response['content'] = $content;
					$response['message'] = 'Successfully assigned practition to user';
				}else{
					$response['status'] = 'error';
					$response['message'] = 'Couldnt assign practitioner to user, failed at update_user_meta';
				}
				
				
			}else{
				$response['status'] = 'error';
				$response['message'] = 'Practitioner and or user id supplied, but users dont exist';
			}
			
			
		}else{
			$response['status'] = 'error';
			$response['message'] = 'Either user id or practitioner id missing from arguments';
		}
		
		
		echo json_encode($response);
		wp_die();
	}



	//post a reply to a topic, triggered by ajax
	public function post_new_reply_ajax_call(){
	
		$response = array();
		
		$current_user_id = get_current_user_id();
		$topic_id = isset($_POST['reply_topic_id']) ? sanitize_text_field($_POST['reply_topic_id']) : ''; 
		$reply_content = isset($_POST['reply_content']) ? sanitize_text_field($_POST['reply_content']) : '';
		
		//ensure topic ID and reply content set
		if($topic_id && $reply_content){
				
			$topic = get_post($topic_id);
			
			//ensure topic is valid
			if($topic instanceof WP_Post){
							
				$reply_title = '';
				
				$current_reply_args = array(
					'meta_key'		=> 'topic_id',
					'meta_value'	=> $topic_id,
					'post_status'	=> 'publish',
					'posts_per_page'=> -1,
					'post_type'		=> 'el_replies',
					'orderby'		=> 'date',
					'order'			=> 'ASC'
				);
				
				
				
				
				$post = get_posts($current_reply_args);

				
				//echo '<pre>';
				//var_dump($current_reply_args);
				//var_dump($post);
				
				//echo '</pre>';
				
				
				//wp_die();
				
				$number_of_post = count($post);
				
				if($number_of_post == 0){
					$reply_title = 'Topic #' . $topic_id . ' Reply #1'; 
				}else{
					$reply_title = 'Topic #' . $topic_id . ' Reply #' . ($number_of_post+1); 
				}
				
				//attempt to create a new reply and relate it to the topic
				$post_args = array(
					'post_author'		=> $current_user_id,
					'post_title'		=> $reply_title, //unique ID for the title of post
					'post_content'		=> $reply_content,
					'post_status'		=> 'publish',
					'post_type'			=> 'el_replies'
				);
				
				$post_id = wp_insert_post($post_args, true);
				
				//check for successful addition
				if(is_int($post_id)){
					
					//add meta information so post is related to topic
					add_post_meta($post_id, 'topic_id', $topic_id);

					$response['status'] = 'success';
					$response['message'] = 'successfully added reply #' . $post_id . ' to topic #' . $topic_id;
					$response['reply_id'] = $post_id;
					
				}else{
					$response['status'] = 'error';
					$response['message'] = 'There was an issue inserting your reply';
				}
				
				
			}
			//not a valid id
			else{
				$response['status'] = 'error';
				$response['message'] = 'post couldnt be found, topic ID not correct';
			}	
		}
		//topic id or content not passed
		else{
			$response['status'] = 'error';
			$response['message'] = 'Missing topic id or reply content in ajax call';
		}
		
		echo json_encode($response);
		wp_die();
			
	}


	//ajax call to get the single markup for a topic reply. Call via ajax when we submit a reply.
	public function get_single_reply_table_markup_ajax(){
		
		$response = array();
		
		$reply_id = isset($_POST['reply_id']) ? sanitize_text_field($_POST['reply_id']) : '';
		
		if($reply_id){
			
			$reply = get_post($reply_id);
			if($reply instanceof WP_Post){
				
				//get markup for a single
				$markup = $this->get_single_reply_table_markup($reply->ID);
				$response['status'] = 'success';
				$response['message'] = 'successfully received reply markup';
				$response['content'] = $markup;
				
			}//not a valid id
			else{
				$response['status'] = 'error';
				$response['message'] = 'post couldnt be found, reply ID not correct';
			}
			
		}else{
			$response['status'] = 'error';
			$response['message'] = 'Incorrect or no reply ID set for function SORRY';
		}
		
		
		echo json_encode($response);
		
		wp_die();
		
	}

	/**
	 *Gets a the markup for a single reply in a topic chain
	 * 
	 * Dynamic depending on the user viewing the reply (user, practitioner etc)
	 */
	public static function get_single_reply_table_markup($reply_id){
			
		$instance = self::getInstance();
		$html = '';
		
		if($reply_id){
				
			$reply = get_post($reply_id);
			if($reply){
				
				$reply_id = $reply->ID;
				$reply_content = apply_filters('the_content', $reply->post_content);
				$reply_author_id = get_post_field('post_author', $reply_id);
				$reply_author_nickname = get_user_meta($reply_author_id, 'nickname', true);
				$reply_posted_date = get_the_time('d/m/Y - h:i A', $reply_id);
				
				//determine the current user viewing this reply
				$current_user_id = get_current_user_id();
				
				//get the author of the reply
				$reply_author_subscription_id = rcp_get_subscription_id($reply_author_id);
				
				$reply_author_type = '';
				if($reply_author_subscription_id == '1' || $reply_author_subscription_id == '2'){
					$reply_author_type = 'user';
				}
				if($reply_author_subscription_id == '3'){
					$reply_author_type = 'practitioner';
				}
				
				$reply_author_profile_image_id = get_user_meta($reply_author_id, 'rcp_user_profile_image', true);
				$reply_author_image = get_post($reply_author_profile_image_id);
				if($reply_author_image instanceof WP_Post){
					$profile_thumbnail_url = wp_get_attachment_image_src($reply_author_image->ID, 'thumbnail', false)[0];
				}
				
				//html output
				
				$classes = '';
				$classes .= ($reply_author_type == 'practitioner') ? 'practitioiner-reply ' : '';
				$classes .= ($reply_author_type == 'user') ? 'user-reply ' : '';
				$html .= '<div id="el-reply-' . $reply_id . '" class="el-reply ' . $classes . '" id="el-reply-' . $reply_id . '">';
					$html .= '<div class="reply-wrap cf">';
						$html .= '<div class="reply-meta">';
							//display profile image if set
							if(isset($profile_thumbnail_url)){
								$html .= '<div class="profile-image" style="background-image: url(' . $profile_thumbnail_url  . ');"></div>'; 	
							}
							$html .= '<div class="reply-author"><strong>Posted by:</strong> ' . $reply_author_nickname . ' (User #' . $reply_author_id .')</div>';
							$html .= '<div class="reply-date"><strong>Posted on: </strong>' . $reply_posted_date . '</div>';
						$html .= '</div>';
						$html .= '<div class="reply-content">' . $reply_content . '</div>';
							
						//is a practitiner reply, display link to profile
						if($reply_author_type == 'practitioner'){
							$html .= $instance->get_practitioner_profile_button($reply_author_id);
						}
						//is a user reply, show link to profile (maybe)
						else if($reply_author_type == 'user'){
							
							//only display if the current user is the owner of the comment
							if($current_user_id == $reply_author_id){
								$html .= $instance->get_user_profile_button($reply_author_id);
							}	
						}

					$html .= '</div>';
				$html .= '</div>';
				
			}
			
		}
		
		return $html;
		
	}
	
	//Post a new topic, triggered by ajax
	public function post_new_topic_ajax_call(){
			
		$response = array();
		$current_user_id = get_current_user_id();
	
		//user logged in
		if($current_user_id !== 0){
			
			$topic_title = isset($_POST['topic_title']) ? sanitize_text_field($_POST['topic_title']) : '';
			$topic_content = isset($_POST['topic_content']) ? sanitize_text_field($_POST['topic_content']) : '';
			$topic_category = isset($_POST['topic_category']) ? sanitize_text_field($_POST['topic_category']) : '';
			$topic_privacy = isset($_POST['topic_privacy'])	? sanitize_text_field($_POST['topic_privacy']) : '';
			$topic_assigned_practitioner = isset($_POST['topic_assigned_practitioner']) ? sanitize_text_field($_POST['topic_assigned_practitioner']) : '';
			
			//check for correct fields
			if(empty($topic_title) || empty($topic_content) || empty($topic_privacy)){
					
				$response['status'] = 'error';
				
				//empty title
				if(empty($topic_title)){
					$response['field_error'][] = array(
						'field_id'		=> 'topic_title',
						'field_message'	=> 'Topic title cant be empty'
					);
				}
				//empty content
				if(empty($topic_content)){
					$response['field_error'][] = array(
						'field_id'		=> 'topic_content',
						'field_message'	=> 'Topic content cant be empty'
					);
				}
				
				//check if we have terms and if any were successfully passed to us
				$term_args = array('hide_empty' => false);
				$terms_count = wp_count_terms('el_topic_category', $term_args);
				if(empty($topic_category)){
					//if category passed was empty but we had categories in the system
					if($terms_count != 0){
						$response['field_error'][] = array(
							'field_id'		=> 'topic_category',
							'field_message'	=> 'Category selector shouldnt be empty'
						);
					}
					
				}
				
				//empty privacy setting
				if(empty($topic_privacy)){
					$response['field_error'][] = array(
						'field_id'		=> 'topic_privacy',
						'field_message'	=> 'Topic privacy selector shouldnt be empty'
					);
				}
				
			}else{
				$response['status'] = 'success';
			}
			
			//attempt to create a new post based on content
			if($response['status'] == 'success'){
				
				$post_args = array(
					'post_author'		=> $current_user_id,
					'post_title'		=> $topic_title,
					'post_content'		=> $topic_content,
					'post_status'		=> 'publish',
					'post_type'			=> 'el_topics',
				);
					
				$post_id = wp_insert_post($post_args, true);
				
				//check for successful addition
				if(is_int($post_id)){
					
					//if we have a category selected, append that to our post
					if(!empty($topic_category)){
						wp_set_post_terms($post_id, $topic_category, 'el_topic_category', false);
					}
					
					//add metadata about topic privacy
					add_post_meta($post_id, 'topic_privacy', $topic_privacy);
					
					//if we have an assigned practitioner (private topic)
					if($topic_privacy == 'private'){
						if($topic_assigned_practitioner){
							add_post_meta($post_id, 'topic_assigned_practitioner', $topic_assigned_practitioner);
						}
					}

					$response['message'] = 'Your topic has been successfull created. User ID# ' . $current_user_id . ' Topic ID# ' . $post_id . ' privacy is: ' . $topic_privacy;
					$response['topic_id'] = $post_id;
					
				}else{
					$response['status'] = 'error';
					$response['message'] = 'A new topic couldnt be created';
				}
			}

		}else{
			$response['status'] = 'error';
			$response['message'] = 'User not logged in, cant post new topic.';
		}
		
		echo json_encode($response);
	
		wp_die();
		
	}
	
	//register shortcodes for use in various pages
	public function register_shortcodes(){
		add_shortcode('el_forum_topics_table', array($this, 'get_shortcode_output')); //diplays all public topics
		add_shortcode('el_forum_new_topic_form', array($this, 'get_shortcode_output')); //new topic form
		add_shortcode('el_forum_new_reply_form', array($this, 'get_shortcode_output')); //new reply form
		add_shortcode('el_forum_practitioners_table', array($this, 'get_shortcode_output')); //displays list of practitioners
		add_shortcode('el_forum_practitioner_profile', array($this, 'get_shortcode_output')); //displays single prac profile
		add_shortcode('el_forum_user_profile', array($this, 'get_shortcode_output')); //display single user profile
		add_shortcode('el_forum_user_topics_table', array($this, 'get_shortcode_output')); //display topics owned / assigned to user (user + prac)
		add_shortcode('el_forum_user_profile_form', array($this, 'get_shortcode_output')); //displays the user profile information form. To collect additional user meta after signup
	}
	
	//get output for shortcode (returned to the editor or initiator)
	//TODO: REMEMBER - NO apply_filters allowed in shortcodes, breaks output!
	public function get_shortcode_output($atts, $content = '', $name){
		
		
		$html = '';
	
		//list topics
		if($name == 'el_forum_topics_table'){
			$html = $this->get_topic_table_markup();
		}
		//reply
		else if($name == 'el_forum_new_reply_form'){
			//$html .= $instance::get_reply_form_markup();
		}
		//new topic
		else if($name == 'el_forum_new_topic_form'){
			//$html .= $instance::get_new_topic_form_markup();
		}
		//list of practitioners
		else if($name == 'el_forum_practitioners_table'){
			$html .= $this->get_practitioner_table_markup();	
		}
		//single practitioner front end profile 
		else if($name == 'el_forum_practitioner_profile'){
			$html .= $this->get_single_practitioner_profile();
		}
		//single member front end profile
		else if($name == 'el_forum_user_profile'){
			$html .= $this->get_single_user_profile();
		}
		//list of current users topics
		else if($name == 'el_forum_user_topics_table'){	
			$html .= $this->get_user_topic_table_markup();
		}
		//Get the additional user information form
		else if($name == 'el_forum_user_profile_form'){
			$html .= $this->get_user_profile_form();
		}
		
		return $html;
	}
	
	/**
	* Get the markup for a listing of practitioners
	* Creates a table output consisting of all 'practitioners' in the system, These are restrict content pro users (access level 3).
	*/
	public function get_practitioner_table_markup(){
		
		//find all users that are practitioners
		$user_args = array(
			'orderby'		=> 'registered',
			'order'			=> 'DESC',
			'meta_key'		=> 'rcp_subscription_level',
			'meta_value'	=> '3'
		);
		
		$practitioners = get_users($user_args);
		if($practitioners){
			$html .= '<div class="el-practitioner-table" id="practitioner-table">';
			foreach($practitioners as $practioner){
				$html .= $this->get_single_practitioner_table_markup($practioner->ID);
			}
			$html .= '</div>';
		}else{
			$html .= '<div class="el-notice el-notice-warning">';
				$html .= '<strong>No practitioners are currently registered for this system. Please check back soon</strong>';
			$html .= '</div>';
		}
		
		
		return $html;
		
	}
	
	/**
	 * Get the markup for a single practitioner row
	 * Markup to be used in the practitioner table to showcase practitioner information
	 */
	public function get_single_practitioner_table_markup($practitioner_id){
		
		$html = '';
		
		if($practitioner_id){
			
			$user = get_user_by('ID', $practitioner_id);
			if($user instanceof WP_User){
					
				//collect user info
				$user_id = $user->ID;
				$user_profile_image_id = get_user_meta($user->ID, 'rcp_user_profile_image', true);
				$user_first_name = $user->first_name;
				$user_last_name = $user->last_name; 
				$user_profile_link = $this->get_practitioner_profile_link($user_id);
				
				$html .= '<div class="el-practitioner">';
					
					//if we have a photo
					if($user_profile_image_id){
						$user_profile_attachment = get_post($user_profile_image_id);
						
						if($user_profile_attachment instanceof WP_Post){
							$user_profile_image_src = wp_get_attachment_image_src($user_profile_attachment->ID, 'thumbnail', false)[0];
							$html .= '<div class="profile-image-wrap">';
								$html .= '<a href="' . $user_profile_link . '">';
									$html .= '<div class="profile-image" style="background-image: url(' . $user_profile_image_src . ');"></div>';
								$html .= '</a>';
							$html .= '</div>';
						}
					}
					
					//display name
					if(!empty($user_first_name) || !empty($user_last_name)){
						$html .= '<h3>';
						if(!empty($user_first_name)){
							$html .= $user_first_name . ' ';
						}
						if(!empty($user_last_name)){
							$html .= $user_last_name;
						}
						$html .= '</h3>';
					}
					
					
					
					//get the link to the practitioners profile
					$html .= $this->get_practitioner_profile_button($user_id);
					
				$html .= '</div>';
				
			}else{
				$html .= '<b>Practitioner information couldnt be collected</b>';
			}
			
			
		}else{
			$html .= '<b>No Practitioner ID passed</b>';
		}
		
		return $html;
	}
	
	/**
	 * Gets the practitioner hire button 
	 * 
	 * Displayed on the listing of practitioners and also on any replies practitions have posted on topics. Main way that users will be able to navigate 
	 * to the practitioners profile
	 * 
	 * @param string $practitioner_id
	 */
	public function get_practitioner_profile_button($practitioner_id){
		
		$html = '';
		
		if($practitioner_id){
			
			//TODO: Not hard code this in
			$url = get_site_url() . '/dashboard/dietitions/dietition/?practitioner_id=' . $practitioner_id;
			$html .= '<a class="el-practitioner-button button small-button" href="' . $url .'">View Dietition Profile</a>';
				
		}
		
		
		return $html;
	}
	
	/**
	 * Get the URL to a practitioners profile
	 * 
	 * Useful for when you need the link to the profile for your own custom links 
	 */
	public function get_practitioner_profile_link($practitioner_id){
			
		$html = '';
		
		if($practitioner_id){

			//TODO: Not hard code this in
			$url = get_site_url() . '/dashboard/dietitions/dietition/?practitioner_id=' . $practitioner_id;
			$html = $url;
		}
		
		return $html;
	}
	
	
	
	/**
	 * Gets the markup for a single practitioners front end profile
	 * 
	 * Primary output generated for the 'el_forum_practitioner_profile' shortcode added to the 'practitioner' page, displays all of the practitioner
	 * information and the hire button if the user can hire a practitioner
	 * @param string $practitioner_id
	 */
	public function get_single_practitioner_profile($practitioner_id = null){
		

		$html = '';
		
		$profile_id = '';
		//passed by server
		if(isset($_REQUEST['practitioner_id'])){
			$profile_id = sanitize_text_field($_REQUEST['practitioner_id']); 
		}
	
		//supplied as an arg
		if(!is_null($practitioner_id)){
			$profile_id = $practitioner_id;
		}
	
		//execute only if we have a profile id
		if($profile_id){
			$user = get_user_by('ID', $profile_id);
			
			if($user instanceof WP_User){
				
				$user_id = $user->ID;
				$user_first_name = $user->first_name;
				$user_last_name = $user->last_name; 
				$user_profile_image = get_user_meta($user_id, 'rcp_user_profile_image', true);
				
				$user_bio = get_user_meta($user_id, 'rcp_user_bio', true);
				$user_qualifications = get_user_meta($user_id, 'rcp_user_qualifications', true);
				
				$html .= '<div class="user-table">';
					$html .= '<p>Here you can view the profile for this practitioner</p>';	
					
					//ACCOUNT
					$html .= '<section class="field-section profile cf">';
						$html .= '<h3>Account Information</h3>';	
						
						//display photo if we have one
						if($user_profile_image){
							$user_profile_attachment = get_post($user_profile_image);
							if($user_profile_attachment instanceof WP_Post){
								$user_profile_image_url = wp_get_attachment_image_src($user_profile_attachment->ID, 'medium', false)[0];
								
								$html .= '<div class="profile-image-wrap">';
									$html .= '<div class="profile-image" style="background-image: url(' . $user_profile_image_url .');"></div>';
								$html .= '</div>';
							}	
						}
						//top level important account info
						$html .= '<div class="profile-wrap">';
							$html .= '<div class="user-field">';
								$html .= '<div class="user-key">First Name</div>';
								$html .= '<div class="user-value">' . $user_first_name . '</div>';
							$html .= '</div>';
							$html .= '<div class="user-field">';
								$html .= '<div class="user-key">Last Name</div>';
								$html .= '<div class="user-value">' . $user_last_name . '</div>';
							$html .= '</div>';
							
							//Get hire button
							$html .= $this->get_practitioner_hire_button_markup($user_id);
					
						$html .= '</div>';
					$html .= '</section>';
					
					//BIO
					$html .= '<section class="field-section">';
						$html .= '<h3>BIO</h3>';	
						if(!empty($user_bio)){
							$html .= '<div class="user-field">';
								$html .= '<div class="user-key">BIO</div>';
								$html .= '<div class="user-value">' . $user_bio . '</div>';
							$html .= '</div>';
						}
					$html .= '</section>';
						
					//QUALIFICATIONS
					$html .= '<section class="field-section">';
						$html .= '<h3>Qualifications</h3>';
						if(!empty($user_qualifications)){
							$html .= '<div class="user-field">';
								$html .= '<div class="user-key">Qualifications</div>';
								$html .= '<div class="user-value">' . $user_qualifications . '</div>';
							$html .= '</div>';
						}	
					$html .= '</section>';
					
					
					//Get a sample of topics the practitioner has currently been assigned
					// $html .= '<section class="field-section">';
						// $html .= '<h3>Current Topics</h3>';
						// $html .= '<p>Here are some of the topics this practitioner is currently involved in</p>';
						// $html .= '<b>TODO: Show public topics that have this practitioner in their thread</b>';
					// $html .= '</section>';
					
					//Get hire button
					$html .= '<section class="field-section">';
						$html .= $this->get_practitioner_hire_button_markup($user_id);
					$html .= '</section>';
						
				$html .= '</div>';	
			}
		}else{
			$html .= '<div class="el-notice el-notice-warning">';
				$html .= '<strong>No practitioner ID was supplied. Please go back to the listing of practitioners</strong>';
			$html .= '</div>';
		}
		
		return $html;
	}

	/**
	 *Get single user profile link button. Link directly to the users profile
	 */ 
	 
	 public function get_user_profile_button($user_id){
	 		
	 	$html = '';
		
		if($user_id){
			
			//TODO: Not hard code this in
			$url = get_site_url() . '/your-profile/?user_id=' . $user_id;
			$html .= '<a class="el-user-button small-button button" href="' . $url .'">View User Profile</a>';
				
		}
		
		
		return $html;
		
	 }



	/**
	 * Get single user (member) front end profile
	 * 
	 * Primary output generated for the '[el_forum_user_profile]' shortcode added to the 'User Profile' page.
	 * Displays all of the user meta information collected during signup, used so that the practitioner can find out more information about the user.
	 */
	public function get_single_user_profile($user_id = null){
			
			
		$html = '';
		
		$member_id = '';
		
		//if we are already logged in, try and use the current users ID
		if(is_user_logged_in()){
			$member_id = get_current_user_id();
		}
		
		//passed by server
		if(isset($_REQUEST['user_id'])){
			$member_id = sanitize_text_field($_REQUEST['user_id']); 
		}
		
		//supplied as an arg
		if(!is_null($user_id)){
			$member_id = $user_id;
		}
	
		//execute only if we have a member id
		if($member_id){
				
			$user = get_user_by('ID', $member_id);
			
			if($user instanceof WP_User){

				//get all useful metadata
				$user_id = $user->ID;
				$user_extra_info = get_currentuserinfo();
				
				$user_first_name = $user_extra_info->user_firstname;
				$user_last_name = $user_extra_info->user_lastname;
				$user_email = $user_extra_info->user_email;
				
				$rcp_user_profile_image = get_user_meta($user_id, 'rcp_user_profile_image', true);
				$rcp_user_lightest_weight = get_user_meta($user_id, 'rcp_user_lightest_weight', true);
				$rcp_user_heaviest_weight = get_user_meta($user_id, 'rcp_user_heaviest_weight', true);
				$rcp_user_height = get_user_meta($user_id, 'rcp_user_height', true);
				$rcp_user_days_gym = get_user_meta($user_id, 'rcp_user_days_gym', true);
				$rcp_user_member_of_gym = get_user_meta($user_id, 'rcp_user_member_of_gym', true);
				$rcp_user_exercise_preference = get_user_meta($user_id, 'rcp_user_exercise_preference', true);
				$rcp_user_exercise_time = get_user_meta($user_id, 'rcp_user_exercise_time', true);
				$rcp_user_exercise_extra = get_user_meta($user_id, 'rcp_user_exercise_extra', true);
				$rcp_user_medical_history = get_user_meta($user_id, 'rcp_user_medical_history', true);
				$rcp_user_occupation = get_user_meta($user_id, 'rcp_user_occupation', true);
				$rcp_user_work_hours = get_user_meta($user_id, 'rcp_user_work_hours', true);
				$rcp_user_main_income_earner = get_user_meta($user_id, 'rcp_user_main_income_earner', true);
				$rcp_user_family_information = get_user_meta($user_id, 'rcp_user_family_information', true);
				$rcp_user_smoker = get_user_meta($user_id, 'c', true);
				$rcp_user_smoker_amount = get_user_meta($user_id, 'rcp_user_smoker_amount', true);
				$rcp_user_drinker = get_user_meta($user_id, 'rcp_user_drinker', true);
				$rcp_user_drinker_amount = get_user_meta($user_id, 'rcp_user_drinker_amount', true);
				$rcp_user_drinker_action = get_user_meta($user_id, 'rcp_user_drinker_action', true);
				$rcp_user_social_outing_food = get_user_meta($user_id, 'rcp_user_social_outing_food', true);
				$rcp_user_bought_meals_per_week = get_user_meta($user_id, 'rcp_user_bought_meals_per_week', true);
				$rcp_user_cooked_dinners_per_week = get_user_meta($user_id, 'rcp_user_cooked_dinners_per_week', true);
				$rcp_user_who_cooks = get_user_meta($user_id, 'rcp_user_who_cooks', true);
				$rcp_user_who_shops = get_user_meta($user_id, 'rcp_user_who_shops', true);
				$rcp_user_social_extra = get_user_meta($user_id, 'rcp_user_social_extra', true);
				$rcp_user_disliked_foods = get_user_meta($user_id, 'rcp_user_disliked_foods', true);
				$rcp_user_liked_foods = get_user_meta($user_id, 'rcp_user_liked_foods', true);
				$rcp_user_days_meal = get_user_meta($user_id, 'rcp_user_days_meal', true);
				$rcp_user_days_snacks = get_user_meta($user_id, 'rcp_user_days_snacks', true);
				$rcp_user_ultimate_goal = get_user_meta($user_id, 'rcp_user_ultimate_goal', true);
				$rcp_user_short_term_goal = get_user_meta($user_id, 'rcp_user_short_term_goal', true);
				$rcp_user_hurdles = get_user_meta($user_id, 'rcp_user_hurdles', true);
				$rcp_user_future_hurdles = get_user_meta($user_id, 'rcp_user_future_hurdles', true);
				$rcp_user_excited = get_user_meta($user_id, 'rcp_user_excited', true);
				
				//GENERAL HEALTH INFORMATION
				
				$html .= '<div class="user-table">';
				
					$html .= '<p>Here is your profile information as seen by others.</p>';
					$html .= '<p><a href="/profile-information/" class="button primary-button">Edit your profile</a></p>';
					
					//ACCOUNT

					
					$html .= '<section class="field-section profile cf">';
						$html .= '<h3>Account Information</h3>';	
						
						//display photo if we have one
						if($rcp_user_profile_image){
							$user_profile_attachment = get_post($rcp_user_profile_image);
							if($user_profile_attachment instanceof WP_Post){
								$user_profile_image_url = wp_get_attachment_image_src($user_profile_attachment->ID, 'medium', false)[0];
								
								$html .= '<div class="profile-image-wrap">';
									$html .= '<div class="profile-image" style="background-image: url(' . $user_profile_image_url .');"></div>';
								$html .= '</div>';
							}	
						}
						
						//top level important account info
						$html .= '<div class="profile-wrap">';
							$html .= '<div class="user-field">';
								$html .= '<div class="user-key">First Name</div>';
								$html .= '<div class="user-value">' . $user_first_name . '</div>';
							$html .= '</div>';
							$html .= '<div class="user-field">';
								$html .= '<div class="user-key">Last Name</div>';
								$html .= '<div class="user-value">' . $user_last_name . '</div>';
							$html .= '</div>';
							$html .= '<div class="user-field">';
								$html .= '<div class="user-key">Email</div>';
								$html .= '<div class="user-value">' . $user_email . '</div>';
							$html .= '</div>';	
						$html .= '</div>';
						
					$html .= '</section>';
					
					
					//GENERAL
					$html .= '<section class="field-section">';

						$html .= '<h3>General Health Information</h3>';	
		
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Lightest you have been post 18 years old (in KG)</div>';
							$html .= '<div class="user-value">' . $rcp_user_lightest_weight . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Heaviest you have been post 18 years old (in KG)</div>';
							$html .= '<div class="user-value">' . $rcp_user_heaviest_weight . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Current Height (cm)</div>';
							$html .= '<div class="user-value">' . $rcp_user_height . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">How many days a week do you exercise?</div>';
							$html .= '<div class="user-value">' . $rcp_user_days_gym . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Do you have a gym membership?</div>';
							$html .= '<div class="user-value">' . $rcp_user_member_of_gym . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">What type of exercise do you prefer?</div>';
							if(!empty($rcp_user_exercise_preference)){
								$preferences_array = json_decode($rcp_user_exercise_preference);
								$rcp_user_exercise_preference = implode(',', $preferences_array);
							}
							$html .= '<div class="user-value">' . $rcp_user_exercise_preference . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Average Exercise Time Per Day(Minutes)</div>';
							$html .= '<div class="user-value">' . $rcp_user_exercise_time . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Any extra exercise info (e.g intensity, location, type)</div>';
							$html .= '<div class="user-value">' . $rcp_user_exercise_extra . '</div>';
						$html .= '</div>';
					$html .= '</section>';
					
					
					//MEDICAL INFORMATION
					$html .= '<section class="field-section">';
						$html .= '<h3>Medical Information</h3>';	
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Do you have any other information medically relevant to your Dietition (even if you think it is not significant)</div>';
							$html .= '<div class="user-value">' . $rcp_user_medical_history . '</div>';
						$html .= '</div>';
					$html .= '</section>';
							
					//SOCIAL HISTORY
					$html .= '<section class="field-section">';
						$html .= '<h3>Social History</h3>';
						
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">What is your occupation (eg, plumber, full time student, full time mum/dad etc)</div>';
							$html .= '<div class="user-value">' . $rcp_user_occupation . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">How many hours a week do you work on average</div>';
							$html .= '<div class="user-value">' . $rcp_user_work_hours . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Are you the main income earner for your household?</div>';
							$html .= '<div class="user-value">' . $rcp_user_main_income_earner . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">If you have children, please enter how many and their ages</div>';
							$html .= '<div class="user-value">' . $rcp_user_family_information . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Are you a smoker?</div>';
							$html .= '<div class="user-value">' . $rcp_user_smoker . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">If you smoke, how many would you smoke per week?</div>';
							$html .= '<div class="user-value">' . $rcp_user_smoker_amount . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Do you drink?</div>';
							$html .= '<div class="user-value">' . $rcp_user_smoker . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">If you drink, how many standard drinks would you have per week?</div>';
							$html .= '<div class="user-value">' . $rcp_user_drinker_amount . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Which of the following would you be willing to do?</div>';
							$html .= '<div class="user-value">' . $rcp_user_drinker_action . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Do your social outings generall include food?</div>';
							$html .= '<div class="user-value">' . $rcp_user_social_outing_food . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">How many meals per week are bought on the go or from a shop ready made? (this is any time of the day)</div>';
							$html .= '<div class="user-value">' . $rcp_user_bought_meals_per_week . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">How many dinners per week are home cooked?</div>';
							$html .= '<div class="user-value">' . $rcp_user_cooked_dinners_per_week . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Who does the cooking at home?(put down your relation to them, eg, wife, or myself etc)</div>';
							$html .= '<div class="user-value">' . $rcp_user_who_cooks . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Who does the shopping usually?</div>';
							$html .= '<div class="user-value">' . $rcp_user_who_shops . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Any other information you would like to note about your social history?</div>';
							$html .= '<div class="user-value">' . $rcp_user_social_extra . '</div>';
						$html .= '</div>';
					$html .= '</section>';
					
					
					
					//DIET
					$html .= '<section class="field-section">';
						$html .= '<h3>Diet</h3>';
						
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">What foods do you hate/will not eat at all, even if we suggested them?</div>';
							$html .= '<div class="user-value">' . $rcp_user_disliked_foods . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">What foods do you love and hope to have included in your plan?(list any but please be realistic)</div>';
							$html .= '<div class="user-value">' . $rcp_user_liked_foods . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">How many times a day can you realistically eat a meal?</div>';
							$html .= '<div class="user-value">' . $rcp_user_days_meal . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">How many times a day can you eat a "snack"?</div>';
							$html .= '<div class="user-value">' . $rcp_user_days_meal . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Ultimate Goal (eg, I want to lose 60kg)</div>';
							$html .= '<div class="user-value">' . $rcp_user_ultimate_goal . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Short Term Goal (eg, I want to lose 2kg)</div>';
							$html .= '<div class="user-value">' . $rcp_user_short_term_goal . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Biggest hurdles (in the past)</div>';
							$html .= '<div class="user-value">' . $rcp_user_hurdles . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">What you think your major hurdles will be during this plan if any?</div>';
							$html .= '<div class="user-value">' . $rcp_user_future_hurdles . '</div>';
						$html .= '</div>';
						$html .= '<div class="user-field">';
							$html .= '<div class="user-key">Are you excited to start your Healthy Imaging journey towards a Healthier Image of yourself?</div>';
							$html .= '<div class="user-value">' . $rcp_user_excited . '</div>';
						$html .= '</div>';
					$html .= '</section>';	
				$html .= '</div>';

				
			}
			
		}
		
		
		return $html;
	}

	/**
	 * Button used to hire the practitioner. 
	 * 
	 * Connects a registered user to a practitioner. Each user can have 1 practitioner and practitioners can have many.
	 * This button only shows up for users and if they don't already have an assigned practitioner (if they are a free member it gives them an upgrade button)
	 * 
	 */
	public function get_practitioner_hire_button_markup($practitioner_id){
		
		$html = '';
		
		//if user logged in
		if(is_user_logged_in()){
			
			$user_id = get_current_user_id(); 
			$subscription_level = rcp_get_subscription_id($user_id);
			
			//only allow for standard subscription members
			if($subscription_level == '2'){
					
				//check to make sure the user doesn't already have an assigned practitioner
				$has_practitioner = $this->user_has_assigned_practitioner($user_id);
	
				if($has_practitioner !== true){
					$html .= '<div 
					class="el-hire-practitioner-button button" 
					id="el-hire-practitioner-button" 
					data-button-action="assign_practitioner" 
					data-user-id="' . $user_id .'"
					data-practitioner-id="' . $practitioner_id . '">Hire this practitioner</div>';
				}else{
					//already have prac, send them to their profile
					
					$practitioner_id = $this->get_user_assigned_practitioner($user_id);
					$practitioner_url = $this->get_practitioner_profile_link($practitioner_id);
					
					$html .= '<div class="el-notice el-notice-warning">';
						$html .= 'You already have a practitioner assigned to your acount. ';
						$html .= '<a class="button small-button" href="' . $practitioner_url . '">View Practitioner</a>';
					$html .= '</div>';
				}
			}
			//free members, use upsell button!
			else if($subscription_level == '1'){
				$html .= '<div class="el-notice el-notice-warning">';
					$html .= '<p>You need to upgrade your account to hire a practitioner</p>';
					$html .= '<p><a href="/account-upgrade/" class="button primary-button">Upgrade Account</a></p>';
				$html .= '</div>';
			}else{
				$html .= '<div class="el-notice el-notice-warning">';
					$html .= '<p>Only free or subscribed members can hire practitioners</p>';
					
				$html .= '</div>';
			}
		};
			
		return  $html;
	}
	
	//register the 'el_topic' content type
	public function regsiter_topic_content_type(){
		
		$labels = array(
			'name'               => _x( 'topic', 'post type general name', 'elevate-nutrition-system' ),
			'singular_name'      => _x( 'topic', 'post type singular name', 'elevate-nutrition-system' ),
			'menu_name'          => _x( 'Messaging Topics', 'admin menu', 'elevate-nutrition-system' ),
			'name_admin_bar'     => _x( 'Topic', 'add new on admin bar', 'elevate-nutrition-system' ),
			'add_new'            => _x( 'Add New', 'topic', 'elevate-nutrition-system' ),
			'add_new_item'       => __( 'Add New Topic', 'elevate-nutrition-system' ),
			'new_item'           => __( 'New Topic', 'elevate-nutrition-system' ),
			'edit_item'          => __( 'Edit Topic', 'elevate-nutrition-system' ),
			'view_item'          => __( 'View Topic', 'elevate-nutrition-system' ),
			'all_items'          => __( 'All Topics', 'elevate-nutrition-system' ),
			'search_items'       => __( 'Search Topics', 'elevate-nutrition-system' ),
			'parent_item_colon'  => __( 'Parent Topic:', 'elevate-nutrition-system' ),
			'not_found'          => __( 'No topics found.', 'elevate-nutrition-system' ),
			'not_found_in_trash' => __( 'No topics found in Trash.', 'elevate-nutrition-system' )
		);
	
		$args = array(
			'labels'             => $labels,
	        'description'        => __( 'Topics, central part of the messaging system.', 'elevate-nutrition-system' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'topic' ),
			'capability_type'    => 'post',
			'show_in_admin_bar'	 => false,
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 22,
			'menu_icon'			 => 'dashicons-analytics',
			'supports'           => array( 'title', 'editor', 'author' )
		);
		
		register_post_type('el_topics', $args);
	}

	/**
	 * Register taxonomies (categories) for the topic content type, to organise topics into segments
	 */ 
	public function register_topic_taxonomies(){
		
		$labels = array(
			'name'                       => __( 'Categories', 'elevate-nutrition-system' ),
			'singular_name'              => __( 'Category', 'elevate-nutrition-system' ),
			'search_items'               => __( 'Search Categories', 'elevate-nutrition-system' ),
			'popular_items'              => __( 'Popular Categories', 'elevate-nutrition-system' ),
			'all_items'                  => __( 'All Categories', 'elevate-nutrition-system' ),
			'parent_item'                => __( 'Parent Category', 'elevate-nutrition-system'),
			'parent_item_colon'          => __( 'Parent Category:', 'elevate-nutrition-system'),
			'edit_item'                  => __( 'Edit Category', 'elevate-nutrition-system' ),
			'update_item'                => __( 'Update Category', 'elevate-nutrition-system' ),
			'add_new_item'               => __( 'Add New Category', 'elevate-nutrition-system' ),
			'new_item_name'              => __( 'New Category Name', 'elevate-nutrition-system' ),
			'separate_items_with_commas' => __( 'Separate Categories with commas', 'elevate-nutrition-system' ),
			'add_or_remove_items'        => __( 'Add or remove Categories', 'elevate-nutrition-system' ),
			'choose_from_most_used'      => __( 'Choose from the most used Categories', 'elevate-nutrition-system' ),
			'not_found'                  => __( 'No Categories found.', 'elevate-nutrition-system' ),
			'menu_name'                  => __( 'Categories', 'elevate-nutrition-system' ),
		);
		
		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'show_in_rest'		=> true,
			'publicly_queryable'=> true,
			'show_in_nav_menus' => true,
			'rewrite'			=> array(
				'slug'				=> 'topic-category',
				'with_front'		=> false,
				'hierarchical'		=> true
			),
			'public'			=> true,
		);
		
		
		//reigster taxonomy
		register_taxonomy('el_topic_category', 'el_topics', $args);
		register_taxonomy_for_object_type('el_topic_category', 'el_topics');
		
	}


	/**
	 * Gets the ID of the practitioner that this user has been assigned
	 * 
	 * Paid members can be assigned a single practitioner that they can correspond directly with. This can't be changed and can be selected once.
	 * Returns the ID of the practitioner (or empty if not assigned)
	 */
	public function get_user_assigned_practitioner($user_id){
		
		$html = '';
		
		if($user_id){
			
			$user = get_user_by('ID', $user_id);
			
			if($user instanceof WP_User){
				$assigned_practitioner_id = get_user_meta($user_id, 'practitioner_id', true);
				if($assigned_practitioner_id){
					$html = $assigned_practitioner_id;
				}
			}
		}
		
		
		return $html;
		
	}
	
	/*
	 * Determines if the selected user has a practitioner assigned to them.
	 * 
	 * Returns true if practitioner is assigned to the user, false otherwise
	 */
	public function user_has_assigned_practitioner($user_id){
			
		$html = false;
		
		if($user_id){
			$user = get_user_by('ID', $user_id);
			
			if($user instanceof WP_User){
				$assigned_practitioner_id = get_user_meta($user_id, 'practitioner_id', true);
				if($assigned_practitioner_id){
					$html = true;
				}
			}
		}
		
		return $html;
	}
	
	//register the 'el_reply' content type
	public function register_reply_content_type(){
			
		$labels = array(
			'name'               => _x( 'replies', 'post type general name', 'elevate-nutrition-system' ),
			'singular_name'      => _x( 'reply', 'post type singular name', 'elevate-nutrition-system' ),
			'menu_name'          => _x( 'Messasging Replies', 'admin menu', 'elevate-nutrition-system' ),
			'name_admin_bar'     => _x( 'Replies', 'add new on admin bar', 'elevate-nutrition-system' ),
			'add_new'            => _x( 'Add New', 'reply', 'elevate-nutrition-system' ),
			'add_new_item'       => __( 'Add New Reply', 'elevate-nutrition-system' ),
			'new_item'           => __( 'New Reply', 'elevate-nutrition-system' ),
			'edit_item'          => __( 'Edit Reply', 'elevate-nutrition-system' ),
			'view_item'          => __( 'View Reply', 'elevate-nutrition-system' ),
			'all_items'          => __( 'All Replies', 'elevate-nutrition-system' ),
			'search_items'       => __( 'Search Replies', 'elevate-nutrition-system' ),
			'parent_item_colon'  => __( 'Parent Reply:', 'elevate-nutrition-system' ),
			'not_found'          => __( 'No reply found.', 'elevate-nutrition-system' ),
			'not_found_in_trash' => __( 'No replies found in Trash.', 'elevate-nutrition-system' )
		);
	
		$args = array(
			'labels'             => $labels,
	        'description'        => __( 'Replies, correspondences between users in a topic.', 'elevate-nutrition-system' ),
			'public'             => true,
			'publicly_queryable' => false, //no direct access to replies
			'show_ui'            => true,
			'exclude_from_search'=> true, //exclude replies from searches
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'reply' ),
			'capability_type'    => 'post',
			'show_in_admin_bar'	 => false,
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 23,
			'menu_icon'			 => 'dashicons-testimonial',
			'supports'           => array( 'title', 'editor', 'author')
		);
		
		register_post_type('el_replies', $args);
	}

	//output additional userful info about the user to be accessed via our JS scripts
	public function localize_script_for_js(){
		
		//if we have our main js file enqueued
		if(wp_script_is('el_nutrition_system_script', 'enqueued')){
			
			//enqueue the main ajax_url variable
			wp_localize_script('el_nutrition_system_script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'))); 
			
			
			//collect user information
			$user = wp_get_current_user();
			if($user->ID !== 0){
				
				$additional_info = array();
				$additional_info['user_id'] = $user->ID;
				
				//make our 'user_information' variable accessible from JS
				wp_localize_script('el_nutrition_system_script', 'user_information', $additional_info);
				
			}
			
			
		}
		
		
		
	}
	
	//gets the user name, posted date and other meta for display above a single topic
	public static function get_single_topic_meta($topic_id){
		$instance = self::getInstance();
		$html = '';
		
		if($topic_id){
			$author_id = get_post_field( 'post_author', $topic_id );
			$author_nickname = get_user_meta($author_id, 'nickname', true);
			$posted_date = get_the_time('d/m/Y - h:i A', $reply_id);
			$post_categories = wp_get_post_terms($topic_id, 'el_topic_category');
			
			
			$html .= '<div class="topic-meta">';
				if(!empty($posted_date)){
					$html .= '<span class="meta posted-date"><strong>Posted on:</strong> ' . $posted_date .'</span>'; 
				}
				if(!empty($author_nickname)){
					$html .= '<span class="meta author"><strong>Posted by:</strong> ' . $author_nickname . '</span>'; 
				}
				if(!empty($post_categories)){
					$html .= '<div class="category-listing">';
						$html .= '<span class="title">Categoriesed Under</span>';
						foreach($post_categories as $category){
							$category_term_link = get_term_link($category);
							$category_name = $category->name;
							$html .= '<a class="button small-button" href="' . $category_term_link . '">' . $category_name  . '</a></span>'; 
						}
					$html .= '</div>';
				}
			$html .= '</div>';
			
		}
		
		return $html;
	}
	
	/**
	 *Gets the title, content and meta information for a single topic, displayed on the single topic template 
	 */
	public static function get_single_topic_content($topic_id){
		$instance = self::getInstance();
		
		
		$html = '';
		
		if($topic_id){
			
			$topic = get_post($topic_id);
			$topic_id = $topic->ID;
			$topic_title = apply_filters('the_title', $topic->post_title);
			$topic_content = apply_filters('the_content', $topic->post_content);
			$topic_classes = get_post_class("", $topic_id);
			
			$html .= '<div class="single-topic-wrap">';
			
				//get topic title and content
				$html .= '<article id="post-' . $topic_id . '" class=""">';
					$html .= '<header class="entry-header">';
						$html .= '<h1 class="entry-title">' . $topic_title . '</h1>';
					$html .='</header>';
				
					//get topic meta
				$html .= $instance->get_single_topic_meta($topic_id);
				
					$html .= '<div class="entry-content">';
						$html .= $topic_content;
					$html .= '</div>';
				$html .= '</article>';
				
	
				
			$html .= '</div>';
		}

		return $html;
	}
	
	//called by ajax to get the markup for a single topic (triggered when we submit a new topic)
	public static function get_single_topic_table_markup_ajax(){
		
		$instance = self::getInstance();
		$response = array(); 
		
		//execute only if we have a topic ID
		if($_POST['topic_id']){
			
			$topic_id = $_POST['topic_id'];
			$topic_content .= $instance->get_single_topic_table_markup($topic_id);	
			
			$response['status'] = 'success';
			$response['message'] = 'successfully collected single topic table markup';
			$response['content'] = $topic_content;
				
		}else{
			$response['status'] = 'error';
			$response['message'] = 'topic id passed to output function either incorrect or malformed';
		}
		
		echo json_encode($response);

		wp_die();
	}
	
	/**
	 * Ajax call which determines if the user who just posted a new topic is no longer able to post any more topic
	 */
	public static function check_post_new_reply_limit_ajax(){
			
		$instance = self::getInstance();
		$response = array(); 
		
		//determine if the user can no longer post topics. 
		if($_POST['user_id']){
			
			$user_id = $_POST['user_id'];	
			$can_post_new_topic = $instance->user_can_post_new_topic($user_id);
			
			$response['status'] = 'success';
			$response['message'] = 'Ajax check to determine post count was successful';
			$response['user_can_post'] = $can_post_new_topic;
			
		}else{
			$response['status'] = 'error';
			$response['message'] = 'user id passed to the check_post_new_reply_limit_ajax function was not passed.';
		}
		
		
		
		echo json_encode($response);
		
		wp_die();
		
	}
	
	//get the HTML for a single topic when displayed inside the table listing
	public static function get_single_topic_table_markup($topic_id){
		
		$instance = self::getInstance();
		$html = '';
		
		if($topic_id){
			$topic = get_post($topic_id);
			if($topic){
				
				//collect topic info
				$topic_id = $topic->ID;
				$topic_title = $topic->post_title;
				$topic_content = $topic->post_content;
				if($topic_content){
					$topic_content = wp_trim_words($topic_content, 35, '...');
				}
				
				$topic_categories = wp_get_post_terms($topic_id, 'el_topic_category');
				
				$topic_url = get_permalink($topic_id);
				$topic_posted_date = get_the_time('d/m/Y - h:ia', $topic_id);
				
				$topic_user_id = get_post_field('post_author', $topic_id);
				$topic_user_nickname= get_user_meta($topic_user_id, 'nickname', true);
				$topic_privacy = get_post_meta($topic_id, 'topic_privacy ', true);
				$topic_assigned_practitioner = get_post_meta($topic_id, 'topic_assigned_practitioner', true);
				$topic_author_image_id = get_user_meta($topic_user_id, 'rcp_user_profile_image', true);
				if($topic_author_image_id){
					$topic_author_image_url = wp_get_attachment_image_src($topic_author_image_id, 'full', false)[0];
				}
				
				
				//determine how many replies this topic has
				$replies_args = array(
					'post_type'		=> 'el_replies',
					'post_status'	=> 'publish',
					'meta_key'		=> 'topic_id',
					'meta_value'	=> $topic_id,
					'fields'		=> 'ids',
					'posts_per_page'=> -1
				);
				$replies = get_posts($replies_args);
				$replies_count = ($replies) ? count($replies) : 0;
				
				//Dynamically change the card based on ownership and who is viewing the card
				$classes = '';
				$current_users_topic = false;
				$current_owner_content = '';
				if(is_user_logged_in()){
					
					$current_user_id = get_current_user_id();
					$subscription_level = rcp_get_subscription_id($topic_user_id);
					$profile_link = '';
					
					//TODO: remove hard coded link
					if($subscription_level == '1' || $subscription_level == '2'){
						$profile_link = get_site_url() . '/your-profile/?user_id=' . $user_id;
					}else if($subscription_level == '3'){
						$profile_link = get_site_url() . '/practitioners/practitioner/?practitioner_id=' . $topic_user_id;
					}
				
					//is the post author or the assigned practitioner
					if( ($current_user_id == $topic->post_author) || ($current_user_id == $topic_assigned_practitioner) ){
						$classes .= 'my-topic '; 
						
						if($current_user_id == $topic->post_author){
							$current_owner_content = '<div class="topic-owner">Your Topic</div>';
						}
						if($current_user_id == $topic_assigned_practitioner){
							$current_owner_content = '<div class="topic-owner">Topic has been assigned to you </div>';
						}
					}
				}
								
				
				$html .= '<div class="el-topic cf row-item ' . $classes . '">';
					
					$html .= '<div class="topic-wrap">';
						$html .= '<div class="title cf">';
							if(isset($topic_author_image_url)){
								$html .= '<div class="profile-image" style="background-image: url(' . $topic_author_image_url  . ');"></div>'; 	
							}
							$html .= '<h2 class="topic-title">' . $topic_title . '</h2>';
						$html .= '</div>';
						//display ownership if applicable
						if($current_owner_content){
							$html .= $current_owner_content;
						}
						$html .= '<div class="topic-meta">';
							$html .= '<div class="meta posted-date"><strong>Posted On:</strong> ' . $topic_posted_date . '</div>';
							
							//TODO: adjust and remove non logged in
							//display posted by with a link to profile
							if(is_user_logged_in()){
								$html .= '<div class="meta posted-author">';
									$html .= '<a href="' . $profile_link . '">';
										$html .= '<strong>Posted By:</strong> ' . $topic_user_nickname;
									$html .= '</a>';
								$html .= '</div>'; 
							}
							//else just name
							else{
								$html .= '<div class="meta posted-author"><strong>Posted By:</strong> ' . $topic_user_nickname . '</div>';
							}	
							if($replies_count != 0){
								$html .= '<div class="meta posted-replies"><strong>Replies:</strong> ' . $replies_count . '</div>';
							}

							//display categories 
							if(!empty($topic_categories)){
								$html .= '<div class="meta posted-categories"><strong>Categories:</strong>';
								foreach($topic_categories as $category){
									$category_link = get_term_link($category);
									$category_name = $category->name;
									$html .= '<a href="' . $category_link . '">' . $category_name . '</a>';
								}
								$html .= '</div>';
							}

						$html .= '</div>';	
						//removed for now
						//$html .= '<div class="content">' . $topic_content . '</div>';
					$html .= '</div>';
					
					$html .= '<div class="button-wrap">';
						$html .= '<a class="readmore button small-button primary-button" href="' . $topic_url . '">View Topic </a>';
					$html .= '</div>';
					
					
				$html .= '</div>';
			}	
		}
	
		return $html;
	}



	/**
	 * Ajax call that is used on every single topic. Keeps the current topic chain updated with new DB entries
	 * 
	 * Used to search for a topic ID and pass back a list of replies, updating the DOM with current entries.
	 */
	public function update_topic_with_replies_ajax(){
		
		$response = array();
		
		//collect topic id
		$topic_id = isset($_POST['topic_id']) ? sanitize_text_field($_POST['topic_id']) : '';
		if(topic_id){
			
			$topic_args = array(
				'post_type'		=> 'el_replies',
				'posts_per_page'=> -1,
				'post_status'	=> 'publish',
				'orderby'		=> 'date',
				'order'			=> 'ASC',
				'meta_key'		=> 'topic_id',
				'meta_value'	=> $topic_id	
			);
			
			$topics = get_posts($topic_args);
			//if we have topics
			if($topics){
				
				foreach($topics as $topic){
					
					$response['replies'][$topic->ID] = array(
						'reply_id'		=> $topic->ID,
						'reply_title'	=> $topic->post_title,
						'reply_content'	=> $this->get_single_reply_table_markup($topic->ID)
					);
				}
				
				$response['status'] = 'success';
				$response['message'] = 'successfully returned replies for topic';
			}

		}else{
			$response['status'] = 'error';
			$response['message'] = 'No topic ID set, cant fetch replies';
		}

		
		echo json_encode($response);
		wp_die();
	}

	//
	/**
	 * Gets the markup for the forum table, displaying posted topics
	 * 
	 * Displays all public topics and conditionally the new reply form
	 */
	public function get_topic_table_markup(){
		
		$html = '';
		
		//only logged in users can acesss the reply form
		if(is_user_logged_in()){
				
			$user_id = get_current_user_id();
			
			//check user (member or practitioner)
			$subscription_level = rcp_get_subscription_id($user_id);
			
			//only users can access the form
			if($subscription_level == 1 || $subscription_level == 2){
				
				//Determine if this user has exceeded their quota (free members can only post X topics)
				if($this->user_can_post_new_topic($user_id) == true){
					//Get the post new topic form
					$html .= $this->get_new_topic_form_markup();
				}else{
					
					//determine why the user can't post a new topic
					$reason = $this->get_user_cant_post_new_topic_reason($user_id); 
					
					$html .= '<div class="el-notice el-notice-warning">' . $reason . '</div>';
				}
				
				
				
			}else{
				$html .= '<div class="el-notice el-notice-warning">';
					$html .= '<strong>Only users of the system can post topics. You can see any topics assigned to you under the \'My Topics\' menu item</strong>';
				$html .= '</div>';
			}
		}	
		
		//get a listing of topic categories to display as quick links
		$html .= $this->get_topic_category_list();
		
		
		//get only public topics 
		$topic_args = array(
			'post_type'		=> 'el_topics',
			'posts_per_page'=> -1,
			'post_status'	=> 'publish',
			'meta_key'		=> 'topic_privacy',
			'meta_value'	=> 'public'
		);
		
		$topics = get_posts($topic_args);
		if($topics){
				
			$html .= '<div class="el-topic-table" id="topic-table">';
			foreach($topics as $topic){
				
				//single topic row
				$html .= $this->get_single_topic_table_markup($topic->ID);
			}
			$html .= '</div>';
		}else{
			$html .= '<div class="el-notice el-notice-warning">';
				$html .= '<strong>There are currently no topics. Use the form above to create your first topic</strong>';
			$html .= '</div>';
		}
		
		return $html;
	}

	

	/**
	 * Determines if a user can post a new topic 
	 * 
	 * A user can post a new topic if they are the correct user level and also have not exceeded their new topic limit which
	 * is limited to X for a free member to encourage account upgrades.
	 */
	public static function user_can_post_new_topic($user_id){
		
		$instance = self::getInstance();
		
		$result = false;
		
		if(is_user_logged_in()){
				
			$user_id = get_current_user_id();			
			//check user (member or practitioner)
			$subscription_level = rcp_get_subscription_id($user_id);
			
			//only users can access the form
			if($subscription_level == 1 || $subscription_level == 2){
						
				//if the user is level 1 (free) ensure they haven't reahed their post limit
				if($subscription_level == 1){
						
					$topic_args = array(
						'post_type'		=> 'el_topics',
						'posts_per_page'=> -1,
						'post_status'	=> 'publish',
						'orderby'		=> 'date',
						'order'			=> 'DESC',
						'author' 		=> $user_id,
						'fields'		=> 'ids'
					);
					
					$topics = get_posts($topic_args);
					
					//determine how many topics the user has created
					$number_of_topics = count($topics);
					
					if($number_of_topics < $instance->number_of_topics_free_member){
						$result = true;
					}
		
				}
				//user is a paid member, can post
				else if($subscription_level == 2){
					$result = true;
				}
			}
		}	
		
		
		return $result;
	}
	
	/**
	 * Determines why a user can't post a new topic
	 * 
	 * Used when we need to know why a user can't post a new topic, returns the reason as to why they cant post
	 * and used in an error notice
	 */
	public static function get_user_cant_post_new_topic_reason($user_id){
			
		$instance = self::getInstance();
		$message = 'You cant access this resource or content';
		
		if(is_user_logged_in()){
				
			$user_id = get_current_user_id();			
			//check user (member or practitioner)
			$subscription_level = rcp_get_subscription_id($user_id);
			
			//only users can access the form
			if($subscription_level == 1 || $subscription_level == 2){
						
				//if the user is level 1 (free) ensure they haven't reahed their post limit
				if($subscription_level == 1){
					
					$topic_args = array(
						'post_type'		=> 'el_topics',
						'posts_per_page'=> -1,
						'post_status'	=> 'publish',
						'orderby'		=> 'date',
						'order'			=> 'DESC',
						'author' 		=> $user_id,
						'fields'		=> 'ids'
					);
					
					$topics = get_posts($topic_args);
					//determine how many topics the user has created
					$number_of_topics = count($topics);
					
					if($number_of_topics >= $instance->number_of_topics_free_member){
						$url = get_site_url(null,'account-upgrade');
						$message = 'You have used all of your allocated topics, <a href="' . $url . '">please upgrade to continue to post topics</a>';
					}

				}
				
			}else{
				$message = 'Only users of the system can post topics, not practitioners';
			}
		}else{
			$message = 'You are currently not logged in';
		}
		
		return $message;
	}
	

	/**
	 * Gets the markup for displaying the topic categories as a series of quick links
	 * 
	 * Used above the topic table listings for users to quickly access archives topics based on categories
	 */ 
	public function get_topic_category_list(){
		
		$html .= '';
		
		$category_args = array(
			'hide_empty' => false
		);
		$categories = get_terms('el_topic_category', $category_args);
		if($categories){
			$html .= '<div class="topic-category-list">';
			$html .= '<h3>Topic Categories</h3>';
			foreach($categories as $category){
				$category_link = get_term_link($category);
				$category_name = $category->name;
				$category_count = $category->count;
				
				$html .= '<a href="' . $category_link . '" class="button small-button term">' . $category_name . ' (' . $category_count . ')</a>';
			}
			$html .= '</div>';
		}
		
		return $html;
	}
	
	/**
	 *Gets the markup for the topic categories, used above the listing of topics as quick links
	 */
	public static function display_get_topic_category_list(){
		$instance = self::getInstance();
	
		$html = '';
		
		$html .= $instance->get_topic_category_list();
		
		echo $html;
	}
	
	/**
	 * Gets topics belongong to a single user (member or practitioner)
	 * 
	 * Members will see all topics they've posted, Practitioners will see all topics from their assigned users
	 * 
	 */
	public function get_user_topic_table_markup(){
			
		$html = '';
		
		//only logged in users can acesss the reply form
		if(is_user_logged_in()){
				
			$user_id = get_current_user_id();
			//check user (member or practitioner)
			$subscription_level = rcp_get_subscription_id($user_id);
			
			if($subscription_level == 1 || $subscription_level == 2){
				//Get the post new topic form
				$html .= $this->get_new_topic_form_markup();
			}
		}
		
		$topic_args = array(
			'post_type'		=> 'el_topics',
			'posts_per_page'=> -1,
			'post_status'	=> 'publish',
			'orderby'		=> 'date',
			'order'			=> 'DESC'
		);
		
		if(is_user_logged_in()){
			$user_id = get_current_user_id();
			
			//check user (member or practitioner)
			$subscription_level = rcp_get_subscription_id($user_id);
			//member 
			if($subscription_level == 1 || $subscription_level == 2){
				$topic_args['author'] = $user_id;
			}
			//practitioner
			else if($subscription_level == 3){
				$topic_args['meta_key'] = 'topic_assigned_practitioner';
				$topic_args['meta_value'] = $user_id;
			}
		}
			
		$topics = get_posts($topic_args);
		if($topics){
				
			$html .= '<div class="el-topic-table" id="topic-table">';
			foreach($topics as $topic){
				
				//single topic row
				$html .= $this->get_single_topic_table_markup($topic->ID);
			}
			$html .= '</div>';
		}else{
			//print notice for user
			$html .= '<div class="el-notice el-notice-warning">';
				$html .= '<strong>There are currently no topics to display</strong>';
			$html .= '</div>';
			
			//print the shell of the topic table (so new topics can be appended)
			$html .= '<div class="el-topic-table" id="topic-table"></div>';
		}
		
		return $html;
	}
	
	
	/*
	 * Get the topic markup for a single category page (showing all applicable topics)
	 * 
	 * Used on single taxonomy page in child theme
	 */
	public function get_category_topic_table_markup($category_id = null){
		
		$html = ''; 
		
		if($category_id){
			
			
			//only logged in users can acesss the reply form
			if(is_user_logged_in()){
					
				$user_id = get_current_user_id();
				//check user (member or practitioner)
				$subscription_level = rcp_get_subscription_id($user_id);
				
				if($subscription_level == 1 || $subscription_level == 2){
					//Get the post new topic form
					$html .= $this->get_new_topic_form_markup();
				}
			}	
			
			//get a listing of topic categories to display as quick links
			$html .= $this->get_topic_category_list();

			$topic_args = array(
				'post_type'		=> 'el_topics',
				'posts_per_page'=> -1,
				'post_status'	=> 'publish',
				'orderby'		=> 'date',
				'order'			=> 'DESC',
				'tax_query'	=> array(
					array(
						'taxonomy'	=> 'el_topic_category',
						'field'		=> 'ID',
						'terms'		=> $category_id
					)
				)
			);
			$topics = get_posts($topic_args);
			if($topics){
					
				$html .= '<div class="el-topic-table" id="topic-table">';
				foreach($topics as $topic){
					
					//single topic row
					$html .= $this->get_single_topic_table_markup($topic->ID);
				}
				$html .= '</div>';
			}
			
		}else{
			$html .= '<strong>No ID supplied to get_category_topic_table_markup</strong>';
		}
		
		
		return $html;
	}
	
	/**
	 * Displays a table of topics for a selected category id
	 * 
	 * Used on single taxonomy page to show all applicable topics sorted into categories
	 */
	public static function display_category_topic_table_markup($category_id){
		$instance = self::getInstance();
		
		$html = '';
		$html .= $instance->get_category_topic_table_markup($category_id);
		
		echo $html;
	}
	
	//displays the topic table (all topics along with the new topic form)
	public static function display_topic_table_markup(){
		$instance = self::getInstance();
		
		$html = $instance->get_topic_table_markup();
		
		echo $html;
	}
	
	/**
	 * Get the form markup for the new topic form.
	 * 
	 * Lets users post topics that can be public for interation or private (if a member has signed up and selected a practitioner)
	 */
	public function get_new_topic_form_markup(){

		$html = '';
		
		//main wrapper
		$html .= '<div class="el-form-wrapper">';

			$html .= '<h3>Post a new topic</h3>';
			$html .= '<p>You can use the form below to post your new topic for discussion</p>';
			//topic form 
			$html .= '<div class="el-new-topic-form el-form">';
				$html .= '<form method="post" action="" id="post-topic-form">';
					//title
					$html .= '<div class="form-field">';
						$html .= '<label for="topic-title">Topic Title</label>';
						$html .= '<input type="text" id="topic_title" name="topic_title" class=""/>';
					$html .= '</div>';
					//content
					$html .= '<div class="form-field">';
						$html .= '<label for="topic_content">Content</label>';
						$html .= '<textarea id="topic_content" name="topic_content" class=""/></textarea>';
					$html .= '</div>';
					
					//category
					$category_args = array(
						'hide_empty' => false
					);
					$categories = get_terms('el_topic_category', $category_args);
					if($categories){
						$html .= '<div class="form-field">';
						$html .= '<label for="topic_category">Topic Category</label>';
						$html .= '<select name="topic_category" id="topic_category">';
						foreach($categories as $category){
							$category_id = $category->term_id;
							$category_name = $category->name;
							$html .= '<option value="' . $category_id . '">' . $category_name . '</option>';
						}
						$html .= '</select>';
						$html .= '</div>';
					}
					
					//public or private topics
					$html .= '<div class="form-field">';
						$html .= '<div class="field-help">Depending on your membership level you can optionally message a nutritionist directly</div>';		
						$html .= '<div>';
							$html .= '<input type="radio" name="topic_privacy" id="topic_privacy_public" value="public" checked/>';
							$html .= '<label for="topic_privacy_public">Public Topic</label>';
						$html .= '</div>';
						
						//users with an assigned practitioner can message them directly
						if(is_user_logged_in()){
							$user_id = get_current_user_id();
							
							//get subscription level
							$subscription_level = rcp_get_subscription_id($user_id);
							//paid member
							if($subscription_level == 2){
								//
								if($this->user_has_assigned_practitioner($user_id)){
									$practitioner_id = $this->get_user_assigned_practitioner($user_id);
									$html .= '<div>';
										$html .= '<input type="radio" name="topic_privacy" id="topic_privacy_private" value="private"/>';
										$html .= '<label for="topic_privacy_private">Private Practitioner Topic</label>';
										$html .= '<input type="hidden" name="topic_assigned_practitioner" id="topic_assigned_practitioner" value="' . $practitioner_id . '"/>';
									$html .= '</div>';								
								}
								//paid member but no practitioner yet
								else{
									$html .= '<div class="el-notice el-notice-warning">';
										$html .= '<p>You have not chosen a dietition yet so you cant create private topics</p>';
										$html .= '<p><a class="button primary-button" href="' . get_site_url() . '/dashboard/dietitions">View Dietitions</a></p>';
									$html .= '</div>';
								}	
							}
						}

					$html .= '</div>';
					
					
					//submit (post ajax)
					$html .= '<div class="form-field">';
						$html .= '<input type="submit" class="button secondary-button" data-button-action="post-new-topic" value="Post Topic"/>';
					$html .= '</div>';
					
				$html .= '</form>';
				
			$html .= '</div>';
		$html .= '</div>';
		
		
		
		return $html;
	}
	
	//displays the markup for the new topic form to display to users
	//TODO maybe delete?
	public static function display_new_topic_form(){
		
		$instance = self::getInstance();
		
		$html = '';
		
		echo $html;
	}
	
	//gets the markup for the reply form (used within topics)
	//TODO: Remove hard coded value to topic ID
	public static function get_reply_form_markup($topic_id){
		
	
		$instance = self::getInstance();
		
		$html = '';
		$is_admin = false;
		$can_post_reply = false;
		
		$topic = get_post($topic_id);
		
		//logged in
		
		if(wp_get_current_user() != 0){
			
			$user_id = get_current_user_id();
			$userdata = get_userdata($user_id);
			
		
			// //if admin, can post reply anyway
			// foreach($userdata->roles as $role){
				// if($role == 'administrator'){
					// $is_admin = true;	
					// $can_post_reply = true;
				// }
			// }
			
			//check if current user is author
			$topic_author_id = $topic->post_author;
			if($topic_author_id == $user_id){
				$can_post_reply = true;
			}
			
			//logged in but not admin
			if($is_admin != true){
				$subscription_level = rcp_get_subscription_id($user_id);
				
				//practitioner (can reply to users)
				if($subscription_level == 3){
					$can_post_reply = true;
				}
				
			}
		}else{
			$html .= '<p>You are currently not logged in, please log in first to post a reply</p>';
		}
		
		
		//if we can post a reply
		if($can_post_reply){
			
			if($topic_id){
				
				//topic form 
				$html .= '<div class="el-form-wrapper">';
					$html .= '<h3>Post a reply</h3>';
					$html .= '<div class="el-post-reply-form el-form">';
						$html .= '<form method="post" action="" id="post-reply-form">';
							//content
							$html .= '<div class="form-field">';
								$html .= '<label for="reply_content">Enter your reply</label>';
								$html .= '<textarea id="reply_content" name="reply_content" class=""/></textarea>';
								$html .= '<input type="hidden" name="reply_topic_id" id="reply_topic_id" value="' . $topic_id .'"/>';
							$html .= '</div>';
							//submit (post ajax)
							$html .= '<div class="form-field">';
								$html .= '<input type="submit" class="button secondary-button" value="Submit Reply"/>';
							$html .= '</div>';
							
						$html .= '</form>';
						
					$html .= '</div>';
				$html .= '</div>';
				
				
			}else{
				$html .= '<b>Error: no topic ID set</b>';
			}
			
		}else{
			$html .= '<div class="el-notice el-notice-warning">';
				$html .= 'Sorry, only practitioners are authorised to post a reply';
			$html .= '</div>';
		}
		
		

		
		
		
		return $html;
		
	}

	//displays the reply form (for replying to topics)
	public static function display_reply_form_markup(){
		$instance = self::getInstance();
		
		$html = $instance->get_reply_form_markup();
		
		echo $html;
	}
	
	
	//gets the markup for a table of replies on a single topic
	public static function get_reply_table_markup($topic_id){
			
		$instance = self::getInstance();
		$html = '';
		
		if($topic_id){
				
			//determine how many replies this topic has
			$post_args = array(
				'meta_key'		=> 'topic_id',
				'meta_value'	=> $topic_id,
				'post_status'	=> 'publish',
				'post_type'		=> 'el_replies',
				'orderby'		=> 'date',
				'order'			=> 'ASC'
			);
			$posts = get_posts($post_args);
					
			$html .= '<div class="reply-table" id="reply-table" data-topic-id="' . $topic_id .'">';
				//get all posts
				if($posts){
					foreach($posts as $post){
						$html .= $instance->get_single_reply_table_markup($post->ID);
					}
				}
			$html .= '</div>';
			
		}
		
		echo $html;
	}
	
	//outputs a list of all replies to a single topic, displayed above the reply form
	public static function display_reply_table_markup($topic_id){
			
		$instance = self::getInstance();
		
		$html = '';
		
		if($topic_id){
			$html .= $instance->get_reply_table_markup($topic_id);
		}
		
		echo $html;
	}
	
	
	//register a new admin setting page for the plugin
	public function register_messaging_system_settings_menu(){
		
		add_menu_page(
			__('Messaging System Settings', 'elevate-nutrition-system'),
			__('Messaging System Settings', 'elevate-nutrition-system'),
			'manage_options',
			'messaging_system_settings',
			array($this, 'display_messaging_sytem_settings_menu'),
			'dashicons-format-chat',
			60
		);
	}
	
	//registers the setting sections for use in sytstem
	public function register_messaging_system_settings_sections(){
		
		//adds a section to hold all the page settings
		add_settings_section(
			'messaging_system_pages', //ID
			'Messaging System Pages', //title (section))
			false, //display function, not needed (just display fields)
			'messaging_system_settings' //page (setting page)
		);
		
		//add setting 
		register_setting('messaging_system_pages','el_messaging_dashboard_page_id');
		register_setting('messaging_system_pages','el_messaging_login_page_id');
		
		//add field
		add_settings_field(
			'el_messaging_dashboard_page_id', //ID
			'Dashboard Page',  //title
			array($this, 'render_admin_setting_field'),  //display function
			'messaging_system_settings', //page (setting page)
			'messaging_system_pages', //section (setting section)
			array(
				'id'	=> 'el_messaging_dashboard_page_id',
				'type'	=> 'page-select'
			)
		);
		
			
	}
	
	//renders the setting fields
	//TODO: Expand to handle more field types going forward
	public function render_admin_setting_field($args){
		
		$html = '';
		
		//conditionally render the field based on type and id
		if($args['type'] == 'page-select'){
			
			//get a listing of all pages on the website
			
			
			$html .= '<select name="' . $args['id'] . '" id="' . $args['id'] . '">';
				$
			$html .= '</select>';	
		}
		
		echo $html;
	}

	
	//builds the output for the messaging system options page
	public function display_messaging_sytem_settings_menu(){
		?>		

		<div class="wrap">'
			<h1><?php echo __('Messaging System Settings', 'elevate-nutrition-system'); ?></h1>
			<form method="post" action="options.php">
				<?php
				
				 //add_settings_section callback is displayed here. For every new section we need to call settings_fields.
				settings_fields('messaging_system_pages'); 
				
				//all the add_settings_field callbacks is displayed here
				do_settings_sections('messaging_system_settings');
				
				submit_button(); 
				?>
				
			</form>
		</div>

	<?php
	}
	
	//checks that the current user has access to the requested resource (page, topic, reply etc)
	public function check_user_access(){
		
		$queried_object = get_queried_object();
		
		
		//check access to single post 
		if($queried_object instanceof WP_Post){
			
			$post_type = get_post_type($queried_object);
			
			//on topic or reply cct
			if($post_type == 'el_topics' || $post_type == 'el_replies'){
				
				$current_user_id = get_current_user_id();
				
				//current user not logged in, redirect to homepage
				if($current_user_id == 0){
					$url = get_site_url();
					wp_redirect( $url );
					exit;
				}
				
			}
			
			
		}
	}
	
		
	/**
	 * Gets the larger profile form for the user, lets them set additional information about themselves.
	 * Also used for the practitioners to collect new information about themselves.
	 * 
	 * Used to display user information (and collect form input)
	 */
	public function get_user_profile_form($user_id = null){
			
		$html = '';
		
		$profile_id = '';
		
		//if not user_id passed, try and get the currently logged in user
		if(is_null($user_id)){
			if(is_user_logged_in()){
				$user_id = get_current_user_id();
			}
		}
		
		
		//Determine user level so we can show the applicable form
		
		$user_subscription_id = rcp_get_subscription_id($user_id);
		
		$user_type = '';
		if($user_subscription_id == '1' || $user_subscription_id == '2'){
			$user_type = 'user';
		}
		if($user_subscription_id == '3'){
			$user_type = 'practitioner';
		}
		
		
		
		
		//Display applicable form
		if($user_type == 'user'){
			
			//Check if we submitted the form, if so print a notice
			if(isset($_POST['rcp_user_submit_profile_form'])){
				$url = get_site_url(null,'dashboard');
				$html .= '<div class="el-notice el-notice-success">';
					$html .= '<p>You have successfully updated your profile.</p>';
					$html .= '<a class="button primary-buton" href="' . $url .'">Please click here to go to the dashboard</a>';
				$html .= '</div>';
			}
			
			//BUILD the tab bar at the top
			$html .= '<div class="user-progress-bar">';
				$html .= '<div class="element current"></div>';
				$html .= '<div class="element"></div>';
				$html .= '<div class="element"></div>';
				$html .= '<div class="element"></div>';
				$html .= '<div class="element"></div>';
				$html .= '<div class="element"></div>';
				$html .= '<div class="element"></div>';
			$html .= '</div>';
			

			
			$html .= '<form name="user_extended_profile" id="user_extended_profile" method="post" class="profile-form" action="" enctype="multipart/form-data">';
			
				//USERID
				$html .= '<input type="hidden" name="rcp_user_id" id="rcp_user_id" value="' . $user_id .'"/>';
			
			
				//PURPOSE
				$html .= '<div class="form-section current" id="form-profile-image">';
					$html .= '<h3>What is your main goal today?</h3>';	
					
					//overall goal
					$html .= '<p class="form-field inline">';
						$rcp_user_overall_goal = ($user_id) ? get_user_meta($user_id, 'rcp_user_overall_goal', true) : 'no'; 
						
						$html .= '<span>What is your main reason for joining?</span>';
						//get Fit
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_overall_goal == 'get_fit' || empty($rcp_user_overall_goal)){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_overall_goal" id="rcp_user_overall_goal_get_fit" type="radio" value="get_fit" ' . $checked .'/>';
							$html .= '<label for="rcp_user_overall_goal_get_fit">Get Fit</label>';
						$html .= '</span>';
						//lose weight
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_overall_goal == 'lose_weight'){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_overall_goal" id="rcp_user_overall_goal_lose_weight" type="radio" value="lose_weight" ' . $checked .'/>';
							$html .= '<label for="rcp_user_overall_goal_lose_weight">Lose Weight</label>';
						$html .= '</span>';
						//get Healthy
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_overall_goal == 'get_healthy'){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_overall_goal" id="rcp_user_overall_goal_get_healthy" type="radio" value="get_healthy" ' . $checked .'/>';
							$html .= '<label for="rcp_user_overall_goal_get_healthy">Get healthy</label>';
						$html .= '</span>';
					$html .= '</p>';
					
					
					$html .= '<div class="form-field">';
						$html .= '<span class="button secondary-button form-button small-button form-next-button">Next</span>';
						$html .= '<input type="submit" class="primary-button" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</div>';
					
				$html .= '</div>';
			
			
				//FILE UPLOADS
				$html .= '<div class="form-section" id="form-profile-image">';
					$html .= '<h3>Profile Image</h3>';	
					$html .= '<p class="form-field full image-upload">';
		
					$rcp_user_profile_image = get_user_meta($user_id, 'rcp_user_profile_image', true);
					$user_profile_image = get_post($rcp_user_profile_image);
					if($user_profile_image instanceof WP_Post){
						$profile_thumbnail_url = wp_get_attachment_image_src($user_profile_image->ID, 'thumbnail', false)[0];
						$html .= '<div class="profile-image-wrap">';
							$html .= '<img src="' . $profile_thumbnail_url . '" class="profile-image"/>';
						$html .= '</div>';
					}else{
						$html .= '<div class="el-notice el-notice-warning">';
							$html .= 'Your assigned image is missing. Please re-upload the image';
						$html .= '</div>';
					}
						$html .= '<label style="display: block;" for="rcp_user_profile_image">Chose your profile image</label>';
						$html .= '<input type="file" name="rcp_user_profile_image" id="rcp_user_profile_image">';
					$html .= '</p>';
					
					$html .= '<div class="form-field">';
						$html .= '<span class="button secondary-button form-button small-button form-next-button">Next</span>';
						$html .= '<input type="submit" class="primary-button" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</div>';
					
				$html .= '</div>';
					
				//TODO: MOVE TO A NEW SECTION AWAY FROM FORM
				//Check if the user has an assigned practitioner already
				// if($user_id){
					// $user_has_practitioner = $this->user_has_assigned_practitioner($user_id); 
					// if($user_has_practitioner == true){
						// $practitioner_id = $this->get_user_assigned_practitioner($user_id);
	// 					
						// $html .= '<h3>Your Practitioner</h3>';
						// $html .= '<div class="el-notice el-notice-warning">';
							// $html .= 'You already have a practitioner assigned to you. Practitioner #' . $practitioner_id . ' has been assigned. Click here to view them';
						// $html .= '</div>';
					// }
				//}
		
				
				//GENERAL HEALTH INFORMATION
				$html .= '<div class="form-section" id="form-general-health">';
					$html .= '<h3>General Health Information</h3>';	
			
					//lightest weight
					$html .= '<p class="form-field">';
						
						$rcp_user_lightest_weight = ($user_id) ? get_user_meta($user_id, 'rcp_user_lightest_weight', true) : ''; 
						$html .= '<label for="rcp_user_lightest_weight">Lightest you have been post 18 years old (in KG)</label>';
						$html .= '<input name="rcp_user_lightest_weight" id="rcp_user_lightest_weight" type="number" value="' . $rcp_user_lightest_weight .'" min="30" max="250" step="1" size="3"/>';
					$html .= '</p>';
					
					//heaviest weight
					$html .= '<p class="form-field">';
						$rcp_user_heaviest_weight = ($user_id) ? get_user_meta($user_id, 'rcp_user_heaviest_weight', true) : ''; 
						$html .= '<label for="rcp_user_heaviest_weight">Heaviest you have been post 18 years old (in KG)</label>';
						$html .= '<input name="rcp_user_heaviest_weight" id="rcp_user_heaviest_weight" type="number" value="' . $rcp_user_heaviest_weight .'" min="40" max="250" step="1" size="3"/>';
					$html .= '</p>';
					
					//height
					$html .= '<p class="form-field" >';
						$rcp_user_height = ($user_id) ? get_user_meta($user_id, 'rcp_user_height', true) : ''; 
						$html .= '<label for="rcp_user_height">Current Height (cm)</label>';
						$html .= '<input name="rcp_user_height" id="rcp_user_height" type="number" value="' . $rcp_user_height .'" min="68" max="250" step="1" size="3"/>';
					$html .= '</p>';
					
					//days gym
					$html .= '<p class="form-field">';
						$rcp_user_days_gym = ($user_id) ? get_user_meta($user_id, 'rcp_user_days_gym', true) : '1'; 
						
						$html .= '<label for="rcp_user_days_gym">How many days a week do you exercise?</label>';
						$html .= '<select name="rcp_user_days_gym" id="rcp_user_days_gym" value="' . $rcp_user_days_gym . '">';
							$html .= '<option value="1" ' . ($rcp_user_days_gym == '1' ? 'selected' : '') . '>1 Day</option>';
							$html .= '<option value="2" ' . ($rcp_user_days_gym == '2' ? 'selected' : '') . '>2 Day</option>';
							$html .= '<option value="3" ' . ($rcp_user_days_gym == '3' ? 'selected' : '') . '>3 Day</option>';
							$html .= '<option value="4" ' . ($rcp_user_days_gym == '4' ? 'selected' : '') . '>4 Day</option>';
							$html .= '<option value="5" ' . ($rcp_user_days_gym == '5' ? 'selected' : '') . '>5 Day</option>';
							$html .= '<option value="6" ' . ($rcp_user_days_gym == '6' ? 'selected' : '') . '>6 Day</option>';
							$html .= '<option value="7" ' . ($rcp_user_days_gym == '7' ? 'selected' : '') . '>7 Day</option>';
						$html .= '</select>';
					$html .= '</p>';
					
					//gym membership
					$html .= '<p class="form-field inline">';
						$rcp_user_member_of_gym = ($user_id) ? get_user_meta($user_id, 'rcp_user_member_of_gym', true) : 'no'; 
						
						$html .= '<span>Do you have a gym membership?</span>';
						//no
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_member_of_gym == 'no' || empty($rcp_user_member_of_gym)){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_member_of_gym" id="rcp_user_member_of_gym_no" type="radio" value="no" ' . $checked .'/>';
							$html .= '<label for="rcp_user_member_of_gym_no">No</label>';
						$html .= '</span>';
						//yes
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_member_of_gym == 'yes'){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_member_of_gym" id="rcp_user_member_of_gym_yes" type="radio" value="yes" ' . $checked .'/>';
							$html .= '<label for="rcp_user_member_of_gym_yes">Yes</label>';
						$html .= '</span>';
					$html .= '</p>';
					
					//exercise preference (checkbox)
					$html .= '<p class="form-field inline">';
						
						$rcp_user_exercise_preference = ($user_id) ? get_user_meta($user_id, 'rcp_user_exercise_preference', true) : ''; 
						//json values
						if(!empty($rcp_user_exercise_preference)){
							$rcp_user_exercise_preference = json_decode($rcp_user_exercise_preference);
							$rcp_user_exercise_preference = (array_flip($rcp_user_exercise_preference));
						}
						$html .= '<span>What type of exercise do you prefer?</span>';
						//in a gym
						$html .= '<span class="wrap">';	
							$html .= '<label for="rcp_user_exercise_preference_in_a_gym">In a Gym</label>';
							$html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_in_a_gym" type="checkbox" value="in_a_gym" ' . ( isset($rcp_user_exercise_preference['in_a_gym']) ? 'checked' : '') .'/>';
						$html .= '</span>';
						//outside
						$html .= '<span class="wrap">';	
							$html .= '<label for="rcp_user_exercise_preference_outside">Outside</label>';
							$html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_outside" type="checkbox" value="outside" ' . ( isset($rcp_user_exercise_preference['outside']) ? 'checked' : '') .'/>';
						$html .= '</span>';
						//at home
						$html .= '<span class="wrap">';	
							$html .= '<label for="rcp_user_exercise_preference_at_home">At Home</label>';
							$html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_at_home" type="checkbox" value="at_home" ' . ( isset($rcp_user_exercise_preference['at_home']) ? 'checked' : '') .'/>';
						$html .= '</span>';
						//other
						$html .= '<span class="wrap">';	
							$html .= '<label for="rcp_user_exercise_preference_other">Other</label>';
							$html .= '<input name="rcp_user_exercise_preference[]" id="rcp_user_exercise_preference_other" type="checkbox" value="other" ' . ( isset($rcp_user_exercise_preference['other']) ? 'checked' : '') .'/>';	
						$html .= '</span>';
					$html .= '</p>';
					
					//how long exercise section
					$html .= '<p class="form-field">';
						$rcp_user_exercise_time = ($user_id) ? get_user_meta($user_id, 'rcp_user_exercise_time', true) : ''; 
						$html .= '<label for="rcp_user_exercise_time">Average Exercise Time Per Day(Minutes)</label>';
						$html .= '<input name="rcp_user_exercise_time" id="rcp_user_exercise_time" type="number" value="' . $rcp_user_exercise_time .'" min="0" max="600" step="1" size="3"/>';
					$html .= '</p>';
					
					//additional exercise info
					$html .= '<p class="form-field full">';
						$rcp_user_exercise_extra = ($user_id) ? get_user_meta($user_id, 'rcp_user_exercise_extra', true) : ''; 
						$html .= '<label for="rcp_user_exercise_extra ">Any extra exercise info (e.g intensity, location, type)</label>';
						$html .= '<textarea name="rcp_user_exercise_extra" id="rcp_user_exercise_extra" maxlength="400">' . $rcp_user_exercise_extra . '</textarea>';
					$html .= '</p>';
					
					$html .= '<p class="form-field full">';
						$html .= '<span class="button secondary-button form-button small-button form-previous-button">Previous</span>';
						$html .= '<span class="button secondary-button form-button small-button form-next-button">Next</span>';
						$html .= '<input type="submit" class="primary-button" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</p>';
					
				$html .= '</div>';
				
				//MEDICAL INFORMATION
				$html .= '<div class="form-section" id="form-medical">';
					$html .= '<h3>Medical Information</h3>';	
					//medical history
					$html .= '<p class="form-field full">';
						$rcp_user_medical_history = ($user_id) ? get_user_meta($user_id, 'rcp_user_medical_history', true) : ''; 
						$html .= '<label for="rcp_user_medical_history ">Do you have any other information medically relevant to your Dietition (even if you think it is not significant)</label>';
						$html .= '<textarea name="rcp_user_medical_history" id="rcp_user_medical_history" maxlength="400">' . $rcp_user_medical_history . '</textarea>';
					$html .= '</p>';
					
					$html .= '<p class="form-field full">';
						$html .= '<span class="button secondary-button form-button small-button form-previous-button">Previous</span>';
						$html .= '<span class="button secondary-button form-button small-button form-next-button">Next</span>';
						$html .= '<input type="submit" class="primary-button" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</p>';
					
				$html .= '</div>';
				
				//SOCIAL HISTORY
				$html .= '<div class="form-section" id="form-social-history">';
					$html .= '<h3>Social History</h3>';
					
					//occupation
					$html .= '<p class="form-field">';
						$rcp_user_occupation = ($user_id) ? get_user_meta($user_id, 'rcp_user_occupation', true) : ''; 
						$html .= '<label for="rcp_user_occupation">What is your occupation (eg, plumber, full time student, full time mum/dad etc)</label>';
						$html .= '<input name="rcp_user_occupation" id="rcp_user_occupation" type="text" value="' . $rcp_user_occupation .'" maxlength="150"/>';
					$html .= '</p>';
					
					//total work hours
					$html .= '<p class="form-field">';
						$rcp_user_work_hours = ($user_id) ? get_user_meta($user_id, 'rcp_user_work_hours', true) : ''; 
						$html .= '<label for="rcp_user_work_hours">How many hours a week do you work on average</label>';
						$html .= '<input name="rcp_user_work_hours" id="rcp_user_work_hours" type="number" value="' . $rcp_user_work_hours .'" min="1" max="100" step="1" size="3"/>';
					$html .= '</p>';
					
					//main income earner
					$html .= '<p class="form-field inline">';
						$rcp_user_main_income_earner = $user_id ? get_user_meta($user_id, 'rcp_user_main_income_earner', true) : 'no'; 
						$html .= '<span>Are you the main income earner for your household?</span>';
						//no
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_main_income_earner == 'no' || empty($rcp_user_main_income_earner)){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_main_income_earner" id="rcp_user_main_income_earner_no" type="radio" value="no" ' . $checked .'/>';
							$html .= '<label for="rcp_user_main_income_earner_no">No</label>';
						$html .= '</span>';
						//yes
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_main_income_earner == 'yes'){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_main_income_earner" id="rcp_user_main_income_earner_yes" type="radio" value="yes" ' . $checked .'/>';
							$html .= '<label for="rcp_user_main_income_earner_yes">Yes</label>';
						$html .= '</span>';
						
					$html .= '</p>';
					
					//family kids
					$html .= '<p class="form-field full">';
						$rcp_user_family_information = ($user_id) ? get_user_meta($user_id, 'rcp_user_family_information', true) : ''; 
						$html .= '<label for="rcp_user_family_information">If you have children, please enter how many and their ages</label>';
						$html .= '<textarea name="rcp_user_family_information" id="rcp_user_exercise_extra" maxlength="400">' . $rcp_user_family_information . '</textarea>';
					$html .= '</p>';
					
					//smoking
					$html .= '<p class="form-field inline">';
						$rcp_user_smoker = ($user_id) ? get_user_meta($user_id, 'rcp_user_smoker', true) : 'no'; 
						$html .= '<span>Are you a smoker?</span>';
						//no
						$html .= '<span class="wrap">';
							$checked = '';
							if($rcp_user_smoker == 'no' || empty($rcp_user_smoker)){
								$checked = 'checked';
							}		
							$html .= '<input name="rcp_user_smoker" id="rcp_user_smoker_no" type="radio" value="no" ' . $checked .'/>';
							$html .= '<label for="rcp_user_smoker_no">No</label>';
						$html .= '</span>';	
						//yes
						$html .= '<span class="wrap">';		
							$checked = '';
							if($rcp_user_smoker == 'yes'){
								$checked = 'checked';
							}		
							$html .= '<input name="rcp_user_smoker" id="rcp_user_smoker_yes" type="radio" value="yes" ' . $checked.'/>';
							$html .= '<label for="rcp_user_smoker_yes">Yes</label>';
						$html .= '</span>';	
					$html .= '</p>';
					
					//smoker amount
					$html .= '<p class="form-field">';
						$rcp_user_smoker_amount = ($user_id) ? get_user_meta($user_id, 'rcp_user_smoker_amount', true) : 'no'; 
						$html .= '<label for="rcp_user_smoker_amount">If you smoke, how many would you smoke per week?</label>';
						$html .= '<input name="rcp_user_smoker_amount" id="rcp_user_smoker_amount" type="number" value="' . $rcp_user_exercise_time .'" min="1" max="300" step="1" size="3"/>';
					$html .= '</p>';
					
					//drinker
					$html .= '<p class="form-field inline">';
						$html .= '<span>Do you drink?</span>';
						//no
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_drinker == 'no' || empty($rcp_user_drinker)){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_drinker" id="rcp_user_drinker_no" type="radio" value="no" ' . $checked .'/>';
							$html .= '<label for="rcp_user_drinker_no">No</label>';
						$html .= '</span>';
						//yes
						$html .= '<span class="wrap">';		
							$checked = '';
							if($rcp_user_drinker == 'yes'){
								$checked = 'checked';
							}	
							$html .= '<input name="rcp_user_drinker" id="rcp_user_drinker_yes" type="radio" value="yes" ' . $checked .'/>';
							$html .= '<label for="rcp_user_drinker_yes">Yes</label>';
						$html .= '</span>';
					$html .= '</p>';
					
					//drinker amount
					$html .= '<p class="form-field">';
						$rcp_user_drinker_amount = ($user_id) ? get_user_meta($user_id, 'rcp_user_drinker_amount', true) : 'no'; 
						$html .= '<label for="rcp_user_drinker_amount">If you drink, how many standard drinks would you have per week?</label>';
						$html .= '<input name="rcp_user_drinker_amount" id="rcp_user_drinker_amount" type="number" value="' . $rcp_user_drinker_amount .'" min="1" max="300" step="1" size="3"/>';
					$html .= '</p>';
					
					//drinker action
					$html .= '<p class="form-field inline">';
						$rcp_user_drinker_action = ($user_id) ? get_user_meta($user_id, 'rcp_user_drinker_action', true) : 'nothing'; 
						$html .= '<span>Which of the following would you be willing to do?</span>';
						//nothing
						$html .= '<span class="wrap">';		
							$checked = '';
							if($rcp_user_drinker_action == 'nothing' || empty($rcp_user_drinker_action)){
								$checked = 'checked';
							}		
							$html .= '<input name="rcp_user_drinker_action" id="rcp_user_drinker_action_nothing" type="radio" value="nothing" ' . $checked .'/>';
							$html .= '<label for="rcp_user_drinker_action_nothing">No Change</label>';
						$html .= '</span>';
						//stop drinking
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_drinker_action == 'stop_drinking'){
								$checked = 'checked';
							}	
							$html .= '<input name="rcp_user_drinker_action" id="rcp_user_drinker_action_stop_drinking" type="radio" value="stop_drinking" ' . $checked .'/>';
							$html .= '<label for="rcp_user_drinker_action_stop_drinking">Stop Drinking</label>';
						$html .= '</span>';
						//reduce drinking
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_drinker_action == 'reduce_drinking'){
								$checked = 'checked';
							}	
							$html .= '<input name="rcp_user_drinker_action" id="rcp_user_drinker_action_reduce_drinking" type="radio" value="reduce_drinking" ' . $checked .'/>';
							$html .= '<label for="rcp_user_drinker_action_reduce_drinking">Reduce Drinking</label>';
						$html .= '</span>';
						
					$html .= '</p>';
					
					//social outings good
					$html .= '<p class="form-field inline">';
						$rcp_user_social_outing_food = ($user_id) ? get_user_meta($user_id, 'rcp_user_social_outing_food', true) : 'no'; 
						
						
						
						$html .= '<span>Do your social outings generall include food?</span>';
						//no
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_social_outing_food == 'no' || empty($rcp_user_social_outing_food)){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_social_outing_food" id="rcp_user_social_outing_food_no" type="radio" value="no" ' . $checked .'/>';
							$html .= '<label for="rcp_user_social_outing_food_no">No</label>';
						$html .= '</span>';
						//yes
						$html .= '<span class="wrap">';	
							$checked = '';
							if($rcp_user_social_outing_food == 'yes'){
								$checked = 'checked';
							}
							$html .= '<input name="rcp_user_social_outing_food" id="rcp_user_social_outing_food_yes" type="radio" value="yes" ' . $checked .'/>';
							$html .= '<label for="rcp_user_social_outing_food_yes">Yes</label>';
						$html .= '</span>';
					$html .= '</p>';
					
					//Bought meals per week
					$html .= '<p class="form-field">';
						$rcp_user_bought_meals_per_week = ($user_id) ? get_user_meta($user_id, 'rcp_user_bought_meals_per_week', true) : ''; 		
						$html .= '<label for="rcp_user_bought_meals_per_week">How many meals per week are bought on the go or from a shop ready made? (this is any time of the day)</label>';
						$html .= '<input name="rcp_user_bought_meals_per_week" id="rcp_user_bought_meals_per_week" type="number" value="' . $rcp_user_bought_meals_per_week .'" min="1" max="60" step="1" size="3"/>';
					$html .= '</p>';
					
					//dinners at home
					$html .= '<p class="form-field">';
						$rcp_user_cooked_dinners_per_week = ($user_id) ? get_user_meta($user_id, 'rcp_user_cooked_dinners_per_week', true) : ''; 		
						$html .= '<label for="rcp_user_bought_meals_per_week">How many dinners per week are home cooked?</label>';
						$html .= '<input name="rcp_user_cooked_dinners_per_week" id="rcp_user_cooked_dinners_per_week" type="number" value="' . $rcp_user_cooked_dinners_per_week .'" min="1" max="60" step="1" size="3"/>';
					$html .= '</p>';
					
					//who cooks at home
					$html .= '<p class="form-field">';
						$rcp_user_who_cooks  = ($user_id) ? get_user_meta($user_id, 'rcp_user_who_cooks', true) : ''; 
					
						$html .= '<label for="rcp_user_who_cooks">Who does the cooking at home?(put down your relation to them, eg, wife, or myself etc)</label>';
						$html .= '<input name="rcp_user_who_cooks" id="rcp_user_who_cooks" type="text" value="' . $rcp_user_who_cooks .'" maxlength="150"/>';
					$html .= '</p>';
					
					//who shops at home
					$html .= '<p class="form-field">';
						$rcp_user_who_shops  = ($user_id) ? get_user_meta($user_id, 'rcp_user_who_shops', true) : ''; 
						$html .= '<label for="rcp_user_who_shops">Who does the shopping usually?</label>';
						$html .= '<input name="rcp_user_who_shops" id="rcp_user_who_shops" type="text" value="' . $rcp_user_who_shops .'" maxlength="150"/>';
					$html .= '</p>';
					
					//social extra
					$html .= '<p class="form-field full">';
						$rcp_user_social_extra  = ($user_id) ? get_user_meta($user_id, 'rcp_user_social_extra', true) : ''; 
						$html .= '<label for="rcp_user_social_extra">Any other information you would like to note about your social history?</label>';
						$html .= '<textarea name="rcp_user_social_extra" id="rcp_user_social_extra" maxlength="400">' . $rcp_user_social_extra . '</textarea>';
					$html .= '</p>';
					
					
					$html .= '<p class="form-field full">';
						$html .= '<span class="button secondary-button form-button small-button form-previous-button">Previous</span>';
						$html .= '<span class="button secondary-button form-button small-button form-next-button">Next</span>';
						$html .= '<input type="submit" class="primary-button" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</p>';
					
				$html .= '</div>';
				
				
				//DIET
				$html .= '<div class="form-section" id="form-diet">';
					$html .= '<h3>Diet</h3>';
					
					//disliked foods
					$html .= '<p class="form-field full">';
						$rcp_user_disliked_foods  = ($user_id) ? get_user_meta($user_id, 'rcp_user_disliked_foods', true) : ''; 
						$html .= '<label for="rcp_user_disliked_foods">What foods do you hate/will not eat at all, even if we suggested them?</label>';
						$html .= '<textarea name="rcp_user_disliked_foods" id="rcp_user_social_extra" maxlength="400">' . $rcp_user_disliked_foods . '</textarea>';
					$html .= '</p>';
					
					//liked foods
					$html .= '<p class="form-field full">';
						$rcp_user_liked_foods  = ($user_id) ? get_user_meta($user_id, 'rcp_user_liked_foods', true) : ''; 
						$html .= '<label for="rcp_user_liked_foods">What foods do you love and hope to have included in your plan?(list any but please be realistic)</label>';
						$html .= '<textarea name="rcp_user_liked_foods" id="rcp_user_liked_foods" maxlength="400">' . $rcp_user_liked_foods . '</textarea>';
					$html .= '</p>';
					
					//meal eating
					$html .= '<p class="form-field">';
						$rcp_user_days_meal = ($user_id) ? get_user_meta($user_id, 'rcp_user_days_meal', true) : '1'; 
						
						$html .= '<label for="rcp_user_days_gym">How many times a day can you realistically eat a meal?</label>';
						$html .= '<select name="rcp_user_days_meal" id="rcp_user_days_meal" value="' . $rcp_user_days_meal . '">';
							$html .= '<option value="1" ' . ($rcp_user_days_meal == '1' ? 'selected' : '') . '>1 Times</option>';
							$html .= '<option value="2" ' . ($rcp_user_days_meal == '2' ? 'selected' : '') . '>2 Times</option>';
							$html .= '<option value="3" ' . ($rcp_user_days_meal == '3' ? 'selected' : '') . '>3 Times</option>';
							$html .= '<option value="4" ' . ($rcp_user_days_meal == '4' ? 'selected' : '') . '>4 Times</option>';
							$html .= '<option value="5" ' . ($rcp_user_days_meal == '5' ? 'selected' : '') . '>5 Times</option>';
						$html .= '</select>';
					$html .= '</p>';
					
					//snack eating
					$html .= '<p class="form-field">';
						$rcp_user_days_snacks = ($user_id) ? get_user_meta($user_id, 'rcp_user_days_snacks', true) : '1'; 
						
						$html .= '<label for="rcp_user_days_gym">How many times a day can you eat a "snack"?</label>';
						$html .= '<select name="rcp_user_days_snacks" id="rcp_user_days_meal" value="' . $rcp_user_days_meal . '">';
							$html .= '<option value="1" ' . ($rcp_user_days_snacks == '1' ? 'selected' : '') . '>1 Times</option>';
							$html .= '<option value="2" ' . ($rcp_user_days_snacks == '2' ? 'selected' : '') . '>2 Times</option>';
							$html .= '<option value="3" ' . ($rcp_user_days_snacks == '3' ? 'selected' : '') . '>3 Times</option>';
							$html .= '<option value="4" ' . ($rcp_user_days_snacks == '4' ? 'selected' : '') . '>4 Times</option>';
							$html .= '<option value="5" ' . ($rcp_user_days_snacks == '5' ? 'selected' : '') . '>5 Times</option>';
						$html .= '</select>';
					$html .= '</p>';
					
					$html .= '<p class="form-field full">';
						$html .= '<span class="button secondary-button form-button small-button form-previous-button">Previous</span>';
						$html .= '<span class="button secondary-button  form-button small-button form-next-button">Next</span>';
						$html .= '<input type="submit" class="primary-button" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</p>';
					
				$html .='</div>';
				
				//ACHIEVE
				$html .= '<div class="form-section" id="form-achieve">';
					$html .= '<h3>Your Goals</h3>';
					//ultimate goal
					$html .= '<p class="form-field full">';
						$rcp_user_ultimate_goal = ($user_id) ? get_user_meta($user_id, 'rcp_user_ultimate_goal', true) : ''; 
						$html .= '<label for="rcp_user_ultimate_goal">Ultimate Goal (eg, I want to lose 60kg)</label>';
						$html .= '<textarea name="rcp_user_ultimate_goal" id="rcp_user_ultimate_goal" maxlength="400">' . $rcp_user_ultimate_goal . '</textarea>';
					$html .= '</p>';
					
					//short term goal
					$html .= '<p class="form-field full">';
						$rcp_user_short_term_goal = ($user_id) ? get_user_meta($user_id, 'rcp_user_short_term_goal', true) : ''; 
						$html .= '<label for="rcp_user_short_term_goal">Short Term Goal (eg, I want to lose 2kg)</label>';
						$html .= '<textarea name="rcp_user_short_term_goal" id="rcp_user_short_term_goal" maxlength="400">' . $rcp_user_short_term_goal . '</textarea>';
					$html .= '</p>';
					
					//biggest hurdles
					$html .= '<p class="form-field full">';
						$rcp_user_hurdles = ($user_id) ? get_user_meta($user_id, 'rcp_user_hurdles', true) : ''; 
						$html .= '<label for="rcp_user_hurdles">Biggest hurdles (in the past)</label>';
						$html .= '<textarea name="rcp_user_hurdles" id="rcp_user_hurdles" maxlength="400">' . $rcp_user_hurdles . '</textarea>';
					$html .= '</p>';
					
					//future hurdles 
					$html .= '<p class="form-field full">';
						$rcp_user_future_hurdles = ($user_id) ? get_user_meta($user_id, 'rcp_user_future_hurdles', true) : ''; 
						
						$html .= '<label for="rcp_user_hurdles">What you think your major hurdles will be during this plan if any?</label>';
						$html .= '<textarea name="rcp_user_future_hurdles" id="rcp_user_future_hurdles" maxlength="400">' . $rcp_user_future_hurdles . '</textarea>';
					$html .= '</p>';
					
					//excited!
					$html .= '<p class="form-field inline">';
						$rcp_user_excited  = ($user_id) ? get_user_meta($user_id, 'rcp_user_excited', true) : ''; 
						
						$html .= '<span>Are you excited to start your Healthy Imaging journey towards a Healthier Image of yourself?</span>';
						//no
						$html .= '<span class="wrap">';
							$html .= '<input name="rcp_user_excited" id="rcp_user_excited_no" type="radio" value="no" ' . ($rcp_user_excited == 'no' ? 'checked' : '') .'/>';
							$html .= '<label for="rcp_user_excited_no">No</label>';
						$html .= '</span>';
						//yes
						$html .= '<span class="wrap">';
							$html .= '<input name="rcp_user_excited" id="rcp_user_excited_yes" type="radio" value="yes" ' . ($rcp_user_excited == 'yes' ? 'checked' : '') .'/>';
							$html .= '<label for="rcp_user_excited_yes">Yes</label>';
						$html .= '</span>';
					$html .= '</p>';

					$html .= '<p class="form-field full">';
						$html .= '<span class="button secondary-button form-button small-button form-previous-button">Previous</span>';
						$html .= '<input type="submit" class="primary-button" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</p>';
				
				$html .= '</div>';
				
			$html .= '</form>';	
			
		}
		//PRACTITIONER
		else if($user_type == 'practitioner'){
					
				
			//Check if we submitted the form, if so print a notice
			if(isset($_POST['rcp_user_submit_profile_form'])){
				$url = get_site_url(null,'dashboard');
				$html .= '<div class="el-notice el-notice-success">';
					$html .= '<p>You have successfully updated your profile.</p>';
					$html .= '<a class="button primary-buton" href="' . $url .'">Please click here to go to the dashboard</a>';
				$html .= '</div>';
			}
					
			//BUILD the tab bar at the top
			$html .= '<div class="user-progress-bar">';
				$html .= '<div class="element current"></div>';
				$html .= '<div class="element"></div>';
				$html .= '<div class="element"></div>';
				
			$html .= '</div>';
			
			//Practitioner form
			$html .= '<form name="user_extended_profile" id="user_extended_profile" method="post" class="profile-form" action="" enctype="multipart/form-data">';
			
				//USERID
				$html .= '<input type="hidden" name="rcp_user_id" id="rcp_user_id" value="' . $user_id .'"/>';
				
				$rcp_user_profile_image = get_user_meta($user_id, 'rcp_user_profile_image', true);
				
			
				//FILE UPLOADS
				$html .= '<div class="form-section current">';
					$html .= '<h3>Profile Image</h3>';	
					$html .= '<p class="form-field full image-upload">';
	
					$user_profile_image = get_post($rcp_user_profile_image);
					if($user_profile_image instanceof WP_Post){
						$profile_thumbnail_url = wp_get_attachment_image_src($user_profile_image->ID, 'thumbnail', false)[0];
						$html .= '<div class="profile-image-wrap">';
							$html .= '<img src="' . $profile_thumbnail_url . '" class="profile-image"/>';
						$html .= '</div>';
					}else{
						$html .= '<div class="el-notice el-notice-warning">';
							$html .= 'Your assigned image is missing. Please re-upload the image';
						$html .= '</div>';;
					}
						$html .= '<label style="display:block;" for="rcp_user_profile_image">Chose your profile image</label>';
						$html .= '<input type="file" name="rcp_user_profile_image" id="rcp_user_profile_image">';
					$html .= '</p>';
					
					$html .= '<span class="button form-button small-button form-next-button">Next</span>';
					
				$html .= '</div>';
				
				
				
				//ABOUT YOURSELF
				$html .= '<div class="form-section ">';
					$html .= '<h3>About Yourself</h3>';	
					$html .= '<p class="form-field full">';
						$rcp_user_bio = ($user_id) ? get_user_meta($user_id, 'rcp_user_bio', true) : ''; 
						$html .= '<label for="rcp_user_bio">Bio</label>';
						$html .= '<textarea rows="10" name="rcp_user_bio" id="rcp_user_bio" maxlength="400">' . $rcp_user_bio . '</textarea>';
					$html .= '</p>';
					
					$html .= '<span class="button form-button small-button form-previous-button">Previous</span>';
					$html .= '<span class="button form-button small-button form-next-button">Next</span>';
					
				$html .= '</div>';
				
				
				//QUALIFICATION
				$html .= '<div class="form-section">';
					$html .= '<h3>Qualifications</h3>';	
					$html .= '<p class="form-field full">';
						$rcp_user_qualifications = ($user_id) ? get_user_meta($user_id, 'rcp_user_qualifications', true) : ''; 
						$html .= '<label for="rcp_user_bio">Qualifications</label>';
						$html .= '<textarea rows="10" name="rcp_user_qualifications" id="rcp_user_qualifications" maxlength="400">' . $rcp_user_qualifications . '</textarea>';
					$html .= '</p>';
					
					$html .= '<span class="button form-button small-button form-previous-button">Previous</span>';
					
					//submit
					$html .= '<p class="form-field">';
						$html .= '<input type="submit" name="rcp_user_submit_profile_form" id="rcp_user_submit_profile_form" value="Update Profile"/>';
					$html .= '</p>';
					
					
				$html .= '</div>';
				
				
				
			
				
			
			$html .= '</form>';	
		}
		
		
		
		

		
		return $html;
	}

	/**
	 * Looks for the 'save_profile_form' form action and when found will update either the users or practitioners meta data
	 * 
	 * Used for both users and practitioners, will check for the existence of meatdata applicable to each type and update accordingly
	 *
	 */
	public function save_profile_form(){
		
		
		//Leveraged for 
		if(isset($_POST['rcp_user_submit_profile_form'])){
			
			$user_id = isset($_POST['rcp_user_id']) ? sanitize_text_field($_POST['rcp_user_id']) : '';
			
			//TODO: IMPROVE this process!
			if(isset($_FILES)){
				$profile_image = isset($_FILES['rcp_user_profile_image']) ? $_FILES['rcp_user_profile_image'] : '';
		
				if($profile_image){
					
					//include wp-admin files we need to move files and generate attachments
					if ( ! function_exists( 'wp_handle_upload' ) ) {
					    include( ABSPATH . 'wp-admin/includes/file.php' );
					}
					if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
					    include( ABSPATH . 'wp-admin/includes/image.php' );
					}
					
					$wp_file_move = wp_handle_upload($profile_image, array('test_form' => false));
					
					//if we successfully moved
					if(isset($wp_file_move['file'])){
						
						//create media attachment from uploaded file
						$attachment_args = array(
							'post_status'		=> 'inherit',
							'post_mime_type'	=> $wp_file_move['type'],
							'post_title'		=> '',
							'post_content'		=> ''
						);	
						$attachment_id = wp_insert_attachment($attachment_args, $wp_file_move['file']);

						//check for successful attachment creation
						if(is_int($attachment_id)){
							
							//add this attachment ID to our user 
							update_user_meta($user_id, 'rcp_user_profile_image' , $attachment_id);
							
							//add metadata for attachment
							$attachment_metadata = wp_generate_attachment_metadata($attachment_id, $wp_file_move['file'] );
							
							//update meatdata for attachment (to regenerate thumbnail in media library)
							$attachment_metadata_update = wp_update_attachment_metadata($attachment_id, $attachment_metadata);
						}
					}
				}
			}


			if(isset($_POST['rcp_user_overall_goal'])){
				update_user_meta($user_id, 'rcp_user_overall_goal' , sanitize_text_field($_POST['rcp_user_overall_goal']));
			}
			
			if(isset($_POST['rcp_user_lightest_weight'])){
				update_user_meta($user_id, 'rcp_user_lightest_weight' , sanitize_text_field($_POST['rcp_user_lightest_weight']));
			}
			if(isset($_POST['rcp_user_heaviest_weight'])){
				update_user_meta($user_id, 'rcp_user_heaviest_weight' , sanitize_text_field($_POST['rcp_user_heaviest_weight']));
			}
			if(isset($_POST['rcp_user_height'])){
				update_user_meta($user_id, 'rcp_user_height' , sanitize_text_field($_POST['rcp_user_height']));
			}
			if(isset($_POST['rcp_user_days_gym'])){
				update_user_meta($user_id, 'rcp_user_days_gym' , sanitize_text_field($_POST['rcp_user_days_gym']));
			}
			if(isset($_POST['rcp_user_member_of_gym'])){
				update_user_meta($user_id, 'rcp_user_member_of_gym' , sanitize_text_field($_POST['rcp_user_member_of_gym']));
			}
			
			//handeled checkboxes
			if(isset($_POST['rcp_user_exercise_preference'])){
				if(is_array($_POST['rcp_user_exercise_preference'])){
					update_user_meta($user_id, 'rcp_user_exercise_preference' , json_encode($_POST['rcp_user_exercise_preference']));
				}else{
					update_user_meta($user_id, 'rcp_user_exercise_preference' , sanitize_text_field($_POST['rcp_user_exercise_preference']));
				}	
			}else{
				update_user_meta($user_id, 'rcp_user_exercise_preference' , ''); 
			}
			
			if(isset($_POST['rcp_user_exercise_time'])){
				update_user_meta($user_id, 'rcp_user_exercise_time' , sanitize_text_field($_POST['rcp_user_exercise_time']));
			}
			if(isset($_POST['rcp_user_exercise_extra'])){
				update_user_meta($user_id, 'rcp_user_exercise_extra' , sanitize_text_field($_POST['rcp_user_exercise_extra']));
			}
			if(isset($_POST['rcp_user_medical_history'])){
				update_user_meta($user_id, 'rcp_user_medical_history' , sanitize_text_field($_POST['rcp_user_medical_history']));
			}
			if(isset($_POST['rcp_user_occupation'])){
				update_user_meta($user_id, 'rcp_user_occupation' , sanitize_text_field($_POST['rcp_user_occupation']));
			}
			if(isset($_POST['rcp_user_work_hours'])){
				update_user_meta($user_id, 'rcp_user_work_hours' , sanitize_text_field($_POST['rcp_user_work_hours']));
			}
			if(isset($_POST['rcp_user_main_income_earner'])){
				update_user_meta($user_id, 'rcp_user_main_income_earner' , sanitize_text_field($_POST['rcp_user_main_income_earner']));
			}
			if(isset($_POST['rcp_user_family_information'])){
				update_user_meta($user_id, 'rcp_user_family_information' , sanitize_text_field($_POST['rcp_user_family_information']));
			}
			if(isset($_POST['rcp_user_smoker'])){
				update_user_meta($user_id, 'rcp_user_smoker' , sanitize_text_field($_POST['rcp_user_smoker']));
			}
			if(isset($_POST['rcp_user_smoker_amount'])){
				update_user_meta($user_id, 'rcp_user_smoker_amount' , sanitize_text_field($_POST['rcp_user_smoker_amount']));
			}
			if(isset($_POST['rcp_user_drinker'])){
				update_user_meta($user_id, 'rcp_user_drinker' , sanitize_text_field($_POST['rcp_user_drinker']));
			}
			if(isset($_POST['rcp_user_drinker_amount'])){
				update_user_meta($user_id, 'rcp_user_drinker_amount' , sanitize_text_field($_POST['rcp_user_drinker_amount']));
			}
			if(isset($_POST['rcp_user_drinker_action'])){
				update_user_meta($user_id, 'rcp_user_drinker_action' , sanitize_text_field($_POST['rcp_user_drinker_action']));
			}
			if(isset($_POST['rcp_user_social_outing_food'])){
				update_user_meta($user_id, 'rcp_user_social_outing_food' , sanitize_text_field($_POST['rcp_user_social_outing_food']));
			}
			if(isset($_POST['rcp_user_bought_meals_per_week'])){
				update_user_meta($user_id, 'rcp_user_bought_meals_per_week' , sanitize_text_field($_POST['rcp_user_bought_meals_per_week']));
			}
			if(isset($_POST['rcp_user_cooked_dinners_per_week'])){
				update_user_meta($user_id, 'rcp_user_cooked_dinners_per_week' , sanitize_text_field($_POST['rcp_user_cooked_dinners_per_week']));
			}
			if(isset($_POST['rcp_user_who_cooks'])){
				update_user_meta($user_id, 'rcp_user_who_cooks' , sanitize_text_field($_POST['rcp_user_who_cooks']));
			}
			if(isset($_POST['rcp_user_who_shops'])){
				update_user_meta($user_id, 'rcp_user_who_shops' , sanitize_text_field($_POST['rcp_user_who_shops']));
			}
			if(isset($_POST['rcp_user_social_extra'])){
				update_user_meta($user_id, 'rcp_user_social_extra' , sanitize_text_field($_POST['rcp_user_social_extra']));
			}
			if(isset($_POST['rcp_user_disliked_foods'])){
				update_user_meta($user_id, 'rcp_user_disliked_foods' , sanitize_text_field($_POST['rcp_user_disliked_foods']));
			}
			if(isset($_POST['rcp_user_liked_foods'])){
				update_user_meta($user_id, 'rcp_user_liked_foods' , sanitize_text_field($_POST['rcp_user_liked_foods']));
			}
			if(isset($_POST['rcp_user_days_meal'])){
				update_user_meta($user_id, 'rcp_user_days_meal' , sanitize_text_field($_POST['rcp_user_days_meal']));
			}
			if(isset($_POST['rcp_user_days_snacks'])){
				update_user_meta($user_id, 'rcp_user_days_snacks' , sanitize_text_field($_POST['rcp_user_days_snacks']));
			}
			if(isset($_POST['rcp_user_ultimate_goal'])){
				update_user_meta($user_id, 'rcp_user_ultimate_goal' , sanitize_text_field($_POST['rcp_user_ultimate_goal']));
			}
			if(isset($_POST['rcp_user_short_term_goal'])){
				update_user_meta($user_id, 'rcp_user_short_term_goal' , sanitize_text_field($_POST['rcp_user_short_term_goal']));
			}
			if(isset($_POST['rcp_user_hurdles'])){
				update_user_meta($user_id, 'rcp_user_hurdles' , sanitize_text_field($_POST['rcp_user_hurdles']));
			}
			if(isset($_POST['rcp_user_future_hurdles'])){
				update_user_meta($user_id, 'rcp_user_future_hurdles' , sanitize_text_field($_POST['rcp_user_future_hurdles']));
			}
			if(isset($_POST['rcp_user_excited'])){
				update_user_meta($user_id, 'rcp_user_excited' , sanitize_text_field($_POST['rcp_user_excited']));
			}
			
			
			
			//PRACTITIONER FIELDS HERE
			if(isset($_POST['rcp_user_bio'])){
				update_user_meta($user_id, 'rcp_user_bio' , sanitize_text_field($_POST['rcp_user_bio']));
			}
			if(isset($_POST['rcp_user_qualifications'])){
				update_user_meta($user_id, 'rcp_user_qualifications' , sanitize_text_field($_POST['rcp_user_qualifications']));
			}
			
		}

	}


	
	/**
	 * Displays the profile for the user
	 */
	public static function display_user_profile_form($user_id){
		$instance = self::getInstance();
		
		$html = '';
		$html .= $instance->get_user_profile_form($user_id);
		
		echo $html;
		
		
	}
	
	//sets / gets singleton
	public static function getInstance(){
		if(is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}
	
 }



?>