<?php 
/*
Plugin Name:  WCAG Validator
Plugin URI:   http://wpsmartlab.com
Description:  Validate all posts with WCAG standard. Plugin generate a new report after saving post. You can also validate all post.
Version:      1.0
Author:       WPSmartLab
Author URI:   http://wpsmartlab.com
Text Domain:  wcag-validator
*/

class WCAG_Validator {
	public $menu_id;
	
	public $count_array_values = 0;
	public $count_errors = 0;
	
	public $post_types = array( 'post', 'page' );
	
	public $validate_status = '';

	// Plugin initialization
	public function __construct() {
		// Load up the localization file if we're using WordPress in a different language
		load_plugin_textdomain( 'wcag-validator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		
		// Set some strings
		$this->init();
		
		add_action( 'admin_menu',                              	array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_validatewcag',             		array( $this, 'ajax_process_validate' ) );
		// Allow people to change what capability is required to use this plugin
		$this->capability = apply_filters( 'wcag_validate', 'manage_options' );
		
		add_action( 'save_post', array( $this, 'ajax_process_validate' ) );
		
		foreach( $this->post_types as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'manage_posts_columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'manage_posts_custom_column' ), 10, 2);
		}
			
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}
	
	public function init() {
		
		$this->errors_t = array( 
			'headers' 	=> __('Headers', 'wcag-validator'), 
			'links' 	=> __('Links', 'wcag-validator'), 
			'images' 	=> __('Images', 'wcag-validator'), 
			'tables' 	=> __('Tables', 'wcag-validator')
		);
		
		$this->errors_i = array(
			1 	=> __( "Post image of name %s doesn't have an alternative text", 'wcag-validator' ),
			2 	=> __( "An image of name %s doesn't have an alternative text", 'wcag-validator' ),
			3	=> __( "Content doesn't contain subtitles with h3, h4 element", 'wcag-validator' ),
			4	=> __( "An link of name %s probably have a bad value", 'wcag-validator' ),
			5 	=> __( "The table doesn't have caption or th element", 'wcag-validator' )
		);
	}

	/**
	 * Adds the meta box container.
	 */
	public function add_meta_box( $post_type ) {
		if ( in_array( $post_type, $this->post_types )) {
			add_meta_box( 'wcag-validator' ,__( 'WCAG Report', 'wcag-validator' ), array( $this, 'render_meta_box_content' ), $post_type, 'side', 'core' );
		}
	}

	public function render_meta_box_content( $post ) {
		global $post_id;
		if( !$post_id ) {
			_e('Houston, we have a problem', 'wcag-validator');
			return;
		}
		$status = $this->get_wcag_status( $post_id );
		?>
		<p><?php echo $status; ?> <?php echo $this->validate_status; ?></p>
		<?php if( $this->count_errors != 0 ): ?>
			<h4><?php _e('Lists of errors:', 'wcag-validator'); ?></h4>
			<?php if( isset( $this->wcagmeta ) ): ?>
				<?php foreach( $this->wcagmeta as $title=>$section ): ?>
					<?php if( !empty( $section ) ): ?>
					<p><strong><?php echo $this->errors_t[$title]; ?></strong></p>
					<ol>
					<?php foreach( $section as $id=>$error ): ?>
						<?php
							$error = explode(':', $error);
							$error_id = isset( $error[0] ) ? (int) $error[0] : 0;
							$error_file = isset( $error[1] ) ? (string) $error[1] : 'unknown name';
						?>
						<?php if( isset( $this->errors_i[$error_id] ) ): ?>
						<li><?php echo sprintf( $this->errors_i[$error_id], $error_file ); ?></li>
						<?php endif; ?>
					<?php endforeach ?>
					</ol>
					<?php  endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	public function manage_posts_columns($defaults) {
		$defaults['wcag'] = __('WCAG', 'wcag-validator');
		return $defaults;
	}
	
	public function count_array($item, $key) {
		$this->count_array_values++;
	}
	
	public function get_wcag_status( $post_ID ) {
		$color = 'green';
		$this->validate_status = __('This posts is valid', 'wcag-validator');
		
		$this->wcagmeta = get_post_meta($post_ID, 'wcag_validate', true);
		// if is array just iterate
		if( is_array( $this->wcagmeta ) ) {
			$meta = $this->wcagmeta;
			if( isset( $meta ) ) array_walk_recursive( $meta, array( $this, 'count_array' ) );
			$this->count_errors = $this->count_array_values;
			// Reset counter
			$this->count_array_values = 0;
			if( isset( $meta['headers'] ) ) {
				if( !empty( $meta['headers'] ) OR !empty( $meta['images'] ) OR !empty( $meta['links'] ) OR !empty( $meta['tables'] ) ) {
					if( $this->count_errors > 2 ) {
						$color = 'red';
					} else {
						$color = 'orange';
					}
					$this->validate_status = sprintf( _n( 'This document contain %d error.', 'This document contain %d errors.', $this->count_errors, 'wcag-validator' ), $this->count_errors );
				}
			}
		}
		// if meta has value 1, that means the post is valid
		if( $this->wcagmeta == 1 ) {
			$color = 'green';
			$this->validate_status = __('This post is valid', 'wcag-validator');
		}
		// if meta is empty, this means the post was not validate
		if( empty( $this->wcagmeta ) ) {
			$color = "black";
			$this->validate_status = __('This post wasn\'t validate yet', 'wcag-validator');
		}
		return '<div title="'.$this->validate_status.'" style="display: inline-block; margin: 4px 0 0; width: 12px; height: 12px; background-color: '.$color.'; border-radius: 50%;"></div>';
	}

	public function manage_posts_custom_column($column_name, $post_ID) {
		if ($column_name == 'wcag') {
			echo $this->get_wcag_status( $post_ID );
		}
	}

	public function add_admin_menu() {
		$this->menu_id = add_management_page( __( 'WCAG Validator', 'wcag-validator' ), __( 'WCAG Validator', 'wcag-validator' ), $this->capability, 'wcag-validator', array($this, 'admin_page') );
	}

	public function admin_page() {
		global $wpdb;
	?>
	<div class="wrap wcag-validator">
		<h2><?php _e('WCAG Validator', 'wcag-validator'); ?></h2>
	<?php
	if ( !empty( $_POST['wcag-validator'] ) || ! empty( $_REQUEST['ids'] ) ) {
		if ( ! current_user_can( $this->capability ) ) wp_die( __( 'Cheatin&#8217; uh?' ) );
		check_admin_referer( 'wcag-validator' );

		if ( !$posts = $wpdb->get_results( "
				SELECT ID 
				FROM $wpdb->posts 
				WHERE ( post_type = 'post' OR post_type = 'page' ) 
				AND ( post_status = 'publish' OR post_status = 'draft' )
				ORDER BY post_date DESC"
			) ) {
			echo '	<p>' . __( "Unable to find any posts.", 'wcag-validator' ) . "</p></div>";
			return;
		}

		// Generate the list of IDs
		$ids = array();
		foreach ( $posts as $post ) {
			$ids[] = $post->ID;
		}
		$ids = implode( ',', $ids );
		$count = count( $posts );

		$text_wcag_errors = sprintf( __( 'Houston, all done! %1$s post(s) are valid, but we find %3$s failure(s) in our spaceship.', 'wcag-validator' ), "' + wv_successes + '", "' + wv_totaltime + '", "' + wv_errors + '" );
		$text_wcag_noerrors = sprintf( __( 'Houston, all ll done! %1$s post(s) was successfully validate in %2$s seconds and there were no erros failures.', 'wcag-validator' ), "' + wv_successes + '", "' + wv_totaltime + '" );
	?>

		<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'wcag-validator' ) ?></em></p></noscript>
		
		<div id="wcag_message" class="updated fade">
			<p><?php _e( 'Please be patient while the post are validated. This can take a while if your server is slow or if you have many posts. You will be notifed when validator will completed scaninig', 'wcag-validator' ); ?></p>
		</div>
		
		<div id="wcag-validator-bar" style="width: 100%; height:22px; border: 1px solid #CCC; background: #FFF;">
			<div id="wcag-validator-bar-percent" style="color: #FFF; background: #333; height:22px;font-weight:bold; width: 0%;">
				<p style="padding: 0 10px; margin: 0 0;"></p>
			</div>
		</div>

		<p><input type="button" class="button hide-if-no-js" name="wcag-validator-stop" id="wcag-validator-stop" value="<?php _e( 'Abort', 'wcag-validator' ) ?>" /></p>

		<h3 class="title"><?php _e( 'WCAG Report:', 'wcag-validator' ) ?></h3>

		<p>
			<?php printf( __( 'Total Posts: %s', 'wcag-validator' ), $count ); ?><br />
			<?php printf( __( 'Valid posts: %s', 'wcag-validator' ), '<span id="wcag-validator-debug-successcount">0</span>' ); ?><br />
			<?php printf( __( 'WCAG Errors: %s', 'wcag-validator' ), '<span id="wcag-validator-debug-failurecount">0</span>' ); ?>
		</p>

		<ol id="wcag-validator-debuglist"></ol>

		<script type="text/javascript">
		// <![CDATA[
			jQuery(document).ready(function($){
				var i, wv_posts = [<?php echo $ids; ?>], wv_total = wv_posts.length, wv_count = 1, wv_percent = 0, wv_successes = 0;
				var wv_errors = 0, wv_resulttext = '', wv_timestart = new Date().getTime(), wv_timeend = 0, wv_totaltime = 0, wv_stop = true;
			
				// Stop button
				$("#wcag-validator-stop").click(function() {
					wv_stop = false;
				});

				function WCAGValidationStatus( id, success, response ) {
					$("#wcag-validator-bar-percent p").html( Math.round( ( wv_count / wv_total ) * 1000 ) / 10 + '%' );
					$("#wcag-validator-bar-percent").css('width', ( Math.round( ( wv_count / wv_total ) * 1000 ) / 10 + '%' ) );
					wv_count = wv_count + 1;

					if ( success ) {
						wv_successes = wv_successes + 1;
						$("#wcag-validator-debug-successcount").html(wv_successes);
						$("#wcag-validator-debuglist").append("<li>" + response.success + "</li>");
					}
					else {
						wv_errors = wv_errors + 1;
						$("#wcag-validator-debug-failurecount").html(wv_errors);
						$("#wcag-validator-debuglist").append("<li>" + response.error + "</li>");
					}
				}

				function WCAGValidationStop() {
					wv_timeend = new Date().getTime();
					wv_totaltime = Math.round( ( wv_timeend - wv_timestart ) / 1000 );

					$('#wcag-validator-stop').hide();

					if ( wv_errors > 0 ) {
						wv_resulttext = '<?php echo $text_wcag_errors; ?>';
					} else {
						wv_resulttext = '<?php echo $text_wcag_noerrors; ?>';
					}

					$("#wcag_message p").html("<strong>" + wv_resulttext + "</strong>");
				}

				// Regenerate a specified image via AJAX
				function WCAGValidate( id ) {
					$.ajax({
						type: 'POST',
						url: ajaxurl,
						data: { action: "validatewcag", id: id },
						success: function( response ) {
							if ( response !== Object( response ) || ( typeof response.success === "undefined" && typeof response.error === "undefined" ) ) {
								response = new Object;
								response.success = false;
								console.log(response);
								response.error = "<?php printf( esc_js( __( 'Houston we have a problem. With the post of an ID %d is something wrong', 'wcag-validator' ) ), '" + id + "' ); ?>";
							}

							if ( response.success ) {
								WCAGValidationStatus( id, true, response );
							}
							else {
								WCAGValidationStatus( id, false, response );
							}

							if ( wv_posts.length && wv_stop ) {
								WCAGValidate( wv_posts.shift() );
							}
							else {
								WCAGValidationStop();
							}
						},
						error: function( response ) {
							WCAGValidationStatus( id, false, response );

							if ( wv_posts.length && wv_stop ) {
								WCAGValidate( wv_posts.shift() );
							}
							else {
								WCAGValidationStop();
							}
						}
					});
				}

				WCAGValidate( wv_posts.shift() );
			});
		// ]]>
		</script>
	<?php
	} else {
	?>
		<form method="post" action="">
			<?php wp_nonce_field('wcag-validator') ?>
			<p><?php printf( __( "Use this tool to validate your post with WCAG standard. This plugin validate if images has alternative text, quotation was added and content has headline", 'wcag-validator' ), admin_url( 'options-media.php' ) ); ?></p>
			<p><?php _e( 'To begin, just press the button below.', 'wcag-validator'); ?></p>
			<p><input type="submit" class="button hide-if-no-js" name="wcag-validator" id="wcag-validator" value="<?php _e( 'Validate all posts', 'wcag-validator' ) ?>" /></p>
		</form>
		<?php
	}
	?>
	</div>

	<?php
	}
	
	public function check_images( $post ) {
		$errors = array();
		$id = $post->ID;
		$featured_id = get_post_thumbnail_id( $id );
		$post_image =  get_post( $featured_id );
		$alt = get_post_meta( $featured_id, '_wp_attachment_image_alt', true );
		if( $featured_id AND empty( $alt ) ) {
			$errors[] = '1:'.$post_image->post_title;
		}
		
		preg_match_all( '/<img[^>]+>/i', $post->post_content, $result );
		$img = array();
		if( isset( $result[0] ) AND is_array( $result[0] ) ) {
			foreach( $result[0] as $img_tag )
			{
				preg_match_all( '/(alt|title|src)="([^"]*)"/i', $img_tag, $img );
				$src = isset( $img[2][0] ) ? strtolower( trim( $img[2][0] ) ) : null;
				$alt = isset( $img[2][1] ) ? strtolower( trim( $img[2][1] ) ) : null;
				if( $src ) {
					$filename = basename( $src );
					if( empty( $alt ) OR preg_match( "/{$alt}/", $src )  ) {
						$errors[] = '2:'.$filename;
					}
				}

			}
		}
		return $errors;
	}
	
	public function check_headers( $post ) {
		$errors = array();
		if( strlen( $post->post_content ) > 3500 ) {
			if( !preg_match( '/\<h3/', $post->post_content ) AND !preg_match( '/\<h4/', $post->post_content ) ) {
				$errors[] = '3:header';
			}
		}
		return $errors;
	}
	
	public function check_links( $post ) {
		$bad_names = array( 'more', 'read', 'read more', 'czytaj', 'czytaj więcej', 'kliknij', 'tutaj', 'kilknij tu' );
		$errors = array();
		preg_match_all( '/<a[^>]+>([^"]*)<\/a>/i', $post->post_content, $result );
		if( isset( $result[1] ) AND is_array( $result[1] ) ) {
			foreach( $result[1] as $name ) {
				$name = mb_strtolower( trim( $name ) );
				if( in_array( $name, $bad_names ) ) {
					$errors[] = '4:'.$name;
				}
			}
		}
		return $errors;
	}
	
	public function check_tables( $post ) {
		$errors = array();
		preg_match_all( '/<table.*?>(.*?)<\/table>/si', $post->post_content, $tables );
		
		if( isset( $tables[1] ) AND is_array( $tables[1] ) ) {
			foreach( $tables[1] as $table ) {
				if( !preg_match('/<caption.*?>/i', $table ) OR !preg_match('/<th.*?>/i', $table ) ) {
					$errors[] = '5:table';
				}
			}
		}
		return $errors;
	}

	public function ajax_process_validate( $post_ID = null ) {

		@error_reporting( 0 ); // Don't break the JSON result
		header( 'Content-type: application/json' );
		// If post_ID exists is known the 
		$id = $post_ID ? $post_ID : (int) $_REQUEST['id'];
		if( !$id ) {
			if( !$post_ID ) $this->die_json_error_msg( 0, 'No ID of post' );
		}
		$post = get_post( $id );
		
		if( empty( $post->post_content ) ) {
			if( !$post_ID ) $this->die_json_error_msg( $post->ID, 'Content is empty' );
		}

		$errors = array();
		$id = $post->ID;
		// Validate headers in post content has more then 3500 lenght
		$errors[ $id ]['headers'] = $this->check_headers( $post );
		// Validate all alternative in images
		$errors[ $id ]['images'] = $this->check_images( $post );
		// Validate all link, which may contain a bad name
		$errors[ $id ]['links'] = $this->check_links( $post );
		// Validate if table contain caption and th element
		$errors[ $id ]['tables'] = $this->check_tables( $post );
		
		@set_time_limit( 100 );
		if( !empty( $errors[ $id ]['headers'] ) OR !empty( $errors[ $id ]['images'] ) OR !empty( $errors[ $id ]['links'] ) OR !empty( $errors[ $id ]['tables'] ) ) {
			$meta = update_post_meta( $id, 'wcag_validate', $errors[ $id ] );
			if( !$post_ID ) die( json_encode( array( 'error' => sprintf( __( 'Found errors for post of name "%s".', 'wcag-validator' ), esc_html( get_the_title( $post->ID ) ), $post->ID, timer_stop() ) ) ) );
		} else {
			$meta = update_post_meta( $id, 'wcag_validate', 1 );
		}
		if( !$post_ID ) die( json_encode( array( 'success' => sprintf( __( 'The post of name "%s" propably is valid with WCAG standard.', 'wcag-validator' ), esc_html( get_the_title( $post->ID ) ), $post->ID, timer_stop() ) ) ) );
	}

	public function die_json_error_msg( $id, $message ) {
		die( json_encode( array( 'error' => sprintf( __( 'We have problem to validate this post: "%s" of ID %d, error: %s', 'wcag-validator' ), esc_html( get_the_title( $id ) ), $id, $message ) ) ) );
	}

}

// Start up this plugin
add_action( 'init', 'wcag_validator', 99 );
function wcag_validator() {
	global $wcag_validator;
	$wcag_validator = new WCAG_Validator();
}

?>