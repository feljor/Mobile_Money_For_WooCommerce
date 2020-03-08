<?php 
/**
 * Plugin Name: Mobile Money
 * Plugin URI:  https://mercipro.com/developpement-web/
 * Description: Acceptez les paiements Mobile Money sur votre site pour maximiser vos ventes aux utilisateurs qui n'ont pas de cartes bancaire.
 * Version:     1.1
 * Author:      MerciPro Inc
 * Author URI:  https://mercipro.com/
 * Text Domain: mobilemoneyforwoocommerce
 * License: Commercial  
	
*/

if (!defined('ABSPATH')){
	exit;
}

define('MMFWC_VER', '1.1');
if (! defined('MMFWC_PLUGIN_FILE')) {
	define('MMFWC_PLUGIN_FILE', __FILE__);
}

add_action('wp', function (){
	if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
		deactivate_plugins(plugin_basename(__FILE__));
	}
});

// Ajouter le Plugin dans le Menu WordPress

add_action( 'admin_menu', 'mobile_money_for_woocommerce_menu' );  function mobile_money_for_woocommerce_menu() {    
	$page_title = 'Mobile Money For Woocommerce';
	$menu_title = 'Mobile Money';
	$capability = 'manage_options';   
	$menu_slug  = 'wc-settings&tab=checkout&section=mobile_money_for_woocommerce';   
	$function   = 'mobile_money_for_woocommerce';   
	$icon_url   = 'dashicons-smartphone';   
	$position   = 4;    
add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position ); 
} 

