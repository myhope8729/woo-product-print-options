<?php
/*
* Plugin Name: WooCommerce Product Print Options
* Description: Print options for products
* Version: 1.0.2
* Plugin URI: 
* Author: myhope1227
* 
*/ 
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'woo_print_options' ) ):
class woo_print_options{

	public static function plugin_activate(){
		global $wpdb;
	    $print_services = $wpdb->prefix . 'print_services';

	    $service_sql = "CREATE TABLE `".$print_services."` (
	                `service_id` int(11) NOT NULL AUTO_INCREMENT,
	                `service_name` varchar(255) DEFAULT '',
	                `attr_name` varchar(30) DEFAULT '',
	                `attr_label` varchar(30) DEFAULT '',
	                `attr_value_slug` varchar(30) DEFAULT '',
	                `attr_value_name` varchar(30) DEFAULT '',
	                PRIMARY KEY (`service_id`)
	        ) ".$wpdb->get_charset_collate()." ;";

	    if (!function_exists('dbDelta')) {
	        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	    }

	    dbDelta($service_sql);
	}

	public static function plugin_deactivate(){
		global $wpdb;
	    $print_services = $wpdb->prefix . 'print_services';

	    $wpdb->query( "DROP TABLE IF EXISTS ".$print_services );
	    delete_option("print_type_options");
	}

	public function instance(){
		add_action( 'admin_menu', array($this, 'plugin_admin_page') );
		add_action( 'wp_ajax_get_attribute_values', array($this, 'get_attribute_values' ));
		add_action( 'wp_ajax_get_frontend_template', array($this, 'get_frontend_template' ));
		add_action( 'wp_ajax_get_colors', array($this, 'get_colors' ));
		add_action( 'init', array($this, 'set_frontend_hook'), 10, 2 );
		wp_enqueue_style( "woo-print-option-style", plugin_dir_url( __FILE__ ).'assets/frontend.css', null, null);
		wp_enqueue_script( 'wc-print-option-script', plugin_dir_url( __FILE__ ) . 'assets/frontend.js', array( 'jquery'), null, true );
	} 

	public function set_frontend_hook(){
		add_filter( 'woocommerce_locate_template', array($this, 'plugin_woocommerce_locate_template'), 10, 3 );
		remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20);
		add_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 5);
		add_filter( 'woocommerce_available_variation', array($this, 'plugin_add_service_data' ));		
		add_filter('woocommerce_add_cart_item_data', array($this, 'plugin_add_item_data'),10,3);
		add_filter('woocommerce_get_item_data', array($this, 'plugin_add_item_meta'),10,2);
		add_action( 'woocommerce_checkout_create_order_line_item', array($this, 'plugin_add_custom_order_line_item_meta'),10,4 );
		add_action( 'woocommerce_before_calculate_totals', array($this, 'plugin_add_print_price'), 20, 1);
	}

	public function plugin_add_print_price( $cart ) {

	    // This is necessary for WC 3.0+
	    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
	        return;

	    // Avoiding hook repetition (when using price calculations for example)
	    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 )
	        return;

	    foreach ( $cart->get_cart() as $item ) {
	    	if (isset($item['price_with_print'])){
	    		$item['data']->set_price( $item['price_with_print'] );	
	    	}	        
	    }
	}

	public function plugin_add_item_data($cart_item_data, $product_id, $variation_id)
	{
	    if(isset($_REQUEST['services'])){
	        $cart_item_data['services'] = sanitize_text_field($_REQUEST['services']);
	    }
	    if (isset($_REQUEST['colors'])){
	    	$cart_item_data['colors'] = sanitize_text_field($_REQUEST['colors']);
	    }
	    if (isset($_REQUEST['price_with_print'])){
	    	$cart_item_data['price_with_print'] = sanitize_text_field($_REQUEST['price_with_print']);	
	    }

	    return $cart_item_data;
	}

	public function plugin_add_item_meta($item_data, $cart_item)
	{
		global $product, $wpdb;
		$tbl_name = $wpdb->prefix . 'print_services';

	    if(array_key_exists('services', $cart_item))
	    {
	        $custom_details = $cart_item['services'];

	        $sql = "SELECT * FROM {$tbl_name} WHERE service_id={$custom_details}";
	        $services = $wpdb->get_results($sql, ARRAY_N);
	        $current_service = $services[0];

	        $options = get_option('print_type_options','');
			$optionArray = unserialize($options);
			$color_name = $optionArray[$custom_details][$cart_item['colors']]["name"];

	        $item_data[] = array(
	            'key'   => 'Technologie tisku',
	            'value' => $current_service[1]." - ".$color_name
	        );
	    }

	    return $item_data;
	}

	public function wdm_add_custom_order_line_item_meta($item, $cart_item_key, $values, $order)
	{
	    if(array_key_exists('services', $values))
	    {
	        $item->add_meta_data('_services_name',$values['services']);
	    }
	}

	public function plugin_add_service_data($variation){
		global $product, $wpdb;
		$tbl_name = $wpdb->prefix . 'print_services';
		$attributes = $product->get_attributes();
		$variation['service_list'] = "";
		$services_html = "
			<div class='select-wrapper'>\r\n
				<select id='services' name='services'>\r\n
					<option value=''>zvolte technologii</option>\r\n";
		foreach ($attributes as $key => $attribute) {
			$slugs =  wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'slugs' ) ) ;
			foreach ($slugs as $ind => $slug) {
				$sql = "SELECT * FROM {$tbl_name} WHERE attr_name = '".str_replace("pa_", "", $attribute->get_name())."' AND attr_value_slug='".$slug."'";
				$tmp_services = $wpdb->get_results($sql, ARRAY_N);
				if (sizeof($tmp_services) > 0){
					foreach ($tmp_services as $key => $service) {
						$services_html = $services_html."<option value='".$service[0]."'>".$service[1]."</option>\r\n";
					}
				}
			}
		}
		$services_html = $services_html."</select>\r\n</div>";
		$variation['service_list'] = $services_html;
		return $variation;
	}

	public function plugin_admin_page() {
		add_menu_page('WooCommerce Product print services', 'Print services', 'manage_options', 'print_services', array($this, 'plugin_setting'));
		add_submenu_page('print_services', 'WooCommerce Product print options', 'Print options', 'manage_options','print_options', array($this, 'plugin_print_options'));
		add_submenu_page(NULL, 'Edit print service', 'Edit print service', 'manage_options', 'edit_service', array($this, 'plugin_edit_service'));
		add_submenu_page(NULL, 'Delete print service', 'Delete print service', 'manage_options', 'delete_service', array($this, 'plugin_delete_service'));
	}

	public function get_attribute_values(){
		$attr_name = $_REQUEST['attr_name'];
		$attr_values = get_terms(array('taxonomy' => "pa_".$attr_name));
		echo json_encode($attr_values);
		exit;
	}

	public function get_colors(){
		$service_id = $_REQUEST['service_id'];
		$options = get_option('print_type_options','');
		$optionArray = unserialize($options);
		echo json_encode($optionArray[$service_id]);
		exit;
	}

	public function get_frontend_template(){
		global $wpdb;
		$tbl_name = $wpdb->prefix . 'print_services';
		$params = $_REQUEST['params'];
		$services_html = "
			<div class='select-wrapper'>\r\n
				<select id='services'>\r\n
					<option value=''>zvolte technologii</option>\r\n";
		foreach ($params as $key => $param) {
			$sql = "SELECT * FROM {$tbl_name} WHERE attr_name = '".str_replace("pa_", "", $key)."' AND attr_value_slug='".$param."'";
			$tmp_services = $wpdb->get_results($sql, ARRAY_N);
			if (sizeof($tmp_services) > 0){
				foreach ($tmp_services as $key => $service) {
					$services_html = $services_html."<option value='".$service[0]."'>".$service[1]."</option>\r\n";
				}
			}
		}
		$services_html = $services_html."</select>\r\n</div>";
		echo $services_html;
		exit;
	}

	public function plugin_delete_service(){
		$service_id = $_REQUEST['service_id'];
		global $wpdb;
		$print_services = $wpdb->prefix . 'print_services';
		$sql = "DELETE FROM {$print_services} WHERE service_id={$service_id}";
		$wpdb->query($sql);
		wp_redirect(admin_url( 'admin.php?page=print_services'));
	}	

	public function plugin_edit_service(){
		$service_id = $_REQUEST['service_id'];
		global $wpdb;
		$print_services = $wpdb->prefix . 'print_services';

		if (isset($_REQUEST['save_service'])){
			$service_name = $_REQUEST['service_name'];
			$attr_name = $_REQUEST['attribute_name'];
			$attr_value = $_REQUEST['attribute_value'];
			$label_arr = wc_get_attribute_taxonomy_labels();

			$term_obj = get_term_by('slug', $attr_value, 'pa_'.$attr_name);

			$wpdb->update($print_services, array(
				'service_name' => $service_name,
				'attr_name' => $attr_name,
				'attr_label' => $label_arr[$attr_name],
				'attr_value_slug' => $attr_value,
				'attr_value_name' => $term_obj->name,
			), array('service_id'=>$service_id));

			wp_redirect(admin_url( 'admin.php?page=print_services'));
		}

		$sql = "SELECT * FROM {$print_services} WHERE service_id=".$service_id;
		$service_obj_arr = $wpdb->get_results($sql);
		$service_obj = $service_obj_arr[0];
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		$attr_values = get_terms(array('taxonomy' => "pa_".$service_obj->attr_name));
?>
	<div class="wrap woocommerce">
		<h1>Print Service</h1>

		<br class="clear">
		<div id="col-container">
			<div id="col-left">
				<div class="col-wrap">
					<div class="form-wrap">
						<h2>Edit a print service</h2>
						<form method="post" action="">
							<div class="form-field">
								<label for="service_name">Service name</label>
								<input name="service_name" id="service_name" type="text" value="<?php echo $service_obj->service_name;?>">
								<p class="description">Name for the Service.</p>
							</div>
							<div class="form-field">
								<label for="attribute_name">Attribute name</label>
								<select name="attribute_name" id="attribute_name">
								<?php
								foreach ($attribute_taxonomies as $tax_ind => $taxonomy) {
								?>
									<option value="<?php echo $taxonomy->attribute_name;?>" <?php echo (($service_obj->attr_name == $taxonomy->attribute_name)?'selected':'');?>>
										<?php echo $taxonomy->attribute_label;?>
									</option>
								<?php
								}
								?>
								</select>
								<p class="description">Select attribute name from dropdown</p>
							</div>
							<div class="form-field">
								<label for="attribute_value">Attribute value</label>
								<select name="attribute_value" id="attribute_value">
								<?php
								foreach ($attr_values as $attr_ind => $attribute) {
								?>
									<option value="<?php echo $attribute->slug;?>" <?php echo (($service_obj->attr_value_slug == $attribute->slug)?'selected':'');?>>
										<?php echo $attribute->name;?>
									</option>
								<?php
								}
								?>
								</select>
								<p class="description">Select attribute value for service.</p>
							</div>
							<p class="submit">
								<input type="submit" name="save_service" id="submit" class="button button-primary" value="Save a service"/>
							</p>
						</form>
					</div>
				</div>
			</div>
<script type="text/javascript">
	jQuery(document).ready(function($){
		$("#attribute_name").change(function(){
			var attr_name = $(this).val();
			$.ajax({
				url:ajaxurl,
				method:'POST',
				data:{'action':'get_attribute_values', 'attr_name' : attr_name},
				dataType: 'json',
				success:function(terms){
					$("#attribute_value").empty();
					$.each(terms, function(key,term){
						$("#attribute_value").append("<option value='" + term.slug +"'>" + term.name + "</option>");
					});
				}
			})
		});
	});
</script>
<?php
	}

	public function plugin_setting(){
		global $wpdb;
		$print_services = $wpdb->prefix . 'print_services';
		if (isset($_REQUEST['add_service'])){
			$service_name = $_REQUEST['service_name'];
			$attr_name = $_REQUEST['attribute_name'];
			$attr_value = $_REQUEST['attribute_value'];
			$label_arr = wc_get_attribute_taxonomy_labels();

			$term_obj = get_term_by('slug', $attr_value, 'pa_'.$attr_name);

			$wpdb->insert($print_services, array(
				'service_name' => $service_name,
				'attr_name' => $attr_name,
				'attr_label' => $label_arr[$attr_name],
				'attr_value_slug' => $attr_value,
				'attr_value_name' => $term_obj->name,
			), array( '%s','%s', '%s', '%s', '%s'));
		}
        $sql = "SELECT * FROM {$print_services}";

        $service_arr = $wpdb->get_results($sql);

		$attribute_taxonomies = wc_get_attribute_taxonomies();

		foreach ($attribute_taxonomies as $key => $attribute_taxonomy) {
			$first_attribute = $attribute_taxonomy;	
			break;
		}

		$f_attribute_array = get_terms(array('taxonomy' => "pa_".$first_attribute->attribute_name));
?>
	<div class="wrap woocommerce">
		<h1>Print Services</h1>

		<br class="clear">
		<div id="col-container">
			<div id="col-right">
				<div class="col-wrap">
					<table class="widefat attributes-table wp-list-table ui-sortable" style="width:100%">
						<thead>
							<tr>
								<th scope="col">
									Service Name
								</th>
								<th scope="col">
									Attribute Name
								</th>
								<th scope="col">
									Attribute Value
								</th>
								<th scope="col">
									Edit
								</th>
								<th scope="col">
									Delete
								</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($service_arr as $key => $service) {
						?>
							<tr>
								<td><?php echo $service->service_name;?></td>
								<td><?php echo $service->attr_label;?></td>
								<td><?php echo $service->attr_value_name;?></td>
								<td>
									<a href="<?php echo admin_url( 'admin.php?page=edit_service&service_id='.$service->service_id); ?>">Edit</a> 
								</td>
								<td>
									<a href="<?php echo admin_url( 'admin.php?page=delete_service&service_id='.$service->service_id); ?>">Delete</a> 
								</td>
							</tr>
						<?php
						}?>
						</tbody>
					</table>
				</div>
			</div>
			<div id="col-left">
				<div class="col-wrap">
					<div class="form-wrap">
						<h2>Add a new print service</h2>
						<form method="post" action="">
							<div class="form-field">
								<label for="service_name">Service name</label>
								<input name="service_name" id="service_name" type="text" value="">
								<p class="description">Name for the Service.</p>
							</div>
							<div class="form-field">
								<label for="attribute_name">Attribute name</label>
								<select name="attribute_name" id="attribute_name">
								<?php
								foreach ($attribute_taxonomies as $tax_ind => $taxonomy) {
								?>
									<option value="<?php echo $taxonomy->attribute_name;?>"><?php echo $taxonomy->attribute_label;?></option>
								<?php
								}
								?>
								</select>
								<p class="description">Select attribute name from dropdown</p>
							</div>
							<div class="form-field">
								<label for="attribute_value">Attribute value</label>
								<select name="attribute_value" id="attribute_value">
								<?php
								foreach ($f_attribute_array as $attr_ind => $attribute) {
								?>
									<option value="<?php echo $attribute->slug;?>"><?php echo $attribute->name;?></option>
								<?php
								}
								?>
								</select>
								<p class="description">Select attribute value for service.</p>
							</div>
							<p class="submit">
								<input type="submit" name="add_service" id="submit" class="button button-primary" value="Add a service"/>
							</p>
						</form>
					</div>
				</div>
			</div>
<script type="text/javascript">
	jQuery(document).ready(function($){
		$("#attribute_name").change(function(){
			var attr_name = $(this).val();
			$.ajax({
				url:ajaxurl,
				method:'POST',
				data:{'action':'get_attribute_values', 'attr_name' : attr_name},
				dataType: 'json',
				success:function(terms){
					$("#attribute_value").empty();
					$.each(terms, function(key,term){
						$("#attribute_value").append("<option value='" + term.slug +"'>" + term.name + "</option>");
					});
				}
			})
		});
	});
</script>
<?php
	}

	public function plugin_print_options(){

		$options = get_option('print_type_options','');
		$optionArray = unserialize($options);

		global $wpdb;
		$print_services = $wpdb->prefix . 'print_services';
		
        $sql = "SELECT * FROM {$print_services}";

        $service_arr = $wpdb->get_results($sql);

		if (isset($_REQUEST['add_color'])){
			$print_service = $_REQUEST['print_service'];
			$color_name = $_REQUEST['color_name'];
			if (isset($optionArray[$print_service])){
				$optionArray[$print_service][$color_name] = array("name" => $color_name);
			}else{
				$optionArray[$print_service] = array($color_name => array("name" => $color_name));
			}
			update_option('print_type_options', serialize($optionArray));
		}

		if (isset($_REQUEST['save'])){
			$print_service = $_REQUEST['print_service'];
			$color_name = $_REQUEST['current_color'];
			if (isset($_REQUEST['from'])){
				$from_array = $_REQUEST['from'];
				$to_array = $_REQUEST['to'];
				$price_array = $_REQUEST['price'];
				$preparation_fee = $_REQUEST['preparation'];
				$range_array = array();
				foreach ($from_array as $ind => $from) {
					$range_array[$ind]['from'] = $from;
					$range_array[$ind]['to'] = $to_array[$ind];
					$range_array[$ind]['price'] = $price_array[$ind];
				}
				$optionArray[$print_service][$color_name]['range'] = $range_array;
			}
			$optionArray[$print_service][$color_name]['preparation'] = $preparation_fee;
			update_option('print_type_options', serialize($optionArray));
		}

	    $print_service = isset($_REQUEST['print_service'])?$_REQUEST['print_service']:$service_arr[0]->service_id;
?>
	<form action="" method="post" id="woo_print_options">
		<div class="wrap">
			<h2>Woocommerce Product print options</h2>
			<table class="form-table">
				<tbody>
					<tr>
						<th>
							<label for="print_service" style="float: left;margin:8px 20px 0 0;">Print Services</label>
							<select name="print_service" id="print_service" class="regular-text">
						<?php
							foreach ($service_arr as $key => $service) {
								echo '<option value="'.$service->service_id.'"'.(($print_service == $service->service_id)?'selected':'').'>'.$service->service_name.'</option>';
							}
						?>
							</select>
						</th>
					</tr>
					<tr>
						<td>
							<input type="text" name="color_name"/>&nbsp;&nbsp;<input type="submit" class="button" name="add_color" value="Add new color"/>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
<?php
		global $po_active_tab;
		if (isset($optionArray[$print_service])){
			$colors = $optionArray[$print_service];
			if (!empty($colors)){
				$colorSlugArray = array_keys($colors);
			}
			
			$po_active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $colorSlugArray[0];
			add_action('po_settings_tab', array($this, 'po_color_tab'));
			add_action('po_settings_content', array($this, 'po_color_tab_content'));
		}
?>
		<h2 class="nav-tab-wrapper">
<?php
			do_action( 'po_settings_tab' );
?>
		</h2>
<?php
			do_action( 'po_settings_content' );
?>
	</form>
<script>
jQuery(document).ready(function($){
	$("#print_service").change(function(){
		var print_type = $(this).val();
		document.location.href = "<?php echo admin_url( 'admin.php?page=print_options'); ?>" + "&print_service=" + print_type;
	});
	$(".add_range").click(function(e){
		e.preventDefault();
		e.stopPropagation();
		$(".attributes-table").append(
			'<tr>\
				<td><input type="text" name="from[]" value=""/></td>\
				<td><input type="text" name="to[]" value=""/></td>\
				<td><input type="text" name="price[]" value=""/></td>\
			</tr>');
	});
});
</script>
<?php 
	}

	public function po_color_tab(){
		global $wpdb, $po_active_tab; 

		$print_services = $wpdb->prefix . 'print_services';
	
        $sql = "SELECT * FROM {$print_services}";
        $service_arr = $wpdb->get_results($sql);

	    $print_service = isset($_REQUEST['print_service'])?$_REQUEST['print_service']:$service_arr[0]->service_id;

		$options = get_option('print_type_options','');
		$optionArray = unserialize($options);

		$colors = $optionArray[$print_service];
		if ($po_active_tab != ''){
			foreach ($colors as $key => $color) {
?> 
		<a class="nav-tab <?php echo $po_active_tab == $key ? 'nav-tab-active' : ''; ?>" href="<?php echo admin_url( 'admin.php?page=print_options&print_service='.$print_service.'&tab='.$key); ?>"><?php _e( $color['name'], 'po' ); ?> </a>
<?php
			}
		}
	}

	public function po_color_tab_content(){
		global $po_active_tab; 

		$terms_array = array(
	        'taxonomy' => 'pa_moznosti-potisku', 
	        'hide_empty' => false
	    );
	    $pa_printtypes = get_terms( $terms_array );
	    $print_service = isset($_REQUEST['print_service'])?$_REQUEST['print_service']:$pa_printtypes[0]->term_id;

		$options = get_option('print_type_options','');
		$optionArray = unserialize($options);

		$colors = $optionArray[$print_service];
?>
		<h3><?php _e( $colors[$po_active_tab]['name'], 'op' ); ?></h3>
		<table class="widefat attributes-table wp-list-table">
			<thead>
				<tr>
					<th colspan="3" class="text-right"><button class="add_range button">Add Range</button></th>
				</tr>
				<tr>
					<th scope="col">From</th>
					<th scope="col">To</th>
					<th scope="col">Price per pcs</th>
				</tr>
			</thead>
			<tbody>
			<?php
				if (isset($colors[$po_active_tab]['range']) && sizeof($colors[$po_active_tab]['range']) > 0){
					foreach ($colors[$po_active_tab]['range'] as $ind => $range) {
			?>
				<tr>
					<td><input type="text" name="from[]" value="<?php echo $range['from'];?>"/></td>
					<td><input type="text" name="to[]" value="<?php echo $range['to'];?>"/></td>
					<td><input type="text" name="price[]" value="<?php echo $range['price'];?>"/></td>
				</tr>
			<?php
					}
				}
			?>
			</tbody>
		</table>
		<p><label class="text-label">Prepress preparation</label>&nbsp;<input type="text" name="preparation" value="<?php echo $colors[$po_active_tab]['preparation'];?>"/></p>
		<input type="hidden" name="current_color" value="<?php echo $po_active_tab;?>"/>
		<p class="submit">
			<input type="submit" class="button" name="save" value="Save range"/>
		</p>
<?php
	}

	public function plugin_plugin_path() {

	  // gets the absolute path to this plugin directory

	  return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	public function plugin_woocommerce_locate_template( $template, $template_name, $template_path ) {
		global $woocommerce;

		$_template = $template;

		if ( ! $template_path ) $template_path = $woocommerce->template_url;

		$plugin_path  = $this->plugin_plugin_path() . '/woocommerce/';

		$template = locate_template(

			array(
				$template_path . $template_name,
				$template_name
			)
		);

		if ( ! $template && file_exists( $plugin_path . $template_name ) )
			$template = $plugin_path . $template_name;

		if ( ! $template )
			$template = $_template;

		return $template;
	}
}

$wpo_object=new woo_print_options(); 
$wpo_object->instance();
register_activation_hook( __FILE__, array('woo_print_options', 'plugin_activate') );
register_deactivation_hook( __FILE__, array('woo_print_options', 'plugin_deactivate') );

endif;