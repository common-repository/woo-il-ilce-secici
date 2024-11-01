<?php
/**
 * Plugin Name: WooCommerce İl İlçe Seçici
 * Description: WooCommerce için İl/İlçe Seçici Getirme imkanı sunar.
 * Version:     1.0.0
 * Author:      TemaNinja
 * Author URI:  https://www.tema.ninja
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-il-ilce-secici
 * 
 * WC requires at least: 2.6.0
 * WC tested up to: 3.3.5
 * 
 * Credits goes to WP City Select Plugin, this pluginn is based on WP City Select.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Check if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	class WC_Il_Ilce_Sec {

		// plugin version
		const VERSION = '1.0.0';

		private $plugin_path;
		private $plugin_url;

		private $sehirler;

		public function __construct() {
			add_filter( 'woocommerce_billing_fields', array( $this, 'billing_fields' ), 10, 2 );
			add_filter( 'woocommerce_shipping_fields', array( $this, 'shipping_fields' ), 10, 2 );
			add_filter( 'woocommerce_form_field_city', array( $this, 'form_field_city' ), 20, 4 );
			add_filter( 'woocommerce_states', array( $this, 'woo_turkish_woocommerce_states'), 10, 2);
			add_filter( 'woocommerce_default_address_fields',  array( $this,'order_fields'));

			//js scripts
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		}

		public function billing_fields( $fields, $country ) {
			$fields['billing_city']['type'] = 'city';
		
		
			

			return $fields;
		}

		public function shipping_fields( $fields, $country ) {
			$fields['shipping_city']['type'] = 'city';
			

			return $fields;
		}

		public function get_sehirler( $cc = null ) {
			if ( empty( $this->sehirler ) ) {
				$this->ulke_sehirlerini_getir();
			}

			if ( ! is_null( $cc ) ) {
				return isset( $this->sehirler[ $cc ] ) ? $this->sehirler[ $cc ] : false;
			} else {
				return $this->sehirler;
			}
		}

		public function ulke_sehirlerini_getir() {
			global $sehirler;

			
			$allowed = array_merge( WC()->countries->get_allowed_countries(), WC()->countries->get_shipping_countries() );

			if ( $allowed ) {
				foreach ( $allowed as $code => $ulke ) {
					if ( ! isset( $sehirler[ $code ] ) && file_exists( $this->get_plugin_path() . '/sehirler/' . $code . '.php' ) ) {
						include( $this->get_plugin_path() . '/sehirler/' . $code . '.php' );
					}
				}
			}

			$this->sehirler = apply_filters( 'wc_il_ilce_sec', $sehirler );
		}

		public function form_field_city( $field, $key, $args, $value ) {

			if ( ( ! empty( $args['clear'] ) ) ) {
				$after = '<div class="clear"></div>';
			} else {
				$after = '';
			}

			if ( $args['required'] ) {
				$args['class'][] = 'validate-required';
				$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>';
			} else {
				$required = '';
			}

			$custom_attributes = array();

			if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
				foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
				}
			}

			if ( ! empty( $args['validate'] ) ) {
				foreach( $args['validate'] as $validate ) {
					$args['class'][] = 'validate-' . $validate;
				}
			}

			$field  = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field" data-priority="'.$args['priority'].'">';
			if ( $args['label'] ) {
				$field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required . '</label>';
			}

			$ulke_key = $key == 'billing_city' ? 'billing_country' : 'shipping_country';
			$suanki_cc  = WC()->checkout->get_value( $ulke_key );

			$ilce_key = $key == 'billing_city' ? 'billing_state' : 'shipping_state';
			$suanki_sc  = WC()->checkout->get_value( $ilce_key );

			// Get country cities
			$sehirler = $this->get_sehirler( $suanki_cc );

			if ( is_array( $sehirler ) ) {

				$field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="city_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '">
					<option value="">'. __( 'Select an option&hellip;', 'woocommerce' ) .'</option>';

				if ( $suanki_sc && $sehirler[ $suanki_sc ] ) {
					$dropdown_cities = $sehirler[ $suanki_sc ];
				} else if ( is_array( reset($sehirler) ) ) {
					$dropdown_cities = array_reduce( $sehirler, 'array_merge', array() );
					sort( $dropdown_cities );
				} else {
					$dropdown_cities = $sehirler;
				}

				foreach ( $dropdown_cities as $city_name ) {
					$field .= '<option value="' . esc_attr( $city_name ) . '" '.selected( $value, $city_name, false ) . '>' . $city_name .'</option>';
				}

				$field .= '</select>';

			} else {

				$field .= '<input type="text" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" value="' . esc_attr( $value ) . '"  placeholder="' . esc_attr( $args['placeholder'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . ' />';
			}

			
			if ( $args['description'] ) {
				$field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';
			}

			$field .= '</p>' . $after;

			return $field;
		}

		public function load_scripts() {
			if ( is_cart() || is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {

				$sehir_secim_dizini = $this->get_plugin_url() . 'js/sehir-select.js';
				
				wp_enqueue_script( 'woocommerce-il-ilce-secici', $sehir_secim_dizini, array( 'jquery', 'woocommerce' ), self::VERSION, true );

				$sehirler = json_encode( $this->get_sehirler() );
				wp_localize_script( 'woocommerce-il-ilce-secici', 'wc_il_ilce_sec_params', array(
					'sehirler' => $sehirler,
					'i18n_select_city_text' => esc_attr__( 'Select an option&hellip;', 'woocommerce' )
				) );
				wp_enqueue_script('jquery');
			}
		}

		public function get_plugin_path() {

			if ( $this->plugin_path ) {
				return $this->plugin_path;
			}

			return $this->plugin_path = plugin_dir_path( __FILE__ );
		}

		public function get_plugin_url() {

			if ( $this->plugin_url ) {
				return $this->plugin_url;
			}

			return $this->plugin_url = plugin_dir_url( __FILE__ );
		}
		
		public function order_fields($fields) {
			
			$fields['state']['priority'] = 61;
			$fields['city']['priority'] = 62;

			return $fields;
		}
		
	
		public function woo_turkish_woocommerce_states( $states ) {
	
		  $states['TR'] = array(
		    'Adana' => __( 'Adana', 'woocommerce' ),
			'Adiyaman' => __( 'Ad&#305;yaman', 'woocommerce' ),
			'Afyon' => __( 'Afyon', 'woocommerce' ),
			'Ağrı' => __( 'A&#287;r&#305;', 'woocommerce' ),
			'Amasya' => __( 'Amasya', 'woocommerce' ),
			'Ankara' => __( 'Ankara', 'woocommerce' ),
			'Antalya' => __( 'Antalya', 'woocommerce' ),
			'Artvin' => __( 'Artvin', 'woocommerce' ),
			'Aydın' => __( 'Ayd&#305;n', 'woocommerce' ),
			'Balıkesir' => __( 'Bal&#305;kesir', 'woocommerce' ),
			'Bilecik' => __( 'Bilecik', 'woocommerce' ),
			'Bingöl' => __( 'Bing&#246;l', 'woocommerce' ),
			'Bitlis' => __( 'Bitlis', 'woocommerce' ),
			'Bolu' => __( 'Bolu', 'woocommerce' ),
			'Burdur' => __( 'Burdur', 'woocommerce' ),
			'Bursa' => __( 'Bursa', 'woocommerce' ),
			'Çanakkale' => __( '&#199;anakkale', 'woocommerce' ),
			'Çankırı' => __( '&#199;ank&#305;r&#305;', 'woocommerce' ),
			'Çorum' => __( '&#199;orum', 'woocommerce' ),
			'Denizli' => __( 'Denizli', 'woocommerce' ),
			'Diyarbakır' => __( 'Diyarbak&#305;r', 'woocommerce' ),
			'Edirne' => __( 'Edirne', 'woocommerce' ),
			'Elazığ' => __( 'Elaz&#305;&#287;', 'woocommerce' ),
			'Erzincan' => __( 'Erzincan', 'woocommerce' ),
			'Erzurum' => __( 'Erzurum', 'woocommerce' ),
			'Eskişehir' => __( 'Eski&#351;ehir', 'woocommerce' ),
			'Gaziantep' => __( 'Gaziantep', 'woocommerce' ),
			'Giresun' => __( 'Giresun', 'woocommerce' ),
			'Gümüşhane' => __( 'G&#252;m&#252;&#351;hane', 'woocommerce' ),
			'Hakkari' => __( 'Hakkari', 'woocommerce' ),
			'Hatay' => __( 'Hatay', 'woocommerce' ),
			'Isparta' => __( 'Isparta', 'woocommerce' ),
			'İçel' => __( '&#304;&#231;el', 'woocommerce' ),
			'İstanbul' => __( '&#304;stanbul', 'woocommerce' ),
			'İzmir' => __( '&#304;zmir', 'woocommerce' ),
			'Kars' => __( 'Kars', 'woocommerce' ),
			'Kastamonu' => __( 'Kastamonu', 'woocommerce' ),
			'Kayseri' => __( 'Kayseri', 'woocommerce' ),
			'Kırklareli' => __( 'K&#305;rklareli', 'woocommerce' ),
			'Kırşehir' => __( 'K&#305;r&#351;ehir', 'woocommerce' ),
			'Kocaeli' => __( 'Kocaeli', 'woocommerce' ),
			'Konua' => __( 'Konya', 'woocommerce' ),
			'K&#252;tahya' => __( 'K&#252;tahya', 'woocommerce' ),
			'Malatya' => __( 'Malatya', 'woocommerce' ),
			'Manisa' => __( 'Manisa', 'woocommerce' ),
			'Kahramanmaraş' => __( 'Kahramanmara&#351;', 'woocommerce' ),
			'Mardin' => __( 'Mardin', 'woocommerce' ),
			'Muğla' => __( 'Mu&#287;la', 'woocommerce' ),
			'Mus' => __( 'Mu&#351;', 'woocommerce' ),
			'Nevşehir' => __( 'Nev&#351;ehir', 'woocommerce' ),
			'Niğde' => __( 'Ni&#287;de', 'woocommerce' ),
			'Ordu' => __( 'Ordu', 'woocommerce' ),
			'Rize' => __( 'Rize', 'woocommerce' ),
			'Sakarya' => __( 'Sakarya', 'woocommerce' ),
			'Samsun' => __( 'Samsun', 'woocommerce' ),
			'Siirt' => __( 'Siirt', 'woocommerce' ),
			'Sinop' => __( 'Sinop', 'woocommerce' ),
			'Sivas' => __( 'Sivas', 'woocommerce' ),
			'Tekirdağ' => __( 'Tekirda&#287;', 'woocommerce' ),
			'Tokat' => __( 'Tokat', 'woocommerce' ),
			'Trabzon' => __( 'Trabzon', 'woocommerce' ),
			'Tunceli' => __( 'Tunceli', 'woocommerce' ),
			'Şanlıurfa' => __( '&#350;anl&#305;urfa', 'woocommerce' ),
			'Uşak' => __( 'U&#351;ak', 'woocommerce' ),
			'Van' => __( 'Van', 'woocommerce' ),
			'Yozgat' => __( 'Yozgat', 'woocommerce' ),
			'Zonguldak' => __( 'Zonguldak', 'woocommerce' ),
			'Aksaray' => __( 'Aksaray', 'woocommerce' ),
			'Bayburt' => __( 'Bayburt', 'woocommerce' ),
			'Karaman' => __( 'Karaman', 'woocommerce' ),
			'Kırıkkale' => __( 'K&#305;r&#305;kkale', 'woocommerce' ),
			'Batman' => __( 'Batman', 'woocommerce' ),
			'Şırnak' => __( '&#350;&#305;rnak', 'woocommerce' ),
			'Bartın' => __( 'Bart&#305;n', 'woocommerce' ),
			'Ardahan' => __( 'Ardahan', 'woocommerce' ),
			'Iğdır' => __( 'I&#287;d&#305;r', 'woocommerce' ),
			'Yalova' => __( 'Yalova', 'woocommerce' ),
			'Karabük' => __( 'Karab&#252;k', 'woocommerce' ),
			'Kilis' => __( 'Kilis', 'woocommerce' ),
			'Osmaniye' => __( 'Osmaniye', 'woocommerce' ),
			'Düzce' => __( 'D&#252;zce', 'woocommerce' ),
		  );
		
		  return $states;
		}
	}

	$GLOBALS['wc_il_ilce_sec'] = new WC_Il_Ilce_Sec();
}