// Ajouter le lien vers la Configuration et la Documentation du Plugin
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'mobile_money_for_woocommerce_action_links');
function mobile_money_for_woocommerce_action_links($links)
{
	return array_merge(
		$links,		array(
			'<a href="'.admin_url('admin.php?page=wc- 
settings&tab=checkout&section=mobile_money_for_woocommerce').'">&nbsp;Configuration</a>', 
			'<a href="https://mercipro.com/developpement-web/">&nbsp;Développeur</a>'
		)
	);
}

// Inscrire le Plugin dans la liste des Payment Gateways for Woocommerce

add_filter('woocommerce_payment_gateways', 'mobile_money_for_woocommerce_payment_gateways');
function mobile_money_for_woocommerce_payment_gateways( $gateways ){
	$gateways[] = 'mobile_money_for_woocommerce';
	return $gateways;
}

add_action('plugins_loaded', 'mobile_money_for_woocommerce_plugin_activation');
function mobile_money_for_woocommerce_plugin_activation(){
	
	class mobile_money_for_woocommerce extends WC_Payment_Gateway {

		public $MMFWC_number;

		// Enlever ces commentaires pour plusieurs numéros mobile money

//		public $MMFWC_number2;
//		public $MMFWC_number3;
		public $txt_description;
		public $number_type;
		public $order_status;
		public $instructions;
		public $domain;

		public function __construct(){

			// Domaine Principal
			$this->domain 				= 'mobile_money';

			$this->id 					= 'mobile_money_for_woocommerce';

			// Titre du Plugin pris en charge par Woocommerce
			$this->title 				= $this->get_option('title', 'Mobile Money');

			// Description prise en charge par Woocommerce
			$this->description 			= $this->get_option('description', 'Le moyen le plus simple d\'accepter les payements via Mobile Money');

			// Titre affiché sur la page Réglage de Woocommerce
			$this->method_title 		= __("Mobile Money", $this->domain);

			// Description affichée sur la page Réglage de Woocommerce
			$this->method_description 	= __("Accepter les payements via Mobile Money sur votre boutique en ligne pour maximiser vos ventes.", $this->domain );
			$this->icon 				= plugins_url('images/mobilemoney.png', __FILE__);
			$this->has_fields 			= true;

			$this->mobile_money_for_woocommerce_options_fields();
			$this->init_settings();
			
			$this->MMFWC_number = $this->get_option('MMFWC_number');

            // Enlever ces commentaires pour plusieurs numéros mobile money

//			$this->MMFWC_number2 = $this->get_option('MMFWC_number2');
//			$this->MMFWC_number3 = $this->get_option('MMFWC_number3');
			$this->number_type 	= $this->get_option('number_type');
			$this->txt_description 	= $this->get_option('txt_description');
			$this->order_status = $this->get_option('order_status');
			$this->instructions = $this->get_option('instructions');

			add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );
            add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'mobile_money_for_woocommerce_thankyou_page' ) );
            add_action( 'woocommerce_email_before_order_table', array( $this, 'mobile_money_for_woocommerce_email_instructions' ), 10, 3 );
		}


		public function mobile_money_for_woocommerce_options_fields(){
			$this->form_fields = array(
				'enabled' 	=>	array(
					'title'		=> __( 'Activer/Désactiver', $this->domain ),
					'type' 		=> 'checkbox',
					'label'		=> __( 'Mobile Money for Woocommerce', $this->domain ),
					'default'	=> 'yes'
				),
				'title' 	=> array(
					'title' 	=> __( 'Titre', $this->domain ),
					'type' 		=> 'text',
					'default'	=> __( 'Mobile Money', $this->domain )
				),
				'txt_description' => array(
					'title'		=> __( 'Description', $this->domain ),
					'type' 		=> 'textarea',
					'default'	=> __( 'Veuillez effectuer le paiement sur votre téléphone au numéro suivant.', $this->domain ),
					'desc_tip'    => true
				),
                'order_status' => array(
                    'title'       => __( 'Status de la Commande', $this->domain ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Sélectionner le Statut que vous voulez accorder à la commande avant la vérification du paiement.', $this->domain ),
                    'default'     => 'wc-on-hold',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),				
				'MMFWC_number'	=> array(
					'title'			=> 'Mobile Money',
					'description' 	=> __( 'Ajouter un numéro Mobile Money qui sera affiché à vos clients sur la page de paiement', $this->domain ),
					'type'			=> 'text',
					'desc_tip'      => true
				),

				// Enlevez le commentaire si vous avez plusieurs numéros mobile money.

                /*'MMFWC_number2'	=> array(
					'title'			=> 'Mobile Money 2',
					'description' 	=> __( 'Ajouter un numéro Orange Money qui sera affiché à vos clients sur la page de paiement', $this->domain ),
					'type'			=> 'text',
					'desc_tip'      => true
				),
				'MMFWC_number3'	=> array(
					'title'			=> 'Mobile Money 3',
					'description' 	=> __( 'Ajouter un numéro M-Pesa qui sera affiché à vos clients sur la page de paiement', $this->domain ),
					'type'			=> 'text',
					'desc_tip'      => true
				),*/
				'number_type'	=> array(
					'title'			=> __( 'Type de Compte si nécessaire.', $this->domain ),
					'type'			=> 'select',
					'class'       	=> 'wc-enhanced-select',
					'description' 	=> __( 'Sélectionner le type de compte', $this->domain ),
					'options'	=> array(
					    'Mobile'	 => __( 'Mobile', $this->domain ),
					    'Privé'	 => __( 'Privé', $this->domain ),
					    'Agent'	     => __( 'Agent', $this->domain ),
						'Entreprise'	 => __( 'Entreprise', $this->domain ),

					),
					'desc_tip'      => true
				),				
                'instructions' => array(
                    'title'       	=> __( 'Message', $this->domain ),
                    'type'        	=> 'textarea',
                    'description' 	=> __( 'Le message qui sera affiché à la page de remerciement et dans les Emails.', $this->domain ),
                    'default'     	=> __( 'Nous avons reçu votre requête, nous vérifions les informations de paiement. Cette procédure peut prendre jusqu\'à 15 minutes.', $this->domain ),
                    'desc_tip'    	=> true
                ),								
			);
		}


		public function payment_fields(){

			global $woocommerce;
			echo wpautop( wptexturize( " ".$this->txt_description." ") );
			echo wpautop( wptexturize( "Mobile Money: " .$this->MMFWC_number ) );

			// Enlever ce commentaire pour plusieurs numéros Mobile Money

//			echo wpautop( wptexturize( "Mobile Money 2: " .$this->MMFWC_number2 ) );
//			echo wpautop( wptexturize( "Mobile Money 3: " .$this->MMFWC_number3 ) );

			?>
				<p><h4>Vérification de la Transaction</h4></p>
					<p><label for="MMFWC_number"><?php _e( 'N° Expéditaire: (le numéro qui a effectuer le paiement)', $this->domain );?></label>
				</p>
				<p>
					<input type="text" name="MMFWC_number" id="MMFWC_number" placeholder="Ex: 097xxxxxx">
				</p>
				<p>
					<label for="MMFWC_transaction_id"><?php _e( 'ID Transaction: (Identifiant unique de la transaction)', $this->domain );?></label>
			    </p>
				<p>
					<input type="text" name="MMFWC_transaction_id" id="MMFWC_transaction_id" placeholder="Ex: RDT4xxxxx">
				</p>
			<?php 
		}
		

		public function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			$status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

			// Marquer le paiement en attente (nous attendons les infos de MMFWC)
			$order->update_status( $status, __( 'Paiement via Mobile Money. ', $this->domain ) );

			// Reduire le stock s'il y en a
			$order->reduce_order_stock();

			// Enlever le produit dans le panier
			$woocommerce->cart->empty_cart();

			// Rediriger vers la page de remerciement
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}	


        public function mobile_money_for_woocommerce_thankyou_page() {
		    $order_id = get_query_var('order-received');
		    $order = new WC_Order( $order_id );
		    if( $order->payment_method == $this->id ){
	            $thankyou = $this->instructions;
	            return $thankyou;		        
		    } else {
		    	return __( 'Félicitation, vous êtes fantastique. Nous avons reçu votre commande.', $this->domain );
		    }

        }


        public function mobile_money_for_woocommerce_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		    if( $order->payment_method != $this->id )
		        return;        	
            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

	}

}


/**
 * Empty field validation
 */
