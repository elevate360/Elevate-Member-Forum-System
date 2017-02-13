/*
 * Front end scripts and the nutrition system 
 */

jQuery(document).ready(function($){
	
	
	//validate the submission of the new topic form
	$('#post-topic-form').validate({
		
		rules:{
			topic_title: {
				required: true,
				rangelength: [10, 100]
			},
			topic_content: {
				required: true,
				rangelength: [5, 500]
			},
			topic_privacy: {
				required: true
			}
		},
		messages:{
			topic_title: {
				required: 'Please enter a title for the new topic',
				rangelength: 'Title must be between X and X characters'
			},
			topic_content:{
				required: 'Please enter your topics content',
				rangelength: 'Your content must be between X and X Characters'
			},
			topic_privacy: {
				required: 'Topic privacy must be entered'
			}
		},
		submitHandler: function(form){
			
			var form = $(form); 
			
			//collect form values
			var topic_title = form.find('#topic_title').val();
			var topic_content = form.find('#topic_content').val();
			var topic_privacy = form.find('[name="topic_privacy"]:checked').val();
			
			var topic_category_value = '';
			var topic_category = form.find('#topic_category');
			if(topic_category.length > 0){
				topic_category_value = topic_category.find(':selected').val();
			}
			
			//optionall check for topic practitioner id
			var topic_assigned_practitioner = form.find('#topic_assigned_practitioner').val();
			
			options = {
				actionType: 'post_topic',
				topic_title: topic_title,
				topic_content: topic_content,
				topic_privacy: topic_privacy,
				topic_category: topic_category_value
			}
					
			if(topic_assigned_practitioner){
				options.topic_assigned_practitioner = topic_assigned_practitioner;
			}
			
			post_new_topic_ajax(options);
		},
		invalidHandler: function(form){
			//alert("invalid");
		}
		
	});
	
	
	
	//post topic reply form
	$('#post-reply-form').validate({
		rules:{
			reply_content: {
				required: true,
				rangelength: [3, 200]
			}
		},
		messages:{
			reply_content:{
				required: 'Please enter your reply content',
				rangelength: 'Your content must be between X and X Characters'
			}
		},
		submitHandler: function(form){
			
			var form = $(form); 
			
			//THIS IS THE ISSUE WE NED TO CHECK THE FORM (GET IT AGAIN)
			
			//collect form values
			var reply_content = $('#post-reply-form').find('#reply_content').val();
			var reply_topic_id = $('#post-reply-form').find('#reply_topic_id').val();
			
			options = {
				actionType: 'post_reply',
				reply_content: reply_content,
				reply_topic_id: reply_topic_id
			}
			
			console.log("Im now valid and submitting!");
			
			//do ajax call
			post_new_reply_ajax(options);
		},
		invalidHandler: function(form){
			//alert("invalid topic reply submit");
		}
		
		
	});
	
	
	//Process user profile form completiion 
	$('#rcp_user_submit_profile_form').on('click', function(){
		
		var user_form = $(this).parents('form');	
		var serialized_form = user_form.serialize();
		
		var user_id = user_form.find("#rcp_user_id").val();
		
		data = {
			action: 'submit_user_profile_form',
			user_id: user_id,
			form: serialized_form
		}
		
		//ajax call to assign user
		$.ajax({
			type: 'POST',
			url: ajax_object.ajax_url,
			data: data,
			cache: false,
			success: function(response){
						
				var response = JSON.parse(response);
				
				//returned status was success
				if(response.status == 'success'){
					 window.location.reload();
				}else if(response.status == 'error'){
					console.log("Error: " + response.message);
				}
			},
			error: function(response){
			}
		}); 
		
		
	});
	
	
	
	
	//Bind a listener to the practitioner assign button
	$('body').on('click', '#el-hire-practitioner-button', function(){
		
		var hire_button = $(this);
		var practitioner_info = $(this).parents('.el-practitioner');
		
		//pull user and practitioner id
		var user_id = $(this).attr('data-user-id');
		var practitioner_id = $(this).attr('data-practitioner-id');
		var action_type = $(this).attr('data-button-action');
		
		data = {
			action: action_type,
			practitioner_id: practitioner_id,
			user_id: user_id
		};
		
		//ajax call to assign user
		$.ajax({
			type: 'POST',
			url: ajax_object.ajax_url,
			data: data,
			cache: false,
			success: function(response){
				
				var response = JSON.parse(response);
				
				//returned status was success
				if(response.status == 'success'){
					//append the returned success message below the practtitioner info
					practitioner_info.after(response.content);
					//remove button
					hire_button.remove();
					
				}else{
					console.log('Error Returned: ' + response.message);
				}
			},
			error: function(response){
				console.log("Error from the assign practitioner button")
			}
		});
		
		
	});
	
	
	//ajax call to post a new topic
	function post_new_topic_ajax(options){
		
		//colect data
		var data = {}
		data.action = options.actionType;
		data.topic_title = options.topic_title;
		data.topic_content = options.topic_content;
		data.topic_privacy = options.topic_privacy;
		data.topic_category = options.topic_category;
		
		if(options.hasOwnProperty('topic_assigned_practitioner')){
			data.topic_assigned_practitioner = options.topic_assigned_practitioner;
		}

		//do the main ajax call
		$.ajax({
			type: 'POST',
			url: ajax_object.ajax_url,
			data: data,
			cache: false,
			success: function(response){
				
				var response = JSON.parse(response);
				
				//returned status was success
				if(response.status == 'success'){
					
					var topic_id = response.topic_id; 
					//alert(response.message);
					
					//call second ajax call to get HTML
					options = {
						actionType: 'get_topic_markup',
						topic_id: topic_id
					}
					
					//get the markup for the new topic entry and add it to the table
					add_topic_markup_to_table_ajax(options);
					
				}
				//returned error
				else if(response.status == 'error'){
					console.log("error: " + response.message);
				}
			},
			//Ajax ERROR
			error: function(reponse){
				console.log("Error from the post_new_topic_ajax function");
			}
			
		});
		
	}
	

	//gets the HTML for the new topic posted
	function add_topic_markup_to_table_ajax(options){
		
		var data = {}
		data.action = options.actionType;
		data.topic_id = options.topic_id;
		
		var content = ''; 
		
		//do the main ajax call
		$.ajax({
			type: 'POST',
			url: ajax_object.ajax_url,
			data: data,
			cache: false,
			success: function(response){
				
				var response = JSON.parse(response);
				
				if(response.status == 'success'){
					//sucess, add topic to table
					content = response.content;
					$('#topic-table').prepend(content);
					
				}else{
					//alert("error getting topic content");
				}
			},
			error: function(response){
				//alert("Couldnt get new topic html, ajax failed");
			}
		});
		
	}
	
	
	//ajax call to post a reply to a topic
	function post_new_reply_ajax(options){
		
		//colect data
		var data = {};
		data.action = options.actionType;
		data.reply_content = options.reply_content;
		data.reply_topic_id = options.reply_topic_id;
		
		$.ajax({
			type: 'POST',
			url: ajax_object.ajax_url,
			data: data,
			cache: false,
			success: function(response){
				
				var response = JSON.parse(response);
				
				if(response.status == 'success'){
					
					//successfully posted a reply, update table
					var options = {
						actionType: 'get_reply_markup',
						reply_id: response.reply_id
					}
					
					//clear the reply form
					$('#reply_content').val('');
					
					//get the markup for a reply and add it to the table
					add_reply_markup_to_table_ajax(options);
					
					
				}else{
					
				}
				
			},
			error: function(response){
				console.log("Error: " + response.message);
				//alert("Couldnt post new topic, fail on ajax function");
			}	
		});
	}
	
	//gets the HTML for the new reply 
	function add_reply_markup_to_table_ajax(options){
		
		var data = {}
		data.action = options.actionType;
		data.reply_id = options.reply_id;
		
		$.ajax({
			type: 'POST',
			url: ajax_object.ajax_url,
			data: data,
			cache: false,
			success: function(response){
				
				var response = JSON.parse(response);
				
				//successfully collected reply content, update table
				if(response.status == 'success'){
					var content = response.content;
					$('#reply-table').append(content);
				}
			},
			error: function(response){
				
			}
		}); 
		
		
		
	}
	
	//Ajax function to be continually called when viewing a single topic. It's purpose is to 
	//continually look for new replies to add to the open topic
	function update_topic_with_replies_ajax(options, topic_table){
		
		var topic_table = topic_table;
		
		data = {
			action: options.action,
			topic_id: options.topic_id
		};
		
		$.ajax({
			type: 'POST',
			url: ajax_object.ajax_url,
			data: data,
			cache: false,
			success: function(response){
				response = JSON.parse(response);
			},
			error: function(response){
				response = JSON.parse(response);
				console.log("Error from ajax update_topic_with_replies_ajax funtion")
			},
			complete: function(response){
				response = JSON.parse(response.responseText);
				console.log(response.message);
				
				//update the UI with the correct topics
				update_topic_replies(response, topic_table);
				
				//call recursively forever
				setTimeout(function(){
					update_topic_with_replies_ajax(data, topic_table);
				}, 50000); 
				
			}
		});	
	}
	
	//keeps the UI updated for a single topic
	function update_topic_replies(response,topic_table){
		
		var current_replies = topic_table.find('.el-reply');
		
		
		if(response.hasOwnProperty('replies')){
			var replies = response.replies;	
			var length = Object.keys(replies).length;
			
			for( var key in replies){
				reply = replies[key];
				
				//if reply doesn't exist in curren table, update it'
				var find = '#el-reply-' + reply.reply_id; 
				var item_exists = current_replies.siblings(find).length;
				if( item_exists < 1){
					topic_table.append(reply.reply_content); 
				}else{
					test = 5;
				}
				
			}

		}
		
		
		
	}
	
	
	
	//find reply table and run our long polling event
	$('#reply-table').each(function(){
		
		//find the topic id
		var topic_table = $(this);
		var topic_id = topic_table.attr('data-topic-id');
		
		var options = {
			action: 'update_topic_with_replies',
			topic_id: topic_id
		}
		update_topic_with_replies_ajax(options, topic_table);
	}); 
	
	
	//User profile form NEXT functionality
	$('.profile-form').on('click', '.form-next-button', function(){
		
		//update progress bar
		var form_progress_bar = $('.user-progress-bar'); 
		var form_progress_bar_current = form_progress_bar.find('.current');
		var form_progress_bar_next = form_progress_bar_current.next('.element');
		form_progress_bar_current.removeClass('current');
		form_progress_bar_next.addClass('current');
		
		//update current form section
		var form_sections = $(this).parents('form').find('.form-section');
		var form_section_current = form_sections.filter('.current');
		var form_section_next = form_section_current.next('.form-section');
		form_section_current.removeClass('current');
		form_section_next.addClass('current');
		
		form_section_current.hide();
		form_section_next.show();
	});
	
	//User profile form PREVIOUS functionality
		$('.profile-form').on('click', '.form-previous-button', function(){
		
		//update progress bar
		var form_progress_bar = $('.user-progress-bar'); 
		var form_progress_bar_current = form_progress_bar.find('.current');
		var form_progress_bar_previous = form_progress_bar_current.prev('.element');
		form_progress_bar_current.removeClass('current');
		form_progress_bar_previous.addClass('current');
		
		//update current form section
		var form_sections = $(this).parents('form').find('.form-section');
		var form_section_current = form_sections.filter('.current');
		var form_section_previous = form_section_current.prev('.form-section');
		form_section_current.removeClass('current');
		form_section_previous.addClass('current');
		
		form_section_current.hide();
		form_section_previous.show();
	});
	
});
