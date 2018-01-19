<?php

class FrmUnitTest extends WP_UnitTestCase {

	protected $user_id = 0;

	protected $contact_form_key = 'contact-with-email';
	protected $contact_form_field_count = 10;

	protected $all_fields_form_key = 'all_field_types';
	protected $all_field_types_count = 50;

	protected $repeat_sec_form_key = 'rep_sec_form';
	protected $create_post_form_key = 'create-a-post';

	protected $is_pro_active = false;

	/**
	 * Ensure that the plugin has been installed and activated.
	 */
	function setUp() {
		parent::setUp();

		if ( is_multisite() ) {
			$this->is_pro_active = get_site_option( 'frmpro-authorized' );
		} else {
			$this->is_pro_active = get_option( 'frmpro-authorized' );
		}

		$this->frm_install();

		$this->factory->form = new Form_Factory( $this );
		$this->factory->field = new Field_Factory( $this );
		$this->factory->entry = new Entry_Factory( $this );

		$this->create_users();
	}

	function tearDown() {
		parent::tearDown();

		global $wp_version;
		if ( $wp_version <= 4.6 ) {
			// Prior to WP 4.7, the Formidable tables were deleted on tearDown and not restored with setUp
			delete_option( 'frm_options' );
			delete_option( 'frm_db_version' );
		}

		$this->empty_tables();
	}

	/**
	 * Some of the tests for FrmDb are triggering a transaction commit, preventing further tests from working.
	 * This is a temporary workaround until we review FrmDb tests in detail.
	 */
	private function empty_tables() {
		global $wpdb;
		$tables = $this->get_table_names();
		foreach ( $tables as $table ){
			$exists = $wpdb->get_var( 'DESCRIBE ' . $table );
			if ( $exists ) {
				$wpdb->query( "TRUNCATE $table" );
			}
		}
	}

	/**
	 * @covers FrmAppController::install()
	 */
	function frm_install() {
		if ( ! defined( 'WP_IMPORTING' ) ) {
			// set this to false so all our tests won't be done with this active
			define( 'WP_IMPORTING', false );
		}

		FrmHooksController::trigger_load_hook( 'load_admin_hooks' );
		FrmAppController::install();

		$this->do_tables_exist();
		$this->import_xml();
		$this->create_files();
	}

