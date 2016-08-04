<?php
	/**
	 * Plugin Name:  Woo Product Download from Amazon S3
	 * Plugin URI:   https://wordpress.org/plugins/woo-product-download-from-amazon-s3/
	 * Description:  WooCommerce Product Download / Upload to / from using Amazon S3 service.
	 * Version:      1.0.1
	 * Author:       Emran
	 * Author URI:   https://emran.me/
	 * License:      GPLv2.0+
	 * License URI:  http://www.gnu.org/licenses/gpl-2.0.txt
	 *
	 * Text Domain:  woo-product-download-from-amazon-s3
	 * Domain Path:  /languages/
	 */

	defined( 'ABSPATH' ) or die( 'Keep Quit' );

	if ( ! class_exists( 'EA_WC_Amazon_S3' ) ):

		class EA_WC_Amazon_S3 {

			private $access_id;
			private $secret_key;
			private $bucket;
			private $endpoint;

			public function __construct() {
				$this->constants();
				$this->includes();
				$this->hooks();

				do_action( 'ea_wc_amazon_s3_loaded', $this );
			}

			private function constants() {
				$this->access_id  = trim( get_option( 'ea_wc_amazon_s3_key' ) );
				$this->secret_key = trim( get_option( 'ea_wc_amazon_s3_secret_key' ) );
				$this->endpoint   = trim( get_option( 'ea_wc_amazon_s3_endpoint' ) );
			}

			private function includes() {
				if ( ! class_exists( 'S3' ) ) {
					require_once dirname( __FILE__ ) . '/includes/S3.php';
				}
			}

			public function init() {
				load_plugin_textdomain( 'woo-product-download-from-amazon-s3', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			}

			private function hooks() {

				// Init
				add_action( 'init', array( $this, 'init' ) );

				// Upload Process
				add_action( 'wp_loaded', array( $this, 'upload_handler' ) );

				// Add Settings Tab
				add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_tab' ), 50 );

				// Show Settings Fields
				add_action( 'woocommerce_settings_tabs_woo-product-download-from-amazon-s3', array( $this, 'settings_fields' ) );

				// Save Settings Fields
				add_action( 'woocommerce_update_options_woo-product-download-from-amazon-s3', array( $this, 'update_settings' ) );

				// File Download Process
				add_action( 'woocommerce_download_file_from_ea_wc_amazon_s3', array( $this, 'process_download' ), 10, 2 );

				// File Download Method
				add_filter( 'woocommerce_file_download_method', array( $this, 'file_download_method' ) );

				// Add Admin Script
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

				// Add Media Tab
				add_filter( 'media_upload_tabs', array( $this, 'media_tabs' ) );

				// Bucket List
				add_action( 'media_upload_ea_wc_amazon_buckets', array( $this, 'buckets_iframe' ) );

				// Upload to bucket
				add_action( 'media_upload_ea_wc_amazon_upload', array( $this, 'upload_iframe' ) );

				// Plugin Row Meta
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ), 999 );
			}

			public function upload_handler() {

				if ( ! is_admin() || ! isset( $_POST[ 'ea_wc_amazon_s3_upload_submit' ] ) ) {
					return;
				}

				if ( ! isset( $_POST[ '_wpnonce' ] ) || ! wp_verify_nonce( $_POST[ '_wpnonce' ], 'ea_wc_amazon_s3_upload_file' ) ) {
					wp_die( esc_html__( 'Not Verified', 'woo-product-download-from-amazon-s3' ), esc_html__( 'Error', 'woo-product-download-from-amazon-s3' ), array( 'back_link' => TRUE ) );
				}

				if ( empty( $_FILES[ 'ea_wc_amazon_s3_file' ] ) || empty( $_FILES[ 'ea_wc_amazon_s3_file' ][ 'name' ] ) ) {
					wp_die( esc_html__( 'Please select a file to upload', 'woo-product-download-from-amazon-s3' ), esc_html__( 'Error', 'woo-product-download-from-amazon-s3' ), array( 'back_link' => TRUE ) );
				}

				$file = array(
					'bucket' => $_POST[ 'ea_wc_amazon_s3_bucket' ],
					'name'   => $_FILES[ 'ea_wc_amazon_s3_file' ][ 'name' ],
					'file'   => $_FILES[ 'ea_wc_amazon_s3_file' ][ 'tmp_name' ],
					'type'   => $_FILES[ 'ea_wc_amazon_s3_file' ][ 'type' ]
				);

				if ( $this->upload_file( $file ) ) {

					set_transient( 'ea_wc_amazon_s3_abs_path', $this->get_s3_absolute_path(), MINUTE_IN_SECONDS );
					set_transient( 'ea_wc_amazon_s3_file_name', trim( $file[ 'name' ] ), MINUTE_IN_SECONDS );
					set_transient( 'ea_wc_amazon_s3_bucket_name', trim( $file[ 'bucket' ] ), MINUTE_IN_SECONDS );

					wp_safe_redirect( add_query_arg( 'ea_wc_amazon_s3_success', '1', $_SERVER[ 'HTTP_REFERER' ] ) );
					exit;
				} else {

					delete_transient( 'ea_wc_amazon_s3_abs_path' );
					delete_transient( 'ea_wc_amazon_s3_file_name' );
					delete_transient( 'ea_wc_amazon_s3_bucket_name' );

					wp_die( esc_html__( 'Something went wrong during the upload process.', 'woo-product-download-from-amazon-s3' ), esc_html__( 'Error', 'woo-product-download-from-amazon-s3' ), array( 'back_link' => TRUE ) );
				}
			}

			public function upload_file( $file = array() ) {

				$s3     = new S3( $this->access_id, $this->secret_key, FALSE );
				$bucket = empty( $file[ 'bucket' ] ) ? $this->bucket : $file[ 'bucket' ];

				$resource           = $s3->inputFile( $file[ 'file' ] );
				$resource[ 'type' ] = $file[ 'type' ];

				$push_file = $s3->putObject( $resource, $bucket, $file[ 'name' ] );

				if ( $push_file ) {
					return TRUE;
				} else {
					return FALSE;
				}
			}

			public function get_s3_absolute_path() {

				$protocol = ( is_ssl() ? 'https://' : 'http://' );
				$url      = trim( get_option( 'ea_wc_amazon_s3_endpoint' ) );

				return trailingslashit( $protocol . $url );
			}

			public function plugin_action_links( $links ) {

				if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					$action_links = apply_filters( 'ea_wc_amazon_s3_action_links', array(
						'settings' => sprintf( '<a href="%1$s" title="%2$s">%2$s</a>', esc_url( add_query_arg( array(
							                                                                                       'page' => 'wc-settings',
							                                                                                       'tab'  => 'woo-product-download-from-amazon-s3'
						                                                                                       ), admin_url( 'admin.php' ) ) ), esc_attr__( 'Amazon S3 Settings', 'woo-product-download-from-amazon-s3' ) ),
					) );

					return array_merge( $action_links, $links );
				}

				return (array) $links;

			}

			public function file_download_method() {
				return 'from_ea_wc_amazon_s3';
			}

			public function admin_enqueue_scripts() {
				wp_enqueue_media();
				wp_enqueue_script( 'woo-product-download-from-amazon-s3', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/js/scripts.js', array( 'jquery' ), FALSE, TRUE );
			}

			public function buckets_iframe() {
				wp_iframe( array( $this, 'buckets_iframe_content' ) );
			}

			public function buckets_iframe_content( $type = 'file', $errors = NULL, $id = NULL ) {

				wp_enqueue_style( 'media' );

				$page     = isset( $_GET[ 'p' ] ) ? $_GET[ 'p' ] : 1;
				$per_page = 30;
				$offset   = $per_page * ( $page - 1 );
				$offset   = $offset < 1 ? 30 : $offset;
				$start    = isset( $_GET[ 'start' ] ) ? rawurldecode( $_GET[ 'start' ] ) : '';
				$bucket   = isset( $_GET[ 'bucket' ] ) ? rawurldecode( $_GET[ 'bucket' ] ) : FALSE;

				if ( ! $bucket ) {
					$buckets = $this->get_s3_buckets();
				} else {
					$this->bucket = $bucket;
					$files        = $this->get_s3_files( $start, $offset );
				}
				?>
				<script type="text/javascript">

					jQuery(function ($) {
						$(document.body).on('click', '.insert-s3', function (e) {
							e.preventDefault();
							var file = $(this).data('s3');
							$(parent.window.file_name_field).val(file);
							$(parent.window.file_path_field).val("<?php echo $this->get_s3_absolute_path() . trailingslashit( $this->bucket ); ?>" + file);
							parent.window.tb_remove();
						});
					});

				</script>
				<div style="margin: 20px 1em 1em; padding-right:20px;" id="media-items">
					<?php
						if ( ! $bucket ) { ?>
							<h3 class="media-title"><?php esc_html_e( 'Select a Bucket', 'woo-product-download-from-amazon-s3' ); ?></h3>
							<?php

							if ( is_array( $buckets ) ) {

								echo '<table class="wp-list-table widefat fixed striped" style="max-height: 500px;overflow-y:scroll;">';
								echo '<tr>';
								echo '<th>' . esc_html__( 'Bucket name', 'woo-product-download-from-amazon-s3' ) . '</th>';
								echo '<th>' . esc_html__( 'Actions', 'woo-product-download-from-amazon-s3' ) . ' </th>';
								echo '</tr>';

								foreach ( $buckets as $key => $bucket ) {
									echo '<tr>';
									echo '<td>' . $bucket . '</td>';
									echo '<td>';
									echo '<a href = "' . esc_url( add_query_arg( 'bucket', $bucket ) ) . '">' . esc_html__( 'Browse', 'woo-product-download-from-amazon-s3' ) . '</a>';
									echo '</td>';
									echo '</tr>';
								}
								echo '</table>';
							}

						} else {

							$back = admin_url( 'media-upload.php?post_id=' . absint( $_GET[ 'post_id' ] ) );

							if ( is_array( $files ) ) {
								$i           = 0;
								$total_items = count( $files );

								echo '<p><button class="button-secondary" onclick="history.back();">' . esc_html__( 'Go Back', 'woo-product-download-from-amazon-s3' ) . '</button></p>';

								echo '<table class="wp-list-table widefat fixed striped" style="max-height: 500px;overflow-y:scroll;">';

								echo '<tr>';
								echo '<th>' . esc_html__( 'File name', 'woo-product-download-from-amazon-s3' ) . '</th>';
								echo '<th>' . esc_html__( 'File size', 'woo-product-download-from-amazon-s3' ) . '</th>';
								echo '<th>' . esc_html__( 'Uploaded on', 'woo-product-download-from-amazon-s3' ) . '</th>';
								echo '<th>' . esc_html__( 'Actions', 'woo-product-download-from-amazon-s3' ) . '</th>';
								echo '</tr>';

								foreach ( $files as $key => $file ) {

									echo '<tr>';
									if ( $i == 0 ) {
										$first_file = $key;
									}

									if ( $i == 10 ) {
										$last_file = $key;
									}

									if ( $file[ 'name' ][ strlen( $file[ 'name' ] ) - 1 ] === '/' ) {
										continue; // Don't show folders
									}

									echo '<td>' . esc_html( $file[ 'name' ] ) . '</td>';
									echo '<td>' . size_format( $file[ 'size' ] ) . '</td>';
									echo '<td>' . human_time_diff( $file[ 'time' ] ) . ' ' . esc_html__( 'ago', 'woo-product-download-from-amazon-s3' ) . '</td>';

									echo '<td>';
									echo '<a class="insert-s3 button-secondary" href="#" data-s3="' . esc_attr( $file[ 'name' ] ) . '">' . esc_html__( 'Use File', 'woo-product-download-from-amazon-s3' ) . '</a>';
									echo '</td>';
									echo '</tr>';
									$i ++;
								}
								echo '</table>';
							}

							$base = admin_url( 'media-upload.php?post_id=' . absint( $_GET[ 'post_id' ] ) . '&tab=ea_wc_amazon_buckets' );

							if ( $bucket ) {
								$base = add_query_arg( 'bucket', $bucket, $base );
							}

							echo '<div class="s3-pagination tablenav">';
							echo '<div class="tablenav-pages alignright">';
							if ( isset( $_GET[ 'p' ] ) && $_GET[ 'p' ] > 1 ) {
								echo '<a class="page-numbers prev button-secondary" href="' . esc_url( remove_query_arg( 'p', $base ) ) . '">' . esc_html__( 'Start Over', 'woo-product-download-from-amazon-s3' ) . '</a>';
							}
							if ( $i >= 10 ) {
								echo '<a class="page-numbers next button-secondary" href="' . esc_url( add_query_arg( array(
									                                                                                      'p'     => $page + 1,
									                                                                                      'start' => $last_file
								                                                                                      ), $base ) ) . '">' . esc_html__( 'View More', 'woo-product-download-from-amazon-s3' ) . '</a>';
							}
							echo '</div>';
							echo '</div>';
						}
					?>
				</div>
				<?php
			}

			public function get_s3_buckets( $marker = NULL, $max = NULL ) {

				$s3 = new S3( $this->access_id, $this->secret_key, is_ssl(), $this->endpoint );

				return $s3->listBuckets();
			}

			public function get_s3_files( $marker = NULL, $max = NULL ) {

				$s3 = new S3( $this->access_id, $this->secret_key, is_ssl(), $this->endpoint );

				return $s3->getBucket( $this->bucket, NULL, $marker, $max );
			}

			public function media_tabs( $tabs ) {
				$tabs[ 'ea_wc_amazon_buckets' ] = esc_html__( 'Browse S3 Buckets', 'woo-product-download-from-amazon-s3' );
				$tabs[ 'ea_wc_amazon_upload' ]  = esc_html__( 'Upload to Amazon S3', 'woo-product-download-from-amazon-s3' );

				return $tabs;
			}

			public function upload_iframe() {
				wp_iframe( array( $this, 'upload_iframe_content' ) );
			}

			public function upload_iframe_content( $type = 'file', $errors = NULL, $id = NULL ) {

				wp_enqueue_style( 'media' );

				//$form_action_url = add_query_arg( array( 'ea_wc_amazon_s3_action' => 's3_upload' ), admin_url() );
				?>
				<style>
					.ea_wc_amazon_s3_errors {
						-webkit-border-radius : 2px;
						-moz-border-radius    : 2px;
						border-radius         : 2px;
						border                : 1px solid #E6DB55;
						margin                : 0 0 21px 0;
						background            : #FFFFE0;
						color                 : #333;
						}

					.ea_wc_amazon_s3_errors p {
						margin  : 10px 15px;
						padding : 0 10px;
						}
				</style>
				<script>
					jQuery(document).ready(function ($) {
						$('.woo-product-download-from-amazon-s3-insert').on('click', function (e) {
							e.preventDefault();

							var link = "<?php echo get_transient( 'ea_wc_amazon_s3_abs_path' ) ?>";
							var file = "<?php echo get_transient( 'ea_wc_amazon_s3_file_name' ) ?>";
							var bucket = "<?php echo trailingslashit( get_transient( 'ea_wc_amazon_s3_bucket_name' ) ); ?>";
							$(parent.window.file_name_field).val(file);
							$(parent.window.file_path_field).val(link + bucket + file);
							parent.window.tb_remove();
							$('.ea_wc_amazon_s3_errors').remove();
						});
					});
				</script>
				<div class="wrap">
					<form enctype="multipart/form-data" method="post" action="<?php echo esc_url( admin_url() ); ?>">
						<p>
							<select name="ea_wc_amazon_s3_bucket" id="ea_wc_amazon_s3_bucket">
								<?php foreach ( $this->get_s3_buckets() as $key => $bucket ) : ?>
									<option value="<?php echo esc_attr( $bucket ); ?>"><?php echo esc_html( $bucket ); ?></option>
								<?php endforeach; ?>
							</select>
							<label for="ea_wc_amazon_s3_bucket"><?php esc_html_e( 'Select a bucket to upload the file to', 'woo-product-download-from-amazon-s3' ); ?></label>
						</p>
						<p>
							<input type="file" name="ea_wc_amazon_s3_file">
						</p>

						<p>
							<input type="submit" name="ea_wc_amazon_s3_upload_submit" class="button-secondary"
							       value="<?php esc_attr_e( 'Upload to S3', 'woo-product-download-from-amazon-s3' ); ?>"/>
						</p>
						<?php wp_nonce_field( 'ea_wc_amazon_s3_upload_file' ); ?>
						<?php
							if ( ! empty( $_GET[ 'ea_wc_amazon_s3_success' ] ) && '1' == $_GET[ 'ea_wc_amazon_s3_success' ] ) {
								echo '<div class="ea_wc_amazon_s3_errors"><p class="ea_wc_amazon_s3_success">' . __( 'Success! <a href="#" class="woo-product-download-from-amazon-s3-insert">Insert uploaded file</a>.' ) . '</p></div>';
							}
						?>
					</form>
				</div>
				<?php
			}

			public function process_download( $file_path, $filename ) {

				if ( ! $this->is_aws_hosted_file( $file_path ) ) {

					$file_download_method = get_option( 'woocommerce_file_download_method', 'force' );

					// This Hook is used from /woocommerce/includes/class-wc-download-handler.php file
					do_action( 'woocommerce_download_file_' . $file_download_method, $file_path, $filename );
				} else {

					$file_path = str_replace( $this->get_s3_absolute_path(), '', $file_path );
					$file      = $this->get_s3_url( $file_path );

					$remote_header = get_headers( $file, TRUE );

					preg_match( '/\d{3}/', $remote_header[ 0 ], $remote_code );

					if ( $remote_code[ 0 ] == '404' || $remote_code[ 0 ] == '403' ) {
						$this->download_error( __( 'File not found. Please try again.', 'woocommerce' ) );
					}

					$this->download_headers( $file, $filename );
					$this->readfile_chunked( $file );
				}

				exit();
			}

			public function is_aws_hosted_file( $file_path ) {

				$parse_url = parse_url( $file_path );

				return ( trim( get_option( 'ea_wc_amazon_s3_endpoint' ) ) == $parse_url[ 'host' ] );
			}

			public function get_s3_url( $filename ) {

				$s3 = new S3( $this->access_id, $this->secret_key, is_ssl(), $this->endpoint );

				if ( FALSE !== strpos( $filename, '/' ) ) {
					$parts  = explode( '/', $filename );
					$bucket = $parts[ 0 ];
					if ( in_array( $bucket, $this->get_s3_buckets() ) ) {
						$filename = preg_replace( '#^' . $parts[ 0 ] . '/#', '', $filename, 1 );
					} else {
						$bucket = $this->bucket;
					}
				} else {
					$bucket = $this->bucket;
				}

				//return str_ireplace( 's3.amazonaws.com', self::$link, $s3->getAuthenticatedURL( $bucket, $filename, ( 60 * $expires ), FALSE, is_ssl() ) );
				return $s3->getAuthenticatedURL( $bucket, $filename, ( 1 * HOUR_IN_SECONDS ), FALSE, is_ssl() );
			}

			private function download_error( $message, $title = '', $status = 404 ) {
				if ( ! strstr( $message, '<a ' ) ) {
					$message .= ' <a href="' . esc_url( home_url() ) . '" class="wc-forward">' . __( 'Go to homepage', 'woocommerce' ) . '</a>';
				}
				wp_die( $message, $title, array( 'response' => $status ) );
			}

			private function download_headers( $file_path, $filename ) {
				$this->check_server_config();
				$this->clean_buffers();
				nocache_headers();

				header( "X-Robots-Tag: noindex, nofollow", TRUE );
				header( "Content-Type: " . $this->get_download_content_type( $filename ) );
				header( "Content-Description: File Transfer" );
				header( "Content-Disposition: attachment; filename=\"" . $filename . "\";" );
				header( "Content-Transfer-Encoding: binary" );

				if ( $size = @filesize( $file_path ) ) {
					header( "Content-Length: " . $size );
				}
			}

			private function check_server_config() {
				if ( function_exists( 'set_time_limit' ) && FALSE === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
					@set_time_limit( 0 );
				}
				if ( function_exists( 'get_magic_quotes_runtime' ) && get_magic_quotes_runtime() && version_compare( phpversion(), '5.4', '<' ) ) {
					set_magic_quotes_runtime( 0 );
				}
				if ( function_exists( 'apache_setenv' ) ) {
					@apache_setenv( 'no-gzip', 1 );
				}
				@ini_set( 'zlib.output_compression', 'Off' );
				@session_write_close();
			}

			private function clean_buffers() {
				if ( ob_get_level() ) {
					$levels = ob_get_level();
					for ( $i = 0; $i < $levels; $i ++ ) {
						@ob_end_clean();
					}
				} else {
					@ob_end_clean();
				}
			}

			private function get_download_content_type( $file_path ) {
				$file_extension = strtolower( substr( strrchr( $file_path, "." ), 1 ) );
				$ctype          = "application/force-download";

				foreach ( get_allowed_mime_types() as $mime => $type ) {
					$mimes = explode( '|', $mime );
					if ( in_array( $file_extension, $mimes ) ) {
						$ctype = $type;
						break;
					}
				}

				return $ctype;
			}

			public function readfile_chunked( $file ) {
				$chunksize = 1024 * 1024;
				$handle    = @fopen( $file, 'r' );

				if ( FALSE === $handle ) {
					return FALSE;
				}

				while ( ! @feof( $handle ) ) {
					echo @fread( $handle, $chunksize );

					if ( ob_get_length() ) {
						ob_flush();
						flush();
					}
				}

				return @fclose( $handle );
			}

			public function add_tab( $settings_tabs ) {
				$settings_tabs[ 'woo-product-download-from-amazon-s3' ] = esc_html__( 'Amazon S3 Settings', 'woo-product-download-from-amazon-s3' );

				return $settings_tabs;
			}

			public function update_settings() {
				woocommerce_update_options( $this->settings_options() );
			}

			public function settings_options() {

				$settings = array();

				$settings[] = array(
					'title' => esc_html__( 'Amazon S3 Settings', 'woo-product-download-from-amazon-s3' ),
					'type'  => 'title',
					'id'    => 'ea_wc_amazon_s3',
				);

				$settings[] = array(
					'name' => esc_html__( 'Amazon S3 Access Key ID', 'woo-product-download-from-amazon-s3' ),
					'desc' => esc_html__( 'After logging into your Amazon S3 account, click on "Security Credentials" in the sidebar. Scroll down to "Access Keys (Access Key ID and Secret Access Key)" and you will see your Access Key ID. Copy and paste it here.', 'woo-product-download-from-amazon-s3' ),
					'id'   => 'ea_wc_amazon_s3_key',
					'type' => 'text',
				);

				$settings[] = array(
					'name' => esc_html__( 'Amazon S3 Secret Key', 'woo-product-download-from-amazon-s3' ),
					'desc' => esc_html__( 'In the same Access Credentials area, your "Secret Key" will be hidden. You will need to click the "Show" link to see it. Copy and paste it here.', 'woo-product-download-from-amazon-s3' ),
					'id'   => 'ea_wc_amazon_s3_secret_key',
					'type' => 'text',
				);

				$settings[] = array(
					'name' => esc_html__( 'Amazon S3 EndPoint', 'woo-product-download-from-amazon-s3' ),
					'desc' => __( ' Amazon S3 Endpoint like: <code>s3-us-west-2.amazonaws.com</code>' ),
					'id'   => 'ea_wc_amazon_s3_endpoint',
					'type' => 'text',
				);
				$settings[] = array( 'type' => 'sectionend', 'id' => 'ea_wc_amazon_s3' );

				return apply_filters( 'ea_wc_amazon_s3_setting_options', $settings );
			}

			public function settings_fields() {
				woocommerce_admin_fields( $this->settings_options() );
			}
		}

		new EA_WC_Amazon_S3();

	endif;