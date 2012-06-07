jQuery(document).ready(
	function($) {
		/* Init */
		av_nonce = av_settings.nonce;
		av_ajax = av_settings.ajax;
		av_theme = av_settings.theme;
		av_msg_1 = av_settings.msg_1;
		av_msg_2 = av_settings.msg_2;
		av_msg_3 = av_settings.msg_3;
			
		/* Einzelne Datei prüfen */
		function check_theme_file(current) {
			/* ID umwandeln */
			var id = parseInt(current || 0);
			
			/* File ermitteln */
			var file = av_files[id];
			
			/* Request starten */
			$.post(
				av_ajax,
			 	{
					'action': 				 'get_ajax_response',
				 	'_ajax_nonce': 		 av_nonce,
				 	'_theme_file': 		 file,
				 	'_action_request': 'check_theme_file'
			 	},
			 	function(input) {
					/* Wert initialisieren */
			 		var item = $('#av_template_' + id);
				 	
				 	/* Daten vorhanden? */
				 	if (input) {
						/* Input konvertieren */
						input = eval('(' + input + ')');
						
						/* Sicherheitscheck */
						if (!input.nonce || input.nonce != av_nonce) {
							return;
						}
						
						/* Farblich anpassen */
						item.addClass('danger');
						
				 		/* Werte initialisieren */
					 	var i = 0;
					 	var lines = input.data;
					 	var len = lines.length;
						
					 	/* Zeilen loopen */
						for (i; i < len; i = i + 3) {
							var num = parseInt(lines[i]) + 1;
							var line = lines[i + 1].replace(/@span@/g, '<span>').replace(/@\/span@/g, '</span>');
							var md5 = lines[i + 2];
							var file = item.text();
							
							item.append('<p><a href="#" id="' + md5 + '">' + av_msg_1 + '</a> <a href="theme-editor.php?file=' + file + '&theme=' + av_theme + '&dir=theme" target="_blank">' + av_msg_2 + ' ' + num + '</a><code>' + line + '</code></p>');
							
							$('#' + md5).click(
								function() {
									$.post(
										av_ajax,
										{
											'action': 					'get_ajax_response',
										 	'_ajax_nonce': 			av_nonce,
										 	'_file_md5': 				$(this).attr('id'),
										 	'_action_request':	'update_white_list'
									 	},
									 	function(input) {
									 		/* Keine Daten? */
											if (!input) {
												return;
											}
											
											/* Input konvertieren */
											input = eval('(' + input + ')');
											
											/* Sicherheitscheck */
											if (!input.nonce || input.nonce != av_nonce) {
												return;
											}
											
								 			var parent = $('#' + input.data[0]).parent();
								 			
											if (parent.parent().children().length <= 1) {
												parent.parent().hide('slow').remove();
											}
											parent.hide('slow').remove();
									 	}
									);
									
									return false;
								}
							);
						}
					} else {
			 			item.addClass('done');
				 	}
				 	
					/* Counter erhöhen */
					av_files_loaded ++;
			 	 	
			 	 	/* Hinweis ausgeben */
				 	if (av_files_loaded >= av_files_total) {
						$('#av_manual .alert').text(av_msg_3).fadeIn().fadeOut().fadeIn().fadeOut().fadeIn().animate({opacity: 1.0}, 500).fadeOut(
							'slow',
							function() {
								$(this).empty();
							}
						);
				 	} else {
				 		check_theme_file(id + 1);
				 	}
			 	}
			);
		}
		
		/* Tempates Check */
		$('#av_manual a.button').click(
			function() {
				/* Request */
		 		$.post(
		 			av_ajax,
		 			{
					 	action: 					'get_ajax_response',
					 	_ajax_nonce: 			av_nonce,
					 	_action_request:	'get_theme_files'
		 			},
		 			function(input) {
		 				/* Keine Daten? */
						if (!input) {
							return;
						}
						
						/* Input konvertieren */
						input = eval('(' + input + ')');
						
						/* Sicherheitscheck */
						if (!input.nonce || input.nonce != av_nonce) {
							return;
						}
						
						/* Wert initialisieren */
						var output = '';
						
						/* Globale Werte */
						av_files = input.data;
						av_files_total = av_files.length;
						av_files_loaded = 0;
						
						/* Files visualisieren */
						jQuery.each(
							av_files,
							function(i, val) {
								output += '<div id="av_template_' + i + '">' + val + '</div>';
							}
						);
						
						/* Werte zuweisen */
						$('#av_manual .alert').empty();
						$('#av_manual .output').empty().append(output);
						
						/* Files loopen */
						check_theme_file();
					}
				);
		 		
		 		return false;
			}
		);
		
		/* Checkboxen markieren */
		function manage_options() {
			var id = 'av_cronjob_enable';
			$('#' + id).parents('.form-table').find('input[id!="' + id + '"]').attr('disabled', !$('#' + id).attr('checked'));
		}
		
		/* Checkbox überwachen */
		$('#av_cronjob_enable').click(manage_options);
		
		/* Fire! */
		manage_options();
	}
);