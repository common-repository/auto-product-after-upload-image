<?php
/**
 * Plugin Name: Auto Product After Upload Image
 * Plugin URI: #
 * Description: As name. Plugin allow create multiple at once by Image uploaded
 * Version: 2024.04.21
 * Author: Quyle91
 * Author URI: https://quyle91.github.io
 * License: GPLv2 or later
 */



// 0/8/12/2020 13h:50pm



if(!class_exists('APAUI')){
	class APAUI	{
		//apaui
		function __construct(){			

			if(!function_exists('add_shortcode')) {
                    return;
            }

            if(!is_admin()){
            	return;
            }
            add_action( 'admin_enqueue_scripts', array($this,'enqueue_scripts_and_styles' ));
            add_action(	'admin_menu', array($this,'apaui_create_menu'));    

            if(!class_exists( 'WooCommerce' ) ){
				return;
			}
            add_action( 'add_attachment', array($this,'auto_post_after_image_upload' ) );
            if ( ! function_exists( 'post_exists' ) ) {
			    require_once( ABSPATH . 'wp-admin/includes/post.php' );
			}
		}

		function get_data_tax(){
			$apaui_tax = get_option('apaui_tax');
	    	$zdata 	= explode(",", $apaui_tax);
	    	$tdata = array();
	    	$temp = "";
	    	if(!empty($zdata)){
	    		foreach ($zdata as $key => $value) {
	    			if ($temp !== explode("|", $value)[0]){
	    				$tdata[explode("|", $value)[0]] = array(explode("|", $value)[1]);
						$temp = explode("|", $value)[0];
	    			}else{
	    				$tdata[explode("|", $value)[0]][] = explode("|", $value)[1];
	    			}
	    		}
	    	}
	    	return $tdata;
		}

		function get_data_updatepost(){
			$apaui_updatepost = get_option('apaui_updatepost');
	        $apaui_updatepost = explode(",", htmlentities($apaui_updatepost)); 
			$updatepost_arr = array();
    		foreach ($apaui_updatepost as $key => $value) {
    			if(!empty(explode("|", $value)[1])){
    				$updatepost_arr[explode("|", $value)[0]] = explode("|", $value)[1];
    			}else{
    				$updatepost_arr[explode("|", $value)[0]] = "";
    			}		        			
    		}
    		return $updatepost_arr;
		}

		function get_data_custom_taxonomy(){
			$data_tax = $this->get_data_tax();
			$return = array();
			foreach ($data_tax as $key => $value) {
				if (!in_array($key, array('product_type','product_tag','product_cat','product_visibility'))) {
					$return[]= array($key=>$value);
				}
			}
			return $return;
		}
		function auto_post_after_image_upload($attachId){
			if (empty(get_option("apaui_enable")) or !get_option("apaui_enable") =="enabled"){
	    		return $attachId;
	    	}
	    	switch (get_option('apaui_mode')) {
	    		case 'apaui_mode_2':
	    			$this->mode_v2($attachId);
	    			break;
	    		default:
	    			$this->mode_v1($attachId);
	    			break;
	    	}
		}
		function mode_v1($attachId){
	    	$data_tax = $this->get_data_tax();
	    	$data_updatepost = $this->get_data_updatepost();
	    	$custom_taxonomies = $this->get_data_custom_taxonomy();
			$attachTitle = get_the_title($attachId);
			
			$img_part1 = explode("@",$attachTitle)[0];
			// main info
			$product_name = explode("-",$img_part1)[0];	
			$product_name = explode("{",$product_name)[0];
			$product_sku  = !empty(explode("-",$img_part1)[1])? explode("-",$img_part1)[1] : "";
			$product_sku = explode("{",$product_sku)[0];
		    $product_price = explode(" ", explode("@",$attachTitle)[1])[0];
		    // more info
		    if(isset(explode("@",$attachTitle)[2])){
		    	$attr_title = explode("@",$attachTitle)[2];		    
		    }
		    $attr_array = array();
		    if(!empty($attr_title)){
		    	if(explode(",", $attr_title)[1]){
		    		$attr_array = array("price"=>explode(" ", explode(",", $attr_title)[1])[0]);
		    	}
		    	
				$attr_str = html_entity_decode(explode(",", $attr_title)[0]);			
				if(!empty(explode("&", $attr_str))){
					foreach (explode("&", $attr_str) as $value) {
						if(explode("=", $value)[0] and explode("=", $value)[1]){
							$attr_array['data'][explode("=", $value)[0]] = explode("=", $value)[1];
						}
						
					}
				}
		    }
	    	$found_product = post_exists( $product_name ,'','','product');

		    if (!$found_product){		    	
    			switch ($data_tax['product_type'][0]) {
    				case 'variable':
						//Create main product
						$product = new WC_Product_Variable();

						$att_var = array();
						if(!empty($attr_array['data'])){
							foreach ($attr_array['data'] as $key=> $value) {
								$attribute = new WC_Product_Attribute();
								$attribute->set_name( $key );

								$attribute->set_options( array(
								        $value
								) );
								$attribute->set_visible( 1 );
								$attribute->set_variation( 1 );
								$att_var[] = $attribute;
							}
						}

						$product->set_attributes($att_var);
						$product->set_name($product_name);
						$product->set_status('publish');						
						$product->set_sku($product_sku);
						if($data_updatepost['post_content']){
							$product->set_description(html_entity_decode($data_updatepost['post_content']));
						}
						if($data_updatepost['post_excerpt']){
							$product->set_short_description($data_updatepost['post_excerpt']);
						}
						$product->set_image_id($attachId);
						
						for ($i=1; $i <=5 ; $i++) { 
							if(in_array("rated-".$i, $data_tax['product_visibility'])){
				    			$product->set_average_rating($i);
			    			}
						}

						$product->set_category_ids($data_tax['product_cat']);
						$product->set_tag_ids($data_tax['product_tag']);
						
						$id = $product->save();
						
						//variation 
						if(!empty($attr_array['data'])){
							$variation = new WC_Product_Variation();
							if(!empty($attr_array['price'])){
								$variation->set_regular_price($attr_array['price']);
							}
							$variation->set_manage_stock(false);
							$variation->set_stock_status("instock");
							$variation->set_parent_id($id);
							$variation->set_image_id($attachId);
							$variation->set_attributes($attr_array['data']);
							$variation->save();
						}

    					break;
    				
    				default:
    					$product  = new WC_Product();
    					$data_tax = $this->get_data_tax();	    	
				    	$data_updatepost = $this->get_data_updatepost();

						$product->set_name($product_name);		    			
						$product->set_status('publish');
						if($data_updatepost['post_content']){
							$product->set_description(html_entity_decode($data_updatepost['post_content']));
						}
						if($data_updatepost['post_excerpt']){
							$product->set_short_description($data_updatepost['post_excerpt']);
						}
						$product->set_sku($product_sku);
						$product->set_price($product_price);
						$product->set_regular_price($product_price);
						$product->set_image_id($attachId);
							
						
						for ($i=1; $i <=5 ; $i++) { 
							if(in_array("rated-".$i, $data_tax['product_visibility'])){
				    			$product->set_average_rating($i);
			    			}
						}

						$product->set_category_ids($data_tax['product_cat']);
						$product->set_tag_ids($data_tax['product_tag']);
						
						$id = $product->save();					
    					break;
    			}

    			
				if(!empty($custom_taxonomies)){
					foreach ($custom_taxonomies as $key => $arr) {
						foreach ($arr as $tax_slug => $term_arr) {
							wp_set_object_terms( $id, $term_arr, $tax_slug,true );
						}						
					}
				}
				wp_set_object_terms($id,$data_tax['product_visibility'],'product_visibility');

				/*category */
				preg_match("/\{(.*)\}/", $img_part1, $cmatches);
				if($cmatches){					
				    $cat_temp = $cmatches[1];				    
				    $cat_temp = explode("}{",$cat_temp);
				    if(!empty($cat_temp) and is_array($cat_temp)){
				    	$cat_arr = [];
				    	foreach ($cat_temp as $key => $term_name) {
				    		$found_cat = get_term_by('name', $term_name, 'product_cat');	
				    		if($found_cat){
				    			$cat_arr[] = $found_cat->term_id;
				    		}else{
				    			$new_term = wp_insert_term( $term_name, "product_cat");
				    			$cat_arr[] = $new_term['term_id'];
				    		}
				    	}
				    	wp_set_object_terms($id,$cat_arr,'product_cat');
				    }
				}


				// for virtual and downloadable
				if(get_option('apaui_is_virtual')){
					$product->set_virtual(true);
				}
				if(get_option('apaui_is_downloadable')){				
					$product->set_downloadable(true);
					$imageurl = reset(wp_get_attachment_image_src( $attachId, 'full'));
			        $downloads = [['url'=>$imageurl ] ];
			        $files = array();
			        foreach ( $downloads as $key => $file ) {
						if ( isset( $file['url'] ) ) {
							$file['file'] = $file['url'];
						}

						if ( empty( $file['file'] ) ) {
							continue;
						}

						$download = new WC_Product_Download();
						$download->set_id( ! empty( $file['id'] ) ? $file['id'] : wp_generate_uuid4() );
						$download->set_name( $file['name'] ? $file['name'] : wc_get_filename_from_url( $file['file'] ) );
						$download->set_file( apply_filters( 'woocommerce_file_download_path', $file['file'], $product, $key ) );
						$files[]  = $download;
					}
					$product->set_downloads( $files );
				}



				$product->save();


		    }else {
		    	return $attachId;
		    	// update gallery
		    	$product = wc_get_product($found_product);
		    	$gallery = $product->get_gallery_image_ids();

		    	if (!in_array($attachId, $gallery)) {
		    		$gallery[] = $attachId;
		    	}
		    	$product->set_gallery_image_ids($gallery);
		    	$product->save();
		    	$attr_array2 = array("price"=>explode(" ", explode(",", $attr_title)[1])[0]);
		    	$attr_str = html_entity_decode(explode(",", $attr_title)[0]);
				if(!empty(explode("&", $attr_str))){
					foreach (explode("&", $attr_str) as $value) {
						$attr_array2['data']["attribute_".explode("=", $value)[0]] = explode("=", $value)[1];
					}
				}	
		    	switch ($data_tax['product_type'][0]) {
		    		case 'variable':
		    			$children = $product->get_children();

		    			// check variation product 
				    	if(!empty($children)){				    		
				    		// check attr ( not variation)			

							$attr_array_merger = $attr_array;
							foreach ($attr_array_merger['data'] as $key => $value) {
								$attr_array_merger['data'][$key] = array($value);
							}

							foreach ($children as $variation_id) {
				    			$variation = new WC_Product_Variation($variation_id);
				    			$var_attr = $variation->get_variation_attributes();
				    			
				    			foreach ($variation->get_variation_attributes() as $key => $value) {
				    				$new_key = explode("attribute_",$key)[1];
				    				if(in_array($new_key, array_keys($attr_array_merger['data'])))	{
			    						$attr_array_merger['data'][$new_key][] = $value;
				    				}else{
				    					$attr_array_merger['data'][$new_key] = array($value);
				    				}
				    				
				    			}
				    		}
				    		foreach ($attr_array_merger['data'] as $key => $value) {
				    			$attr_array_merger['data'][$key] = array_unique($value);

				    		}
				    		switch (empty($attr_array_merger)) {
				    			case true:
				    				if(!empty($attr_array2['price'])){
										$variation->set_regular_price($attr_array2['price']);
									}
									$variation->set_image_id($attachId);									
									$variation->save();
				    				break;
				    			
				    			default:
				    				
				    				$att_var = array();	
									if(!empty($attr_array_merger['data'])){
										foreach ($attr_array_merger['data'] as $key=> $value) {
											$attribute = new WC_Product_Attribute();
											$attribute->set_name( $key );
											$attribute->set_options( $value);
											$attribute->set_visible( 1 );
											$attribute->set_variation( 1 );
											$att_var[] = $attribute;
										}
									}
									$product->set_attributes($att_var);
									$product->save();

									if(!empty($attr_array_merger['data'])){
										$variation = new WC_Product_Variation();
										if(!empty($attr_array_merger['price'])){
											$variation->set_regular_price($attr_array_merger['price']);
										}
										$variation->set_manage_stock(false);
										$variation->set_stock_status("instock");
										$variation->set_parent_id($found_product);
										$variation->set_image_id($attachId);
										$variation->set_attributes($attr_array['data']);
										$variation->save();
									}									

				    				break;
				    		}
				    	}else{
				    		// create new attribute
				    		$att_var = array();	
							if(!empty($attr_array['data'])){
								foreach ($attr_array['data'] as $key=> $value) {
									$attribute = new WC_Product_Attribute();
									$attribute->set_name( $key );
									$attribute->set_options(array(0=>$value));
									$attribute->set_visible( 1 );
									$attribute->set_variation( 1 );
									$att_var[] = $attribute;
								}
							}

							$product->set_attributes($att_var);
							$product->save();

							if(!empty($attr_array['data'])){
								$variation = new WC_Product_Variation();
								if(!empty($attr_array['price'])){
									$variation->set_regular_price($attr_array['price']);
								}
								$variation->set_manage_stock(false);
								$variation->set_stock_status("instock");
								$variation->set_parent_id($found_product);
								$variation->set_image_id($attachId);
								$variation->set_attributes($attr_array['data']);
								$variation->save();
							}
							$variation->save();

				    	}

		    			break;
	    			default:
	    				break;
		    	}
		    	// update file downloadable
		    	if(get_option('apaui_is_downloadable')){				
					$product->set_downloadable(true);
					$files = (array) $product->get_downloads();
					$imageurl = reset(wp_get_attachment_image_src( $attachId, 'full'));
			        $downloads = [['url'=>$imageurl ] ];

			        foreach ( $downloads as $key => $file ) {
						if ( isset( $file['url'] ) ) {
							$file['file'] = $file['url'];
						}

						if ( empty( $file['file'] ) ) {
							continue;
						}

						$download = new WC_Product_Download();
						$id = ! empty( $file['id'] ) ? $file['id'] : wp_generate_uuid4();
						$download->set_id( $id );
						$download->set_name( $file['name'] ? $file['name'] : wc_get_filename_from_url( $file['file'] ) );
						$download->set_file( apply_filters( 'woocommerce_file_download_path', $file['file'], $product, $key ) );
						$files[$id]  = $download;
					}
					$product->set_downloads( $files );
					$product->save();
				}

		    }
			return $attachId;
		}

		/*
			Request for 
			title][slug][sku][color=black,white,red&size=10-15 Years,15-20 Years, 20-25 Years][price
		*/
		function mode_v2($attachId){

	    	$data_tax = $this->get_data_tax();
	    	$data_updatepost = $this->get_data_updatepost();
	    	$custom_taxonomies = $this->get_data_custom_taxonomy();
			$attachTitle = get_the_title($attachId);
			$attachTitle = html_entity_decode($attachTitle);


			$seperator = '][';
			if(isset($updatepost_arr['seperator']) and $updatepost_arr['seperator']){
				$seperator = $updatepost_arr['seperator'];
			}
			$product_name = explode($seperator,$attachTitle)[0];
			$product_slug = explode($seperator,$attachTitle)[1];
			$product_sku = explode($seperator,$attachTitle)[2];
			$product_attrs = explode($seperator,$attachTitle)[3];
			$product_attrs = $this->get_attrs_variations($product_attrs);
			$product_price = explode($seperator,$attachTitle)[4];

			$product_id = wc_get_product_id_by_sku( $product_sku );
			$product = wc_get_product($product_id);

			if(!$product){
				$product = new WC_Product_Variable();
			}
			$product->set_name($product_name);
			$product->set_status('publish');
			$product->set_sku($product_sku);
			$product->set_image_id($attachId);

			$this->set_data($product,$data_updatepost);			
			$this->set_category($product,$data_tax);
			$this->set_rate($product,$data_tax);
			$this->set_tags($product,$data_tax);
			$this->set_visiblity($product,$data_tax);
			$this->set_global_attrs($product,$custom_taxonomies);
			$product->save();
			$this->set_attributes_custom($product,$product_attrs);
			$this->sort_variations($product,$attachId,$product_price);
			$this->create_variations_data($product,$attachId,$product_price);
			$product->save();
			
			return $attachId;
		}
		function get_attrs_variations($strings){
			//color=black,white,red&size=10-15 Years,15-20 Years, 20-25 Years
			if(!$strings){return []; }
			$attr_arr = explode("&",$strings);
			if(empty($attr_arr)) return [];
			$return = [];
			foreach ($attr_arr as $key => $value) {
				$attr_type = explode("=",$value);
				if(isset($attr_type[0]) and $attr_type[0] and isset($attr_type[1]) and $attr_type[1]){
					$att_values = explode(",",$attr_type[1]);
					$return[trim($attr_type[0])] = array_map('trim', $att_values);
				}
			}
			return $return;
		}
		function set_attributes_custom($product,$product_attrs){
			if(!empty($product_attrs)){
				foreach ($product_attrs as $taxonomy_slug=> $value) {
					foreach ($value as $term_name) {
						$this->maybe_set_attribute($product, $taxonomy_slug, $term_name );
					}
				}
			}
		}
		function sort_variations($product,$attachId,$product_price = 0){	
	        $data_store = $product->get_data_store();
	        $data_store->create_all_product_variations( $product );
	        $data_store->sort_all_product_variations( $product->get_id() );
			
		}
		function create_variations_data($product,$attachId,$product_price = 0){
			// set children data for first load
			$data_store = $product->get_data_store();
			$children = $data_store->read_children( $product );
			$product->set_children( $children['all'] );
			$product->set_visible_children( $children['visible'] );

	        // re-set prices
			$current_products = $product->get_children();
			if(!empty($current_products)){
				foreach ($current_products as $variation_id) {
					$variation = wc_get_product($variation_id);
					$variation->set_regular_price($product_price);
			        $variation->set_sale_price('');
			        $this->set_virtual($variation);
			        $this->set_downloadable($variation,$attachId);
			        $variation->save();
				}
			}
		}
		function set_data($product,$data_updatepost){
			if($data_updatepost['post_content']){
				$product->set_description(html_entity_decode($data_updatepost['post_content']));
			}
			if($data_updatepost['post_excerpt']){
				$product->set_short_description($data_updatepost['post_excerpt']);
			}			
		}
		function set_global_attrs($product,$custom_taxonomies){
			if(!empty($custom_taxonomies)){
				foreach ($custom_taxonomies as $key => $arr) {
					if(!empty($arr)){
						foreach ($arr as $tax_slug => $term_arr) {
							$tax_slug = str_replace('pa_', '', $tax_slug);
							if(!empty($term_arr)){
								foreach ($term_arr as $term_name) {
									$this->maybe_set_attribute($product, $tax_slug, $term_name,0 );
								}
							}
						}
					}
				}
			}
		}
		function maybe_set_attribute($product, $taxonomy, $term_name,$set_variation = 1 ){
 			$a = $this->maybe_create_attribute($taxonomy, $taxonomy);
 			$taxonomy = $a->slug;
			$b = $this->maybe_create_attribute_term($term_name, sanitize_title($term_name), str_replace("pa_","",$taxonomy));
			$term_id = $b->term_id;
			$attributes = (array) $product->get_attributes();
			// 1. If the product attribute is set for the product
			if( array_key_exists( $taxonomy, $attributes ) ) {
			    foreach( $attributes as $key => $attribute ){
			        if( $key == $taxonomy ){
			            $options = (array) $attribute->get_options();
			            $options[] = $term_id;
			            $attribute->set_options($options);
			            $attribute->set_visible( 1 );
			            $attribute->set_variation( $set_variation );
			            $attributes[$key] = $attribute;
			            break;
			        }
			    }
			    $product->set_attributes( $attributes );
			}
			// 2. The product attribute is not set for the product
			else {
			    $attribute = new WC_Product_Attribute();
			    $attribute->set_id( sizeof( $attributes) + 1 );
			    $attribute->set_name( $taxonomy );
			    $attribute->set_options( array( $term_id ) );
			    $attribute->set_position( sizeof( $attributes) + 1 );
			    $attribute->set_visible( 1 );
			    $attribute->set_variation( $set_variation );
			    $attributes[] = $attribute;
			    $product->set_attributes( $attributes );
			}
			if( ! has_term( $term_name, $taxonomy, $product->get_id() )){
			    wp_set_object_terms($product->get_id(), sanitize_title($term_name), $taxonomy, true );
			}
		}
		// https://stackoverflow.com/questions/58110425/created-woocommerce-product-attribute-programmatically-and-added-terms-to-them-b
		function maybe_create_attribute(string $attributeName, string $attributeSlug): ?\stdClass {
		    delete_transient('wc_attribute_taxonomies');
		    \WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
		    $attributeLabels = wp_list_pluck(wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name');
		    $attributeWCName = array_search($attributeSlug, $attributeLabels, TRUE);

		    if (! $attributeWCName) {
		        $attributeWCName = wc_sanitize_taxonomy_name($attributeSlug);
		    }

		    $attributeId = wc_attribute_taxonomy_id_by_name($attributeWCName);
		    if (! $attributeId) {
		        $taxonomyName = wc_attribute_taxonomy_name($attributeWCName);
		        unregister_taxonomy($taxonomyName);
		        $attributeId = wc_create_attribute(array(
		            'name' => ucfirst($attributeName),
		            'slug' => $attributeSlug,
		            'type' => 'select',
		            'order_by' => 'menu_order',
		            'has_archives' => 0,
		        ));

		        register_taxonomy($taxonomyName, apply_filters('woocommerce_taxonomy_objects_' . $taxonomyName, array(
		            'product'
		        )), apply_filters('woocommerce_taxonomy_args_' . $taxonomyName, array(
		            'labels' => array(
		                'name' => ucfirst($attributeSlug),
		            ),
		            'hierarchical' => FALSE,
		            'show_ui' => FALSE,
		            'query_var' => TRUE,
		            'rewrite' => FALSE,
		        )));
		    }

		    return wc_get_attribute($attributeId);
		}
		function maybe_create_attribute_term(string $termName, string $termSlug, string $taxonomy, int $order = 0): ?\WP_Term {
		    $taxonomy = wc_attribute_taxonomy_name($taxonomy);
		    if (! $term = get_term_by('slug', $termSlug, $taxonomy)) {
		        $term = wp_insert_term(ucfirst($termName), $taxonomy, array(
		            'slug' => $termSlug,
		        ));
		        $term = get_term_by('id', $term['term_id'], $taxonomy);
		        if ($term) {
		            update_term_meta($term->term_id, 'order', $order);
		        }
		    }

		    return $term;
		}
		function set_visiblity($product,$data_tax){
			if(isset($data_tax['product_visibility'])){
				wp_set_object_terms($product->get_id(),$data_tax['product_visibility'],'product_visibility');
			}
		}
		function set_category($product,$data_tax){

			$product->set_category_ids($data_tax['product_cat']);
		}
		function set_rate($product,$data_tax){
			for ($i=1; $i <=5 ; $i++) { 
				if(in_array("rated-".$i, $data_tax['product_visibility'])){
	    			$product->set_average_rating($i);
    			}
			}
		}		
		function set_tags($product,$data_tax){

			$product->set_tag_ids($data_tax['product_tag']);
		}
		function set_virtual($product){
			if(get_option('apaui_is_virtual')){
				$product->set_virtual(true);
			}
		}
		function set_downloadable($product,$attachId){
			if(get_option('apaui_is_downloadable')){
				$product->set_downloadable(true);
				$imageurl = get_post($attachId);
				$pd_object = new WC_Product_Download();
				$pd_object->set_id( md5( $imageurl->guid ) );
				$pd_object->set_name( $imageurl->post_name );
				$pd_object->set_file( $imageurl->guid );
				$downloads = $product->get_downloads();
				$downloads[md5( $imageurl->guid )] = $pd_object;
				$product->set_downloads($downloads);
				
			}
		}
		function enqueue_scripts_and_styles() {
		       	wp_register_style( 'apaui-css', plugins_url( '/css/main.css', __FILE__ ));
				wp_enqueue_style( 'apaui-css' );
				wp_register_script('apaui-js', plugins_url( '/js/main.js', __FILE__ ));
		    	wp_enqueue_script( 'apaui-js' );
		}

		function register_mysettings() {	        
			register_setting( 'apaui-settings-group', 'apaui_enable' );
			register_setting( 'apaui-settings-group', 'apaui_mode' );
	        register_setting( 'apaui-settings-group', 'apaui_tax' );
	        register_setting( 'apaui-settings-group', 'apaui_updatepost' );
	        register_setting( 'apaui-settings-group', 'apaui_is_virtual' );
	        register_setting( 'apaui-settings-group', 'apaui_is_downloadable' );
		}
		 
		function apaui_create_menu() {
	        add_menu_page('Auto Product After Upload Image', 'APAUI Creator', 'administrator', __FILE__, array($this,'apaui_settings_page'),null, 100);
	        add_action( 'admin_init', array($this,'register_mysettings' ));
		}

		function apaui_settings_page() {
			?>
			<div class="wrap">
			<h2>Create Multiple Woocommerce Products Settings.</h2>			
			<mark> <?php if(!class_exists( 'WooCommerce' ) ){
            	$this->$notice = "Install <a target=_blank href='".get_admin_url()."plugin-install.php?s=woocommerce&tab=search&type=term"."'>Woocommerce</a> first!";
			} ?> </mark>
			<?php if( isset($_GET['settings-updated']) ) { ?>
			    <div id="message" class="updated">
			        <p><strong><?php _e('Settings saved.') ?></strong></p>
			    </div>
			<?php }?>			
			<form method="post" action="options.php" id="apauiForm">
			    <?php settings_fields( 'apaui-settings-group' ); ?>			    
			    <table class="form-table">

			    	<tr valign="top">
				        <th scope="row">Enabled</th>
				        <td>				        	
				        	<input type="checkbox" name="apaui_enable" id="apaui_enable" value="enabled" <?php echo (get_option("apaui_enable")=="enabled")? "checked": "";  ?>>				        	
				        </td>
			        </tr>
			        <tr valign="top">
				        <th scope="row">Import Mode</th>
				        <td>
				        	<input type="radio" name="apaui_mode" value="apaui_mode_1" <?php echo (get_option("apaui_mode")=="apaui_mode_1")? "checked": "";  ?>> Version 1: <code>product name -sku{your cat 1}{your cat 2} @10@color=red&weight=3kg,22</code>	</br>
				        	<input type="radio" name="apaui_mode" value="apaui_mode_2" <?php echo (get_option("apaui_mode")=="apaui_mode_2")? "checked": "";  ?>> Version 2: Only for variations<code>title][slug][sku][color=black,white,red&size=10-15 Years,15-20 Years, 20-25 Years][price</code>  </br>	        	
				        </td>
			        </tr>

			    	<tr valign="top">
			        	<th scope="row">Wordpress default</th>
			        	<td>
			        		<input type="hidden" value="<?php echo get_option('apaui_updatepost'); ?>" name="apaui_updatepost" id="apaui_updatepost"/>
			        		--------------------------
			        	</td>
			        </tr>
			        <tr valign="top" class="apaui_updatepost">
			        	<th scope="row">The Content</th>
			        	<td>
			        		<?php 
			        		$updatepost_arr = $this->get_data_updatepost();
		        		 	?>
			        		<textarea cols="120" rows="3" name="post_content" class="input"><?php if(!empty($updatepost_arr['post_content'])) echo $updatepost_arr['post_content'] ; ?></textarea>
			        		<p><em>You can put html code</em></p>
			        	</td>
			        </tr>
			        <tr valign="top" class="apaui_updatepost">
			        	<th scope="row">The Excerpt</th>
			        	<td>
			        		<input type="text" name="post_excerpt" class="input" value='<?php if(!empty($updatepost_arr['post_excerpt'])) echo $updatepost_arr['post_excerpt'] ; ?>'>
			        	</td>
			        </tr>			        
			        <tr valign="top">
			        	<th scope="row">Taxonomies</th>
			        	<td>
			        		<input type="hidden" value="<?php echo get_option('apaui_tax'); ?>" name="apaui_tax" id="apaui_tax"/>
			        		--------------------------
			        	</td>
			        </tr>			        
			        <?php 	
				        $apaui_tax = get_option('apaui_tax');
				        $apaui_tax = explode(",", $apaui_tax);		        	
				        $taxonomy_objects = get_object_taxonomies( 'product', 'objects' ); // 
		               	$out = "";
		               	foreach ( $taxonomy_objects as $taxonomy_slug => $taxonomy ){
		                   	$terms = get_terms( $taxonomy_slug, 'hide_empty=0' );		                   	
		                   	if ( !empty( $terms ) ) {
		                   		$out.='<tr valign="top" class="apaui_tax_class" >';
		                   		$out.='<th scope="row" id="'.$taxonomy->name.'">'.$taxonomy->label.'</th><td>';		                      	
		                      	foreach ( $terms as $term ) {

		                      		switch ($taxonomy->name) {
		                      			case 'product_tag':
		                      				$checked = in_array($taxonomy->name.'|'.$term->term_id,$apaui_tax )? "checked": "" ;
		                      				$value = $term->term_id;
		                      				$type = "checkbox";
		                      				break;
		                      			case 'product_cat':
		                      				$checked = in_array($taxonomy->name.'|'.$term->term_id,$apaui_tax )? "checked": "" ;
		                      				$value = $term->term_id;
		                      				$type = "checkbox";
		                      				break;
		                      			case 'product_type':
		                      				$checked = in_array($taxonomy->name.'|'.$term->slug,$apaui_tax )? "checked": "" ;
		                      				$value = $term->slug;
		                      				$type = "radio";
		                      				$name = "apaui_product_type";
		                      				break;
		                      			case 'product_visibility':
		                      				$checked = in_array($taxonomy->name.'|'.$term->slug,$apaui_tax )? "checked": "" ;
		                      				$value = $term->slug;
		                      				$type = "checkbox";
		                      				break;
		                      			default:
		                      				// for custom taxonomy
		                      				$checked = in_array($taxonomy->name.'|'.$term->slug,$apaui_tax )? "checked": "" ;
		                      				$value = $term->slug;
		                      				$type = "checkbox";
		                      				$name = 'apaui_'.$term->slug;
		                      				break;
		                      		}
		                        	$out.='<input name="'.$name.'" type="'.$type.'" value="'.$taxonomy->name.'|'.$value.'" id="apaui_'.$term->slug.'" '.$checked.' />';
		                        	$out.='<label for="apaui_'.$term->slug.'">'.$term->name.'</label>   ';		                        	
		                      	}		                      	
		                      	$out.="</td></tr>";
		                    }

		               	}
		               	echo $out;
			         ?>
			        <tr valign="top">
			        	<th scope="row">Woocommerce other option</th>
			        	<td>
			        		<input type="hidden" value="<?php echo get_option('apaui_tax'); ?>" name="apaui_tax" id="apaui_tax"/>
			        		--------------------------
			        	</td>
			        </tr>	
			        <tr valign="top">
				        <th scope="row">Product data</th>
				        <td>				        	
				        	<input type="checkbox" name="apaui_is_virtual" id="apaui_is_virtual" value="enabled" <?php echo (get_option("apaui_is_virtual")=="enabled")? "checked": "";  ?>> Is virtual
				        	<input type="checkbox" name="apaui_is_downloadable" id="apaui_is_downloadable" value="enabled" <?php echo (get_option("apaui_is_downloadable")=="enabled")? "checked": "";  ?>> Is downloadable
				        </td>
			        </tr>
			        <tr valign="top">
				        <th scope="row">
				        	<h3>Mode V2 config</h3>
				        </th>
				        <td>				        	
				        	
				        </td>
			        </tr>
			        <tr valign="top" class="apaui_updatepost">
			        	<th scope="row">Your custom seperator</th>
			        	<td>
			        		<input placeholder="][" type="text" name="seperator" class="input" value='<?php if(!empty($updatepost_arr['seperator'])) echo $updatepost_arr['seperator'] ; ?>'>
			        	</td>
			        </tr>
			    </table>
			    <?php submit_button(); ?>
			</form>
			<div>
				<h3>How to use</h3>
				Have a look in screenshots <a target="_blank" href="https://wordpress.org/plugins/auto-product-after-upload-image/">https://wordpress.org/plugins/auto-product-after-upload-image/</a>
			</div>
			<div>
				<h3>Need more functions?</h3>
				Contact me in Facebook: <a href=https://facebook.com/timquen2014>https://facebook.com/timquen2014</a> or inbox me in Gmail: <a href=mailto:quylv.dsth@gmail.com>quylv.dsth@gmail.com</a>
			</div>
			</div>
			<?php
		}

	}
}
function apauicreat_loader() {
    global $apaui;
    $apaui = new APAUI();
}
add_action( 'plugins_loaded', 'apauicreat_loader' );