add_action( 'woocommerce_checkout_process', 'mobile_money_for_woocommerce_payment_process' );
function mobile_money_for_woocommerce_payment_process(){

    if($_POST['payment_method'] != 'mobile_money_for_woocommerce')
        return;

    $MMFWC_number = sanitize_text_field( $_POST['MMFWC_number'] );
    $MMFWC_transaction_id = sanitize_text_field( $_POST['MMFWC_transaction_id'] );

    if( !isset($MMFWC_number) || empty($MMFWC_number) )
        wc_add_notice( __( 'Veuillez ajouter votre numéro Mobile Money.', 'mobile_money'), 'error' );

    if( !isset($MMFWC_transaction_id) || empty($MMFWC_transaction_id) )
        wc_add_notice( __( 'Veuillez confirmer le paiement en renseignant le numéro de transaction reçu par SMS pour Mobile Money', 'Mobile_money' ), 'error' );

}

/**
 * Ajouter les informations de paiement MMFWC dans la base des données
 */
add_action( 'woocommerce_checkout_update_order_meta', 'mobile_money_for_woocommerce_additional_fields_update' );
function mobile_money_for_woocommerce_additional_fields_update( $order_id ){

    if($_POST['payment_method'] != 'mobile_money_for_woocommerce' )
        return;

    $MMFWC_number = sanitize_text_field( $_POST['MMFWC_number'] );
    $MMFWC_transaction_id = sanitize_text_field( $_POST['MMFWC_transaction_id'] );

	$number = isset($MMFWC_number) ? $MMFWC_number : '';
	$transaction = isset($MMFWC_transaction_id) ? $MMFWC_transaction_id : '';

	update_post_meta($order_id, '_MMFWC_number', $number);
	update_post_meta($order_id, '_MMFWC_transaction', $transaction);

}

/**
 * Les données à afficher sur la page de commande d'administration */
add_action('woocommerce_admin_order_data_after_billing_address', 'mobile_money_for_woocommerce_admin_order_data' );
function mobile_money_for_woocommerce_admin_order_data( $order ){
    
    if( $order->payment_method != 'mobile_money_for_woocommerce' )
        return;

	$number = (get_post_meta($order->id, '_MMFWC_number', true)) ? get_post_meta($order->id, '_MMFWC_number', true) : '';
	$transaction = (get_post_meta($order->id, '_MMFWC_transaction', true)) ? get_post_meta($order->id, '_MMFWC_transaction', true) : '';

	?>
		<table class="wp-list-table widefat fixed striped posts">
			<tbody>
				<tr>
					<th><?php _e('Numéro du Client:', 'mobile_money') ;?></th>
					<td><?php echo esc_attr( $number );?></td>
				</tr>
				<tr>
					<th><?php _e('ID de Transaction:', 'mobile_money') ;?></th>
					<td><?php echo esc_attr( $transaction );?></td>
				</tr>
			</tbody>
		</table>
	<?php 
	
}

/**
 * Les données à afficher dans la page de revision de la commande
 */
add_action('woocommerce_order_details_after_customer_details', 'mobile_money_for_woocommerce_additional_info_order_review_fields' );
function mobile_money_for_woocommerce_additional_info_order_review_fields( $order ){
    
    if( $order->payment_method != 'mobile_money_for_woocommerce' )
        return;

	$number = (get_post_meta($order->id, '_MMFWC_number', true)) ? get_post_meta($order->id, '_MMFWC_number', true) : '';
	$transaction = (get_post_meta($order->id, '_MMFWC_transaction', true)) ? get_post_meta($order->id, '_MMFWC_transaction', true) : '';

	?>
		<tr>
			<th><?php _e('Numéro du Client:', 'mobile_money');?></th>
			<td><?php echo esc_attr( $number );?></td>
		</tr>
		<tr>
			<th><?php _e('ID de Transaction:', 'mobile_money');?></th>
			<td><?php echo esc_attr( $transaction );?></td>
		</tr>
	<?php 
	
}	

/**
 * Register new admin column
 */
add_filter( 'manage_edit-shop_order_columns', 'mobile_money_for_woocommerce_admin_new_column' );
function mobile_money_for_woocommerce_admin_new_column($columns){

    $new_columns = (is_array($columns)) ? $columns : array();
    unset( $new_columns['order_actions'] );
    $new_columns['mobile_no'] = __('Numéro du Client', 'mobile_money');
    $new_columns['tran_id'] = __('ID de Transaction', 'mobile_money');

    $new_columns['order_actions'] = $columns['order_actions'];
    return $new_columns;

}

/**
 * Load data in new column
 */
add_action( 'manage_shop_order_posts_custom_column', 'mobile_money_for_woocommerce_admin_column_value', 2 );
function mobile_money_for_woocommerce_admin_column_value($column){

    global $post;

    $mobile_no = (get_post_meta($post->ID, '_MMFWC_number', true)) ? get_post_meta($post->ID, '_MMFWC_number', true) : '';
    $tran_id = (get_post_meta($post->ID, '_MMFWC_transaction', true)) ? get_post_meta($post->ID, '_MMFWC_transaction', true) : '';

    if ( $column == 'mobile_no' ) {    
        echo esc_attr( $mobile_no );
    }
    if ( $column == 'tran_id' ) {    
        echo esc_attr( $tran_id );
    }
}