	function get_table_names() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'frm_fields', $wpdb->prefix . 'frm_forms',
			$wpdb->prefix . 'frm_items',  $wpdb->prefix . 'frm_item_metas',
		);
		if ( is_multisite() && is_callable( 'FrmProCopy::table_name' ) ) {
			$tables[] = FrmProCopy::table_name();
		}

		return $tables;
	}

	function do_tables_exist( $should_exist = true ) {
		global $wpdb;
		$method = $should_exist ? 'assertNotEmpty' : 'assertEmpty';
		foreach ( $this->get_table_names() as $table_name ) {
			$message = $table_name . ' table failed to ' . ( $should_exist ? 'install' : 'uninstall' );
			$this->$method( $wpdb->query( 'DESCRIBE ' . $table_name ), $message );
		}
	}

    function import_xml() {
        // install test data in older format
		add_filter( 'frm_default_templates_files', 'FrmUnitTest::install_data' );
        FrmXMLController::add_default_templates();

        $form = FrmForm::getOne( 'contact-db12' );
        $this->assertEquals( $form->form_key, 'contact-db12' );
    }

	function create_files() {
		if ( ! is_callable( 'FrmProFileImport::import_attachment' ) ) {
			return;
		}

		$single_file_upload_field = FrmField::getOne( 'single-file-upload-field' );
		$multi_file_upload_field = FrmField::getOne( 'multi-file-upload-field' );

		$file_urls = array(
			array(
				'val' => 'https://s3.amazonaws.com/fp.strategy11.com/images/knowledgebase/global-settings_enter-license1.png',
				'field' => $single_file_upload_field,
				'entry' => 'jamie_entry_key',
			),
			array(
				'val' => 'https://formidableforms.com/wp-content/uploads/formidable/formidablepro.real_estate_listings.2015-08-10.xml',
				'field' => $single_file_upload_field,
				'entry' => 'steph_entry_key',
			),
			array(
				'val' => array(
					'https://s3.amazonaws.com/fp.strategy11.com/images/knowledgebase/global-settings_enter-license1.png',
					'https://s3.amazonaws.com/fp.strategy11.com/images/knowledgebase/create-a-form_add-new.png',
					'https://formidableforms.com/wp-content/uploads/formidable/formidablepro.real_estate_listings.2015-08-10.xml',
				),
				'field' => $multi_file_upload_field,
				'entry' => 'jamie_entry_key',
			),
			array(
				'val' => 'https://formidableforms.com/wp-content/uploads/formidable/formidablepro.real_estate_listings.2015-08-10.xml',
				'field' => FrmField::getOne( 'file_upload_single' ),
				'entry' => 'many_files_key',
			),
			array(
				'val' => array(
					'https://cdn.formidableforms.com/wp-content/uploads/2016/11/goal-form.png',
					'https://cdn.formidableforms.com/wp-content/uploads/2016/11/goal-progress.png',
					'https://cdn.formidableforms.com/wp-content/uploads/2016/09/new-graph-types1.png',
				),
				'field' => FrmField::getOne( 'file_upload_multiple' ),
				'entry' => 'many_files_key',
			),
			array(
				'val' => array(
					'https://cdn.formidableforms.com/wp-content/uploads/2017/07/user-registration-multisite.jpeg',
					'https://cdn.formidableforms.com/wp-content/uploads/2017/07/lost-password-form.png',
					'https://cdn.formidableforms.com/wp-content/uploads/2017/07/login-form.png',
				),
				'field' => FrmField::getOne( 'file_upload_multiple_repeating' ),
				'entry' => 'file-repeat-child-one',
			),
			array(
				'val' => array(
					'https://cdn.formidableforms.com/wp-content/uploads/2016/11/normal-section-job-history-1.png',
					'https://cdn.formidableforms.com/wp-content/uploads/2016/11/repeating-section-job-history-1.png',
				),
				'field' => FrmField::getOne( 'file_upload_multiple_repeating' ),
				'entry' => 'file-repeat-child-two',
			),
		);

		$_REQUEST['csv_files'] = 1;
		foreach ( $file_urls as $values ) {
			$media_ids = FrmProFileImport::import_attachment( $values['val'], $values['field'] );

			if ( is_array( $values['val']) ) {
				$media_ids = explode(',', $media_ids );
			} else {
				$this->assertTrue( is_numeric( $media_ids ), 'The following file is not importing correctly: ' . $values[ 'val' ] );
			}

			// Insert into entries
			$entry_id = FrmEntry::get_id_by_key( $values['entry'] );
			FrmEntryMeta::add_entry_meta( $entry_id, $values['field']->id, null, $media_ids );
		}
	}

	function get_all_fields_for_form_key( $form_key ) {
		$field_totals = array(
			$this->all_fields_form_key => $this->is_pro_active ? $this->all_field_types_count : ( $this->all_field_types_count - 3 ),
			$this->create_post_form_key => 10,
			$this->contact_form_key => $this->contact_form_field_count,
			$this->repeat_sec_form_key => 3
		);
		$expected_field_num = isset( $field_totals[ $form_key ] ) ? $field_totals[ $form_key ] : 0;

		$form_id = $this->factory->form->get_id_by_key( $form_key );
		$fields = FrmField::get_all_for_form( $form_id, '', 'include' );

		$actual_field_num = count( $fields );
		$this->assertEquals( $actual_field_num, $expected_field_num, $actual_field_num . ' fields were retrieved for ' . $form_key . ' form, but ' . $expected_field_num . ' were expected. This could mean that certain fields were not imported correctly.');

		return $fields;
	}

	/**
	* Set the current user to 1
	*/
	function set_current_user_to_1( ) {
		$this->set_user_by_role( 'administrator' );
	}

	function set_current_user_to_username( $login ) {
		$user = get_user_by( 'login', $login );

		if ( $user ) {
			wp_set_current_user( $user->ID );
		}
	}

	/**
	 * Get a user by the specified role and set them as the current user
	 *
	 * @param string $role
	 *
	 * @return WP_User
	 */
    function set_user_by_role( $role ) {
	    $user = $this->get_user_by_role( $role );
	    wp_set_current_user( $user->ID );

	    $this->assertTrue( current_user_can( $role ), 'Failed setting the current user role' );

	    FrmAppHelper::maybe_add_permissions();

		return $user->ID;
    }

	/**
	 * Get a user of a specific role
	 *
	 * @param string $role
	 *
	 * @return WP_User
	 */
	public function get_user_by_role( $role ) {
		$users = get_users( array( 'role' => $role, 'number' => 1 ) );
		if ( empty( $users ) ) {
			$this->fail( 'No users with this role currently exist.' );
			$user = null;
		} else {
			$user = reset( $users );
		}

		return $user;
	}

	function go_to_new_post() {
		$new_post = $this->factory->post->create_and_get();
		$page = get_permalink( $new_post->ID );

		$this->set_front_end( $page );
		return $new_post->ID;
	}

	function set_front_end( $page = '' ) {
		if ( $page == '' ) {
			$page = home_url( '/' );
		}

		$this->clean_up_global_scope();
		$this->go_to( $page );
		$this->assertFalse( is_admin(), 'Failed to switch to the front-end' );
	}

	function set_admin_screen( $page = 'index.php' ) {
		global $current_screen;

		$screens = array(
			'index.php' => array( 'base' => 'dashboard', 'id' => 'dashboard' ),
			'admin.php?page=formidable' => array( 'base' => 'admin', 'id' => 'toplevel_page_formidable' ),
		);

		if ( $page == 'formidable-edit' ) {
			$form = $this->factory->form->get_object_by_id( $this->contact_form_key );
			$page = 'admin.php?page=formidable&frm_action=edit&id=' . $form->id;
			$screens[ $page ] = $screens['admin.php?page=formidable'];
		}

		$screen = $screens[ $page ];

		$_GET = $_POST = $_REQUEST = array();
		$GLOBALS['taxnow'] = $GLOBALS['typenow'] = '';
		$screen = (object) $screen;
		$hook = parse_url( $page );

		$GLOBALS['hook_suffix'] = $hook['path'];
		set_current_screen();

		$this->assertTrue( $current_screen->in_admin(), 'Failed to switch to the back-end' );
		$this->assertEquals( $screen->base, $current_screen->base, $page );

		FrmHooksController::trigger_load_hook();
	}

	function clean_up_global_scope() {
		parent::clean_up_global_scope();
		if ( isset( $GLOBALS['current_screen'] ) ) {
			unset( $GLOBALS['current_screen'] );
		}

		global $frm_vars;
		$frm_vars = array(
			'load_css'          => false,
			'forms_loaded'      => array(),
			'created_entries'   => array(),
			'pro_is_authorized' => false,
			'next_page'         => array(),
			'prev_page'         => array(),
		);

		if ( class_exists( 'FrmProEddController' ) ) {
			$frmedd_update  = new FrmProEddController();
			$frm_vars['pro_is_authorized'] = $frmedd_update->pro_is_authorized();
		}
	}

	function get_footer_output() {
        ob_start();
        do_action( 'wp_footer' );
        $output = ob_get_contents();
        ob_end_clean();

		return $output;
	}

    static function install_data() {
        return array(
        	dirname( __FILE__ ) . '/testdata.xml',
			dirname( __FILE__ ) . '/free-form.xml',
	        dirname( __FILE__ ) . '/editform.xml',
			dirname( __FILE__ ) . '/file-upload.xml',
        );
    }

	static function generate_xml( $type, $xml_args ) {
		// Code copied from FrmXMLController::generate_xml
		global $wpdb;

		$type = (array) $type;
		if ( in_array( 'items', $type) && ! in_array( 'forms', $type) ) {
			// make sure the form is included if there are entries
			$type[] = 'forms';
		}

		if ( in_array( 'forms', $type) ) {
			// include actions with forms
			$type[] = 'actions';
		}

		$tables = array(
			'items'     => $wpdb->prefix .'frm_items',
			'forms'     => $wpdb->prefix .'frm_forms',
			'posts'     => $wpdb->posts,
			'styles'    => $wpdb->posts,
			'actions'   => $wpdb->posts,
		);

		$defaults = array( 'ids' => false );
		$args = wp_parse_args( $xml_args, $defaults );

		//make sure ids are numeric
		if ( is_array( $args['ids'] ) && ! empty( $args['ids'] ) ) {
			$args['ids'] = array_filter( $args['ids'], 'is_numeric' );
		}

		$records = array();

		foreach ( $type as $tb_type ) {
			$where = array();
			$join = '';
			$table = $tables[ $tb_type ];

			$select = $table .'.id';
			$query_vars = array();

			switch ( $tb_type ) {
                case 'forms':
                    //add forms
                    if ( $args['ids'] ) {
						$where[] = array( 'or' => 1, $table . '.id' => $args['ids'], $table .'.parent_form_id' => $args['ids'] );
                	} else {
						$where[ $table . '.status !' ] = 'draft';
                	}
                break;
                case 'actions':
                    $select = $table .'.ID';
					$where['post_type'] = FrmFormActionsController::$action_post_type;
                    if ( ! empty($args['ids']) ) {
						$where['menu_order'] = $args['ids'];
                    }
                break;
                case 'items':
                    //$join = "INNER JOIN {$wpdb->prefix}frm_item_metas im ON ($table.id = im.item_id)";
                    if ( $args['ids'] ) {
						$where[ $table . '.form_id' ] = $args['ids'];
                    }
                break;
                case 'styles':
                    // Loop through all exported forms and get their selected style IDs
                    $form_ids = $args['ids'];
                    $style_ids = array();
                    foreach ( $form_ids as $form_id ) {
                        $form_data = FrmForm::getOne( $form_id );
                        // For forms that have not been updated while running 2.0, check if custom_style is set
                        if ( isset( $form_data->options['custom_style'] ) ) {
                            $style_ids[] = $form_data->options['custom_style'];
                        }
                        unset( $form_id, $form_data );
                    }
                    $select = $table .'.ID';
                    $where['post_type'] = 'frm_styles';

                    // Only export selected styles
                    if ( ! empty( $style_ids ) ) {
                        $where['ID'] = $style_ids;
                    }
                break;
                default:
                    $select = $table .'.ID';
                    $join = ' INNER JOIN ' . $wpdb->postmeta . ' pm ON (pm.post_id=' . $table . '.ID)';
                    $where['pm.meta_key'] = 'frm_form_id';

                    if ( empty($args['ids']) ) {
                        $where['pm.meta_value >'] = 1;
                    } else {
                        $where['pm.meta_value'] = $args['ids'];
                    }
                break;
			}

			$records[ $tb_type ] = FrmDb::get_col( $table . $join, $where, $select );
			unset($tb_type);
		}

		$xml_header = '<?xml version="1.0" encoding="' . esc_attr( get_bloginfo('charset') ) . "\" ?>\n";
		ob_start();
		include(FrmAppHelper::plugin_path() .'/classes/views/xml/xml.php');
		$xml_body = ob_get_contents();
		ob_end_clean();

		$xml = $xml_header . $xml_body;

		$cwd = getcwd();
		$path = "$cwd" .'/'. "temp.xml";
		@chmod( $path,0755 );
		$fw = fopen( $path, "w" );
		fputs( $fw,$xml, strlen( $xml ) );
		fclose( $fw );

		return $path;
	}

	/**
	 * Create an administrator, editor, and subscriber
	 *
	 * @since 2.0
	 */
	private function create_users() {

		$admin_args = array(
			'user_login'  =>  'admin',
			'user_email' => 'admin@mail.com',
			'user_pass' => 'admin',
			'role' => 'administrator',
		);
		$admin = $this->factory->user->create_object( $admin_args );
		$this->assertNotEmpty( $admin );

		$editor_args = array(
			'user_login'  =>  'editor',
			'user_email' => 'editor@mail.com',
			'user_pass' => 'editor',
			'role' => 'editor',
		);
		$editor = $this->factory->user->create_object( $editor_args );
		$this->assertNotEmpty( $editor );

		$subscriber_args = array(
			'user_login'  =>  'subscriber',
			'user_email' => 'subscriber@mail.com',
			'user_pass' => 'subscriber',
			'role' => 'subscriber',
		);
		$subscriber = $this->factory->user->create_object( $subscriber_args );
		$this->assertNotEmpty( $subscriber );

	}

	protected function run_private_method( $method, $args ) {
		$this->check_php_version( '5.3' );
		$m = new ReflectionMethod( $method[0], $method[1] );
		$m->setAccessible( true );
		return $m->invokeArgs( is_string( $method[0] ) ? null : $method[0], $args );
	}

	/**
	* Skip this if running < php 5.3
	*/
	protected function get_private_property( $object, $property ) {
		$this->check_php_version( '5.3' );
		$rc = new ReflectionClass( $object );
		$p = $rc->getProperty( $property );
		$p->setAccessible( true );
		return $p->getValue( $object );
	}

	protected function check_php_version( $required ) {
		if ( version_compare( phpversion(), $required, '<' ) ) {
			$this->markTestSkipped( 'Test requires PHP > ' . $required );
		}
	}
}
